<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PayrollSim extends CI_Controller {

    private static $PRAY_KEYS = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];
    private static $PRAY_LABEL = ['subuh'=>'Subuh','dzuhur'=>'Dzuhur','ashar'=>'Ashar','maghrib'=>'Maghrib','isha'=>'Isya','friday'=>'Jumat'];
    private static $NO_SC     = ['-', 'NO-SC', 'NO SCHEDULE'];

    // id master insentif per komisi otomatis (branch SDR=1 & GBR=2) => [key, canonical]
    private static $AUTO_COMM = [
        12 => ['disiplin',  'Komisi Disiplin Kehadiran'], 18 => ['disiplin',  'Komisi Disiplin Kehadiran'],
        28 => ['transport', 'Komisi Transport'],          27 => ['transport', 'Komisi Transport'],
        5  => ['beras',     'Komisi Beras'],               24 => ['beras',     'Komisi Beras'],
        29 => ['soskes',    'Komisi Soskes'],              33 => ['soskes',    'Komisi Soskes'],
        9  => ['sholat',    'Komisi Sholat'],              21 => ['sholat',    'Komisi Sholat'],
    ];

    // Cache instance-level dipakai _calc() saat dipanggil berulang dalam satu request
    // (mis. dari apply_auto_to_payroll() yang loop semua karyawan cabang) — hindari
    // N+1: master insentif sama utk semua karyawan 1 cabang, dan payroll_insentif
    // existing bisa di-pre-warm sekaligus lewat 1 query alih-alih per karyawan.
    private $ins_master_cache = [];
    private $pi_batch_cache = null;
    // Batch-cache lain, di-pre-warm oleh apply_auto_to_payroll() via _batch_prewarm().
    // null = belum di-pre-warm → _calc() jatuh ke query per-karyawan seperti biasa
    // (dipertahankan supaya salary() single-employee tetap jalan tanpa perlu prewarm).
    private $emp_batch_cache = null;
    private $adds_batch_cache = null;
    private $atts_batch_cache = null;
    private $leaves_batch_cache = null;
    private $dbl_dates_cache = [];

    public function __construct() {
        parent::__construct();
        if (!is_cli() && !$this->ion_auth->logged_in()) {
            $this->_json(['error' => 'Unauthorized'], 401); exit;
        }
    }

    private function _json($data, $code = 200) {
        $this->output
            ->set_status_header($code)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    private function _no_sc($code) {
        return in_array(strtoupper(trim((string)$code)), self::$NO_SC);
    }

    private function _period($month, $year) {
        $pm = $month == 1 ? 12 : $month - 1;
        $py = $month == 1 ? $year - 1 : $year;
        $from = sprintf('%d-%02d-26', $py, $pm);
        $to   = sprintf('%d-%02d-25', $year, $month);
        $list = []; $cur = new DateTime($from); $end = new DateTime($to);
        while ($cur <= $end) { $list[] = $cur->format('Y-m-d'); $cur->modify('+1 day'); }
        return compact('from', 'to', 'list');
    }

    private function _tiered($lat, $base, $mult, $thr, $max) {
        if ($lat <= 0 || $base <= 0) return 0;
        $f = $base;
        if ($thr > 0 && $lat > $thr) $f += ceil(($lat - $thr) / $thr) * $mult;
        return $max > 0 ? min($f, $max) : $f;
    }

    // GET /payroll_sim/employees?q=
    public function employees() {
        $q = trim((string)$this->input->get('q'));
        if (strlen($q) < 2) return $this->_json([]);
        $like = '%' . $q . '%';
        $rows = $this->db->query("
            SELECT u.id, u.first_name, u.last_name, u.employee_code,
                   p.position_name, b.branch_name, u.salary
            FROM users u
            JOIN position p ON p.id = u.position_id
            JOIN branch b ON b.id = p.branch_id
            WHERE u.active = 1
              AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.employee_code LIKE ?)
            ORDER BY u.first_name LIMIT 20
        ", [$like, $like, $like])->result_array();
        $this->_json(array_map(fn($r) => [
            'id' => $r['id'], 'name' => trim($r['first_name'].' '.($r['last_name']??'')),
            'code' => $r['employee_code'], 'position' => $r['position_name'],
            'branch' => $r['branch_name'], 'salary' => $r['salary'],
        ], $rows));
    }

    // GET /payroll_sim/branches
    public function branches() {
        $this->_json($this->db->query(
            "SELECT id, branch_name FROM branch WHERE is_active=1 ORDER BY branch_name"
        )->result_array());
    }

    // GET /payroll_sim/insentif?branch_id=
    public function insentif() {
        $bid = $this->input->get('branch_id');
        $sql = "SELECT i.id, i.insentif_name AS name, i.formula, i.nominal, b.branch_name
                FROM insentif i JOIN branch b ON b.id = i.branch_id
                WHERE i.is_active = '1'" . ($bid ? ' AND i.branch_id = ?' : '') .
               " ORDER BY b.branch_name, i.insentif_name";
        $this->_json($this->db->query($sql, $bid ? [$bid] : [])->result_array());
    }

    // GET /payroll_sim/deduction?branch_id=
    public function deduction() {
        $bid = $this->input->get('branch_id');
        $sql = "SELECT d.id, d.deduction_name AS name, b.branch_name
                FROM deduction d JOIN branch b ON b.id = d.branch_id
                WHERE d.is_active = '1'" . ($bid ? ' AND d.branch_id = ?' : '') .
               " ORDER BY b.branch_name, d.deduction_name";
        $this->_json($this->db->query($sql, $bid ? [$bid] : [])->result_array());
    }

    // POST /payroll_sim/salary
    public function salary() {
        if ($this->input->method() !== 'post') return $this->_json(['error' => 'POST required'], 405);
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $emp_id   = (int)($body['employeeId'] ?? 0);
        $month    = (int)($body['month'] ?? 0);
        $year     = (int)($body['year'] ?? 0);
        $custom   = $body['customItems'] ?? [];
        if (!$emp_id || !$month || !$year)
            return $this->_json(['error' => 'employeeId, month, year required'], 400);
        try { $this->_json($this->_calc($emp_id, $month, $year, $custom)); }
        catch (Exception $e) { $this->_json(['error' => $e->getMessage()], 500); }
    }

    // Tulis komisi otomatis (5 jenis) ke payroll_insentif untuk seluruh karyawan
    // di branch & periode. Auto = sumber kebenaran → row yang sudah ada di-update.
    // Dipanggil dari Payroll::generate sebelum payroll_detail dibuat.
    // $skip_manual: kalau true, row payroll_insentif yang is_manual=1 (pernah
    // di-edit sengaja lewat save_insentif/importer) TIDAK ditimpa. Dipakai oleh
    // Lock Gaji generate() supaya tidak diam-diam menghapus koreksi manual.
    // recalc_auto_insentif() (tombol "Hitung Ulang") pakai false -- itu memang
    // force-overwrite by design, admin klik sadar.
    // Return: jumlah baris payroll_insentif yang di-tulis/update.
    public function apply_auto_to_payroll($branch_id, $month, $year, $skip_manual = false) {
        $branch_id = (int)$branch_id;
        $month     = (int)$month;
        $year      = (int)$year;
        $auto_ids  = array_keys(self::$AUTO_COMM);

        // Master insentif AUTO yang aktif di branch ini
        $master = $this->db->select('id')
                           ->from('insentif')
                           ->where('branch_id', $branch_id)
                           ->where('is_active', '1')
                           ->where_in('id', $auto_ids)
                           ->get()->result_array();
        if (empty($master)) return 0;
        $valid_ids = array_map('intval', array_column($master, 'id'));

        // Karyawan aktif branch yang sudah join sebelum/akhir periode
        // (paralel dgn Presence_model::_get_employee → users.active=1 + ber-shift-cluster)
        $period = $this->_period($month, $year);
        $emps = $this->db->distinct()->select('users.id')
                         ->from('users')
                         ->join('position', 'position.id = users.position_id')
                         ->join('users_shift_cluster', 'users_shift_cluster.user_id = users.id')
                         ->where('position.branch_id', $branch_id)
                         ->where('users.active', '1')
                         ->where('DATE(users.join_date) <=', $period['to'])
                         ->get()->result_array();
        if (empty($emps)) return 0;

        $now = date('Y-m-d H:i:s');
        $eids = array_map(fn($e) => (int)$e['id'], $emps);

        // Pre-warm employee/schedule/presence/leave cache dalam sedikit query
        // (dulu ~4-5 query per karyawan di _calc()/_auto_commissions() — akar
        // penyebab Lock Gaji timeout 30s PHP di server utk cabang banyak karyawan).
        $this->_batch_prewarm($eids, $period);

        // Pre-warm _calc()'s pi_batch_cache + siapkan peta existing (user,insentif_id)
        // dari SATU query — hindari N+1 (dulu 1 query cek-ada + 1 insert/update per
        // pasangan karyawan×komisi, bisa ratusan-ribuan round-trip berurutan tiap
        // Lock Gaji, sampai kena batas max_execution_time PHP di server).
        $this->pi_batch_cache = [];
        foreach ($eids as $eid) {
            $this->pi_batch_cache[$branch_id.'-'.$eid.'-'.$month.'-'.$year] = [];
        }
        $existing_rows = $this->db->select('*')
            ->from('payroll_insentif')
            ->where_in('user_id', $eids)
            ->where('insentif_month', $month)
            ->where('insentif_year', $year)
            ->get()->result_array();
        $existing_by_key = [];
        foreach ($existing_rows as $r) {
            $this->pi_batch_cache[$branch_id.'-'.$r['user_id'].'-'.$month.'-'.$year][] = $r;
            $existing_by_key[$r['user_id'].'-'.$r['insentif_id']] = [
                'id' => (int)$r['id'],
                'is_manual' => !empty($r['is_manual']),
            ];
        }

        $count = 0;
        $to_insert = [];
        $to_update = [];
        foreach ($emps as $e) {
            $eid = (int)$e['id'];
            try { $r = $this->_calc($eid, $month, $year, []); }
            catch (Exception $ex) { continue; }
            $by_id = [];
            foreach ($r['income']['insentifList'] as $ins) {
                $by_id[(int)$ins['id']] = (int)$ins['amount'];
            }
            foreach ($valid_ids as $mid) {
                if (!array_key_exists($mid, $by_id)) continue;
                $amt = (int)$by_id[$mid];
                $key = $eid.'-'.$mid;
                if (isset($existing_by_key[$key])) {
                    if ($skip_manual && $existing_by_key[$key]['is_manual']) { continue; }
                    $to_update[] = ['id' => $existing_by_key[$key]['id'], 'insentif_amount' => $amt];
                } else {
                    $to_insert[] = [
                        'user_id'        => $eid,
                        'insentif_id'    => $mid,
                        'insentif_month' => $month,
                        'insentif_year'  => $year,
                        'insentif_amount'=> $amt,
                        'created_at'     => $now,
                    ];
                }
                $count++;
            }
        }

        if (!empty($to_insert)) {
            foreach (array_chunk($to_insert, 500) as $chunk) {
                $this->db->insert_batch('payroll_insentif', $chunk);
            }
        }
        if (!empty($to_update)) {
            foreach (array_chunk($to_update, 500) as $chunk) {
                $this->db->update_batch('payroll_insentif', $chunk, 'id');
            }
        }
        $this->pi_batch_cache = null;
        $this->emp_batch_cache = null;
        $this->adds_batch_cache = null;
        $this->atts_batch_cache = null;
        $this->leaves_batch_cache = null;

        return $count;
    }

    // Pre-warm cache batch (employee, jadwal, presensi, izin) utk semua $eids
    // sekaligus dalam beberapa query, dipanggil sebelum loop per-karyawan di
    // apply_auto_to_payroll(). _calc() jatuh ke query per-karyawan seperti biasa
    // kalau cache ini null (dipertahankan utk salary() single-employee).
    private function _batch_prewarm($eids, $period) {
        if (empty($eids)) { return; }

        $this->emp_batch_cache = [];
        $emp_rows = $this->db->query("
            SELECT u.*, p.position_name,
                   b.id AS branch_id, b.branch_name,
                   b.is_fine_system, b.is_pray_system, b.max_overtime,
                   b.pray_late_fix_rate, b.pray_late_multiple_count,
                   b.pray_late_start_rate, b.pray_late_multiple_rate
            FROM users u
            JOIN position p ON p.id = u.position_id
            JOIN branch b ON b.id = p.branch_id
            WHERE u.id IN (" . implode(',', array_fill(0, count($eids), '?')) . ")
        ", $eids)->result_array();
        foreach ($emp_rows as $r) { $this->emp_batch_cache[(int)$r['id']] = $r; }

        $this->adds_batch_cache = [];
        foreach ($eids as $eid) { $this->adds_batch_cache[$eid] = []; }
        $adds_rows = $this->db->select('usa.user_id, usa.additional_date, usa.additional_type,
                   s.shift_code,
                   s.late_amount_start, s.late_amount_multiple_start,
                   s.late_multiple_count_start, s.late_amount_max_start,
                   s.late_amount_rest, s.late_amount_multiple_rest,
                   s.late_multiple_count_rest, s.late_amount_max_rest', false)
            ->from('users_shift_additional usa')
            ->join('shift s', 's.id = usa.shift_id')
            ->where_in('usa.user_id', $eids)
            ->where('usa.additional_date >=', $period['from'])
            ->where('usa.additional_date <=', $period['to'])
            ->get()->result_array();
        foreach ($adds_rows as $r) { $this->adds_batch_cache[(int)$r['user_id']][] = $r; }

        $this->atts_batch_cache = [];
        foreach ($eids as $eid) { $this->atts_batch_cache[$eid] = []; }
        $atts_rows = $this->db->select('*')
            ->from('presence')
            ->where_in('user_id', $eids)
            ->where('flow_date >=', $period['from'])
            ->where('flow_date <=', $period['to'])
            ->where('presence_status', 'approved')
            ->get()->result_array();
        foreach ($atts_rows as $r) { $this->atts_batch_cache[(int)$r['user_id']][] = $r; }

        $this->leaves_batch_cache = [];
        foreach ($eids as $eid) { $this->leaves_batch_cache[$eid] = []; }
        $leave_rows = $this->db->select("user_id, leave_type, leave_range,
                   (leave_proof IS NOT NULL AND leave_proof != '') AS has_proof", false)
            ->from('leave')
            ->where_in('user_id', $eids)
            ->where('leave_status', 'approve')
            ->where('leave_start <=', $period['to'])
            ->where('leave_end >=', $period['from'])
            ->get()->result_array();
        foreach ($leave_rows as $r) { $this->leaves_batch_cache[(int)$r['user_id']][] = $r; }
    }

    private function _calc($eid, $month, $year, $custom) {
        $total_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $period = $this->_period($month, $year);
        $today  = date('Y-m-d');

        // 1. Employee
        if ($this->emp_batch_cache !== null) {
            if (!isset($this->emp_batch_cache[$eid])) throw new Exception('Mitra Kerja tidak ditemukan');
            $emp = $this->emp_batch_cache[$eid];
        } else {
            $emp_q = $this->db->query("
                SELECT u.*, p.position_name,
                       b.id AS branch_id, b.branch_name,
                       b.is_fine_system, b.is_pray_system, b.max_overtime,
                       b.pray_late_fix_rate, b.pray_late_multiple_count,
                       b.pray_late_start_rate, b.pray_late_multiple_rate
                FROM users u
                JOIN position p ON p.id = u.position_id
                JOIN branch b ON b.id = p.branch_id
                WHERE u.id = ?
            ", [$eid]);
            if (!$emp_q->num_rows()) throw new Exception('Mitra Kerja tidak ditemukan');
            $emp = $emp_q->row_array();
        }
        $salary = (float)($emp['salary'] ?? 0);
        $sal_min = (float)($emp['salary_minimum'] ?? 0);

        // Masa kerja (bulan) — acuan akhir periode payroll (tgl 25)
        $masa_months = 0;
        if (!empty($emp['join_date']) && $emp['join_date'] !== '0000-00-00') {
            $jd  = new DateTime($emp['join_date']);
            $ref = new DateTime(sprintf('%d-%02d-25', $year, $month));
            $d   = $jd->diff($ref);
            $masa_months = $jd <= $ref ? ($d->y * 12 + $d->m) : 0;
        }
        // Status menikah dari PTKP: 'K/x' = kawin, 'TK/x' = tidak kawin
        $is_married = strpos(strtoupper(trim((string)($emp['ptkp_status'] ?? ''))), 'K') === 0;

        // 2. Shift schedule
        $adds = $this->adds_batch_cache !== null
            ? ($this->adds_batch_cache[$eid] ?? [])
            : $this->db->query("
                SELECT usa.additional_date, usa.additional_type,
                       s.shift_code,
                       s.late_amount_start, s.late_amount_multiple_start,
                       s.late_multiple_count_start, s.late_amount_max_start,
                       s.late_amount_rest, s.late_amount_multiple_rest,
                       s.late_multiple_count_rest, s.late_amount_max_rest
                FROM users_shift_additional usa
                JOIN shift s ON s.id = usa.shift_id
                WHERE usa.user_id = ? AND usa.additional_date BETWEEN ? AND ?
            ", [$eid, $period['from'], $period['to']])->result_array();

        $adt_map = []; $strip = 0;
        foreach ($adds as $r) {
            $adt_map[$r['additional_date']] = $r;
            // NO-SC / No Schedule: bukan hari kerja, jadi tidak kena denda/alfa —
            // TAPI tetap kena potongan "Kekurangan Hari Kerja" (strip), sama seperti
            // Presence_model::get_fine() (production). Gaji pokok diprorata hanya
            // untuk hari yang benar-benar berjadwal.
            if ($this->_no_sc($r['shift_code'])) $strip++;
        }
        $period_days   = count($period['list']);
        $spd           = $period_days > 0 ? $salary / $period_days : 0;   // salary per day (period)
        $spd_alpha     = $total_days  > 0 ? $salary / $total_days  : 0;   // salary per day (alpha)

        // 3. Attendance
        $atts = $this->atts_batch_cache !== null
            ? ($this->atts_batch_cache[$eid] ?? [])
            : $this->db->query("
                SELECT * FROM presence
                WHERE user_id = ? AND flow_date BETWEEN ? AND ? AND presence_status = 'approved'
            ", [$eid, $period['from'], $period['to']])->result_array();
        $att_map = [];
        foreach ($atts as $r) $att_map[$r['flow_date']] = $r;

        // 4. Double deduction dates — konstan per cabang+periode, cache instance-level
        // (bukan per-karyawan) supaya tak di-query ulang tiap _calc().
        $dbl_cache_key = $emp['branch_id'].'-'.$period['from'].'-'.$period['to'];
        if (!isset($this->dbl_dates_cache[$dbl_cache_key])) {
            $dbls = $this->db->query("
                SELECT special_date FROM double_deduction_date
                WHERE branch_id = ? AND special_date BETWEEN ? AND ? AND is_active = 1
            ", [$emp['branch_id'], $period['from'], $period['to']])->result_array();
            $this->dbl_dates_cache[$dbl_cache_key] = array_column($dbls, 'special_date', 'special_date');
        }
        $dbl_dates = $this->dbl_dates_cache[$dbl_cache_key];

        // 5. Fines
        $fine_total = 0;
        $fb = [
            'lateFine' => 0, 'halfFine' => 0, 'restFine' => 0, 'prayFine' => 0,
            'leaveFine' => 0, 'alfaWeekday' => 0, 'alfaWeekend' => 0,
            'alfaSpecial' => 0, 'earlyLeaveFine' => 0,
        ];
        $fl = ['late' => [], 'rest' => [], 'pray' => [], 'alfa' => [], 'leave' => []];
        $early_min_total = 0;
        $is_fine = (string)($emp['is_fine_system'] ?? '') === '1';
        $is_pray = (string)($emp['is_pray_system'] ?? '') === '1';

        foreach ($att_map as $date => $att) {
            $adt = $adt_map[$date] ?? null;
            if (!$adt || $this->_no_sc($adt['shift_code'])) continue;
            $entry_late = (float)($att['entry_time_late'] ?? 0);
            $rest_late  = (float)($att['rest_time_late']  ?? 0);

            if ($is_fine && $att['presence_type'] === 'normal') {
                if ($entry_late > 0) {
                    $f = $this->_tiered($entry_late,
                        (float)$adt['late_amount_start'], (float)$adt['late_amount_multiple_start'],
                        (float)$adt['late_multiple_count_start'], (float)$adt['late_amount_max_start']);
                    $fb['lateFine'] += $f; $fine_total += $f;
                    $fl['late'][] = ['date' => $date, 'minutes' => $entry_late, 'amount' => $f];
                }
                $has_entry = !empty($att['entry_time']);
                $has_out   = !empty($att['out_time']);
                if (($has_entry && !$has_out) || (!$has_entry && $has_out)) {
                    $half = (int)round($spd / 2);
                    $fb['halfFine'] += $half; $fine_total += $half;
                    $fl['late'][] = ['date' => $date, 'minutes' => 0, 'amount' => $half, 'note' => '½ hari'];
                }
                if ($rest_late > 0) {
                    $f = $this->_tiered($rest_late,
                        (float)$adt['late_amount_rest'], (float)$adt['late_amount_multiple_rest'],
                        (float)$adt['late_multiple_count_rest'], (float)$adt['late_amount_max_rest']);
                    $fb['restFine'] += $f; $fine_total += $f;
                    $fl['rest'][] = ['date' => $date, 'minutes' => $rest_late, 'amount' => $f];
                } elseif (!empty($att['rest_time_in']) && empty($att['rest_time_out'])) {
                    $f = (float)($adt['late_amount_max_rest'] ?? 0);
                    $fb['restFine'] += $f; $fine_total += $f;
                    $fl['rest'][] = ['date' => $date, 'minutes' => 0, 'amount' => $f, 'note' => 'Tak tercatat'];
                }
                $em = (float)($att['early_leave_short_minutes'] ?? 0);
                if (!empty($att['is_early_leave']) && $em > 0) $early_min_total += $em;
            }

            if ($is_pray) {
                $pb  = (float)($emp['pray_late_start_rate']    ?? 0);
                $pm  = (float)($emp['pray_late_multiple_rate'] ?? 0);
                $pt  = (float)($emp['pray_late_multiple_count']?? 0);
                $pmx = (float)($emp['pray_late_fix_rate']      ?? 0);
                foreach (self::$PRAY_KEYS as $key) {
                    $pl = (float)($att["{$key}_time_late"] ?? 0);
                    $pi = $att["{$key}_time_in"]  ?? '';
                    $po = $att["{$key}_time_out"] ?? '';
                    $pf = $pl > 0 ? $this->_tiered($pl, $pb, $pm, $pt, $pmx) : ($pi && !$po ? $pmx : 0);
                    if ($pf > 0) {
                        $fb['prayFine'] += $pf; $fine_total += $pf;
                        $fl['pray'][] = ['date' => $date, 'waktu' => $key, 'minutes' => $pl, 'amount' => $pf];
                    }
                }
            }

            if ($is_fine && $att['presence_type'] !== 'normal') {
                $pct = 100 - (float)($att['presence_get_paid'] ?? 100);
                if ($pct > 0) {
                    $dow = (int)date('w', strtotime($date));
                    $lf  = (int)round(($pct / 100) * $spd) * (in_array($dow, [0,6]) ? 2 : 1);
                    $fb['leaveFine'] += $lf; $fine_total += $lf;
                    $fl['leave'][] = ['date' => $date, 'type' => $att['presence_type'], 'pct' => $pct, 'amount' => $lf];
                }
            }
        }

        if ($early_min_total > 0) {
            $ef = (int)floor(($total_days > 0 ? $salary / $total_days / 10 : 0) * ($early_min_total / 60));
            $fb['earlyLeaveFine'] = $ef; $fine_total += $ef;
        }

        foreach ($period['list'] as $date) {
            if ($date > $today) continue;
            $adt = $adt_map[$date] ?? null;
            if (!$adt || $adt['additional_type'] !== 'work' || $this->_no_sc($adt['shift_code'])) continue;
            if (isset($att_map[$date])) continue;
            $dow = (int)date('w', strtotime($date));
            $is_wknd = in_array($dow, [0,6]);
            $is_spec  = isset($dbl_dates[$date]);
            if ($is_wknd || $is_spec) {
                $f = (int)round($spd_alpha * 2);
                $fine_total += $f;
                $fb[$is_spec ? 'alfaSpecial' : 'alfaWeekend'] += $f;
                $fl['alfa'][] = ['date' => $date, 'type' => $is_spec ? 'special' : 'weekend', 'amount' => $f];
            } else {
                $f = (int)round($spd_alpha);
                $fine_total += $f; $fb['alfaWeekday'] += $f;
                $fl['alfa'][] = ['date' => $date, 'type' => 'weekday', 'amount' => $f];
            }
        }

        // 6. Insentif
        if (!isset($this->ins_master_cache[$emp['branch_id']])) {
            $this->ins_master_cache[$emp['branch_id']] = $this->db->query(
                "SELECT *, id AS master_id FROM insentif WHERE branch_id = ? AND is_active = '1' ORDER BY insentif_name",
                [$emp['branch_id']]
            )->result_array();
        }
        $ins_master = $this->ins_master_cache[$emp['branch_id']];

        $pi_cache_key = $emp['branch_id'].'-'.$eid.'-'.$month.'-'.$year;
        if ($this->pi_batch_cache !== null && array_key_exists($pi_cache_key, $this->pi_batch_cache)) {
            $pi_rows = $this->pi_batch_cache[$pi_cache_key];
        } else {
            $pi_rows = $this->db->query("
                SELECT pi.* FROM payroll_insentif pi
                JOIN users u ON u.id = pi.user_id
                JOIN position p ON p.id = u.position_id
                WHERE p.branch_id = ? AND pi.user_id = ? AND pi.insentif_month = ? AND pi.insentif_year = ?
            ", [$emp['branch_id'], $eid, $month, $year])->result_array();
        }
        $ins_ov = [];
        foreach ($pi_rows as $r) $ins_ov[$r['insentif_id']] = (float)$r['insentif_amount'];

        // Hadir penuh (masuk+pulang) — kecualikan tanggal berjadwal NO-SC, sama seperti
        // perhitungan denda. Tap yang kejadian saat NO-SC (mis. karyawan iseng absen)
        // tidak boleh ikut menaikkan kelayakan komisi per-hadir (Transport, dll).
        $pres_cnt = 0;
        foreach ($att_map as $date => $att) {
            $adt = $adt_map[$date] ?? null;
            if ($adt && $this->_no_sc($adt['shift_code'])) continue;
            if (!empty($att['entry_time']) && !empty($att['out_time'])) $pres_cnt++;
        }

        $pstr = sprintf('%d-%02d', $year, $month);

        // Komisi otomatis: hitung kelayakan & nominal dari aturan
        $auto_comm = $this->_auto_commissions($eid, $period, $att_map, $adt_map, $fb, $fl,
            $pres_cnt, $masa_months, $is_married, $is_pray, $month, $year);

        $ins_list = []; $ins_total = 0;
        foreach ($ins_master as $ins) {
            $mid = (int)$ins['master_id'];
            // ── Item komisi otomatis (override logika manual) ──
            if (isset(self::$AUTO_COMM[$mid])) {
                [$ckey, $canonical] = self::$AUTO_COMM[$mid];
                $ac = $auto_comm[$ckey];
                $ins_list[] = [
                    'id' => $mid, 'name' => $canonical, 'formula' => 'auto_commission',
                    'nominal' => $ac['nominal'], 'amount' => $ac['amount'], 'active' => $ac['eligible'],
                    'isAuto' => true, 'autoCalc' => true, 'hasOverride' => false,
                    'eligible' => $ac['eligible'], 'conditions' => $ac['conditions'],
                    'formulaDetail' => $ac['formulaDetail'], 'source' => $ac['source'],
                    'logItems' => $ac['logItems'] ?? [],
                ];
                $ins_total += $ac['amount'];
                continue;
            }
            $has_ov = isset($ins_ov[$ins['master_id']]);
            if ($has_ov) { $amt = $ins_ov[$ins['master_id']]; }
            elseif ($ins['formula'] === 'per_presence') { $amt = $pres_cnt * (float)$ins['nominal']; }
            elseif ($ins['formula'] !== 'none') { $amt = (float)$ins['nominal']; }
            else { $amt = 0; }
            $amt = (int)round($amt);
            $nfmt = number_format((float)$ins['nominal'], 0, ',', '.');
            $afmt = number_format($amt, 0, ',', '.');
            if ($ins['formula'] === 'per_presence' && !$has_ov) {
                $src = 'Auto — formula per hadir';
                $fd  = "Rp {$nfmt} × {$pres_cnt} hadir = Rp {$afmt}";
            } elseif ($ins['formula'] !== 'none' && !$has_ov) {
                $src = 'Auto — nominal tetap'; $fd = "Nominal tetap: Rp {$nfmt}";
            } elseif ($has_ov) {
                $src = "HR input {$pstr}"; $fd = "Rp {$afmt} (diisi HR periode ini)";
            } else {
                $src = 'Tidak ada nilai'; $fd = 'Tidak ada nilai untuk periode ini';
            }
            $ins_list[] = [
                'id' => $ins['master_id'], 'name' => $ins['insentif_name'],
                'formula' => $ins['formula'], 'nominal' => (float)$ins['nominal'],
                'amount' => $amt, 'active' => true, 'isAuto' => true,
                'autoCalc' => $ins['formula'] !== 'none' && !$has_ov,
                'hasOverride' => $has_ov, 'formulaDetail' => $fd, 'source' => $src,
            ];
            $ins_total += $amt;
        }

        // 7. Deduction
        $ded_master = $this->db->query(
            "SELECT *, id AS master_id FROM deduction WHERE branch_id = ? AND is_active = '1' ORDER BY deduction_name",
            [$emp['branch_id']]
        )->result_array();
        $pd_rows = $this->db->query("
            SELECT pd.* FROM payroll_deduction pd
            JOIN users u ON u.id = pd.user_id
            JOIN position p ON p.id = u.position_id
            WHERE p.branch_id = ? AND pd.user_id = ? AND pd.deduction_month = ? AND pd.deduction_year = ?
        ", [$emp['branch_id'], $eid, $month, $year])->result_array();
        $ded_ov = [];
        foreach ($pd_rows as $r)
            $ded_ov[$r['deduction_id']] = ['amount' => (float)$r['deduction_amount'], 'note' => $r['deduction_note'] ?? ''];

        $ded_list = []; $ded_total = 0;
        foreach ($ded_master as $ded) {
            $ov  = $ded_ov[$ded['master_id']] ?? null;
            $amt = $ov ? (int)round($ov['amount']) : 0;
            $note = $ov ? ($ov['note'] ?? '') : '';
            $afmt = number_format($amt, 0, ',', '.');
            $fd  = $ov ? "Rp {$afmt} (diisi HR periode ini)" . ($note ? " — {$note}" : '') : 'Tidak ada entry untuk periode ini';
            $ded_list[] = [
                'id' => $ded['master_id'], 'name' => $ded['deduction_name'],
                'amount' => $amt, 'note' => $note, 'active' => true,
                'isAuto' => true, 'autoCalc' => false, 'hasOverride' => !!$ov,
                'formulaDetail' => $fd, 'source' => $ov ? "HR input {$pstr}" : 'Tidak ada entry',
            ];
            $ded_total += $amt;
        }

        // 8. Overtime
        $max_ot  = (float)($emp['max_overtime'] ?? 9999);
        $ot_rate = (float)($emp['overtime_hour_rate'] ?? 0);
        $ot_detail = $this->db->query("
            SELECT overtime_date, overtime_hour
            FROM overtime
            WHERE user_id = ? AND overtime_date BETWEEN ? AND ? AND overtime_status = 'approve'
            ORDER BY overtime_date DESC
        ", [$eid, $period['from'], $period['to']])->result_array();
        $ot_hours = 0; $ot_log = [];
        foreach ($ot_detail as $r) {
            $jam = min((float)$r['overtime_hour'], $max_ot);
            $ot_hours += $jam;
            $ot_log[] = [
                'date'   => date('d M Y', strtotime($r['overtime_date'])),
                'note'   => rtrim(rtrim(number_format($jam, 1, ',', ''), '0'), ',') . ' jam',
                'amount' => (int)round($jam * $ot_rate),
            ];
        }
        $ot_amt = (int)round($ot_hours * $ot_rate);

        // 9. Strip, payment receive, custom, THP
        // Pakai spd_alpha (gaji / hari kalender sebulan), sama seperti Presence_model::get_fine()
        // ($salary_per_day = salary / totalDayInMonth), bukan spd (gaji / hari periode payroll).
        $strip_ded = (int)round($spd_alpha * $strip);
        $pay_rcv   = ($salary - $fb['alfaWeekday']) < $sal_min ? $sal_min : $salary;
        $cb = array_sum(array_column(array_filter($custom, fn($x) => ($x['type']??'') === 'bonus'),     'amount'));
        $cd = array_sum(array_column(array_filter($custom, fn($x) => ($x['type']??'') === 'deduction'), 'amount'));

        $total_in  = $pay_rcv + $ot_amt + $ins_total + $cb;
        $total_out = $fine_total + $ded_total + $strip_ded + $cd;
        $thp_raw   = $total_in - $total_out;

        $masa = (!empty($emp['join_date']) && $emp['join_date'] !== '0000-00-00')
            ? intdiv($masa_months, 12) . ' thn ' . ($masa_months % 12) . ' bln'
            : '-';

        return [
            'employee' => [
                'id' => $emp['id'], 'name' => trim($emp['first_name'].' '.($emp['last_name']??'')),
                'code' => $emp['employee_code'], 'position' => $emp['position_name'],
                'branch' => $emp['branch_name'], 'joinDate' => $emp['join_date'], 'masaKerja' => $masa,
                'salary' => $salary, 'salaryMin' => $sal_min, 'ptkpStatus' => $emp['ptkp_status'] ?? '',
                'isFineSystem' => $is_fine, 'isPraySystem' => $is_pray,
            ],
            'period' => ['month' => $month, 'year' => $year, 'from' => $period['from'], 'to' => $period['to']],
            'income' => [
                'gajiPokok' => $pay_rcv, 'overtime' => $ot_amt, 'overtimeHours' => $ot_hours,
                'overtimeLog' => $ot_log,
                'insentif' => $ins_total, 'insentifList' => $ins_list,
                'customBonus' => (int)$cb, 'total' => (int)$total_in,
            ],
            'outcome' => [
                'fine' => $fine_total, 'fineBreakdown' => $fb, 'fineLog' => $fl,
                'deduction' => $ded_total, 'deductionList' => $ded_list,
                'strip' => $strip_ded, 'customDeduct' => (int)$cd, 'total' => (int)$total_out,
            ],
            'summary' => [
                'thp' => max(0, $thp_raw), 'debt' => $thp_raw < 0 ? abs($thp_raw) : 0,
                'totalIncome' => (int)$total_in, 'totalOutcome' => (int)$total_out,
            ],
        ];
    }

    private function _rp($n) { return 'Rp ' . number_format((float)round($n), 0, ',', '.'); }

    // Hitung kelayakan & nominal 5 komisi otomatis
    private function _auto_commissions($eid, $period, $att_map, $adt_map, $fb, $fl,
                                       $pres_cnt, $masa_months, $is_married, $is_pray, $month = null, $year = null) {
        $masa_tahun = $masa_months >= 12;
        $masa_str   = intdiv($masa_months, 12) . ' thn ' . ($masa_months % 12) . ' bln';

        // 1. Cuti/izin/sakit (approved) yang overlap periode
        $leaves = $this->leaves_batch_cache !== null
            ? ($this->leaves_batch_cache[$eid] ?? [])
            : $this->db->query("
                SELECT leave_type, leave_range,
                       (leave_proof IS NOT NULL AND leave_proof != '') AS has_proof
                FROM `leave`
                WHERE user_id = ? AND leave_status = 'approve'
                  AND leave_start <= ? AND leave_end >= ?
            ", [$eid, $period['to'], $period['from']])->result_array();
        $sakit_days = 0; $sakit_no_proof = 0; $izin_cnt = 0;
        foreach ($leaves as $l) {
            if ($l['leave_type'] === 'sakit') {
                $sakit_days += (int)$l['leave_range'];
                if ((int)$l['has_proof'] !== 1) $sakit_no_proof++;
            } elseif ($l['leave_type'] === 'izin') { $izin_cnt++; }
        }
        // Total hari sakit dlm periode (bukan per pengajuan) yg dinilai — cegah lolos
        // dengan memecah sakit jadi beberapa pengajuan ≤2 hari yg masing2 valid.
        $sakit_invalid = ($sakit_days > 2 || $sakit_no_proof > 0) ? 1 : 0;

        // 2. Rekap sholat per jenis dari finger — kecualikan tanggal berjadwal NO-SC,
        // sama seperti perhitungan denda & hadir (tap saat NO-SC tak boleh ikut hitung).
        $pray = ['subuh'=>0,'dzuhur'=>0,'ashar'=>0,'maghrib'=>0,'isha'=>0,'friday'=>0];
        foreach ($att_map as $date => $att) {
            $adt = $adt_map[$date] ?? null;
            if ($adt && $this->_no_sc($adt['shift_code'])) continue;
            foreach (self::$PRAY_KEYS as $k) {
                if (!empty($att["{$k}_time_in"])) $pray[$k]++;
            }
        }
        // Untuk eligibility komisi: Jumat digabung ke Dzuhur (sholat siang).
        // Kunci 'dzuhur_eff' menggantikan 'dzuhur' & 'friday' dalam syarat ≥20.
        $pray_eff = [
            'subuh'      => $pray['subuh'],
            'dzuhur_eff' => $pray['dzuhur'] + $pray['friday'],
            'ashar'      => $pray['ashar'],
            'maghrib'    => $pray['maghrib'],
            'isha'       => $pray['isha'],
        ];
        $pray_eff_label = [
            'subuh' => 'Subuh', 'dzuhur_eff' => 'Dzuhur+Jumat',
            'ashar' => 'Ashar', 'maghrib' => 'Maghrib', 'isha' => 'Isya',
        ];
        $pray_qualify = array_keys(array_filter($pray_eff, fn($n) => $n >= 20));
        $sholat_ok = $is_pray && count($pray_qualify) >= 2;

        // ── DISIPLIN ──
        // Akumulasi denda keterlambatan: kehadiran + istirahat + sholat + pulang awal
        $denda_tp = (int)round(
            ($fb['lateFine']       ?? 0) +
            ($fb['restFine']       ?? 0) +
            ($fb['prayFine']       ?? 0) +
            ($fb['earlyLeaveFine'] ?? 0)
        );
        $alfa_days = count($fl['alfa'] ?? []);
        $nosc_days = 0;
        foreach ($period['list'] as $date) {
            $adt = $adt_map[$date] ?? null;
            if ($adt && $this->_no_sc($adt['shift_code'])) $nosc_days++;
        }
        $dis_nom = $masa_tahun ? 150000 : 100000;
        $dis_cond = [
            ['label'=>'Akumulasi denda telat (hadir+istirahat+sholat) + pulang awal ≤ Rp50.000', 'value'=>'aktual '.$this->_rp($denda_tp), 'ok'=>$denda_tp <= 50000],
            ['label'=>'Tidak ada alfa', 'value'=>$alfa_days.' hari alfa', 'ok'=>$alfa_days === 0],
            ['label'=>'Sakit terdokumentasi (maksimal 2 hari, wajib disertai surat ijin)', 'value'=>$sakit_invalid ? ($sakit_days.' hari sakit'.($sakit_no_proof ? ' (ada tanpa surat)' : '')) : 'OK', 'ok'=>$sakit_invalid === 0],
            ['label'=>'Tidak ada izin (no-schedule)', 'value'=>$izin_cnt ? $izin_cnt.' izin' : 'OK', 'ok'=>$izin_cnt === 0],
            ['label'=>'Tidak ada NO-SC', 'value'=>$nosc_days ? $nosc_days.' hari NO-SC' : 'OK', 'ok'=>$nosc_days === 0],
        ];
        $dis_ok = !array_filter($dis_cond, fn($c) => !$c['ok']);

        // ── TRANSPORT ──
        $tr_cond = [['label'=>'Hadir penuh (masuk+pulang) ≥ 25×', 'value'=>'aktual '.$pres_cnt.'×', 'ok'=>$pres_cnt >= 25]];
        $tr_ok = $pres_cnt >= 25;

        // ── BERAS ──
        $br_cond = [['label'=>'Status menikah (PTKP K/…)', 'value'=>$is_married ? 'menikah' : 'belum menikah', 'ok'=>$is_married]];

        // ── SOSKES / BPJS ──
        $m_int = $month ? (int)$month : (int)date('m', strtotime($period['to']));
        $y_int = $year ? (int)$year : (int)date('Y', strtotime($period['to']));
        $bpjs = $this->db->get_where('bpjs_payment', ['user_id' => $eid, 'month' => $m_int, 'year' => $y_int])->row_array();
        $cfg_bpjs = $this->db->get('bpjs_config')->row_array();
        $soskes_nom = $cfg_bpjs && !empty($cfg_bpjs['mandiri_insentif']) ? (float)$cfg_bpjs['mandiri_insentif'] : 101500;

        // Aturan: Komisi Soskes HANYA bila ada record bpjs_payment mandiri yang sudah di-ACC admin.
        // Tanpa record / kantor / mandiri pending → tidak dapat Komisi Soskes.
        if ($bpjs && $bpjs['pay_mode'] === 'kantor') {
            $ss_ok = false;
            $ss_cond = [
                ['label'=>'Masa kerja ≥ 1 tahun', 'value'=>'aktual '.$masa_str, 'ok'=>$masa_tahun],
                ['label'=>'Metode Pembayaran BPJS', 'value'=>'Dibayarkan Kantor (Berlaku Potongan Gaji, Bukan Komisi)', 'ok'=>false],
            ];
        } else if ($bpjs && $bpjs['pay_mode'] === 'mandiri') {
            $is_app = ($bpjs['status'] === 'approved');
            $ss_ok = $masa_tahun && $is_app;
            $ss_cond = [
                ['label'=>'Masa kerja ≥ 1 tahun', 'value'=>'aktual '.$masa_str, 'ok'=>$masa_tahun],
                ['label'=>'Bukti Bayar BPJS Mandiri Sudah Di-ACC Admin', 'value'=> $is_app ? 'Approved' : 'Belum di-ACC / Pending', 'ok'=>$is_app],
            ];
        } else {
            // Tidak ada record bpjs_payment → asumsikan mandiri, namun belum ada bukti → no komisi.
            $ss_ok = false;
            $ss_cond = [
                ['label'=>'Masa kerja ≥ 1 tahun', 'value'=>'aktual '.$masa_str, 'ok'=>$masa_tahun],
                ['label'=>'Bukti Bayar BPJS Mandiri Sudah Di-ACC Admin', 'value'=>'Belum ada bukti bayar di menu BPJS', 'ok'=>false],
            ];
        }

        // ── SHOLAT ──
        // Label menampilkan jenis qualified (Dzuhur+Jumat dianggap satu jenis sholat siang).
        $sh_qual_label = array_map(fn($k) => $pray_eff_label[$k], $pray_qualify);
        $sh_cond = [[
            'label'=>'≥2 jenis sholat, masing-masing ≥20 record (Jumat digabung dengan Dzuhur)',
            'value'=> count($pray_qualify) >= 2 ? implode(' + ', $sh_qual_label) : count($pray_qualify).' jenis ≥20',
            'ok'=>$sholat_ok,
        ]];
        // Log per-jenis tetap menampilkan masing-masing waktu (termasuk Jumat terpisah),
        // ditambah baris gabungan Dzuhur+Jumat untuk transparansi syarat eligibility.
        $sh_log = [];
        foreach (self::$PRAY_KEYS as $k) {
            if ($pray[$k] > 0) $sh_log[] = ['date'=>self::$PRAY_LABEL[$k], 'note'=>$pray[$k].' record', 'count'=>$pray[$k], 'qualify'=>false];
        }
        $dz_eff = $pray_eff['dzuhur_eff'];
        if ($dz_eff > 0) {
            $sh_log[] = [
                'date'=>'Dzuhur+Jumat (gabungan)',
                'note'=>$dz_eff.' record (Dzuhur '.$pray['dzuhur'].' + Jumat '.$pray['friday'].')',
                'count'=>$dz_eff,
                'qualify'=>$dz_eff >= 20,
            ];
        }
        foreach (['subuh','ashar','maghrib','isha'] as $k) {
            if ($pray[$k] >= 20) {
                foreach ($sh_log as &$row) {
                    if ($row['date'] === self::$PRAY_LABEL[$k]) { $row['qualify'] = true; break; }
                }
                unset($row);
            }
        }

        $mk = function($eligible, $nominal, $conditions, $extra = []) {
            return array_merge([
                'eligible' => $eligible, 'nominal' => $nominal,
                'amount' => $eligible ? $nominal : 0, 'conditions' => $conditions,
                'source' => $eligible ? 'Auto — syarat terpenuhi' : 'Auto — syarat tidak terpenuhi',
                'formulaDetail' => $eligible ? ('Memenuhi syarat → '.$this->_rp($nominal)) : 'Tidak memenuhi syarat → Rp 0',
            ], $extra);
        };

        return [
            'disiplin'  => $mk($dis_ok, $dis_nom, $dis_cond, [
                'formulaDetail' => ($dis_ok ? 'Memenuhi' : 'Tidak memenuhi') . ' syarat · masa kerja ' .
                    ($masa_tahun ? '≥1th → Rp150.000' : '<1th → Rp100.000') . ' → ' . ($dis_ok ? $this->_rp($dis_nom) : 'Rp 0'),
            ]),
            'transport' => $mk($tr_ok, 100000, $tr_cond),
            'beras'     => $mk($is_married, 170000, $br_cond),
            'soskes'    => $mk($ss_ok, $soskes_nom, $ss_cond),
            'sholat'    => $mk($sholat_ok, 50000, $sh_cond, ['logItems' => $sh_log]),
        ];
    }
}

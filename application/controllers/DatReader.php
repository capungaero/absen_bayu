<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * DatReader — pembaca file .dat mesin absensi (tool baru).
 *
 * Arsitektur DUA dataset (terpisah dari `presence`):
 *   dat_reader_mirror : hasil sync mesin Solution Cloud MURNI (gabungan mesin),
 *                       per (user, tanggal). Hidden, di-refresh tiap sync.
 *   dat_reader_work   : data kerja editable yang DITAMPILKAN. Baris yang diedit
 *                       admin (beda dari mirror) ditandai is_edited=1.
 *
 * Klasifikasi tap POSISIONAL (kerja ~10 jam, bukan window shift):
 *   tap pertama=Datang, terakhir=Pulang, tengah=istirahat (out_ist & in_ist).
 *
 * Alur: sync (upload/cloud) → isi mirror+work → admin lihat/edit/simpan (work) →
 * "Dorong ke Presensi" menulis work ke `presence` (ADITIF: skip baris yg sudah
 * ada, skip periode terkunci). Selector rentang: periode berjalan / rentang / tanggal.
 *
 * Auth: ion_auth session + role (admin/admin-branch/hr). CSRF dikecualikan.
 */
class DatReader extends CI_Controller {

    private $role;
    private $userdata;

    public function __construct() {
        parent::__construct();
        if (!$this->ion_auth->logged_in()) {
            $this->_die(['error' => 'Unauthorized'], 401);
        }
        $this->role     = $this->ion_auth->get_users_groups()->row()->name;
        $this->userdata = $this->ion_auth->user()->row();
        if (!in_array($this->role, ['admin', 'admin-branch', 'hr'])) {
            $this->_die(['error' => 'Forbidden'], 403);
        }
        $this->load->library('attlog_parser');
        $this->load->library('attendance_employee_resolver');
        $this->load->library('cloud_attlog_client');
        $this->load->model('payroll_model', 'payroll');
        $this->load->model('presence_daily_report_model', 'daily_report');
        $this->load->model('Sync_model', 'sync_machines');
        $this->load->model('Dat_reader_model', 'dat_reader');
    }

    private function _die($data, $code) {
        $this->output->set_status_header($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data); exit;
    }
    private function _json($data, $code = 200) {
        $this->output->set_status_header($code)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
    private function _body() {
        $j = json_decode(file_get_contents('php://input'), true);
        return is_array($j) ? $j : [];
    }
    private function _allowed_branch($branch_id) {
        $branch_id = (int)$branch_id;
        if ($this->role === 'admin') return $branch_id ?: null;
        $own = (int)$this->userdata->branch_id;
        return ($branch_id === 0 || $branch_id === $own) ? $own : false;
    }
    private function _locked($branch_id, $month, $year) {
        return $this->payroll->get_detail([
            'branch_id' => (int)$branch_id, 'month' => (int)$month, 'year' => (int)$year,
        ])->num_rows() > 0;
    }

    // ───────────────────────── GET dat_reader/branches ─────────────────────────
    public function branches() {
        if ($this->role === 'admin') {
            $rows = $this->db->query("SELECT id, branch_name FROM branch WHERE is_active=1 ORDER BY branch_name")->result_array();
        } else {
            $rows = $this->db->query("SELECT id, branch_name FROM branch WHERE id = ? AND is_active=1",
                [(int)$this->userdata->branch_id])->result_array();
        }
        $this->_json($rows);
    }

    // ───────────────────────── GET dat_reader/period ───────────────────────────
    public function period() {
        $branch_id = $this->_allowed_branch($this->input->get('branch_id'));
        $month = (int)date('m'); $year = (int)date('Y');
        $range = attlog_presence_period_range($month, $year);
        $this->_json([
            'month' => $month, 'year' => $year,
            'from' => $range['from'], 'to' => $range['to'], 'today' => date('Y-m-d'),
            'locked' => ($branch_id && $branch_id !== false) ? $this->_locked($branch_id, $month, $year) : false,
        ]);
    }

    // ─────────────── POST dat_reader/sync_upload (multipart: file) ──────────────
    public function sync_upload() {
        if ($this->input->method() !== 'post') return $this->_json(['error' => 'POST required'], 405);
        $branch_id = $this->_allowed_branch($this->input->post('branch_id'));
        if ($branch_id === false || $branch_id === null) return $this->_json(['error' => 'Cabang tidak valid'], 422);

        list($from, $to, $mode) = $this->_resolve_range(
            $this->input->post('mode'), $this->input->post('from'), $this->input->post('to'));
        if (!$from) return $this->_json(['error' => 'Rentang tanggal tidak valid (maks 92 hari)'], 422);

        if (empty($_FILES['file']['name']))  return $this->_json(['error' => 'File .dat belum dipilih'], 422);
        if (!empty($_FILES['file']['error'])) return $this->_json(['error' => 'Upload gagal (kode '.$_FILES['file']['error'].')'], 422);
        $raw = file_get_contents($_FILES['file']['tmp_name']);
        if ($raw === false || trim($raw) === '') return $this->_json(['error' => 'File .dat kosong / tidak terbaca'], 422);

        $res = $this->_sync_core($raw, $branch_id, $from, $to, [basename($_FILES['file']['name'])]);
        if (!$res['ok']) return $this->_json(['error' => $res['msg']], 422);
        $this->_json($this->_rows_response($branch_id, $from, $to, ['mode' => $mode, 'sync' => $res, 'source' => 'upload']));
    }

    // ─────────────── POST dat_reader/sync_cloud (json) ──────────────────────────
    public function sync_cloud() {
        if ($this->input->method() !== 'post') return $this->_json(['error' => 'POST required'], 405);
        $b = $this->_body();
        $branch_id = $this->_allowed_branch(isset($b['branch_id']) ? $b['branch_id'] : 0);
        if ($branch_id === false || $branch_id === null) return $this->_json(['error' => 'Cabang tidak valid'], 422);

        list($from, $to, $mode) = $this->_resolve_range($b['mode'] ?? null, $b['from'] ?? null, $b['to'] ?? null);
        if (!$from) return $this->_json(['error' => 'Rentang tanggal tidak valid (maks 92 hari)'], 422);

        $machines = $this->_active_attendance_machines();
        if (empty($machines)) return $this->_json(['error' => 'Tidak ada mesin Solution Cloud aktif (tipe attendance).'], 422);
        $download = $this->cloud_attlog_client->download_batch($machines);
        if ($download === false) return $this->_json(['error' => 'Gagal download dari semua mesin. Cek koneksi/kredensial cloud.'], 502);

        $raw = '';
        $sources = [];
        foreach ($download['machines'] as $m) { $raw .= "\n".$m['raw']; $sources[] = $m['sn']; }
        if (trim($raw) === '') return $this->_json(['error' => 'Mesin terhubung tapi tidak ada data .dat valid.', 'failed' => $download['failed']], 422);

        $res = $this->_sync_core($raw, $branch_id, $from, $to, $sources);
        if (!$res['ok']) return $this->_json(['error' => $res['msg'], 'failed' => $download['failed']], 422);
        $this->_json($this->_rows_response($branch_id, $from, $to,
            ['mode' => $mode, 'sync' => $res, 'source' => 'cloud', 'failed' => $download['failed']]));
    }

    // ─────────────── GET dat_reader/data?branch_id=&mode=&from=&to= ─────────────
    public function data() {
        $branch_id = $this->_allowed_branch($this->input->get('branch_id'));
        if ($branch_id === false || $branch_id === null) return $this->_json(['error' => 'Cabang tidak valid'], 422);
        list($from, $to, $mode) = $this->_resolve_range(
            $this->input->get('mode'), $this->input->get('from'), $this->input->get('to'));
        if (!$from) return $this->_json(['error' => 'Rentang tanggal tidak valid (maks 92 hari)'], 422);
        $this->_json($this->_rows_response($branch_id, $from, $to, ['mode' => $mode]));
    }

    // ─────────────── POST dat_reader/save (json: branch_id, from, to, edits[]) ──
    public function save() {
        if ($this->input->method() !== 'post') return $this->_json(['error' => 'POST required'], 405);
        $b = $this->_body();
        $branch_id = $this->_allowed_branch(isset($b['branch_id']) ? $b['branch_id'] : 0);
        if ($branch_id === false || $branch_id === null) return $this->_json(['error' => 'Cabang tidak valid'], 422);
        list($from, $to, $mode) = $this->_resolve_range($b['mode'] ?? null, $b['from'] ?? null, $b['to'] ?? null);
        if (!$from) return $this->_json(['error' => 'Rentang tanggal tidak valid'], 422);

        $edits = isset($b['edits']) && is_array($b['edits']) ? $b['edits'] : [];
        if (empty($edits)) return $this->_json(['error' => 'Tidak ada perubahan untuk disimpan'], 422);

        // Validasi: hanya boleh edit karyawan di cabang terpilih.
        $uids = array_values(array_unique(array_map(function($e){ return (int)$e['user_id']; }, $edits)));
        $allowed = $this->_branch_user_ids($branch_id, $uids);
        $edits = array_values(array_filter($edits, function($e) use ($allowed){ return isset($allowed[(int)$e['user_id']]); }));
        if (empty($edits)) return $this->_json(['error' => 'Karyawan yang diedit tidak ada di cabang ini'], 422);

        $saved = $this->dat_reader->save_work_edits($edits, (int)$this->userdata->id, date('Y-m-d H:i:s'));
        $this->_json($this->_rows_response($branch_id, $from, $to, ['mode' => $mode, 'saved' => $saved]));
    }

    // ─────────────── POST dat_reader/push (json: branch_id, from, to) ───────────
    // Dorong data kerja → presence. ADITIF: skip baris yg sudah ada, skip terkunci.
    public function push() {
        if ($this->input->method() !== 'post') return $this->_json(['error' => 'POST required'], 405);
        $b = $this->_body();
        $branch_id = $this->_allowed_branch(isset($b['branch_id']) ? $b['branch_id'] : 0);
        if ($branch_id === false || $branch_id === null) return $this->_json(['error' => 'Cabang tidak valid'], 422);
        list($from, $to, $mode) = $this->_resolve_range($b['mode'] ?? null, $b['from'] ?? null, $b['to'] ?? null);
        if (!$from) return $this->_json(['error' => 'Rentang tanggal tidak valid'], 422);

        $rows = $this->dat_reader->get_work_range($branch_id, $from, $to);
        if (empty($rows)) return $this->_json(['error' => 'Tidak ada data kerja pada rentang ini. Sync dulu.'], 422);

        $uids = array_values(array_unique(array_map(function($r){ return (int)$r['user_id']; }, $rows)));
        $existing  = $this->dat_reader->presence_existing_pairs($uids, $from, $to);
        $ubranch   = $this->_user_branch_map($uids);
        $shift_map = $this->_shift_map($uids, $from, $to);
        $created_at = date('Y-m-d H:i:s');

        $lock_cache = []; $insert = []; $pushed = [];
        $skipped_existing = 0; $skipped_locked = 0; $skipped_empty = 0;
        foreach ($rows as $r) {
            $uid = (int)$r['user_id']; $date = $r['flow_date'];
            if (isset($existing[$uid.'|'.$date])) { $skipped_existing++; continue; }
            if (empty($r['datang']) && empty($r['pulang']) && empty($r['out_ist']) && empty($r['in_ist'])) { $skipped_empty++; continue; }

            $pp = $this->_period_of_date($date);
            $bid = isset($ubranch[$uid]) ? $ubranch[$uid] : $branch_id;
            $lk = $bid.'|'.$pp['month'].'|'.$pp['year'];
            if (!isset($lock_cache[$lk])) $lock_cache[$lk] = $this->_locked($bid, $pp['month'], $pp['year']);
            if ($lock_cache[$lk]) { $skipped_locked++; continue; }

            $shift = isset($shift_map[$uid.'|'.$date]) ? $shift_map[$uid.'|'.$date] : null;
            $insert[] = $this->_presence_payload($uid, $date, $r, $shift, $created_at);
            $pushed[] = ['user_id' => $uid, 'flow_date' => $date];
        }

        if (empty($insert)) {
            return $this->_json($this->_rows_response($branch_id, $from, $to, ['mode' => $mode, 'push' => [
                'inserted' => 0, 'skipped_existing' => $skipped_existing,
                'skipped_locked' => $skipped_locked, 'skipped_empty' => $skipped_empty,
            ]]));
        }

        $this->db->trans_begin();
        $this->db->insert_batch('presence', $insert);
        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return $this->_json(['error' => 'Gagal menyimpan ke presence (transaksi dibatalkan).'], 500);
        }
        $this->db->trans_commit();
        $this->daily_report->sync_by_rows($insert);
        $this->dat_reader->mark_pushed($pushed, $created_at);

        $this->_json($this->_rows_response($branch_id, $from, $to, ['mode' => $mode, 'push' => [
            'inserted' => count($insert), 'skipped_existing' => $skipped_existing,
            'skipped_locked' => $skipped_locked, 'skipped_empty' => $skipped_empty,
        ]]));
    }

    // ════════════════════════ INTERNAL ════════════════════════

    /** Resolusi rentang dari mode. period=periode payroll berjalan; date=satu hari; range=from..to. */
    private function _resolve_range($mode, $from, $to) {
        $mode = in_array($mode, ['period', 'range', 'date'], true) ? $mode : 'period';
        if ($mode === 'period') {
            $r = attlog_presence_period_range((int)date('m'), (int)date('Y'));
            return [$r['from'], $r['to'], 'period'];
        }
        $ok = function($d){ return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) ? $d : null; };
        $f = $ok($from); $t = ($mode === 'date') ? $f : $ok($to);
        if (!$f || !$t) return [null, null, $mode];
        if ($t < $f) { $tmp = $f; $f = $t; $t = $tmp; }
        if ((strtotime($t) - strtotime($f)) / 86400 > 92) return [null, null, $mode];
        return [$f, $t, $mode];
    }

    private function _active_attendance_machines() {
        $rows = $this->sync_machines->get_active_by_type('attendance');
        $machines = [];
        foreach ($rows as $row) {
            $sn = attlog_sanitize_machine_sn($row['machine_sn']);
            if ($sn === '' || $row['password'] === '') continue;
            $machines[] = ['sn' => $sn, 'pass' => $row['password']];
        }
        return $machines;
    }

    /** Parse raw .dat → klasifikasi posisional → upsert mirror + refresh work. */
    private function _sync_core($raw, $branch_id, $from, $to, $source) {
        $parsed = $this->attlog_parser->parse_taps($raw, $from, $to);
        $row_data = $parsed['rows'];
        if (empty($row_data)) {
            return ['ok' => false, 'msg' => 'Tidak ada tap pada '.$from.' s/d '.$to.
                '. Total baris: '.$parsed['stats']['total_lines'].', invalid: '.$parsed['stats']['invalid_count'].'.'];
        }

        $matcher = $this->attendance_employee_resolver->build_by_finger_date($row_data, $from, $to);
        $employee_map = $matcher['map'];
        $user_ids = $this->attendance_employee_resolver->employee_ids_from_map($employee_map);

        $mirror_rows = [];
        $missing = 0; $missing_fingers = [];
        $src = is_array($source) ? implode('+', $source) : (string)$source;
        foreach ($row_data as $finger_id => $dates) {
            foreach ($dates as $row) {
                $date = $row['date']; $times = $row['time']; sort($times);
                $emp = isset($employee_map[$finger_id][$date]) ? $employee_map[$finger_id][$date] : null;
                if (!$emp) {
                    $missing++;
                    if (count($missing_fingers) < 50 && !in_array($finger_id, $missing_fingers)) $missing_fingers[] = $finger_id;
                    continue;
                }
                $slot = $this->_classify_positional($times);
                $mirror_rows[] = [
                    'user_id' => $emp['id'], 'flow_date' => $date,
                    'datang' => $slot['datang'], 'out_ist' => $slot['out_ist'],
                    'in_ist' => $slot['in_ist'], 'pulang' => $slot['pulang'],
                    'tap_count' => count($times), 'all_taps' => implode(',', array_map(function($t){ return substr($t,0,5); }, $times)),
                    'source' => substr($src, 0, 120),
                ];
            }
        }
        if (empty($mirror_rows)) {
            return ['ok' => false, 'msg' => 'Tap ditemukan tapi tidak ada yang cocok karyawan. Tidak dikenal: '.implode(', ', $missing_fingers)];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->trans_begin();
        $this->dat_reader->upsert_mirror($mirror_rows, $now);
        $this->dat_reader->sync_work_from_mirror($user_ids, $from, $to, $now);
        if (!$this->db->trans_status()) { $this->db->trans_rollback(); return ['ok' => false, 'msg' => 'Gagal simpan mirror/work.']; }
        $this->db->trans_commit();

        return ['ok' => true, 'synced' => count($mirror_rows), 'missing' => $missing, 'missing_fingers' => $missing_fingers];
    }

    /** pertama=datang, terakhir=pulang, tengah-pertama=out_ist, tengah-terakhir=in_ist. */
    private function _classify_positional($times) {
        $slot = ['datang' => null, 'out_ist' => null, 'in_ist' => null, 'pulang' => null];
        $n = count($times);
        if ($n === 0) return $slot;
        $slot['datang'] = $times[0];
        if ($n >= 2) $slot['pulang'] = $times[$n - 1];
        $middle = array_slice($times, 1, max(0, $n - 2));
        if (count($middle) >= 1) $slot['out_ist'] = $middle[0];
        if (count($middle) >= 2) $slot['in_ist']  = $middle[count($middle) - 1];
        return $slot;
    }

    private function _rows_response($branch_id, $from, $to, $extra = []) {
        $rows = $this->dat_reader->get_work_range($branch_id, $from, $to);
        $uids = array_values(array_unique(array_map(function($r){ return (int)$r['user_id']; }, $rows)));
        $existing = $this->dat_reader->presence_existing_pairs($uids, $from, $to);

        $out = [];
        foreach ($rows as $r) {
            $in_presence = isset($existing[$r['user_id'].'|'.$r['flow_date']]);
            $out[] = [
                'key' => $r['user_id'].'|'.$r['flow_date'],
                'user_id' => (int)$r['user_id'],
                'employee_code' => $r['employee_code'],
                'employee_name' => trim($r['first_name'].' '.$r['last_name']),
                'date' => $r['flow_date'], 'weekday' => get_dayname($r['flow_date']),
                'datang' => $r['datang'] ?: '', 'out_ist' => $r['out_ist'] ?: '',
                'in_ist' => $r['in_ist'] ?: '', 'pulang' => $r['pulang'] ?: '',
                'mirror' => [
                    'datang' => $r['m_datang'] ?: '', 'out_ist' => $r['m_out_ist'] ?: '',
                    'in_ist' => $r['m_in_ist'] ?: '', 'pulang' => $r['m_pulang'] ?: '',
                ],
                'tap_count' => (int)$r['tap_count'], 'all_taps' => $r['all_taps'], 'source' => $r['source'],
                'is_edited' => (int)$r['is_edited'], 'pushed_at' => $r['pushed_at'], 'in_presence' => $in_presence,
            ];
        }
        return array_merge([
            'status' => true, 'from' => $from, 'to' => $to,
            'rows' => $out, 'recap' => $this->_recap($out),
        ], $extra);
    }

    private function _recap($rows) {
        $emp = []; $tap = 0; $lengkap = 0; $tidak = 0; $edited = 0; $inpres = 0; $pushed = 0;
        foreach ($rows as $r) {
            $emp[$r['user_id']] = true;
            $tap += $r['tap_count'];
            if ($r['datang'] !== '' && $r['pulang'] !== '') $lengkap++; else $tidak++;
            if ($r['is_edited']) $edited++;
            if ($r['in_presence']) $inpres++;
            if (!empty($r['pushed_at'])) $pushed++;
        }
        return [
            'karyawan' => count($emp), 'hari_absen' => count($rows), 'total_tap' => $tap,
            'hadir_lengkap' => $lengkap, 'tidak_lengkap' => $tidak,
            'diedit' => $edited, 'sudah_di_presence' => $inpres, 'sudah_didorong' => $pushed,
        ];
    }

    private function _branch_user_ids($branch_id, $uids) {
        if (empty($uids)) return [];
        $rows = $this->db->select('u.id')->from('users u')->join('position p', 'p.id = u.position_id', 'left')
            ->where('p.branch_id', (int)$branch_id)->where_in('u.id', $uids)->get()->result_array();
        $out = [];
        foreach ($rows as $r) { $out[(int)$r['id']] = true; }
        return $out;
    }

    private function _user_branch_map($uids) {
        $out = [];
        if (empty($uids)) return $out;
        $rows = $this->db->select('u.id, p.branch_id')->from('users u')->join('position p', 'p.id = u.position_id', 'left')
            ->where_in('u.id', $uids)->get()->result_array();
        foreach ($rows as $r) { $out[(int)$r['id']] = (int)$r['branch_id']; }
        return $out;
    }

    private function _shift_map($uids, $from, $to) {
        $map = [];
        if (empty($uids)) return $map;
        $rows = $this->db->select('users_shift_additional.user_id, users_shift_additional.additional_date, shift.start_time_late, shift.rest_time_range')
            ->join('shift', 'shift.id = users_shift_additional.shift_id')
            ->where_in('users_shift_additional.user_id', $uids)
            ->where('additional_date >=', $from)->where('additional_date <=', $to)
            ->where('additional_type', 'work')
            ->where('users_shift_additional.deleted_at IS NULL', null, false)
            ->get('users_shift_additional')->result_array();
        foreach ($rows as $s) { $map[$s['user_id'].'|'.$s['additional_date']] = $s; }
        return $map;
    }

    /** Periode payroll yang memuat $date (identitas = bulan akhir periode). */
    private function _period_of_date($date) {
        $d = (int)substr($date, 8, 2); $m = (int)substr($date, 5, 2); $y = (int)substr($date, 0, 4);
        if ($d >= START_PAYROLL_DATE) { $m++; if ($m > 12) { $m = 1; $y++; } }
        return ['month' => $m, 'year' => $y];
    }

    private function _hms($v) { return preg_match('/^\d{1,2}:\d{2}$/', (string)$v) ? $v.':00' : (string)$v; }

    private function _presence_payload($uid, $date, $r, $shift, $created_at) {
        $p = attlog_payload();
        $p['user_id'] = $uid; $p['flow_date'] = $date; $p['created_at'] = $created_at;
        // Data hasil kurasi admin di DAT Reader = edit manusia — tandai manual
        // supaya sync provenance-aware tidak menimpanya.
        $p['input_by'] = 'manual';
        if (isset($this->userdata->id)) { $p['input_by_user_id'] = $this->userdata->id; }
        if (!empty($r['datang']))  $p['entry_time']    = $date.' '.$this->_hms($r['datang']);
        if (!empty($r['pulang']))  $p['out_time']      = $date.' '.$this->_hms($r['pulang']);
        if (!empty($r['out_ist'])) $p['rest_time_in']  = $date.' '.$this->_hms($r['out_ist']);
        if (!empty($r['in_ist']))  $p['rest_time_out'] = $date.' '.$this->_hms($r['in_ist']);
        if ($shift && !empty($r['datang']) && !empty($shift['start_time_late'])) {
            $p['entry_time_late'] = late_minutes($shift['start_time_late'], $this->_hms($r['datang']));
        }
        if ($shift && !empty($r['out_ist']) && !empty($r['in_ist']) && !empty($shift['rest_time_range'])) {
            $limit = date('H:i:s', strtotime($this->_hms($r['out_ist']).' +'.$shift['rest_time_range'].' minutes'));
            $p['rest_time_late'] = late_minutes($limit, $this->_hms($r['in_ist']));
        }
        return $p;
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pray_classifier
 *
 * Mengubah tap mentah sholat (attendance_tap, machine_type='pray') menjadi baris
 * harian (pray_day): 6 slot waktu (subuh/dzuhur/ashar/maghrib/isha/friday), masing-
 * masing punya in/out + menit telat. Window per SLOT diambil dari `branch`
 * (kolom {slot}_pray_time_in/out/range), BUKAN dari shift individu seperti
 * Attendance_classifier -- semua karyawan 1 cabang berbagi window sholat yang sama.
 *
 * Aturan Jumat/gender direplikasi PERSIS dari hr/Presence::_import_pray_sheet()
 * (paritas dgn sync_pray_machine Python, 20 Agu 2026): karyawati tidak sholat
 * Jumat -- tap Jumat-nya masuk slot dzuhur biasa, bukan slot friday.
 *
 * Satu tap hanya boleh masuk SATU slot (first-match-wins, urutan subuh->friday),
 * sama seperti jalur lama -- supaya hasil klasifikasi konsisten dgn histori data
 * yang sudah diimpor jalur lama selama ini.
 *
 * Tidak menyentuh tabel `presence` sama sekali (itu tugas Pray_deriver_model).
 */
class Pray_classifier
{
    private $CI;
    const PRAYERS = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->library('attendance_ingest');
        $this->CI->load->helper('late');
    }

    /**
     * Klasifikasi ulang rentang tanggal.
     * $user_ids   kosong = semua user yang punya baris pray_day di rentang
     * $only_dirty TRUE = hanya baris needs_reclass=1
     *
     * Return ['days','window','empty','skipped_edited'].
     */
    public function classify_range($user_ids, $from, $to, $only_dirty = FALSE)
    {
        $out = ['days' => 0, 'window' => 0, 'empty' => 0, 'skipped_edited' => 0];

        $targets = $this->_target_days($user_ids, $from, $to, $only_dirty);
        if (empty($targets)) { return $out; }

        $uids = [];
        foreach ($targets as $t) { $uids[(int) $t['user_id']] = (int) $t['user_id']; }
        $uids = array_values($uids);

        $taps        = $this->CI->attendance_ingest->taps_for_range($uids, $from, $to, FALSE, 'pray');
        $employee_map = $this->_employee_map($uids); // id => [branch_id, jenis_kelamin]
        $branch_map  = $this->_branch_pray_map(array_unique(array_column($employee_map, 'branch_id')));
        $now         = date('Y-m-d H:i:s');

        $batch = [];
        foreach ($targets as $t) {
            $uid  = (int) $t['user_id'];
            $key  = $uid.'|'.$t['flow_date'];
            $emp  = isset($employee_map[$uid]) ? $employee_map[$uid] : NULL;

            $times = [];
            if (!empty($taps[$key])) {
                foreach ($taps[$key] as $tap) { $times[] = substr($tap['tap_at'], 11, 8); }
            }
            sort($times);

            $branch = ($emp && isset($branch_map[$emp['branch_id']])) ? $branch_map[$emp['branch_id']] : NULL;
            $is_female = $emp && isset($emp['jenis_kelamin']) && $emp['jenis_kelamin'] === 'P';
            $friday = get_dayname($t['flow_date']) == 'Jumat';

            $slot = $this->classify_times($times, $branch, $friday, $is_female);

            $out['days']++;
            $out[$slot['method'] === 'window' ? 'window' : 'empty']++;
            if (!empty($t['is_edited'])) { $out['skipped_edited']++; }

            $batch[] = [
                'user_id' => $uid, 'flow_date' => $t['flow_date'], 'slot' => $slot,
                'tap_count' => count($times),
                'all_taps' => substr(implode(',', array_map(function ($x) { return substr($x, 0, 5); }, $times)), 0, 255),
            ];
        }

        $this->_save($batch, $now);
        return $out;
    }

    /**
     * Klasifikasi satu hari dari daftar jam 'H:i:s' terurut, window 1 cabang.
     * Return ['subuh'=>['in'=>,'out'=>,'late'=>], ..., 'method'=>'window'|'empty'].
     */
    public function classify_times($times, $branch, $is_friday, $is_female)
    {
        $slot = ['method' => 'empty'];
        foreach (self::PRAYERS as $p) { $slot[$p] = ['in' => NULL, 'out' => NULL, 'late' => 0]; }
        if (empty($times) || empty($branch)) { return $slot; }

        $slot['method'] = 'window';
        foreach ($times as $time) {
            $assigned = false;
            foreach (self::PRAYERS as $pray) {
                if ($assigned) { break; }
                // Karyawati tidak sholat Jumat -- tap Jumat masuk dzuhur biasa.
                if (($is_friday && $pray === 'dzuhur' && !$is_female)
                    || (!$is_friday && $pray === 'friday')
                    || ($is_friday && $pray === 'friday' && $is_female)) {
                    continue;
                }

                $win_in  = isset($branch[$pray.'_pray_time_in'])  ? $branch[$pray.'_pray_time_in']  : NULL;
                $win_out = isset($branch[$pray.'_pray_time_out']) ? $branch[$pray.'_pray_time_out'] : NULL;
                if (empty($win_in) || empty($win_out)) { continue; }

                if ($slot[$pray]['in'] === NULL && $time >= $win_in && $time <= $win_out) {
                    $slot[$pray]['in'] = $time;
                    $assigned = true;
                } elseif ($slot[$pray]['in'] !== NULL && $slot[$pray]['out'] === NULL
                          && $time >= $win_in && $time <= $win_out) {
                    $slot[$pray]['out'] = $time;
                    $assigned = true;
                    $range = isset($branch[$pray.'_pray_time_range']) ? (int) $branch[$pray.'_pray_time_range'] : 0;
                    $limit = date('H:i:s', strtotime($slot[$pray]['in'].' +'.$range.' minutes'));
                    if ($limit <= $win_out && $slot[$pray]['out'] > $limit) {
                        $slot[$pray]['late'] = late_minutes($limit, $slot[$pray]['out']);
                    }
                }
            }
        }

        $has_any = false;
        foreach (self::PRAYERS as $p) { if ($slot[$p]['in'] !== NULL) { $has_any = true; break; } }
        if (!$has_any) { $slot['method'] = 'empty'; }
        return $slot;
    }

    // =====================================================================
    // INTERNAL
    // =====================================================================

    /**
     * Target = gabungan (a) hari yang ADA tap sholat mentah di rentang (attendance_tap
     * machine_type='pray') -- ini yang membuat seed pertama kali bekerja walau
     * pray_day masih kosong -- DAN (b) baris pray_day existing (utk needs_reclass/
     * is_edited tetap ikut walau kebetulan tak ada tap baru di rentang ini).
     * Beda dari Attendance_classifier::_target_days() yang cuma baca attendance_day
     * existing -- attendance_day sendiri sudah lama terisi dari proses lain,
     * pray_day baru dibuat 4 Sep 2026 jadi butuh jalur seed dari tap langsung.
     */
    private function _target_days($user_ids, $from, $to, $only_dirty)
    {
        $edited = [];
        if (!$only_dirty) {
            $this->CI->db->select('user_id, flow_date, is_edited')
                ->where('flow_date >=', $from)->where('flow_date <=', $to)
                ->where('is_edited', 1);
            if (!empty($user_ids)) { $this->CI->db->where_in('user_id', $user_ids); }
            foreach ($this->CI->db->get('pray_day')->result_array() as $r) {
                $edited[$r['user_id'].'|'.$r['flow_date']] = $r;
            }
        }

        if ($only_dirty) {
            $this->CI->db->select('user_id, flow_date, is_edited')
                ->where('flow_date >=', $from)->where('flow_date <=', $to)
                ->where('needs_reclass', 1);
            if (!empty($user_ids)) { $this->CI->db->where_in('user_id', $user_ids); }
            $dirty = $this->CI->db->get('pray_day')->result_array();

            // Tap baru yang BELUM PERNAH punya baris pray_day sama sekali (mis.
            // tanggal baru sejak run sebelumnya) harus tetap ikut diproses walau
            // only_dirty=true -- kalau tidak, begitu tak ada lagi needs_reclass=1
            // tersisa, sync otomatis diam-diam berhenti mengklasifikasi tanggal
            // baru selamanya (bug ditemukan 18 Sep 2026: pray_day macet di baris
            // terakhir tanggal 7 Sep sementara tap terus masuk tiap hari).
            $this->CI->db->select('DISTINCT t.user_id, t.tap_date AS flow_date', FALSE)
                ->from('attendance_tap t')
                ->join('pray_day pd', 'pd.user_id = t.user_id AND pd.flow_date = t.tap_date', 'left')
                ->where('t.machine_type', 'pray')
                ->where('t.user_id IS NOT NULL', NULL, FALSE)
                ->where('t.tap_date >=', $from)->where('t.tap_date <=', $to)
                ->where('pd.user_id IS NULL', NULL, FALSE);
            if (!empty($user_ids)) { $this->CI->db->where_in('t.user_id', $user_ids); }
            $unseeded = $this->CI->db->get()->result_array();

            $out = [];
            foreach ($dirty as $r) { $out[$r['user_id'].'|'.$r['flow_date']] = $r; }
            foreach ($unseeded as $r) {
                $key = $r['user_id'].'|'.$r['flow_date'];
                if (!isset($out[$key])) {
                    $out[$key] = ['user_id' => $r['user_id'], 'flow_date' => $r['flow_date'], 'is_edited' => 0];
                }
            }
            return array_values($out);
        }

        $this->CI->db->select('DISTINCT t.user_id, t.tap_date AS flow_date', FALSE)
            ->from('attendance_tap t')
            ->where('t.machine_type', 'pray')
            ->where('t.user_id IS NOT NULL', NULL, FALSE)
            ->where('t.tap_date >=', $from)->where('t.tap_date <=', $to);
        if (!empty($user_ids)) { $this->CI->db->where_in('t.user_id', $user_ids); }
        $tap_days = $this->CI->db->get()->result_array();

        $out = [];
        foreach ($tap_days as $r) {
            $key = $r['user_id'].'|'.$r['flow_date'];
            $out[$key] = ['user_id' => $r['user_id'], 'flow_date' => $r['flow_date'],
                'is_edited' => isset($edited[$key]) ? 1 : 0];
        }
        foreach ($edited as $key => $r) { if (!isset($out[$key])) { $out[$key] = $r; } }
        return array_values($out);
    }

    private function _employee_map($user_ids)
    {
        $out = [];
        if (empty($user_ids)) { return $out; }
        $rows = $this->CI->db->select('u.id, p.branch_id, u.jenis_kelamin')
            ->from('users u')->join('position p', 'p.id = u.position_id', 'left')
            ->where_in('u.id', $user_ids)->get()->result_array();
        foreach ($rows as $r) {
            $out[(int) $r['id']] = ['branch_id' => (int) $r['branch_id'], 'jenis_kelamin' => $r['jenis_kelamin']];
        }
        return $out;
    }

    private function _branch_pray_map($branch_ids)
    {
        $out = [];
        $branch_ids = array_filter($branch_ids);
        if (empty($branch_ids)) { return $out; }
        $cols = 'id';
        foreach (self::PRAYERS as $p) { $cols .= ",{$p}_pray_time_in,{$p}_pray_time_out,{$p}_pray_time_range"; }
        $rows = $this->CI->db->select($cols)->where_in('id', $branch_ids)->get('branch')->result_array();
        foreach ($rows as $r) { $out[(int) $r['id']] = $r; }
        return $out;
    }

    /** Tulis hasil ke pray_day. Kolom m_* selalu ditimpa; efektif hanya kalau is_edited=0. */
    private function _save($batch, $now)
    {
        if (empty($batch)) { return; }

        foreach (array_chunk($batch, 200) as $chunk) {
            $place = [];
            $vals = [];
            foreach ($chunk as $b) {
                $s = $b['slot'];
                // 2 (user_id,flow_date) + 12 (m_*) + 18 (efektif) + 4 (tap_count,all_taps,method,created_at) = 36
                $place[] = '('.implode(',', array_fill(0, 36, '?')).')';
                array_push($vals, $b['user_id'], $b['flow_date']);
                // m_* (12 kolom: 6 slot x in/out)
                foreach (self::PRAYERS as $p) { array_push($vals, $s[$p]['in'], $s[$p]['out']); }
                // efektif (18 kolom: 6 slot x in/out/late)
                foreach (self::PRAYERS as $p) { array_push($vals, $s[$p]['in'], $s[$p]['out'], $s[$p]['late']); }
                array_push($vals, $b['tap_count'], $b['all_taps'], $s['method'], $now);
            }

            $m_cols = []; $eff_cols = [];
            foreach (self::PRAYERS as $p) { $m_cols[] = "m_{$p}_in"; $m_cols[] = "m_{$p}_out"; }
            foreach (self::PRAYERS as $p) { $eff_cols[] = "{$p}_in"; $eff_cols[] = "{$p}_out"; $eff_cols[] = "{$p}_late"; }

            $upd = [];
            foreach ($m_cols as $c) { $upd[] = "$c = VALUES($c)"; }
            foreach (self::PRAYERS as $p) {
                $upd[] = "{$p}_in = IF(pray_day.is_edited=1, pray_day.{$p}_in, VALUES({$p}_in))";
                $upd[] = "{$p}_out = IF(pray_day.is_edited=1, pray_day.{$p}_out, VALUES({$p}_out))";
                $upd[] = "{$p}_late = IF(pray_day.is_edited=1, pray_day.{$p}_late, VALUES({$p}_late))";
            }
            $upd[] = 'tap_count = VALUES(tap_count)';
            $upd[] = 'all_taps = VALUES(all_taps)';
            $upd[] = 'classify_method = IF(pray_day.is_edited=1, "manual", VALUES(classify_method))';
            $upd[] = 'needs_reclass = 0';
            $upd[] = 'classified_at = VALUES(created_at)';
            $upd[] = 'updated_at = VALUES(created_at)';

            $sql = 'INSERT INTO pray_day (user_id, flow_date, '.implode(',', $m_cols).', '.implode(',', $eff_cols)
                 .', tap_count, all_taps, classify_method, created_at)
                    VALUES '.implode(',', $place).'
                    ON DUPLICATE KEY UPDATE '.implode(', ', $upd);
            $this->CI->db->query($sql, $vals);
        }
    }
}

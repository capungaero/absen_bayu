<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Attendance_classifier
 *
 * Mengubah tap mentah (attendance_tap) menjadi baris harian (attendance_day):
 * datang / keluar istirahat / masuk istirahat / pulang + menit telat.
 *
 * Metode UTAMA = window shift, sama persis dengan jalur import resmi ke
 * `presence` (Attlog_parser::classify_taps). Hari yang tidak punya jadwal, atau
 * yang semua tapnya di luar window, jatuh ke metode POSISIONAL sebagai fallback
 * berlabel — jadwal telat diupload itu kejadian normal di sini, dan membuang
 * fallback akan mengosongkan hari-hari itu secara diam-diam.
 *
 * Karena tap disimpan permanen, hari mana pun bisa diklasifikasi ULANG kapan
 * saja — misalnya setelah jadwal shift akhirnya diupload. Baris yang sudah
 * dikoreksi admin (is_edited=1) tidak pernah tertimpa: hasil mesin selalu
 * masuk ke kolom m_*, penyalinan ke kolom efektif hanya untuk is_edited=0.
 *
 * Tidak menyentuh tabel `presence` sama sekali.
 */
class Attendance_classifier
{
    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->library('attlog_parser');
        $this->CI->load->library('attendance_ingest');
        $this->CI->load->helper('attlog');
        $this->CI->load->helper('late');
        $this->CI->load->helper('schedule');
    }

    /**
     * Klasifikasi ulang rentang tanggal.
     *
     * $user_ids   kosong = semua user yang punya baris attendance_day di rentang
     * $mode       'auto'       window, fallback posisional (default)
     *             'window'     window saja; tanpa jadwal -> baris dikosongkan
     *             'positional' posisional saja
     * $only_dirty TRUE = hanya baris needs_reclass=1
     *
     * Return ['days','window','positional','empty','skipped_edited'].
     */
    public function classify_range($user_ids, $from, $to, $mode = 'auto', $only_dirty = FALSE)
    {
        $out = ['days' => 0, 'window' => 0, 'positional' => 0, 'empty' => 0, 'skipped_edited' => 0];

        $targets = $this->_target_days($user_ids, $from, $to, $only_dirty);
        if (empty($targets)) { return $out; }

        $uids = [];
        foreach ($targets as $t) { $uids[(int) $t['user_id']] = (int) $t['user_id']; }
        $uids = array_values($uids);

        $taps      = $this->CI->attendance_ingest->taps_for_range($uids, $from, $to);
        $shift_map = $this->shift_map($uids, $from, $to);
        $now       = date('Y-m-d H:i:s');

        $batch = [];
        foreach ($targets as $t) {
            $key   = $t['user_id'].'|'.$t['flow_date'];
            $times = [];
            if (!empty($taps[$key])) {
                foreach ($taps[$key] as $tap) { $times[] = substr($tap['tap_at'], 11, 8); }
            }
            sort($times);

            $shift = isset($shift_map[$key]) ? $shift_map[$key] : NULL;
            $slot  = $this->classify_times($times, $shift, $mode);

            $out['days']++;
            $out[$slot['method'] === 'window' ? 'window' : ($slot['method'] === 'positional' ? 'positional' : 'empty')]++;
            if (!empty($t['is_edited'])) { $out['skipped_edited']++; }

            $batch[] = [
                'user_id'   => (int) $t['user_id'],
                'flow_date' => $t['flow_date'],
                'slot'      => $slot,
                'tap_count' => count($times),
                'all_taps'  => substr(implode(',', array_map(function ($x) { return substr($x, 0, 5); }, $times)), 0, 255),
                'shift_id'  => $shift ? (int) $shift['shift_id'] : NULL,
            ];
        }

        $this->_save($batch, $now);
        return $out;
    }

    /**
     * Klasifikasi satu hari dari daftar jam 'H:i:s' terurut.
     *
     * Window: pakai jam-jam batas shift, identik Attlog_parser::classify_taps —
     * datang = tap PERTAMA di window masuk, pulang = tap TERAKHIR di window
     * pulang; telat memakai late_minutes yang membuang detik.
     * Posisional: pertama=datang, terakhir=pulang,
     * tengah-pertama=keluar istirahat, tengah-terakhir=masuk istirahat.
     *
     * Return ['entry','rest_in','rest_out','out','entry_late','rest_late','method'].
     */
    public function classify_times($times, $shift, $mode = 'auto')
    {
        $slot = [
            'entry' => NULL, 'rest_in' => NULL, 'rest_out' => NULL, 'out' => NULL,
            'entry_late' => 0, 'rest_late' => 0, 'method' => 'empty',
        ];
        if (empty($times)) { return $slot; }

        if ($mode !== 'positional' && !empty($shift)) {
            $w = $this->_classify_window($times, $shift);
            if ($w !== NULL) { return $w; }
        }

        if ($mode === 'window') { return $slot; }

        return $this->_classify_positional($times);
    }

    /** Peta shift lengkap (kolom window) per "user_id|tanggal", jadwal terbaru saja. */
    public function shift_map($user_ids, $from, $to)
    {
        $map = [];
        if (empty($user_ids)) { return $map; }

        $rows = $this->CI->db
            ->select('users_shift_additional.user_id, users_shift_additional.additional_date,
                      shift.id AS shift_id, shift.shift_code,
                      shift.start_time_in, shift.start_time_out, shift.start_time_late,
                      shift.end_time_in, shift.end_time_out,
                      shift.start_time_rest, shift.end_time_rest, shift.rest_time_range')
            ->join('shift', 'shift.id = users_shift_additional.shift_id')
            ->where_in('users_shift_additional.user_id', $user_ids)
            ->where('users_shift_additional.additional_type', 'work')
            ->where('users_shift_additional.additional_date >=', $from)
            ->where('users_shift_additional.additional_date <=', $to)
            ->where(latest_schedule_subquery(), NULL, FALSE)
            ->get('users_shift_additional')->result_array();

        foreach ($rows as $r) {
            $map[$r['user_id'].'|'.$r['additional_date']] = $r;
        }
        return $map;
    }

    // =====================================================================
    // INTERNAL
    // =====================================================================

    /** Baris attendance_day yang jadi sasaran klasifikasi. */
    private function _target_days($user_ids, $from, $to, $only_dirty)
    {
        $this->CI->db->select('user_id, flow_date, is_edited')
            ->where('flow_date >=', $from)
            ->where('flow_date <=', $to);
        if (!empty($user_ids)) { $this->CI->db->where_in('user_id', $user_ids); }
        if ($only_dirty)       { $this->CI->db->where('needs_reclass', 1); }

        return $this->CI->db->get('attendance_day')->result_array();
    }

    /** Return NULL kalau tidak ada satu pun tap yang masuk window shift. */
    private function _classify_window($times, $shift)
    {
        $slot = [
            'entry' => NULL, 'rest_in' => NULL, 'rest_out' => NULL, 'out' => NULL,
            'entry_late' => 0, 'rest_late' => 0, 'method' => 'window',
        ];

        foreach ($times as $time) {
            if ($slot['entry'] === NULL
                && attlog_time_between($time, $shift['start_time_in'], $shift['start_time_out'])) {
                $slot['entry'] = $time;
                $slot['entry_late'] = late_minutes($shift['start_time_late'], $time);
                continue;
            }

            // Jam pulang = tap TERAKHIR di window pulang (lihat Attlog_parser).
            if (attlog_time_between($time, $shift['end_time_in'], $shift['end_time_out'])) {
                $slot['out'] = $time;
                continue;
            }

            if (attlog_time_between($time, $shift['start_time_rest'], $shift['end_time_rest'])) {
                if ($slot['rest_in'] === NULL) {
                    $slot['rest_in'] = $time;
                } elseif ($slot['rest_out'] === NULL) {
                    $slot['rest_out'] = $time;
                    $limit = date('H:i:s', strtotime($slot['rest_in'].' +'.$shift['rest_time_range'].' minutes'));
                    $slot['rest_late'] = late_minutes($limit, $time);
                }
            }
        }

        if ($slot['entry'] === NULL && $slot['out'] === NULL
            && $slot['rest_in'] === NULL && $slot['rest_out'] === NULL) {
            return NULL;
        }
        return $slot;
    }

    private function _classify_positional($times)
    {
        $slot = [
            'entry' => NULL, 'rest_in' => NULL, 'rest_out' => NULL, 'out' => NULL,
            'entry_late' => 0, 'rest_late' => 0, 'method' => 'positional',
        ];
        $n = count($times);
        if ($n === 0) { $slot['method'] = 'empty'; return $slot; }

        $slot['entry'] = $times[0];
        if ($n >= 2) { $slot['out'] = $times[$n - 1]; }

        $middle = array_slice($times, 1, max(0, $n - 2));
        if (count($middle) >= 1) { $slot['rest_in']  = $middle[0]; }
        if (count($middle) >= 2) { $slot['rest_out'] = $middle[count($middle) - 1]; }

        return $slot;
    }

    /**
     * Tulis hasil ke attendance_day. Kolom m_* SELALU ditimpa (itu cerminan
     * mesin); kolom efektif hanya ikut kalau is_edited=0 — koreksi admin
     * dipertahankan lewat IF() di ON DUPLICATE KEY UPDATE.
     */
    private function _save($batch, $now)
    {
        if (empty($batch)) { return; }

        foreach (array_chunk($batch, 300) as $chunk) {
            $place = [];
            $vals  = [];
            foreach ($chunk as $b) {
                $s = $b['slot'];
                $place[] = '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                array_push($vals,
                    $b['user_id'], $b['flow_date'],
                    $s['entry'], $s['rest_in'], $s['rest_out'], $s['out'],
                    $s['entry'], $s['rest_in'], $s['rest_out'], $s['out'],
                    $s['entry_late'], $s['rest_late'],
                    $b['tap_count'], $b['all_taps'], $s['method'], $b['shift_id'], $now);
            }

            $sql = 'INSERT INTO attendance_day
                      (user_id, flow_date,
                       m_entry_time, m_rest_in, m_rest_out, m_out_time,
                       entry_time, rest_in, rest_out, out_time,
                       entry_late, rest_late, tap_count, all_taps,
                       classify_method, shift_id, created_at)
                    VALUES '.implode(',', $place).'
                    ON DUPLICATE KEY UPDATE
                      m_entry_time = VALUES(m_entry_time),
                      m_rest_in    = VALUES(m_rest_in),
                      m_rest_out   = VALUES(m_rest_out),
                      m_out_time   = VALUES(m_out_time),
                      entry_time = IF(attendance_day.is_edited=1, attendance_day.entry_time, VALUES(entry_time)),
                      rest_in    = IF(attendance_day.is_edited=1, attendance_day.rest_in,    VALUES(rest_in)),
                      rest_out   = IF(attendance_day.is_edited=1, attendance_day.rest_out,   VALUES(rest_out)),
                      out_time   = IF(attendance_day.is_edited=1, attendance_day.out_time,   VALUES(out_time)),
                      entry_late = IF(attendance_day.is_edited=1, attendance_day.entry_late, VALUES(entry_late)),
                      rest_late  = IF(attendance_day.is_edited=1, attendance_day.rest_late,  VALUES(rest_late)),
                      tap_count       = VALUES(tap_count),
                      all_taps        = VALUES(all_taps),
                      classify_method = IF(attendance_day.is_edited=1, "manual", VALUES(classify_method)),
                      shift_id        = VALUES(shift_id),
                      needs_reclass   = 0,
                      classified_at   = VALUES(created_at),
                      updated_at      = VALUES(created_at)';
            $this->CI->db->query($sql, $vals);
        }
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Attlog_parser
 *
 * Parser pure untuk konten .dat dari mesin Solution Cloud. Setiap baris
 * berformat: <finger_id>\t<YYYY-MM-DD>\t<HH:MM:SS>[\t<status>\t<verify>].
 *
 * Method publik tidak menyentuh database — semua input/output via argumen
 * dan return value. Cocok dipanggil dari controller import maupun dari
 * smoke test (lihat scripts/smoke_attlog_parse.php).
 */
class Attlog_parser
{
    /**
     * Parse raw text .dat. Baris dengan tanggal di luar [period_from, period_to]
     * di-skip tanpa increment invalid_count.
     *
     * Return:
     *   [
     *     'rows' => [
     *       finger_id => [
     *         'YYYY-MM-DD' => ['date' => 'YYYY-MM-DD', 'time' => ['HH:MM:SS', ...]]
     *       ]
     *     ],
     *     'stats' => [
     *       'total_lines' => int,        // baris non-kosong
     *       'raw_count' => int,          // tap valid yang masuk period
     *       'invalid_count' => int,      // baris dengan format/datetime salah
     *       'unique_finger_ids' => int,
     *     ],
     *     'period' => ['from' => string, 'to' => string],
     *   ]
     */
    public function parse_taps($raw, $period_from, $period_to)
    {
        $lines = preg_split('/\r\n|\r|\n/', trim((string) $raw));
        $rows = [];
        $stats = [
            'total_lines'   => 0,
            'raw_count'     => 0,
            'invalid_count' => 0,
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') { continue; }
            $stats['total_lines']++;

            $cols = preg_split('/\s+/', $line);
            if (count($cols) < 3) { $stats['invalid_count']++; continue; }

            $finger_id = trim($cols[0]);
            $datetime = $cols[1].' '.$cols[2];
            $timestamp = strtotime($datetime);
            if ($finger_id === '' || !$timestamp) {
                $stats['invalid_count']++;
                continue;
            }

            $date = date('Y-m-d', $timestamp);
            if ($date < $period_from || $date > $period_to) { continue; }

            if (!isset($rows[$finger_id][$date])) {
                $rows[$finger_id][$date] = [
                    'date' => $date,
                    'time' => [],
                ];
            }
            $rows[$finger_id][$date]['time'][] = date('H:i:s', $timestamp);
            $stats['raw_count']++;
        }

        $stats['unique_finger_ids'] = count($rows);

        return [
            'rows'   => $rows,
            'stats'  => $stats,
            'period' => ['from' => $period_from, 'to' => $period_to],
        ];
    }

    /**
     * Parse raw text .dat menjadi daftar tap DATAR, tanpa filter periode.
     *
     * Dipakai lapis raw (attendance_tap): arsip menyimpan semua tap apa adanya,
     * pemotongan periode itu urusan tampilan. Over-ingest tidak mahal karena
     * penulisan memakai INSERT IGNORE pada UNIQUE (machine_sn, finger_id, tap_at).
     *
     * Berbeda dari parse_taps() yang mengelompokkan per finger/tanggal dan
     * membuang tap di luar periode. parse_taps() JANGAN diubah -- masih dipakai
     * hr/Presence::_import_attlog_dat dan scripts/smoke_attlog_parse.php.
     *
     * Return:
     *   [
     *     'taps' => [
     *       ['finger_id' => string, 'tap_at' => 'Y-m-d H:i:s', 'tap_date' => 'Y-m-d',
     *        'raw_status' => string|null, 'raw_verify' => string|null],
     *       ...
     *     ],
     *     'stats' => ['total_lines', 'raw_count', 'invalid_count',
     *                 'unique_finger_ids', 'first_tap_at', 'last_tap_at'],
     *   ]
     */
    public function parse_tap_list($raw)
    {
        $lines = preg_split('/\r\n|\r|\n/', trim((string) $raw));
        $taps = [];
        $fingers = [];
        $stats = [
            'total_lines'   => 0,
            'raw_count'     => 0,
            'invalid_count' => 0,
        ];
        $first = null;
        $last = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') { continue; }
            $stats['total_lines']++;

            $cols = preg_split('/\s+/', $line);
            if (count($cols) < 3) { $stats['invalid_count']++; continue; }

            $finger_id = trim($cols[0]);
            $timestamp = strtotime($cols[1].' '.$cols[2]);
            if ($finger_id === '' || !$timestamp) {
                $stats['invalid_count']++;
                continue;
            }

            $tap_at = date('Y-m-d H:i:s', $timestamp);
            $taps[] = [
                'finger_id'  => $finger_id,
                'tap_at'     => $tap_at,
                'tap_date'   => date('Y-m-d', $timestamp),
                'raw_status' => isset($cols[3]) ? substr(trim($cols[3]), 0, 8) : null,
                'raw_verify' => isset($cols[4]) ? substr(trim($cols[4]), 0, 8) : null,
            ];
            $fingers[$finger_id] = true;
            $stats['raw_count']++;
            if ($first === null || $tap_at < $first) { $first = $tap_at; }
            if ($last === null || $tap_at > $last) { $last = $tap_at; }
        }

        $stats['unique_finger_ids'] = count($fingers);
        $stats['first_tap_at'] = $first;
        $stats['last_tap_at'] = $last;

        return ['taps' => $taps, 'stats' => $stats];
    }

    /**
     * Klasifikasi tap menjadi entry/out/rest berdasarkan window shift.
     *
     * Input:
     *   $row_data     hasil parse_taps()['rows']: finger_id => date => {date, time[]}
     *   $employee_map finger_id => date => {id, ...} (dari attendance_employee_resolver)
     *   $shift_map    "<user_id>|<date>" => row shift (start_time_in/out, start_time_late,
     *                 end_time_in/out, start_time_rest, end_time_rest, rest_time_range)
     *   $use_schedule kalau false, semua tap diisi via attlog_apply_fallback()
     *   $created_at   timestamp Y-m-d H:i:s yang ditempel di setiap payload
     *
     * Output:
     *   [
     *     'rows'      => [employee_id => date => payload],  // siap di-insert ke presence
     *     'to_delete' => [employee_id => [date, ...]],      // pasangan delete-before-insert
     *     'stats'     => [
     *        'matched_employee' => int,
     *        'missing_employee' => int,
     *        'no_schedule'      => int,  // shift kosong padahal use_schedule=true
     *        'no_window_match'  => int,  // semua tap di luar window shift
     *        'fallback_rows'    => int,  // payload diisi via fallback (use_schedule=false)
     *     ],
     *   ]
     *
     * Pure function: tidak menyentuh DB, tidak modify input.
     */
    public function classify_taps($row_data, $employee_map, $shift_map, $use_schedule, $created_at)
    {
        $input = [];
        $to_delete = [];
        $stats = [
            'matched_employee' => 0,
            'missing_employee' => 0,
            'no_schedule'      => 0,
            'no_window_match'  => 0,
            'fallback_rows'    => 0,
        ];

        foreach ($row_data as $finger_id => $dates) {
            foreach ($dates as $row) {
                $date = $row['date'];
                $employee = isset($employee_map[$finger_id][$date]) ? $employee_map[$finger_id][$date] : null;

                if (empty($employee)) {
                    $stats['missing_employee']++;
                    continue;
                }
                $stats['matched_employee']++;

                $emp_id = $employee['id'];
                if (!isset($to_delete[$emp_id])) { $to_delete[$emp_id] = []; }
                if (!in_array($date, $to_delete[$emp_id])) { $to_delete[$emp_id][] = $date; }

                $shift_key = $emp_id.'|'.$date;
                $shift = isset($shift_map[$shift_key]) ? $shift_map[$shift_key] : [];

                $times = $row['time'];
                sort($times);

                $payload = attlog_payload();
                $payload['user_id'] = $emp_id;
                $payload['flow_date'] = $date;
                $payload['created_at'] = $created_at;

                if (!$use_schedule) {
                    attlog_apply_fallback($payload, $date, $times);
                    $input[$emp_id][$date] = $payload;
                    $stats['fallback_rows']++;
                    continue;
                }

                if (empty($shift)) {
                    $stats['no_schedule']++;
                    continue;
                }

                foreach ($times as $time) {
                    $d_day = $date.' '.$time;

                    if (attlog_time_between($time, $shift['start_time_in'], $shift['start_time_out']) && $payload['entry_time'] == '') {
                        $payload['entry_time'] = $d_day;
                        $payload['entry_time_late'] = late_minutes($shift['start_time_late'], $time);
                        continue;
                    }

                    // Jam pulang = tap TERAKHIR di window pulang.
                    //
                    // Aturan lama memakai tap PERTAMA, sehingga karyawan yang
                    // sempat tap di lokasi lain sebelum benar-benar pulang
                    // kehilangan jam kerja (mis. tap 19:45 di Gambir lalu 20:08
                    // di Sudirman -> tercatat pulang 19:45).
                    //
                    // CATATAN: sync Python di VPS masih memakai tap pertama.
                    // Selama keduanya berjalan berdampingan, jam pulang akan
                    // berbeda; perbedaan hilang setelah VPS ikut memakai jalur ini.
                    if (attlog_time_between($time, $shift['end_time_in'], $shift['end_time_out'])) {
                        $payload['out_time'] = $d_day;
                        continue;
                    }

                    if (attlog_time_between($time, $shift['start_time_rest'], $shift['end_time_rest'])) {
                        if ($payload['rest_time_in'] == '') {
                            $payload['rest_time_in'] = $d_day;
                        } else if ($payload['rest_time_out'] == '') {
                            $payload['rest_time_out'] = $d_day;
                            $limit = date('H:i:s', strtotime($payload['rest_time_in'].' +'.$shift['rest_time_range'].' minutes'));
                            $payload['rest_time_late'] = late_minutes($limit, $time);
                        }
                    }
                }

                if ($payload['entry_time'] == '' && $payload['out_time'] == ''
                    && $payload['rest_time_in'] == '' && $payload['rest_time_out'] == '') {
                    $stats['no_window_match']++;
                    continue;
                }

                $input[$emp_id][$date] = $payload;
            }
        }

        return [
            'rows'      => $input,
            'to_delete' => $to_delete,
            'stats'     => $stats,
        ];
    }
}

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

                    if (attlog_time_between($time, $shift['end_time_in'], $shift['end_time_out']) && $payload['out_time'] == '') {
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

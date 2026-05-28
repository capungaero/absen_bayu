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
}

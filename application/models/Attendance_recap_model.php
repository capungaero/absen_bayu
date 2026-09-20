<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Attendance_recap_model
 *
 * Menyusun ulang tabel attendance_recap (rekap harian absen KERJA + OFF/Libur,
 * sudah final dihitung terhadap jadwal shift) dari attendance_day +
 * users_shift_additional. Tidak menyentuh presence sama sekali -- terpisah
 * dari siklus sync presence utama supaya tetap segar walau sync utama lag.
 *
 * Karyawan yang dijadwalkan shift 'work' tapi belum ada tap sama sekali di
 * attendance_day tetap disertakan dengan status 'belum_absen'. Karyawan
 * berjadwal 'free' (OFF/Libur) disertakan dengan status 'off' -- shift_id
 * dkk akan NULL karena hari OFF memang tidak punya shift (paritas dengan
 * _has_work_shift() di Attendance_daily_report_model.php).
 */
class Attendance_recap_model extends CI_Model
{
    /** Return ['days' => int] jumlah baris user+tanggal yang ditulis/diperbarui. */
    public function refresh($from, $to)
    {
        $sql = "
            SELECT
                usa.additional_date AS flow_date,
                usa.additional_type,
                u.id AS user_id,
                u.employee_code,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
                p.branch_id,
                b.branch_name,
                p.position_name,
                s.id AS shift_id,
                s.shift_name,
                s.start_time_in AS shift_time_in,
                s.end_time_out AS shift_time_out,
                ad.entry_time,
                ad.out_time,
                ad.entry_late
            FROM users_shift_additional usa
            JOIN (
                SELECT user_id, additional_date, MAX(id) AS id
                FROM users_shift_additional
                WHERE additional_date >= ? AND additional_date <= ?
                GROUP BY user_id, additional_date
            ) latest_usa ON latest_usa.id = usa.id
            JOIN users u ON u.id = usa.user_id
            JOIN position p ON p.id = u.position_id
            JOIN branch b ON b.id = p.branch_id
            LEFT JOIN shift s ON s.id = usa.shift_id
            LEFT JOIN attendance_day ad ON ad.user_id = u.id AND ad.flow_date = usa.additional_date
            WHERE u.active = 1
              AND usa.additional_type IN ('work', 'free')
              AND usa.additional_date >= ?
              AND usa.additional_date <= ?
              AND usa.additional_date <= CURDATE()
        ";
        $rows = $this->db->query($sql, [$from, $to, $from, $to])->result_array();
        $this->_save($rows);
        return ['days' => count($rows)];
    }

    private function _save($rows)
    {
        if (empty($rows)) { return; }

        $now = date('Y-m-d H:i:s');
        foreach (array_chunk($rows, 300) as $chunk) {
            $place = [];
            $vals  = [];
            foreach ($chunk as $r) {
                $late = (int) $r['entry_late'];
                if ($r['additional_type'] === 'free') {
                    $status = 'off';
                } elseif ($r['entry_time'] === NULL && $r['out_time'] === NULL) {
                    $status = 'belum_absen';
                } elseif ($late > 0) {
                    $status = 'terlambat';
                } else {
                    $status = 'hadir';
                }

                $place[] = '('.implode(',', array_fill(0, 16, '?')).')';
                array_push($vals,
                    $r['user_id'], $r['flow_date'], $r['employee_code'], $r['employee_name'],
                    $r['branch_id'], $r['branch_name'], $r['position_name'],
                    $r['shift_id'], $r['shift_name'], $r['shift_time_in'], $r['shift_time_out'],
                    $r['entry_time'], $r['out_time'], $late, $status, $now
                );
            }

            $sql = 'INSERT INTO attendance_recap
                      (user_id, flow_date, employee_code, employee_name,
                       branch_id, branch_name, position_name,
                       shift_id, shift_name, shift_time_in, shift_time_out,
                       entry_time, out_time, late_minutes, status, updated_at)
                    VALUES '.implode(',', $place).'
                    ON DUPLICATE KEY UPDATE
                      employee_code = VALUES(employee_code),
                      employee_name = VALUES(employee_name),
                      branch_id = VALUES(branch_id),
                      branch_name = VALUES(branch_name),
                      position_name = VALUES(position_name),
                      shift_id = VALUES(shift_id),
                      shift_name = VALUES(shift_name),
                      shift_time_in = VALUES(shift_time_in),
                      shift_time_out = VALUES(shift_time_out),
                      entry_time = VALUES(entry_time),
                      out_time = VALUES(out_time),
                      late_minutes = VALUES(late_minutes),
                      status = VALUES(status),
                      updated_at = VALUES(updated_at)';
            $this->db->query($sql, $vals);
        }
    }
}

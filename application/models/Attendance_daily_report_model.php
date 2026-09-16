<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Attendance_daily_report_model extends CI_Model {

    public function build($date = null, $type = 'pagi', $lacak_attendance = []) {
        $date = $date ?: date('Y-m-d');
        $rows = $this->db->query($this->_query(), [$date, $date, $date])->result_array();
        $branches = [];
        $missing_shift = [];

        foreach ($rows as $row) {
            $employee = $this->_employee($row);
            if (!$this->_has_work_shift($row)) {
                if ($row['additional_type'] !== 'free') {
                    $missing_shift[] = $employee;
                }
                continue;
            }

            if (!$this->_matches_report_period($row, $type)) {
                continue;
            }

            $lacak = $lacak_attendance[(string)$row['employee_code']] ?? null;
            $status = $this->_status($row, $lacak);

            $branch_key = $employee['branch_name'];
            if (!isset($branches[$branch_key])) {
                $branches[$branch_key] = $this->_empty_branch($employee);
            }
            $position_key = $employee['position_name'];
            if (!isset($branches[$branch_key]['positions'][$position_key])) {
                $branches[$branch_key]['positions'][$position_key] = $this->_empty_position($position_key);
            }

            $detail = array_merge($employee, [
                'shift_code' => $row['shift_code'],
                'shift_name' => $row['shift_name'],
                'entry_time' => $status === 'hadir_gps' ? $lacak['time'] : $row['entry_time'],
                'late_minutes' => (int)$row['late_minutes'],
                'vehicle' => $status === 'hadir_gps' ? $lacak['vehicle'] : '',
                'source' => $status === 'hadir_gps' ? 'Lacak' : ($row['entry_time'] ? 'Fingerprint' : ''),
                'status' => $status,
            ]);

            $branches[$branch_key]['total']++;
            $branches[$branch_key][$this->_bucket($status)]++;
            $branches[$branch_key]['positions'][$position_key]['total']++;
            $branches[$branch_key]['positions'][$position_key][$this->_bucket($status)]++;
            $branches[$branch_key]['positions'][$position_key]['details'][] = $detail;
        }

        $totals = [
            'scheduled' => 0, 'hadir' => 0, 'terlambat' => 0, 'hadir_gps' => 0,
            'izin' => 0, 'sakit' => 0, 'off' => 0, 'belum' => 0,
            'missing_shift' => count($missing_shift),
        ];
        $branch_order = ['Sudirman' => 0, 'Gambir' => 1, 'Kanvas' => 2];
        uasort($branches, function ($left, $right) use ($branch_order) {
            return ($branch_order[$left['branch_name']] ?? 99) <=> ($branch_order[$right['branch_name']] ?? 99);
        });
        foreach ($branches as &$branch) {
            $branch['percent'] = $this->_percent($branch);
            foreach ($branch['positions'] as &$position) {
                $position['percent'] = $this->_percent($position);
            }
            unset($position);
            ksort($branch['positions']);
            $branch['positions'] = array_values($branch['positions']);

            $totals['scheduled'] += $branch['total'];
            foreach (['hadir', 'terlambat', 'hadir_gps', 'izin', 'sakit', 'off', 'belum'] as $field) {
                $totals[$field] += $branch[$field];
            }
        }
        unset($branch);

        return [
            'date' => $date,
            'generated_at' => date('Y-m-d H:i:s'),
            'branches' => array_values($branches),
            'missing_shift' => $missing_shift,
            'totals' => $totals,
        ];
    }

    /** "Hadir" utk rasio X/Y = hadir fingerprint + terlambat + hadir GPS (bukan izin/sakit/off/alfa). */
    private function _percent($group) {
        $present = $group['hadir'] + $group['terlambat'] + $group['hadir_gps'];
        return $group['total'] > 0 ? (int)round(($present / $group['total']) * 100) : 0;
    }

    private function _bucket($status) {
        return $status === 'alfa' ? 'belum' : $status;
    }

    private function _query() {
        return "
            SELECT users.id AS user_id, users.employee_code, users.location,
                TRIM(CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, ''))) AS employee_name,
                COALESCE(position.position_name, '-') AS position_name,
                COALESCE(branch.id, 0) AS branch_id,
                COALESCE(branch.branch_name, 'Tanpa Cabang') AS branch_name,
                usa.id AS schedule_id, usa.additional_type,
                shift.id AS shift_id, shift.shift_code, shift.shift_name,
                shift.is_active AS shift_active, shift.deleted_at AS shift_deleted_at,
                presence.presence_type, ar.entry_time, ar.status AS recap_status,
                COALESCE(ar.late_minutes, 0) AS late_minutes
            FROM users
            LEFT JOIN position ON position.id = users.position_id
            LEFT JOIN branch ON branch.id = position.branch_id
            LEFT JOIN (
                SELECT usa1.* FROM users_shift_additional usa1
                JOIN (
                    SELECT user_id, MAX(id) AS id FROM users_shift_additional
                    WHERE additional_date = ? AND deleted_at IS NULL GROUP BY user_id
                ) latest_usa ON latest_usa.id = usa1.id
            ) usa ON usa.user_id = users.id
            LEFT JOIN shift ON shift.id = usa.shift_id
            LEFT JOIN attendance_recap ar ON ar.user_id = users.id AND ar.flow_date = ?
            LEFT JOIN (
                SELECT p1.* FROM presence p1
                JOIN (
                    SELECT user_id, MAX(id) AS id FROM presence
                    WHERE flow_date = ? AND presence_status = 'approved' GROUP BY user_id
                ) latest_presence ON latest_presence.id = p1.id
            ) presence ON presence.user_id = users.id
            WHERE users.active = '1'
            ORDER BY CASE
                WHEN UPPER(users.location) LIKE '%SUDIRMAN%' THEN 1
                WHEN UPPER(users.location) LIKE '%GAMBIR%' THEN 2
                WHEN UPPER(users.location) LIKE '%KANVAS%' THEN 3
                ELSE 4 END,
                position.position_name, users.first_name, users.last_name
        ";
    }

    /** additional_type='free' (OFF/Libur) LOLOS di sini -- statusnya ditentukan di _status(). */
    private function _has_work_shift($row) {
        if ($row['additional_type'] === 'free') { return true; }
        $code = strtoupper(trim((string)$row['shift_code']));
        $name = strtoupper(trim((string)$row['shift_name']));
        return $row['additional_type'] === 'work'
            && !empty($row['shift_id'])
            && $row['shift_active'] === '1'
            && empty($row['shift_deleted_at'])
            && $code !== '' && $code !== '-' && $code !== 'NO-SC'
            && $name !== 'NO SCHEDULE';
    }

    private function _matches_report_period($row, $type) {
        if ($row['additional_type'] === 'free') { return true; }
        $code = strtoupper(trim((string)$row['shift_code']));
        $name = strtoupper(trim((string)$row['shift_name']));
        if ($type === 'siang') {
            return strpos($name, 'SIANG') === 0 || strpos($code, 'S') === 0;
        }
        return strpos($name, 'PAGI') === 0
            || $name === 'KANVAS'
            || $code === 'KVS';
    }

    private function _status($row, $lacak = null) {
        if ($row['additional_type'] === 'free') return 'off';
        if ($row['presence_type'] === 'sakit') return 'sakit';
        if (in_array($row['presence_type'], ['izin', 'cuti'], true)) return 'izin';
        // Tap Lacak (kanvas GPS) = status sendiri "Hadir GPS", terpisah dari
        // fingerprint -- sesuai format rekap PDF acuan (bukan digabung ke hadir biasa).
        if ($lacak) return 'hadir_gps';
        if ($row['recap_status'] === 'terlambat') return 'terlambat';
        if ($row['recap_status'] === 'hadir') return 'hadir';
        return 'alfa';
    }

    private function _employee($row) {
        $report_branch = $this->_report_branch_name($row);
        return [
            'user_id' => (int)$row['user_id'],
            'employee_code' => $row['employee_code'],
            'name' => $row['employee_name'],
            'position_name' => $row['position_name'],
            'branch_id' => $report_branch === 'Sudirman' ? 1 : ($report_branch === 'Gambir' ? 2 : 3),
            'branch_name' => $report_branch,
        ];
    }

    private function _report_branch_name($row) {
        $location = strtoupper(trim((string)$row['location']));
        if (strpos($location, 'GAMBIR') !== false) return 'Gambir';
        if (strpos($location, 'KANVAS') !== false) return 'Kanvas';
        if (strpos($location, 'SUDIRMAN') !== false) return 'Sudirman';

        $branch = strtoupper(trim((string)$row['branch_name']));
        if (strpos($branch, 'GAMBIR') !== false) return 'Gambir';
        if (strpos($branch, 'KANVAS') !== false) return 'Kanvas';
        if (strpos($branch, 'SUDIRMAN') !== false || preg_match('/\bSDR\b/', $branch)) return 'Sudirman';
        return trim((string)$row['branch_name']) ?: 'Tanpa Cabang';
    }

    private function _empty_branch($employee) {
        return [
            'branch_id' => (int)$employee['branch_id'],
            'branch_name' => $employee['branch_name'],
            'total' => 0, 'hadir' => 0, 'terlambat' => 0, 'hadir_gps' => 0,
            'izin' => 0, 'sakit' => 0, 'off' => 0, 'belum' => 0,
            'percent' => 0, 'positions' => [],
        ];
    }

    private function _empty_position($position_name) {
        return [
            'position_name' => $position_name,
            'total' => 0, 'hadir' => 0, 'terlambat' => 0, 'hadir_gps' => 0,
            'izin' => 0, 'sakit' => 0, 'off' => 0, 'belum' => 0,
            'percent' => 0, 'details' => [],
        ];
    }
}

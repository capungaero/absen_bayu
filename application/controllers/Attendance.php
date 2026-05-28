<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Attendance extends CI_Controller {

    function __construct() {
        parent::__construct();
        $this->load->model('presence_model', 'presence');
        $this->load->model('branch_model', 'branch');
        $this->load->model('subdivision_model', 'subdivision');

        if (!$this->ion_auth->logged_in()) {
            redirect('');
        }

        $this->role     = $this->ion_auth->get_users_groups()->row()->name;
        $this->userdata = $this->ion_auth->user()->row();

        if (!in_array($this->role, ['admin', 'admin-branch'])) {
            redirect('dashboard');
        }
    }

    public function index() {
        $branch_id = ($this->role === 'admin') ? $this->input->get('branch_id', true) : $this->_get_branch_id();
        $branch_id = $branch_id ? (int) $branch_id : null;

        $filter_mode = $this->input->get('mode', true) === 'week' ? 'week' : 'date';
        $date_value = $this->input->get('date', true);
        $week_value = $this->input->get('week', true);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_value) || strtotime($date_value) === false) {
            $date_value = date('Y-m-d');
        }

        if ($filter_mode === 'week') {
            if (preg_match('/^(\d{4})-W(\d{2})$/', (string) $week_value, $match)) {
                $week_year = (int) $match[1];
                $week = (int) $match[2];
            } else {
                $week_year = (int) date('o');
                $week = (int) date('W');
                $week_value = sprintf('%04d-W%02d', $week_year, $week);
            }

            $week_start = new DateTime();
            $week_start->setISODate($week_year, $week);
            $from_date = $week_start->format('Y-m-d');
            $to_date = $week_start->modify('+6 days')->format('Y-m-d');
        } else {
            $from_date = $date_value;
            $to_date = $date_value;
            $week_value = date('o-\WW', strtotime($date_value));
        }

        // Query shift dengan karyawan
        $shifts = $this->_get_shifts_with_attendance($branch_id, $from_date, $to_date);

        $data['shifts']   = $shifts;
        $data['branches'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
        $data['filter_mode'] = $filter_mode;
        $data['date_value'] = $date_value;
        $data['week_value'] = $week_value;
        $data['from_date'] = $from_date;
        $data['to_date']   = $to_date;
        $data['branch_id'] = $branch_id;
        $data['is_admin'] = $this->role === 'admin';

        $this->template->load('layout/admin', 'attendance/index', $data);
    }

    public function daily_report() {
        $is_admin = $this->role === 'admin';
        if ($is_admin) {
            $branch_id_raw = $this->input->get('branch_id', true);
            $branch_id = ($branch_id_raw !== '' && $branch_id_raw !== null) ? (int) $branch_id_raw : null;
        } else {
            $branch_id = $this->_get_branch_id();
        }

        $date_from = $this->input->get('date_from', true);
        $date_to = $this->input->get('date_to', true);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_from) || strtotime($date_from) === false) {
            $date_from = date('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_to) || strtotime($date_to) === false) {
            $date_to = $date_from;
        }
        if ($date_from > $date_to) {
            $swap = $date_from;
            $date_from = $date_to;
            $date_to = $swap;
        }

        $division_id_raw = $this->input->get('division_id', true);
        $division_id = ($division_id_raw !== '' && $division_id_raw !== null) ? (int) $division_id_raw : null;
        $search = trim((string) $this->input->get('q', true));
        $page = max(1, (int) $this->input->get('page', true));
        $per_page = 100;
        $offset = ($page - 1) * $per_page;

        $filters = [
            'branch_id' => $branch_id,
            'division_id' => $division_id,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'search' => $search
        ];

        $rows = $this->_get_daily_report_rows($filters, $per_page, $offset);
        $total_rows = $this->_count_daily_report_rows($filters);
        $summary = $this->_get_daily_report_summary($filters);
        $absent_rows = $this->_get_daily_report_absent_rows($filters);

        $branches = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();

        $this->db->order_by('subdivision_name', 'ASC');
        if ($branch_id) {
            $this->db->where('branch_id', $branch_id);
        }
        $divisions = $this->db->get('subdivision')->result_array();

        $data['rows'] = $rows;
        $data['summary'] = $summary;
        $data['absent_rows'] = $absent_rows;
        $data['branches'] = $branches;
        $data['divisions'] = $divisions;
        $data['branch_id'] = $branch_id;
        $data['division_id'] = $division_id;
        $data['date_from'] = $date_from;
        $data['date_to'] = $date_to;
        $data['search'] = $search;
        $data['is_admin'] = $is_admin;
        $data['page'] = $page;
        $data['per_page'] = $per_page;
        $data['total_rows'] = $total_rows;
        $data['total_pages'] = max(1, (int) ceil($total_rows / $per_page));

        $this->template->load('layout/admin', 'attendance/daily_report', $data);
    }

    private function _get_shifts_with_attendance($branch_id, $from_date, $to_date) {
        $params = [$from_date, $to_date, $from_date, $to_date];
        $branch_filter = '';

        if ($branch_id) {
            $branch_filter = ' AND p.branch_id = ?';
            $params[] = $branch_id;
        }

        $sql = "
            SELECT
                s.id,
                s.shift_name,
                s.start_time_in AS time_in,
                s.end_time_out AS time_out,
                u.id AS user_id,
                u.employee_code,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS name,
                p.id AS position_id,
                p.position_name,
                p.branch_id,
                b.branch_name,
                SUM(CASE
                    WHEN pr.presence_type = 'normal'
                        AND (pr.entry_time IS NOT NULL OR pr.out_time IS NOT NULL)
                    THEN 1 ELSE 0
                END) AS present,
                SUM(CASE WHEN pr.presence_type = 'normal' AND pr.entry_time_late > 0 THEN 1 ELSE 0 END) AS late,
                SUM(CASE WHEN pr.presence_type = 'sakit' THEN 1 ELSE 0 END) AS sick,
                SUM(CASE WHEN pr.presence_type IN ('izin', 'cuti') THEN 1 ELSE 0 END) AS permit,
                SUM(CASE
                    WHEN usa.additional_type = 'work'
                        AND usa.additional_date <= CURDATE()
                        AND (
                            pr.id IS NULL
                            OR (
                                pr.presence_type = 'normal'
                                AND pr.entry_time IS NULL
                                AND pr.out_time IS NULL
                                AND pr.rest_time_in IS NULL
                                AND pr.rest_time_out IS NULL
                            )
                        )
                    THEN 1 ELSE 0
                END) AS absent,
                COUNT(DISTINCT usa.additional_date) AS total_days
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
            JOIN shift s ON s.id = usa.shift_id
            LEFT JOIN presence pr ON pr.user_id = u.id
                AND pr.flow_date = usa.additional_date
                AND pr.presence_status = 'approved'
            WHERE u.active = 1
                AND usa.additional_type = 'work'
                AND usa.additional_date >= ?
                AND usa.additional_date <= ?
                {$branch_filter}
            GROUP BY
                s.id, s.shift_name, s.start_time_in, s.end_time_out,
                u.id, u.employee_code, u.first_name, u.last_name,
                p.id, p.position_name, p.branch_id, b.branch_name
            ORDER BY s.shift_code, b.branch_name, u.employee_code
        ";

        return $this->db->query($sql, $params)->result_array();
    }

    private function _apply_daily_report_filters($filters) {
        $this->db->where('flow_date >=', $filters['date_from'])
                 ->where('flow_date <=', $filters['date_to']);

        if (!empty($filters['branch_id'])) {
            $this->db->where('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['division_id'])) {
            $this->db->where('division_id', $filters['division_id']);
        }

        if ($filters['search'] !== '') {
            $this->db->group_start()
                     ->like('employee_name', $filters['search'])
                     ->or_like('employee_code', $filters['search'])
                     ->group_end();
        }
    }

    private function _get_daily_report_rows($filters, $limit, $offset) {
        $this->db->select('*')->from('presence_daily_report');
        $this->_apply_daily_report_filters($filters);
        return $this->db->order_by('flow_date', 'DESC')
                        ->order_by('branch_name', 'ASC')
                        ->order_by('division_name', 'ASC')
                        ->order_by('employee_name', 'ASC')
                        ->get('', $limit, $offset)
                        ->result_array();
    }

    private function _count_daily_report_rows($filters) {
        $this->db->from('presence_daily_report');
        $this->_apply_daily_report_filters($filters);
        return (int) $this->db->count_all_results();
    }

    private function _get_daily_report_summary($filters) {
        $this->db->select('
            COUNT(*) AS total,
            SUM(CASE WHEN entry_time IS NOT NULL THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN entry_time_late > 0 THEN 1 ELSE 0 END) AS late,
            SUM(CASE WHEN rest_time_in IS NOT NULL OR rest_time_out IS NOT NULL THEN 1 ELSE 0 END) AS rest_count,
            SUM(CASE WHEN dzuhur_time_in IS NOT NULL OR dzuhur_time_out IS NOT NULL THEN 1 ELSE 0 END) AS dzuhur_count,
            SUM(CASE WHEN ashar_time_in IS NOT NULL OR ashar_time_out IS NOT NULL THEN 1 ELSE 0 END) AS ashar_count,
            SUM(CASE WHEN maghrib_time_in IS NOT NULL OR maghrib_time_out IS NOT NULL THEN 1 ELSE 0 END) AS maghrib_count,
            SUM(CASE WHEN isha_time_in IS NOT NULL OR isha_time_out IS NOT NULL THEN 1 ELSE 0 END) AS isha_count
        ', false)->from('presence_daily_report');
        $this->_apply_daily_report_filters($filters);
        $row = $this->db->get()->row_array();

        return $row ?: [
            'total' => 0,
            'present' => 0,
            'late' => 0,
            'rest_count' => 0,
            'dzuhur_count' => 0,
            'ashar_count' => 0,
            'maghrib_count' => 0,
            'isha_count' => 0
        ];
    }

    private function _get_daily_report_absent_rows($filters) {
        $params = [$filters['date_from'], $filters['date_to'], $filters['date_from'], $filters['date_to']];
        $branch_filter = '';
        $division_filter = '';
        $search_filter = '';

        if (!empty($filters['branch_id'])) {
            $branch_filter = ' AND position.branch_id = ?';
            $params[] = $filters['branch_id'];
        }

        if (!empty($filters['division_id'])) {
            $division_filter = ' AND users.subdivision_id = ?';
            $params[] = $filters['division_id'];
        }

        if ($filters['search'] !== '') {
            $search_filter = " AND (
                users.employee_code LIKE ?
                OR TRIM(CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, ''))) LIKE ?
            )";
            $params[] = '%'.$filters['search'].'%';
            $params[] = '%'.$filters['search'].'%';
        }

        $sql = "
            SELECT
                usa.additional_date AS flow_date,
                users.id AS user_id,
                users.employee_code,
                TRIM(CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, ''))) AS employee_name,
                branch.branch_name,
                subdivision.subdivision_name AS division_name,
                position.position_name,
                shift.shift_code,
                shift.shift_name,
                shift.start_time,
                shift.end_time
            FROM users_shift_additional usa
            JOIN (
                SELECT user_id, additional_date, MAX(id) AS id
                FROM users_shift_additional
                WHERE additional_date >= ?
                  AND additional_date <= ?
                GROUP BY user_id, additional_date
            ) latest_usa ON latest_usa.id = usa.id
            JOIN users ON users.id = usa.user_id
            JOIN position ON position.id = users.position_id
            JOIN branch ON branch.id = position.branch_id
            LEFT JOIN subdivision ON subdivision.id = users.subdivision_id
            JOIN shift ON shift.id = usa.shift_id
            LEFT JOIN presence_daily_report report
                ON report.user_id = users.id
                AND report.flow_date = usa.additional_date
            WHERE users.active = 1
              AND usa.additional_type = 'work'
              AND usa.additional_date >= ?
              AND usa.additional_date <= ?
              AND usa.additional_date <= CURDATE()
              {$branch_filter}
              {$division_filter}
              {$search_filter}
              AND (
                report.id IS NULL
                OR (
                    report.presence_type = 'normal'
                    AND report.entry_time IS NULL
                    AND report.out_time IS NULL
                    AND report.rest_time_in IS NULL
                    AND report.rest_time_out IS NULL
                )
              )
            ORDER BY usa.additional_date DESC, branch.branch_name ASC, subdivision.subdivision_name ASC, shift.start_time ASC, employee_name ASC
        ";

        return $this->db->query($sql, $params)->result_array();
    }

    // =========================================================================
    // REPORT ABSEN MESIN — log tap mentah dari file .dat mesin fingerprint
    // =========================================================================

    public function machine_report() {
        $this->load->model('sync_model', 'sync');

        $date_from  = $this->input->get('date_from', true);
        $date_to    = $this->input->get('date_to', true);
        $machine_sn = attlog_sanitize_machine_sn($this->input->get('machine_sn', true));

        // Filter cabang: admin bisa pilih, non-admin pakai cabang sendiri
        $is_admin = $this->role === 'admin';
        if ($is_admin) {
            $branch_id_raw = $this->input->get('branch_id', true);
            $branch_id     = ($branch_id_raw !== '' && $branch_id_raw !== null) ? (int) $branch_id_raw : null;
        } else {
            $branch_id = $this->_get_branch_id();
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_from) || strtotime($date_from) === false) {
            $date_from = date('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_to) || strtotime($date_to) === false) {
            $date_to = $date_from;
        }
        if (strtotime($date_to) < strtotime($date_from)) {
            $date_to = $date_from;
        }

        // Mesin: build map sn => name, filter by branch jika dipilih
        $all_machines = $this->sync->get_all($branch_id);
        $machine_map  = [];
        foreach ($all_machines as $m) {
            $machine_map[$m['machine_sn']] = $m;
        }

        // Pastikan machine_sn yang dipilih ada di cabang yang dipilih
        if (!empty($machine_sn) && !isset($machine_map[$machine_sn])) {
            $machine_sn = '';
        }

        // Karyawan: build map employee_code => row, filter by branch jika dipilih
        $emp_query = $this->db
            ->select("u.id, u.employee_code, TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS name")
            ->from('users u')
            ->join('position p', 'p.id = u.position_id', 'left');
        if ($branch_id !== null) {
            $emp_query->where('p.branch_id', $branch_id);
        }
        $employees_raw = $emp_query->get()->result_array();
        $employee_map = [];
        foreach ($employees_raw as $e) {
            $employee_map[$e['employee_code']] = $e;
        }

        // Scan file .dat di uploads/attendance/{year}/{month}/
        $uploadDir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'attendance';
        $logs      = [];
        $seen      = [];   // untuk dedup (sn|emp_code|datetime)

        // Saat scan .dat, hanya catat log milik karyawan di branch yang dipilih
        // (employee_map sudah di-filter by branch, emp = null berarti bukan cabang ini)
        if (is_dir($uploadDir)) {
            $from_ts       = strtotime($date_from);
            $to_ts         = strtotime($date_to);
            $current       = mktime(0, 0, 0, (int)date('n', $from_ts), 1, (int)date('Y', $from_ts));
            $scanned_dirs  = [];

            while ($current <= $to_ts) {
                $year    = date('Y', $current);
                $month   = date('m', $current);
                $dir_key = $year . '/' . $month;

                if (!in_array($dir_key, $scanned_dirs)) {
                    $scanned_dirs[] = $dir_key;
                    $dir = $uploadDir . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;

                    if (is_dir($dir)) {
                        $files = glob($dir . DIRECTORY_SEPARATOR . 'attlog_*.dat') ?: [];
                        foreach ($files as $file) {
                            $basename = basename($file);
                            if (!preg_match('/^attlog_(.+?)_\d{8}_\d{6}\.dat$/', $basename, $match)) {
                                continue;
                            }
                            $sn = $match[1];

                            if (!empty($machine_sn) && $sn !== $machine_sn) {
                                continue;
                            }

                            $machine_name = isset($machine_map[$sn]) ? $machine_map[$sn]['name'] : $sn;

                            $raw   = @file_get_contents($file);
                            if ($raw === false) continue;
                            $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw)));

                            foreach ($lines as $line) {
                                $cols = preg_split('/\t/', $line);
                                if (count($cols) < 2) continue;

                                $emp_code     = trim($cols[0]);
                                $tap_datetime = trim($cols[1]);

                                if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $tap_datetime)) continue;

                                $tap_date = substr($tap_datetime, 0, 10);
                                if ($tap_date < $date_from || $tap_date > $date_to) continue;

                                $dedup_key = $sn . '|' . $emp_code . '|' . $tap_datetime;
                                if (isset($seen[$dedup_key])) continue;
                                $seen[$dedup_key] = true;

                                $emp = isset($employee_map[$emp_code]) ? $employee_map[$emp_code] : null;

                                // Jika filter cabang aktif, lewati karyawan di luar cabang
                                if ($branch_id !== null && $emp === null) continue;

                                $logs[] = [
                                    'machine_sn'    => $sn,
                                    'machine_name'  => $machine_name,
                                    'employee_code' => $emp_code,
                                    'employee_id'   => $emp ? $emp['id'] : null,
                                    'employee_name' => $emp ? trim($emp['name']) : '-',
                                    'tap_datetime'  => $tap_datetime,
                                    'keterangan'    => '',
                                    'status'        => '',
                                ];
                            }
                        }
                    }
                }

                $current = mktime(0, 0, 0, (int)date('n', $current) + 1, 1, (int)date('Y', $current));
            }
        }

        usort($logs, function ($a, $b) {
            $cmp = strcmp($a['tap_datetime'], $b['tap_datetime']);
            return $cmp !== 0 ? $cmp : strcmp($a['machine_sn'], $b['machine_sn']);
        });

        // --- Hitung keterlambatan ---
        // Cari tap pertama per employee_code per tanggal
        $first_tap = []; // [emp_code][date] => tap_datetime
        foreach ($logs as $log) {
            $date     = substr($log['tap_datetime'], 0, 10);
            $emp_code = $log['employee_code'];
            if (!isset($first_tap[$emp_code][$date]) || $log['tap_datetime'] < $first_tap[$emp_code][$date]) {
                $first_tap[$emp_code][$date] = $log['tap_datetime'];
            }
        }

        // Ambil jadwal shift: filter by branch jika dipilih
        $shift_map = [];
        if (!empty($logs)) {
            $sq = $this->db
                ->select('usa.user_id, usa.additional_date, s.start_time, s.start_time_late, s.shift_name')
                ->from('users_shift_additional usa')
                ->join('shift s', 's.id = usa.shift_id')
                ->where('usa.additional_type', 'work')
                ->where('usa.additional_date >=', $date_from)
                ->where('usa.additional_date <=', $date_to);
            if ($branch_id !== null) {
                $sq->join('users u', 'u.id = usa.user_id', 'inner')
                   ->join('position p', 'p.id = u.position_id', 'left')
                   ->where('p.branch_id', $branch_id);
            }
            $schedule_rows = $sq->get()->result_array();

            foreach ($schedule_rows as $r) {
                $shift_map[$r['user_id']][$r['additional_date']] = [
                    'start_time'      => $r['start_time'],
                    'start_time_late' => $r['start_time_late'],
                    'shift_name'      => $r['shift_name'],
                ];
            }
        }

        // Terapkan keterangan ke masing-masing log (hanya tap pertama per karyawan per hari)
        foreach ($logs as &$log) {
            if (empty($log['employee_id'])) continue;

            $date     = substr($log['tap_datetime'], 0, 10);
            $emp_code = $log['employee_code'];
            $user_id  = $log['employee_id'];

            // Hanya tap pertama per karyawan per hari yang dinilai keterlambatannya
            if (!isset($first_tap[$emp_code][$date]) || $first_tap[$emp_code][$date] !== $log['tap_datetime']) {
                continue;
            }

            // Tidak ada jadwal shift untuk tanggal ini
            if (!isset($shift_map[$user_id][$date])) {
                $log['keterangan'] = 'Tidak Ada Jadwal';
                $log['status']     = 'no_schedule';
                continue;
            }

            $shift           = $shift_map[$user_id][$date];
            $start_time      = $shift['start_time'];      // jam resmi mulai shift
            $start_time_late = $shift['start_time_late']; // batas toleransi keterlambatan
            $tap_time        = date('H:i:s', strtotime($log['tap_datetime']));

            $late_minutes = late_minutes($start_time_late, $tap_time);
            if ($late_minutes <= 0) {
                // Hadir tepat waktu (dalam toleransi)
                $log['keterangan'] = 'Tepat Waktu';
                $log['status']     = 'ontime';
            } else {
                // Terlambat dihitung dari batas toleransi, dengan detik dalam menit batas diabaikan.
                $log['keterangan'] = 'Terlambat ' . $late_minutes . ' menit';
                $log['status']     = 'late';
            }
        }
        unset($log);

        // Cek apakah jadwal shift tersedia untuk rentang tanggal yang dipilih
        $sc = $this->db
            ->where('additional_type', 'work')
            ->where('additional_date >=', $date_from)
            ->where('additional_date <=', $date_to);
        if ($branch_id !== null) {
            $sc->join('users u', 'u.id = users_shift_additional.user_id', 'inner')
               ->join('position p', 'p.id = u.position_id', 'left')
               ->where('p.branch_id', $branch_id);
        }
        $schedule_count = $sc->count_all_results('users_shift_additional');

        $branches = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();

        $data['logs']                  = $logs;
        $data['machines']              = $all_machines;
        $data['branches']              = $branches;
        $data['branch_id']             = $branch_id;
        $data['date_from']             = $date_from;
        $data['date_to']               = $date_to;
        $data['machine_sn']            = (string) $machine_sn;
        $data['is_admin']              = $is_admin;
        $data['shift_schedule_exists'] = $schedule_count > 0;
        $data['shift_schedule_count']  = $schedule_count;

        $this->template->load('layout/admin', 'attendance/machine_report', $data);
    }

    // =========================================================================
    // REKAP IZIN PULANG CEPAT (PLA) — per periode payroll
    // =========================================================================

    public function early_leave_report() {
        $is_admin = $this->role === 'admin';
        if ($is_admin) {
            $branch_id_raw = $this->input->get('branch_id', true);
            $branch_id = ($branch_id_raw !== '' && $branch_id_raw !== null) ? (int) $branch_id_raw : null;
        } else {
            $branch_id = $this->_get_branch_id();
        }

        $month = (int) $this->input->get('month', true);
        $year  = (int) $this->input->get('year', true);
        if ($month < 1 || $month > 12) { $month = (int) date('m'); }
        if ($year < 2000 || $year > 2100) { $year = (int) date('Y'); }

        $period = attlog_presence_period_range($month, $year);
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $rows = $this->_get_early_leave_rows($branch_id, $period['from'], $period['to']);
        $report = $this->_build_early_leave_report($rows, $days_in_month);

        $data = [
            'branches'      => $this->branch->get_data(['branch_name' => 'ASC'])->result_array(),
            'branch_id'     => $branch_id,
            'month'         => $month,
            'year'          => $year,
            'period'        => $period,
            'days_in_month' => $days_in_month,
            'report'        => $report,
            'is_admin'      => $is_admin,
        ];

        $this->template->load('layout/admin', 'attendance/early_leave_report', $data);
    }

    /**
     * Ambil semua row PLA per user dalam range tanggal (presence_status approved
     * supaya konsisten dengan _seed_early_leave_deductions di Payroll).
     */
    private function _get_early_leave_rows($branch_id, $from, $to) {
        $this->db->select("
                presence.id AS presence_id,
                presence.user_id,
                presence.flow_date,
                presence.entry_time,
                presence.out_time,
                presence.rest_time_in,
                presence.rest_time_out,
                presence.early_leave_short_minutes,
                presence.presence_status,
                users.employee_code,
                users.first_name,
                users.last_name,
                users.salary,
                position.position_name,
                position.branch_id,
                branch.branch_name,
                subdivision.subdivision_name
            ", false)
            ->from('presence')
            ->join('users', 'users.id = presence.user_id')
            ->join('position', 'position.id = users.position_id')
            ->join('branch', 'branch.id = position.branch_id')
            ->join('subdivision', 'subdivision.id = users.subdivision_id', 'LEFT')
            ->where('presence.is_early_leave', 1)
            ->where('presence.presence_status', 'approved')
            ->where('presence.flow_date >=', $from)
            ->where('presence.flow_date <=', $to);

        if (!empty($branch_id)) {
            $this->db->where('position.branch_id', (int) $branch_id);
        }

        return $this->db->order_by('branch.branch_name, users.first_name, presence.flow_date', 'ASC')
                        ->get()->result_array();
    }

    /**
     * Bangun struktur rekap: per-user agregasi + total.
     */
    private function _build_early_leave_report($rows, $days_in_month) {
        $per_user = [];
        foreach ($rows as $row) {
            $uid = (int) $row['user_id'];
            if (!isset($per_user[$uid])) {
                $per_user[$uid] = [
                    'user_id'        => $uid,
                    'employee_code'  => $row['employee_code'],
                    'name'           => trim($row['first_name'].' '.$row['last_name']),
                    'salary'         => (int) $row['salary'],
                    'branch_name'    => $row['branch_name'],
                    'position_name'  => $row['position_name'],
                    'subdivision'    => $row['subdivision_name'],
                    'dates'          => [],
                    'total_short_minutes' => 0,
                ];
            }
            $per_user[$uid]['dates'][] = [
                'flow_date'  => $row['flow_date'],
                'entry_time' => $row['entry_time'],
                'out_time'   => $row['out_time'],
                'short_minutes' => (int) $row['early_leave_short_minutes'],
            ];
            $per_user[$uid]['total_short_minutes'] += (int) $row['early_leave_short_minutes'];
        }

        $total_short = 0;
        $total_amount = 0;
        foreach ($per_user as &$u) {
            $u['hourly_rate'] = presence_hourly_rate($u['salary'], $days_in_month);
            $u['deduction_amount'] = presence_early_leave_deduction_amount(
                $u['salary'], $days_in_month, $u['total_short_minutes']
            );
            $total_short  += $u['total_short_minutes'];
            $total_amount += $u['deduction_amount'];
        }
        unset($u);

        // Stable sort: by branch + name (sudah disort di SQL, tapi pastikan kalau builder iterates by-key)
        $list = array_values($per_user);
        usort($list, function ($a, $b) {
            $c = strcmp($a['branch_name'] ?? '', $b['branch_name'] ?? '');
            return $c !== 0 ? $c : strcmp($a['name'], $b['name']);
        });

        return [
            'per_user'          => $list,
            'total_users'       => count($list),
            'total_short_minutes' => $total_short,
            'total_amount'      => $total_amount,
        ];
    }


    private function _get_branch_id() {
        $user_id = $this->userdata->id;
        $row = $this->db->select('position.branch_id')
                        ->from('users')
                        ->join('position', 'position.id = users.position_id', 'left')
                        ->where('users.id', $user_id)
                        ->get()->row_array();
        return $row ? $row['branch_id'] : null;
    }
}

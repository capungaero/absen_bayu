<?php
 
Class Wa_model extends CI_Model {

    protected $config_table = 'wa_config';
    protected $log_table    = 'wa_log';

    public function __construct() {
        parent::__construct();
        $this->_init_mysql_tables();
    }

    /**
     * Buat tabel MySQL jika belum ada dan isi default config
     */
    protected function _init_mysql_tables() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `wa_config` (
                `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `user_code`              VARCHAR(100) NOT NULL DEFAULT '',
                `secret`                 VARCHAR(255) NOT NULL DEFAULT '',
                `device_id`              VARCHAR(100) NOT NULL DEFAULT '',
                `is_active`              TINYINT(1) NOT NULL DEFAULT 1,
                `send_morning_enabled`   TINYINT(1) NOT NULL DEFAULT 1,
                `morning_time`           VARCHAR(10) NOT NULL DEFAULT '08:00',
                `send_afternoon_enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `afternoon_time`         VARCHAR(10) NOT NULL DEFAULT '13:00',
                `notif_absent_enabled`   TINYINT(1) NOT NULL DEFAULT 1,
                `absent_notif_time`      VARCHAR(10) NOT NULL DEFAULT '10:00',
                `target_phones`          TEXT,
                `cron_token`             VARCHAR(100) DEFAULT NULL,
                `created_at`             DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at`             DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $has_cron_token = $this->db->query("SHOW COLUMNS FROM `wa_config` LIKE 'cron_token'")->num_rows();
        if (!$has_cron_token) {
            $this->db->query("ALTER TABLE `wa_config` ADD COLUMN `cron_token` VARCHAR(100) DEFAULT NULL AFTER `target_phones`");
        }

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `wa_log` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `type`       VARCHAR(50) NOT NULL,
                `recipient`  VARCHAR(200),
                `phone`      VARCHAR(50),
                `message`    TEXT,
                `status`     VARCHAR(20) NOT NULL DEFAULT 'pending',
                `http_code`  INT,
                `response`   TEXT,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        // Insert empty config; real kirimi.id credentials must be filled from the WA config page.
        $count = $this->db->query("SELECT COUNT(*) AS cnt FROM wa_config")->row_array();
        if ((int)$count['cnt'] === 0) {
            $cron_token = $this->db->escape($this->_new_cron_token());
            $this->db->query("
                INSERT INTO wa_config (user_code, secret, device_id, is_active, send_morning_enabled, morning_time, send_afternoon_enabled, afternoon_time, notif_absent_enabled, absent_notif_time, cron_token)
                VALUES ('', '', '', 0, 1, '08:00', 1, '13:00', 1, '10:00', {$cron_token})
            ");
        }

        $config = $this->get_config();
        if (!empty($config) && empty($config['cron_token'])) {
            $this->db->where('id', $config['id'])->update($this->config_table, [
                'cron_token' => $this->_new_cron_token()
            ]);
        }
    }

    // =====================================================================
    // CONFIG
    // =====================================================================

    public function get_config($branch_id = null) {
        return $this->db->order_by('id', 'DESC')
                        ->limit(1)
                        ->get($this->config_table)
                        ->row_array();
    }

    public function save_config($data) {
        $existing = $this->get_config();
        if ($existing) {
            $this->db->where('id', $existing['id'])
                     ->update($this->config_table, $data);
            return true;
        }
        $this->db->insert($this->config_table, $data);
        return $this->db->affected_rows() > 0;
    }

    protected function _new_cron_token() {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(32));
        }

        return hash('sha256', uniqid('', true).mt_rand());
    }

    // =====================================================================
    // PRESENCE DATA FOR REKAP
    // =====================================================================

    public function get_today_summary($branch_id = null) {
        $today = date('Y-m-d');

        $this->db->select('
            branch.id AS branch_id,
            branch.branch_name,
            COUNT(DISTINCT users.id) AS total_employee,
            SUM(CASE WHEN presence.id IS NOT NULL AND presence.entry_time IS NOT NULL AND presence.presence_type = "normal" AND (presence.entry_time_late IS NULL OR presence.entry_time_late = 0) THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN presence.id IS NOT NULL AND presence.entry_time IS NOT NULL AND presence.presence_type = "normal" AND presence.entry_time_late > 0 THEN 1 ELSE 0 END) AS terlambat,
            SUM(CASE WHEN presence.id IS NOT NULL AND presence.presence_type IN ("izin","cuti") THEN 1 ELSE 0 END) AS izin,
            SUM(CASE WHEN presence.id IS NOT NULL AND presence.presence_type = "sakit" THEN 1 ELSE 0 END) AS sakit,
            SUM(CASE WHEN presence.id IS NULL THEN 1 ELSE 0 END) AS tidak_hadir
        ');
        $this->db->from('users');
        $this->db->join('position', 'position.id = users.position_id');
        $this->db->join('branch', 'branch.id = position.branch_id');
        $this->db->join('presence',
            'presence.user_id = users.id AND presence.flow_date = "' . $today . '" AND presence.presence_status = "approved"',
            'LEFT'
        );
        $this->db->where('users.active', '1');

        if ($branch_id !== null) {
            $this->db->where('branch.id', $branch_id);
        }

        $this->db->group_by('branch.id');
        return $this->db->get()->result_array();
    }

    public function get_absent_employees($branch_id = null) {
        $today = date('Y-m-d');

        $this->db->select('
            users.id,
            users.first_name,
            users.phone,
            position.position_name,
            branch.branch_name,
            branch.id AS branch_id
        ');
        $this->db->from('users');
        $this->db->join('position', 'position.id = users.position_id');
        $this->db->join('branch', 'branch.id = position.branch_id');
        $this->db->join('presence',
            'presence.user_id = users.id AND presence.flow_date = "' . $today . '" AND presence.presence_status = "approved"',
            'LEFT'
        );
        $this->db->where('users.active', '1');
        $this->db->where('presence.id IS NULL');

        if ($branch_id !== null) {
            $this->db->where('branch.id', $branch_id);
        }

        $this->db->order_by('branch.branch_name', 'ASC');
        $this->db->order_by('users.first_name', 'ASC');

        return $this->db->get()->result_array();
    }

    public function get_today_attendance_detail($branch_id = null) {
        $today = date('Y-m-d');

        $this->db->select('
            users.first_name,
            users.phone,
            position.position_name,
            branch.branch_name,
            branch.id AS branch_id,
            presence.entry_time,
            presence.out_time,
            presence.presence_type,
            presence.entry_time_late
        ');
        $this->db->from('presence');
        $this->db->join('users', 'users.id = presence.user_id');
        $this->db->join('position', 'position.id = users.position_id');
        $this->db->join('branch', 'branch.id = position.branch_id');
        $this->db->where('DATE(presence.flow_date)', $today);
        $this->db->where('presence.presence_status', 'approved');

        if ($branch_id !== null) {
            $this->db->where('branch.id', $branch_id);
        }

        $this->db->order_by('branch.branch_name', 'ASC');
        $this->db->order_by('users.first_name', 'ASC');

        return $this->db->get()->result_array();
    }

    public function get_today_shift_report($branch_id = null) {
        $today = date('Y-m-d');
        $params = [$today, $today];
        $branch_filter = '';

        if ($branch_id !== null) {
            $branch_filter = ' AND branch.id = ?';
            $params[] = $branch_id;
        }

        $sql = "
            SELECT
                shift.id AS shift_id,
                shift.shift_code,
                shift.shift_name,
                shift.start_time,
                shift.start_time_late,
                COALESCE(branch.id, 0) AS branch_id,
                COALESCE(branch.branch_name, 'Tanpa Cabang') AS branch_name,
                users.id AS user_id,
                users.employee_code,
                TRIM(CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, ''))) AS employee_name,
                COALESCE(position.position_name, '-') AS position_name,
                presence.id AS presence_id,
                presence.entry_time,
                presence.out_time,
                presence.presence_type,
                COALESCE(presence.entry_time_late, 0) AS late_minutes
            FROM users_shift_additional
            JOIN (
                SELECT user_id, additional_date, MAX(id) AS id
                FROM users_shift_additional
                WHERE additional_date = ?
                GROUP BY user_id, additional_date
            ) latest_usa ON latest_usa.id = users_shift_additional.id
            JOIN users ON users.id = users_shift_additional.user_id
            LEFT JOIN position ON position.id = users.position_id
            LEFT JOIN branch ON branch.id = position.branch_id
            JOIN shift ON shift.id = users_shift_additional.shift_id
            LEFT JOIN presence ON presence.user_id = users.id
                AND presence.flow_date = users_shift_additional.additional_date
                AND presence.presence_status = 'approved'
            WHERE users.active = '1'
                AND users_shift_additional.additional_type = 'work'
                AND users_shift_additional.additional_date = ?
                AND COALESCE(shift.shift_code, '') != '-'
                AND UPPER(COALESCE(shift.shift_name, '')) != 'NO SCHEDULE'
                {$branch_filter}
            ORDER BY shift.start_time, shift.shift_code, branch.branch_name, users.first_name
        ";

        $rows = $this->db->query($sql, $params)->result_array();
        $report = [];

        foreach ($rows as $row) {
            $key = $row['shift_id'].'-'.$row['branch_id'];

            if (!isset($report[$key])) {
                $report[$key] = [
                    'shift_id' => $row['shift_id'],
                    'shift_code' => $row['shift_code'],
                    'shift_name' => $row['shift_name'],
                    'start_time' => $row['start_time'],
                    'start_time_late' => $row['start_time_late'],
                    'branch_id' => $row['branch_id'],
                    'branch_name' => $row['branch_name'],
                    'total_employee' => 0,
                    'hadir' => 0,
                    'terlambat' => 0,
                    'izin' => 0,
                    'sakit' => 0,
                    'tidak_hadir' => 0,
                    'late_employees' => [],
                    'absent_employees' => [],
                ];
            }

            $report[$key]['total_employee']++;
            $presence_type = $row['presence_type'];
            $has_presence = !empty($row['presence_id']);
            $has_attendance = !empty($row['entry_time']) || !empty($row['out_time']);
            $late_minutes = (int)$row['late_minutes'];

            if (!$has_presence) {
                $report[$key]['tidak_hadir']++;
                $report[$key]['absent_employees'][] = $this->_format_employee_report_row($row);
                continue;
            }

            if ($presence_type === 'sakit') {
                $report[$key]['sakit']++;
                continue;
            }

            if ($presence_type === 'izin' || $presence_type === 'cuti') {
                $report[$key]['izin']++;
                continue;
            }

            if ($presence_type === 'normal' && $has_attendance) {
                if ($late_minutes > 0) {
                    $report[$key]['terlambat']++;
                    $late_row = $this->_format_employee_report_row($row);
                    $late_row['entry_time'] = $row['entry_time'];
                    $late_row['late_minutes'] = $late_minutes;
                    $report[$key]['late_employees'][] = $late_row;
                } else {
                    $report[$key]['hadir']++;
                }
                continue;
            }

            $report[$key]['tidak_hadir']++;
            $report[$key]['absent_employees'][] = $this->_format_employee_report_row($row);
        }

        return array_values($report);
    }

    protected function _format_employee_report_row($row) {
        return [
            'user_id' => $row['user_id'],
            'employee_code' => $row['employee_code'],
            'name' => $row['employee_name'],
            'position_name' => $row['position_name'],
        ];
    }

    // =====================================================================
    // LOGS
    // =====================================================================

    public function insert_log($data) {
        $this->db->insert($this->log_table, $data);
        return $this->db->affected_rows() > 0;
    }

    public function get_logs($limit = 50, $offset = 0) {
        return $this->db->order_by('id', 'DESC')
                        ->limit($limit, $offset)
                        ->get($this->log_table)
                        ->result_array();
    }

    public function count_logs() {
        return $this->db->count_all($this->log_table);
    }

    public function was_sent_today($type) {
        $today = date('Y-m-d');
        $count = $this->db->where('type', $type)
                          ->where("DATE(created_at) = '$today'")
                          ->where('status', 'success')
                          ->count_all_results($this->log_table);
        return $count > 0;
    }
}

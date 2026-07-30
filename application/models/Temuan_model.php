<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Temuan_model — data untuk tool TEMUAN (laporan masalah di toko).
 * Tabel dibuat otomatis saat model dimuat (pola Wa_model).
 */
class Temuan_model extends CI_Model {

    protected $location_table  = 'temuan_location';
    protected $temuan_table    = 'temuan';
    protected $config_table    = 'temuan_config';
    protected $inspector_table = 'temuan_inspector';
    protected $spv_table       = 'temuan_spv';

    public function __construct() {
        parent::__construct();
        $this->_init_tables();
    }

    private function _init_tables() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->location_table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `branch_id` INT NOT NULL,
                `name` VARCHAR(120) NOT NULL,
                `pj_user_id` INT NULL DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `idx_branch` (`branch_id`),
                KEY `idx_pj` (`pj_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->temuan_table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `branch_id` INT NOT NULL,
                `location_id` INT UNSIGNED NOT NULL,
                `reporter_id` INT NOT NULL,
                `description` TEXT NULL,
                `photo_path` VARCHAR(255) NULL,
                `status` ENUM('baru','dikerjakan','menunggu_acc','selesai','ditolak') NOT NULL DEFAULT 'baru',
                `taken_by` INT NULL DEFAULT NULL,
                `taken_as` VARCHAR(10) NULL DEFAULT NULL,
                `taken_at` DATETIME NULL,
                `done_by` INT NULL DEFAULT NULL,
                `done_as` VARCHAR(10) NULL DEFAULT NULL,
                `done_at` DATETIME NULL,
                `done_photo_path` VARCHAR(255) NULL,
                `reject_by` INT NULL DEFAULT NULL,
                `reject_as` VARCHAR(10) NULL DEFAULT NULL,
                `reject_reason` TEXT NULL,
                `reject_at` DATETIME NULL,
                `acc_by` INT NULL DEFAULT NULL,
                `acc_at` DATETIME NULL,
                `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `idx_branch_status` (`branch_id`, `status`),
                KEY `idx_location` (`location_id`),
                KEY `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->config_table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `notify_enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `notify_done_enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `target_phones` TEXT NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Migrasi tabel lama: kolom label pelaku respon (PJ/SPV/Admin)
        if ($this->db->query("SHOW COLUMNS FROM `{$this->temuan_table}` LIKE 'taken_as'")->num_rows() === 0) {
            $this->db->query("ALTER TABLE `{$this->temuan_table}`
                ADD COLUMN `taken_as` VARCHAR(10) NULL DEFAULT NULL AFTER `taken_by`,
                ADD COLUMN `done_as` VARCHAR(10) NULL DEFAULT NULL AFTER `done_by`");
        }

        // Migrasi alur tolak/ACC: status baru + kolom reject/acc/soft-delete
        if ($this->db->query("SHOW COLUMNS FROM `{$this->temuan_table}` LIKE 'reject_reason'")->num_rows() === 0) {
            $this->db->query("ALTER TABLE `{$this->temuan_table}`
                MODIFY COLUMN `status` ENUM('baru','dikerjakan','menunggu_acc','selesai','ditolak') NOT NULL DEFAULT 'baru',
                ADD COLUMN `reject_by` INT NULL DEFAULT NULL AFTER `done_photo_path`,
                ADD COLUMN `reject_as` VARCHAR(10) NULL DEFAULT NULL AFTER `reject_by`,
                ADD COLUMN `reject_reason` TEXT NULL AFTER `reject_as`,
                ADD COLUMN `reject_at` DATETIME NULL AFTER `reject_reason`,
                ADD COLUMN `acc_by` INT NULL DEFAULT NULL AFTER `reject_at`,
                ADD COLUMN `acc_at` DATETIME NULL AFTER `acc_by`,
                ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `acc_at`");
        }

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->inspector_table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT NOT NULL,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->spv_table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT NOT NULL,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        if ((int)$this->db->count_all($this->config_table) === 0) {
            $this->db->insert($this->config_table, [
                'notify_enabled'      => 1,
                'notify_done_enabled' => 1,
                'target_phones'       => '',
                'updated_at'          => date('Y-m-d H:i:s'),
            ]);
        }
    }

    // ====================================================================
    // CONFIG
    // ====================================================================

    public function get_config() {
        return $this->db->order_by('id', 'DESC')->limit(1)->get($this->config_table)->row_array();
    }

    public function save_config($data) {
        $existing = $this->get_config();
        $data['updated_at'] = date('Y-m-d H:i:s');
        if ($existing) {
            $this->db->where('id', $existing['id'])->update($this->config_table, $data);
        } else {
            $this->db->insert($this->config_table, $data);
        }
    }

    // ====================================================================
    // ROSTER (INSPECTOR & SPV — daftar user pilih manual)
    // ====================================================================

    private function _roster_table($type) {
        return $type === 'spv' ? $this->spv_table : $this->inspector_table;
    }

    public function get_roster($type) {
        $table = $this->_roster_table($type);
        return $this->db
            ->select("i.id, i.user_id, TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) AS name,
                      p.position_name, b.branch_name")
            ->from("{$table} i")
            ->join('users u', 'u.id = i.user_id', 'left')
            ->join('position p', 'p.id = u.position_id', 'left')
            ->join('branch b', 'b.id = p.branch_id', 'left')
            ->order_by('name')
            ->get()->result_array();
    }

    public function in_roster($type, $user_id) {
        return (int)$this->db->where('user_id', (int)$user_id)
                             ->count_all_results($this->_roster_table($type)) > 0;
    }

    public function add_to_roster($type, $user_id) {
        if ($this->in_roster($type, $user_id)) { return false; }
        $this->db->insert($this->_roster_table($type), [
            'user_id'    => (int)$user_id,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    public function remove_from_roster($type, $id) {
        $this->db->where('id', (int)$id)->delete($this->_roster_table($type));
        return $this->db->affected_rows() > 0;
    }

    // Kompatibilitas pemanggil lama
    public function is_inspector($user_id) { return $this->in_roster('inspector', $user_id); }

    // ====================================================================
    // LOKASI (KODE AREA)
    // ====================================================================

    public function get_locations($branch_id = null, $active_only = false) {
        $this->db->select("l.*, b.branch_name, TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name,''))) AS pj_name")
                 ->from("{$this->location_table} l")
                 ->join('branch b', 'b.id = l.branch_id', 'left')
                 ->join('users u', 'u.id = l.pj_user_id', 'left');
        if ($branch_id !== null) {
            $this->db->where('l.branch_id', $branch_id);
        }
        if ($active_only) {
            $this->db->where('l.is_active', 1);
        }
        return $this->db->order_by('b.branch_name, l.name')->get()->result_array();
    }

    public function get_location($id) {
        return $this->db->where('id', $id)->get($this->location_table)->row_array();
    }

    public function save_location($data, $id = null) {
        if ($id) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $id)->update($this->location_table, $data);
            return $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->location_table, $data);
        return $this->db->insert_id();
    }

    /** Hapus lokasi; kalau sudah dipakai temuan, nonaktifkan saja. */
    public function delete_location($id) {
        $used = (int)$this->db->where('location_id', $id)->count_all_results($this->temuan_table);
        if ($used > 0) {
            $this->db->where('id', $id)->update($this->location_table, [
                'is_active'  => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return 'deactivated';
        }
        $this->db->where('id', $id)->delete($this->location_table);
        return 'deleted';
    }

    // ====================================================================
    // TEMUAN
    // ====================================================================

    public function create_temuan($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->temuan_table, $data);
        return $this->db->insert_id();
    }

    private function _select_full() {
        $this->db
            ->select("t.*, l.name AS location_name, l.pj_user_id, b.branch_name,
                      TRIM(CONCAT(r.first_name,' ',COALESCE(r.last_name,''))) AS reporter_name,
                      TRIM(CONCAT(pj.first_name,' ',COALESCE(pj.last_name,''))) AS pj_name,
                      TRIM(CONCAT(tk.first_name,' ',COALESCE(tk.last_name,''))) AS taken_by_name,
                      TRIM(CONCAT(dn.first_name,' ',COALESCE(dn.last_name,''))) AS done_by_name,
                      TRIM(CONCAT(rj.first_name,' ',COALESCE(rj.last_name,''))) AS reject_by_name,
                      TRIM(CONCAT(ac.first_name,' ',COALESCE(ac.last_name,''))) AS acc_by_name")
            ->from("{$this->temuan_table} t")
            ->join("{$this->location_table} l", 'l.id = t.location_id', 'left')
            ->join('branch b', 'b.id = t.branch_id', 'left')
            ->join('users r', 'r.id = t.reporter_id', 'left')
            ->join('users pj', 'pj.id = l.pj_user_id', 'left')
            ->join('users tk', 'tk.id = t.taken_by', 'left')
            ->join('users dn', 'dn.id = t.done_by', 'left')
            ->join('users rj', 'rj.id = t.reject_by', 'left')
            ->join('users ac', 'ac.id = t.acc_by', 'left');
    }

    public function get_temuan($id) {
        $this->_select_full();
        return $this->db->where('t.id', $id)->get()->row_array();
    }

    public function list_temuan($filters = [], $limit = 100, $offset = 0) {
        $this->_select_full();
        $this->_apply_filters($filters);
        return $this->db->order_by('t.created_at', 'DESC')
                        ->limit($limit, $offset)
                        ->get()->result_array();
    }

    public function count_temuan($filters = []) {
        $this->db->from("{$this->temuan_table} t");
        $this->_apply_filters($filters);
        return (int)$this->db->count_all_results();
    }

    private function _apply_filters($filters) {
        if (empty($filters['include_deleted'])) {
            $this->db->where('t.is_deleted', 0);
        }
        if (!empty($filters['branch_id'])) {
            $this->db->where('t.branch_id', $filters['branch_id']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('t.status', $filters['status']);
        }
        if (!empty($filters['location_id'])) {
            $this->db->where('t.location_id', $filters['location_id']);
        }
        if (!empty($filters['from'])) {
            $this->db->where('t.created_at >=', $filters['from'] . ' 00:00:00');
        }
        if (!empty($filters['to'])) {
            $this->db->where('t.created_at <=', $filters['to'] . ' 23:59:59');
        }
    }

    /** Rekap jumlah per status. $include_deleted utk halaman Laporan. */
    public function status_summary($branch_id = null, $include_deleted = false, $from = null, $to = null) {
        $this->db->select("status, COUNT(*) AS total")
                 ->from($this->temuan_table)
                 ->group_by('status');
        if (!$include_deleted) { $this->db->where('is_deleted', 0); }
        if ($branch_id !== null) { $this->db->where('branch_id', $branch_id); }
        if ($from) { $this->db->where('created_at >=', $from . ' 00:00:00'); }
        if ($to)   { $this->db->where('created_at <=', $to . ' 23:59:59'); }
        $rows = $this->db->get()->result_array();
        $out = ['baru' => 0, 'dikerjakan' => 0, 'menunggu_acc' => 0, 'selesai' => 0, 'ditolak' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int)$r['total'];
        }
        return $out;
    }

    public function update_temuan($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $id)->update($this->temuan_table, $data);
        return $this->db->affected_rows() > 0;
    }
}

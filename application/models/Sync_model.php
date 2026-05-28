<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sync_model extends CI_Model {

    protected $machine_table = 'sync_machine';
    protected $log_table     = 'sync_log';

    public function __construct() {
        parent::__construct();
        $this->_init_tables();
    }

    protected function _init_tables() {
        // Buat tabel sync_machine jika belum ada (skema baru: machine_sn, machine_type)
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `sync_machine` (
                `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name`                VARCHAR(100) NOT NULL DEFAULT '',
                `machine_sn`          VARCHAR(100) NOT NULL DEFAULT '',
                `machine_type`        VARCHAR(20)  NOT NULL DEFAULT 'attendance',
                `password`            VARCHAR(100) NOT NULL DEFAULT '',
                `branch_id`           INT UNSIGNED DEFAULT NULL,
                `is_active`           TINYINT(1)   NOT NULL DEFAULT 1,
                `auto_sync_enabled`   TINYINT(1)   NOT NULL DEFAULT 0,
                `sync_times`          VARCHAR(255)  DEFAULT NULL,
                `last_sync_at`        DATETIME     DEFAULT NULL,
                `last_sync_status`    VARCHAR(20)  DEFAULT NULL,
                `created_at`          DATETIME     DEFAULT CURRENT_TIMESTAMP,
                `updated_at`          DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Migrasi: tambah machine_sn jika tabel lama masih pakai ip_address
        $has_sn   = $this->db->query("SHOW COLUMNS FROM `sync_machine` LIKE 'machine_sn'")->num_rows();
        $has_type = $this->db->query("SHOW COLUMNS FROM `sync_machine` LIKE 'machine_type'")->num_rows();
        if (!$has_sn) {
            $this->db->query("ALTER TABLE `sync_machine` ADD COLUMN `machine_sn` VARCHAR(100) NOT NULL DEFAULT '' AFTER `name`");
        }
        if (!$has_type) {
            $this->db->query("ALTER TABLE `sync_machine` ADD COLUMN `machine_type` VARCHAR(20) NOT NULL DEFAULT 'attendance' AFTER `machine_sn`");
        }

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `sync_log` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `machine_id`  INT UNSIGNED NOT NULL,
                `machine_name` VARCHAR(100) DEFAULT '',
                `status`      VARCHAR(20)  NOT NULL DEFAULT 'pending',
                `records`     INT          DEFAULT 0,
                `message`     TEXT,
                `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Migrasi: tambah machine_name ke sync_log jika belum ada
        $has_mname = $this->db->query("SHOW COLUMNS FROM `sync_log` LIKE 'machine_name'")->num_rows();
        if (!$has_mname) {
            $this->db->query("ALTER TABLE `sync_log` ADD COLUMN `machine_name` VARCHAR(100) DEFAULT '' AFTER `machine_id`");
        }

        // Machine credentials must be configured explicitly from the UI.
    }

    protected function _seed_default_machines() {
        return;
    }

    // =========================================================
    // MACHINE CRUD
    // =========================================================

    public function get_all($branch_id = null) {
        $this->db->select('sm.*, b.branch_name');
        $this->db->from('sync_machine sm');
        $this->db->join('branch b', 'b.id = sm.branch_id', 'left');
        if ($branch_id !== null) {
            $this->db->where('sm.branch_id', $branch_id);
        }
        $this->db->order_by('sm.id', 'ASC');
        return $this->db->get()->result_array();
    }

    public function get_by_id($id) {
        return $this->db->where('id', $id)->get($this->machine_table)->row_array();
    }

    public function insert($data) {
        $this->db->insert($this->machine_table, $data);
        return $this->db->insert_id();
    }

    public function update($id, $data) {
        $this->db->where('id', $id)->update($this->machine_table, $data);
        return $this->db->affected_rows() > 0;
    }

    public function delete($id) {
        $this->db->where('id', $id)->delete($this->machine_table);
        return $this->db->affected_rows() > 0;
    }

    // =========================================================
    // SYNC LOG
    // =========================================================

    public function insert_log($data) {
        $this->db->insert($this->log_table, $data);
        return $this->db->insert_id();
    }

    public function get_active_by_type($type = 'attendance') {
        return $this->db
            ->where('machine_type', $type)
            ->where('is_active', 1)
            ->order_by('id', 'ASC')
            ->get($this->machine_table)
            ->result_array();
    }

    public function get_logs($machine_id = null, $limit = 50) {
        $this->db->select('sl.*, sm.name AS machine_name');
        $this->db->from('sync_log sl');
        $this->db->join('sync_machine sm', 'sm.id = sl.machine_id', 'left');
        if ($machine_id !== null) {
            $this->db->where('sl.machine_id', $machine_id);
        }
        $this->db->order_by('sl.id', 'DESC');
        $this->db->limit($limit);
        return $this->db->get()->result_array();
    }
}

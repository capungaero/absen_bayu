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
    protected $type_table      = 'temuan_type';
    protected $location_pj_table = 'temuan_location_pj';
    protected $category_table  = 'temuan_category';
    protected $subject_table   = 'temuan_subject';
    protected $division_table  = 'temuan_division';

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
                `spv_user_id` INT NULL DEFAULT NULL,
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
                `location_id` INT UNSIGNED NULL DEFAULT NULL,
                `individu_spv_id` INT NULL DEFAULT NULL,
                `reporter_id` INT NOT NULL,
                `type_id` INT UNSIGNED NULL DEFAULT NULL,
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
                `due_at` DATETIME NULL,
                `due_extended_at` DATETIME NULL,
                `extension_status` ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
                `extension_reason` TEXT NULL,
                `extension_requested_by` INT NULL DEFAULT NULL,
                `extension_requested_as` VARCHAR(10) NULL DEFAULT NULL,
                `extension_requested_at` DATETIME NULL,
                `extension_decided_by` INT NULL DEFAULT NULL,
                `extension_decided_at` DATETIME NULL,
                `extension_decision_note` TEXT NULL,
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

        // Migrasi: SPV melekat per area (1 PJ + 1 SPV per area)
        if ($this->db->query("SHOW COLUMNS FROM `{$this->location_table}` LIKE 'spv_user_id'")->num_rows() === 0) {
            $this->db->query("ALTER TABLE `{$this->location_table}`
                ADD COLUMN `spv_user_id` INT NULL DEFAULT NULL AFTER `pj_user_id`");
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

        // Migrasi: penolakan wajib ACC inspector/admin (status menunggu_acc_tolak + kolom keputusan)
        if ($this->db->query("SHOW COLUMNS FROM `{$this->temuan_table}` LIKE 'reject_decision'")->num_rows() === 0) {
            $this->db->query("ALTER TABLE `{$this->temuan_table}`
                MODIFY COLUMN `status` ENUM('baru','dikerjakan','menunggu_acc','selesai','ditolak','menunggu_acc_tolak') NOT NULL DEFAULT 'baru',
                ADD COLUMN `reject_decision` ENUM('approved','denied') NULL DEFAULT NULL AFTER `reject_at`,
                ADD COLUMN `reject_decided_by` INT NULL DEFAULT NULL AFTER `reject_decision`,
                ADD COLUMN `reject_decided_at` DATETIME NULL AFTER `reject_decided_by`,
                ADD COLUMN `reject_decision_note` TEXT NULL AFTER `reject_decided_at`");
        }

        // Migrasi: timer H+1 + pengajuan tambahan waktu
        if ($this->db->query("SHOW COLUMNS FROM `{$this->temuan_table}` LIKE 'due_at'")->num_rows() === 0) {
            $this->db->query("ALTER TABLE `{$this->temuan_table}`
                ADD COLUMN `due_at` DATETIME NULL AFTER `is_deleted`,
                ADD COLUMN `due_extended_at` DATETIME NULL AFTER `due_at`,
                ADD COLUMN `extension_status` ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none' AFTER `due_extended_at`,
                ADD COLUMN `extension_reason` TEXT NULL AFTER `extension_status`,
                ADD COLUMN `extension_requested_by` INT NULL DEFAULT NULL AFTER `extension_reason`,
                ADD COLUMN `extension_requested_as` VARCHAR(10) NULL DEFAULT NULL AFTER `extension_requested_by`,
                ADD COLUMN `extension_requested_at` DATETIME NULL AFTER `extension_requested_as`,
                ADD COLUMN `extension_decided_by` INT NULL DEFAULT NULL AFTER `extension_requested_at`,
                ADD COLUMN `extension_decided_at` DATETIME NULL AFTER `extension_decided_by`,
                ADD COLUMN `extension_decision_note` TEXT NULL AFTER `extension_decided_at`");
            // Backfill deadline utk temuan lama: created_at + 1 hari, akhir hari (23:59:59)
            $this->db->query("UPDATE `{$this->temuan_table}`
                SET `due_at` = CONCAT(DATE_ADD(DATE(`created_at`), INTERVAL 1 DAY), ' 23:59:59')
                WHERE `due_at` IS NULL");
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
            CREATE TABLE IF NOT EXISTS `{$this->category_table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(80) NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->type_table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `category_id` INT UNSIGNED NULL DEFAULT NULL,
                `name` VARCHAR(80) NOT NULL,
                `target_mode` ENUM('objek','individu') NOT NULL DEFAULT 'objek',
                `requires_action` TINYINT(1) NOT NULL DEFAULT 1,
                `require_photo_initial` TINYINT(1) NOT NULL DEFAULT 1,
                `require_photo_done` TINYINT(1) NOT NULL DEFAULT 1,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->subject_table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `temuan_id` INT UNSIGNED NOT NULL,
                `user_id` INT NOT NULL,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_temuan_user` (`temuan_id`, `user_id`),
                KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Migrasi: kolom jenis temuan + seed default + backfill temuan lama
        if ($this->db->query("SHOW COLUMNS FROM `{$this->temuan_table}` LIKE 'type_id'")->num_rows() === 0) {
            $this->db->query("ALTER TABLE `{$this->temuan_table}`
                ADD COLUMN `type_id` INT UNSIGNED NULL DEFAULT NULL AFTER `reporter_id`");
        }
        if ((int)$this->db->count_all($this->type_table) === 0) {
            $now = date('Y-m-d H:i:s');
            $this->db->insert($this->category_table, ['name' => 'Umum', 'is_active' => 1, 'created_at' => $now]);
            $cat_id = (int)$this->db->insert_id();
            $this->db->insert_batch($this->type_table, [
                ['category_id' => $cat_id, 'name' => 'Temuan Rak', 'target_mode' => 'objek', 'requires_action' => 1, 'require_photo_initial' => 1, 'require_photo_done' => 1, 'is_active' => 1, 'created_at' => $now],
                ['category_id' => $cat_id, 'name' => 'Temuan Kebersihan', 'target_mode' => 'objek', 'requires_action' => 1, 'require_photo_initial' => 1, 'require_photo_done' => 1, 'is_active' => 1, 'created_at' => $now],
                ['category_id' => $cat_id, 'name' => 'Temuan Disiplin', 'target_mode' => 'individu', 'requires_action' => 0, 'require_photo_initial' => 1, 'require_photo_done' => 0, 'is_active' => 1, 'created_at' => $now],
                ['category_id' => $cat_id, 'name' => 'Salah Input', 'target_mode' => 'objek', 'requires_action' => 0, 'require_photo_initial' => 0, 'require_photo_done' => 0, 'is_active' => 1, 'created_at' => $now],
            ]);
            $default_id = (int)$this->db->select('id')->where('name', 'Temuan Rak')->get($this->type_table)->row_array()['id'];
            $this->db->where('type_id', null)->update($this->temuan_table, ['type_id' => $default_id]);
        }

        // Migrasi: kategori (Jenis) + target objek/individu utk temuan_type yang sudah ada
        if ($this->db->query("SHOW COLUMNS FROM `{$this->type_table}` LIKE 'category_id'")->num_rows() === 0) {
            $this->db->query("ALTER TABLE `{$this->type_table}`
                ADD COLUMN `category_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`,
                ADD COLUMN `target_mode` ENUM('objek','individu') NOT NULL DEFAULT 'objek' AFTER `name`");
            if ((int)$this->db->count_all($this->category_table) === 0) {
                $this->db->insert($this->category_table, ['name' => 'Umum', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s')]);
            }
            $cat = $this->db->select('id')->order_by('id')->limit(1)->get($this->category_table)->row_array();
            if ($cat) {
                $this->db->where('category_id', null)->update($this->type_table, ['category_id' => $cat['id']]);
            }
        }

        // Migrasi: temuan.location_id jadi nullable + kolom individu_spv_id (mode individu)
        if ($this->db->query("SHOW COLUMNS FROM `{$this->temuan_table}` LIKE 'individu_spv_id'")->num_rows() === 0) {
            $this->db->query("ALTER TABLE `{$this->temuan_table}`
                MODIFY COLUMN `location_id` INT UNSIGNED NULL DEFAULT NULL,
                ADD COLUMN `individu_spv_id` INT NULL DEFAULT NULL AFTER `location_id`");
        }

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->location_pj_table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `location_id` INT UNSIGNED NOT NULL,
                `user_id` INT NOT NULL,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_loc_user` (`location_id`, `user_id`),
                KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Migrasi: filter visibilitas SPV per divisi (master temuan_division + division_id pada area)
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->division_table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(80) NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        if ($this->db->query("SHOW COLUMNS FROM `{$this->location_table}` LIKE 'division_id'")->num_rows() === 0) {
            $this->db->query("ALTER TABLE `{$this->location_table}`
                ADD COLUMN `division_id` INT UNSIGNED NULL DEFAULT NULL AFTER `branch_id`");
        }
        // Bekas percobaan filter pakai tabel subdivision (CV payroll) — tidak jadi dipakai.
        if ($this->db->query("SHOW COLUMNS FROM `{$this->location_table}` LIKE 'subdivision_id'")->num_rows() > 0) {
            $this->db->query("ALTER TABLE `{$this->location_table}` DROP COLUMN `subdivision_id`");
        }

        // Migrasi: PJ area jadi banyak-orang; backfill dari pj_user_id lama sekali saja.
        if ((int)$this->db->count_all($this->location_pj_table) === 0) {
            $legacy = $this->db->select('id, pj_user_id')->where('pj_user_id IS NOT NULL', null, false)
                                ->get($this->location_table)->result_array();
            if (!empty($legacy)) {
                $now = date('Y-m-d H:i:s');
                $rows = array_map(function ($l) use ($now) {
                    return ['location_id' => $l['id'], 'user_id' => $l['pj_user_id'], 'created_at' => $now];
                }, $legacy);
                $this->db->insert_batch($this->location_pj_table, $rows);
            }
        }

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
    // ROSTER (INSPECTOR — daftar user pilih manual)
    // ====================================================================

    private function _roster_table($type) {
        return $this->inspector_table;
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

    /** Apakah user jadi SPV di minimal satu area aktif. */
    public function has_spv_area($user_id) {
        return (int)$this->db->where('spv_user_id', (int)$user_id)
                             ->count_all_results($this->location_table) > 0;
    }

    /** Apakah user jadi PJ di minimal satu area. */
    public function has_pj_area($user_id) {
        return (int)$this->db->where('user_id', (int)$user_id)
                             ->count_all_results($this->location_pj_table) > 0;
    }

    /** Apakah user salah satu PJ area tsb. */
    public function is_location_pj($location_id, $user_id) {
        return (int)$this->db->where(['location_id' => (int)$location_id, 'user_id' => (int)$user_id])
                             ->count_all_results($this->location_pj_table) > 0;
    }

    public function get_location_pjs($location_id) {
        return $this->db
            ->select("lp.user_id, TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) AS name, p.position_name")
            ->from("{$this->location_pj_table} lp")
            ->join('users u', 'u.id = lp.user_id', 'left')
            ->join('position p', 'p.id = u.position_id', 'left')
            ->where('lp.location_id', (int)$location_id)
            ->order_by('name')
            ->get()->result_array();
    }

    /** Ganti seluruh daftar PJ area tsb (replace-all dari array user_id). */
    public function set_location_pjs($location_id, $user_ids) {
        $location_id = (int)$location_id;
        $this->db->where('location_id', $location_id)->delete($this->location_pj_table);
        $user_ids = array_unique(array_filter(array_map('intval', (array)$user_ids)));
        if (empty($user_ids)) { return; }
        $now = date('Y-m-d H:i:s');
        $rows = array_map(function ($uid) use ($location_id, $now) {
            return ['location_id' => $location_id, 'user_id' => $uid, 'created_at' => $now];
        }, $user_ids);
        $this->db->insert_batch($this->location_pj_table, $rows);
    }

    // ====================================================================
    // JENIS (KATEGORI)
    // ====================================================================

    public function get_categories($active_only = false) {
        $this->db->from($this->category_table);
        if ($active_only) { $this->db->where('is_active', 1); }
        return $this->db->order_by('name')->get()->result_array();
    }

    public function get_category($id) {
        return $this->db->where('id', $id)->get($this->category_table)->row_array();
    }

    public function save_category($data, $id = null) {
        if ($id) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $id)->update($this->category_table, $data);
            return $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->category_table, $data);
        return $this->db->insert_id();
    }

    /** Hapus kategori; kalau sudah dipakai nama temuan, nonaktifkan saja. */
    public function delete_category($id) {
        $used = (int)$this->db->where('category_id', $id)->count_all_results($this->type_table);
        if ($used > 0) {
            $this->db->where('id', $id)->update($this->category_table, [
                'is_active'  => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return 'deactivated';
        }
        $this->db->where('id', $id)->delete($this->category_table);
        return 'deleted';
    }

    // ====================================================================
    // DIVISI (master khusus TEMUAN — filter visibilitas SPV)
    // ====================================================================

    public function get_divisions($active_only = false) {
        $this->db->from($this->division_table);
        if ($active_only) { $this->db->where('is_active', 1); }
        return $this->db->order_by('name')->get()->result_array();
    }

    public function get_division($id) {
        return $this->db->where('id', $id)->get($this->division_table)->row_array();
    }

    public function save_division($data, $id = null) {
        if ($id) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $id)->update($this->division_table, $data);
            return $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->division_table, $data);
        return $this->db->insert_id();
    }

    /** Hapus divisi; kalau masih dipakai area, nonaktifkan saja. */
    public function delete_division($id) {
        $used = (int)$this->db->where('division_id', $id)->count_all_results($this->location_table);
        if ($used > 0) {
            $this->db->where('id', $id)->update($this->division_table, [
                'is_active'  => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return 'deactivated';
        }
        $this->db->where('id', $id)->delete($this->division_table);
        return 'deleted';
    }

    /** Divisi-divisi dari area yang di-SPV-i user ini (dasar filter visibilitas SPV). */
    public function get_spv_division_ids($user_id) {
        $rows = $this->db->select('DISTINCT(division_id) AS division_id', false)
                         ->where('spv_user_id', (int)$user_id)
                         ->where('division_id IS NOT NULL', null, false)
                         ->get($this->location_table)->result_array();
        return array_map(function ($r) { return (int)$r['division_id']; }, $rows);
    }

    // ====================================================================
    // NAMA TEMUAN
    // ====================================================================

    public function get_types($active_only = false) {
        $this->db->select("ty.*, cat.name AS category_name")
                 ->from("{$this->type_table} ty")
                 ->join("{$this->category_table} cat", 'cat.id = ty.category_id', 'left');
        if ($active_only) { $this->db->where('ty.is_active', 1); }
        return $this->db->order_by('cat.name, ty.name')->get()->result_array();
    }

    public function get_type($id) {
        return $this->db->where('id', $id)->get($this->type_table)->row_array();
    }

    public function save_type($data, $id = null) {
        if ($id) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $id)->update($this->type_table, $data);
            return $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->type_table, $data);
        return $this->db->insert_id();
    }

    /** Hapus jenis; kalau sudah dipakai temuan, nonaktifkan saja. */
    public function delete_type($id) {
        $used = (int)$this->db->where('type_id', $id)->count_all_results($this->temuan_table);
        if ($used > 0) {
            $this->db->where('id', $id)->update($this->type_table, [
                'is_active'  => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return 'deactivated';
        }
        $this->db->where('id', $id)->delete($this->type_table);
        return 'deleted';
    }

    // ====================================================================
    // SUBJECT (karyawan target — mode individu)
    // ====================================================================

    public function add_subjects($temuan_id, $user_ids) {
        $user_ids = array_unique(array_filter(array_map('intval', (array)$user_ids)));
        if (empty($user_ids)) { return; }
        $now = date('Y-m-d H:i:s');
        $rows = array_map(function ($uid) use ($temuan_id, $now) {
            return ['temuan_id' => (int)$temuan_id, 'user_id' => $uid, 'created_at' => $now];
        }, $user_ids);
        $this->db->insert_batch($this->subject_table, $rows);
    }

    public function get_subjects($temuan_id) {
        return $this->db
            ->select("s.user_id, TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) AS name, p.position_name")
            ->from("{$this->subject_table} s")
            ->join('users u', 'u.id = s.user_id', 'left')
            ->join('position p', 'p.id = u.position_id', 'left')
            ->where('s.temuan_id', (int)$temuan_id)
            ->order_by('name')
            ->get()->result_array();
    }

    // ====================================================================
    // LOKASI (KODE AREA)
    // ====================================================================

    public function get_locations($branch_id = null, $active_only = false) {
        $this->db->select("l.*, b.branch_name, dv.name AS division_name,
                           TRIM(CONCAT(s.first_name, ' ', COALESCE(s.last_name,''))) AS spv_name,
                           (SELECT GROUP_CONCAT(lp.user_id) FROM {$this->location_pj_table} lp WHERE lp.location_id = l.id) AS pj_user_ids,
                           (SELECT GROUP_CONCAT(TRIM(CONCAT(u2.first_name,' ',COALESCE(u2.last_name,''))) SEPARATOR ', ')
                              FROM {$this->location_pj_table} lp2 JOIN users u2 ON u2.id = lp2.user_id
                             WHERE lp2.location_id = l.id) AS pj_names")
                 ->from("{$this->location_table} l")
                 ->join('branch b', 'b.id = l.branch_id', 'left')
                 ->join('users s', 's.id = l.spv_user_id', 'left')
                 ->join("{$this->division_table} dv", 'dv.id = l.division_id', 'left');
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
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $now;
        // Timer H+1: batas akhir hari kerja besoknya (23:59:59)
        $data['due_at'] = date('Y-m-d', strtotime($now . ' +1 day')) . ' 23:59:59';
        $this->db->insert($this->temuan_table, $data);
        return $this->db->insert_id();
    }

    private function _select_full() {
        $this->db
            ->select("t.*, l.name AS location_name, l.spv_user_id, b.branch_name,
                      ty.name AS type_name, ty.target_mode AS type_target_mode,
                      ty.category_id AS type_category_id, cat.name AS category_name,
                      ty.requires_action AS type_requires_action,
                      ty.require_photo_initial AS type_require_photo_initial,
                      ty.require_photo_done AS type_require_photo_done,
                      TRIM(CONCAT(r.first_name,' ',COALESCE(r.last_name,''))) AS reporter_name,
                      TRIM(CONCAT(isv.first_name,' ',COALESCE(isv.last_name,''))) AS individu_spv_name,
                      (SELECT GROUP_CONCAT(lp.user_id) FROM {$this->location_pj_table} lp WHERE lp.location_id = l.id) AS pj_user_ids,
                      (SELECT GROUP_CONCAT(TRIM(CONCAT(u2.first_name,' ',COALESCE(u2.last_name,''))) SEPARATOR ', ')
                         FROM {$this->location_pj_table} lp2 JOIN users u2 ON u2.id = lp2.user_id
                        WHERE lp2.location_id = l.id) AS pj_name,
                      TRIM(CONCAT(sv.first_name,' ',COALESCE(sv.last_name,''))) AS spv_name,
                      (SELECT GROUP_CONCAT(sj.user_id) FROM {$this->subject_table} sj WHERE sj.temuan_id = t.id) AS subject_user_ids,
                      (SELECT GROUP_CONCAT(TRIM(CONCAT(u3.first_name,' ',COALESCE(u3.last_name,''))) SEPARATOR ', ')
                         FROM {$this->subject_table} sj2 JOIN users u3 ON u3.id = sj2.user_id
                        WHERE sj2.temuan_id = t.id) AS subject_names,
                      TRIM(CONCAT(tk.first_name,' ',COALESCE(tk.last_name,''))) AS taken_by_name,
                      TRIM(CONCAT(dn.first_name,' ',COALESCE(dn.last_name,''))) AS done_by_name,
                      TRIM(CONCAT(rj.first_name,' ',COALESCE(rj.last_name,''))) AS reject_by_name,
                      TRIM(CONCAT(rjd.first_name,' ',COALESCE(rjd.last_name,''))) AS reject_decided_by_name,
                      TRIM(CONCAT(ac.first_name,' ',COALESCE(ac.last_name,''))) AS acc_by_name,
                      TRIM(CONCAT(exr.first_name,' ',COALESCE(exr.last_name,''))) AS extension_requested_by_name,
                      TRIM(CONCAT(exd.first_name,' ',COALESCE(exd.last_name,''))) AS extension_decided_by_name")
            ->from("{$this->temuan_table} t")
            ->join("{$this->location_table} l", 'l.id = t.location_id', 'left')
            ->join("{$this->type_table} ty", 'ty.id = t.type_id', 'left')
            ->join("{$this->category_table} cat", 'cat.id = ty.category_id', 'left')
            ->join('branch b', 'b.id = t.branch_id', 'left')
            ->join('users r', 'r.id = t.reporter_id', 'left')
            ->join('users isv', 'isv.id = t.individu_spv_id', 'left')
            ->join('users sv', 'sv.id = l.spv_user_id', 'left')
            ->join('users tk', 'tk.id = t.taken_by', 'left')
            ->join('users dn', 'dn.id = t.done_by', 'left')
            ->join('users rj', 'rj.id = t.reject_by', 'left')
            ->join('users rjd', 'rjd.id = t.reject_decided_by', 'left')
            ->join('users ac', 'ac.id = t.acc_by', 'left')
            ->join('users exr', 'exr.id = t.extension_requested_by', 'left')
            ->join('users exd', 'exd.id = t.extension_decided_by', 'left');
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
        $this->db->from("{$this->temuan_table} t")
                 ->join("{$this->location_table} l", 'l.id = t.location_id', 'left');
        $this->_apply_filters($filters);
        return (int)$this->db->count_all_results();
    }

    private function _apply_filters($filters) {
        if (empty($filters['include_deleted'])) {
            $this->db->where('t.is_deleted', 0);
        }
        // Pembatasan visibilitas: PJ murni (bukan SPV/inspector/admin) hanya lihat area miliknya.
        // SPV tetap lihat seluruh cabang (perlu untuk backup SPV lain yang libur).
        if (!empty($filters['visible_to'])) {
            $uid = (int)$filters['visible_to'];
            $this->db->where("(
                EXISTS (SELECT 1 FROM {$this->location_pj_table} lp WHERE lp.location_id = l.id AND lp.user_id = {$uid})
                OR t.individu_spv_id = {$uid}
                OR EXISTS (SELECT 1 FROM {$this->subject_table} sj WHERE sj.temuan_id = t.id AND sj.user_id = {$uid})
            )", null, false);
        }
        if (!empty($filters['division_ids'])) {
            // Area tanpa tag divisi (atau temuan individu tanpa area) tetap terlihat semua SPV cabang.
            $ids = implode(',', array_map('intval', (array)$filters['division_ids']));
            $this->db->where("(l.division_id IS NULL OR l.division_id IN ({$ids}))", null, false);
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
        if (!empty($filters['type_id'])) {
            $this->db->where('t.type_id', $filters['type_id']);
        }
        if (!empty($filters['from'])) {
            $this->db->where('t.created_at >=', $filters['from'] . ' 00:00:00');
        }
        if (!empty($filters['to'])) {
            $this->db->where('t.created_at <=', $filters['to'] . ' 23:59:59');
        }
    }

    /** Rekap jumlah per status. $vis_filters = ['visible_to'=>uid] atau ['division_ids'=>[..]] atau []. */
    public function status_summary($branch_id = null, $include_deleted = false, $from = null, $to = null, $vis_filters = []) {
        // backward-compat: kalau dipanggil dengan int/null langsung (caller lama)
        if (!is_array($vis_filters)) { $vis_filters = $vis_filters ? ['visible_to' => (int)$vis_filters] : []; }
        $this->db->select("t.status, COUNT(*) AS total")
                 ->from("{$this->temuan_table} t")
                 ->join("{$this->location_table} l", 'l.id = t.location_id', 'left')
                 ->group_by('t.status');
        if (!$include_deleted) { $this->db->where('t.is_deleted', 0); }
        if ($branch_id !== null) { $this->db->where('t.branch_id', $branch_id); }
        if ($from) { $this->db->where('t.created_at >=', $from . ' 00:00:00'); }
        if ($to)   { $this->db->where('t.created_at <=', $to . ' 23:59:59'); }
        if (!empty($vis_filters['visible_to'])) {
            $uid = (int)$vis_filters['visible_to'];
            $this->db->where("(
                EXISTS (SELECT 1 FROM {$this->location_pj_table} lp WHERE lp.location_id = l.id AND lp.user_id = {$uid})
                OR t.individu_spv_id = {$uid}
                OR EXISTS (SELECT 1 FROM {$this->subject_table} sj WHERE sj.temuan_id = t.id AND sj.user_id = {$uid})
            )", null, false);
        }
        if (!empty($vis_filters['division_ids'])) {
            $ids = implode(',', array_map('intval', (array)$vis_filters['division_ids']));
            $this->db->where("(l.division_id IS NULL OR l.division_id IN ({$ids}))", null, false);
        }
        $rows = $this->db->get()->result_array();
        $out = ['baru' => 0, 'dikerjakan' => 0, 'menunggu_acc' => 0, 'menunggu_acc_tolak' => 0, 'selesai' => 0, 'ditolak' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int)$r['total'];
        }
        return $out;
    }

    /** Baris mentah utk rekap bulanan (exclude soft-delete & ditolak; join info PJ/SPV area). */
    public function get_report_rows($branch_id, $from, $to) {
        $this->db
            ->select("t.id, t.status, t.created_at, t.done_at, t.due_at, t.due_extended_at,
                      ty.name AS type_name, ty.target_mode AS type_target_mode, cat.name AS category_name,
                      l.id AS location_id, l.name AS location_name, b.branch_name,
                      (SELECT GROUP_CONCAT(TRIM(CONCAT(u2.first_name,' ',COALESCE(u2.last_name,''))) SEPARATOR ', ')
                         FROM {$this->location_pj_table} lp2 JOIN users u2 ON u2.id = lp2.user_id
                        WHERE lp2.location_id = l.id) AS pj_name,
                      (SELECT GROUP_CONCAT(lp3.user_id)
                         FROM {$this->location_pj_table} lp3
                        WHERE lp3.location_id = l.id) AS pj_user_ids,
                      l.division_id,
                      TRIM(CONCAT(sv.first_name,' ',COALESCE(sv.last_name,''))) AS spv_name,
                      TRIM(CONCAT(isv.first_name,' ',COALESCE(isv.last_name,''))) AS individu_spv_name,
                      (SELECT GROUP_CONCAT(TRIM(CONCAT(u3.first_name,' ',COALESCE(u3.last_name,''))) SEPARATOR ', ')
                         FROM {$this->subject_table} sj2 JOIN users u3 ON u3.id = sj2.user_id
                        WHERE sj2.temuan_id = t.id) AS subject_names")
            ->from("{$this->temuan_table} t")
            ->join("{$this->location_table} l", 'l.id = t.location_id', 'left')
            ->join("{$this->type_table} ty", 'ty.id = t.type_id', 'left')
            ->join("{$this->category_table} cat", 'cat.id = ty.category_id', 'left')
            ->join('branch b', 'b.id = t.branch_id', 'left')
            ->join('users sv', 'sv.id = l.spv_user_id', 'left')
            ->join('users isv', 'isv.id = t.individu_spv_id', 'left')
            ->where('t.is_deleted', 0)
            ->where('t.status !=', 'ditolak')
            ->where('t.created_at >=', $from . ' 00:00:00')
            ->where('t.created_at <=', $to . ' 23:59:59');
        if ($branch_id !== null) { $this->db->where('t.branch_id', $branch_id); }
        return $this->db->get()->result_array();
    }

    public function update_temuan($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $id)->update($this->temuan_table, $data);
        return $this->db->affected_rows() > 0;
    }
}

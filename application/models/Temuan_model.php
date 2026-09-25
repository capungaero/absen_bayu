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
    protected $location_spv_table = 'temuan_location_spv';
    protected $category_table  = 'temuan_category';
    protected $subject_table   = 'temuan_subject';
    protected $division_table  = 'temuan_division';
    protected $wa_contact_table = 'temuan_wa_contact';
    protected $location_contact_table = 'temuan_location_contact';
    // HP kantor dipakai bareng: satu kontak WA bisa terasosiasi ke banyak
    // karyawan langsung, dan/atau ke satu/banyak "divisi" (temuan_division,
    // dicocokkan ke position.position_name -- lihat get_employee_notify_phones).
    protected $wa_contact_employee_table = 'temuan_wa_contact_employee';
    protected $wa_contact_division_table = 'temuan_wa_contact_division';
    // No. WA KERJA per user (PJ/Pengawas) -- notif WA DILARANG pakai users.phone
    // (no pribadi; karyawan dilarang bawa HP). Diisi admin saat menugaskan.
    protected $work_phone_table = 'temuan_work_phone';

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

        // Snapshot harian "Daily Report Temuan & Inspeksi" -- dibuat sekali oleh
        // cron jam 22:00 (lihat Temuan::cron()), dibaca ulang persis sama untuk
        // ditampilkan online dan untuk isi pesan WA jam 22:15. Tanggal hari ini
        // (belum ada snapshot) dihitung live langsung dari tabel temuan.
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `temuan_daily_report` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `report_date` DATE NOT NULL,
                `total` INT NOT NULL DEFAULT 0,
                `executed_count` INT NOT NULL DEFAULT 0,
                `on_time_count` INT NOT NULL DEFAULT 0,
                `late_count` INT NOT NULL DEFAULT 0,
                `not_executed_count` INT NOT NULL DEFAULT 0,
                `overall_status` VARCHAR(30) NOT NULL DEFAULT '',
                `categories_json` TEXT NULL,
                `status_counts_json` TEXT NULL,
                `activity_json` TEXT NULL,
                `backlog_json` TEXT NULL,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_report_date` (`report_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        if ($this->db->query("SHOW COLUMNS FROM `temuan_daily_report` LIKE 'status_counts_json'")->num_rows() === 0) {
            $this->db->query("ALTER TABLE `temuan_daily_report` ADD COLUMN `status_counts_json` TEXT NULL AFTER `categories_json`");
        }
        if ($this->db->query("SHOW COLUMNS FROM `temuan_daily_report` LIKE 'activity_json'")->num_rows() === 0) {
            $this->db->query("ALTER TABLE `temuan_daily_report` ADD COLUMN `activity_json` TEXT NULL AFTER `status_counts_json`");
        }
        if ($this->db->query("SHOW COLUMNS FROM `temuan_daily_report` LIKE 'backlog_json'")->num_rows() === 0) {
            $this->db->query("ALTER TABLE `temuan_daily_report` ADD COLUMN `backlog_json` TEXT NULL AFTER `activity_json`");
        }

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

        // Keterangan bebas dari PJ/Pengawas saat Lapor Selesai (opsional, sejajar done_photo_path).
        if ($this->db->query("SHOW COLUMNS FROM `{$this->temuan_table}` LIKE 'done_note'")->num_rows() === 0) {
            $this->db->query("ALTER TABLE `{$this->temuan_table}`
                ADD COLUMN `done_note` TEXT NULL AFTER `done_photo_path`");
        }

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->work_phone_table}` (
                `user_id` INT NOT NULL,
                `phone` VARCHAR(30) NOT NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->inspector_table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT NOT NULL,
                `level` ENUM('utama','asisten') NOT NULL DEFAULT 'utama',
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        // Asisten inspector: boleh posting temuan (sama inspector biasa) tapi TIDAK
        // dapat hak "backup responder"/ACC/putus-pengajuan lintas area, dan visibilitas
        // list dikunci ke temuan yang dia lapor sendiri saja (reporter_id).
        if ($this->db->query("SHOW COLUMNS FROM `{$this->inspector_table}` LIKE 'level'")->num_rows() === 0) {
            $this->db->query("ALTER TABLE `{$this->inspector_table}`
                ADD COLUMN `level` ENUM('utama','asisten') NOT NULL DEFAULT 'utama' AFTER `user_id`");
        }

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

        // Migrasi: SPV area jadi banyak-orang (pola sama PJ); backfill dari spv_user_id sekali saja.
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->location_spv_table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `location_id` INT UNSIGNED NOT NULL,
                `user_id` INT NOT NULL,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_loc_user` (`location_id`, `user_id`),
                KEY `idx_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        if ((int)$this->db->count_all($this->location_spv_table) === 0) {
            $legacy = $this->db->select('id, spv_user_id')->where('spv_user_id IS NOT NULL', null, false)
                                ->get($this->location_table)->result_array();
            if (!empty($legacy)) {
                $now = date('Y-m-d H:i:s');
                $rows = array_map(function ($l) use ($now) {
                    return ['location_id' => $l['id'], 'user_id' => $l['spv_user_id'], 'created_at' => $now];
                }, $legacy);
                $this->db->insert_batch($this->location_spv_table, $rows);
            }
        }

        // Migrasi: Pengawas Utama vs Backup (is_primary) — notif WA & rekap laporan pakai Utama saja.
        if ($this->db->query("SHOW COLUMNS FROM `{$this->location_spv_table}` LIKE 'is_primary'")->num_rows() === 0) {
            $this->db->query("ALTER TABLE `{$this->location_spv_table}`
                ADD COLUMN `is_primary` TINYINT(1) NOT NULL DEFAULT 0 AFTER `user_id`");
            // Backfill: pengawas tercatat paling awal per area jadi Utama (satu-satunya kandidat kalau cuma 1).
            $this->db->query("
                UPDATE `{$this->location_spv_table}` t
                JOIN (SELECT location_id, MIN(id) AS min_id FROM `{$this->location_spv_table}` GROUP BY location_id) m
                  ON m.location_id = t.location_id AND m.min_id = t.id
                SET t.is_primary = 1
            ");
        }

        // Migrasi: Kontak Notifikasi WA (nama+nomor bebas, dipilih manual per Kode Area).
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->wa_contact_table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(80) NOT NULL,
                `phone` VARCHAR(30) NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->wa_contact_employee_table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `contact_id` INT UNSIGNED NOT NULL,
                `user_id` INT NOT NULL,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_contact_user` (`contact_id`, `user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->wa_contact_division_table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `contact_id` INT UNSIGNED NOT NULL,
                `division_id` INT UNSIGNED NOT NULL,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_contact_division` (`contact_id`, `division_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `{$this->location_contact_table}` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `location_id` INT UNSIGNED NOT NULL,
                `contact_id` INT UNSIGNED NOT NULL,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_loc_contact` (`location_id`, `contact_id`),
                KEY `idx_contact` (`contact_id`)
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
    // DAILY REPORT TEMUAN & INSPEKSI
    // ====================================================================

    /**
     * Hitung rekap 1 hari langsung dari tabel temuan (bukan baca snapshot).
     * Dipakai baik untuk preview live tanggal hari ini, maupun oleh cron jam
     * 22:00 untuk membuat snapshot yang disimpan (lihat save_daily_report()).
     *
     * Definisi (independen dari flag is_late di Temuan.php yang menganggap
     * 'baru'-lewat-deadline sebagai telat -- di sini 'baru' yang sama sekali
     * belum disentuh masuk not_executed, bukan late):
     *  - not_executed : status masih 'baru' (belum ada yang mengambil)
     *  - executed     : status != 'baru' (sudah diambil/diputuskan, apa pun hasilnya)
     *  - on_time      : status 'selesai' & done_at <= deadline efektif
     *  - late         : (status 'selesai' & done_at > deadline) ATAU (masih
     *                    berjalan/dikerjakan & sekarang sudah lewat deadline)
     *  - 'ditolak' dihitung masuk executed tapi sengaja tidak masuk on_time/late.
     */
    const STATUSES = ['baru', 'dikerjakan', 'menunggu_acc', 'menunggu_acc_tolak', 'selesai', 'ditolak'];

    public function compute_daily_report($date) {
        $now = date('Y-m-d H:i:s');
        $rows = $this->db
            ->select('t.status, t.done_at, t.due_at, t.due_extended_at, ty.name AS type_name')
            ->from("{$this->temuan_table} t")
            ->join("{$this->type_table} ty", 'ty.id = t.type_id', 'left')
            ->where('t.is_deleted', 0)
            ->where('t.created_at >=', $date.' 00:00:00')
            ->where('t.created_at <=', $date.' 23:59:59')
            ->get()->result_array();

        $categories = [];
        $status_counts = array_fill_keys(self::STATUSES, 0);
        $executed = 0; $on_time = 0; $late = 0; $not_executed = 0;

        foreach ($rows as $r) {
            $type_name = $r['type_name'] ?: 'Tanpa Jenis';
            if (!isset($categories[$type_name])) {
                $categories[$type_name] = ['total' => 0, 'by_status' => array_fill_keys(self::STATUSES, 0)];
            }
            $categories[$type_name]['total']++;
            $categories[$type_name]['by_status'][$r['status']]++;
            $status_counts[$r['status']]++;

            if ($r['status'] === 'baru') {
                $not_executed++;
                continue;
            }
            $executed++;
            if ($r['status'] === 'ditolak') { continue; }

            $effective_due = !empty($r['due_extended_at']) ? $r['due_extended_at'] : $r['due_at'];
            if ($r['status'] === 'selesai') {
                if ($effective_due && strtotime($r['done_at']) > strtotime($effective_due)) { $late++; }
                else { $on_time++; }
            } elseif ($effective_due && strtotime($now) > strtotime($effective_due)) {
                $late++;
            }
            // masih berjalan & belum lewat deadline: sengaja tidak masuk on_time/late.
        }

        ksort($categories);
        $category_list = [];
        foreach ($categories as $name => $c) {
            $category_list[] = ['name' => $name, 'total' => $c['total'], 'by_status' => $c['by_status']];
        }

        return [
            'date'               => $date,
            'total'              => count($rows),
            'categories'         => $category_list,
            'status_counts'      => $status_counts,
            'executed_count'     => $executed,
            'on_time_count'      => $on_time,
            'late_count'         => $late,
            'not_executed_count' => $not_executed,
            'overall_status'     => $this->_daily_report_status(count($rows), $late, $not_executed),
            'activity'           => $this->compute_daily_activity($date),
            'recap'              => $this->compute_overall_recap(),
        ];
    }

    /**
     * Aktivitas hari ini terhadap temuan APA PUN tanggal dibuatnya -- beda dari
     * blok di atas yang hanya menghitung temuan yang DIBUAT tanggal ini.
     * Ini menangkap kasus "temuan kemarin baru dikerjakan/diselesaikan hari
     * ini", yang sebelumnya tidak pernah muncul di laporan sama sekali.
     * done_at = waktu pelapor menandai selesai (dipakai jg utk on-time/telat,
     * konsisten dgn compute_daily_report()); acc_at = waktu inspector ACC
     * (penentu status final 'selesai').
     */
    public function compute_daily_activity($date) {
        $rows = $this->db
            ->select('t.status, t.created_at, t.taken_at, t.done_at, t.acc_at, t.due_at, t.due_extended_at,
                      t.reject_at, t.reject_decision, t.reject_decided_at')
            ->from("{$this->temuan_table} t")
            ->where('t.is_deleted', 0)
            ->group_start()
                ->where('DATE(t.taken_at)', $date)
                ->or_where('DATE(t.done_at)', $date)
                ->or_where('DATE(t.acc_at)', $date)
                ->or_where('DATE(t.reject_at)', $date)
                ->or_where('DATE(t.reject_decided_at)', $date)
            ->group_end()
            ->get()->result_array();

        $carried_over = 0; $started = 0; $reported_done = 0;
        $closed = 0; $closed_on_time = 0; $closed_late = 0;
        $rejected_final = 0; $reopened = 0;

        foreach ($rows as $r) {
            if (substr((string)$r['created_at'], 0, 10) !== $date) { $carried_over++; }
            if (substr((string)$r['taken_at'], 0, 10) === $date) { $started++; }
            if (substr((string)$r['done_at'], 0, 10) === $date) { $reported_done++; }
            if ($r['status'] === 'selesai' && substr((string)$r['acc_at'], 0, 10) === $date) {
                $closed++;
                $effective_due = !empty($r['due_extended_at']) ? $r['due_extended_at'] : $r['due_at'];
                if ($effective_due && $r['done_at'] && strtotime($r['done_at']) > strtotime($effective_due)) { $closed_late++; }
                else { $closed_on_time++; }
            }
            if (substr((string)$r['reject_decided_at'], 0, 10) === $date) {
                if ($r['reject_decision'] === 'approved') { $rejected_final++; }
                elseif ($r['reject_decision'] === 'denied') { $reopened++; }
            }
        }

        return [
            'total_touched'        => count($rows),
            'carried_over_count'   => $carried_over,
            'started_count'        => $started,
            'reported_done_count'  => $reported_done,
            'closed_count'         => $closed,
            'closed_on_time_count' => $closed_on_time,
            'closed_late_count'    => $closed_late,
            'rejected_final_count' => $rejected_final,
            'reopened_count'       => $reopened,
        ];
    }

    /** Rekap SELURUH temuan (live, lintas tanggal dibuat) per status persis kategori di halaman aplikasi -- dipanggil saat snapshot dibuat, jadi utk tanggal lampau nilainya beku sesuai kondisi saat snapshot itu diambil. */
    public function compute_overall_recap() {
        $rows = $this->db->select('status')
            ->from("{$this->temuan_table}")
            ->where('is_deleted', 0)
            ->get()->result_array();

        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $r) {
            if (isset($counts[$r['status']])) { $counts[$r['status']]++; }
        }

        return [
            'total'         => count($rows),
            'status_counts' => $counts,
        ];
    }

    /**
     * Deskripsi singkat (baris pertama) temuan yang SUDAH LEWAT DEADLINE dan
     * masih terbuka SAAT INI -- lintas tanggal dibuat (bukan cuma temuan hari
     * ini), diurutkan yang paling lama menunggak duluan. Sebelumnya seksi ini
     * cuma menampilkan temuan yang dibuat hari itu, jadi temuan lama yang
     * menunggak berhari-hari tidak pernah ditagih di WA.
     */
    public function get_overdue_findings_summary($limit = 10) {
        $now = date('Y-m-d H:i:s');
        $rows = $this->db->select("t.description, t.created_at, COALESCE(t.due_extended_at, t.due_at) AS effective_due")
            ->from("{$this->temuan_table} t")
            ->where('t.is_deleted', 0)
            ->where_not_in('t.status', ['selesai', 'ditolak'])
            ->where('COALESCE(t.due_extended_at, t.due_at) IS NOT NULL', null, false)
            ->where('COALESCE(t.due_extended_at, t.due_at) <', $now)
            ->order_by('effective_due', 'ASC')
            ->limit($limit)
            ->get()->result_array();
        $out = [];
        foreach ($rows as $r) {
            $first_line = trim(strtok((string)$r['description'], "\r\n"));
            if ($first_line === '') { continue; }
            $days_late = max(0, (int)floor((strtotime($now) - strtotime($r['effective_due'])) / 86400));
            $out[] = mb_substr($first_line, 0, 140).' _('.$days_late.' hari telat)_';
        }
        return $out;
    }

    private function _daily_report_status($total, $late, $not_executed) {
        if ($total === 0) { return 'tidak_ada'; }
        $ratio = ($late + $not_executed) / $total;
        if ($ratio <= 0) { return 'baik'; }
        if ($ratio <= 0.2) { return 'cukup'; }
        return 'perlu_perhatian';
    }

    public function save_daily_report($date, $data) {
        $payload = [
            'report_date'         => $date,
            'total'               => (int)$data['total'],
            'executed_count'      => (int)$data['executed_count'],
            'on_time_count'       => (int)$data['on_time_count'],
            'late_count'          => (int)$data['late_count'],
            'not_executed_count'  => (int)$data['not_executed_count'],
            'overall_status'      => $data['overall_status'],
            'categories_json'     => json_encode($data['categories'], JSON_UNESCAPED_UNICODE),
            'status_counts_json'  => json_encode($data['status_counts'] ?? [], JSON_UNESCAPED_UNICODE),
            'activity_json'       => json_encode($data['activity'] ?? [], JSON_UNESCAPED_UNICODE),
            'backlog_json'        => json_encode($data['recap'] ?? [], JSON_UNESCAPED_UNICODE),
            'created_at'          => date('Y-m-d H:i:s'),
        ];
        $existing = $this->db->where('report_date', $date)->get('temuan_daily_report')->row_array();
        if ($existing) {
            $this->db->where('id', $existing['id'])->update('temuan_daily_report', $payload);
        } else {
            $this->db->insert('temuan_daily_report', $payload);
        }
    }

    /** NULL kalau belum pernah ada snapshot utk tanggal itu. */
    public function get_daily_report($date) {
        $row = $this->db->where('report_date', $date)->get('temuan_daily_report')->row_array();
        if (!$row) { return null; }
        $row['date'] = $row['report_date']; // samakan shape dgn compute_daily_report()
        $row['categories'] = json_decode($row['categories_json'], true) ?: [];
        $row['status_counts'] = json_decode($row['status_counts_json'] ?? '', true) ?: array_fill_keys(self::STATUSES, 0);
        $row['activity'] = json_decode($row['activity_json'] ?? '', true) ?: [];
        $row['recap'] = json_decode($row['backlog_json'] ?? '', true) ?: [];
        unset($row['categories_json'], $row['status_counts_json'], $row['activity_json'], $row['backlog_json']);
        return $row;
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
            ->select("i.id, i.user_id, i.level, TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) AS name,
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

    public function add_to_roster($type, $user_id, $level = 'utama') {
        if ($this->in_roster($type, $user_id)) { return false; }
        $this->db->insert($this->_roster_table($type), [
            'user_id'    => (int)$user_id,
            'level'      => in_array($level, ['utama', 'asisten'], true) ? $level : 'utama',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return true;
    }

    public function set_roster_level($type, $id, $level) {
        if (!in_array($level, ['utama', 'asisten'], true)) { return false; }
        $this->db->where('id', (int)$id)->update($this->_roster_table($type), ['level' => $level]);
        return $this->db->affected_rows() > 0;
    }

    public function remove_from_roster($type, $id) {
        $this->db->where('id', (int)$id)->delete($this->_roster_table($type));
        return $this->db->affected_rows() > 0;
    }

    // Kompatibilitas pemanggil lama -- "inspector" apa saja (utama ATAU asisten).
    public function is_inspector($user_id) { return $this->in_roster('inspector', $user_id); }

    public function get_inspector_level($user_id) {
        $row = $this->db->select('level')->where('user_id', (int)$user_id)
            ->get($this->inspector_table)->row_array();
        return $row ? $row['level'] : null;
    }

    /** Inspector UTAMA saja -- hak penuh (lintas area/cabang, ACC, putus pengajuan). */
    public function is_full_inspector($user_id) {
        return $this->get_inspector_level($user_id) === 'utama';
    }

    /** Asisten inspector -- cuma boleh posting temuan + lihat punya sendiri. */
    public function is_assistant_inspector($user_id) {
        return $this->get_inspector_level($user_id) === 'asisten';
    }

    /** Apakah user jadi SPV di minimal satu area aktif. */
    /** Nomor HP Pengawas Utama area tsb (untuk notif WA); null kalau tak ada/kosong. */
    // Notif WA memakai NO KERJA ({$this->work_phone_table}) -- BUKAN users.phone
    // (no pribadi; karyawan dilarang bawa HP, keputusan user 24 Agu 2026).
    // Tidak ada fallback: PJ/Pengawas tanpa no kerja tidak dikirimi WA.
    public function get_primary_spv_phone($location_id) {
        if (empty($location_id)) { return null; }
        $row = $this->db->select('wp.phone')
                        ->from("{$this->location_spv_table} ls")
                        ->join("{$this->work_phone_table} wp", 'wp.user_id = ls.user_id')
                        ->where('ls.location_id', (int)$location_id)
                        ->where('ls.is_primary', 1)
                        ->limit(1)->get()->row_array();
        return ($row && !empty($row['phone'])) ? $row['phone'] : null;
    }

    /** No KERJA semua PJ area tsb -- utk notif WA. */
    public function get_location_pj_phones($location_id) {
        if (empty($location_id)) { return []; }
        $rows = $this->db->select('wp.phone')
                        ->from("{$this->location_pj_table} lp")
                        ->join("{$this->work_phone_table} wp", 'wp.user_id = lp.user_id')
                        ->where('lp.location_id', (int)$location_id)
                        ->get()->result_array();
        return array_values(array_filter(array_map(function ($r) { return $r['phone']; }, $rows)));
    }

    /** Peta {user_id: no_kerja} utk daftar user tertentu (atau semua kalau null). */
    public function get_work_phone_map($user_ids = null) {
        if (is_array($user_ids) && empty($user_ids)) { return []; }
        if (is_array($user_ids)) {
            $this->db->where_in('user_id', array_map('intval', $user_ids));
        }
        $rows = $this->db->get($this->work_phone_table)->result_array();
        $map = [];
        foreach ($rows as $r) { $map[(int)$r['user_id']] = $r['phone']; }
        return $map;
    }

    public function set_work_phone($user_id, $phone) {
        $user_id = (int)$user_id;
        $phone = trim((string)$phone);
        if (!$user_id || $phone === '') { return false; }
        $this->db->query(
            "INSERT INTO `{$this->work_phone_table}` (user_id, phone, updated_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE phone = VALUES(phone), updated_at = NOW()",
            [$user_id, $phone]
        );
        return true;
    }

    public function get_work_phone($user_id) {
        $row = $this->db->where('user_id', (int)$user_id)->get($this->work_phone_table)->row_array();
        return ($row && !empty($row['phone'])) ? $row['phone'] : null;
    }

    /** Nomor HP user tertentu -- utk notif WA; null kalau tak ada/kosong. */
    public function get_user_phone($user_id) {
        $user_id = (int)$user_id;
        if ($user_id <= 0) { return null; }
        $row = $this->db->select('phone')->where('id', $user_id)->get('users')->row_array();
        return ($row && !empty($row['phone'])) ? $row['phone'] : null;
    }

    public function has_spv_area($user_id) {
        return (int)$this->db->where('user_id', (int)$user_id)
                             ->count_all_results($this->location_spv_table) > 0;
    }

    /**
     * Ganti seluruh daftar Pengawas area tsb (replace-all dari array user_id).
     * $primary_user_id = Pengawas Utama; kalau kosong/tak ada di $user_ids, dipilih elemen pertama.
     */
    public function set_location_spvs($location_id, $user_ids, $primary_user_id = null) {
        $location_id = (int)$location_id;
        $this->db->where('location_id', $location_id)->delete($this->location_spv_table);
        $user_ids = array_values(array_unique(array_filter(array_map('intval', (array)$user_ids))));
        if (empty($user_ids)) { return; }
        $primary_user_id = (int)$primary_user_id;
        if (!in_array($primary_user_id, $user_ids, true)) { $primary_user_id = $user_ids[0]; }
        $now = date('Y-m-d H:i:s');
        $rows = array_map(function ($uid) use ($location_id, $now, $primary_user_id) {
            return ['location_id' => $location_id, 'user_id' => $uid, 'is_primary' => $uid === $primary_user_id ? 1 : 0, 'created_at' => $now];
        }, $user_ids);
        $this->db->insert_batch($this->location_spv_table, $rows);
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

    // ====================================================================
    // KONTAK NOTIFIKASI WA (nama+nomor bebas, dipilih manual per Kode Area)
    // ====================================================================

    public function get_wa_contacts($active_only = false) {
        $this->db->select("{$this->wa_contact_table}.*,
            (SELECT GROUP_CONCAT(ce.user_id) FROM {$this->wa_contact_employee_table} ce
              WHERE ce.contact_id = {$this->wa_contact_table}.id) AS employee_ids,
            (SELECT GROUP_CONCAT(TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) SEPARATOR ', ')
               FROM {$this->wa_contact_employee_table} ce2 JOIN users u ON u.id = ce2.user_id
              WHERE ce2.contact_id = {$this->wa_contact_table}.id) AS employee_names,
            (SELECT GROUP_CONCAT(cd.division_id) FROM {$this->wa_contact_division_table} cd
              WHERE cd.contact_id = {$this->wa_contact_table}.id) AS division_ids,
            (SELECT GROUP_CONCAT(dv.name SEPARATOR ', ')
               FROM {$this->wa_contact_division_table} cd2 JOIN {$this->division_table} dv ON dv.id = cd2.division_id
              WHERE cd2.contact_id = {$this->wa_contact_table}.id) AS division_names")
                 ->from($this->wa_contact_table);
        if ($active_only) { $this->db->where('is_active', 1); }
        return $this->db->order_by('name')->get()->result_array();
    }

    /** Ganti seluruh daftar karyawan terasosiasi langsung ke kontak WA tsb. */
    public function set_wa_contact_employees($contact_id, $user_ids) {
        $contact_id = (int)$contact_id;
        $this->db->where('contact_id', $contact_id)->delete($this->wa_contact_employee_table);
        $user_ids = array_unique(array_filter(array_map('intval', (array)$user_ids)));
        if (empty($user_ids)) { return; }
        $now = date('Y-m-d H:i:s');
        $rows = array_map(function ($uid) use ($contact_id, $now) {
            return ['contact_id' => $contact_id, 'user_id' => $uid, 'created_at' => $now];
        }, $user_ids);
        $this->db->insert_batch($this->wa_contact_employee_table, $rows);
    }

    /** Ganti seluruh daftar divisi terasosiasi ke kontak WA tsb (semua karyawan posisi itu ikut). */
    public function set_wa_contact_divisions($contact_id, $division_ids) {
        $contact_id = (int)$contact_id;
        $this->db->where('contact_id', $contact_id)->delete($this->wa_contact_division_table);
        $division_ids = array_unique(array_filter(array_map('intval', (array)$division_ids)));
        if (empty($division_ids)) { return; }
        $now = date('Y-m-d H:i:s');
        $rows = array_map(function ($did) use ($contact_id, $now) {
            return ['contact_id' => $contact_id, 'division_id' => $did, 'created_at' => $now];
        }, $division_ids);
        $this->db->insert_batch($this->wa_contact_division_table, $rows);
    }

    /**
     * Semua no. HP kantor yang terasosiasi ke karyawan ini -- langsung (ditandai
     * per-orang) ATAU lewat divisi (temuan_division.name dicocokkan ke
     * position.position_name, case-insensitive -- divisi di sini SAMA DENGAN
     * posisi/jabatan karyawan, bukan roster terpisah). Satu karyawan bisa dapat
     * >1 nomor (mis. rangkap jabatan/divisi). Kontak nonaktif tak ikut.
     */
    public function get_employee_notify_phones($user_id) {
        $user_id = (int)$user_id;
        $direct = $this->db->select('c.phone')
                           ->from("{$this->wa_contact_employee_table} ce")
                           ->join("{$this->wa_contact_table} c", 'c.id = ce.contact_id')
                           ->where('ce.user_id', $user_id)
                           ->where('c.is_active', 1)
                           ->get()->result_array();
        $via_division = $this->db->select('c.phone')
                           ->from('users u')
                           ->join('position p', 'p.id = u.position_id')
                           ->join("{$this->division_table} dv", 'LOWER(dv.name) = LOWER(p.position_name)')
                           ->join("{$this->wa_contact_division_table} cd", 'cd.division_id = dv.id')
                           ->join("{$this->wa_contact_table} c", 'c.id = cd.contact_id')
                           ->where('u.id', $user_id)
                           ->where('dv.is_active', 1)
                           ->where('c.is_active', 1)
                           ->get()->result_array();
        $phones = array_merge(array_column($direct, 'phone'), array_column($via_division, 'phone'));
        return array_values(array_unique(array_filter(array_map('trim', $phones))));
    }

    public function get_wa_contact($id) {
        return $this->db->where('id', $id)->get($this->wa_contact_table)->row_array();
    }

    public function save_wa_contact($data, $id = null) {
        if ($id) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->where('id', $id)->update($this->wa_contact_table, $data);
            return $id;
        }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert($this->wa_contact_table, $data);
        return $this->db->insert_id();
    }

    public function delete_wa_contact($id) {
        $this->db->where('contact_id', $id)->delete($this->location_contact_table);
        $this->db->where('contact_id', $id)->delete($this->wa_contact_employee_table);
        $this->db->where('contact_id', $id)->delete($this->wa_contact_division_table);
        $this->db->where('id', $id)->delete($this->wa_contact_table);
    }

    /** Ganti seluruh daftar kontak notifikasi area tsb (replace-all dari array contact_id). */
    public function set_location_contacts($location_id, $contact_ids) {
        $location_id = (int)$location_id;
        $this->db->where('location_id', $location_id)->delete($this->location_contact_table);
        $contact_ids = array_unique(array_filter(array_map('intval', (array)$contact_ids)));
        if (empty($contact_ids)) { return; }
        $now = date('Y-m-d H:i:s');
        $rows = array_map(function ($cid) use ($location_id, $now) {
            return ['location_id' => $location_id, 'contact_id' => $cid, 'created_at' => $now];
        }, $contact_ids);
        $this->db->insert_batch($this->location_contact_table, $rows);
    }

    /** Nomor HP semua kontak notifikasi aktif yang dipilih untuk area tsb. */
    public function get_location_contact_phones($location_id) {
        if (empty($location_id)) { return []; }
        $rows = $this->db->select('c.phone')
                         ->from("{$this->location_contact_table} lc")
                         ->join("{$this->wa_contact_table} c", 'c.id = lc.contact_id')
                         ->where('lc.location_id', (int)$location_id)
                         ->where('c.is_active', 1)
                         ->get()->result_array();
        return array_filter(array_map(function ($r) { return $r['phone']; }, $rows));
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
                           (SELECT GROUP_CONCAT(ls.user_id) FROM {$this->location_spv_table} ls WHERE ls.location_id = l.id) AS spv_user_ids,
                           (SELECT GROUP_CONCAT(TRIM(CONCAT(u4.first_name,' ',COALESCE(u4.last_name,''))) SEPARATOR ', ')
                              FROM {$this->location_spv_table} ls2 JOIN users u4 ON u4.id = ls2.user_id
                             WHERE ls2.location_id = l.id) AS spv_name,
                           (SELECT ls3.user_id FROM {$this->location_spv_table} ls3 WHERE ls3.location_id = l.id AND ls3.is_primary = 1 LIMIT 1) AS primary_spv_id,
                           (SELECT TRIM(CONCAT(u6.first_name,' ',COALESCE(u6.last_name,'')))
                              FROM {$this->location_spv_table} ls4 JOIN users u6 ON u6.id = ls4.user_id
                             WHERE ls4.location_id = l.id AND ls4.is_primary = 1 LIMIT 1) AS primary_spv_name,
                           (SELECT GROUP_CONCAT(TRIM(CONCAT(u7.first_name,' ',COALESCE(u7.last_name,''))) SEPARATOR ', ')
                              FROM {$this->location_spv_table} ls5 JOIN users u7 ON u7.id = ls5.user_id
                             WHERE ls5.location_id = l.id AND ls5.is_primary = 0) AS backup_spv_names,
                           (SELECT GROUP_CONCAT(lp.user_id) FROM {$this->location_pj_table} lp WHERE lp.location_id = l.id) AS pj_user_ids,
                           (SELECT GROUP_CONCAT(TRIM(CONCAT(u2.first_name,' ',COALESCE(u2.last_name,''))) SEPARATOR ', ')
                              FROM {$this->location_pj_table} lp2 JOIN users u2 ON u2.id = lp2.user_id
                             WHERE lp2.location_id = l.id) AS pj_names,
                           (SELECT GROUP_CONCAT(lc.contact_id) FROM {$this->location_contact_table} lc WHERE lc.location_id = l.id) AS contact_ids,
                           (SELECT GROUP_CONCAT(c.name SEPARATOR ', ')
                              FROM {$this->location_contact_table} lc2 JOIN {$this->wa_contact_table} c ON c.id = lc2.contact_id
                             WHERE lc2.location_id = l.id) AS contact_names")
                 ->from("{$this->location_table} l")
                 ->join('branch b', 'b.id = l.branch_id', 'left')
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
            ->select("t.*, l.name AS location_name, b.branch_name, dv.name AS division_name,
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
                      (SELECT GROUP_CONCAT(ls.user_id) FROM {$this->location_spv_table} ls WHERE ls.location_id = l.id) AS spv_user_ids,
                      (SELECT GROUP_CONCAT(TRIM(CONCAT(u4.first_name,' ',COALESCE(u4.last_name,''))) SEPARATOR ', ')
                         FROM {$this->location_spv_table} ls2 JOIN users u4 ON u4.id = ls2.user_id
                        WHERE ls2.location_id = l.id) AS spv_name,
                      (SELECT ls3.user_id FROM {$this->location_spv_table} ls3
                        WHERE ls3.location_id = l.id AND ls3.is_primary = 1 LIMIT 1) AS primary_spv_id,
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
            ->join("{$this->division_table} dv", 'dv.id = l.division_id', 'left')
            ->join("{$this->type_table} ty", 'ty.id = t.type_id', 'left')
            ->join("{$this->category_table} cat", 'cat.id = ty.category_id', 'left')
            ->join('branch b', 'b.id = t.branch_id', 'left')
            ->join('users r', 'r.id = t.reporter_id', 'left')
            ->join('users isv', 'isv.id = t.individu_spv_id', 'left')
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
                 ->join("{$this->location_table} l", 'l.id = t.location_id', 'left')
                 ->join('branch b', 'b.id = t.branch_id', 'left')
                 ->join("{$this->type_table} ty", 'ty.id = t.type_id', 'left')
                 ->join('users r', 'r.id = t.reporter_id', 'left');
        $this->_apply_filters($filters);
        return (int)$this->db->count_all_results();
    }

    private function _apply_filters($filters) {
        if (empty($filters['include_deleted'])) {
            $this->db->where('t.is_deleted', 0);
        }
        // Pembatasan visibilitas: PJ/Pengawas murni (bukan inspector/admin) hanya lihat area yang ditugaskan.
        if (!empty($filters['visible_to'])) {
            $uid = (int)$filters['visible_to'];
            $this->db->where("(
                EXISTS (SELECT 1 FROM {$this->location_pj_table} lp WHERE lp.location_id = l.id AND lp.user_id = {$uid})
                OR EXISTS (SELECT 1 FROM {$this->location_spv_table} ls WHERE ls.location_id = l.id AND ls.user_id = {$uid})
                OR t.individu_spv_id = {$uid}
                OR EXISTS (SELECT 1 FROM {$this->subject_table} sj WHERE sj.temuan_id = t.id AND sj.user_id = {$uid})
            )", null, false);
        }
        // Asisten inspector: HANYA temuan yang dia lapor sendiri (bukan area-assignment).
        if (!empty($filters['reporter_id'])) {
            $this->db->where('t.reporter_id', (int)$filters['reporter_id']);
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
        // Search bebas dipakai tab Dashboard & Laporan -- cari di keterangan,
        // kode area, cabang, jenis temuan, dan nama pelapor sekaligus.
        if (!empty($filters['q'])) {
            $q = trim($filters['q']);
            $lq = $this->db->escape_like_str($q);
            // PJ & Pengawas (SPV) area disimpan di tabel terpisah (banyak-ke-satu
            // lokasi), namanya cuma tersedia sbg subquery GROUP_CONCAT di
            // _select_full() -- tak bisa langsung di-LIKE di WHERE. Dicek lewat
            // EXISTS ke tabel PJ/SPV area + individu_spv_id (pengawas ad-hoc
            // mode individu) supaya cari nama PJ/pengawas ikut ketemu.
            $this->db->group_start()
                ->like('t.description', $q)
                ->or_like('l.name', $q)
                ->or_like('b.branch_name', $q)
                ->or_like('ty.name', $q)
                ->or_like('r.first_name', $q)
                ->or_like('r.last_name', $q)
                ->or_where("EXISTS (SELECT 1 FROM {$this->location_pj_table} lp JOIN users upj ON upj.id = lp.user_id
                    WHERE lp.location_id = l.id AND (upj.first_name LIKE '%{$lq}%' ESCAPE '!' OR upj.last_name LIKE '%{$lq}%' ESCAPE '!'))", null, false)
                ->or_where("EXISTS (SELECT 1 FROM {$this->location_spv_table} ls JOIN users usv ON usv.id = ls.user_id
                    WHERE ls.location_id = l.id AND (usv.first_name LIKE '%{$lq}%' ESCAPE '!' OR usv.last_name LIKE '%{$lq}%' ESCAPE '!'))", null, false)
                ->or_where("EXISTS (SELECT 1 FROM users uisv
                    WHERE uisv.id = t.individu_spv_id AND (uisv.first_name LIKE '%{$lq}%' ESCAPE '!' OR uisv.last_name LIKE '%{$lq}%' ESCAPE '!'))", null, false)
                ->group_end();
        }
    }

    /** Rekap jumlah per status. $vis_filters = ['visible_to'=>uid] atau []. */
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
                OR EXISTS (SELECT 1 FROM {$this->location_spv_table} ls WHERE ls.location_id = l.id AND ls.user_id = {$uid})
                OR t.individu_spv_id = {$uid}
                OR EXISTS (SELECT 1 FROM {$this->subject_table} sj WHERE sj.temuan_id = t.id AND sj.user_id = {$uid})
            )", null, false);
        }
        if (!empty($vis_filters['reporter_id'])) {
            $this->db->where('t.reporter_id', (int)$vis_filters['reporter_id']);
        }
        $rows = $this->db->get()->result_array();
        $out = ['baru' => 0, 'dikerjakan' => 0, 'menunggu_acc' => 0, 'menunggu_acc_tolak' => 0, 'selesai' => 0, 'ditolak' => 0];
        foreach ($rows as $r) {
            $out[$r['status']] = (int)$r['total'];
        }
        return $out;
    }

    /** Baris mentah utk rekap bulanan (exclude soft-delete & ditolak; join info PJ/SPV area). */
    public function get_report_rows($branch_id, $from, $to, $q = null) {
        $this->db
            ->select("t.id, t.status, t.created_at, t.done_at, t.due_at, t.due_extended_at, t.reporter_id, t.description,
                      ty.name AS type_name, ty.target_mode AS type_target_mode, cat.name AS category_name,
                      l.id AS location_id, l.name AS location_name, b.branch_name, dv.name AS division_name,
                      TRIM(CONCAT(r.first_name,' ',COALESCE(r.last_name,''))) AS reporter_name,
                      (SELECT GROUP_CONCAT(TRIM(CONCAT(u2.first_name,' ',COALESCE(u2.last_name,''))) SEPARATOR ', ')
                         FROM {$this->location_pj_table} lp2 JOIN users u2 ON u2.id = lp2.user_id
                        WHERE lp2.location_id = l.id) AS pj_name,
                      (SELECT GROUP_CONCAT(lp3.user_id)
                         FROM {$this->location_pj_table} lp3
                        WHERE lp3.location_id = l.id) AS pj_user_ids,
                      l.division_id,
                      (SELECT TRIM(CONCAT(u5.first_name,' ',COALESCE(u5.last_name,'')))
                         FROM {$this->location_spv_table} ls JOIN users u5 ON u5.id = ls.user_id
                        WHERE ls.location_id = l.id AND ls.is_primary = 1 LIMIT 1) AS spv_name,
                      (SELECT GROUP_CONCAT(ls2.user_id)
                         FROM {$this->location_spv_table} ls2
                        WHERE ls2.location_id = l.id) AS spv_user_ids,
                      TRIM(CONCAT(isv.first_name,' ',COALESCE(isv.last_name,''))) AS individu_spv_name,
                      (SELECT GROUP_CONCAT(TRIM(CONCAT(u3.first_name,' ',COALESCE(u3.last_name,''))) SEPARATOR ', ')
                         FROM {$this->subject_table} sj2 JOIN users u3 ON u3.id = sj2.user_id
                        WHERE sj2.temuan_id = t.id) AS subject_names")
            ->from("{$this->temuan_table} t")
            ->join("{$this->location_table} l", 'l.id = t.location_id', 'left')
            ->join("{$this->division_table} dv", 'dv.id = l.division_id', 'left')
            ->join("{$this->type_table} ty", 'ty.id = t.type_id', 'left')
            ->join("{$this->category_table} cat", 'cat.id = ty.category_id', 'left')
            ->join('branch b', 'b.id = t.branch_id', 'left')
            ->join('users r', 'r.id = t.reporter_id', 'left')
            ->join('users isv', 'isv.id = t.individu_spv_id', 'left')
            ->where('t.is_deleted', 0)
            ->where('t.status !=', 'ditolak')
            ->where('t.created_at >=', $from . ' 00:00:00')
            ->where('t.created_at <=', $to . ' 23:59:59');
        if ($branch_id !== null) { $this->db->where('t.branch_id', $branch_id); }
        if (!empty($q)) {
            $q = trim($q);
            $this->db->group_start()
                ->like('t.description', $q)
                ->or_like('l.name', $q)
                ->or_like('b.branch_name', $q)
                ->or_like('ty.name', $q)
                ->or_like('r.first_name', $q)
                ->or_like('r.last_name', $q)
                ->group_end();
        }
        return $this->db->get()->result_array();
    }

    /** Baris temuan mode OBJEK (per Kode Area) utk pivot rekap per-jenis di Excel. */
    public function get_pivot_rows_objek($branch_id, $from, $to) {
        $this->db
            ->select("t.id, t.status, t.created_at, t.done_at, t.due_at, t.due_extended_at,
                      t.type_id, ty.name AS type_name, ty.requires_action AS type_requires_action,
                      l.id AS location_id, l.name AS location_name, b.id AS branch_id, b.branch_name,
                      (SELECT GROUP_CONCAT(TRIM(CONCAT(u2.first_name,' ',COALESCE(u2.last_name,''))) SEPARATOR ', ')
                         FROM {$this->location_pj_table} lp2 JOIN users u2 ON u2.id = lp2.user_id
                        WHERE lp2.location_id = l.id) AS pj_name,
                      (SELECT TRIM(CONCAT(u5.first_name,' ',COALESCE(u5.last_name,'')))
                         FROM {$this->location_spv_table} ls JOIN users u5 ON u5.id = ls.user_id
                        WHERE ls.location_id = l.id AND ls.is_primary = 1 LIMIT 1) AS spv_name")
            ->from("{$this->temuan_table} t")
            ->join("{$this->location_table} l", 'l.id = t.location_id', 'inner')
            ->join("{$this->type_table} ty", 'ty.id = t.type_id', 'inner')
            ->join('branch b', 'b.id = t.branch_id', 'left')
            ->where('t.is_deleted', 0)
            ->where('t.status !=', 'ditolak')
            ->where('ty.target_mode', 'objek')
            ->where('t.created_at >=', $from . ' 00:00:00')
            ->where('t.created_at <=', $to . ' 23:59:59');
        if ($branch_id !== null) { $this->db->where('t.branch_id', $branch_id); }
        return $this->db->get()->result_array();
    }

    /** Baris temuan mode OBJEK, satu baris per (temuan,PJ) -- utk rekap per-orang (PJ area bisa >1 orang). */
    public function get_pivot_rows_objek_per_pj($branch_id, $from, $to) {
        $this->db
            ->select("t.id, t.status, t.created_at, t.done_at, t.due_at, t.due_extended_at,
                      t.type_id, ty.name AS type_name, ty.requires_action AS type_requires_action,
                      lp.user_id AS pj_user_id,
                      TRIM(CONCAT(u2.first_name,' ',COALESCE(u2.last_name,''))) AS pj_name,
                      b.id AS branch_id, b.branch_name")
            ->from("{$this->temuan_table} t")
            ->join("{$this->location_table} l", 'l.id = t.location_id', 'inner')
            ->join("{$this->location_pj_table} lp", 'lp.location_id = l.id', 'inner')
            ->join('users u2', 'u2.id = lp.user_id', 'inner')
            ->join("{$this->type_table} ty", 'ty.id = t.type_id', 'inner')
            ->join('branch b', 'b.id = t.branch_id', 'left')
            ->where('t.is_deleted', 0)
            ->where('t.status !=', 'ditolak')
            ->where('ty.target_mode', 'objek')
            ->where('t.created_at >=', $from . ' 00:00:00')
            ->where('t.created_at <=', $to . ' 23:59:59');
        if ($branch_id !== null) { $this->db->where('t.branch_id', $branch_id); }
        return $this->db->get()->result_array();
    }

    /** Baris temuan mode INDIVIDU (per Mitra), satu baris per (temuan,mitra) -- utk pivot rekap Excel. */
    public function get_pivot_rows_individu($branch_id, $from, $to) {
        $this->db
            ->select("t.id, t.status, t.created_at, t.done_at, t.due_at, t.due_extended_at,
                      t.type_id, ty.name AS type_name, ty.requires_action AS type_requires_action,
                      sj.user_id AS subject_user_id,
                      TRIM(CONCAT(u3.first_name,' ',COALESCE(u3.last_name,''))) AS subject_name,
                      b.id AS branch_id, b.branch_name,
                      TRIM(CONCAT(isv.first_name,' ',COALESCE(isv.last_name,''))) AS individu_spv_name")
            ->from("{$this->temuan_table} t")
            ->join("{$this->subject_table} sj", 'sj.temuan_id = t.id', 'inner')
            ->join('users u3', 'u3.id = sj.user_id', 'inner')
            ->join("{$this->type_table} ty", 'ty.id = t.type_id', 'inner')
            ->join('branch b', 'b.id = t.branch_id', 'left')
            ->join('users isv', 'isv.id = t.individu_spv_id', 'left')
            ->where('t.is_deleted', 0)
            ->where('t.status !=', 'ditolak')
            ->where('ty.target_mode', 'individu')
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

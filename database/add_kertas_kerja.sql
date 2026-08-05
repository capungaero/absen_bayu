-- Kertas Kerja: to-do list harian karyawan (checklist + catatan bebas).
-- kertas_kerja       = header 1 baris per (user_id, kerja_date).
-- kertas_kerja_item  = baris checklist per hari (delete+reinsert saat submit ulang).
-- kertas_kerja_spv_assignment = penugasan manual SPV -> karyawan yang diawasi
--   (bukan scope per-cabang, karena SPV bisa ditugaskan lintas cabang).
CREATE TABLE IF NOT EXISTS `kertas_kerja` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `kerja_date` DATE NOT NULL,
  `notes`      TEXT NULL DEFAULT NULL,
  `status`     ENUM('new','read') NOT NULL DEFAULT 'new',
  `read_by`    INT NULL DEFAULT NULL,
  `read_at`    DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL,
  UNIQUE KEY `uniq_user_date` (`user_id`, `kerja_date`),
  INDEX `idx_status` (`status`),
  INDEX `idx_kerja_date` (`kerja_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kertas_kerja_item` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `kertas_kerja_id` INT UNSIGNED NOT NULL,
  `item_text`       VARCHAR(500) NOT NULL,
  `is_done`         TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order`      INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_kk_id` (`kertas_kerja_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kertas_kerja_spv_assignment` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `spv_user_id`      INT NOT NULL,
  `employee_user_id` INT NOT NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by`       INT NULL DEFAULT NULL,
  UNIQUE KEY `uniq_spv_employee` (`spv_user_id`, `employee_user_id`),
  INDEX `idx_spv` (`spv_user_id`),
  INDEX `idx_employee` (`employee_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

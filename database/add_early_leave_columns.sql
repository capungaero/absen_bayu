-- ============================================================================
-- Fitur: Izin Pulang Cepat (Pulang Lebih Awal / PLA)
-- ============================================================================
-- Aturan:
--   * Centang Izin Pulang Cepat -> user bisa isi Jam Selesai < shift.end_time.
--   * Kekurangan jam dihitung otomatis (kekurangan = expected_net - actual_net).
--   * Absensi tampil biru + badge PLA di tabel presensi.
--   * Potongan masuk ke payroll_deduction dengan keterangan
--     "Potongan Izin Pulang Lebih Awal (auto)" (deduction_id sesuai cabang).
--   * Jika net work hours < 5 jam -> presence_status='deny' (tidak hadir).
--
-- Formula potongan:
--   hourly_rate     = users.salary / cal_days_in_month(month) / 10
--   deduction_total = hourly_rate * total_short_hours_in_month
--
-- Migration ini idempotent: dicek lewat information_schema sebelum ALTER,
-- aman dijalankan ulang. Catatan: payroll_deduction.deduction_note di skema
-- production sudah ada sebagai TEXT (kolom direferensi code di Payroll
-- controller tetapi tidak di dump SQL lama), karena itu tidak diubah lagi
-- di sini supaya tidak overwrite data existing.
-- ============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_add_early_leave_columns $$
CREATE PROCEDURE sp_add_early_leave_columns()
BEGIN
    -- presence.is_early_leave
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'presence' AND COLUMN_NAME = 'is_early_leave'
    ) THEN
        ALTER TABLE `presence`
            ADD COLUMN `is_early_leave` TINYINT(1) NOT NULL DEFAULT 0
                COMMENT 'Flag Izin Pulang Cepat (PLA)' AFTER `flag`;
    END IF;

    -- presence.early_leave_short_minutes
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'presence' AND COLUMN_NAME = 'early_leave_short_minutes'
    ) THEN
        ALTER TABLE `presence`
            ADD COLUMN `early_leave_short_minutes` INT NOT NULL DEFAULT 0
                COMMENT 'Kekurangan jam kerja dalam menit, 0 kalau bukan early leave'
                AFTER `is_early_leave`;
    END IF;

    -- Index untuk lookup PLA per periode
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'presence' AND INDEX_NAME = 'idx_presence_early_leave'
    ) THEN
        CREATE INDEX `idx_presence_early_leave`
            ON `presence` (`is_early_leave`, `flow_date`);
    END IF;

    -- presence_daily_report.is_early_leave
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'presence_daily_report' AND COLUMN_NAME = 'is_early_leave'
    ) THEN
        ALTER TABLE `presence_daily_report`
            ADD COLUMN `is_early_leave` TINYINT(1) NOT NULL DEFAULT 0
                COMMENT 'Mirror dari presence.is_early_leave' AFTER `presence_status`;
    END IF;

    -- presence_daily_report.early_leave_short_minutes
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'presence_daily_report' AND COLUMN_NAME = 'early_leave_short_minutes'
    ) THEN
        ALTER TABLE `presence_daily_report`
            ADD COLUMN `early_leave_short_minutes` INT NOT NULL DEFAULT 0
                COMMENT 'Mirror dari presence.early_leave_short_minutes'
                AFTER `is_early_leave`;
    END IF;

    -- Index PLA di daily report
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'presence_daily_report' AND INDEX_NAME = 'idx_pdr_early_leave'
    ) THEN
        CREATE INDEX `idx_pdr_early_leave`
            ON `presence_daily_report` (`is_early_leave`, `flow_date`);
    END IF;

    -- payroll_deduction.deduction_note (TEXT atau VARCHAR, terserah versi yang lebih dulu).
    -- Code di Payroll::save_deduction sudah referensi kolom ini; di skema lama
    -- mungkin belum ada. Tambah sebagai TEXT supaya panjang note tidak terbatas.
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_deduction' AND COLUMN_NAME = 'deduction_note'
    ) THEN
        ALTER TABLE `payroll_deduction`
            ADD COLUMN `deduction_note` TEXT DEFAULT NULL
                COMMENT 'Keterangan potongan (mis. "Potongan Izin Pulang Lebih Awal")'
                AFTER `deduction_amount`;
    END IF;
END $$

DELIMITER ;

CALL sp_add_early_leave_columns();
DROP PROCEDURE IF EXISTS sp_add_early_leave_columns;

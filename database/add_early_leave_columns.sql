-- ============================================================================
-- Fitur: Izin Pulang Cepat (Pulang Lebih Awal / PLA)
-- ============================================================================
-- Aturan:
--   * Centang Izin Pulang Cepat -> user bisa isi Jam Selesai < shift.end_time.
--   * Kekurangan jam dihitung otomatis (kekurangan = expected_net - actual_net).
--   * Absensi tampil biru + badge PLA di tabel presensi.
--   * Potongan masuk ke payroll_deduction dengan keterangan
--     "Potongan Izin Pulang Lebih Awal" (deduction_id sesuai cabang).
--   * Jika net work hours < 5 jam -> presence_status='deny' (tidak hadir).
--
-- Formula potongan:
--   hourly_rate     = users.salary / cal_days_in_month(month) / 10
--   deduction_total = hourly_rate * total_short_hours_in_month
--
-- Idempotent: gunakan IF NOT EXISTS untuk MySQL 8.x. Untuk MySQL 5.x, jalankan
-- manual pakai ALTER TABLE biasa setelah cek dengan SHOW COLUMNS.
-- ============================================================================

ALTER TABLE `presence`
  ADD COLUMN `is_early_leave` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Flag Izin Pulang Cepat (PLA)' AFTER `flag`,
  ADD COLUMN `early_leave_short_minutes` INT NOT NULL DEFAULT 0
    COMMENT 'Kekurangan jam kerja dalam menit, 0 kalau bukan early leave'
    AFTER `is_early_leave`;

CREATE INDEX `idx_presence_early_leave`
  ON `presence` (`is_early_leave`, `flow_date`);

-- Code di Payroll::save_deduction() sudah refer ke deduction_note, tapi
-- skema lama tidak punya kolom ini -> insert silently kena warning/dropped.
-- Tambah sekarang supaya konsisten dan bisa simpan keterangan potongan.
ALTER TABLE `payroll_deduction`
  ADD COLUMN `deduction_note` VARCHAR(255) DEFAULT NULL
    COMMENT 'Keterangan potongan (mis. "Potongan Izin Pulang Lebih Awal")'
    AFTER `deduction_amount`;

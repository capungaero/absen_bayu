-- =============================================================
-- Tambah kolom jenis kelamin ke tabel users (data karyawan).
-- Dibiarkan kosong (NULL) — data akan diimport kemudian.
--
-- Sudah dijalankan di PRODUKSI (tifx3722_newtiffa_timesheet) 2026-05-30.
-- Idempotent: aman dijalankan ulang (IF NOT EXISTS, MariaDB/MySQL 8+).
--
-- Cara apply di lokal:
--   mysql -u root newtiffa_timesheet < database/add_gender_column.sql
-- =============================================================

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `jenis_kelamin` VARCHAR(20) DEFAULT NULL AFTER `last_name`;

-- Roster tahunan PPh21 (tools/pph21_export): urutan karyawan per CV tetap setahun.
-- Aturan pajak: susunan mengikuti kertas kerja awal (Mei utk 2026) sampai Desember;
-- karyawan resign tetap dilaporkan (nihil), karyawan baru ditambah di bawah (permanen).
-- Prod 2026 sudah di-seed dari kertas kerja manual Juni (10 Jul 2026).
CREATE TABLE IF NOT EXISTS pph21_roster (
  id INT AUTO_INCREMENT PRIMARY KEY,
  year INT NOT NULL, subdivision_id INT NOT NULL,
  user_id INT UNSIGNED NULL, nik VARCHAR(32) DEFAULT '',
  name VARCHAR(200) NOT NULL, name_key VARCHAR(200) NOT NULL,
  position VARCHAR(200) DEFAULT '', sort_order INT NOT NULL,
  created_at DATETIME,
  UNIQUE KEY uq_order (year, subdivision_id, sort_order),
  KEY k_lookup (year, subdivision_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

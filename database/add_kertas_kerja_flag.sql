-- Kertas Kerja: penanda per-karyawan "wajib isi kertas kerja harian" di PWA.
-- Ditoggle admin di halaman Setting Kertas Kerja (KertasKerjaSetting.php).
-- Pola sama database/add_payroll_insentif_is_manual.sql.
ALTER TABLE users
  ADD COLUMN wajib_kertas_kerja TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'Wajib isi Kertas Kerja harian di PWA -- toggle admin di Setting Kertas Kerja'
  AFTER active;

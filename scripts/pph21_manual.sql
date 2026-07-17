-- Data manual PPh21 (tools/pph21_manual): nilai 4 kolom isian manual kertas kerja
-- Export PPh21 (data di luar absensi), per karyawan per payroll.
--   tunjangan = uang jalan kanvas (ditambahkan ke kolom Tunjangan/Cash Bon)
--   insentif  = uang konsumsi / tunjangan lain (kolom Insentif/Tunjangan Lain)
--   subsidi   = subsidi pajak / lembur (kolom Subsidi Pajak / Lembur)
--   bonus     = bonus / THR (kolom Bonus/THR)
-- Diisi via halaman tools/pph21_manual (manual atau import template Excel);
-- Export PPh21 membaca tabel ini dan mengisi kolomnya otomatis.
CREATE TABLE IF NOT EXISTS pph21_manual (
  id INT AUTO_INCREMENT PRIMARY KEY,
  payroll_id INT NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  tunjangan DECIMAL(14,2) NOT NULL DEFAULT 0,
  insentif  DECIMAL(14,2) NOT NULL DEFAULT 0,
  subsidi   DECIMAL(14,2) NOT NULL DEFAULT 0,
  bonus     DECIMAL(14,2) NOT NULL DEFAULT 0,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME,
  UNIQUE KEY uq_payroll_user (payroll_id, user_id),
  KEY k_payroll (payroll_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

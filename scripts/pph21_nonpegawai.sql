-- PPh21 non-pegawai-tetap (tools/pph21_nonpegawai): pegawai tidak tetap &
-- bukan pegawai/tenaga ahli, per masa pajak per CV. Data diimport dari Excel
-- tiap bulan (komposisi bebas), dihitung otomatis, diexport ke kertas kerja
-- Excel + XML Coretax Bp21Bulk (1 file gabungan per CV).
-- Import bersifat REPLACE per (masa, CV) supaya re-import revisi tidak dobel.
CREATE TABLE IF NOT EXISTS pph21_nonpegawai (
  id INT AUTO_INCREMENT PRIMARY KEY,
  month INT NOT NULL,
  year INT NOT NULL,
  subdivision_id INT NOT NULL,
  kode_objek VARCHAR(12) NOT NULL,      -- 21-100-35 / 21-100-24 / 21-100-07 / dst
  nik VARCHAR(32) NOT NULL,
  nama VARCHAR(200) NOT NULL,
  keterangan VARCHAR(255) DEFAULT '',
  ptkp VARCHAR(8) NOT NULL DEFAULT 'TK/0',
  hari_kerja INT NOT NULL DEFAULT 0,    -- utk kode tarif HARIAN
  neto DECIMAL(14,2) NOT NULL,          -- pembayaran bersih (input admin)
  gross_up TINYINT(1) NOT NULL DEFAULT 1,
  bruto DECIMAL(18,6) NOT NULL,         -- hasil hitung (presisi utk XML Coretax)
  deemed INT NOT NULL,                  -- % DPP (100/50)
  tarif DECIMAL(6,3) NOT NULL,          -- Rate final utk XML
  pph DECIMAL(14,2) NOT NULL,
  doc_number VARCHAR(60) NOT NULL,      -- DocumentNumber (mis. 58/EKS/BRL/VI/2026)
  doc_date DATE NOT NULL,               -- DocumentDate (tanggal pembayaran)
  created_by INT UNSIGNED NULL,
  created_at DATETIME,
  KEY k_masa (year, month, subdivision_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

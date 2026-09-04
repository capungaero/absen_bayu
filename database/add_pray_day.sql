-- Lapis harian sholat (pray_day), analog attendance_day tapi 6 slot waktu
-- (subuh/dzuhur/ashar/maghrib/isha/friday) alih-alih 4 slot kerja.
-- m_* = cermin hasil klasifikasi mesin (selalu ditimpa tiap klasifikasi ulang);
-- kolom tanpa prefiks = nilai EFEKTIF yang menurunkan presence (Pray_deriver_model),
-- dipertahankan kalau is_edited=1 (koreksi admin).
--
-- BEDA PENTING dari attendance_day: pray_day TIDAK menurunkan presence.presence_type
-- atau membuat baris presence baru -- sholat selalu "menempel" pada baris presence
-- yang sudah ada (dibuat jalur attendance/derive kerja). Kalau presence hari itu
-- belum ada, Pray_deriver_model men-skip baris itu (skipped_no_presence), sama
-- persis perilaku _import_pray_sheet() yang sudah ada.
CREATE TABLE IF NOT EXISTS pray_day (
  user_id INT NOT NULL,
  flow_date DATE NOT NULL,

  m_subuh_in TIME NULL, m_subuh_out TIME NULL,
  m_dzuhur_in TIME NULL, m_dzuhur_out TIME NULL,
  m_ashar_in TIME NULL, m_ashar_out TIME NULL,
  m_maghrib_in TIME NULL, m_maghrib_out TIME NULL,
  m_isha_in TIME NULL, m_isha_out TIME NULL,
  m_friday_in TIME NULL, m_friday_out TIME NULL,

  subuh_in TIME NULL, subuh_out TIME NULL, subuh_late INT NOT NULL DEFAULT 0,
  dzuhur_in TIME NULL, dzuhur_out TIME NULL, dzuhur_late INT NOT NULL DEFAULT 0,
  ashar_in TIME NULL, ashar_out TIME NULL, ashar_late INT NOT NULL DEFAULT 0,
  maghrib_in TIME NULL, maghrib_out TIME NULL, maghrib_late INT NOT NULL DEFAULT 0,
  isha_in TIME NULL, isha_out TIME NULL, isha_late INT NOT NULL DEFAULT 0,
  friday_in TIME NULL, friday_out TIME NULL, friday_late INT NOT NULL DEFAULT 0,

  tap_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  all_taps VARCHAR(255) NOT NULL DEFAULT '',
  classify_method VARCHAR(12) NOT NULL DEFAULT 'window' COMMENT 'window|manual',
  needs_reclass TINYINT(1) NOT NULL DEFAULT 0,
  classified_at DATETIME NULL,

  is_edited TINYINT(1) NOT NULL DEFAULT 0,
  edit_note VARCHAR(255) NULL,
  edited_by INT NULL,
  edited_at DATETIME NULL,

  derived_at DATETIME NULL COMMENT 'terakhir kali sukses diturunkan ke presence',
  derive_status VARCHAR(20) NULL COMMENT 'ok|locked|skipped_no_presence|error',

  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,

  PRIMARY KEY (user_id, flow_date),
  KEY idx_pray_day_date_user (flow_date, user_id),
  KEY idx_pray_day_needs (needs_reclass, flow_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

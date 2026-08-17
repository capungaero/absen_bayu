-- =============================================================================
-- Trigger provenance tabel `presence` — default-deny overwrite (Jul 2026).
--
-- Masalah yang ditutup: perlindungan edit manusia bergantung pada penulis yang
-- INGAT set input_by='manual'. Edit langsung ke DB (mysql CLI oleh AI agent,
-- script ad-hoc) tidak set kolom itu, sehingga sync berikutnya menimpanya.
--
-- Solusi: balik default-nya di level DB. Semua perubahan data pada `presence`
-- otomatis ditandai input_by='manual' KECUALI koneksi menyatakan diri sebagai
-- sync resmi via session variable:  SET @absen_sync_ctx = 1;
--
-- Pemegang "kartu sync" (@absen_sync_ctx) — HANYA jalur sync mesin resmi:
--   - hr/Presence.php : upload(), upload_pray(), sync_cloud(), sync_pray_cloud(),
--                       sync_cron()
--   - Wa.php          : _sync_today_attendance()
--   - scripts/vps/absen_sync.py (init_command pada pymysql.connect)
-- Penulis sync BARU wajib ikut set variable ini; kalau lupa, tulisannya
-- membeku sebagai manual (fail-safe: data terlindungi, bukan hilang).
--
-- Escape hatch (sengaja dibuat "berat"): admin yang SENGAJA mau mengembalikan
-- baris manual menjadi milik mesin harus set  SET @absen_sync_ctx = 'admin_reset';
-- sebelum UPDATE yang menurunkan input_by.
--
-- URUTAN DEPLOY: jalankan file ini SETELAH kode ctx di atas ter-deploy, dan
-- DI LUAR jam cron 09:00/21:00 (kalau trigger duluan, cron menandai semua baris
-- hasil sync sebagai manual dan membekukannya).
--
-- Kompatibel MariaDB >= 10.2.3 (multi-trigger per event). Trigger audit lama
-- (presence_audit_* di add_audit_log.sql) adalah AFTER INSERT/UPDATE, jadi
-- berjalan SETELAH trigger ini dan ikut merekam input_by yang sudah dikoreksi.
--
-- Cara pasang (SSH server produksi):
--   mysql --defaults-extra-file=$HOME/.absen.cnf tifx3722_newtiffa_timesheet \
--     < database/add_provenance_triggers.sql
-- =============================================================================

DELIMITER $$

DROP TRIGGER IF EXISTS presence_provenance_bi$$
CREATE TRIGGER presence_provenance_bi
BEFORE INSERT ON presence
FOR EACH ROW
BEGIN
  IF @absen_sync_ctx IS NULL THEN
    SET NEW.input_by = 'manual';
    SET NEW.input_by_user_id = COALESCE(NEW.input_by_user_id, @audit_user_id);
  END IF;
END$$

-- v2 (Fase 2): + perawatan & penegakan `cleared_fields` (lihat
-- add_cleared_fields.sql — kolom tsb HARUS sudah ada sebelum trigger ini).
-- v3 (19 Jul 2026): cakupan cleared_fields diperluas ke 6 anchor sholat
-- (subuh/dzuhur/ashar/maghrib/isha/friday _time_in) — pengosongan sholat
-- manual kini juga terlindungi dari refill sync, sama seperti 4 kolom kerja.
-- v4 (17 Agu 2026): sebelum ini, trigger cuma melindungi (a) label input_by
-- dan (b) kolom yang SUDAH DIKOSONGKAN manual (cleared_fields). Kolom yang
-- SUDAH TERISI dan TIDAK di-cleared sama sekali tidak dijaga di level trigger
-- -- perlindungan "jangan timpa yang udah keisi" 100% mengandalkan tiap kode
-- aplikasi (PHP presence_merge_preserve_existing, Python upsert_presence)
-- benar terus. Terbukti rapuh: ditemukan baris presence dengan input_by
-- tetap 'manual' tapi rest_time_in/rest_time_out (sudah terisi, bukan
-- cleared) tertimpa nilai lain lewat sync otomatis, menghasilkan rest_time_out
-- < rest_time_in (durasi istirahat negatif). Jalur tulis persisnya tidak
-- terlacak pasti -- tapi celahnya nyata: satu bug di kode aplikasi manapun
-- (sekarang atau nanti) bisa lolos tanpa terjaring trigger sama sekali.
-- Fix: tambah lapis pertahanan terakhir -- kalau input_by='manual' dan kolom
-- SUDAH TERISI (bukan cleared), trigger sekarang MENOLAK nilai baru & pasang
-- balik nilai lama, apa pun jalur/kode yang menulisnya. Escape hatch tetap
-- sama: admin_reset (menimpa branch ini sepenuhnya).
DROP TRIGGER IF EXISTS presence_provenance_bu$$
CREATE TRIGGER presence_provenance_bu
BEFORE UPDATE ON presence
FOR EACH ROW
BEGIN
  -- Kolom pembukuan (created_at/updated_at/flag/input_by*) sengaja dikecualikan:
  -- update no-op / bump timestamp tidak boleh mengubah provenance.
  DECLARE data_changed BOOL DEFAULT NOT (
        OLD.entry_time      <=> NEW.entry_time
    AND OLD.entry_time_late <=> NEW.entry_time_late
    AND OLD.out_time        <=> NEW.out_time
    AND OLD.rest_time_in    <=> NEW.rest_time_in
    AND OLD.rest_time_out   <=> NEW.rest_time_out
    AND OLD.rest_time_late  <=> NEW.rest_time_late
    AND OLD.subuh_time_in     <=> NEW.subuh_time_in
    AND OLD.subuh_time_out    <=> NEW.subuh_time_out
    AND OLD.subuh_time_late   <=> NEW.subuh_time_late
    AND OLD.dzuhur_time_in    <=> NEW.dzuhur_time_in
    AND OLD.dzuhur_time_out   <=> NEW.dzuhur_time_out
    AND OLD.dzuhur_time_late  <=> NEW.dzuhur_time_late
    AND OLD.ashar_time_in     <=> NEW.ashar_time_in
    AND OLD.ashar_time_out    <=> NEW.ashar_time_out
    AND OLD.ashar_time_late   <=> NEW.ashar_time_late
    AND OLD.maghrib_time_in   <=> NEW.maghrib_time_in
    AND OLD.maghrib_time_out  <=> NEW.maghrib_time_out
    AND OLD.maghrib_time_late <=> NEW.maghrib_time_late
    AND OLD.isha_time_in      <=> NEW.isha_time_in
    AND OLD.isha_time_out     <=> NEW.isha_time_out
    AND OLD.isha_time_late    <=> NEW.isha_time_late
    AND OLD.friday_time_in    <=> NEW.friday_time_in
    AND OLD.friday_time_out   <=> NEW.friday_time_out
    AND OLD.friday_time_late  <=> NEW.friday_time_late
    AND OLD.presence_type     <=> NEW.presence_type
    AND OLD.presence_status   <=> NEW.presence_status
    AND OLD.presence_get_paid <=> NEW.presence_get_paid
    AND OLD.is_overtime       <=> NEW.is_overtime
    AND OLD.is_early_leave    <=> NEW.is_early_leave
    AND OLD.early_leave_short_minutes <=> NEW.early_leave_short_minutes
  );

  IF @absen_sync_ctx IS NULL THEN
    IF data_changed THEN
      SET NEW.input_by = 'manual';
      SET NEW.input_by_user_id = COALESCE(@audit_user_id, NEW.input_by_user_id);
    END IF;

    -- Perawatan cleared_fields: catat pengosongan sengaja, hapus saat diisi lagi.
    IF OLD.entry_time IS NOT NULL AND NEW.entry_time IS NULL
       AND FIND_IN_SET('entry_time', NEW.cleared_fields) = 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM CONCAT(NEW.cleared_fields, ',entry_time'));
    ELSEIF NEW.entry_time IS NOT NULL AND FIND_IN_SET('entry_time', NEW.cleared_fields) > 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', NEW.cleared_fields, ','), ',entry_time,', ','));
    END IF;
    IF OLD.out_time IS NOT NULL AND NEW.out_time IS NULL
       AND FIND_IN_SET('out_time', NEW.cleared_fields) = 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM CONCAT(NEW.cleared_fields, ',out_time'));
    ELSEIF NEW.out_time IS NOT NULL AND FIND_IN_SET('out_time', NEW.cleared_fields) > 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', NEW.cleared_fields, ','), ',out_time,', ','));
    END IF;
    IF OLD.rest_time_in IS NOT NULL AND NEW.rest_time_in IS NULL
       AND FIND_IN_SET('rest_time_in', NEW.cleared_fields) = 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM CONCAT(NEW.cleared_fields, ',rest_time_in'));
    ELSEIF NEW.rest_time_in IS NOT NULL AND FIND_IN_SET('rest_time_in', NEW.cleared_fields) > 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', NEW.cleared_fields, ','), ',rest_time_in,', ','));
    END IF;
    IF OLD.rest_time_out IS NOT NULL AND NEW.rest_time_out IS NULL
       AND FIND_IN_SET('rest_time_out', NEW.cleared_fields) = 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM CONCAT(NEW.cleared_fields, ',rest_time_out'));
    ELSEIF NEW.rest_time_out IS NOT NULL AND FIND_IN_SET('rest_time_out', NEW.cleared_fields) > 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', NEW.cleared_fields, ','), ',rest_time_out,', ','));
    END IF;

    -- Sholat: anchor per waktu sholat = kolom {pray}_time_in (samakan dgn
    -- konvensi merge _import_pray_sheet & absen_sync.py::sync_pray_machine
    -- yang mengisi in+out sekaligus berdasar kekosongan in-nya saja).
    IF OLD.subuh_time_in IS NOT NULL AND NEW.subuh_time_in IS NULL
       AND FIND_IN_SET('subuh_time_in', NEW.cleared_fields) = 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM CONCAT(NEW.cleared_fields, ',subuh_time_in'));
    ELSEIF NEW.subuh_time_in IS NOT NULL AND FIND_IN_SET('subuh_time_in', NEW.cleared_fields) > 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', NEW.cleared_fields, ','), ',subuh_time_in,', ','));
    END IF;
    IF OLD.dzuhur_time_in IS NOT NULL AND NEW.dzuhur_time_in IS NULL
       AND FIND_IN_SET('dzuhur_time_in', NEW.cleared_fields) = 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM CONCAT(NEW.cleared_fields, ',dzuhur_time_in'));
    ELSEIF NEW.dzuhur_time_in IS NOT NULL AND FIND_IN_SET('dzuhur_time_in', NEW.cleared_fields) > 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', NEW.cleared_fields, ','), ',dzuhur_time_in,', ','));
    END IF;
    IF OLD.ashar_time_in IS NOT NULL AND NEW.ashar_time_in IS NULL
       AND FIND_IN_SET('ashar_time_in', NEW.cleared_fields) = 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM CONCAT(NEW.cleared_fields, ',ashar_time_in'));
    ELSEIF NEW.ashar_time_in IS NOT NULL AND FIND_IN_SET('ashar_time_in', NEW.cleared_fields) > 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', NEW.cleared_fields, ','), ',ashar_time_in,', ','));
    END IF;
    IF OLD.maghrib_time_in IS NOT NULL AND NEW.maghrib_time_in IS NULL
       AND FIND_IN_SET('maghrib_time_in', NEW.cleared_fields) = 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM CONCAT(NEW.cleared_fields, ',maghrib_time_in'));
    ELSEIF NEW.maghrib_time_in IS NOT NULL AND FIND_IN_SET('maghrib_time_in', NEW.cleared_fields) > 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', NEW.cleared_fields, ','), ',maghrib_time_in,', ','));
    END IF;
    IF OLD.isha_time_in IS NOT NULL AND NEW.isha_time_in IS NULL
       AND FIND_IN_SET('isha_time_in', NEW.cleared_fields) = 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM CONCAT(NEW.cleared_fields, ',isha_time_in'));
    ELSEIF NEW.isha_time_in IS NOT NULL AND FIND_IN_SET('isha_time_in', NEW.cleared_fields) > 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', NEW.cleared_fields, ','), ',isha_time_in,', ','));
    END IF;
    IF OLD.friday_time_in IS NOT NULL AND NEW.friday_time_in IS NULL
       AND FIND_IN_SET('friday_time_in', NEW.cleared_fields) = 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM CONCAT(NEW.cleared_fields, ',friday_time_in'));
    ELSEIF NEW.friday_time_in IS NOT NULL AND FIND_IN_SET('friday_time_in', NEW.cleared_fields) > 0 THEN
      SET NEW.cleared_fields = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', NEW.cleared_fields, ','), ',friday_time_in,', ','));
    END IF;

  ELSEIF @absen_sync_ctx = 'admin_reset' THEN
    -- Reset sengaja: baris kembali milik mesin, tanda cleared ikut dihapus.
    SET NEW.cleared_fields = '';
  ELSE
    IF OLD.input_by = 'manual' AND NOT (NEW.input_by <=> 'manual') THEN
      -- Ratchet: sync tidak pernah bisa menurunkan manual -> system.
      SET NEW.input_by = 'manual';
    END IF;

    -- Penegakan: sync dilarang mengisi ulang kolom ber-tanda cleared.
    -- (Pembaca merge di PHP/Python juga skip; ini lapis pengaman terakhir.)
    IF FIND_IN_SET('entry_time', OLD.cleared_fields) > 0
       AND OLD.entry_time IS NULL AND NEW.entry_time IS NOT NULL THEN
      SET NEW.entry_time = NULL, NEW.entry_time_late = OLD.entry_time_late;
    END IF;
    IF FIND_IN_SET('out_time', OLD.cleared_fields) > 0
       AND OLD.out_time IS NULL AND NEW.out_time IS NOT NULL THEN
      SET NEW.out_time = NULL;
    END IF;
    IF FIND_IN_SET('rest_time_in', OLD.cleared_fields) > 0
       AND OLD.rest_time_in IS NULL AND NEW.rest_time_in IS NOT NULL THEN
      SET NEW.rest_time_in = NULL;
    END IF;
    IF FIND_IN_SET('rest_time_out', OLD.cleared_fields) > 0
       AND OLD.rest_time_out IS NULL AND NEW.rest_time_out IS NOT NULL THEN
      SET NEW.rest_time_out = NULL, NEW.rest_time_late = OLD.rest_time_late;
    END IF;

    IF FIND_IN_SET('subuh_time_in', OLD.cleared_fields) > 0
       AND OLD.subuh_time_in IS NULL AND NEW.subuh_time_in IS NOT NULL THEN
      SET NEW.subuh_time_in = NULL, NEW.subuh_time_out = OLD.subuh_time_out, NEW.subuh_time_late = OLD.subuh_time_late;
    END IF;
    IF FIND_IN_SET('dzuhur_time_in', OLD.cleared_fields) > 0
       AND OLD.dzuhur_time_in IS NULL AND NEW.dzuhur_time_in IS NOT NULL THEN
      SET NEW.dzuhur_time_in = NULL, NEW.dzuhur_time_out = OLD.dzuhur_time_out, NEW.dzuhur_time_late = OLD.dzuhur_time_late;
    END IF;
    IF FIND_IN_SET('ashar_time_in', OLD.cleared_fields) > 0
       AND OLD.ashar_time_in IS NULL AND NEW.ashar_time_in IS NOT NULL THEN
      SET NEW.ashar_time_in = NULL, NEW.ashar_time_out = OLD.ashar_time_out, NEW.ashar_time_late = OLD.ashar_time_late;
    END IF;
    IF FIND_IN_SET('maghrib_time_in', OLD.cleared_fields) > 0
       AND OLD.maghrib_time_in IS NULL AND NEW.maghrib_time_in IS NOT NULL THEN
      SET NEW.maghrib_time_in = NULL, NEW.maghrib_time_out = OLD.maghrib_time_out, NEW.maghrib_time_late = OLD.maghrib_time_late;
    END IF;
    IF FIND_IN_SET('isha_time_in', OLD.cleared_fields) > 0
       AND OLD.isha_time_in IS NULL AND NEW.isha_time_in IS NOT NULL THEN
      SET NEW.isha_time_in = NULL, NEW.isha_time_out = OLD.isha_time_out, NEW.isha_time_late = OLD.isha_time_late;
    END IF;
    IF FIND_IN_SET('friday_time_in', OLD.cleared_fields) > 0
       AND OLD.friday_time_in IS NULL AND NEW.friday_time_in IS NOT NULL THEN
      SET NEW.friday_time_in = NULL, NEW.friday_time_out = OLD.friday_time_out, NEW.friday_time_late = OLD.friday_time_late;
    END IF;

    -- v4: lapis pertahanan terakhir -- kolom yang SUDAH TERISI (bukan
    -- cleared) pada baris manual tidak boleh berubah nilai lewat sync,
    -- apa pun kode/jalur penulisnya. Pasang balik nilai lama kalau berubah.
    IF OLD.input_by = 'manual' AND FIND_IN_SET('entry_time', OLD.cleared_fields) = 0
       AND OLD.entry_time IS NOT NULL AND NOT (NEW.entry_time <=> OLD.entry_time) THEN
      SET NEW.entry_time = OLD.entry_time, NEW.entry_time_late = OLD.entry_time_late;
    END IF;
    IF OLD.input_by = 'manual' AND FIND_IN_SET('out_time', OLD.cleared_fields) = 0
       AND OLD.out_time IS NOT NULL AND NOT (NEW.out_time <=> OLD.out_time) THEN
      SET NEW.out_time = OLD.out_time;
    END IF;
    IF OLD.input_by = 'manual' AND FIND_IN_SET('rest_time_in', OLD.cleared_fields) = 0
       AND OLD.rest_time_in IS NOT NULL AND NOT (NEW.rest_time_in <=> OLD.rest_time_in) THEN
      SET NEW.rest_time_in = OLD.rest_time_in;
    END IF;
    IF OLD.input_by = 'manual' AND FIND_IN_SET('rest_time_out', OLD.cleared_fields) = 0
       AND OLD.rest_time_out IS NOT NULL AND NOT (NEW.rest_time_out <=> OLD.rest_time_out) THEN
      SET NEW.rest_time_out = OLD.rest_time_out, NEW.rest_time_late = OLD.rest_time_late;
    END IF;

    IF OLD.input_by = 'manual' AND FIND_IN_SET('subuh_time_in', OLD.cleared_fields) = 0
       AND OLD.subuh_time_in IS NOT NULL AND NOT (NEW.subuh_time_in <=> OLD.subuh_time_in) THEN
      SET NEW.subuh_time_in = OLD.subuh_time_in, NEW.subuh_time_out = OLD.subuh_time_out, NEW.subuh_time_late = OLD.subuh_time_late;
    END IF;
    IF OLD.input_by = 'manual' AND FIND_IN_SET('dzuhur_time_in', OLD.cleared_fields) = 0
       AND OLD.dzuhur_time_in IS NOT NULL AND NOT (NEW.dzuhur_time_in <=> OLD.dzuhur_time_in) THEN
      SET NEW.dzuhur_time_in = OLD.dzuhur_time_in, NEW.dzuhur_time_out = OLD.dzuhur_time_out, NEW.dzuhur_time_late = OLD.dzuhur_time_late;
    END IF;
    IF OLD.input_by = 'manual' AND FIND_IN_SET('ashar_time_in', OLD.cleared_fields) = 0
       AND OLD.ashar_time_in IS NOT NULL AND NOT (NEW.ashar_time_in <=> OLD.ashar_time_in) THEN
      SET NEW.ashar_time_in = OLD.ashar_time_in, NEW.ashar_time_out = OLD.ashar_time_out, NEW.ashar_time_late = OLD.ashar_time_late;
    END IF;
    IF OLD.input_by = 'manual' AND FIND_IN_SET('maghrib_time_in', OLD.cleared_fields) = 0
       AND OLD.maghrib_time_in IS NOT NULL AND NOT (NEW.maghrib_time_in <=> OLD.maghrib_time_in) THEN
      SET NEW.maghrib_time_in = OLD.maghrib_time_in, NEW.maghrib_time_out = OLD.maghrib_time_out, NEW.maghrib_time_late = OLD.maghrib_time_late;
    END IF;
    IF OLD.input_by = 'manual' AND FIND_IN_SET('isha_time_in', OLD.cleared_fields) = 0
       AND OLD.isha_time_in IS NOT NULL AND NOT (NEW.isha_time_in <=> OLD.isha_time_in) THEN
      SET NEW.isha_time_in = OLD.isha_time_in, NEW.isha_time_out = OLD.isha_time_out, NEW.isha_time_late = OLD.isha_time_late;
    END IF;
    IF OLD.input_by = 'manual' AND FIND_IN_SET('friday_time_in', OLD.cleared_fields) = 0
       AND OLD.friday_time_in IS NOT NULL AND NOT (NEW.friday_time_in <=> OLD.friday_time_in) THEN
      SET NEW.friday_time_in = OLD.friday_time_in, NEW.friday_time_out = OLD.friday_time_out, NEW.friday_time_late = OLD.friday_time_late;
    END IF;
  END IF;
END$$

DELIMITER ;

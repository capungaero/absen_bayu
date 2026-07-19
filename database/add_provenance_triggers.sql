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
  ELSEIF OLD.input_by = 'manual' AND NOT (NEW.input_by <=> 'manual')
         AND (@absen_sync_ctx <> 'admin_reset') THEN
    -- Ratchet: sync tidak pernah bisa menurunkan manual -> system.
    SET NEW.input_by = 'manual';
  END IF;
END$$

DELIMITER ;

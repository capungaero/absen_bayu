-- ============================================================================
-- Backfill: set presence_get_paid = 100 untuk semua row sakit historis.
--
-- Background: per perubahan aturan (commit 382d47a), presence_type='sakit'
-- selalu potongan 0%. Calculation layer di Presence_model::get_fine sudah
-- skip sakit, jadi payroll calc benar tanpa migrasi ini. Migrasi ini hanya
-- untuk konsistensi data — supaya kolom presence_get_paid mencerminkan
-- aturan baru dan tidak menyesatkan saat audit row sakit.
--
-- Yang TIDAK berubah:
--   * payroll_deduction yang sudah diinsert dari periode lama (data
--     historical payroll tidak di-recalculate).
--   * Field lain di row presence (entry_time, out_time, dll.).
--   * Row presence non-sakit (izin/cuti/normal).
--
-- Catatan distribusi sebelum migrasi (831 total sakit, 822 perlu backfill):
--   presence_get_paid   count
--   -100                  10
--   0                    114
--   13                     2
--   25                   126
--   50                   483
--   62                     4
--   63                     1
--   75                    80
--   87                     2
--   100                    9
--
-- Migrasi idempotent: aman dijalankan ulang.
-- ============================================================================

UPDATE presence
SET presence_get_paid = 100
WHERE presence_type = 'sakit'
  AND (presence_get_paid IS NULL OR presence_get_paid <> 100);

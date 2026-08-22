-- =============================================================================
-- Proteksi pengajuan lembur ganda (21 Agu 2026).
--
-- Masalah: pengajuan lembur bisa dobel utk (karyawan, tanggal) yang sama --
-- mis. pengajuan manual di aplikasi absensi DAN auto-sync dari lacak masuk
-- dua-duanya. Semua jalur aplikasi (M.php, Api.php, hr/Overtime::insert,
-- gps_presence_sync.php) sudah punya cek duplikat masing-masing, tapi:
--   1. check-then-insert tanpa kunci DB = race window (dua request bersamaan
--      dua-duanya lolos cek, dua-duanya insert);
--   2. aturan tiap jalur sempat beda-beda (ada yang blokir status deny juga).
--
-- Solusi level DB (backstop terakhir, jalur apa pun):
-- kolom generated `active_flag` = 1 untuk baris AKTIF (pending/approve,
-- belum soft-delete), = NULL untuk baris non-aktif (deny/cancel/deleted).
-- UNIQUE (user_id, overtime_date, active_flag) berarti:
--   - maksimal SATU pengajuan aktif per karyawan per tanggal;
--   - baris deny/cancel boleh banyak (NULL tidak dianggap sama oleh unique
--     index MariaDB), jadi pengajuan ulang setelah ditolak tetap bisa.
-- (Catatan: referensi kolom `id` auto_increment DILARANG di generated column
-- MariaDB -- error 1901 -- makanya pakai pola NULL, bukan fallback ke id.)
--
-- Konvensi seragam semua jalur (disamakan sekalian di kode):
-- duplikat = sudah ada baris status pending/approve utk (user, tanggal).
-- deny/cancel TIDAK menghalangi pengajuan baru.
--
-- PRASYARAT: bereskan duplikat aktif existing dulu (kalau masih ada, ADD
-- UNIQUE gagal). Ditemukan 7 grup duplikat historis 2021-2022 (payroll
-- periode tsb sudah final) -- baris id terkecil dipertahankan, sisanya
-- di-cancel dgn jejak di reject_reason.
--
-- Cara pasang (VPS produksi):
--   mariadb absen_copy < database/add_overtime_unique_active.sql
-- =============================================================================

-- 1. Bereskan duplikat aktif existing: pertahankan id terkecil per grup.
UPDATE overtime o
JOIN (
  SELECT user_id, overtime_date, MIN(id) AS keep_id
  FROM overtime
  WHERE deleted_at IS NULL AND overtime_status IN ('pending','approve')
  GROUP BY user_id, overtime_date
  HAVING COUNT(*) > 1
) d ON d.user_id = o.user_id AND d.overtime_date = o.overtime_date
SET o.overtime_status = 'cancel',
    o.reject_reason = CONCAT(IFNULL(o.reject_reason, ''),
      ' [auto-cancel 2026-08-21: duplikat pengajuan, baris id ', d.keep_id, ' dipertahankan]'),
    o.updated_at = NOW()
WHERE o.id <> d.keep_id
  AND o.deleted_at IS NULL
  AND o.overtime_status IN ('pending','approve');

-- 2. Kolom generated + unique index.
ALTER TABLE overtime
  ADD COLUMN active_flag TINYINT AS (
    IF(overtime_status IN ('pending','approve') AND deleted_at IS NULL, 1, NULL)
  ) PERSISTENT,
  ADD UNIQUE KEY uniq_active_overtime (user_id, overtime_date, active_flag);

-- =============================================================================
-- Fase 2 provenance: kolom `cleared_fields` pada tabel `presence` (Jul 2026).
--
-- Masalah yang ditutup: field jam yang SENGAJA dikosongkan admin (mis. hapus
-- entry_time yang salah) tidak bisa dibedakan dari "belum pernah ada data",
-- sehingga gap-fill sync mengisinya kembali dari tap mesin.
--
-- Solusi: kolom `cleared_fields` = daftar koma nama kolom jam yang sengaja
-- dikosongkan (subset dari: entry_time, out_time, rest_time_in, rest_time_out).
-- Dirawat otomatis oleh trigger presence_provenance_bu v2 (file
-- add_provenance_triggers.sql):
--   - koneksi TANPA kartu sync mengosongkan kolom berisi -> nama kolom dicatat;
--   - kolom diisi lagi (tanpa kartu sync) -> nama kolom dihapus dari daftar;
--   - koneksi sync yang mencoba mengisi kolom ber-tanda cleared -> DIBATALKAN
--     oleh trigger (nilai dikembalikan NULL), kecuali @absen_sync_ctx='admin_reset';
--   - admin_reset juga mengosongkan daftar cleared_fields (kembali milik mesin).
-- Pembaca merge (presence_helper, Wa.php, absen_sync.py) juga skip kolom yang
-- terdaftar — trigger adalah lapis pengaman terakhirnya.
--
-- URUTAN DEPLOY:
--   1. Jalankan file ini (ALTER TABLE) — kolom harus ada dulu.
--   2. Jalankan ulang database/add_provenance_triggers.sql (trigger BU v2
--      mereferensikan kolom ini).
--   3. Deploy kode PHP/Python pembaca cleared_fields.
--
-- Cara pasang (SSH server produksi):
--   mysql --defaults-extra-file=$HOME/.absen.cnf tifx3722_newtiffa_timesheet \
--     < database/add_cleared_fields.sql
--   mysql --defaults-extra-file=$HOME/.absen.cnf tifx3722_newtiffa_timesheet \
--     < database/add_provenance_triggers.sql
-- =============================================================================

ALTER TABLE presence
  ADD COLUMN cleared_fields VARCHAR(191) NOT NULL DEFAULT ''
  COMMENT 'Daftar koma kolom jam yang sengaja dikosongkan manual; sync dilarang mengisi ulang'
  AFTER input_by_user_id;

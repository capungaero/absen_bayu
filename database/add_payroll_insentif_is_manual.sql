-- =============================================================================
-- payroll_insentif.is_manual (Jul 2026).
--
-- Bug: apply_auto_to_payroll() (PayrollSim.php) hitung ulang 5 komisi otomatis
-- (disiplin/transport/beras/soskes/sholat) dari aturan attendance dan
-- update_batch TANPA cek apakah row sudah di-edit manual admin lewat
-- save_insentif(). Lock Gaji generate() diam-diam menimpa edit manual
-- (contoh: Komisi Disiplin Kehadiran Ellisa Putri 150000 -> 100000 tertimpa
-- 21 detik setelah edit, saat generate payroll pertama kali).
--
-- Solusi: kolom is_manual menandai row yang nilainya sengaja diset admin
-- (beda dari nilai auto rule). apply_auto_to_payroll() dgn $skip_manual=true
-- (dipanggil dari Lock Gaji generate) skip row ber-tanda ini. Tombol
-- "Hitung Ulang Komisi Otomatis" (recalc_auto_insentif) TETAP force-overwrite
-- ($skip_manual=false) -- itu memang tujuannya, admin klik sadar.
--
-- Cara pasang:
--   mysql --defaults-extra-file=$HOME/.absen.cnf tifx3722_newtiffa_timesheet \
--     < database/add_payroll_insentif_is_manual.sql
-- =============================================================================

ALTER TABLE payroll_insentif
  ADD COLUMN is_manual TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'Nilai sengaja di-set/diubah admin (save_insentif/importer) -- apply_auto_to_payroll(skip_manual=true) tak boleh menimpa'
  AFTER insentif_amount;

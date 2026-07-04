# 00 — Peta Sistem & Aturan Sinkronisasi Engine

> Dokumen ini dan seluruh `docs/rules/*.md` adalah **spec normatif** perhitungan
> absensi/payroll Tiffany Houseware. Setiap aturan menunjuk "Source of truth"
> ke kode PHP produksi. Bila kode PHP berubah, dokumen INI dan semua engine
> turunan wajib diubah bersamaan (lihat checklist di bawah).

## Inventaris engine kalkulasi

| # | Engine | Lokasi | Peran | Status otoritas |
|---|--------|--------|-------|-----------------|
| 1 | **Presence_model (PHP)** | `application/models/Presence_model.php` (`get_fine` :643-1157, `get_insentif` :1159-1226, `get_deduction` :1228-1280) | Engine ASLI yang dipakai halaman payroll & Lock Gaji | **OTORITATIF** — semua engine lain harus cocok dengannya |
| 2 | PayrollSim (PHP) | `application/controllers/PayrollSim.php` (`_calc`, `_auto_commissions` :676-841) | Simulator + penulis 5 komisi otomatis ke `payroll_insentif` (dipanggil `apply_auto_to_payroll`) | Mirror; sumber tunggal aturan **eligibility 5 komisi otomatis** |
| 3 | calculator.js (Node) | `tools/payroll_sim/calculator.js` | Backend SPA simulator read-only (port 3011) | Mirror |
| 4 | absen_pipeline (Python) | `pipeline/absen_pipeline/engine/` | Engine kanonik pipeline AI (DB `absen_ai`) | Mirror — WAJIB lolos paritas rupiah-per-rupiah (lihat `tests/pipeline/`) |

Sejarah membuktikan mirror bisa drift dan menimbulkan bug gaji nyata
(kasus: agregasi hari sakit per-pengajuan, aturan potongan sakit di-flip,
pembagi salary_per_day). Karena itu:

## Checklist sinkronisasi (WAJIB saat mengubah aturan)

Setiap kali mengubah rumus/aturan di salah satu engine:

1. [ ] Ubah **Presence_model.php / PayrollSim.php** (otoritas) lebih dulu.
2. [ ] Perbarui dokumen `docs/rules/NN-*.md` terkait (rumus + contoh angka).
3. [ ] Perbarui `tools/payroll_sim/calculator.js` (jika aturan tersentuh).
4. [ ] Perbarui `pipeline/absen_pipeline/engine/*` (docstring harus mengutip
       section rules + `file:baris` PHP).
5. [ ] Tambah/perbarui kasus golden di `tests/pipeline/golden/` + jalankan
       `pytest tests/pipeline/` sampai hijau.
6. [ ] Deploy PHP ke server (ingat: **tidak ada CI/CD — commit ≠ live**;
       selalu `diff` lokal vs server dulu, lihat memory `payroll-sim-deploy-drift`).

## Konvensi lintas-dokumen

- **Periode payroll**: tanggal 26 bulan sebelumnya s.d. 25 bulan berjalan
  (`START_PAYROLL_DATE=26`, `END_PAYROLL_DATE=25`,
  `application/config/constants.php:92-93`). "Periode Juni 2026" =
  26 Mei–25 Jun 2026. Detail: [01-periode-jadwal.md](01-periode-jadwal.md).
- **Keterlambatan selalu FLOOR ke menit** (detik dipotong, tidak dibulatkan):
  `late_minutes()` di `application/helpers/late_helper.php:11-24`.
  07:50:59 vs batas 07:50:00 = **0 menit** telat.
- **Cabang**: SDR = branch_id 1, GBR = branch_id 2.
- **Kode shift no-schedule**: `'-'`, `'NO-SC'`, `'NO SCHEDULE'`
  (`is_no_schedule_shift()`, `application/helpers/schedule_helper.php:35`).
- **Pembulatan rupiah**: total denda `round()` di akhir
  (`Presence_model.php:1149`); item per-hari umumnya `round()` per item;
  potongan pulang-awal pakai `floor()` (lihat 03-denda).
- Dua sistem opt-in per karyawan (kolom di `branch`, dibaca via join karyawan):
  `is_fine_system` (ikut sistem denda) dan `is_pray_system` (ikut presensi
  sholat). Karyawan di luar sistem = tidak kena denda terkait / tidak dihitung
  sholatnya.

## Daftar dokumen

| Dok | Isi |
|-----|-----|
| [01-periode-jadwal.md](01-periode-jadwal.md) | Periode 26–25, jadwal kerja, NO-SC, hitung hari |
| [02-absensi-dat.md](02-absensi-dat.md) | Format .dat, parsing, klasifikasi tap, resolusi finger→karyawan |
| [03-denda.md](03-denda.md) | Denda telat masuk/istirahat/sholat, setengah hari, pulang awal |
| [04-potongan-izin-alfa.md](04-potongan-izin-alfa.md) | Potongan izin/sakit/cuti, alfa, denda ganda weekend/tanggal khusus |
| [05-komisi.md](05-komisi.md) | 5 komisi otomatis + insentif master + override manual |
| [06-sholat.md](06-sholat.md) | Window sholat, denda sholat, syarat Komisi Sholat |
| [07-bpjs.md](07-bpjs.md) | BPJS kantor/mandiri, Komisi Soskes, potongan bpjs_work/together |
| [08-payroll-aggregation.md](08-payroll-aggregation.md) | THP, salary_debt, lock gaji, tabel payroll_detail |

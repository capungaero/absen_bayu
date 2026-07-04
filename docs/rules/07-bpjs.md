# 07 — BPJS & Komisi Soskes

## Tabel

- `bpjs_config` (global): a.l. `mandiri_insentif` = nominal Komisi Soskes
  (default 101.500 bila kosong).
- `bpjs_payment` per (user_id, month, year): `pay_mode` = `kantor` | `mandiri`,
  `status` = `approved` | pending, bukti bayar (upload).

Source of truth: `PayrollSim.php:756-788`.

## Aturan Komisi Soskes

| Kondisi record bpjs_payment periode ini | Hasil |
|---|---|
| `pay_mode='kantor'` | TIDAK dapat komisi (BPJS dibayar kantor → justru jadi potongan gaji) |
| `pay_mode='mandiri'` + `status='approved'` + masa kerja ≥ 1 th | **DAPAT** `mandiri_insentif` |
| `mandiri` belum approved | Tidak dapat (menunggu ACC admin) |
| Tidak ada record | Tidak dapat (diasumsikan mandiri tanpa bukti) |

## Potongan BPJS di payroll (bpjs_work & bpjs_together)

Dua kolom potongan pada `payroll_detail`:
- `salary_out_work` ("BPJS Ketenagakerjaan") ← input form `work[user_id]`
- `salary_out_together` ("BPJS Bersama") ← input form `together[user_id]`
  (nominal pertama yang terisi juga disimpan sebagai
  `payroll.out_together_nominal`)

Nilai-nilai ini **input manual HR di halaman payroll saat Lock Gaji**
(di-parse `format_angka()`), BUKAN hasil rumus.
Source of truth: `hr/Payroll.php:259-273`.
Pipeline membacanya dari `payroll_detail` (periode terkunci) atau menerima
input eksplisit — tidak menghitung sendiri.

### Contoh
Karyawan masa kerja 1 th 3 bln, upload bukti bayar BPJS mandiri Juni dan
di-ACC admin → Komisi Soskes 101.500 masuk `payroll_insentif` saat
"Hitung Komisi Otomatis". Temannya yang BPJS-nya dibayar kantor → komisi 0,
dan dipotong `salary_out_work` sesuai input HR.

## Status modul

Menu BPJS (config + list pembayaran) sudah ada; keterkaitan otomatis penuh
ke payroll masih parsial (memory `bpjs-module`) — upload bukti via PWA
tersedia (endpoint `Api::bpjs`, `Api::submit_bpjs`).

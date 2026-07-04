# 01 — Periode Payroll & Jadwal Kerja

## Periode payroll (26 → 25)

- Konstanta: `START_PAYROLL_DATE = 26`, `END_PAYROLL_DATE = 25`
  — Source of truth: `application/config/constants.php:92-93`.
- "Periode bulan M tahun Y" = **26 (M−1) s.d. 25 M**.
  Fungsi kanonik: `getRangeWorkDate($month, $year)`
  — Source of truth: `application/helpers/monthname_helper.php:257-294`.
  - `from` dihitung dari `strtotime("Y-M-10 -1 months")` lalu diformat ke
    tanggal 26 — trik "-1 months dari tanggal 10" menghindari bug lompatan
    bulan PHP (31 Jan −1 bulan ≠ Feb).
  - `list` = daftar tanggal `Y-m-d` inklusif from, **eksklusif to** di
    `get_daterange_list()`? TIDAK — `DatePeriod` PHP eksklusif tanggal akhir,
    NAMUN pemanggil menganggap range penuh; verifikasi: `total_day` periode
    Juni 2026 (26 Mei–25 Jun) = 31 hari termasuk 25 Jun.
    **[VERIFIKASI di Fase 4: hitung persis `count(list)` vs data produksi]**
- Kebalikan (tanggal → periode): `payroll_period_of_date($date)`
  — `application/helpers/generic_helper.php:5`.

### Contoh
- Periode **Juni 2026** = 2026-05-26 … 2026-06-25.
- Periode **Januari 2026** = 2025-12-26 … 2026-01-25 (lintas tahun).

## Dua macam "jumlah hari" (JANGAN tertukar)

| Variabel | Definisi | Dipakai untuk |
|----------|----------|---------------|
| `$total_day` | Jumlah hari kalender periode 26–25 (30/31, Feb: 28-31) | `max_for_generate` (pembagi gaji harian di `Payroll::generate`) |
| `$totalDayInMonth` | `cal_days_in_month(bulan, tahun)` = hari kalender bulan M (28–31) | `salaryPerDayForAlpha`, `salary_per_day` (denda ½ hari & potongan izin), tarif pulang awal |

Source of truth: `application/models/Presence_model.php:655-666, 713-714`.
Catatan sejarah: sebelum 3 Jul 2026 `salary_per_day` dibagi `$total_day`;
sekarang `$totalDayInMonth` (commit 63ce6cd) supaya konsisten dengan alfa
dan pulang awal.

## Jadwal kerja per karyawan per tanggal

- Tabel: `users_shift_additional`
  (`user_id`, `additional_date`, `shift_id`, `additional_type`).
- `additional_type`: `'work'` (hari kerja) atau `'free'` (libur/OFF).
- Join ke tabel `shift` memberi jam & tarif denda per shift (lihat 03-denda).
- Diambil via `get_additional_attendance()`
  — Source of truth: `Presence_model.php:646` (pemanggilan) dan definisinya
  di file yang sama.

### Shift NO-SC (no schedule)

- Kode shift `'-'`, `'NO-SC'`, `'NO SCHEDULE'` = terjadwal "tanpa jadwal":
  bukan hari kerja efektif, bukan pula OFF.
  Source of truth: `application/helpers/schedule_helper.php:35` +
  `PayrollSim.php:$NO_SC` (baris 8).
- Efek (Source: `Presence_model.php:668-679, 1100-1103`):
  - dihitung `$strip++` → `salary_basic_out_off_work = salary_per_day × strip`
    dipotong di payroll (lihat 08).
  - TIDAK dihitung alfa meski tidak hadir.
  - Tap/absen pada hari NO-SC TIDAK menambah hadir, TIDAK kena denda,
    TIDAK menambah rekap sholat (PayrollSim `_no_sc` guard di semua loop).
  - Menggugurkan Komisi Disiplin (syarat "Tidak ada NO-SC", lihat 05-komisi).

### Hitungan turunan (per periode, per karyawan)

Source of truth: `Presence_model.php:652-679, 717-722, 1146`.

| Nilai | Rumus |
|-------|-------|
| `total_work` | jumlah baris jadwal `additional_type='work'` (termasuk NO-SC) |
| `total_work_without_strip` (= `presence.max`) | work yang BUKAN NO-SC |
| `strip` | jumlah hari NO-SC |
| `max_for_generate` | `$total_day` (hari kalender periode) |
| `off` | jadwal `free` + tanggal periode yang tak punya baris jadwal sama sekali (`$daterange_list` sisa) |

### Contoh
Karyawan A, periode Juni 2026 (31 hari): jadwal berisi 26 hari `work`
(2 di antaranya NO-SC) + 4 hari `free`, 1 tanggal tanpa baris jadwal.
→ `total_work=26`, `max=24`, `strip=2`, `off=4+1=5`, `max_for_generate=31`.

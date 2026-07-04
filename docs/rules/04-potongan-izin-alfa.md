# 04 — Potongan Izin/Sakit/Cuti, Alfa, dan Denda Ganda

## Potongan izin/sakit/cuti (leave fine)

Berlaku bila `is_fine_system='1'` dan `presence_type != 'normal'`
(nilai: `izin` / `sakit` / `cuti`). Persentase dibayar tersimpan per hari di
`presence.presence_get_paid` (di-set saat approve pengajuan berdasarkan
`GetPotonganIzin(status_work)`: permanent/contract = dibayar 50%, lainnya 75% —
kecuali approver override manual).

```
persen_potong = 100 − presence_get_paid
potongan/hari = round(persen_potong% × salary_per_day)
              × 2 bila hari Sabtu/Minggu
salary_per_day = gaji / hari_kalender_bulan (totalDayInMonth)
```

Source of truth: `Presence_model.php:1045-1071`.

⚠️ **Status FINAL aturan sakit** (2 Jul 2026, keputusan user): izin `sakit`
yang disetujui **TIDAK dikecualikan** — kena potongan sama seperti izin/cuti.
Jangan "memperbaiki" ini tanpa perintah eksplisit
(memory: `sakit-leave-full-pay-rule`).

### Contoh
Gaji 1.900.000, Juni (30 hari), sakit disetujui 1 hari (Rabu), kontrak
(dibayar 50%): salary_per_day = 63.333 → potongan = round(50% × 63.333) =
**31.667**. Hari yang sama jatuh Sabtu → **63.334** (×2).

## Alfa (tidak hadir pada hari kerja terjadwal)

Definisi absen-kerja (Source: `Presence_model.php:1097-1144`):
`additional_type='work'` DAN tidak ada baris presence tanggal itu DAN
tanggal ≤ hari-ini DAN bukan NO-SC.

Tarif dasar:

```
salaryPerDayForAlpha = round(gaji / hari_kalender_bulan)
```

| Kasus | Potongan | Akumulator |
|---|---|---|
| Alfa hari kerja biasa (Sen–Jum, bukan tanggal khusus) | 1 × salaryPerDayForAlpha | `amount_in_weekdays` |
| Alfa **Sabtu/Minggu** | **2 ×** salaryPerDayForAlpha | `amount_in_weekend` |
| Alfa **tanggal khusus** (tabel `double_deduction_date` per cabang, is_active) | **2 ×** salaryPerDayForAlpha | `amount_in_special_double` — ⚠️ nilainya JUGA sudah termasuk dalam `amount_in_weekend` (gabungan); pemisah tampilan = weekend − special_double. Jangan dijumlah dua kali |

`get_dayname()` menentukan Sabtu/Minggu (`monthname_helper.php:144`).

### Contoh
Gaji 2.400.000, Juni (30 hari) → salaryPerDayForAlpha = 80.000.
Alfa Selasa = 80.000; alfa Minggu = 160.000; alfa tanggal khusus
(mis. H+1 lebaran, terdaftar di double_deduction_date) = 160.000.

## Interaksi dengan payment_receive (agregasi)

Alfa weekday juga mengurangi gaji dasar di `Payroll::generate()`:
`tmpReceive = salary − alpha_weekdays_amount`, lalu di-floor ke
`salary_minimum` bila lebih kecil (lihat
[08-payroll-aggregation.md](08-payroll-aggregation.md)).
**[VERIFIKASI Fase 4: pastikan alfa weekday tidak terhitung dobel —
sekali di payment_receive, sekali di fine — cocokkan dgn payroll_detail:
kolom `salary_basic_out_alfa_weekdays` vs komponen `row['fine']`.]**

## Hari OFF & NO-SC

- `free` (OFF) dan NO-SC tidak pernah kena alfa/potongan harian
  (NO-SC dipotong lewat mekanisme `strip`, lihat 01 & 08).
- Tanggal periode tanpa baris jadwal sama sekali dihitung `off`.

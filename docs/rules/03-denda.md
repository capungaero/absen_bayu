# 03 — Denda (Telat Masuk, Istirahat, Sholat, ½ Hari, Pulang Awal)

Semua denda di dokumen ini **hanya berlaku bila `is_fine_system = '1'`**
pada cabang karyawan dan `presence_type = 'normal'`
(kecuali denda sholat yang gate-nya `is_pray_system`, lihat 06).
Source of truth utama: `application/models/Presence_model.php:934-1095`.

## Rumus denda bertingkat (dipakai telat masuk, istirahat, sholat)

Parameter (per shift utk masuk/istirahat — kolom tabel `shift`; per branch
utk sholat — lihat 06):

| Parameter | Masuk | Istirahat |
|---|---|---|
| tarif dasar | `late_amount_start` | `late_amount_rest` |
| tarif kelipatan | `late_amount_multiple_start` | `late_amount_multiple_rest` |
| ambang kelipatan (menit) | `late_multiple_count_start` | `late_multiple_count_rest` |
| plafon (maks) | `late_amount_max_start` | `late_amount_max_rest` |

Algoritme persis (Source: `Presence_model.php:939-962` utk masuk,
`:988-1011` utk istirahat):

```
denda = tarif_dasar                                  # begitu telat > 0 menit
if telat > ambang:
    sisa   = telat − ambang
    denda += floor(sisa / ambang) × tarif_kelipatan
    if sisa % ambang > 0:                            # pecahan ambang dihitung penuh
        denda += tarif_kelipatan
denda = min(denda, plafon)
```

### Contoh 1 (telat masuk)
Shift: dasar 5.000, kelipatan 1.000, ambang 5 menit, plafon 20.000.
Telat **12 menit**: dasar 5.000; sisa 7; floor(7/5)=1 → +1.000; 7%5=2>0 →
+1.000. **Total 7.000**.

### Contoh 2 (kena plafon)
Sama, telat **95 menit**: 5.000 + floor(90/5)=18 → +18.000 = 23.000; 90%5=0.
23.000 > plafon → **20.000**.

## Denda istirahat "tak tercatat"

Bila `rest_time_in` terisi tapi `rest_time_out` kosong → denda **plafon
istirahat penuh** (`late_amount_max_rest`).
Source: `Presence_model.php:1019-1027`. (Konstanta default sistem
`REST_LATE_FIX_RATE = 20000`, `constants.php:94`, dipakai bila kolom shift
kosong pada konfigurasi lama.)

## Denda setengah hari (absen tak lengkap)

Hanya salah satu dari `entry_time`/`out_time` terisi →

```
denda_setengah_hari = round(salary_per_day / 2)
salary_per_day      = gaji_pokok / hari_kalender_bulan   (totalDayInMonth)
```

Source: `Presence_model.php:713-714, 970-978`.

### Contoh
Gaji 2.100.000, Juni (30 hari): salary_per_day = 70.000 → denda **35.000**
per kejadian.

## Potongan pulang lebih awal (early leave)

Berlaku bila `is_early_leave=1` pada presence dan menit kekurangan > 0;
menit kekurangan diakumulasi SE-PERIODE lalu dikonversi rupiah SEKALI:

```
short/hari  = max(0, expected_net − actual_net)          # menit
expected    = (shift.end − shift.start) − rest_time_range
actual      = (out − entry) − (rest_out − rest_in jika lengkap)
tarif/jam   = gaji / hari_kalender_bulan / 10
potongan    = floor(tarif_per_jam × total_short_menit / 60)
```

Source of truth: `application/helpers/presence_helper.php:21-133`
(`presence_net_work_minutes`, `presence_expected_net_minutes`,
`presence_early_leave_short_minutes`, `presence_hourly_rate`,
`presence_early_leave_deduction_amount`); pemakaian di
`Presence_model.php:1034-1043, 1075-1095` (hanya dihitung bila
`is_fine_system='1'`; alokasi kembali per-hari untuk tampilan).

### Contoh
Gaji 2.100.000, Juni (30 hari), total kekurangan 90 menit:
tarif/jam = 2.100.000/30/10 = 7.000 → potongan = floor(7.000 × 1,5) =
**10.500** (sekali, bukan per hari).

Catatan: "Izin Pulang Lebih Awal" yang DISETUJUI (`is_early_leave` dengan
status bukan deny) tetap dihitung hadir (warna biru di UI), dan baris
`payroll_deduction` ber-note `'Potongan Izin Pulang Lebih Awal (auto)'`
DIKECUALIKAN dari deduction manual agar tidak dobel
(Source: `Presence_model.php:1249-1251`).

## Denda telat pulang?

**TIDAK ADA.** Pulang melewati jam jadwal bukan pelanggaran dan sejak
Jul 2026 tidak lagi ditampilkan di PWA (commit 6305f68).

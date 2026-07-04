# 06 — Presensi Sholat

Gate: hanya karyawan cabang dengan `is_pray_system = '1'`.

## Jenis & kolom

6 waktu: `subuh, dzuhur, ashar, maghrib, isha, friday` — masing-masing punya
kolom `\<key>_time_in`, `\<key>_time_out`, `\<key>_time_late` di `presence`.
`friday` = sholat Jumat (menggantikan dzuhur di hari Jumat).

## Window tap sholat (sync)

- Window per cabang **di-set manual** (bukan dari tabel shift); hari Jumat
  memakai window `friday_*`. Import sholat via `sync_pray_cloud` mencocokkan
  tap .dat ke window ini.
- ⚠️ Gotcha (memory `sholat-sync-gotchas`): import sholat TIDAK mem-bump
  `updated_at` presence; duplikat `employee_code` membuat record phantom.

## Denda sholat

Rumus bertingkat yang sama dengan telat masuk (lihat 03-denda) dengan
parameter dari tabel `branch`:

| Parameter | Kolom branch | Default konstanta |
|---|---|---|
| tarif dasar | `pray_late_start_rate` | `PRAY_START_RATE` = 5.000 |
| tarif kelipatan | `pray_late_multiple_rate` | `PRAY_MULTIPLE_RATE` = 1.000 |
| ambang (menit) | `pray_late_multiple_count` | `PRAY_MULTIPLE_COUNT` = 1 |
| plafon | `pray_late_fix_rate` | `PRAY_LATE_FIX_RATE` = 20.000 |

Source of truth: `Presence_model.php:833-899`; konstanta
`constants.php:95-98`.

Kasus khusus: `in` terisi tapi `out` kosong (sholat tak lengkap) → denda
**plafon penuh** (`pray_late_fix_rate`), dicatat `half=true`.

### Contoh
Branch SDR (5.000/1.000/1 menit/plafon 20.000), telat isya **4 menit**:
5.000 + floor(3/1)×1.000 = 8.000; 3%1=0 → **8.000**.
Telat 20 menit → 5.000+19.000 = 24.000 → plafon **20.000**.

## Rekap & syarat Komisi Sholat

- Hitungan record per jenis = jumlah hari `\<key>_time_in` terisi, **kecuali
  hari berjadwal NO-SC** (di-skip — Source: `PayrollSim.php:698-707`).
- Eligibility komisi (lihat 05): ≥2 jenis dengan ≥20 record;
  **Dzuhur+Jumat dijumlahkan jadi satu jenis** (`dzuhur_eff`)
  (Source: `PayrollSim.php:708-722`).
- `on_time` = out terisi & telat 0; selain itu masuk hitungan `late`
  (Source: `Presence_model.php:883-897`).

### Contoh
Record sebulan: Subuh 22, Dzuhur 15, Jumat 4, Ashar 19, Maghrib 25, Isya 18.
`dzuhur_eff = 15+4 = 19` (<20). Qualified: Subuh (22), Maghrib (25) = 2 jenis
→ **dapat** Komisi Sholat.

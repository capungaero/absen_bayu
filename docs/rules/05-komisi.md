# 05 — Komisi / Insentif

## Arsitektur nilai insentif

Semua insentif per karyawan per periode diselesaikan oleh
`Presence_model::get_insentif()` (Source: `Presence_model.php:1159-1226`)
dengan **urutan prioritas**:

1. Baris `payroll_insentif` (user_id, insentif_id, insentif_month/year) —
   MENANG bila ada. Diisi oleh: input manual HR, Payroll Importer, ATAU
   penulis otomatis `PayrollSim::apply_auto_to_payroll()`.
2. Bila tidak ada baris: rumus master `insentif`:
   - `formula='per_presence'` → `hadir_penuh × nominal`
     (hadir penuh = entry & out terisi)
   - `formula='none'` → 0 (murni manual)
   - formula lain → `nominal` flat.

Master: tabel `insentif` (`branch_id`, `insentif_name`, `formula`, `nominal`,
`is_active='1'`).

## 5 Komisi Otomatis

Ditulis ke `payroll_insentif` oleh `apply_auto_to_payroll(branch, month, year)`
— dipanggil tombol "Hitung Komisi Otomatis" dan otomatis di awal Lock Gaji.
ID master di-hardcode (Source: `PayrollSim.php:11-17`):

| Komisi | id SDR | id GBR | Nominal |
|---|---|---|---|
| Komisi Disiplin Kehadiran | 12 | 18 | 100rb (<1 th) / 150rb (≥1 th masa kerja) |
| Komisi Transport | 28 | 27 | 100.000 |
| Komisi Beras | 5 | 24 | 170.000 |
| Komisi Soskes | 29 | 33 | `bpjs_config.mandiri_insentif` (default 101.500) |
| Komisi Sholat | 9 | 21 | 50.000 |

Aturan eligibility — Source of truth: `PayrollSim.php::_auto_commissions()`
(:676-841):

### Komisi Disiplin Kehadiran — SEMUA syarat harus lolos
1. Akumulasi denda telat (masuk + istirahat + sholat) **+ potongan pulang
   awal** ≤ Rp50.000.
2. Tidak ada alfa.
3. **Sakit**: TOTAL hari sakit approved dalam periode ≤ 2 hari DAN semua
   pengajuan bersurat (`leave_proof` terisi). ⚠️ Dihitung **agregat total
   hari**, bukan per pengajuan — mencegah lolos dengan memecah pengajuan
   (bug 3 Jul 2026, commit 177aa5d, memory `payroll-sim-sakit-aggregate-bug`).
4. Tidak ada izin (`leave_type='izin'` approved yang overlap periode).
5. Tidak ada hari NO-SC dalam jadwal periode.

### Komisi Transport
Hadir penuh (entry+out, di hari non-NO-SC) ≥ **25×** dalam periode.

### Komisi Beras
Status menikah: `users.ptkp` diawali `K/` (PTKP_STATUS di
`constants.php:104`).

### Komisi Soskes → lihat [07-bpjs.md](07-bpjs.md)
Masa kerja ≥ 1 tahun DAN `bpjs_payment` periode ini bermode `mandiri`
berstatus `approved`. Mode `kantor` atau tanpa record = TIDAK dapat.

### Komisi Sholat
≥ 2 jenis sholat dengan ≥ 20 record masing-masing dalam periode;
**Dzuhur + Jumat digabung** sebagai satu jenis "sholat siang";
hanya tap pada hari non-NO-SC; syarat tambahan `is_pray_system='1'`.

## Masa kerja

`masa_months` dihitung dari `users.join_date` s.d. akhir periode;
`masa_tahun = masa_months ≥ 12` menentukan nominal Disiplin & syarat Soskes.

### Contoh 1 (Disiplin gugur karena sakit agregat)
Karyawan 524 Juni 2026: 3 pengajuan sakit approved bersurat 1+1+2 hari =
total 4 hari > 2 → **tidak dapat** Komisi Disiplin (walau tiap pengajuan ≤2).

### Contoh 2 (Transport)
Hadir penuh 27× (2 hari lain hanya tap masuk) → 27 ≥ 25 → **dapat 100.000**.
Hadir penuh 24× → gugur.

## Catatan penulisan otomatis

`apply_auto_to_payroll` menulis/meng-update baris utk SEMUA insentif master
cabang (yang non-otomatis di-preserve dari nilai ada / dihitung rumus),
dan me-recalc SELURUH karyawan cabang — nominal karyawan lain bisa ikut
terkoreksi saat tombol ditekan.

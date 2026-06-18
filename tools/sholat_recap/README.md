# Rekap Absen Sholat (standalone, tanpa server)

Tool terpisah untuk membuat **laporan HTML absensi sholat** langsung dari file
`.dat` mesin sholat — **tidak menyentuh server/aplikasi produksi** saat dijalankan.

## Alur
1. **Tarik data** tap dari mesin sholat → kumpulkan file `.dat`.
2. **Bandingkan** dengan jadwal window sholat + toleransi (`config.json`).
3. **Laporan HTML**: hadir, telat, **tidak absen**, tidak lengkap — per karyawan & per sholat.

## Yang dibutuhkan
- **Python 3** (sudah cukup; hanya pakai stdlib, tanpa install apa pun).
- File **`.dat`** dari mesin sholat (format `finger_id⇥YYYY-MM-DD HH:MM:SS⇥...`).
- **`employees.csv`** (`finger_id,name,branch`) — pemetaan finger → nama & cabang.
- **`config.json`** — window jam sholat + toleransi (sudah diisi untuk RG & GBR).

## Cara pakai
```bash
# 1. (sekali saja) buat employees.csv dari server:
bash export_employees.sh
#    atau salin manual employees.sample.csv -> employees.csv lalu isi sendiri.

# 2. taruh file .dat mesin sholat ke folder, mis. ./dat/

# 3. buat laporan (default: ringkasan + harian-detail):
python recap.py --dat ./dat --employees employees.csv \
       --from 2026-06-01 --to 2026-06-30

# 4. buka di browser:
#    laporan_sholat.html         -> ringkasan + rekap per karyawan
#    laporan_sholat_harian.html  -> DETAIL HARIAN (per tanggal x karyawan x sholat)
```
Opsi `--from`/`--to` boleh dikosongkan (otomatis ikut rentang data).
Boleh beberapa sumber: `--dat ./dat fileA.dat fileB.dat`.

**Pilih jenis laporan** dengan `--mode`:
- `--mode both` (default): buat dua-duanya.
- `--mode summary`: hanya ringkasan (`--out`).
- `--mode daily`: hanya harian-detail (`--daily-out`).

**Laporan harian-detail** = satu seksi per tanggal (klik untuk buka), tabel
Nama × Subuh/Dzuhur/Ashar/Maghrib/Isya, tiap sel berisi **jam masuk–keluar**
+ tanda telat (`+Nm`); warna hijau=hadir, oranye=telat, abu=tdk lengkap,
`—`=tidak absen. Jumat otomatis menggantikan kolom Dzuhur. Ada kotak cari nama.

## Logika penilaian (memperbaiki bug sync server)
- **Pasangkan in/out per episode**: tap pertama dalam window = *masuk*; tap berikutnya
  (sebelum window sholat berikutnya) = *keluar* — **boleh melewati jam tutup window**,
  sehingga "selesai sholat di luar window" **tidak lagi hilang** (ini akar masalah di server).
- **Telat** = lama sholat melebihi `tolerance_minutes` (keluar > masuk + toleransi).
- **Tidak Lengkap** = ada *masuk* tanpa *keluar*.
- **Tidak Absen** = tidak ada tap pada window sholat (dihitung pada hari kerja = hari ber-≥1 tap).
- **Jumat**: window `friday` otomatis menggantikan `dzuhur`.
- `evaluate_prayers` di config menentukan sholat mana yang masuk rekapan utama
  (default `dzuhur, ashar, maghrib` — sholat di jam kerja). Subuh/Isya tetap tampil
  bila ada tap-nya.

## config.json
- `tolerance_minutes`: toleransi telat (menit).
- `evaluate_prayers`: sholat yang dihitung dalam ringkasan "tidak absen/telat".
- `branches.<KODE>.windows`: jam `[mulai, selesai]` tiap sholat per cabang.
- `default_branch`: dipakai bila cabang karyawan tak dikenal.
> Pastikan window sesuai pengaturan cabang di aplikasi. Cek/sesuaikan bila berubah.

## Catatan
- File `employees.csv` & `*.dat` berisi data karyawan → **jangan commit** (sudah di-gitignore).
- Mesin & finger_id berbasis `employee_code`. finger yang tak ada di `employees.csv`
  tetap tampil memakai angka finger-nya (ada peringatan di output).

# Resume Perbaikan: Keterlambatan Sholat Salah Hitung (27 Jul 2026)

## Laporan Awal
Admin lapor: beberapa karyawan tercatat "terlambat" sholat padahal jam
masuk/keluar sholatnya kelihatan wajar (contoh: PUTRI OKTAVIANI, cabang 1).

Kasus:
| Tanggal | Jenis | In | Out | Keterlambatan (salah) |
|---|---|---|---|---|
| 2026-07-07 | Dzuhur | 13:37:06 | 13:52:38 | 70 menit |
| 2026-07-20 | Isha | 20:44:09 | 20:59:12 | 61 menit |

Toleransi durasi sholat cabang 1 = 15 menit untuk kedua jenis. Durasi aktual
kedua kasus hanya ~15 menit — seharusnya keterlambatan 0.

## Akar Masalah
Ada 2 jalur yang menulis kolom `presence.<jenis>_time_late`:

1. **PHP** (`Presence.php` — form "Ubah Presensi" & sync .dat lewat web) dan
   `scripts/pray_recompute_june.php` — formula BENAR sejak awal:
   `late = (out - (in + toleransi))`, yaitu **kelebihan** durasi saja.
2. **Python** (`scripts/vps/absen_sync.py`, jalan di VPS Hermes, cron
   `Sync Absensi Siang` 12:30 & `Sync Absensi Sore` 15:00 tiap hari) —
   formula SALAH:
   ```python
   if dur > scans['max_min']:
       late = int(dur)          # pakai TOTAL durasi, bukan kelebihannya
   ```
   Begitu durasi sholat lewat toleransi sedikit saja, seluruh durasi sholat
   dihitung sebagai menit telat, bukan cuma bagian yang lewat toleransi.

## Fix
- `scripts/vps/absen_sync.py:985` diubah jadi
  `late = int(dur - scans['max_min'])`.
- Dideploy ke file yang benar-benar dipakai cron:
  `/home/santai/.hermes/scripts/absen_sync.py` (VPS Hermes,
  161.97.118.145), backup lama disimpan sebagai `.bak_<timestamp>`.
- Dikomit ke repo: commit `12ea7ca` di branch `pph21-nonpegawai`
  (`scripts/vps/absen_sync.py` + `scripts/pray_late_recompute.php` baru).

## Perbaikan Data Lama
Dibuat `scripts/pray_late_recompute.php` — recompute `_time_late` dari
`in`/`out` yang sudah tersimpan (tanpa ubah jam), skip periode payroll yang
sudah terkunci (data yang sudah digaji tidak disentuh). Dijalankan di prod:

- Diperiksa 2010 baris (Jan–Jul 2026)
- 9576 baris dilewati (periode payroll sudah terkunci — histori lama
  sengaja tidak diubah)
- 1 baris terkoreksi: isha 07-20 PUTRI OKTAVIANI 61 → 0 menit
- Kasus dzuhur 07-07 sudah 0 duluan (sempat dikoreksi manual via form)

## Verifikasi
- Replay manual kedua kasus pakai formula lama vs baru → lama hasilkan
  15 menit (cocok pola bug), baru hasilkan 0 (cocok data final).
- Sync live dijalankan ulang 2x pakai runtime asli
  (`/usr/local/lib/hermes-agent/venv/bin/python`) — 4/4 mesin sukses,
  exit code 0, tanpa error.
- Sample baris sholat baru (durasi di bawah toleransi) konsisten
  `late=0`.

## Catatan Insiden Kecil Saat Investigasi
Sempat coba jalankan script manual pakai python sistem (`/usr/bin/python3`
via SSH root) yang tidak lengkap dependency-nya (`pymysql`/`psutil`) —
ini BUKAN environment asli cron. Percobaan itu sempat nyangkutin lock file
`/tmp/absen_sync.lock` sehingga 1 siklus cron terjadwal (12:30) ter-skip.
Tidak ada data hilang — sync manual berikutnya (pakai venv yang benar,
`/usr/local/lib/hermes-agent/venv/`) langsung menutup periode yang sama.
Lock file sudah bersih, cron berikutnya jalan normal.

## Status
Selesai. Formula permanen terpasang di runtime produksi, data historis
non-locked sudah dikoreksi. Kasus di periode payroll yang sudah terkunci
(kalau ada laporan serupa dari bulan yang sudah digaji) perlu keputusan
terpisah — apakah perlu unlock payroll dulu.

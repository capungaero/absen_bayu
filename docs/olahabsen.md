# Olah Report Absen Bulanan

Tanggal catatan: 2026-05-20  
Tujuan: proses baku untuk membuat report absen per karyawan tanpa mengubah struktur program utama.

## Output

Generator:

- `tools/generate_absen_report.php`
- `tools/absen_report_xlsx.php`

Hasil default:

- Excel: `exports/report_absen_<tahun>_<bulan>_<scope>.xlsx`
- Link lokal: `http://127.0.0.1:8080/exports/report_absen_<tahun>_<bulan>_<scope>.xlsx`

Sheet Excel:

- `Ringkasan`: periode, cabang, total karyawan, total anomali, formula skor.
- `Ranking`: identitas, rekap bulanan, skor, ranking.
- `Rekap Harian`: matrix 1 baris per karyawan, kolom tanggal 01-31.
- `Detail Harian`: 1 baris per karyawan per tanggal.
- `Catatan Anomali`: error data, data kosong, data ganjil.
- `Legenda`: arti kode status.

## Cara Pakai

Semua cabang, Mei 2026:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tools\generate_absen_report.php --year=2026 --month=05 --as-of=2026-05-20
```

Cabang GBR/Gambir saja:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tools\generate_absen_report.php --year=2026 --month=05 --branch=GBR --as-of=2026-05-20
```

Output custom:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tools\generate_absen_report.php --year=2026 --month=05 --out=exports\report_absen_mei_custom.xlsx
```

Report berdasar daftar ID fingerprint manual:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tools\generate_absen_report.php --year=2026 --month=05 --employee-codes=337,64,24,466,368,379,356,540 --scope=gambir_referensi --as-of=2026-05-20
```

Catatan: jika satu `employee_code` aktif muncul di lebih dari satu user/cabang, generator memilih user aktif yang punya jumlah `users_shift_additional` terbanyak dalam periode.

Referensi manual cabang Gambir per 2026-05-20:

- `337` ANNISA ZAHRATUL JANNAH
- `64` FIRDAUS
- `24` MUHAMMAD ZIKRI LATIF
- `466` NOVAL RUSMADI PUTRA
- `368` RISKA PERMATA SARI
- `379` RONI SISKANDAR
- `356` YOLI WIRANTI
- `540` SHERLY AMANDA PUTRI

## Sumber Data

DB dibaca dari:

- `application/config/database.php`
- host `127.0.0.1`
- database `newtiffa_timesheet`

Tabel utama:

- `users`: identitas karyawan dan `employee_code`.
- `position`: posisi dan relasi cabang.
- `branch`: cabang.
- `subdivision`: divisi.
- `users_shift_cluster`: cluster shift default karyawan.
- `shift_cluster_rotation`: rotasi master shift dalam cluster.
- `users_shift_additional`: jadwal harian final, dipakai presensi.
- `shift`: master shift.
- `presence`: presensi approved, izin, cuti, sakit.
- `leave`: pengajuan izin/cuti/sakit.

## Urutan Baca Data

1. Ambil karyawan aktif dari `users.active = 1`.
2. Join `position`, `branch`, `subdivision`.
3. Ambil cluster terakhir dari `users_shift_cluster` memakai `MAX(id)` per user.
4. Ambil jadwal harian dari `users_shift_additional` dalam periode.
5. Jika ada duplikat jadwal user+tanggal, pakai `MAX(id)` sebagai jadwal final.
6. Join `shift` untuk kode shift, nama shift, jam mulai, jam selesai.
7. Ambil semua `presence` dalam periode.
8. Untuk status harian, pakai `presence_status = approved`.
9. Jika ada lebih dari 1 row approved user+tanggal, pilih row terakhir dan catat anomali.
10. Ambil `leave` yang overlap periode untuk catatan tambahan.

## Klasifikasi Harian

Kode di `Rekap Harian`:

- `H`: hadir lengkap.
- `T`: terlambat, memakai `presence.entry_time_late > 0`.
- `I`: izin approved.
- `C`: cuti approved.
- `S`: sakit approved.
- `A`: absen pada hari kerja terjadwal.
- `TL`: presensi tidak lengkap, contoh hanya masuk atau hanya pulang.
- `OFF`: jadwal libur.
- `TJ`: tidak ada jadwal harian.
- `PTJ`: ada presensi tetapi tidak ada jadwal harian.
- `F`: tanggal masa depan dari `--as-of`, tidak dihitung skor.

Aturan status:

- Tidak ada jadwal + tanggal sudah lewat = `TJ`.
- Tidak ada jadwal + ada presensi = `PTJ`.
- Jadwal `additional_type = free` = `OFF`.
- Jadwal work + tidak ada presence approved + tanggal sudah lewat = `A`.
- Presence `presence_type` selain `normal` = `I`, `C`, atau `S`.
- Presence normal + masuk dan pulang lengkap = `H` atau `T`.
- Presence normal + masuk/pulang tidak lengkap = `TL`.

## Skor Performance

Karyawan hanya diranking jika punya minimal 1 `hari_kerja` sampai `--as-of`.

Formula:

```text
score = 100
        - (absen * 5)
        - (tidak_lengkap * 3)
        - (terlambat * 2)
        - floor(menit_telat / 30)
```

Tie-break ranking:

1. skor lebih tinggi.
2. absen lebih sedikit.
3. tidak lengkap lebih sedikit.
4. menit telat lebih sedikit.
5. hadir lebih banyak.
6. nama karyawan.

`izin`, `cuti`, dan `sakit` tetap dihitung, tetapi tidak mengurangi skor. Ini supaya ranking menilai disiplin presensi, bukan menghukum izin approved.

## Catatan Anomali

Anomali yang dicatat:

- Jadwal harian tidak ada.
- Presence ada tanpa jadwal.
- Presence approved duplikat.
- Presence hanya pending/deny.
- Shift master hilang dari jadwal work.
- Masuk/pulang tidak lengkap.
- Istirahat tidak lengkap.
- Leave pending.
- Leave approve tidak cocok dengan `presence_type`.
- Leave approve tetapi row presence belum terbentuk.
- Karyawan tidak punya hari kerja sampai `--as-of`.

## Temuan Data Saat Ini

Periode Mei 2026 saat dicek:

- Karyawan aktif setelah cleanup master: 66.
- Cabang SDR (`TIFFANY HOUSEWARE SUDIRMAN`): 57 karyawan aktif, 49 punya jadwal, 1224 row jadwal.
- Cabang GBR (`TIFFANY HOUSEWARE GAMBIR`): 9 karyawan aktif, 5 punya jadwal, 125 row jadwal.
- Presence Mei 2026: 900 row, semua `normal/approved`.
- Leave overlap Mei 2026: tidak ada row terdeteksi pada pengecekan awal.

Catatan penting:

- Cabang master sudah dirapikan: `branch_id=1` untuk Sudirman, `branch_id=2` untuk Gambir.
- GBR punya 19 master shift di `shift`, tapi `shift_cluster_rotation` untuk cluster id `2` masih kosong.
- Karena itu report GBR masih perlu dicek jika jadwal harian belum lengkap untuk semua karyawan Gambir.

## Query Diagnostik Cepat

Cek branch:

```sql
SELECT id, branch_code, branch_name
FROM branch
WHERE branch_name LIKE '%gambir%'
   OR branch_code LIKE '%gambir%'
   OR branch_name LIKE '%GBR%'
   OR branch_code LIKE '%GBR%';
```

Cek jadwal harian periode:

```sql
SELECT b.branch_name, COUNT(DISTINCT u.id) AS active_users,
       COUNT(DISTINCT usa.user_id) AS scheduled_users,
       COUNT(usa.id) AS schedule_rows
FROM users u
JOIN position p ON p.id = u.position_id
JOIN branch b ON b.id = p.branch_id
LEFT JOIN users_shift_additional usa
       ON usa.user_id = u.id
      AND usa.additional_date BETWEEN '2026-05-01' AND '2026-05-31'
WHERE u.active = 1
GROUP BY b.branch_name;
```

Cek rotasi cluster:

```sql
SELECT sc.id AS cluster_id, b.branch_name, sc.cluster_code, sc.cluster_name,
       COUNT(scr.id) AS total_rotasi
FROM shift_cluster sc
JOIN branch b ON b.id = sc.branch_id
LEFT JOIN shift_cluster_rotation scr ON scr.shift_cluster_id = sc.id
GROUP BY sc.id, b.branch_name, sc.cluster_code, sc.cluster_name;
```

## Batasan

- Generator ini tidak mengubah tabel DB.
- Generator ini tidak menambah route, controller, model, atau menu aplikasi.
- Excel dihasilkan sebagai file statis di `exports`, sehingga bisa dibuka lewat URL lokal.
- Untuk bulan berjalan, tanggal setelah `--as-of` diberi kode `F` dan tidak dihitung sebagai absen.

## Auto Detect Absensi Lintas Mesin

Update 2026-05-20:

- Import/sync presensi tidak boleh memakai cabang mesin sebagai identitas karyawan.
- Patokan utama adalah `users.employee_code` dari mesin fingerprint.
- Jika `employee_code` duplikat, resolver memilih karyawan yang punya jadwal kerja (`users_shift_additional.additional_type = 'work'`) pada tanggal log.
- Jika tanggal log belum cukup membedakan, resolver memilih karyawan dengan jumlah jadwal kerja terbanyak pada periode import.
- Jika masih seri, resolver memilih urutan deterministik dari user aktif/id terkecil dan menulis ringkasan `Auto-detect ID duplikat` di pesan import.

Jalur yang sudah memakai resolver ini:

- `application/controllers/hr/Presence.php`: upload Excel presensi, sync cloud, preview Excel cloud, upload/sync presensi sholat.
- `application/controllers/Presence.php`: jalur lama dengan pola import yang sama.
- `application/controllers/Wa.php`: cek absen WA harian.

Status data karyawan aktif saat dicek:

- 66 karyawan aktif.
- 0 tanpa cabang via `users.position_id -> position.branch_id`.
- 0 cabang tidak valid.
- 0 tanpa divisi.
- 66 `employee_code` unik.
- 0 `employee_code` aktif duplikat.

## Cleanup Master Cabang 2026-05-20

Snapshot sebelum update disimpan di `exports/db_fix_branch_employee_before_20260520.tsv`.

Perubahan data:

- `branch_id=1`: `branch_code=SDR`, `branch_name=TIFFANY HOUSEWARE SUDIRMAN`.
- `branch_id=2`: `branch_code=GBR`, `branch_name=TIFFANY HOUSEWARE GAMBIR`.
- User Gambir yang sebelumnya berada di posisi branch Sudirman dipindah ke posisi branch Gambir dan subdivision `HOUSEWARE GAMBIR`.
- Baris user aktif duplikat tanpa histori jadwal/presensi dinonaktifkan.

Hasil validasi setelah update:

- Active users: 66.
- Distinct active `employee_code`: 66.
- Active duplicate `employee_code`: 0.
- Mismatch `position.branch_id` vs `subdivision.branch_id`: 0.
- Tanpa cabang posisi: 0.
- Tanpa cabang subdivision: 0.

## Status Cabang Nonaktif 2026-05-20

Snapshot sebelum update status cabang disimpan di `exports/db_branch_inactive_before_20260520.tsv`.

Perubahan struktur:

- Tambah `branch.is_active TINYINT(1) NOT NULL DEFAULT 1`.
- Ganti/tambah `users.location VARCHAR(255) NULL`.

Perubahan data:

- `SDR / TIFFANY HOUSEWARE SUDIRMAN` aktif.
- `GBR / TIFFANY HOUSEWARE GAMBIR` nonaktif.
- Semua user yang sebelumnya memakai posisi cabang Gambir dipindah ke posisi cabang Sudirman.
- User area Sudirman diberi `users.location = 'HOUSEWARE SUDIRMAN'`.
- User area Gambir diberi `users.location = 'HOUSEWARE GAMBIR'`.

Hasil validasi:

- Branch aktif: `SDR`.
- Branch nonaktif `GBR` memiliki 0 user.
- Active users: 66.
- Distinct active `employee_code`: 66.
- Active duplicate `employee_code`: 0.
- Active users dengan `location = 'HOUSEWARE GAMBIR'`: 9.

Query cek ulang:

```sql
SELECT COUNT(*) AS active_users,
       SUM(CASE WHEN p.branch_id IS NULL THEN 1 ELSE 0 END) AS tanpa_branch_position,
       SUM(CASE WHEN b.id IS NULL THEN 1 ELSE 0 END) AS branch_tidak_valid,
       SUM(CASE WHEN u.subdivision_id IS NULL THEN 1 ELSE 0 END) AS tanpa_divisi,
       COUNT(DISTINCT u.employee_code) AS distinct_employee_code
FROM users u
LEFT JOIN position p ON p.id = u.position_id
LEFT JOIN branch b ON b.id = p.branch_id
WHERE u.active = 1;
```

```sql
SELECT u.employee_code,
       COUNT(*) AS jumlah,
       GROUP_CONCAT(CONCAT(u.id, ':', u.first_name, ':', COALESCE(b.branch_name, 'NO_BRANCH'))
                    ORDER BY u.id SEPARATOR ' | ') AS karyawan
FROM users u
LEFT JOIN position p ON p.id = u.position_id
LEFT JOIN branch b ON b.id = p.branch_id
WHERE u.active = 1
GROUP BY u.employee_code
HAVING COUNT(*) > 1
ORDER BY u.employee_code;
```

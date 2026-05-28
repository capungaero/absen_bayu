# Analisis Import Data Absen

Dokumen ini merangkum bagaimana data absen dari file `.dat`, cloud sync, Excel, dan input manual diposisikan ke tabel `presence`, bagaimana data di-update, serta tabel lain yang berkorelasi.

## Posisi Data `.dat` Ke `presence`

Data `.dat` dibaca sebagai log mentah mesin dengan struktur umum:

```text
ID_FINGERPRINT  YYYY-MM-DD HH:MM:SS  VERIFY_MODE  IN_OUT_MODE  WORK_CODE
```

Contoh:

```text
64  2024-12-31 07:13:36  1  0  1
```

Mapping ke aplikasi:

- `ID_FINGERPRINT` dicocokkan ke `users.employee_code`.
- User dibatasi sesuai cabang melalui `position.branch_id`.
- `users.id` disimpan sebagai `presence.user_id`.
- Tanggal scan disimpan sebagai `presence.flow_date`.
- Scan pertama hari itu disimpan sebagai `presence.entry_time`.
- Scan terakhir hari itu disimpan sebagai `presence.out_time`.
- Import otomatis memakai `presence.input_by = system`.
- Data yang tampil harus memiliki `presence.presence_status = approved`.

## Kolom Penting Tabel `presence`

Kolom identitas:

- `id`
- `user_id`
- `flow_date`

Kolom jam kerja:

- `entry_time`
- `out_time`
- `entry_time_late`
- `rest_time_in`
- `rest_time_out`
- `rest_time_late`

Kolom sholat:

- `subuh_time_in`, `subuh_time_out`, `subuh_time_late`
- `dzuhur_time_in`, `dzuhur_time_out`, `dzuhur_time_late`
- `ashar_time_in`, `ashar_time_out`, `ashar_time_late`
- `maghrib_time_in`, `maghrib_time_out`, `maghrib_time_late`
- `isha_time_in`, `isha_time_out`, `isha_time_late`
- `friday_time_in`, `friday_time_out`, `friday_time_late`

Kolom metadata:

- `created_at`
- `updated_at`
- `input_by`
- `input_by_user_id`
- `presence_get_paid`
- `presence_type`
- `presence_status`
- `is_overtime`
- `flag`

## Payload Default Import

Payload default dibuat oleh `_payload()` di:

```text
application/controllers/hr/Presence.php
```

Nilai default penting:

- `entry_time = null`
- `out_time = null`
- `entry_time_late = 0`
- `rest_time_in = null`
- `rest_time_out = null`
- `rest_time_late = 0`
- `input_by = system`
- `presence_status = approved`
- `is_overtime = '0'`

## Alur Sync `.dat` / Cloud

Fungsi utama import `.dat` / cloud:

```text
application/controllers/hr/Presence.php::_import_attlog_dat()
```

Alurnya:

1. File/log mentah dipecah per baris.
2. Setiap baris diparse menjadi:
   - `finger_id`
   - `datetime`
   - `verify`
   - `status`
   - `workcode`
3. Data difilter berdasarkan periode payroll halaman.
4. Data dikelompokkan per `finger_id + flow_date`.
5. `finger_id` dicocokkan ke `users.employee_code` dan cabang aktif.
6. Data disiapkan ke format `presence`.
7. Data disimpan atau di-merge ke tabel `presence`.

## Mode Tanpa Jadwal

Mode default saat ini adalah tidak memakai jadwal.

Jika `Pakai jadwal` tidak dicentang:

- Sistem tidak mengecek window shift.
- Semua scan per `finger_id + tanggal` dipakai.
- Scan paling awal menjadi `entry_time`.
- Scan paling akhir menjadi `out_time`.
- Hari tanpa jadwal tetap bisa masuk selama ada scan.

Helper fallback:

```text
application/controllers/hr/Presence.php::_apply_attlog_fallback()
```

## Mode Pakai Jadwal

Jika `Pakai jadwal` dicentang:

- Sistem mencari jadwal user di `users_shift_additional`.
- Jadwal di-join ke tabel `shift`.
- Scan dicocokkan dengan window shift.

Mapping window shift:

- Masuk: `shift.start_time_in` sampai `shift.start_time_out`
- Batas telat: `shift.start_time_late`
- Pulang: `shift.end_time_in` sampai `shift.end_time_out`
- Istirahat: `shift.start_time_rest` sampai `shift.end_time_rest`
- Durasi istirahat: `shift.rest_time_range`

Jika tidak ada jadwal atau scan tidak masuk window shift, data bisa dilewati pada mode pakai jadwal.

## Cara Update Saat Sync Dua Mesin

Sync cloud memproses mesin satu per satu.

Alur aman:

1. Data periode dikosongkan sekali di awal.
2. Mesin pertama diproses dan insert ke `presence`.
3. Mesin kedua diproses dengan mode merge.
4. Mesin kedua tidak menghapus hasil mesin pertama.

Merge berdasarkan:

```text
user_id + flow_date
```

Jika record belum ada:

- Insert baru ke `presence`.

Jika record sudah ada:

- Hanya mengisi kolom yang masih kosong.
- Kolom yang bisa diisi:
  - `entry_time`
  - `out_time`
  - `rest_time_in`
  - `rest_time_out`
  - `entry_time_late`
  - `rest_time_late`

Data yang sudah terisi tidak ditimpa.

## Tombol Hapus

Tombol `Hapus` hanya menghapus data absen di tabel `presence`.

Yang dihapus:

- `presence`
- berdasarkan periode payroll aktif
- berdasarkan cabang aktif

Yang tidak dihapus:

- `users_shift_additional`
- `shift`
- `users`
- `branch`
- `position`
- `subdivision`

## Import Excel Lama

Fungsi import Excel:

```text
application/controllers/hr/Presence.php::upload()
```

Format file yang diterima:

- `.xlsx`
- `.csv`

Kolom yang dibaca berdasarkan index:

- `$sheetData[$i][2]` = ID fingerprint / `employee_code`
- `$sheetData[$i][3]` = waktu absen

Format waktu yang diharapkan:

```text
dd-mm-yyyy hh:mm:ss
```

Contoh:

```text
31-12-2025 07:13:36
```

Excel lama wajib punya jadwal kerja di `users_shift_additional`. Jika tidak ada jadwal, data tidak masuk ke `presence`.

Sebelum insert, import Excel lama menghapus data lama untuk `user_id + flow_date` yang ada di file, lalu melakukan `insert_batch('presence', $data)`.

## Update Manual Dari Tampilan

Fungsi update manual:

```text
application/controllers/hr/Presence.php::update_workhour()
```

Field yang bisa diubah manual:

- `entry_time`
- `out_time`
- `rest_time_in`
- `rest_time_out`
- `entry_time_late`
- `rest_time_late`
- `is_overtime`

Metadata manual:

- `input_by = manual`
- `input_by_user_id = user login`

Jika record `user_id + flow_date` sudah ada, data di-update. Jika belum ada, data di-insert.

## Cara Tampilan Membaca `presence`

Model utama:

```text
application/models/Presence_model.php::get_attendance_by_branch()
```

Data presensi diprefetch dari tabel `presence` dengan join:

- `users`
- `position`
- `overtime`

Filter penting:

- `presence_status = approved`
- `flow_date` dalam periode payroll
- `user_id` sesuai cabang

Periode payroll:

- dari tanggal `26` bulan sebelumnya
- sampai tanggal `25` bulan aktif

## Status Hadir Di Tampilan

Logika umum:

- Ada `entry_time` dan `out_time` → hadir penuh.
- Hanya salah satu dari `entry_time` atau `out_time` → hadir sebagian.
- Tidak ada record `presence` → tidak hadir atau libur, tergantung jadwal.
- `presence_type != normal` → izin/cuti/sakit.

## Korelasi Dengan Tabel Lain

### `users`

- `presence.user_id = users.id`
- `.dat` memakai `users.employee_code` untuk mapping ID fingerprint.

### `position`

- Dipakai untuk filter cabang.
- Relasi: `position.id = users.position_id`
- Cabang: `position.branch_id`

### `branch`

- Menentukan cabang aktif pada halaman dan sync.

### `users_shift_additional`

- Jadwal per user per tanggal.
- Dipakai saat mode pakai jadwal.
- Dipakai tampilan kalender untuk menentukan work/free/off.

### `shift`

- Master jam kerja.
- Menentukan window masuk, pulang, istirahat, dan batas telat.

### `overtime`

- Di-join saat menampilkan presensi.
- Relasi:

```text
overtime.user_id = presence.user_id
overtime.overtime_date = presence.flow_date
```

### `leave`

- Proses izin/cuti/sakit bisa membuat atau menghapus record `presence` dengan `presence_type` tertentu.

### `payroll_insentif`

- Insentif dapat dihitung dari jumlah presensi penuh.
- Presensi penuh dihitung dari record yang punya `entry_time` dan `out_time`.

### `payroll_deduction`

- Potongan payroll ditampilkan bersama rekap presensi.

### `presence_import_log`

- Mencatat aktivitas upload/sync/delete.
- Tidak menjadi sumber data presensi.

## Catatan Risiko

- Tabel `presence` belum punya unique index untuk `user_id + flow_date`, sehingga duplikasi bisa terjadi jika import tidak menjaga merge.
- Sync `.dat` sekarang sudah mengecek existing `user_id + flow_date` saat merge.
- Import Excel lama masih delete lalu insert, sehingga bisa mengganti data manual pada tanggal yang sama.
- `flow_date` bertipe `varchar(200)`, padahal isinya tanggal. Lebih ideal jika bertipe `DATE`.
- Import Excel lama hardcoded membaca format `dd-mm-yyyy hh:mm:ss`; jika Excel menyimpan tanggal sebagai serial date, parsing bisa salah.
- Import Excel lama belum memakai helper `_time_between()`, sehingga window lintas tengah malam bisa bermasalah.

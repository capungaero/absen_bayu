# Rencana Perbaikan Modul Absensi

Tanggal audit: 2026-05-20

Status implementasi awal: 2026-05-20

Perbaikan yang sudah dijalankan:

- Akses `branch_id` di endpoint absensi aktif dikunci lewat resolver cabang.
- Cron WA bisa dipanggil tanpa session login, tetap wajib token acak `cron_token`.
- Rekap WA memakai `presence.entry_time_late` sebagai sumber telat.
- Definisi alpha di report shift disamakan dengan daily report.
- `machine_sn` divalidasi sebelum dipakai untuk glob/path file.
- Fallback credential mesin hardcoded dihapus dari alur sync aktif.
- Import presensi aktif memakai jadwal kerja terbaru berdasarkan `MAX(id)`.
- Response sukses upload jadwal di controller legacy diperbaiki dari `false` ke `true`.

## Ringkasan

Modul absensi berjalan, tetapi ada beberapa risiko yang perlu diprioritaskan:

- Hak akses cabang masih bisa dilompati lewat POST `branch_id`.
- Cron WA tidak bisa berjalan tanpa login walaupun sudah memakai token.
- Hitung keterlambatan belum konsisten antar laporan.
- Credential mesin dan endpoint cloud masih hardcoded.
- Import presensi dan jadwal masih tersebar di file controller besar.

## Prioritas P1

### 1. Kunci akses cabang di semua endpoint absensi

Masalah:

- Role `admin-branch`, `hr`, dan `supervisor` masih membaca `branch_id` dari request pada beberapa aksi sensitif.
- Dampak: user non-admin dapat memanipulasi POST dan memproses data cabang lain.

Lokasi:

- `application/controllers/hr/Presence.php`
  - `upload_pray()`
  - `upload()`
  - `sync_cloud()`
  - `sync_pray_cloud()`
  - `clear_period()`

Perbaikan:

- Tambah helper internal:

```php
private function _resolve_branch_id($posted_branch_id = null) {
    if ($this->role === 'admin') {
        return $posted_branch_id ?: $this->userdata->branch_id;
    }

    return $this->userdata->branch_id;
}
```

- Semua endpoint sensitif wajib pakai helper ini.
- Validasi cabang exists sebelum proses.
- Tambah guard agar supervisor hanya bisa cabang sendiri.

Checklist:

- [ ] Ganti semua `$branch_id = $p['branch_id']` pada aksi absensi.
- [ ] Tes admin bisa pilih cabang.
- [ ] Tes non-admin tidak bisa manipulasi cabang.
- [ ] Tambah log jika ada request cabang tidak sesuai user.

### 2. Perbaiki cron WA agar benar-benar bisa jalan

Masalah:

- `Wa::__construct()` memaksa login untuk semua method.
- `Wa::cron($token)` dikomentari sebagai endpoint tanpa login, tetapi tetap redirect sebelum token dicek.

Lokasi:

- `application/controllers/Wa.php`

Perbaikan:

- Bypass login hanya untuk method `cron`.
- Validasi token tetap wajib.
- Token jangan dibuat dari `md5(secret . 'cron_secret')`; gunakan token acak dari konfigurasi/env.

Checklist:

- [ ] Deteksi method aktif dengan `$this->router->fetch_method()`.
- [ ] Skip `logged_in()` hanya jika method `cron`.
- [ ] Tambah kolom/config `cron_token`.
- [ ] Rotasi token lama.

### 3. Hapus credential mesin hardcoded

Masalah:

- SN mesin dan password `solution` masih hardcoded.
- Request cloud memakai `http://solutioncloud.co.id/`.

Lokasi:

- `application/controllers/hr/Presence.php`
- `application/controllers/Presence.php`
- `application/models/Sync_model.php`
- `application/controllers/Wa.php`
- `application/controllers/Sync.php`

Perbaikan:

- Ambil mesin hanya dari tabel `sync_machine`.
- Hapus fallback default dari controller dan model.
- Simpan password terenkripsi atau minimal batasi akses UI password.
- Gunakan HTTPS jika vendor mendukung.

Checklist:

- [ ] Hapus fallback array SN/password.
- [ ] Seed default hanya untuk dev, bukan produksi.
- [ ] Tambah validasi password kosong.
- [ ] Masking password di view.

## Prioritas P2

### 4. Satukan logika hitung terlambat

Masalah:

- `presence.entry_time_late` menyimpan menit telat tanpa detik.
- `Wa_model::get_today_shift_report()` menghitung ulang dengan `TIME(entry_time) > start_time_late`, sehingga telat 7 detik bisa dihitung 1 menit.
- Hari audit: data tersimpan menunjukkan 1 telat, report WA bisa menghitung 3 telat.

Lokasi:

- `application/models/Wa_model.php`
- `application/controllers/Attendance.php`
- `application/controllers/hr/Presence.php`
- `application/controllers/Wa.php`

Perbaikan:

- Jadikan `presence.entry_time_late > 0` sebagai sumber utama report.
- Buat helper/service tunggal untuk menghitung telat:

```php
private function _late_minutes($limit, $time) {
    if ($limit === null || $limit === '' || $time === null || $time === '') {
        return 0;
    }

    $limit = date('H:i', strtotime($limit));
    $time = date('H:i', strtotime($time));

    $limit_minutes = ((int) substr($limit, 0, 2) * 60) + (int) substr($limit, 3, 2);
    $time_minutes = ((int) substr($time, 0, 2) * 60) + (int) substr($time, 3, 2);

    return max(0, $time_minutes - $limit_minutes);
}
```

Checklist:

- [ ] `Wa_model` tidak hitung ulang dari detik.
- [ ] `Attendance` pakai helper yang sama.
- [ ] Manual input, sync cloud, upload Excel, dan report hasilnya sama.
- [ ] Tambah test case masuk `07:50:07` dengan batas `07:50:00` harus `0 menit`.

### 5. Pakai jadwal terbaru saat import absensi

Masalah:

- Import presensi mengambil jadwal dengan `row_array()` tanpa `MAX(id)`.
- Jika satu user punya beberapa baris jadwal tanggal sama, import bisa ambil shift lama.

Lokasi:

- `application/controllers/hr/Presence.php::_import_presence_sheet()`
- `application/controllers/Presence.php::_import_presence_sheet()`

Perbaikan:

- Buat helper `get_latest_work_schedule($user_id, $date)`.
- Query wajib filter `MAX(id)` seperti di report jadwal.
- Pertimbangkan unique index `(user_id, additional_date)` setelah data historis bersih.

Checklist:

- [ ] Ganti query import presensi.
- [ ] Ganti query sync WA jika belum latest.
- [ ] Cek data duplicate sebelum tambah unique index.

### 6. Samakan definisi alpha/belum hadir

Masalah:

- Report shift menghitung alpha hanya jika `presence.id IS NULL`.
- Daily report menghitung alpha juga saat row presensi ada tetapi semua jam kosong.

Lokasi:

- `application/controllers/Attendance.php::_get_shifts_with_attendance()`
- `application/controllers/Attendance.php::_get_daily_report_absent_rows()`

Perbaikan:

- Gunakan definisi yang sama:
  - tidak ada row presence, atau
  - `presence_type = normal` dan semua jam kerja kosong.

Checklist:

- [ ] Update query shift report.
- [ ] Cocokkan total alpha dengan daily report.

### 7. Sanitasi `machine_sn` untuk path file

Masalah:

- `machine_sn` dari input/DB dipakai untuk glob dan nama file.
- Risiko nama file aneh, path manipulation, atau file report salah.

Lokasi:

- `application/controllers/Sync.php`
- `application/controllers/hr/Presence.php`
- `application/controllers/Attendance.php`

Perbaikan:

- Validasi SN hanya `A-Z`, `a-z`, `0-9`, `_`, `-`.
- Gunakan fungsi khusus:

```php
private function _sanitize_machine_sn($sn) {
    $sn = trim((string) $sn);
    return preg_match('/^[A-Za-z0-9_-]+$/', $sn) ? $sn : '';
}
```

Checklist:

- [ ] Validasi saat create/update mesin.
- [ ] Validasi sebelum glob file.
- [ ] Validasi sebelum simpan file `.dat`.

## Prioritas P3

### 8. Kurangi controller besar dan duplikasi

Masalah:

- `application/controllers/hr/Presence.php` sangat besar.
- `application/controllers/Presence.php` masih ada sebagai controller lama.
- Ada logic sama tersebar di controller, model, dan WA.

Perbaikan bertahap:

- `AttendanceImportService`: parse Excel/DAT, map finger, upsert presence.
- `ScheduleService`: resolve jadwal kerja latest.
- `LateCalculator`: hitung telat.
- `MachineSyncService`: download cloud, simpan DAT.
- `DailyReportService`: sync `presence_daily_report`.

Checklist:

- [ ] Tandai `application/controllers/Presence.php` sebagai legacy atau hapus route aksesnya.
- [ ] Pindahkan helper kecil dulu tanpa ubah behavior.
- [ ] Tambah test pada service baru.

### 9. Perbaiki status response upload jadwal legacy

Masalah:

- Controller legacy mengembalikan `status => false` walaupun pesan sukses.

Lokasi:

- `application/controllers/Presence.php`

Perbaikan:

- Jika file sukses diupload, status harus `true`.
- Jika file legacy tidak dipakai, hapus controller agar tidak membingungkan.

## Rencana Eksekusi Disarankan

1. Kunci `branch_id` non-admin di `hr/Presence.php`.
2. Perbaiki cron WA.
3. Samakan hitung terlambat memakai `entry_time_late`.
4. Samakan definisi alpha.
5. Sanitasi `machine_sn`.
6. Hapus hardcoded credential.
7. Refactor service bertahap.

## Uji Minimum Setelah Perbaikan

- Admin bisa proses semua cabang sesuai pilihan.
- Admin cabang tidak bisa proses cabang lain walau POST dimanipulasi.
- Cron WA bisa dipanggil tanpa session login jika token valid.
- Masuk `07:50:07` dengan batas `07:50:00` tetap telat `0 menit`.
- Import Excel presensi tidak menghapus data cabang lain.
- Sync cloud hanya menyimpan file dengan SN valid.
- Daily report dan shift report menghasilkan total terlambat yang sama.

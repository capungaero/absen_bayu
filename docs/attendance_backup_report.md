# Backup dan Report CSV Absen Mingguan

Script:

- `scripts/attendance_backup_report.py`
- `scripts/run_attendance_backup_weekly.ps1`

Tujuan:

- Backup data absen mingguan ke CSV.
- Buat report rinci 1 baris per tanggal per karyawan.
- Buat summary per karyawan dan per tanggal untuk olah manual payroll.

## Sumber Data

Script baca koneksi dari `application/config/database.php`.

Default database saat ini:

- host: `127.0.0.1`
- port: `3306`
- database: `newtiffa_timesheet`
- user: dari config CodeIgniter

Override tanpa ubah file:

```powershell
$env:ABSEN_DB_HOST="127.0.0.1"
$env:ABSEN_DB_PORT="3306"
$env:ABSEN_DB_USER="root"
$env:ABSEN_DB_PASSWORD=""
$env:ABSEN_DB_NAME="newtiffa_timesheet"
```

## Output

Folder default:

```text
exports/attendance_backups/weekly_<start>_to_<end>_<timestamp>/
```

File:

- `report_weekly_detail.csv`: detail tanggal x karyawan.
- `report_weekly_employee_summary.csv`: rekap per karyawan.
- `report_weekly_date_summary.csv`: rekap per tanggal.
- `backup_employees.csv`: identitas kerja tersanitasi.
- `backup_schedules.csv`: jadwal periode.
- `backup_presence.csv`: presensi periode.
- `backup_shifts.csv`: master shift.
- `backup_leave.csv`: izin/cuti/sakit periode.
- `metadata.json`: info periode dan jumlah row.

Catatan keamanan: backup karyawan tidak menyertakan `password`, rekening bank, phone, salary, atau alamat.

## Cara Pakai Manual

Minggu berjalan:

```powershell
python scripts\attendance_backup_report.py
```

Minggu sebelumnya:

```powershell
python scripts\attendance_backup_report.py --previous-week
```

Tanggal spesifik:

```powershell
python scripts\attendance_backup_report.py --week-start 2026-05-18 --week-end 2026-05-24
```

Filter lokasi/cabang:

```powershell
python scripts\attendance_backup_report.py --week-start 2026-05-18 --branch Gambir
```

Filter ID fingerprint:

```powershell
python scripts\attendance_backup_report.py --week-start 2026-05-18 --employee-codes 337,64,24
```

Jika `mysql.exe` tidak terdeteksi:

```powershell
python scripts\attendance_backup_report.py --mysql "D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe"
```

## Otomatis Mingguan

Runner mingguan:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\run_attendance_backup_weekly.ps1
```

Daftar ke Windows Task Scheduler setiap Senin 01:00 untuk minggu sebelumnya:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\install_attendance_backup_task.ps1
```

Ubah hari/jam:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\install_attendance_backup_task.ps1 -DayOfWeek Monday -At 01:00
```

## Aturan Status

- `HADIR`: jadwal kerja, presence approved normal, masuk dan pulang lengkap, tidak telat.
- `TERLAMBAT`: sama seperti hadir, tetapi `presence.entry_time_late > 0`.
- `IZIN`, `CUTI`, `SAKIT`: `presence_type` non-normal atau leave approve tanpa row presence.
- `ABSEN`: jadwal kerja sudah lewat, tidak ada presence approved.
- `TIDAK_LENGKAP`: masuk/pulang tidak lengkap atau hanya ada presence non-approved.
- `LIBUR`: jadwal `additional_type = free`.
- `TANPA_JADWAL`: tidak ada jadwal final untuk tanggal itu.
- `PRESENSI_TANPA_JADWAL`: ada presence approved tetapi tidak ada jadwal.
- `FUTURE`: tanggal setelah `as_of`, tidak dihitung absen.

Jadwal final memakai row terbaru `MAX(users_shift_additional.id)` per user dan tanggal, sama seperti proses report lama.

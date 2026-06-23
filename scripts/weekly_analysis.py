#!/usr/bin/env python3
"""Weekly Attendance Analysis Script"""
import csv
from collections import defaultdict

# Read the detailed report
detail_path = r'E:\VIBECODING\absen_bayu\exports\attendance_backups\weekly_2026-05-18_to_2026-05-24_20260524_003244\report_weekly_detail.csv'
date_summary_path = r'E:\VIBECODING\absen_bayu\exports\attendance_backups\weekly_2026-05-18_to_2026-05-24_20260524_003244\report_weekly_date_summary.csv'
employee_summary_path = r'E:\VIBECODING\absen_bayu\exports\attendance_backups\weekly_2026-05-18_to_2026-05-24_20260524_003244\report_weekly_employee_summary.csv'
presence_path = r'E:\VIBECODING\absen_bayu\exports\attendance_backups\weekly_2026-05-18_to_2026-05-24_20260524_003244\backup_presence.csv'
schedule_path = r'E:\VIBECODING\absen_bayu\exports\attendance_backups\weekly_2026-05-18_to_2026-05-24_20260524_003244\backup_schedules.csv'

ENCODING = 'utf-8-sig'

print("=" * 80)
print("ANALISIS DATA ATTENDANCE - WEEKLY REPORT (18-24 MEI 2026)")
print("=" * 80)
print()

# 1. Date Summary Analysis
print("1. RINGKASAN PER HARI")
print("-" * 80)
with open(date_summary_path, 'r', encoding=ENCODING) as f:
    reader = csv.DictReader(f)
    for row in reader:
        print(f"{row['tanggal']} ({row['hari']})")
        print(f"   Total Karyawan: {row['total_karyawan']}")
        print(f"   Hari Kerja: {row['hari_kerja']}, Hadir: {row['hadir']}, Terlambat: {row['terlambat']}")
        print(f"   Absen: {row['absen']}, Tidak Lengkap: {row['tidak_lengkap']}")
        print(f"   Libur: {row['libur']}, Tanpa Jadwal: {row['tanpa_jadwal']}")
        print()

# 2. Employee Summary Analysis
print("\n2. TOP 10 KARYAWAN DENGAN ABSEN TERBANYAK")
print("-" * 80)
employees = []
with open(employee_summary_path, 'r', encoding=ENCODING) as f:
    reader = csv.DictReader(f)
    for row in reader:
        employees.append(row)

# Sort by absen (most absent)
employees_sorted_absen = sorted(employees, key=lambda x: int(x['absen']), reverse=True)
print(f"{'Nama':<35} {'Kode':<8} {'Hadir':<6} {'Absen':<6} {'Tidak Lengkap':<14} {'Tanpa Jadwal':<12}")
print("-" * 80)
for emp in employees_sorted_absen[:10]:
    if int(emp['absen']) > 0:
        print(f"{emp['employee_name']:<35} {emp['employee_code']:<8} {emp['hadir']:<6} {emp['absen']:<6} {emp['tidak_lengkap']:<14} {emp['tanpa_jadwal']:<12}")

print("\n\n3. TOP 10 KARYAWAN DENGAN KETERLAMBATAN TERBANYAK")
print("-" * 80)
employees_sorted_late = sorted(employees, key=lambda x: int(x['menit_telat']), reverse=True)
print(f"{'Nama':<35} {'Kode':<8} {'Menit Telat':<12} {'Hadir':<6} {'Terlambat':<10}")
print("-" * 80)
for emp in employees_sorted_late[:10]:
    if int(emp['menit_telat']) > 0:
        print(f"{emp['employee_name']:<35} {emp['employee_code']:<8} {emp['menit_telat']:<12} {emp['hadir']:<6} {emp['terlambat']:<10}")

# 3. Presence data analysis
print("\n\n4. ANALISIS DATA PRESENCE (FINGERPRINT MESIN)")
print("-" * 80)
presence_count = 0
unique_dates = set()
unique_users = set()
late_minutes = []

with open(presence_path, 'r', encoding=ENCODING) as f:
    reader = csv.DictReader(f)
    for row in reader:
        presence_count += 1
        unique_dates.add(row['flow_date'])
        unique_users.add(row['user_id'])
        if row['entry_time_late'] and row['entry_time_late'].isdigit():
            late_minutes.append(int(row['entry_time_late']))

print(f"Total Records Presence: {presence_count}")
print(f"Unique Tanggal: {len(unique_dates)}")
print(f"Unique Karyawan (Fingerprint): {len(unique_users)}")
if late_minutes:
    print(f"Rata-rata Keterlambatan: {sum(late_minutes)/len(late_minutes):.1f} menit")
    print(f"Max Keterlambatan: {max(late_minutes)} menit")

# 4. Schedule data analysis
print("\n\n5. ANALISIS DATA JADWAL (SCHEDULE)")
print("-" * 80)
schedule_count = 0
shift_types = defaultdict(int)
with open(schedule_path, 'r', encoding=ENCODING) as f:
    reader = csv.DictReader(f)
    for row in reader:
        schedule_count += 1
        shift_types[row['additional_type']] += 1

print(f"Total Records Schedule: {schedule_count}")
print(f"Jenis Jadwal:")
for stype, count in shift_types.items():
    print(f"   {stype}: {count}")

# 5. Status distribution
print("\n\n6. DISTRIBUSI STATUS KEDATANGAN")
print("-" * 80)
status_count = defaultdict(int)
with open(detail_path, 'r', encoding=ENCODING) as f:
    reader = csv.DictReader(f)
    for row in reader:
        status_count[row['status']] += 1

for status, count in sorted(status_count.items(), key=lambda x: x[1], reverse=True):
    print(f"   {status}: {count}")

# 6. Division summary
print("\n\n7. RINGKASAN PER DIVISI")
print("-" * 80)
div_summary = defaultdict(lambda: {'total': 0, 'hadir': 0, 'absen': 0, 'telat': 0, 'jam_kerja': 0})
with open(employee_summary_path, 'r', encoding=ENCODING) as f:
    reader = csv.DictReader(f)
    for row in reader:
        div = row['division_name'] or 'TANPA DIVISI'
        div_summary[div]['total'] += 1
        div_summary[div]['hadir'] += int(row['hadir'])
        div_summary[div]['absen'] += int(row['absen'])
        div_summary[div]['telat'] += int(row['terlambat'])
        div_summary[div]['jam_kerja'] += int(row['hari_kerja'])

for div, data in sorted(div_summary.items(), key=lambda x: x[1]['total'], reverse=True):
    rate = (data['hadir'] / (data['total'] * 7) * 100) if data['total'] * 7 > 0 else 0
    print(f"{div}:")
    print(f"   Karyawan: {data['total']}, Total Hadir: {data['hadir']}, Total Absen: {data['absen']}")
    print(f"   Total Telat: {data['telat']}, Jam Kerja: {data['jam_kerja']}")
    print(f"   Tingkat Kehadiran: {rate:.1f}%")
    print()

print("\n\n" + "=" * 80)
print("ANALISIS SELESAI")
print("=" * 80)
#!/usr/bin/env python3
"""
Edit Shift Retroaktif (1 Bulan / Masa Penggajian)

RULE BARU:
- ✅ Edit shift BISA mengubah data yang sudah tercatat
- ✅ Berlaku dalam 1 bulan yang sama (rentang masa penggajian)
- ✅ Otomatis tambah assignment untuk tanggal tanpa jadwal (shift diinput telat)
- ✅ Otomatis update data keterlambatan (entry_time_late)
- 🔒 (2026-07-15) Menolak seluruh edit kalau payroll cabang/bulan target sudah
  dikunci (tabel `payroll`) — paralel proteksi lock di absen_sync.py & PHP,
  supaya tool manual ini juga tidak menimpa data periode yang sudah digaji.

TABEL YANG DIGUNAKAN: users_shift_additional (BUKAN users_shift_cluster)
Website tiffany.my.id/absen/ membaca dari tabel ini.

Usage:
  python3 edit_shift.py --user-id 273 --date 2026-06-10 --shift-id 49 --reason "shift diinput telat"
  python3 edit_shift.py --user-id 273 --date 2026-06-10 --shift-id 49 --dry-run

Author: Sinta (Hermes Agent)
"""

import os
import sys
import subprocess
import json
import calendar
from datetime import datetime, timedelta
import argparse

# ═══════════════════════════════════════════════════════════════════════════════
# CONFIGURATION
# ═══════════════════════════════════════════════════════════════════════════════

SSH_CMD = [
    'ssh',
    '-i', os.path.expanduser('~/.ssh/tiffany_key'),
    '-p', '2223',
    'tifx3722@tiffany.my.id'
]

MYSQL_BASE = "mysql -u tifx3722_absen -p'N7mQ4vZ9rT2pL8sW6xY3' -S /var/lib/mysql/mysql.sock tifx3722_newtiffa_timesheet -N -B"

def run_query(sql, fetch=True):
    """Execute MySQL query via SSH using heredoc to avoid quoting issues."""
    # Use heredoc to pass SQL safely
    remote_cmd = f"{MYSQL_BASE} <<'ENDSQL'\n{sql}\nENDSQL"
    full_cmd = SSH_CMD + [remote_cmd]
    result = subprocess.run(full_cmd, capture_output=True, text=True, timeout=30)
    
    if result.returncode != 0:
        raise Exception(f"MySQL error: {result.stderr or result.stdout}")
    
    if not fetch or not result.stdout.strip():
        return []
    
    rows = []
    for line in result.stdout.strip().split('\n'):
        if line and line.strip():
            rows.append(line.split('\t'))
    return rows

# ═══════════════════════════════════════════════════════════════════════════════
# QUERY FUNCTIONS
# ═══════════════════════════════════════════════════════════════════════════════

def get_user_info(user_id):
    """Ambil info user."""
    rows = run_query(f"SELECT id, first_name, last_name FROM users WHERE id = {user_id}")
    if rows:
        return {'id': rows[0][0], 'name': f"{rows[0][1]} {rows[0][2] or ''}".strip()}
    return None

def get_shift_info(shift_id):
    """Ambil info shift."""
    rows = run_query(f"SELECT id, shift_name, shift_code, start_time, end_time FROM shift WHERE id = {shift_id}")
    if rows:
        return {
            'id': rows[0][0],
            'name': rows[0][1],
            'code': rows[0][2],
            'start_time': rows[0][3],
            'end_time': rows[0][4]
        }
    return None

def get_user_schedule(user_id, start_date, end_date):
    """Ambil jadwal shift user dari users_shift_additional."""
    rows = run_query(f"""
        SELECT id, shift_id, additional_date, additional_type
        FROM users_shift_additional
        WHERE user_id = {user_id}
          AND additional_date BETWEEN '{start_date}' AND '{end_date}'
          AND deleted_at IS NULL
        ORDER BY additional_date
    """)
    return rows

def get_user_presence(user_id, start_date, end_date):
    """Ambil data kehadiran user."""
    rows = run_query(f"""
        SELECT id, entry_time, out_time, flow_date, entry_time_late, presence_type
        FROM presence
        WHERE user_id = {user_id}
          AND flow_date BETWEEN '{start_date}' AND '{end_date}'
        ORDER BY flow_date
    """)
    return rows

def get_branch_id(user_id):
    """Ambil branch_id user via position (sama seperti absen_sync.py)."""
    rows = run_query(f"""
        SELECT po.branch_id
        FROM users u
        JOIN position po ON po.id = u.position_id
        WHERE u.id = {user_id}
    """)
    if rows and rows[0][0] not in (None, 'NULL', ''):
        return int(rows[0][0])
    return None

def is_payroll_locked(branch_id, month, year):
    """Cek apakah periode payroll (branch, bulan, tahun) sudah dikunci.

    Paralel dengan get_locked_branches() di absen_sync.py / _payroll_locked()
    PHP — supaya tool edit manual ini tidak menimpa data periode yang sudah
    digaji.
    """
    if branch_id is None:
        return False
    rows = run_query(f"""
        SELECT id FROM payroll
        WHERE branch_id = {branch_id} AND month = {month} AND year = {year}
    """)
    return bool(rows)

# ═══════════════════════════════════════════════════════════════════════════════
# EDIT SHIFT RETROAKTIF
# ═══════════════════════════════════════════════════════════════════════════════

def edit_shift_retroaktif(user_id, target_date, new_shift_id, reason="", dry_run=False):
    """
    Edit shift dengan update retroaktif dalam 1 bulan.
    
    TABEL: users_shift_additional (website baca dari sini)
    
    Kolom:
    - user_id → ID karyawan
    - shift_id → ID shift (49=PAGI, 50=MIDDLE, 51=SIANG, dst)
    - additional_date → tanggal berlaku
    - additional_type → 'work' atau 'free'
    """
    
    result = {
        'success': False,
        'user_id': user_id,
        'target_date': target_date,
        'new_shift_id': new_shift_id,
        'reason': reason,
        'dry_run': dry_run,
        'changes': [],
        'errors': []
    }
    
    # Validasi user
    user = get_user_info(user_id)
    if not user:
        result['errors'].append(f"User ID {user_id} tidak ditemukan")
        return result
    result['user_name'] = user['name']
    
    # Validasi shift baru
    shift = get_shift_info(new_shift_id)
    if not shift:
        result['errors'].append(f"Shift ID {new_shift_id} tidak ditemukan")
        return result
    result['new_shift_name'] = shift['name']
    result['new_shift_code'] = shift['code']

    # Hitung rentang 1 bulan (masa penggajian)
    target_dt = datetime.strptime(target_date, '%Y-%m-%d')
    start_of_month = target_dt.replace(day=1)
    last_day = calendar.monthrange(target_dt.year, target_dt.month)[1]
    end_of_month = target_dt.replace(day=last_day)

    start_date = start_of_month.strftime('%Y-%m-%d')
    end_date = end_of_month.strftime('%Y-%m-%d')
    result['period'] = f"{start_date} s/d {end_date}"

    # Cek lock payroll — tolak seluruh edit kalau periode branch ini sudah
    # digaji, supaya konsisten dengan proteksi di sync (absen_sync.py) & PHP.
    branch_id = get_branch_id(user_id)
    if is_payroll_locked(branch_id, target_dt.month, target_dt.year):
        result['errors'].append(
            f"Periode {target_dt.month}/{target_dt.year} untuk cabang {branch_id} "
            f"sudah dikunci payroll — edit shift dibatalkan"
        )
        return result
    
    # Ambil data jadwal yang ada
    schedule = get_user_schedule(user_id, start_date, end_date)
    schedule_dates = {s[2]: s for s in schedule}  # {date: row}
    
    # Ambil data kehadiran
    presence = get_user_presence(user_id, start_date, end_date)
    presence_dates = {p[3]: p for p in presence}  # {flow_date: row}
    
    # ══════════════════════════════════════════════════════════════════════════
    # STEP 1: EDIT SHIFT UNTUK TANGGAL TARGET
    # ══════════════════════════════════════════════════════════════════════════
    
    if target_date in schedule_dates:
        # UPDATE jadwal yang ada
        old_row = schedule_dates[target_date]
        old_shift_id = old_row[1]
        old_type = old_row[3]
        schedule_id = old_row[0]
        
        old_shift_info = get_shift_info(old_shift_id) if old_shift_id else None
        result['action'] = 'UPDATE'
        result['old_shift_id'] = old_shift_id
        result['old_shift_name'] = old_shift_info['name'] if old_shift_info else 'Libur'
        
        if not dry_run:
            run_query(f"""
                UPDATE users_shift_additional 
                SET shift_id = {new_shift_id}, additional_type = 'work', updated_at = NOW()
                WHERE id = {schedule_id}
            """, fetch=False)
        
        result['changes'].append({
            'type': 'SHIFT_EDIT',
            'date': target_date,
            'old': f"Shift {old_shift_id} ({result['old_shift_name']}) - {old_type}",
            'new': f"Shift {new_shift_id} ({shift['name']}) - work",
        })
    else:
        # INSERT jadwal baru
        result['action'] = 'INSERT'
        result['old_shift_id'] = None
        result['old_shift_name'] = None
        
        if not dry_run:
            run_query(f"""
                INSERT INTO users_shift_additional (user_id, shift_id, additional_date, additional_type, created_at)
                VALUES ({user_id}, {new_shift_id}, '{target_date}', 'work', NOW())
            """, fetch=False)
        
        result['changes'].append({
            'type': 'SHIFT_INSERT',
            'date': target_date,
            'old': 'Tidak ada jadwal',
            'new': f"Shift {new_shift_id} ({shift['name']}) - work",
        })
    
    # ══════════════════════════════════════════════════════════════════════════
    # STEP 2: RETROAKTIF - ISI JADWAL UNTUK TANGGAL YANG ADA KEHADIRAN TAPI TANPA JADWAL
    # (shift diinput telat → karyawan tidak punya jadwal)
    # ══════════════════════════════════════════════════════════════════════════
    
    retroactive_dates = []
    for pres in presence:
        pres_date = pres[3]
        if pres_date != target_date and pres_date not in schedule_dates:
            retroactive_dates.append(pres_date)
    
    for pres_date in retroactive_dates:
        if not dry_run:
            run_query(f"""
                INSERT INTO users_shift_additional (user_id, shift_id, additional_date, additional_type, created_at)
                VALUES ({user_id}, {new_shift_id}, '{pres_date}', 'work', NOW())
            """, fetch=False)
        
        result['changes'].append({
            'type': 'RETROACTIVE_INSERT',
            'date': pres_date,
            'old': 'Tidak ada jadwal (shift terlambat diinput)',
            'new': f"Shift {new_shift_id} ({shift['name']}) - work",
        })
    
    # ══════════════════════════════════════════════════════════════════════════
    # STEP 3: UPDATE DATA KEHADIRAN (entry_time_late)
    # ══════════════════════════════════════════════════════════════════════════
    
    if shift['start_time']:
        shift_start = shift['start_time']
        
        for pres in presence:
            pres_id = pres[0]
            entry_time = pres[1]
            pres_date = pres[3]
            old_late = pres[4] or 0
            
            if entry_time and str(entry_time).strip() not in ('NULL', 'None', ''):
                try:
                    entry_dt = datetime.strptime(str(entry_time), '%Y-%m-%d %H:%M:%S')
                except ValueError:
                    continue
                shift_start_dt = datetime.strptime(str(shift_start), '%H:%M:%S').time()
                shift_start_full = datetime.combine(entry_dt.date(), shift_start_dt)
                
                new_late = max(0, int((entry_dt - shift_start_full).total_seconds() / 60))
                
                if new_late != int(old_late):
                    if not dry_run:
                        run_query(f"""
                            UPDATE presence 
                            SET entry_time_late = {new_late}, updated_at = NOW()
                            WHERE id = {pres_id}
                        """, fetch=False)
                    
                    result['changes'].append({
                        'type': 'PRESENCE_UPDATE',
                        'date': pres_date,
                        'old': f"Terlambat: {old_late} menit",
                        'new': f"Terlambat: {new_late} menit",
                    })
    
    result['success'] = True
    return result

# ═══════════════════════════════════════════════════════════════════════════════
# MAIN
# ═══════════════════════════════════════════════════════════════════════════════

def main():
    parser = argparse.ArgumentParser(
        description='Edit Shift Retroaktif (1 Bulan / Masa Penggajian)',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Contoh:
  # Edit shift Bayu Putra ke PAGI (shift_id=49) untuk 10 Juni, dry-run
  python3 edit_shift.py --user-id 273 --date 2026-06-10 --shift-id 49 --dry-run
  
  # Edit shift (eksekusi)
  python3 edit_shift.py --user-id 273 --date 2026-06-10 --shift-id 49 --reason "shift diinput telat"

Shift ID:
  49 = PAGI NORMAL ALL DIVISI (07:50-18:00)
  50 = MIDDLE NORMAL ALL DIVISI (09:50-20:00)
  51 = SIANG NORMAL ALL DIVISI (11:50-22:00)
  53 = PAGI-KASIR/FINANCE (07:50-18:00)
  54 = SIANG-KASIR/FINANCE (12:20-22:30)
  56 = SECURITY MALAM (07:50-21:30)
  69 = NO SCHEDULE
  
Tabel: users_shift_additional (dibaca oleh website tiffany.my.id/absen/)
        """
    )
    
    parser.add_argument('--user-id', type=int, required=True, help='ID karyawan')
    parser.add_argument('--date', required=True, help='Target tanggal (YYYY-MM-DD)')
    parser.add_argument('--shift-id', type=int, required=True, help='ID shift baru')
    parser.add_argument('--reason', default='', help='Alasan edit shift')
    parser.add_argument('--dry-run', action='store_true', help='Simulasi tanpa eksekusi')
    
    args = parser.parse_args()
    
    print("🔗 Connecting to database...")
    print()
    
    print("=" * 70)
    print("📋 EDIT SHIFT RETROAKTIF (MASA PENGGAJIAN)")
    print("📌 Tabel: users_shift_additional (dibaca oleh website)")
    print("=" * 70)
    print(f"  👤 User ID    : {args.user_id}")
    print(f"  📅 Tanggal    : {args.date}")
    print(f"  🏢 Shift ID   : {args.shift_id}")
    print(f"  📝 Alasan     : {args.reason or '-'}")
    print(f"  🔄 Mode       : {'🧪 DRY RUN (simulasi)' if args.dry_run else '⚡ EKSEKUSI'}")
    print()
    
    result = edit_shift_retroaktif(
        user_id=args.user_id,
        target_date=args.date,
        new_shift_id=args.shift_id,
        reason=args.reason,
        dry_run=args.dry_run
    )
    
    print("─" * 70)
    
    if result['success']:
        mode_label = "[SIMULASI] " if args.dry_run else ""
        print(f"✅ {mode_label}EDIT SHIFT BERHASIL!")
        print()
        print(f"  👤 Karyawan  : {result['user_name']}")
        print(f"  📅 Tanggal   : {result['target_date']}")
        print(f"  🏢 Shift     : {result['new_shift_name']} (ID: {result['new_shift_id']})")
        print(f"  📆 Periode   : {result['period']}")
        print(f"  🔧 Aksi      : {result['action']}")
        
        if result.get('old_shift_name'):
            print(f"  ⬅️  Lama      : {result['old_shift_name']} (ID: {result['old_shift_id']})")
        
        if result.get('reason'):
            print(f"  📝 Alasan    : {result['reason']}")
        
        if result['changes']:
            print()
            print(f"  📋 PERUBAHAN ({len(result['changes'])} item):")
            for i, c in enumerate(result['changes'], 1):
                icon = {'SHIFT_EDIT': '✏️', 'SHIFT_INSERT': '➕', 'RETROACTIVE_INSERT': '🔄', 'PRESENCE_UPDATE': '⏰'}
                print(f"     {i}. {icon.get(c['type'], '•')} [{c['type']}] {c['date']}")
                print(f"        {c['old']} → {c['new']}")
        else:
            print()
            print("  ℹ️  Tidak ada perubahan diperlukan")
        
        print()
        print("  ✅ Data di tabel users_shift_additional sudah diupdate")
        print("  ✅ Website tiffany.my.id/absen/ akan menampilkan data baru")
    else:
        print("❌ EDIT SHIFT GAGAL!")
        for error in result['errors']:
            print(f"  ⚠️  {error}")
    
    print()
    print("=" * 70)

if __name__ == '__main__':
    main()

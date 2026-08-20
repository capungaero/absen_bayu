#!/usr/bin/env python3
"""
Absensi Sync Script for Hermes Agent
Downloads .dat from Solution Cloud, parses attendance logs,
upserts to MySQL presence table, deletes old .dat files.

Runs hourly via Hermes cron.

Uses paramiko SSH tunnel to connect to remote MySQL.
"""

import os
import sys
import requests
import pymysql
import paramiko
import re
from datetime import datetime, timedelta
from pathlib import Path
import socket
import threading
import time

# ═══════════════════════════════════════════════════════════════════════════════
# CONFIGURATION
# ═══════════════════════════════════════════════════════════════════════════════

# SSH tunnel config
SSH_CONFIG = {
    'ssh_host': 'tiffany.my.id',
    'ssh_port': 2223,
    'ssh_user': 'tifx3722',
    'ssh_key': os.path.expanduser('~/.ssh/tiffany_pipeline'),
    'ssh_passphrase': '',
}

# Database -- LOCAL VPS mysql (sejak 18 Agu 2026 VPS jadi produksi, sync tidak
# lagi lewat SSH tunnel ke tiffany.my.id; SSH_CONFIG di atas disimpan buat rollback).
DB_CONFIG = {
    'host': '127.0.0.1',
    'port': 3306,
    'user': 'absen_copy',
    'password': 'bec55489de1d91636660af70850284ff8512c733',
    'database': 'absen_copy',
    'charset': 'utf8mb4',
}

# Solution Cloud
SOLUTION_CLOUD_URL = 'http://solutioncloud.co.id/'

# Fallback machine list -- HANYA dipakai kalau query ke sync_machine gagal total
# (mis. koneksi DB belum siap sama sekali). Daftar ini TERBUKTI bisa nyasar dari
# konfigurasi asli di DB (id 3 sempat ke-hardcode type='attendance' padahal di DB
# type='pray' -- SN itu benar "Mesin Sholat Gambir", bukan mesin absensi. Akibatnya
# tap sholat ikut diklasifikasi sbg jam kerja & tercampur ke entry_time/rest_time_in/
# rest_time_out, lihat investigasi 17 Agu 2026). JANGAN dipakai sbg sumber utama lagi
# -- fetch_machines() di bawah ambil langsung dari tabel sync_machine tiap run supaya
# tidak pernah drift dari config asli.
MACHINES_FALLBACK = [
    {'id': 1, 'name': 'Mesin Absensi Gambir', 'sn': 'BWXP212160931', 'password': 'solution', 'type': 'attendance'},
    {'id': 2, 'name': 'Mesin Absensi Sudirman', 'sn': 'BWXP212161065', 'password': 'solution', 'type': 'attendance'},
    {'id': 3, 'name': 'Mesin Sholat Gambir', 'sn': '6339163400576', 'password': 'solution', 'type': 'pray'},
    {'id': 4, 'name': 'Mesin Sholat Sudirman', 'sn': 'BWXP212161070', 'password': 'solution', 'type': 'pray'},
    {'id': 5, 'name': 'Mesin Sholat Sudirman 2', 'sn': 'BWXP212161076', 'password': 'solution', 'type': 'pray'},
]


def fetch_machines(conn):
    """Ambil daftar mesin aktif langsung dari tabel sync_machine (sumber kebenaran
    tunggal, sama seperti sisi PHP Sync_model::get_active_by_type()). Ini yang
    dipakai main() -- MACHINES_FALLBACK di atas cadangan darurat saja."""
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id, name, machine_sn AS sn, password, machine_type AS type "
                "FROM sync_machine WHERE is_active = 1 ORDER BY id"
            )
            rows = cur.fetchall()
        if rows:
            return list(rows)
    except Exception as e:
        print(f"  ⚠️ Gagal ambil daftar mesin dari DB, pakai fallback: {e}")
    return MACHINES_FALLBACK

# Directory to store .dat files (will be created if not exists)
DAT_DIR = Path(__file__).parent / 'dat_files'
# Lock file to prevent multiple instances
LOCK_FILE = '/tmp/absen_sync.lock'

def acquire_lock():
    """Acquire lock to prevent multiple sync instances."""
    if os.path.exists(LOCK_FILE):
        try:
            with open(LOCK_FILE, 'r') as f:
                pid = int(f.read().strip())
            # Check if process is still running
            os.kill(pid, 0)
            print(f"\n⚠️ Another sync process running (PID: {pid})")
            return False
        except (ProcessLookupError, ValueError):
            # Process doesn't exist, remove stale lock
            os.remove(LOCK_FILE)
    
    # Create lock file with current PID
    with open(LOCK_FILE, 'w') as f:
        f.write(str(os.getpid()))
    return True

def release_lock():
    """Release lock file."""
    if os.path.exists(LOCK_FILE):
        os.remove(LOCK_FILE)


# ═══════════════════════════════════════════════════════════════════════════════
# SSH TUNNEL (using paramiko)
# ═══════════════════════════════════════════════════════════════════════════════

class SSHTunnel:
    """SSH tunnel using paramiko with keepalive."""
    
    def __init__(self, ssh_host, ssh_port, ssh_user, ssh_key, ssh_passphrase, remote_host, remote_port):
        self.ssh_host = ssh_host
        self.ssh_port = ssh_port
        self.ssh_user = ssh_user
        self.ssh_key = ssh_key
        self.ssh_passphrase = ssh_passphrase
        self.remote_host = remote_host
        self.remote_port = remote_port
        self.client = None
        self.local_port = None
        self.server = None
        self.thread = None
        
    def start(self):
        """Start SSH tunnel."""
        # Try loading key - support both passphrase-protected and unprotected keys
        try:
            key = paramiko.RSAKey.from_private_key_file(self.ssh_key, password=self.ssh_passphrase)
        except Exception:
            try:
                key = paramiko.Ed25519Key.from_private_key_file(self.ssh_key, password=self.ssh_passphrase)
            except Exception:
                try:
                    # Try without passphrase
                    key = paramiko.Ed25519Key.from_private_key_file(self.ssh_key)
                except Exception:
                    key = paramiko.RSAKey.from_private_key_file(self.ssh_key)
        
        self.client = paramiko.SSHClient()
        self.client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        self.client.connect(
            hostname=self.ssh_host,
            port=self.ssh_port,
            username=self.ssh_user,
            pkey=key,
        )
        
        # Enable SSH keepalive to prevent tunnel death during idle
        transport = self.client.get_transport()
        transport.set_keepalive(30)
        
        self.local_port = self._get_free_port()
        
        self.server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        self.server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        self.server.bind(('127.0.0.1', self.local_port))
        self.server.listen(5)
        
        self.thread = threading.Thread(target=self._forward, daemon=True)
        self.thread.start()
        
        return self.local_port
    
    def _get_free_port(self):
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            s.bind(('127.0.0.1', 0))
            return s.getsockname()[1]
    
    def _forward(self):
        """Accept and forward connections. Continues on errors."""
        while True:
            try:
                client_socket, addr = self.server.accept()
                channel = self.client.get_transport().open_channel(
                    'direct-tcpip',
                    (self.remote_host, self.remote_port),
                    client_socket.getpeername(),
                )
                threading.Thread(target=self._relay, args=(client_socket, channel), daemon=True).start()
            except Exception:
                time.sleep(0.1)
                continue
    
    def _relay(self, client_socket, channel):
        """Bidirectional relay."""
        import select
        while True:
            try:
                r, _, _ = select.select([client_socket, channel], [], [], 1)
                if client_socket in r:
                    data = client_socket.recv(8192)
                    if not data:
                        break
                    channel.send(data)
                if channel in r:
                    data = channel.recv(8192)
                    if not data:
                        break
                    client_socket.send(data)
            except Exception:
                break
        client_socket.close()
        channel.close()
    
    def stop(self):
        if self.server:
            self.server.close()
        if self.client:
            self.client.close()


# ═══════════════════════════════════════════════════════════════════════════════
# CORE FUNCTIONS
# ═══════════════════════════════════════════════════════════════════════════════

def cloud_download(sn, password):
    """Download .dat file from Solution Cloud.

    Returns:
        str: Raw content of .dat file, or None on failure.
    """
    session = requests.Session()
    session.headers.update({'User-Agent': 'Mozilla/5.0'})

    # Step 1: Login
    login_url = SOLUTION_CLOUD_URL + 'sc_pro.asp'
    try:
        resp = session.post(login_url, data={'sn': sn, 'pass': password}, timeout=30)
        resp.raise_for_status()
    except Exception as e:
        print(f"  ❌ Login failed for {sn}: {e}")
        return None

    # Step 2: Download .dat
    download_url = SOLUTION_CLOUD_URL + 'download.asp'
    try:
        resp = session.get(download_url, timeout=60)
        resp.raise_for_status()
    except Exception as e:
        print(f"  ❌ Download failed for {sn}: {e}")
        return None

    # Validate response
    raw = resp.text
    if '<html' in raw.lower() or '<!doctype' in raw.lower():
        print(f"  ❌ Got HTML response for {sn} (login failed?)")
        return None
    if '\t' not in raw:
        print(f"  ❌ Invalid .dat format for {sn} (no tab separator)")
        return None

    return raw


def parse_dat(raw):
    """Parse .dat content into list of (finger_id, timestamp) tuples.

    Format per line: finger_id\tdate\ttime
    Example: 1234\t2026-06-01\t09:15:00
    """
    logs = []
    for line in raw.strip().split('\n'):
        line = line.strip()
        if not line:
            continue
        parts = re.split(r'\s+', line)
        if len(parts) < 3:
            continue
        finger_id = parts[0].strip()
        date_str = parts[1].strip()
        time_str = parts[2].strip()

        try:
            timestamp = datetime.strptime(f"{date_str} {time_str}", "%Y-%m-%d %H:%M:%S")
        except ValueError:
            continue

        if not finger_id or not timestamp:
            continue

        logs.append((finger_id, timestamp, date_str, time_str))

    return logs


def get_payroll_period(ref_date=None):
    """Return (from_date, to_date) for the payroll period containing ref_date.

    Period M = START_PAYROLL_DATE of (M-1) to END_PAYROLL_DATE of M.
    Mirrors PHP: _current_period_start() and attlog_presence_period_range().
    """
    if ref_date is None:
        today = datetime.now()
    elif isinstance(ref_date, str):
        today = datetime.strptime(ref_date, '%Y-%m-%d')
    else:
        today = ref_date

    if today.day >= START_PAYROLL_DATE:
        period_from = today.replace(day=START_PAYROLL_DATE)
        m2 = today.month + 1
        y2 = today.year
        if m2 > 12:
            m2 = 1
            y2 += 1
        period_to = datetime(y2, m2, END_PAYROLL_DATE)
    else:
        m1 = today.month - 1
        y1 = today.year
        if m1 < 1:
            m1 = 12
            y1 -= 1
        period_from = datetime(y1, m1, START_PAYROLL_DATE)
        period_to = today.replace(day=END_PAYROLL_DATE)

    return period_from.strftime('%Y-%m-%d'), period_to.strftime('%Y-%m-%d')


def get_locked_branches(conn, month, year):
    """Set branch_id yang payroll periode (month, year)-nya SUDAH dikunci.

    Baris di tabel payroll = periode terkunci (paralel _payroll_locked di PHP).
    Sync WAJIB melewati cabang terkunci agar rekap yang sudah digaji tidak
    tersentuh — sebelumnya cek ini hanya ada di jalur PHP (2026-07-08).
    """
    with conn.cursor() as cur:
        cur.execute("SELECT branch_id FROM payroll WHERE month = %s AND year = %s",
                    (int(month), int(year)))
        return {int(r['branch_id']) for r in cur.fetchall()}


def period_month_year(period_to):
    """'YYYY-MM-DD' (tanggal 25 akhir periode) -> (month, year) periode payroll."""
    return int(period_to[5:7]), int(period_to[0:4])


def recalc_period_lateness(conn, period_from, period_to, locked_branches=None):
    """Recalculate entry_time_late and rest_time_late for all normal presence
    records in [period_from, period_to] based on each record's current shift.

    Called after every attendance sync pass so that shift changes made after
    the initial record was saved are reflected in the lateness figures.
    Returns the count of updated rows.
    """
    query = """
        SELECT p.id, p.user_id, p.flow_date,
               p.entry_time, p.rest_time_in, p.rest_time_out,
               p.entry_time_late, p.rest_time_late, po.branch_id
        FROM presence p
        JOIN users u ON u.id = p.user_id
        JOIN position po ON po.id = u.position_id
        WHERE p.presence_type = 'normal'
          AND p.flow_date >= %s AND p.flow_date <= %s
    """
    shift_query = """
        SELECT s.*
        FROM users_shift_additional usa
        JOIN shift s ON s.id = usa.shift_id
        WHERE usa.user_id = %s AND usa.additional_date = %s
          AND usa.additional_type = 'work'
          AND usa.id = (
              SELECT MAX(usa2.id) FROM users_shift_additional usa2
              WHERE usa2.user_id = usa.user_id
                AND usa2.additional_date = usa.additional_date
          )
        LIMIT 1
    """

    def to_time_str(val):
        if val is None:
            return None
        if isinstance(val, timedelta):
            total = int(val.total_seconds())
            return f"{total // 3600:02d}:{(total % 3600) // 60:02d}:{total % 60:02d}"
        if isinstance(val, datetime):
            return val.strftime('%H:%M:%S')
        s = str(val)
        if ' ' in s:
            return s.split(' ')[1]
        return s

    with conn.cursor() as cur:
        cur.execute(query, (period_from, period_to))
        rows = cur.fetchall()

    updated = 0
    for row in rows:
        # Cabang terkunci payroll: jangan sentuh (2026-07-08)
        if locked_branches and int(row.get('branch_id') or 0) in locked_branches:
            continue
        with conn.cursor() as cur:
            cur.execute(shift_query, (row['user_id'], row['flow_date']))
            shift = cur.fetchone()
        if not shift:
            continue

        recalc = {}

        entry_t = to_time_str(row['entry_time'])
        new_entry_late = 0
        if entry_t:
            new_entry_late = minutes_between(to_time_str(shift.get('start_time_late')), entry_t)
        if int(row['entry_time_late'] or 0) != new_entry_late:
            recalc['entry_time_late'] = new_entry_late

        new_rest_late = 0
        rest_in_t = to_time_str(row['rest_time_in'])
        rest_out_t = to_time_str(row['rest_time_out'])
        end_rest = to_time_str(shift.get('end_time_rest'))
        rest_range = int(shift.get('rest_time_range') or 0)
        if rest_in_t and rest_out_t and rest_range and end_rest:
            limit_dt = (datetime.strptime('2000-01-01 ' + rest_in_t, '%Y-%m-%d %H:%M:%S')
                        + timedelta(minutes=rest_range))
            limit_t = limit_dt.strftime('%H:%M:%S')
            if rest_out_t <= end_rest:
                new_rest_late = minutes_between(limit_t, rest_out_t)
        if int(row['rest_time_late'] or 0) != new_rest_late:
            recalc['rest_time_late'] = new_rest_late

        if recalc:
            set_clause = ', '.join(f"{k} = %s" for k in recalc)
            with conn.cursor() as cur:
                cur.execute(f"UPDATE presence SET {set_clause} WHERE id = %s",
                            list(recalc.values()) + [row['id']])
            conn.commit()
            updated += 1

    return updated


def get_employee_map(conn, finger_ids, date, tunnel_port=None, db_config=None):
    """Map finger_id to user_id using employee_code.

    Fetches ALL active employees (small table) then filters in Python.
    Auto-reconnects if connection drops.
    Returns dict: employee_code -> {'user_id': int, ...}
    """
    if not finger_ids:
        return {}

    query = """
        SELECT u.id as user_id, u.employee_code, u.first_name, u.last_name,
               u.jenis_kelamin, po.branch_id
        FROM users u
        JOIN position po ON po.id = u.position_id
        WHERE u.active = 1
    """

    for attempt in range(3):
        try:
            with conn.cursor() as cur:
                cur.execute(query)
                rows = cur.fetchall()
            wanted = set(str(fid) for fid in finger_ids)
            return {row['employee_code']: row for row in rows if row['employee_code'] in wanted}
        except Exception as e:
            if attempt < 2 and tunnel_port:
                print(f"  ⚠️ DB query failed (attempt {attempt+1}), reconnecting: {e}")
                try:
                    conn.close()
                except Exception:
                    pass
                # Reconnect - caller must use returned conn
                conn = pymysql.connect(
                    host='127.0.0.1', port=tunnel_port,
                    user=db_config['user'], password=db_config['password'],
                    database=db_config['database'], charset=db_config['charset'],
                    cursorclass=pymysql.cursors.DictCursor,
                    read_timeout=60, write_timeout=60, connect_timeout=30,
                    # Kartu identitas sync: tanpa ini, trigger presence_provenance_*
                    # menandai semua tulisan sebagai edit manual.
                    init_command="SET @absen_sync_ctx = 1",
                )
                continue
            raise
    return {}


# ═══════════════════════════════════════════════════════════════════════════════
# PRAYER TIME CONFIG (sholat machine sync)
# ═══════════════════════════════════════════════════════════════════════════════

# Location: Payakumbuh, West Sumatra
PAYAKUMBUH_LAT = -0.2128
PAYAKUMBUH_LNG = 100.5975
ALADHAN_API_URL = "https://api.aladhan.com/v1/timings/{date}?latitude={lat}&longitude={lng}&method=11"

# Payroll period constants (same as PHP constants.php)
START_PAYROLL_DATE = 26
END_PAYROLL_DATE = 25

# Prayer break rule: max 15 minutes
PRAYER_MAX_MINUTES = 15
# Batas wajar tap KELUAR susulan di luar window klasifikasi (menit sejak tap
# MASUK). Cuma menentukan apakah tap dicatat sbg keluar -- keterlambatan
# tetap dihitung normal dari PRAYER_MAX_MINUTES/FRIDAY_MAX_MINUTES di bawah,
# jadi kalau kelamaan tetap kelihatan telat, bukan disembunyikan.
PRAYER_CLOSE_MAX_MINUTES = 180

# Friday prayer rules (khusus laki-laki)
# Window: -15 menit s/d +60 menit dari waktu dzuhur
# Toleransi: 70 menit (bukan 15 menit)
FRIDAY_MIN_BEFORE = 15    # menit sebelum waktu sholat
FRIDAY_MIN_AFTER = 60     # menit setelah waktu sholat
FRIDAY_MAX_MINUTES = 70   # toleransi jeda in-out

# Fallback monthly average prayer times for Payakumbuh (WIB)
# Used when API is unavailable. Format: {month: {prayer: 'HH:MM'}}
MONTHLY_PRAYER_AVERAGES = {
    # month_num: {prayer_name: start_time_str}
    1:  {'subuh':'04:50','dzuhur':'12:15','ashar':'15:30','maghrib':'18:20','isha':'19:35'},
    2:  {'subuh':'04:48','dzuhur':'12:15','ashar':'15:35','maghrib':'18:20','isha':'19:35'},
    3:  {'subuh':'04:45','dzuhur':'12:15','ashar':'15:35','maghrib':'18:18','isha':'19:33'},
    4:  {'subuh':'04:42','dzuhur':'12:15','ashar':'15:35','maghrib':'18:15','isha':'19:30'},
    5:  {'subuh':'04:40','dzuhur':'12:15','ashar':'15:35','maghrib':'18:12','isha':'19:28'},
    6:  {'subuh':'04:43','dzuhur':'12:18','ashar':'15:38','maghrib':'18:15','isha':'19:30'},
    7:  {'subuh':'04:45','dzuhur':'12:18','ashar':'15:40','maghrib':'18:18','isha':'19:33'},
    8:  {'subuh':'04:45','dzuhur':'12:18','ashar':'15:38','maghrib':'18:20','isha':'19:33'},
    9:  {'subuh':'04:43','dzuhur':'12:15','ashar':'15:35','maghrib':'18:18','isha':'19:30'},
    10: {'subuh':'04:40','dzuhur':'12:15','ashar':'15:30','maghrib':'18:15','isha':'19:28'},
    11: {'subuh':'04:42','dzuhur':'12:15','ashar':'15:28','maghrib':'18:15','isha':'19:28'},
    12: {'subuh':'04:47','dzuhur':'12:15','ashar':'15:30','maghrib':'18:18','isha':'19:32'},
}

# Map Aladhan API prayer names to our internal names
ALADHAN_TO_PRAYER = {
    'Fajr': 'subuh',
    'Dhuhr': 'dzuhur',
    'Asr': 'ashar',
    'Maghrib': 'maghrib',
    'Isha': 'isha',
}

# Cache for prayer times (avoid repeated API calls)
_prayer_times_cache = {}

# Window sholat per-cabang dari tabel branch (sinkron dgn PHP _import_pray_sheet,
# 20 Agu 2026). Kalau terisi, classify_prayer_scan pakai ini; Aladhan API jadi
# fallback saja. Menghilangkan inkonsistensi klasifikasi PHP vs Python (dulu
# tap sama bisa beda kolom tergantung jalur sync mana yang jalan terakhir).
_branch_windows = None

def load_branch_prayer_windows(conn):
    """Muat window sholat cabang aktif pertama. Return dict atau None."""
    global _branch_windows
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT * FROM branch WHERE is_active=1 ORDER BY id LIMIT 1")
            b = cur.fetchone()
        if not b:
            _branch_windows = None
            return None
        def t(v):
            if v is None:
                return None
            s = str(v)
            if len(s.split(':')) == 2:
                s += ':00'
            return s
        wins = {}
        for name in ('subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'):
            start = t(b.get(name + '_pray_time_in'))
            end = t(b.get(name + '_pray_time_out'))
            rng = b.get(name + '_pray_time_range')
            if start and end:
                wins[name] = {'start': start, 'end': end,
                              'range': int(rng) if rng else None}
        _branch_windows = wins or None
        if _branch_windows:
            print(f"  \U0001F54C Window sholat dari config cabang id {b.get('id')} ({len(wins)} window)")
        return _branch_windows
    except Exception as e:
        print(f"  \u26A0\uFE0F Gagal muat window cabang, fallback Aladhan: {e}")
        _branch_windows = None
        return None


def fetch_prayer_times(date_str=None):
    """Fetch prayer times for Payakumbuh from Aladhan API.
    
    Falls back to monthly averages if API fails.
    
    Args:
        date_str: 'YYYY-MM-DD' format. Defaults to today.
    
    Returns:
        dict: {prayer_name: {'start': 'HH:MM', 'end': 'HH:MM+15min'}}
    """
    global _prayer_times_cache
    
    if date_str is None:
        date_str = datetime.now().strftime('%Y-%m-%d')
    
    # Check cache
    if date_str in _prayer_times_cache:
        return _prayer_times_cache[date_str]
    
    dt = datetime.strptime(date_str, '%Y-%m-%d')
    month = dt.month
    
    # Try API first
    api_date = dt.strftime('%d-%m-%Y')
    api_url = ALADHAN_API_URL.format(date=api_date, lat=PAYAKUMBUH_LAT, lng=PAYAKUMBUH_LNG)
    
    prayer_times = {}
    api_ok = False
    
    try:
        import requests
        resp = requests.get(api_url, timeout=10)
        resp.raise_for_status()
        data = resp.json()
        
        if data.get('code') == 200:
            timings = data['data']['timings']
            for api_name, prayer_name in ALADHAN_TO_PRAYER.items():
                time_str = timings.get(api_name, '')
                if time_str:
                    # Clean timezone suffix like "+07:00" or " (WIB)"
                    clean = re.sub(r'\s*\(.*?\)', '', time_str).strip()
                    clean = re.sub(r'[+-]\d{2}:\d{2}$', '', clean).strip()
                    prayer_times[prayer_name] = clean
            api_ok = bool(prayer_times)
    except Exception as e:
        print(f"  ⚠️ API prayer times failed: {e}")
    
    if not api_ok:
        print(f"  📋 Using monthly average fallback for month {month}")
        fallback = MONTHLY_PRAYER_AVERAGES.get(month, MONTHLY_PRAYER_AVERAGES[6])
        prayer_times = dict(fallback)
    
    # Build classification windows: prayer_time ± 1.5 jam for grouping
    windows = []
    for name, start_time in prayer_times.items():
        start_dt = datetime.strptime(start_time, '%H:%M')
        # Wide window: from 30 min before prayer to 1.5 jam after
        cls_start = (start_dt - timedelta(minutes=30)).strftime('%H:%M')
        cls_end = (start_dt + timedelta(minutes=90)).strftime('%H:%M')
        windows.append({
            'name': name,
            'start': cls_start,
            'end': cls_end,
            'prayer_time': start_time,
        })
    
    # Add jumat (Friday-specific window for male employees)
    dzuhur_w = next((w for w in windows if w['name'] == 'dzuhur'), None)
    if dzuhur_w:
        dzuhur_dt = datetime.strptime(dzuhur_w['prayer_time'], '%H:%M')
        fri_start = (dzuhur_dt - timedelta(minutes=FRIDAY_MIN_BEFORE)).strftime('%H:%M')
        fri_end = (dzuhur_dt + timedelta(minutes=FRIDAY_MIN_AFTER)).strftime('%H:%M')
        windows.append({
            'name': 'jumat',
            'start': fri_start,
            'end': fri_end,
            'prayer_time': dzuhur_w['prayer_time'],
        })
    
    # Sort by start time
    windows.sort(key=lambda w: w['start'])
    
    _prayer_times_cache[date_str] = windows
    return windows


def classify_prayer_scan(time_str, date_str):
    """Classify a fingerprint scan to a prayer time window.
    
    Uses dynamic prayer times from API/monthly averages.
    Returns prayer name (subuh/dzuhur/ashar/maghrib/isha/jumat) or None.
    
    On Friday: jumat window checked FIRST, then dzuhur for remaining.
    """
    def parse_t(val):
        return datetime.strptime(val, "%H:%M:%S").time()
    
    t = parse_t(time_str)
    dt = datetime.strptime(date_str, "%Y-%m-%d")
    is_friday = dt.weekday() == 4

    # Window cabang (kalau dimuat) menang atas Aladhan -- urutan cek sama
    # dgn PHP: friday duluan di hari Jumat, sisanya urut waktu.
    if _branch_windows:
        if is_friday and 'friday' in _branch_windows:
            w = _branch_windows['friday']
            if w['start'] <= time_str <= w['end']:
                return 'jumat'
        for name in ('subuh', 'dzuhur', 'ashar', 'maghrib', 'isha'):
            if is_friday and name == 'dzuhur':
                continue
            w = _branch_windows.get(name)
            if w and w['start'] <= time_str <= w['end']:
                return name
        return None

    windows = fetch_prayer_times(date_str)
    
    # On Friday: check jumat window FIRST (narrower, male-specific)
    if is_friday:
        jumat_w = next((w for w in windows if w['name'] == 'jumat'), None)
        if jumat_w:
            s = parse_t(jumat_w['start'] + ':00')
            e = parse_t(jumat_w['end'] + ':00')
            if s <= t <= e:
                return 'jumat'
    
    # Check all windows normally
    for pw in windows:
        if pw['name'] == 'jumat':
            continue  # Already handled above
        s = parse_t(pw['start'] + ':00')
        e = parse_t(pw['end'] + ':00')
        if s <= t <= e:
            return pw['name']
    return None





def get_today_shift(conn, user_id, date):
    """Get shift schedule for user on given date."""
    query = """
        SELECT usa.*, s.*
        FROM users_shift_additional usa
        JOIN shift s ON s.id = usa.shift_id
        WHERE usa.user_id = %s 
          AND usa.additional_date = %s 
          AND usa.additional_type = 'work'
        ORDER BY usa.id DESC
        LIMIT 1
    """
    
    with conn.cursor() as cur:
        cur.execute(query, (user_id, date))
        return cur.fetchone()


def time_between(time_str, start, end):
    """Check if time is between start and end (handles midnight crossing)."""
    if not start or not end:
        return False
    
    # Handle timedelta objects from MySQL
    def parse_time(val):
        if isinstance(val, timedelta):
            total_seconds = int(val.total_seconds())
            hours = total_seconds // 3600
            minutes = (total_seconds % 3600) // 60
            seconds = total_seconds % 60
            return datetime.strptime(f"{hours:02d}:{minutes:02d}:{seconds:02d}", "%H:%M:%S").time()
        return datetime.strptime(str(val), "%H:%M:%S").time()
    
    t = parse_time(time_str)
    s = parse_time(start)
    e = parse_time(end)
    
    if s <= e:
        return s <= t <= e
    else:  # Crosses midnight
        return t >= s or t <= e


def minutes_between(from_time, to_time):
    """Selisih menit antara from_time (limit) dan to_time, MEMOTONG DETIK.

    Mirror persis PHP helper late_minutes(): kedua waktu dibulatkan turun ke
    HH:MM (detik dibuang) sebelum diselisihkan. Penting agar perhitungan
    keterlambatan Python identik dengan PHP — kalau tidak, 2 jalur sync akan
    saling membalik nilai rest_time_late ±1 menit (limit istirahat mengandung
    detik dari rest_time_in). Return 0 bila salah satu kosong atau to <= from.
    """
    if not from_time or not to_time:
        return 0

    def to_hm(val):
        if isinstance(val, timedelta):
            total = int(val.total_seconds())
            return total // 3600, (total % 3600) // 60
        s = str(val)
        if ' ' in s:  # 'YYYY-mm-dd HH:MM:SS'
            s = s.split(' ')[1]
        parts = s.split(':')
        return int(parts[0]), int(parts[1])

    fh, fm = to_hm(from_time)
    th, tm = to_hm(to_time)
    return max(0, (th * 60 + tm) - (fh * 60 + fm))


def upsert_presence(conn, payload):
    """Insert or update presence record.

    Returns: 'inserted', 'updated', or 'skipped'

    CHANGE (2026-06-14): latest scan wins (overwrite) — mengatasi record NULL
    lama memblokir update setelah jadwal telat di-assign.
    CHANGE (2026-07-08): PROVENANCE-AWARE (keputusan user, konsisten dgn PHP
    hr/Presence::_import_presence_sheet):
    - Baris input_by='system' (tulisan sync/mesin)  -> latest scan wins,
      supaya sync ulang setelah jadwal telat tetap mereklasifikasi.
    - Baris input_by='manual' (pernah diedit manusia) -> HANYA mengisi kolom
      kosong; koreksi manual tidak pernah tertimpa mesin.
    """
    query_check = """
        SELECT id, entry_time, out_time, rest_time_in, rest_time_out,
               entry_time_late, rest_time_late, input_by, cleared_fields
        FROM presence
        WHERE user_id = %s AND flow_date = %s
    """

    with conn.cursor() as cur:
        cur.execute(query_check, (payload['user_id'], payload['flow_date']))
        existing = cur.fetchone()

        if not existing:
            # Insert new
            cols = ', '.join(payload.keys())
            placeholders = ', '.join(['%s'] * len(payload))
            sql = f"INSERT INTO presence ({cols}) VALUES ({placeholders})"
            cur.execute(sql, list(payload.values()))
            conn.commit()
            return 'inserted'

        is_manual = str(existing.get('input_by') or '') not in ('system', 'machine', '')

        update = {'updated_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S')}

        if is_manual:
            # Fill-empty only — mirror presence_merge_preserve_existing (PHP).
            # cleared_fields = kolom yang SENGAJA dikosongkan manual (dirawat
            # trigger presence_provenance_bu) -> dilarang diisi ulang.
            cleared = {f.strip() for f in str(existing.get('cleared_fields') or '').split(',') if f.strip()}
            for field in ['entry_time', 'out_time', 'rest_time_in', 'rest_time_out']:
                if field in cleared:
                    continue
                if not existing.get(field) and payload.get(field):
                    update[field] = payload[field]
            # Late HANYA diisi bila kolom jam sumbernya juga baru terisi oleh
            # import ini. late=0 adalah nilai sah (tidak telat), bukan "belum
            # dihitung" — dulu dianggap kosong sehingga re-sync membalikkan
            # koreksi manual jadi telat lagi (bug empty(0), paritas fix PHP
            # presence_helper.php Jul 2026).
            if not existing.get('entry_time') and 'entry_time' not in cleared \
                    and payload.get('entry_time_late'):
                update['entry_time_late'] = payload['entry_time_late']
            if not existing.get('rest_time_out') and 'rest_time_out' not in cleared \
                    and payload.get('rest_time_late'):
                update['rest_time_late'] = payload['rest_time_late']
        else:
            # Latest scan wins (perilaku 2026-06-14) untuk baris tulisan mesin
            for field in ['entry_time', 'out_time', 'rest_time_in', 'rest_time_out']:
                if payload.get(field):
                    update[field] = payload[field]
            for field in ['entry_time_late', 'rest_time_late']:
                if payload.get(field) and payload[field] > 0:
                    update[field] = payload[field]
                elif payload.get(field) is not None and (not existing.get(field) or existing.get(field) == 0):
                    update[field] = payload[field]

        if len(update) == 1:  # Only updated_at
            return 'skipped'

        set_clause = ', '.join([f"{k} = %s" for k in update.keys()])
        sql = f"UPDATE presence SET {set_clause} WHERE id = %s"
        cur.execute(sql, list(update.values()) + [existing['id']])
        conn.commit()
        return 'updated'


def save_dat_file(sn, raw, date):
    """Save .dat file to disk."""
    DAT_DIR.mkdir(parents=True, exist_ok=True)
    filename = f"attlog_{sn}_{date}.dat"
    filepath = DAT_DIR / filename
    
    with open(filepath, 'w') as f:
        f.write(raw)
    
    return filepath


def cleanup_old_dats(sn, current_date):
    """Delete old .dat files for this machine (keep only today's)."""
    if not DAT_DIR.exists():
        return 0
    
    pattern = f"attlog_{sn}_*.dat"
    deleted = 0
    
    for f in DAT_DIR.glob(pattern):
        if current_date not in f.name:
            f.unlink()
            deleted += 1
    
    return deleted


def log_sync(conn, machine_id, machine_name, status, records, message):
    """Insert sync log to database."""
    query = """
        INSERT INTO sync_log (machine_id, machine_name, status, records, message, created_at)
        VALUES (%s, %s, %s, %s, %s, %s)
    """
    
    with conn.cursor() as cur:
        cur.execute(query, (machine_id, machine_name, status, records, message, datetime.now()))
        conn.commit()


def update_machine_status(conn, machine_id, status):
    """Update machine last_sync_at and last_sync_status."""
    query = """
        UPDATE sync_machine 
        SET last_sync_at = %s, last_sync_status = %s 
        WHERE id = %s
    """
    
    with conn.cursor() as cur:
        cur.execute(query, (datetime.now(), status, machine_id))
        conn.commit()


# ═══════════════════════════════════════════════════════════════════════════════
# MAIN SYNC LOGIC
# ═══════════════════════════════════════════════════════════════════════════════

def get_db_connection(port):
    """Create a DB connection via the SSH tunnel."""
    return pymysql.connect(
        host='127.0.0.1',
        port=port,
        user=DB_CONFIG['user'],
        password=DB_CONFIG['password'],
        database=DB_CONFIG['database'],
        charset=DB_CONFIG['charset'],
        cursorclass=pymysql.cursors.DictCursor,
        read_timeout=60,
        write_timeout=60,
        connect_timeout=30,
        # Kartu identitas sync: tanpa ini, trigger presence_provenance_*
        # menandai semua tulisan sebagai edit manual.
        init_command="SET @absen_sync_ctx = 1",
    )

# ═══════════════════════════════════════════════════════════════════════════════
# PRAYER MACHINE SYNC (separate from work attendance)
# ═══════════════════════════════════════════════════════════════════════════════

def sync_pray_machine(machine, sync_date=None, tunnel_port=None):
    """Sync prayer attendance machine to presence table prayer fields ONLY.
    
    Does NOT touch work attendance data (entry_time, out_time, rest_time, etc.).
    """
    machine_id = machine['id']
    machine_name = machine['name']
    sn = machine['sn']
    password = machine['password']
    
    print(f"\n🕌 Syncing {machine_name} (SN: {sn}) [PRAYER MODE]...")
    
    # Download .dat FIRST (may take long)
    raw = cloud_download(sn, password)
    if raw is None:
        msg = "Gagal download dari Solution Cloud."
        try:
            conn = get_db_connection(tunnel_port)
            log_sync(conn, machine_id, machine_name, 'failed', 0, msg)
            update_machine_status(conn, machine_id, 'failed')
            conn.close()
        except Exception:
            print(f"  ⚠️ Cannot log to DB")
        return {'success': False, 'records': 0, 'message': msg}
    
    # Parse .dat
    logs = parse_dat(raw)
    print(f"  📄 Parsed {len(logs)} records")
    
    if not logs:
        msg = "File .dat kosong."
        try:
            conn = get_db_connection(tunnel_port)
            log_sync(conn, machine_id, machine_name, 'failed', 0, msg)
            update_machine_status(conn, machine_id, 'failed')
            conn.close()
        except Exception:
            print(f"  ⚠️ Cannot log to DB")
        return {'success': False, 'records': 0, 'message': msg}
    
    # Save .dat
    today = sync_date or datetime.now().strftime('%Y-%m-%d')
    save_dat_file(sn, raw, today)
    
    # NOW create DB connection (after download)
    conn = get_db_connection(tunnel_port)
    
    # Compute payroll period — process full month, not just today
    period_from, period_to = get_payroll_period(today)
    cutoff = min(today, period_to)

    # Window sholat per-cabang (paritas PHP) -- dimuat sekali per run.
    load_branch_prayer_windows(conn)

    # Group logs by (finger_id, date) within the period
    logs_by_key = {}
    for finger_id, timestamp, date_str, time_str in logs:
        if date_str < period_from or date_str > cutoff:
            continue
        key = (finger_id, date_str)
        if key not in logs_by_key:
            logs_by_key[key] = []
        logs_by_key[key].append((timestamp, date_str, time_str))

    finger_ids = list(set(k[0] for k in logs_by_key))
    employee_map = get_employee_map(conn, finger_ids, today)
    print(f"  👥 Matched {len(employee_map)}/{len(finger_ids)} employees")

    # First pass: collect prayer in/out per (user_id, date, col_prefix).
    # Latest scan wins — overwrite any previously stored value.
    # {(user_id, date_str, col_prefix): {'in': time_str, 'out': time_str|None, 'late': int}}
    prayer_results = {}
    stats = {'processed': 0, 'no_match': 0, 'missing': 0}

    for (finger_id, date_str), day_entries in logs_by_key.items():
        if finger_id not in employee_map:
            stats['missing'] += 1
            continue

        user_id = employee_map[finger_id]['user_id']
        jenis_kelamin = employee_map[finger_id].get('jenis_kelamin')
        day_entries.sort(key=lambda x: x[0])

        # Collect first/second scan per prayer for this day.
        # FIX 2026-08-19: window klasifikasi (classify_prayer_scan) cuma
        # dipakai buat nentuin tap MASUK. Tap KELUAR yang lewat window tipis
        # dulu DIBUANG TOTAL (masuk stats no_match) padahal mestinya tetap
        # dicatat + dihitung telat -- itu justru fungsi kolom *_time_late.
        # Sekarang: kalau tap gak cocok window manapun TAPI ada sholat yang
        # barusan dibuka (in ada, out belum) dalam batas wajar
        # (PRAYER_CLOSE_MAX_MINUTES), tap ini dicatat sebagai keluarnya.
        day_prayers = {}  # {col_prefix: {'in': ts, 'out': ts|None, 'max_min': int}}
        open_col = None
        open_in_ts = None
        for timestamp, ds, time_str in day_entries:
            prayer = classify_prayer_scan(time_str, date_str)

            if prayer:
                if prayer == 'jumat':
                    col = 'friday' if jenis_kelamin != 'P' else 'dzuhur'
                    max_min = FRIDAY_MAX_MINUTES if col == 'friday' else PRAYER_MAX_MINUTES
                else:
                    col = prayer
                    max_min = PRAYER_MAX_MINUTES
                # Range per-cabang menang atas konstanta (paritas PHP).
                if _branch_windows and _branch_windows.get(col, {}).get('range'):
                    max_min = _branch_windows[col]['range']

                if col not in day_prayers:
                    day_prayers[col] = {'in': None, 'out': None, 'max_min': max_min}
                if day_prayers[col]['in'] is None:
                    day_prayers[col]['in'] = time_str
                    open_col = col
                    open_in_ts = time_str
                elif day_prayers[col]['out'] is None:
                    day_prayers[col]['out'] = time_str
                    if open_col == col:
                        open_col = None
                continue

            if (open_col is not None and day_prayers[open_col]['out'] is None):
                in_dt = datetime.strptime(f"{date_str} {open_in_ts}", '%Y-%m-%d %H:%M:%S')
                cur_dt = datetime.strptime(f"{date_str} {time_str}", '%Y-%m-%d %H:%M:%S')
                elapsed_min = (cur_dt - in_dt).total_seconds() / 60
                if 0 <= elapsed_min <= PRAYER_CLOSE_MAX_MINUTES:
                    day_prayers[open_col]['out'] = time_str
                    open_col = None
                    continue

            stats['no_match'] += 1

        for col, scans in day_prayers.items():
            if not scans['in']:
                continue
            late = 0
            if scans['out']:
                try:
                    in_dt = datetime.strptime(f"{date_str} {scans['in']}", '%Y-%m-%d %H:%M:%S')
                    out_dt = datetime.strptime(f"{date_str} {scans['out']}", '%Y-%m-%d %H:%M:%S')
                    dur = (out_dt - in_dt).total_seconds() / 60
                    if dur > scans['max_min']:
                        late = int(dur - scans['max_min'])
                except Exception:
                    pass
            prayer_results[(user_id, date_str, col)] = {
                'in': f"{date_str} {scans['in']}",
                'out': f"{date_str} {scans['out']}" if scans['out'] else None,
                'late': late,
                'max_min': scans['max_min'],
            }

    # Second pass: upsert prayer data — PROVENANCE-AWARE (2026-07-08):
    # - Baris 'system'  -> latest scan wins (perilaku lama).
    # - Baris 'manual'  -> hanya isi bila kolom in sholat itu masih kosong.
    # Plus: skip cabang yang payroll periode ini sudah terkunci.
    pm, py = period_month_year(period_to)
    locked_branches = get_locked_branches(conn, pm, py)
    if locked_branches:
        print(f"  🔒 Cabang terkunci payroll {pm}/{py}: {sorted(locked_branches)} — dilewati")
    user_branch = {e['user_id']: int(e.get('branch_id') or 0) for e in employee_map.values()}

    updated_prayers = 0
    skipped_no_presence = 0
    skipped_locked = 0
    skipped_manual = 0

    for (user_id, date_str, col), data in prayer_results.items():
        if user_branch.get(user_id, 0) in locked_branches:
            skipped_locked += 1
            continue

        field_in = f"{col}_time_in"
        field_out = f"{col}_time_out"
        field_late = f"{col}_time_late"

        with conn.cursor() as cur:
            cur.execute(f"SELECT id, input_by, cleared_fields, {field_in} as cur_in, {field_out} as cur_out "
                        "FROM presence WHERE user_id = %s AND flow_date = %s",
                        (user_id, date_str))
            existing = cur.fetchone()

        if not existing:
            skipped_no_presence += 1
            continue

        # Sholat yang SENGAJA dikosongkan manual (cleared_fields, dirawat trigger
        # presence_provenance_bu) tidak boleh diisi ulang sync -- sama dgn PHP
        # _import_pray_sheet. Dicek PER FIELD (in/out terpisah) -- sebelumnya
        # satu field terisi manual mengunci seluruh baris walau field lain masih
        # kosong (lihat investigasi 18 Agu 2026: 13 baris dzuhur kehilangan
        # time_out selamanya krn field in-nya kepasang duluan).
        cleared = {f.strip() for f in str(existing.get('cleared_fields') or '').split(',') if f.strip()}
        is_manual = str(existing.get('input_by') or '') not in ('system', 'machine', '')
        lock_in = (field_in in cleared) or (is_manual and existing.get('cur_in'))
        lock_out = (field_out in cleared) or (is_manual and existing.get('cur_out'))

        if lock_in and lock_out:
            skipped_manual += 1
            continue

        set_parts = []
        params = []
        if not lock_in:
            set_parts.append(f"{field_in} = %s")
            params.append(data['in'])
        if not lock_out:
            set_parts.append(f"{field_out} = %s")
            params.append(data['out'])

        final_in = data['in'] if not lock_in else existing.get('cur_in')
        final_out = data['out'] if not lock_out else existing.get('cur_out')
        late = 0
        if final_in and final_out:
            try:
                in_dt = final_in if isinstance(final_in, datetime) else datetime.strptime(str(final_in), '%Y-%m-%d %H:%M:%S')
                out_dt = final_out if isinstance(final_out, datetime) else datetime.strptime(str(final_out), '%Y-%m-%d %H:%M:%S')
                dur = (out_dt - in_dt).total_seconds() / 60
                if dur > data['max_min']:
                    late = int(dur - data['max_min'])
            except Exception:
                pass
        set_parts.append(f"{field_late} = %s")
        params.append(late)
        set_parts.append("updated_at = %s")
        params.append(datetime.now())
        params.append(existing['id'])

        with conn.cursor() as cur:
            cur.execute(f"UPDATE presence SET {', '.join(set_parts)} WHERE id = %s", params)
        conn.commit()
        updated_prayers += 1
        stats['processed'] += 1

    # Log sync
    msg = (f"Sholat sync selesai (periode {period_from} s/d {cutoff}). "
           f"Data sholat diperbarui: {updated_prayers}. "
           f"Lewati (belum ada absen kerja): {skipped_no_presence}. "
           f"Lewati (cabang terkunci payroll): {skipped_locked}. "
           f"Lewati (sholat hasil edit manual): {skipped_manual}. "
           f"Total log: {len(logs)}. "
           f"Match karyawan: {len(employee_map)}. "
           f"Tidak cocok window sholat: {stats['no_match']}.")
    
    log_sync(conn, machine_id, machine_name, 'success', len(logs), msg)
    update_machine_status(conn, machine_id, 'success')
    
    print(f"  ✅ {msg}")
    
    return {
        'success': True,
        'records': len(logs),
        'stats': stats,
        'message': msg,
    }


def sync_machine(machine, sync_date=None, tunnel_port=None):
    """Sync a single machine."""
    machine_id = machine['id']
    machine_name = machine['name']
    sn = machine['sn']
    password = machine['password']
    machine_type = machine['type']
    
    print(f"\n🔄 Syncing {machine_name} (SN: {sn})...")
    
    # Download .dat FIRST (may take long, don't hold DB connection)
    raw = cloud_download(sn, password)
    if raw is None:
        msg = f"Gagal download dari Solution Cloud. Cek SN/password."
        # Try to log failure (create connection just for this)
        try:
            conn = get_db_connection(tunnel_port)
            log_sync(conn, machine_id, machine_name, 'failed', 0, msg)
            update_machine_status(conn, machine_id, 'failed')
            conn.close()
        except Exception:
            print(f"  ⚠️ Cannot log to DB")
        return {'success': False, 'records': 0, 'message': msg}
    
    # Parse .dat
    logs = parse_dat(raw)
    print(f"  📄 Parsed {len(logs)} attendance records")
    
    if not logs:
        msg = "File .dat kosong atau format tidak valid."
        try:
            conn = get_db_connection(tunnel_port)
            log_sync(conn, machine_id, machine_name, 'failed', 0, msg)
            update_machine_status(conn, machine_id, 'failed')
            conn.close()
        except Exception:
            print(f"  ⚠️ Cannot log to DB")
        return {'success': False, 'records': 0, 'message': msg}
    
    # Save .dat file
    today = sync_date or datetime.now().strftime('%Y-%m-%d')
    dat_file = save_dat_file(sn, raw, today)
    print(f"  💾 Saved to {dat_file}")

    # NOW create DB connection (after download, no idle time)
    conn = get_db_connection(tunnel_port)

    # Compute current payroll period so we process the full month, not just today.
    period_from, period_to = get_payroll_period(today)
    cutoff = min(today, period_to)

    # Group logs by (finger_id, date) within the period
    logs_by_key = {}
    for finger_id, timestamp, date_str, time_str in logs:
        if date_str < period_from or date_str > cutoff:
            continue
        key = (finger_id, date_str)
        if key not in logs_by_key:
            logs_by_key[key] = []
        logs_by_key[key].append((timestamp, date_str, time_str))

    # Get employee mapping from all unique finger_ids in the period
    finger_ids = list(set(k[0] for k in logs_by_key))
    employee_map = get_employee_map(conn, finger_ids, today)
    print(f"  👥 Matched {len(employee_map)}/{len(finger_ids)} employees")

    # Cabang yang payroll periode ini SUDAH dikunci — jangan sentuh (2026-07-08)
    pm, py = period_month_year(period_to)
    locked_branches = get_locked_branches(conn, pm, py)
    if locked_branches:
        print(f"  🔒 Cabang terkunci payroll {pm}/{py}: {sorted(locked_branches)} — dilewati")

    # Process attendance per (employee, date)
    stats = {'inserted': 0, 'updated': 0, 'skipped': 0, 'missing': 0, 'no_schedule': 0, 'no_window': 0, 'skipped_locked': 0}

    for (finger_id, date_str), day_entries in logs_by_key.items():
        if finger_id not in employee_map:
            stats['missing'] += 1
            continue

        user_id = employee_map[finger_id]['user_id']

        if int(employee_map[finger_id].get('branch_id') or 0) in locked_branches:
            stats['skipped_locked'] += 1
            continue

        # Get shift for THIS date (not necessarily today)
        shift = get_today_shift(conn, user_id, date_str)
        if not shift:
            stats['no_schedule'] += 1
            continue

        # Prepare presence payload for this specific date
        payload = {
            'user_id': user_id,
            'entry_time': None,
            'entry_time_late': 0,
            'out_time': None,
            'rest_time_in': None,
            'rest_time_out': None,
            'rest_time_late': 0,
            'flow_date': date_str,
            'created_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'input_by': 'system',
            'presence_status': 'approved',
            'presence_type': 'normal',
            'is_overtime': '0',
        }

        day_entries.sort(key=lambda x: x[0])

        for timestamp, ds, time_str in day_entries:
            if (shift.get('start_time_in') and shift.get('start_time_out') and
                    time_between(time_str, shift['start_time_in'], shift['start_time_out']) and
                    not payload['entry_time']):
                payload['entry_time'] = f"{date_str} {time_str}"
                if shift.get('start_time_late'):
                    payload['entry_time_late'] = minutes_between(shift['start_time_late'], time_str)
                continue

            if (shift.get('end_time_in') and shift.get('end_time_out') and
                    time_between(time_str, shift['end_time_in'], shift['end_time_out']) and
                    not payload['out_time']):
                payload['out_time'] = f"{date_str} {time_str}"
                continue

            if shift.get('start_time_rest') and shift.get('end_time_rest'):
                if time_between(time_str, shift['start_time_rest'], shift['end_time_rest']):
                    if not payload['rest_time_in']:
                        payload['rest_time_in'] = f"{date_str} {time_str}"
                    elif not payload['rest_time_out']:
                        payload['rest_time_out'] = f"{date_str} {time_str}"
                        if shift.get('rest_time_range'):
                            limit = (datetime.strptime(payload['rest_time_in'], "%Y-%m-%d %H:%M:%S") +
                                     timedelta(minutes=int(shift['rest_time_range']))).strftime("%H:%M:%S")
                            payload['rest_time_late'] = minutes_between(limit, time_str)

        if (not payload['entry_time'] and not payload['out_time'] and
                not payload['rest_time_in'] and not payload['rest_time_out']):
            stats['no_window'] += 1
            continue

        status = upsert_presence(conn, payload)
        stats[status] += 1

    # Period-wide lateness recalc — re-derive lateness for all records in the
    # period so that any shift change after initial recording is reflected.
    # Cabang terkunci payroll ikut dilewati.
    recalc_count = recalc_period_lateness(conn, period_from, cutoff, locked_branches)
    if recalc_count > 0:
        print(f"  🔄 Rekap keterlambatan diperbarui: {recalc_count} record")

    # Cleanup old .dat files
    deleted = cleanup_old_dats(sn, today)
    if deleted > 0:
        print(f"  🗑️ Deleted {deleted} old .dat files")

    # Log sync
    total_processed = stats['inserted'] + stats['updated'] + stats['skipped']
    msg = (f"Sync selesai (periode {period_from} s/d {cutoff}). "
           f"Presensi baru: {stats['inserted']}, "
           f"diperbarui: {stats['updated']}, "
           f"dilewati: {stats['skipped']}. "
           f"Total log: {len(logs)}. "
           f"Finger tidak cocok: {stats['missing']}. "
           f"Tanpa jadwal: {stats['no_schedule']}. "
           f"Di luar jam shift: {stats['no_window']}. "
           f"Dilewati (cabang terkunci payroll): {stats['skipped_locked']}. "
           f"Rekap keterlambatan diperbarui: {recalc_count}. "
           f"File .dat lama dihapus: {deleted}.")
    
    log_sync(conn, machine_id, machine_name, 'success', len(logs), msg)
    update_machine_status(conn, machine_id, 'success')
    
    # Close connection
    conn.close()
    
    print(f"  ✅ {msg}")
    
    return {
        'success': True,
        'records': len(logs),
        'stats': stats,
        'deleted': deleted,
        'message': msg,
    }


def check_resources():
    """Check server resources before running. Returns True if safe to proceed."""
    import psutil
    
    # CPU load check
    cpu_percent = psutil.cpu_percent(interval=1)
    cpu_count = psutil.cpu_count()
    load_avg = os.getloadavg()[0]  # 1-minute load average
    
    # Memory check
    memory = psutil.virtual_memory()
    memory_percent = memory.percent
    
    # Disk check
    disk = psutil.disk_usage('/')
    disk_percent = disk.percent
    
    print(f"\n📊 Resource Check:")
    print(f"  CPU Load: {load_avg:.2f} ({cpu_percent:.1f}%)")
    print(f"  Memory: {memory_percent:.1f}% used")
    print(f"  Disk: {disk_percent:.1f}% used")
    
    # Threshold check (60%)
    if memory_percent > 60:
        print(f"\n❌ STOP: Memory usage {memory_percent:.1f}% > 60% limit")
        return False
    
    if disk_percent > 60:
        print(f"\n❌ STOP: Disk usage {disk_percent:.1f}% > 60% limit")
        return False
    
    if load_avg > cpu_count * 0.6:
        print(f"\n❌ STOP: CPU load {load_avg:.2f} > {cpu_count * 0.6:.2f} limit (60% of {cpu_count} cores)")
        return False
    
    print("\n✅ Resources OK - proceeding with sync")
    return True


def main():
    """Main entry point."""
    import argparse
    parser = argparse.ArgumentParser(description='Absensi Sync')
    parser.add_argument('--date', help='Sync specific date (YYYY-MM-DD)', default=None)
    args = parser.parse_args()
    
    sync_date = args.date
    
    # Acquire lock to prevent multiple instances
    if not acquire_lock():
        print("\n⚠️ Sync aborted: Another instance already running")
        return {
            'success': False,
            'machines_synced': 0,
            'machines_failed': 0,
            'total_records': 0,
            'total_deleted': 0,
            'timestamp': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'error': 'Another instance running'
        }
    
    try:
        # Check resources before proceeding
        if not check_resources():
            print("\n⚠️ Sync aborted due to high resource usage")
            release_lock()
            return {
                'success': False,
                'machines_synced': 0,
                'machines_failed': 0,
                'total_records': 0,
                'total_deleted': 0,
                'timestamp': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                'error': 'High resource usage'
            }
        
        print("=" * 60)
        print("🔄 E-ABSENSI SYNC - Hermes Agent")
        print(f"⏰ {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        if sync_date:
            print(f"📅 Target date: {sync_date}")
        print("=" * 60)
        
        # Connect to DB -- local VPS mysql (no SSH tunnel, lihat catatan DB_CONFIG)
        print("\n🔗 Connecting to local database...")

        tunnel = None
        tunnel_port = DB_CONFIG['port']

        conn = get_db_connection(tunnel_port)
        if not conn:
            print("❌ Cannot connect to database. Exiting.")
            release_lock()
            sys.exit(1)

        print("✅ Database connected")

        # Ambil daftar mesin LANGSUNG dari DB tiap run (bukan hardcode) -- lihat
        # komentar fetch_machines()/MACHINES_FALLBACK di atas kenapa ini penting.
        machines = fetch_machines(conn)
        print(f"  📋 {len(machines)} mesin aktif: " + ", ".join(f"{m['name']} ({m['type']})" for m in machines))

        # Sync each machine
        results = []
        total_records = 0
        total_deleted = 0

        for machine in machines:
            # Fresh DB connection per machine (SSH tunnel drops after heavy queries)
            try:
                conn = get_db_connection(tunnel_port)
            except Exception as e:
                print(f"  ❌ Cannot connect to DB: {e}")
                results.append({'success': False, 'records': 0, 'message': str(e)})
                continue

            if machine.get('type') == 'pray':
                result = sync_pray_machine(machine, sync_date=sync_date, tunnel_port=tunnel_port)
            else:
                result = sync_machine(machine, sync_date=sync_date, tunnel_port=tunnel_port)
            results.append(result)
            if result['success']:
                total_records += result['records']
                total_deleted += result.get('deleted', 0)

            # Close connection after each machine
            try:
                conn.close()
            except:
                pass
        
        # Summary
        print("\n" + "=" * 60)
        print("📊 SYNC SUMMARY")
        print("=" * 60)
        
        success_count = sum(1 for r in results if r['success'])
        fail_count = len(results) - success_count
        
        print(f"✅ Berhasil: {success_count}/{len(results)} mesin")
        if fail_count > 0:
            print(f"❌ Gagal: {fail_count} mesin")
        print(f"📄 Total records: {total_records}")
        print(f"🗑️ Total .dat lama dihapus: {total_deleted}")
        
        # Close connections
        try:
            conn.close()
        except:
            pass
        if tunnel:
            tunnel.stop()
        print("\n✅ Sync selesai!")
        release_lock()
        
        # Return summary for Hermes
        return {
            'success': success_count > 0,
            'machines_synced': success_count,
            'machines_failed': fail_count,
            'total_records': total_records,
            'total_deleted': total_deleted,
            'timestamp': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        }
    
    except Exception as e:
        print(f"\n❌ Sync error: {e}")
        import traceback
        traceback.print_exc()
        release_lock()
        return {
            'success': False,
            'machines_synced': 0,
            'machines_failed': 0,
            'total_records': 0,
            'total_deleted': 0,
            'timestamp': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'error': str(e)
        }
    finally:
        release_lock()


if __name__ == '__main__':
    main()

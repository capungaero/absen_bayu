"""Konfigurasi pipeline: .env loader sederhana + matematika periode payroll.

Periode: 26 (M-1) .. 25 M — Source of truth: application/config/constants.php:92-93
dan getRangeWorkDate() di application/helpers/monthname_helper.php:257-294.
Spec: docs/rules/01-periode-jadwal.md
"""
import os
from datetime import date, timedelta

ENV_PATHS = [
    os.environ.get("ABSEN_PIPELINE_ENV", ""),
    os.path.expanduser("~/absen_ai/.env"),
    os.path.join(os.path.dirname(__file__), "..", "..", ".env"),
]

_loaded = False


def load_env():
    """Muat file .env pertama yang ada (format KEY=VALUE, # komentar)."""
    global _loaded
    if _loaded:
        return
    for path in ENV_PATHS:
        if path and os.path.isfile(path):
            with open(path, encoding="utf-8") as f:
                for line in f:
                    line = line.strip()
                    if not line or line.startswith("#") or "=" not in line:
                        continue
                    k, _, v = line.partition("=")
                    os.environ.setdefault(k.strip(), v.strip())
            break
    _loaded = True


def env(key, default=""):
    load_env()
    return os.environ.get(key, default)


START_PAYROLL_DATE = 26  # constants.php:92
END_PAYROLL_DATE = 25    # constants.php:93


def period_range(period):
    """'YYYY-MM' -> (from_date, to_date, [date,...]) inklusif kedua ujung.

    'Periode 2026-06' = 2026-05-26 .. 2026-06-25 (31 tanggal).
    """
    year, month = int(period[:4]), int(period[5:7])
    pm, py = (12, year - 1) if month == 1 else (month - 1, year)
    d_from = date(py, pm, START_PAYROLL_DATE)
    d_to = date(year, month, END_PAYROLL_DATE)
    days = []
    cur = d_from
    while cur <= d_to:
        days.append(cur)
        cur += timedelta(days=1)
    return d_from, d_to, days


def period_of(d):
    """date -> 'YYYY-MM' periode payroll yang memuatnya."""
    if d.day >= START_PAYROLL_DATE:
        y, m = (d.year + 1, 1) if d.month == 12 else (d.year, d.month + 1)
    else:
        y, m = d.year, d.month
    return f"{y:04d}-{m:02d}"


# Kode shift no-schedule — schedule_helper.php:35 & PayrollSim.php:8
NO_SC_CODES = {"-", "NO-SC", "NO SCHEDULE"}


def is_no_sc(code):
    return (code or "").strip().upper() in NO_SC_CODES


def cloud_machines():
    """[(sn, password), ...] dari CLOUD_MACHINES=sn:pass,sn:pass"""
    out = []
    for item in env("CLOUD_MACHINES").split(","):
        item = item.strip()
        if ":" in item:
            sn, _, pw = item.partition(":")
            out.append((sn, pw))
    return out

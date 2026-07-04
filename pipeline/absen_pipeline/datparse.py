"""S2 — parse: baca semua raw .dat periode -> tabel raw_taps.

Port murni dari Attlog_parser::parse_taps
(application/libraries/Attlog_parser.php:36-82). Spec: docs/rules/02-absensi-dat.md.
Tanpa akses DB produksi. Anomali per (finger,date) dihitung utk review manusia.
"""
import re
from datetime import datetime

from .config import period_range
from .db import ai_conn, log_run


def parse_content(text, d_from, d_to):
    """-> (taps: {(finger_id, date): [time,...]}, stats) — mirror parse_taps PHP."""
    taps = {}
    stats = {"total_lines": 0, "raw_count": 0, "invalid_count": 0}
    for line in re.split(r"\r\n|\r|\n", text.strip()):
        line = line.strip()
        if not line:
            continue
        stats["total_lines"] += 1
        cols = re.split(r"\s+", line)
        if len(cols) < 3:
            stats["invalid_count"] += 1
            continue
        finger_id = cols[0].strip()
        try:
            dt = datetime.strptime(cols[1] + " " + cols[2], "%Y-%m-%d %H:%M:%S")
        except ValueError:
            stats["invalid_count"] += 1
            continue
        if not finger_id:
            stats["invalid_count"] += 1
            continue
        d = dt.date()
        if d < d_from or d > d_to:
            continue
        taps.setdefault((finger_id, d), []).append(dt.time())
        stats["raw_count"] += 1
    return taps, stats


def _anomaly(times):
    n = len(times)
    if n == 1:
        return "single_tap"
    if n > 8:
        return "excessive"
    if n % 2 == 1:
        return "odd_count"
    return None


def run(period):
    d_from, d_to, _ = period_range(period)
    conn = ai_conn()
    finish = log_run(conn, "parse", period)
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT id, file_path FROM raw_files WHERE period=%s", (period,))
            files = cur.fetchall()
        all_taps = {}   # (finger,date) -> set of (time, file_id) — dedup antar file
        stats = {"files": len(files), "total_lines": 0, "raw_count": 0, "invalid_count": 0}
        for f in files:
            try:
                text = open(f["file_path"], encoding="utf-8", errors="replace").read()
            except OSError as e:
                stats.setdefault("unreadable", []).append({"file": f["file_path"], "error": str(e)})
                continue
            taps, st = parse_content(text, d_from, d_to)
            for k in ("total_lines", "raw_count", "invalid_count"):
                stats[k] += st[k]
            for key, times in taps.items():
                bucket = all_taps.setdefault(key, {})
                for t in times:
                    bucket.setdefault(t, f["id"])  # tap sama dari file lain = dedup

        rows = []
        for (finger, d), timemap in sorted(all_taps.items()):
            times = sorted(timemap)
            anom = _anomaly(times)
            for t in times:
                rows.append({
                    "period": period, "branch_id": 0, "finger_id": finger,
                    "tap_date": d, "tap_time": t,
                    "raw_file_id": timemap[t], "anomaly": anom,
                })
        with conn.cursor() as cur:
            cur.execute("DELETE FROM raw_taps WHERE period=%s", (period,))
            if rows:
                cur.executemany(
                    "INSERT INTO raw_taps (period, branch_id, finger_id, tap_date, tap_time, raw_file_id, anomaly) "
                    "VALUES (%(period)s,%(branch_id)s,%(finger_id)s,%(tap_date)s,%(tap_time)s,%(raw_file_id)s,%(anomaly)s)",
                    rows,
                )
        conn.commit()
        stats["taps_inserted"] = len(rows)
        stats["finger_days"] = len(all_taps)
        finish("ok", stats)
        return stats
    except Exception as e:
        finish("error", {"error": str(e)})
        raise
    finally:
        conn.close()

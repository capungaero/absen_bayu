"""S1 — fetch: download .dat dari Solution Cloud, arsip immutable + manifest.

Port dari ~/.hermes/scripts/download_dat.py (login sc_pro.asp -> download.asp)
dan pola Cloud_attlog_client.php. File disimpan
PIPELINE_RAW_DIR/<period>/attlog_<sn>_<timestamp>.dat; hash sama = skip
(idempoten); baris tercatat di tabel raw_files. Raw TIDAK pernah ditimpa.
"""
import hashlib
import os
from datetime import datetime

import requests

from .config import env, cloud_machines
from .db import ai_conn, log_run

CLOUD_URL = "http://solutioncloud.co.id/"


def _download_machine(sn, password):
    s = requests.Session()
    s.headers.update({"User-Agent": "Mozilla/5.0"})
    r = s.post(CLOUD_URL + "sc_pro.asp", data={"sn": sn, "pass": password}, timeout=30)
    r.raise_for_status()
    r = s.get(CLOUD_URL + "download.asp", timeout=120)
    r.raise_for_status()
    return r.content


def _ingest(conn, period, sn, content, source):
    sha = hashlib.sha256(content).hexdigest()
    with conn.cursor() as cur:
        cur.execute("SELECT id, file_path FROM raw_files WHERE sha256=%s", (sha,))
        row = cur.fetchone()
        if row:
            return row["file_path"], False
    raw_dir = os.path.join(env("PIPELINE_RAW_DIR", "raw"), period)
    os.makedirs(raw_dir, exist_ok=True)
    fname = f"attlog_{sn}_{datetime.now():%Y%m%d_%H%M%S}.dat"
    path = os.path.join(raw_dir, fname)
    with open(path, "wb") as f:
        f.write(content)
    with conn.cursor() as cur:
        cur.execute(
            "INSERT INTO raw_files (period, branch_id, file_path, sha256, source, fetched_at) "
            "VALUES (%s, 0, %s, %s, %s, %s)",
            (period, path, sha, source, datetime.now()),
        )
    conn.commit()
    return path, True


def run(period, from_files=None):
    """Fetch semua mesin cloud (atau ingest file lokal bila from_files)."""
    conn = ai_conn()
    finish = log_run(conn, "fetch", period)
    stats = {"new": 0, "dup": 0, "failed": [], "files": []}
    try:
        if from_files:
            for path in from_files:
                sn = os.path.basename(path).split("_")[1] if "_" in os.path.basename(path) else "manual"
                with open(path, "rb") as f:
                    content = f.read()
                p, is_new = _ingest(conn, period, sn, content, "manual")
                stats["files"].append(p)
                stats["new" if is_new else "dup"] += 1
        else:
            for sn, pw in cloud_machines():
                try:
                    content = _download_machine(sn, pw)
                except Exception as e:  # jaringan/login gagal per mesin, lanjut mesin lain
                    stats["failed"].append({"sn": sn, "error": str(e)})
                    continue
                p, is_new = _ingest(conn, period, sn, content, "cloud")
                stats["files"].append(p)
                stats["new" if is_new else "dup"] += 1
        finish("ok" if not stats["failed"] else "error", stats)
        return stats
    except Exception as e:
        finish("error", {"error": str(e), **stats})
        raise
    finally:
        conn.close()

from __future__ import annotations

import argparse
import csv
import io
import json
import os
import re
import shutil
import subprocess
import tempfile
from dataclasses import dataclass
from datetime import date, datetime, timedelta
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional

from attendance_backup_queries import BACKUP_FIELD_MAP, build_backup_queries
from attendance_report_core import (
    DATE_SUMMARY_FIELDS,
    DETAIL_FIELDS,
    SUMMARY_FIELDS,
    build_report,
    date_range,
    parse_date,
    safe_int,
    week_range,
)


PROJECT_ROOT = Path(__file__).resolve().parents[1]
DEFAULT_OUT_DIR = PROJECT_ROOT / "exports" / "attendance_backups"
CI_DB_CONFIG = PROJECT_ROOT / "application" / "config" / "database.php"
MYSQL_CANDIDATES = [
    Path("D:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe"),
    Path("C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe"),
]


@dataclass
class DbConfig:
    host: str
    port: int
    user: str
    password: str
    database: str


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Backup data absen dan buat report mingguan CSV dari MySQL absen_bayu."
    )
    parser.add_argument("--week-start", help="Tanggal awal minggu, format YYYY-MM-DD.")
    parser.add_argument("--week-end", help="Tanggal akhir minggu, default week-start + 6 hari.")
    parser.add_argument("--previous-week", action="store_true", help="Pakai minggu sebelumnya.")
    parser.add_argument("--as-of", help="Tanggal batas hitung absen, default hari ini atau week-end.")
    parser.add_argument("--branch", help="Filter cabang/lokasi, contoh SDR, GBR, Gambir.")
    parser.add_argument("--employee-codes", help="Filter ID fingerprint, pisahkan koma.")
    parser.add_argument("--out-dir", default=str(DEFAULT_OUT_DIR), help="Folder output backup/report.")
    parser.add_argument("--mysql", help="Path mysql.exe jika auto-detect gagal.")
    return parser.parse_args()


def read_ci_db_config(path: Path = CI_DB_CONFIG) -> DbConfig:
    text = path.read_text(encoding="utf-8", errors="replace")

    def pick_string(key: str, default: str = "") -> str:
        match = re.search(rf"['\"]{re.escape(key)}['\"]\s*=>\s*['\"]([^'\"]*)['\"]", text)
        return match.group(1) if match else default

    def pick_int(key: str, default: int) -> int:
        match = re.search(rf"['\"]{re.escape(key)}['\"]\s*=>\s*(\d+)", text)
        return int(match.group(1)) if match else default

    return DbConfig(
        host=os.getenv("ABSEN_DB_HOST", pick_string("hostname", "127.0.0.1")),
        port=int(os.getenv("ABSEN_DB_PORT", str(pick_int("port", 3306)))),
        user=os.getenv("ABSEN_DB_USER", pick_string("username", "root")),
        password=os.getenv("ABSEN_DB_PASSWORD", pick_string("password", "")),
        database=os.getenv("ABSEN_DB_NAME", pick_string("database", "newtiffa_timesheet")),
    )


def find_mysql(explicit: Optional[str]) -> str:
    if explicit:
        return explicit
    env_path = os.getenv("MYSQL_CLI")
    if env_path:
        return env_path
    found = shutil.which("mysql")
    if found:
        return found
    for candidate in MYSQL_CANDIDATES:
        if candidate.exists():
            return str(candidate)
    raise RuntimeError("mysql.exe tidak ditemukan. Pakai --mysql atau set MYSQL_CLI.")


def write_defaults_file(config: DbConfig) -> str:
    handle = tempfile.NamedTemporaryFile("w", delete=False, encoding="utf-8", newline="\n")
    with handle:
        handle.write("[client]\n")
        handle.write(f"host={config.host}\n")
        handle.write(f"port={config.port}\n")
        handle.write(f"user={config.user}\n")
        handle.write(f"password={config.password}\n")
        handle.write("default-character-set=utf8mb4\n")
    return handle.name


def run_mysql(mysql_path: str, config: DbConfig, sql: str) -> List[Dict[str, Any]]:
    defaults_file = write_defaults_file(config)
    try:
        cmd = [
            mysql_path,
            f"--defaults-extra-file={defaults_file}",
            "-D",
            config.database,
            "--batch",
            "--raw",
            "-e",
            sql,
        ]
        proc = subprocess.run(cmd, text=True, capture_output=True, encoding="utf-8", errors="replace", check=False)
        if proc.returncode != 0:
            raise RuntimeError(proc.stderr.strip() or "mysql query failed")
        return parse_tsv(proc.stdout)
    finally:
        try:
            os.unlink(defaults_file)
        except OSError:
            pass


def parse_tsv(output: str) -> List[Dict[str, Any]]:
    reader = csv.reader(io.StringIO(output), delimiter="\t")
    try:
        header = next(reader)
    except StopIteration:
        return []
    rows = []
    for raw in reader:
        if len(raw) < len(header):
            raw += [""] * (len(header) - len(raw))
        rows.append({key: (None if value == "NULL" else value) for key, value in zip(header, raw)})
    return rows


def sql_quote(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def parse_employee_codes(value: Optional[str]) -> List[str]:
    if not value:
        return []
    codes = [item.strip() for item in value.split(",") if item.strip()]
    bad = [code for code in codes if not re.match(r"^[A-Za-z0-9._-]+$", code)]
    if bad:
        raise ValueError("employee-codes berisi karakter tidak aman: " + ", ".join(bad))
    return codes


def id_list(ids: Iterable[Any]) -> str:
    clean = [str(safe_int(value)) for value in ids if safe_int(value) > 0]
    return ",".join(clean) or "0"


def resolve_period(args: argparse.Namespace) -> tuple[date, date, date]:
    today = date.today()
    if args.week_start:
        start = parse_date(args.week_start)
        end = parse_date(args.week_end) if args.week_end else start + timedelta(days=6)
    else:
        start, end = week_range(today, args.previous_week)
    as_of = parse_date(args.as_of) if args.as_of else min(today, end)
    if as_of < start:
        as_of = start
    if as_of > end:
        as_of = end
    return start, end, as_of


def fetch_employees(
    mysql_path: str,
    config: DbConfig,
    branch: Optional[str],
    employee_codes: List[str],
) -> List[Dict[str, Any]]:
    where = ["u.active = 1"]
    if branch:
        needle = sql_quote(f"%{branch.strip()}%")
        where.append(f"(b.branch_code LIKE {needle} OR b.branch_name LIKE {needle} OR u.location LIKE {needle})")
    if employee_codes:
        where.append("u.employee_code IN (" + ",".join(sql_quote(code) for code in employee_codes) + ")")
    sql = f"""
        SELECT
            u.id,
            u.employee_code,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
            u.active,
            COALESCE(b.branch_code, '') AS branch_code,
            COALESCE(b.branch_name, '') AS branch_name,
            COALESCE(sd.subdivision_name, '') AS division_name,
            COALESCE(p.position_name, '') AS position_name,
            COALESCE(u.location, '') AS location,
            u.join_date,
            u.status_work,
            u.status_work_expiration
        FROM users u
        LEFT JOIN position p ON p.id = u.position_id
        LEFT JOIN branch b ON b.id = p.branch_id
        LEFT JOIN subdivision sd ON sd.id = u.subdivision_id
        WHERE {" AND ".join(where)}
        ORDER BY COALESCE(u.location, b.branch_name), sd.subdivision_name, employee_name
    """
    return run_mysql(mysql_path, config, sql)


def fetch_latest_schedules(mysql_path: str, config: DbConfig, start: date, end: date, user_ids: List[Any]) -> List[Dict[str, Any]]:
    sql = f"""
        SELECT
            usa.user_id,
            usa.additional_date,
            usa.additional_type,
            usa.shift_id,
            usa.id AS schedule_id,
            s.shift_code,
            s.shift_name,
            s.start_time,
            s.end_time,
            s.start_time_late
        FROM users_shift_additional usa
        JOIN (
            SELECT user_id, additional_date, MAX(id) AS id
            FROM users_shift_additional
            WHERE additional_date BETWEEN {sql_quote(start.isoformat())} AND {sql_quote(end.isoformat())}
              AND deleted_at IS NULL
            GROUP BY user_id, additional_date
        ) latest ON latest.id = usa.id
        LEFT JOIN shift s ON s.id = usa.shift_id
        WHERE usa.user_id IN ({id_list(user_ids)})
        ORDER BY usa.user_id, usa.additional_date
    """
    return run_mysql(mysql_path, config, sql)


def fetch_presence(mysql_path: str, config: DbConfig, start: date, end: date, user_ids: List[Any]) -> List[Dict[str, Any]]:
    sql = f"""
        SELECT *
        FROM presence
        WHERE flow_date BETWEEN {sql_quote(start.isoformat())} AND {sql_quote(end.isoformat())}
          AND user_id IN ({id_list(user_ids)})
        ORDER BY user_id, flow_date, id
    """
    return run_mysql(mysql_path, config, sql)


def fetch_leaves(mysql_path: str, config: DbConfig, start: date, end: date, user_ids: List[Any]) -> List[Dict[str, Any]]:
    sql = f"""
        SELECT *
        FROM `leave`
        WHERE leave_start <= {sql_quote(end.isoformat())}
          AND leave_end >= {sql_quote(start.isoformat())}
          AND deleted_at IS NULL
          AND user_id IN ({id_list(user_ids)})
        ORDER BY user_id, leave_start, id
    """
    return run_mysql(mysql_path, config, sql)


def index_schedules(rows: List[Dict[str, Any]]) -> Dict[str, Dict[str, Any]]:
    return {f"{row['user_id']}|{str(row['additional_date'])[:10]}": row for row in rows}


def index_presence(rows: List[Dict[str, Any]]) -> Dict[str, Dict[str, List[Dict[str, Any]]]]:
    out: Dict[str, Dict[str, List[Dict[str, Any]]]] = {}
    for row in rows:
        key = f"{row['user_id']}|{str(row['flow_date'])[:10]}"
        bucket = out.setdefault(key, {"all": [], "approved": []})
        bucket["all"].append(row)
        if row.get("presence_status") == "approved":
            bucket["approved"].append(row)
    return out


def index_leaves(rows: List[Dict[str, Any]], start: date, end: date) -> Dict[str, List[Dict[str, Any]]]:
    out: Dict[str, List[Dict[str, Any]]] = {}
    for row in rows:
        leave_start = max(start, parse_date(row["leave_start"]))
        leave_end = min(end, parse_date(row["leave_end"]))
        for day in date_range(leave_start, leave_end):
            out.setdefault(f"{row['user_id']}|{day.isoformat()}", []).append(row)
    return out


def write_csv(path: Path, rows: List[Dict[str, Any]], fields: List[str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", newline="", encoding="utf-8-sig") as handle:
        writer = csv.DictWriter(handle, fieldnames=fields, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)


def fetch_backup_rows(mysql_path: str, config: DbConfig, start: date, end: date, user_ids: List[Any]) -> Dict[str, List[Dict[str, Any]]]:
    queries = build_backup_queries(start.isoformat(), end.isoformat(), id_list(user_ids))
    return {name: run_mysql(mysql_path, config, sql) for name, sql in queries.items()}


def output_dir(base_dir: Path, start: date, end: date) -> Path:
    stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    return base_dir / f"weekly_{start.isoformat()}_to_{end.isoformat()}_{stamp}"


def main() -> int:
    args = parse_args()
    start, end, as_of = resolve_period(args)
    config = read_ci_db_config()
    mysql_path = find_mysql(args.mysql)
    employee_codes = parse_employee_codes(args.employee_codes)

    employees = fetch_employees(mysql_path, config, args.branch, employee_codes)
    user_ids = [row["id"] for row in employees]
    schedules = fetch_latest_schedules(mysql_path, config, start, end, user_ids)
    presence = fetch_presence(mysql_path, config, start, end, user_ids)
    leaves = fetch_leaves(mysql_path, config, start, end, user_ids)

    report = build_report(
        employees,
        date_range(start, end),
        as_of,
        index_schedules(schedules),
        index_presence(presence),
        index_leaves(leaves, start, end),
    )

    out_path = output_dir(Path(args.out_dir), start, end)
    write_csv(out_path / "report_weekly_detail.csv", report["details"], DETAIL_FIELDS)
    write_csv(out_path / "report_weekly_employee_summary.csv", report["employee_summaries"], SUMMARY_FIELDS)
    write_csv(out_path / "report_weekly_date_summary.csv", report["date_summaries"], DATE_SUMMARY_FIELDS)

    backup_rows = fetch_backup_rows(mysql_path, config, start, end, user_ids)
    for name, rows in backup_rows.items():
        write_csv(out_path / name, rows, BACKUP_FIELD_MAP[name])

    metadata = {
        "generated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "period_start": start.isoformat(),
        "period_end": end.isoformat(),
        "as_of": as_of.isoformat(),
        "database": config.database,
        "host": config.host,
        "port": config.port,
        "branch_filter": args.branch or "",
        "employee_codes": employee_codes,
        "employee_count": len(employees),
        "schedule_rows_latest": len(schedules),
        "presence_rows": len(presence),
        "leave_rows": len(leaves),
        "files": sorted(path.name for path in out_path.glob("*.csv")),
        "privacy_note": "CSV backup employees tidak menyertakan password, bank, phone, salary, atau alamat.",
    }
    (out_path / "metadata.json").write_text(json.dumps(metadata, indent=2), encoding="utf-8")

    print(f"OK: {out_path}")
    print(f"employees={len(employees)}")
    print(f"presence_rows={len(presence)}")
    print(f"detail_rows={len(report['details'])}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

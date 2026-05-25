from __future__ import annotations

from collections import defaultdict
from datetime import date, datetime, timedelta
from typing import Any, Dict, Iterable, List, Optional, Tuple


DETAIL_FIELDS = [
    "tanggal",
    "hari",
    "user_id",
    "employee_code",
    "employee_name",
    "branch_code",
    "branch_name",
    "division_name",
    "position_name",
    "location",
    "schedule_id",
    "additional_type",
    "shift_id",
    "shift_code",
    "shift_name",
    "shift_start_time",
    "shift_end_time",
    "shift_late_limit",
    "presence_id",
    "presence_status",
    "presence_type",
    "entry_time",
    "out_time",
    "rest_time_in",
    "rest_time_out",
    "late_minutes",
    "status",
    "status_code",
    "notes",
]

SUMMARY_FIELDS = [
    "user_id",
    "employee_code",
    "employee_name",
    "branch_code",
    "branch_name",
    "division_name",
    "position_name",
    "location",
    "hari_kerja",
    "hadir",
    "terlambat",
    "menit_telat",
    "izin",
    "cuti",
    "sakit",
    "absen",
    "tidak_lengkap",
    "libur",
    "tanpa_jadwal",
    "presence_tanpa_jadwal",
    "future",
    "anomali",
]

DATE_SUMMARY_FIELDS = [
    "tanggal",
    "hari",
    "total_karyawan",
    "hari_kerja",
    "hadir",
    "terlambat",
    "menit_telat",
    "izin",
    "cuti",
    "sakit",
    "absen",
    "tidak_lengkap",
    "libur",
    "tanpa_jadwal",
    "presence_tanpa_jadwal",
    "future",
    "anomali",
]

STATUS_CODE = {
    "HADIR": "H",
    "TERLAMBAT": "T",
    "IZIN": "I",
    "CUTI": "C",
    "SAKIT": "S",
    "ABSEN": "A",
    "TIDAK_LENGKAP": "TL",
    "LIBUR": "OFF",
    "TANPA_JADWAL": "TJ",
    "PRESENSI_TANPA_JADWAL": "PTJ",
    "FUTURE": "F",
}

TOTAL_KEYS = [
    "hari_kerja",
    "hadir",
    "terlambat",
    "menit_telat",
    "izin",
    "cuti",
    "sakit",
    "absen",
    "tidak_lengkap",
    "libur",
    "tanpa_jadwal",
    "presence_tanpa_jadwal",
    "future",
    "anomali",
]


def parse_date(value: Any) -> date:
    if isinstance(value, date):
        return value
    text = str(value).strip()
    return datetime.strptime(text, "%Y-%m-%d").date()


def date_range(start: date, end: date) -> List[date]:
    if end < start:
        raise ValueError("week end must be greater than or equal to week start")
    days = []
    current = start
    while current <= end:
        days.append(current)
        current += timedelta(days=1)
    return days


def week_range(today: Optional[date] = None, previous_week: bool = False) -> Tuple[date, date]:
    today = today or date.today()
    monday = today - timedelta(days=today.weekday())
    if previous_week:
        monday -= timedelta(days=7)
    return monday, monday + timedelta(days=6)


def is_blank(value: Any) -> bool:
    return value is None or str(value).strip() in {"", "NULL"}


def safe_int(value: Any, default: int = 0) -> int:
    if is_blank(value):
        return default
    try:
        return int(value)
    except (TypeError, ValueError):
        return default


def normalize_date_key(value: Any) -> str:
    if isinstance(value, date):
        return value.isoformat()
    return str(value)[:10]


def time_only(value: Any) -> str:
    if is_blank(value):
        return ""
    text = str(value)
    if len(text) >= 16 and text[4:5] == "-" and text[13:14] == ":":
        return text[11:16]
    if len(text) >= 5 and text[2:3] == ":":
        return text[:5]
    return text


def day_name(day: date) -> str:
    return ["Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu", "Minggu"][day.weekday()]


def identity_row(employee: Dict[str, Any]) -> Dict[str, Any]:
    return {
        "user_id": employee.get("id", ""),
        "employee_code": employee.get("employee_code", ""),
        "employee_name": employee.get("employee_name", ""),
        "branch_code": employee.get("branch_code", ""),
        "branch_name": employee.get("branch_name", ""),
        "division_name": employee.get("division_name", ""),
        "position_name": employee.get("position_name", ""),
        "location": employee.get("location", ""),
    }


def init_totals() -> Dict[str, int]:
    return {key: 0 for key in TOTAL_KEYS}


def classify_day(
    day: date,
    as_of: date,
    schedule: Optional[Dict[str, Any]],
    presence: Optional[Dict[str, Any]],
    presence_bucket: Dict[str, List[Dict[str, Any]]],
    leaves: Iterable[Dict[str, Any]],
) -> Tuple[str, List[str]]:
    notes: List[str] = []
    is_future = day > as_of

    if not schedule:
        if presence:
            notes.append("Presensi ada tanpa jadwal")
            return "PRESENSI_TANPA_JADWAL", notes
        if not is_future:
            notes.append("Jadwal tidak ada")
        return ("FUTURE" if is_future else "TANPA_JADWAL"), notes

    if schedule.get("additional_type") == "free":
        return "LIBUR", notes

    shift_code = str(schedule.get("shift_code") or "").strip()
    shift_name = str(schedule.get("shift_name") or "").strip().upper()
    if is_blank(schedule.get("shift_id")) or shift_code == "-" or shift_name == "NO SCHEDULE":
        notes.append("Shift master tidak ditemukan atau no schedule")
        return ("FUTURE" if is_future else "TANPA_JADWAL"), notes

    if not presence:
        for leave in leaves:
            if str(leave.get("leave_status")) == "approve":
                notes.append("Leave approve tanpa row presence")
                return str(leave.get("leave_type") or "izin").upper(), notes
        if presence_bucket.get("all"):
            notes.append("Ada presence non-approved")
            return "TIDAK_LENGKAP", notes
        return ("FUTURE" if is_future else "ABSEN"), notes

    if len(presence_bucket.get("approved", [])) > 1:
        notes.append("Duplikat presence approved")

    presence_type = str(presence.get("presence_type") or "normal")
    if presence_type != "normal":
        return presence_type.upper(), notes

    has_entry = not is_blank(presence.get("entry_time"))
    has_out = not is_blank(presence.get("out_time"))
    if has_entry ^ has_out:
        notes.append("Masuk/pulang tidak lengkap")
        return "TIDAK_LENGKAP", notes
    if not has_entry and not has_out:
        return ("FUTURE" if is_future else "ABSEN"), notes
    if (not is_blank(presence.get("rest_time_in"))) ^ (not is_blank(presence.get("rest_time_out"))):
        notes.append("Istirahat tidak lengkap")
    return ("TERLAMBAT" if safe_int(presence.get("entry_time_late")) > 0 else "HADIR"), notes


def add_totals(totals: Dict[str, int], status: str, presence: Optional[Dict[str, Any]], counted: bool) -> None:
    field = {
        "HADIR": "hadir",
        "TERLAMBAT": "terlambat",
        "IZIN": "izin",
        "CUTI": "cuti",
        "SAKIT": "sakit",
        "ABSEN": "absen",
        "TIDAK_LENGKAP": "tidak_lengkap",
        "LIBUR": "libur",
        "TANPA_JADWAL": "tanpa_jadwal",
        "PRESENSI_TANPA_JADWAL": "presence_tanpa_jadwal",
        "FUTURE": "future",
    }.get(status)
    if field:
        totals[field] += 1
    if status in {"PRESENSI_TANPA_JADWAL"}:
        totals["anomali"] += 1
    if counted and status in {"HADIR", "TERLAMBAT", "IZIN", "CUTI", "SAKIT", "ABSEN", "TIDAK_LENGKAP"}:
        totals["hari_kerja"] += 1
    if presence and status == "TERLAMBAT":
        totals["menit_telat"] += safe_int(presence.get("entry_time_late"))


def detail_row(
    employee: Dict[str, Any],
    day: date,
    schedule: Optional[Dict[str, Any]],
    presence: Optional[Dict[str, Any]],
    status: str,
    notes: List[str],
) -> Dict[str, Any]:
    row = identity_row(employee)
    row.update(
        {
            "tanggal": day.isoformat(),
            "hari": day_name(day),
            "schedule_id": schedule.get("schedule_id", "") if schedule else "",
            "additional_type": schedule.get("additional_type", "") if schedule else "",
            "shift_id": schedule.get("shift_id", "") if schedule else "",
            "shift_code": schedule.get("shift_code", "") if schedule else "",
            "shift_name": schedule.get("shift_name", "") if schedule else "",
            "shift_start_time": time_only(schedule.get("start_time")) if schedule else "",
            "shift_end_time": time_only(schedule.get("end_time")) if schedule else "",
            "shift_late_limit": time_only(schedule.get("start_time_late")) if schedule else "",
            "presence_id": presence.get("id", "") if presence else "",
            "presence_status": presence.get("presence_status", "") if presence else "",
            "presence_type": presence.get("presence_type", "") if presence else "",
            "entry_time": time_only(presence.get("entry_time")) if presence else "",
            "out_time": time_only(presence.get("out_time")) if presence else "",
            "rest_time_in": time_only(presence.get("rest_time_in")) if presence else "",
            "rest_time_out": time_only(presence.get("rest_time_out")) if presence else "",
            "late_minutes": safe_int(presence.get("entry_time_late")) if presence else 0,
            "status": status,
            "status_code": STATUS_CODE.get(status, status),
            "notes": "; ".join(notes),
        }
    )
    return row


def build_report(
    employees: List[Dict[str, Any]],
    days: List[date],
    as_of: date,
    schedules: Dict[str, Dict[str, Any]],
    presence_rows: Dict[str, Dict[str, List[Dict[str, Any]]]],
    leaves: Dict[str, List[Dict[str, Any]]],
) -> Dict[str, List[Dict[str, Any]]]:
    details: List[Dict[str, Any]] = []
    employee_summaries: List[Dict[str, Any]] = []
    date_totals: Dict[str, Dict[str, int]] = defaultdict(init_totals)

    for employee in employees:
        user_id = safe_int(employee.get("id"))
        totals = init_totals()
        for day in days:
            key = f"{user_id}|{day.isoformat()}"
            schedule = schedules.get(key)
            bucket = presence_rows.get(key, {"all": [], "approved": []})
            presence = bucket["approved"][-1] if bucket.get("approved") else None
            status, notes = classify_day(day, as_of, schedule, presence, bucket, leaves.get(key, []))
            counted = day <= as_of
            add_totals(totals, status, presence, counted)
            add_totals(date_totals[day.isoformat()], status, presence, counted)
            details.append(detail_row(employee, day, schedule, presence, status, notes))

        summary = identity_row(employee)
        summary.update(totals)
        employee_summaries.append(summary)

    date_summaries = []
    for day in days:
        row = {"tanggal": day.isoformat(), "hari": day_name(day), "total_karyawan": len(employees)}
        row.update(date_totals[day.isoformat()])
        date_summaries.append(row)

    return {
        "details": details,
        "employee_summaries": employee_summaries,
        "date_summaries": date_summaries,
    }

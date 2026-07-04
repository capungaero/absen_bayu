"""S3 — enrich: SATU-SATUNYA stage yang membaca MySQL produksi (read-only).

1. Bekukan master ke config_snapshot + ref_* per periode.
2. Klasifikasi raw_taps -> rekap_absensi_harian memakai window shift
   (port murni Attlog_parser::classify_taps, application/libraries/
   Attlog_parser.php:110-200 + attlog_time_between attlog_helper.php:29-40
   + late_minutes late_helper.php:11-24). Spec: docs/rules/02-absensi-dat.md.
"""
import json
from datetime import date, datetime

from .config import env, period_range, is_no_sc
from .db import SSHTunnel, ai_conn, prod_conn, log_run, replace_period

SHIFT_JSON_COLS = [
    "start_time", "end_time", "start_time_in", "start_time_out", "start_time_late",
    "end_time_in", "end_time_out", "start_time_rest", "end_time_rest", "rest_time_range",
    "late_amount_start", "late_amount_multiple_start", "late_multiple_count_start",
    "late_amount_max_start", "late_amount_rest", "late_amount_multiple_rest",
    "late_multiple_count_rest", "late_amount_max_rest",
]

SNAPSHOT_SCOPES = {
    "branch": "SELECT * FROM branch",
    "shift": "SELECT * FROM shift",
    "insentif": "SELECT * FROM insentif",
    "deduction": "SELECT * FROM deduction",
    "bpjs_config": "SELECT * FROM bpjs_config",
    "double_deduction_date": "SELECT * FROM double_deduction_date",
}


def late_minutes(limit, tap):
    """Port late_helper.php:11-24 — floor ke menit, detik dibuang."""
    if not limit or not tap:
        return 0
    lh, lm = int(str(limit)[:2]), int(str(limit)[3:5])
    th, tm = int(str(tap)[:2]), int(str(tap)[3:5])
    return max(0, (th * 60 + tm) - (lh * 60 + lm))


def time_between(t, start, end):
    """Port attlog_time_between (attlog_helper.php:29-40); dukung lewat tengah malam."""
    if not start or not end:
        return False
    t, start, end = str(t), str(start), str(end)
    if start <= end:
        return start <= t <= end
    return t >= start or t <= end


def _t(v):
    """Normalisasi ke 'HH:MM:SS' string (timedelta pymysql / time / str)."""
    if v is None or v == "":
        return None
    if hasattr(v, "total_seconds"):
        s = int(v.total_seconds()) % 86400
        return f"{s // 3600:02d}:{s % 3600 // 60:02d}:{s % 60:02d}"
    s = str(v)
    return s if len(s) >= 8 else s + ":00"


def classify_day(times, shift):
    """Port loop klasifikasi Attlog_parser::classify_taps (:160-183).

    times: list 'HH:MM:SS' terurut; shift: dict window (string times).
    Return payload {entry_time, entry_late, out_time, rest_in, rest_out, rest_late}.
    """
    p = {"entry_time": None, "entry_late": 0, "out_time": None,
         "rest_in": None, "rest_out": None, "rest_late": 0}
    for t in times:
        if time_between(t, shift.get("start_time_in"), shift.get("start_time_out")) and not p["entry_time"]:
            p["entry_time"] = t
            p["entry_late"] = late_minutes(shift.get("start_time_late"), t)
            continue
        if time_between(t, shift.get("end_time_in"), shift.get("end_time_out")) and not p["out_time"]:
            p["out_time"] = t
            continue
        if time_between(t, shift.get("start_time_rest"), shift.get("end_time_rest")):
            if not p["rest_in"]:
                p["rest_in"] = t
            elif not p["rest_out"]:
                p["rest_out"] = t
                rin = p["rest_in"]
                rng = int(shift.get("rest_time_range") or 0)
                total = int(rin[:2]) * 60 + int(rin[3:5]) + rng
                limit = f"{(total // 60) % 24:02d}:{total % 60:02d}:00"
                p["rest_late"] = late_minutes(limit, t)
    return p


def run(period):
    d_from, d_to, days = period_range(period)
    tunnel = SSHTunnel().start()
    ai = ai_conn()
    finish = log_run(ai, "enrich", period)
    stats = {}
    try:
        prod = prod_conn(tunnel)
        with prod.cursor() as cur:
            # -------- config snapshot
            now = datetime.now()
            with ai.cursor() as acur:
                for scope, sql in SNAPSHOT_SCOPES.items():
                    cur.execute(sql)
                    acur.execute(
                        "REPLACE INTO config_snapshot (period, scope, payload, snapped_at) VALUES (%s,%s,%s,%s)",
                        (period, scope, json.dumps(cur.fetchall(), default=str), now),
                    )
            ai.commit()

            # -------- ref_employee (+ flag sistem dari branch)
            cur.execute(
                """SELECT u.id AS user_id, COALESCE(u.employee_code,'') AS employee_code,
                          TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) AS full_name,
                          p.branch_id, p.position_name, sd.subdivision_name AS subdivision,
                          COALESCE(u.salary,0) AS salary, COALESCE(u.salary_minimum,0) AS salary_minimum, u.ptkp_status, u.status_work,
                          u.join_date, COALESCE(u.active,'0') AS active,
                          COALESCE(b.is_fine_system,'0') AS is_fine_system, COALESCE(b.is_pray_system,'0') AS is_pray_system
                   FROM users u
                   JOIN position p ON p.id = u.position_id
                   JOIN branch b ON b.id = p.branch_id
                   LEFT JOIN subdivision sd ON sd.id = u.subdivision_id"""
            )
            employees = cur.fetchall()
            for e in employees:
                e["period"] = period
            stats["employees"] = replace_period(
                ai, "ref_employee", period, employees,
                ["period", "user_id", "employee_code", "full_name", "branch_id",
                 "position_name", "subdivision", "salary", "salary_minimum",
                 "ptkp_status", "status_work", "join_date", "is_fine_system",
                 "is_pray_system", "active"],
            )
            # deteksi duplikat employee_code AKTIF (phantom risk)
            seen, dups = {}, []
            for e in employees:
                if str(e["active"]) == "1" and e["employee_code"]:
                    seen.setdefault(e["employee_code"], []).append(e["user_id"])
            dups = {k: v for k, v in seen.items() if len(v) > 1}
            stats["dup_employee_code"] = dups

            # -------- ref_shift_schedule
            cur.execute(
                """SELECT usa.user_id, usa.additional_date AS sched_date,
                          usa.shift_id, usa.additional_type AS sched_type,
                          s.shift_code, s.start_time, s.end_time, s.start_time_in,
                          s.start_time_out, s.start_time_late, s.end_time_in, s.end_time_out,
                          s.start_time_rest, s.end_time_rest, s.rest_time_range,
                          s.late_amount_start, s.late_amount_multiple_start,
                          s.late_multiple_count_start, s.late_amount_max_start,
                          s.late_amount_rest, s.late_amount_multiple_rest,
                          s.late_multiple_count_rest, s.late_amount_max_rest
                   FROM users_shift_additional usa
                   LEFT JOIN shift s ON s.id = usa.shift_id
                   WHERE usa.additional_date BETWEEN %s AND %s""",
                (d_from, d_to),
            )
            sched_rows = []
            schedule = {}
            for r in cur.fetchall():
                sj = {c: _t(r[c]) if "time" in c and "range" not in c else r[c] for c in SHIFT_JSON_COLS}
                row = {
                    "period": period, "user_id": r["user_id"], "sched_date": r["sched_date"],
                    "shift_id": r["shift_id"], "shift_code": r["shift_code"],
                    "sched_type": r["sched_type"],
                    "is_no_sc": 1 if is_no_sc(r["shift_code"]) else 0,
                    "shift_json": json.dumps(sj, default=str),
                }
                sched_rows.append(row)
                schedule[(r["user_id"], r["sched_date"])] = (row, sj)
            stats["schedule"] = replace_period(
                ai, "ref_shift_schedule", period, sched_rows,
                ["period", "user_id", "sched_date", "shift_id", "shift_code",
                 "sched_type", "is_no_sc", "shift_json"],
            )

            # -------- ref_leave (approved yang overlap periode)
            cur.execute(
                """SELECT id AS leave_id, user_id, leave_type, leave_start, leave_end,
                          leave_range,
                          (leave_proof IS NOT NULL AND leave_proof != '') AS has_proof,
                          leave_status AS status
                   FROM `leave`
                   WHERE leave_start <= %s AND leave_end >= %s""",
                (d_to, d_from),
            )
            leaves = cur.fetchall()
            for l in leaves:
                l["period"] = period
            stats["leaves"] = replace_period(
                ai, "ref_leave", period, leaves,
                ["period", "leave_id", "user_id", "leave_type", "leave_start",
                 "leave_end", "leave_range", "has_proof", "status"],
            )

            # -------- ref_manual_inputs (lembur, insentif/deduction manual, bpjs)
            year, month = int(period[:4]), int(period[5:7])
            manual = []
            cur.execute(
                "SELECT * FROM overtime WHERE overtime_date BETWEEN %s AND %s", (d_from, d_to))
            for r in cur.fetchall():
                manual.append({"period": period, "user_id": r["user_id"], "input_type": "overtime",
                               "ref_id": r["id"], "payload": json.dumps(r, default=str)})
            cur.execute(
                "SELECT * FROM payroll_insentif WHERE insentif_month=%s AND insentif_year=%s AND deleted_at IS NULL",
                (month, year))
            for r in cur.fetchall():
                manual.append({"period": period, "user_id": r["user_id"], "input_type": "insentif_manual",
                               "ref_id": r["id"], "payload": json.dumps(r, default=str)})
            cur.execute(
                "SELECT * FROM payroll_deduction WHERE deduction_month=%s AND deduction_year=%s",
                (month, year))
            for r in cur.fetchall():
                manual.append({"period": period, "user_id": r["user_id"], "input_type": "deduction_manual",
                               "ref_id": r["id"], "payload": json.dumps(r, default=str)})
            cur.execute("SELECT * FROM bpjs_payment WHERE month=%s AND year=%s", (month, year))
            for r in cur.fetchall():
                manual.append({"period": period, "user_id": r["user_id"], "input_type": "bpjs_payment",
                               "ref_id": r["id"], "payload": json.dumps(r, default=str)})
            stats["manual_inputs"] = replace_period(
                ai, "ref_manual_inputs", period, manual,
                ["period", "user_id", "input_type", "ref_id", "payload"],
            )
        prod.close()

        # -------- klasifikasi taps -> rekap_absensi_harian
        code_to_user = {}
        emp_by_id = {}
        for e in employees:
            emp_by_id[e["user_id"]] = e
            if e["employee_code"] and str(e["active"]) == "1":
                code_to_user.setdefault(e["employee_code"], e["user_id"])

        # Tap mesin SHOLAT tidak boleh masuk klasifikasi kerja — di app produksi
        # mesin kerja & sholat diproses jalur terpisah (sync_presence_cloud vs
        # sync_pray_cloud). Dzuhur ~12:00 akan salah tangkap sbg rest_in/out.
        sholat_sn = env("CLOUD_MACHINE_SHOLAT", "")
        with ai.cursor() as cur:
            cur.execute(
                "SELECT t.finger_id, t.tap_date, t.tap_time FROM raw_taps t "
                "LEFT JOIN raw_files f ON f.id = t.raw_file_id "
                "WHERE t.period=%s AND (f.file_path IS NULL OR f.file_path NOT LIKE %s) "
                "ORDER BY t.finger_id, t.tap_date, t.tap_time",
                (period, f"%attlog_{sholat_sn}_%" if sholat_sn else "%__NEVER__%"))
            taps_by = {}
            for r in cur.fetchall():
                taps_by.setdefault((r["finger_id"], r["tap_date"]), []).append(_t(r["tap_time"]))

        leave_by_user_date = {}
        for l in leaves:
            if l["status"] != "approve":
                continue
            cur_d = l["leave_start"]
            while cur_d <= l["leave_end"]:
                leave_by_user_date[(l["user_id"], cur_d)] = l["leave_type"]
                cur_d = date.fromordinal(cur_d.toordinal() + 1)

        today = date.today()
        rekap, unmatched_fingers = [], set()
        matched = 0
        payloads = {}
        for (finger, d), times in taps_by.items():
            uid = code_to_user.get(finger)
            if uid is None:
                unmatched_fingers.add(finger)
                continue
            matched += 1
            sched = schedule.get((uid, d))
            if not sched:
                continue  # tap tanpa jadwal -> tak diklasifikasi (paralel no_schedule stat PHP)
            payloads[(uid, d)] = classify_day(times, sched[1])

        for (uid, d), (srow, _sj) in schedule.items():
            e = emp_by_id.get(uid)
            if not e:
                continue
            p = payloads.get((uid, d), {})
            leave_type = leave_by_user_date.get((uid, d))
            if srow["is_no_sc"]:
                status = "no_sc"
            elif srow["sched_type"] == "free":
                status = "off"
            elif leave_type:
                status = leave_type
            elif p.get("entry_time") and p.get("out_time"):
                status = "hadir"
            elif p.get("entry_time") or p.get("out_time"):
                status = "hadir_sebagian"
            elif d <= today:
                status = "alfa"
            else:
                status = "belum"
            rekap.append({
                "period": period, "user_id": uid, "tanggal": d,
                "shift_code": srow["shift_code"], "status": status,
                "entry_time": p.get("entry_time"), "out_time": p.get("out_time"),
                "entry_late_m": p.get("entry_late", 0),
                "rest_in": p.get("rest_in"), "rest_out": p.get("rest_out"),
                "rest_late_m": p.get("rest_late", 0),
                "early_leave_m": 0, "source": "dat",
            })
        stats["rekap_rows"] = replace_period(
            ai, "rekap_absensi_harian", period, rekap,
            ["period", "user_id", "tanggal", "shift_code", "status", "entry_time",
             "out_time", "entry_late_m", "rest_in", "rest_out", "rest_late_m",
             "early_leave_m", "source"],
        )
        stats["matched_finger_days"] = matched
        stats["unmatched_fingers"] = sorted(unmatched_fingers)[:20]
        finish("ok", stats)
        return stats
    except Exception as e:
        finish("error", {"error": str(e), **{k: v for k, v in stats.items() if isinstance(v, (int, str))}})
        raise
    finally:
        ai.close()
        tunnel.stop()

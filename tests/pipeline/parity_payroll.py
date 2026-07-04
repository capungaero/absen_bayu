#!/usr/bin/env python3
"""Paritas L2/L3: insentif, deduction, lembur, THP vs payroll_detail golden.

python tests/pipeline/parity_payroll.py [2026-06]
bpjs_work/bpjs_together diambil dari payroll_detail (input manual HR saat lock).
"""
import csv
import sys
from datetime import date, timedelta
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "pipeline"))
from absen_pipeline.engine.fines import get_fine  # noqa: E402
from absen_pipeline.engine import payroll as P  # noqa: E402

GOLDEN = Path(__file__).resolve().parent / "golden"


def tsv(path):
    with open(path, encoding="utf-8", newline="") as f:
        rows = list(csv.DictReader(f, delimiter="\t"))
    for r in rows:
        for k, v in r.items():
            if v == "NULL":
                r[k] = None
    return rows


def main(period="2026-06"):
    year, month = int(period[:4]), int(period[5:7])
    g = GOLDEN / period
    cfg = GOLDEN / "config_snapshot_now"

    users = {r["id"]: r for r in tsv(cfg / "users.tsv")}
    positions = {r["id"]: r for r in tsv(cfg / "position.tsv")}
    branches = {r["id"]: r for r in tsv(cfg / "branch.tsv")}
    shifts = {r["id"]: r for r in tsv(cfg / "shift.tsv")}
    ins_masters = tsv(cfg / "insentif.tsv")
    ded_masters = tsv(cfg / "deduction.tsv")
    dbl = {r["special_date"] for r in tsv(cfg / "double_deduction_date.tsv")
           if str(r.get("is_active")) == "1"}

    sched_by_user, pres_by_user = {}, {}
    for r in tsv(g / "schedule.tsv"):
        sh = shifts.get(r.get("shift_id") or "") or {}
        e = {"type": r["additional_type"], "code": r.get("shift_code") or "free",
             "is_no_sc": (r.get("shift_code") or "").strip().upper() in ("-", "NO-SC", "NO SCHEDULE"),
             "start_time": sh.get("start_time"), "end_time": sh.get("end_time")}
        for c in ("late_amount_start", "late_amount_multiple_start", "late_multiple_count_start",
                  "late_amount_max_start", "late_amount_rest", "late_amount_multiple_rest",
                  "late_multiple_count_rest", "late_amount_max_rest"):
            e[c] = sh.get(c)
        sched_by_user.setdefault(r["user_id"], {})[str(r["additional_date"])[:10]] = e
    for r in tsv(g / "presence.tsv"):
        pres_by_user.setdefault(r["user_id"], {})[str(r["flow_date"])[:10]] = r

    pi_by_user, pd_by_user, ot_by_user = {}, {}, {}
    for r in tsv(g / "payroll_insentif.tsv"):
        pi_by_user.setdefault(r["user_id"], {})[int(r["insentif_id"])] = r["insentif_amount"]
    for r in tsv(g / "payroll_deduction.tsv"):
        pd_by_user.setdefault(r["user_id"], []).append(r)
    for r in tsv(g / "overtime.tsv"):
        ot_by_user.setdefault(r["user_id"], []).append(r)

    detail = tsv(g / "payroll_detail.tsv")
    today = date(year, month, 25) + timedelta(days=30)
    period_total_day = len(tsv(g / "presence.tsv")) and 0  # dihitung di bawah
    # total hari periode: 26 (M-1)..25 M
    pm, py = (12, year - 1) if month == 1 else (month - 1, year)
    period_total_day = (date(year, month, 25) - date(py, pm, 26)).days + 1

    mism, ok = [], 0
    for d in detail:
        uid = d["user_id"]
        u = users.get(uid)
        pos = positions.get(u["position_id"], {}) if u else {}
        br = branches.get(pos.get("branch_id", ""), {})
        branch_id = pos.get("branch_id")
        sched = sched_by_user.get(uid, {})
        pres = pres_by_user.get(uid, {})

        emp = {"salary": u["salary"], "is_fine_system": br.get("is_fine_system"),
               "is_pray_system": br.get("is_pray_system")}
        fine = get_fine(emp, sched, pres, br, dbl, month, year, today=today)

        masters_b = [m for m in ins_masters if m.get("branch_id") == branch_id
                     and str(m.get("is_active")) == "1"]
        ins_total, _ = P.resolve_insentif(masters_b, pi_by_user.get(uid, {}),
                                          P.full_presence_count(pres))
        dmasters_b = [m for m in ded_masters if m.get("branch_id") == branch_id
                      and str(m.get("is_active")) == "1"]
        ded_total, _ = P.resolve_deduction(dmasters_b, pd_by_user.get(uid, []))
        ot_hours, ot_amount = P.overtime_total(
            ot_by_user.get(uid, []), pres, sched, br.get("max_overtime"),
            u.get("overtime_hour_rate"))

        strip = sum(1 for s in sched.values() if s["type"] == "work" and s["is_no_sc"])
        agg = P.aggregate(u["salary"], u["salary_minimum"], fine["amount_in_weekdays"],
                          ot_amount, ins_total, fine["amount"],
                          d["salary_out_work"], ded_total, d["salary_out_together"],
                          period_total_day, strip)

        checks = [
            ("salary_in_insentive", int(round(ins_total))),
            ("salary_out_deduction", int(round(ded_total))),
            ("salary_in_overtime", int(ot_amount)),
            ("salary_in_basic", int(round(agg["payment_receive"]))),
            ("salary_basic_out_off_work", int(round(agg["salary_basic_out_off_work"]))),
            ("salary_thp", int(round(agg["salary_thp"]))),
            ("salary_debt", int(round(agg["salary_debt"]))),
        ]
        row_ok = True
        for col, got in checks:
            want = int(round(float(d[col] or 0)))
            if got != want:
                row_ok = False
                mism.append((uid, col, got, want))
        ok += row_ok

    print(f"[{period}] karyawan cocok penuh: {ok}/{len(detail)}; mismatch: {len(mism)}")
    from collections import Counter
    print("  per kolom:", dict(Counter(m[1] for m in mism)))
    for m in mism[:20]:
        print("  user %s %s: engine=%s app=%s" % m)
    return 0 if not mism else 1


if __name__ == "__main__":
    sys.exit(main(sys.argv[1] if len(sys.argv) > 1 else "2026-06"))

#!/usr/bin/env python3
"""Paritas L2 (komponen denda) engine Python vs payroll_detail golden.

Jalankan: python tests/pipeline/parity_fines.py [2026-06]
Membandingkan get_fine() vs kolom payroll_detail: salary_out_fine,
salary_basic_out_alfa_weekdays, salary_basic_out_alfa_weekend.
"""
import csv
import sys
from datetime import date, timedelta
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "pipeline"))
from absen_pipeline.engine.fines import get_fine  # noqa: E402

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
    dbl = {r["special_date"] for r in tsv(cfg / "double_deduction_date.tsv")
           if str(r.get("is_active")) == "1"}

    schedule_by_user = {}
    for r in tsv(g / "schedule.tsv"):
        sh = shifts.get(r.get("shift_id") or "") or {}
        entry = {
            "type": r["additional_type"],
            "code": r.get("shift_code") or "free",
            "is_no_sc": (r.get("shift_code") or "").strip().upper() in ("-", "NO-SC", "NO SCHEDULE"),
        }
        for c in ("late_amount_start", "late_amount_multiple_start", "late_multiple_count_start",
                  "late_amount_max_start", "late_amount_rest", "late_amount_multiple_rest",
                  "late_multiple_count_rest", "late_amount_max_rest"):
            entry[c] = sh.get(c)
        schedule_by_user.setdefault(r["user_id"], {})[str(r["additional_date"])[:10]] = entry

    pres_by_user = {}
    for r in tsv(g / "presence.tsv"):
        pres_by_user.setdefault(r["user_id"], {})[str(r["flow_date"])[:10]] = r

    detail = tsv(g / "payroll_detail.tsv")
    today = date(year, month, 25) + timedelta(days=30)  # replay: semua tanggal sudah lewat

    mism, ok = [], 0
    for d in detail:
        uid = d["user_id"]
        u = users.get(uid)
        if not u:
            mism.append((uid, "user tidak ada di snapshot", "", ""))
            continue
        br = branches.get(positions.get(u["position_id"], {}).get("branch_id", ""), {})
        emp = {"salary": u["salary"], "is_fine_system": br.get("is_fine_system"),
               "is_pray_system": br.get("is_pray_system")}
        res = get_fine(emp, schedule_by_user.get(uid, {}), pres_by_user.get(uid, {}),
                       br, dbl, month, year, today=today)
        checks = [
            ("salary_out_fine", res["amount"]),
            ("salary_basic_out_alfa_weekdays", int(round(res["amount_in_weekdays"]))),
            ("salary_basic_out_alfa_weekend", int(round(res["amount_in_weekend"]))),
        ]
        row_ok = True
        for col, got in checks:
            want = int(round(float(d[col] or 0)))
            if int(got) != want:
                row_ok = False
                mism.append((uid, col, int(got), want))
        ok += row_ok

    print(f"[{period}] karyawan cocok penuh: {ok}/{len(detail)}; mismatch: {len(mism)}")
    for m in mism[:25]:
        print("  user %s %s: engine=%s app=%s" % m)
    return 0 if not mism else 1


if __name__ == "__main__":
    sys.exit(main(sys.argv[1] if len(sys.argv) > 1 else "2026-06"))

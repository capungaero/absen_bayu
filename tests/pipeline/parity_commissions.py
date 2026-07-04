#!/usr/bin/env python3
"""Paritas komisi otomatis: engine vs baris payroll_insentif golden (id auto).

python tests/pipeline/parity_commissions.py [2026-06]
"""
import csv
import sys
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "pipeline"))
from absen_pipeline.engine.fines import get_fine  # noqa: E402
from absen_pipeline.engine.commissions import AUTO_COMM_IDS, auto_commissions  # noqa: E402

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
    bpjs_cfg = (tsv(cfg / "bpjs_config.tsv") or [{}])[0]
    dbl = {r["special_date"] for r in tsv(cfg / "double_deduction_date.tsv")
           if str(r.get("is_active")) == "1"}

    sched_by_user, pres_by_user, leave_by_user, bpjs_by_user = {}, {}, {}, {}
    for r in tsv(g / "schedule.tsv"):
        sh = shifts.get(r.get("shift_id") or "") or {}
        e = {"type": r["additional_type"],
             "is_no_sc": (r.get("shift_code") or "").strip().upper() in ("-", "NO-SC", "NO SCHEDULE")}
        for c in ("late_amount_start", "late_amount_multiple_start", "late_multiple_count_start",
                  "late_amount_max_start", "late_amount_rest", "late_amount_multiple_rest",
                  "late_multiple_count_rest", "late_amount_max_rest"):
            e[c] = sh.get(c)
        sched_by_user.setdefault(r["user_id"], {})[str(r["additional_date"])[:10]] = e
    for r in tsv(g / "presence.tsv"):
        pres_by_user.setdefault(r["user_id"], {})[str(r["flow_date"])[:10]] = r
    for r in tsv(g / "leave.tsv"):
        leave_by_user.setdefault(r["user_id"], []).append(
            {"leave_type": r["leave_type"], "leave_range": r["leave_range"],
             "has_proof": "1" if r.get("leave_proof") else "0",
             "status": r["leave_status"]})
    for r in tsv(g / "bpjs_payment.tsv"):
        bpjs_by_user[r["user_id"]] = r

    pi = {}
    for r in tsv(g / "payroll_insentif.tsv"):
        pi.setdefault(r["user_id"], {})[int(r["insentif_id"])] = int(float(r["insentif_amount"] or 0))

    detail = tsv(g / "payroll_detail.tsv")
    ref_date = date(year, month, 25)

    mism, ok = [], 0
    for d in detail:
        uid = d["user_id"]
        u = users.get(uid)
        pos = positions.get(u["position_id"], {})
        br = branches.get(pos.get("branch_id", ""), {})
        ids = AUTO_COMM_IDS.get(str(pos.get("branch_id")), AUTO_COMM_IDS["1"])
        emp = {"salary": u["salary"], "is_fine_system": br.get("is_fine_system"),
               "is_pray_system": br.get("is_pray_system"),
               "ptkp_status": u.get("ptkp_status"), "join_date": u.get("join_date")}
        sched = sched_by_user.get(uid, {})
        pres = pres_by_user.get(uid, {})
        fine = get_fine(emp, sched, pres, br, dbl, month, year,
                        today=date(year, month, 28))
        res = auto_commissions(emp, fine, sched, pres, leave_by_user.get(uid, []),
                               bpjs_by_user.get(uid), bpjs_cfg, ref_date)
        row_ok = True
        for key, mid in ids.items():
            want = pi.get(uid, {}).get(mid)
            if want is None:
                continue  # tak ada baris (mis. periode lama)
            got = res[key]["amount"]
            if got != want:
                row_ok = False
                mism.append((uid, key, got, want))
        ok += row_ok

    print(f"[{period}] karyawan cocok penuh: {ok}/{len(detail)}; mismatch: {len(mism)}")
    from collections import Counter
    print("  per komisi:", dict(Counter(m[1] for m in mism)))
    for m in mism[:20]:
        print("  user %s %s: engine=%s app=%s" % m)
    return 0 if not mism else 1


if __name__ == "__main__":
    sys.exit(main(sys.argv[1] if len(sys.argv) > 1 else "2026-06"))

"""S4 — compute: jalankan engine kanonik utk satu periode, isi tabel rekap_*.

Sumber kehadiran: tabel presence PRODUKSI (read-only) — sumber yang sama
dengan payroll app, sehingga angka setara halaman payroll. (Upgrade ke
full-.dat menyusul setelah rekap sholat .dat lengkap — sesuai keputusan
user "perbaiki sambil running".) Engine sudah paritas 74/74 vs golden.
"""
import json
from datetime import date, datetime

from .config import period_range, is_no_sc
from .db import SSHTunnel, ai_conn, prod_conn, log_run, replace_period
from .engine.fines import get_fine, _round_php
from .engine.commissions import AUTO_COMM_IDS, auto_commissions
from .engine import payroll as P


def run(period):
    d_from, d_to, days = period_range(period)
    year, month = int(period[:4]), int(period[5:7])
    tunnel = SSHTunnel().start()
    ai = ai_conn()
    finish = log_run(ai, "compute", period)
    try:
        prod = prod_conn(tunnel)
        with prod.cursor() as cur:
            cur.execute(
                """SELECT u.id uid, u.salary, u.salary_minimum, u.ptkp_status,
                          u.join_date, u.overtime_hour_rate, p.branch_id
                   FROM users u JOIN position p ON p.id=u.position_id
                   WHERE u.active='1'""")
            emps = {r["uid"]: r for r in cur.fetchall()}
            cur.execute("SELECT * FROM branch")
            branches = {str(r["id"]): r for r in cur.fetchall()}
            cur.execute("SELECT * FROM insentif WHERE is_active='1'")
            ins_masters = cur.fetchall()
            cur.execute("SELECT * FROM deduction WHERE is_active='1'")
            ded_masters = cur.fetchall()
            cur.execute("SELECT * FROM bpjs_config LIMIT 1")
            bpjs_cfg = cur.fetchone() or {}
            cur.execute("SELECT special_date FROM double_deduction_date WHERE is_active='1'")
            dbl = {str(r["special_date"])[:10] for r in cur.fetchall()}
            cur.execute(
                "SELECT p.* FROM presence p WHERE p.flow_date BETWEEN %s AND %s", (d_from, d_to))
            pres_by = {}
            for r in cur.fetchall():
                pres_by.setdefault(r["user_id"], {})[str(r["flow_date"])[:10]] = r
            cur.execute(
                """SELECT usa.user_id, usa.additional_date, usa.additional_type, s.*
                   FROM users_shift_additional usa LEFT JOIN shift s ON s.id=usa.shift_id
                   WHERE usa.additional_date BETWEEN %s AND %s""", (d_from, d_to))
            sched_by = {}
            for r in cur.fetchall():
                e = dict(r)
                e["type"] = r["additional_type"]
                e["is_no_sc"] = is_no_sc(r.get("shift_code"))
                sched_by.setdefault(r["user_id"], {})[str(r["additional_date"])[:10]] = e
            cur.execute(
                """SELECT user_id, leave_type, leave_range,
                          (leave_proof IS NOT NULL AND leave_proof != '') has_proof,
                          leave_status status FROM `leave`
                   WHERE leave_status='approve' AND leave_start<=%s AND leave_end>=%s""",
                (d_to, d_from))
            leave_by = {}
            for r in cur.fetchall():
                r["has_proof"] = str(r["has_proof"])
                leave_by.setdefault(r["user_id"], []).append(r)
            cur.execute("SELECT * FROM overtime WHERE overtime_date BETWEEN %s AND %s",
                        (d_from, d_to))
            ot_by = {}
            for r in cur.fetchall():
                ot_by.setdefault(r["user_id"], []).append(r)
            cur.execute("SELECT * FROM bpjs_payment WHERE month=%s AND year=%s", (month, year))
            bpjs_by = {r["user_id"]: r for r in cur.fetchall()}
            cur.execute(
                "SELECT * FROM payroll_insentif WHERE insentif_month=%s AND insentif_year=%s "
                "AND deleted_at IS NULL", (month, year))
            pi_by = {}
            for r in cur.fetchall():
                pi_by.setdefault(r["user_id"], {})[int(r["insentif_id"])] = r["insentif_amount"]
            cur.execute(
                "SELECT * FROM payroll_deduction WHERE deduction_month=%s AND deduction_year=%s",
                (month, year))
            pd_by = {}
            for r in cur.fetchall():
                pd_by.setdefault(r["user_id"], []).append(r)
        prod.close()

        today = date.today()
        denda_rows, komisi_rows, payroll_rows = [], [], []
        for uid, u in emps.items():
            bid = str(u["branch_id"])
            br = branches.get(bid, {})
            sched = sched_by.get(uid, {})
            if not sched:
                continue
            pres = pres_by.get(uid, {})
            emp = {"salary": u["salary"], "is_fine_system": br.get("is_fine_system"),
                   "is_pray_system": br.get("is_pray_system"),
                   "ptkp_status": u.get("ptkp_status"), "join_date": u.get("join_date")}
            fine = get_fine(emp, sched, pres, br, dbl, month, year, today=today)

            for t in fine["trace"]:
                denda_rows.append({
                    "period": period, "user_id": uid, "tanggal": t.get("tanggal"),
                    "jenis": t["jenis"], "menit": int(t.get("menit") or 0),
                    "nominal": int(t.get("rp") or 0),
                    "rule_trace": json.dumps(t, default=str)})
            for jenis, val in (("alfa_weekday", fine["amount_in_weekdays"]),
                               ("alfa_weekend", fine["amount_in_weekend"]),
                               ("pulang_awal", fine["amount_early_leave"]),
                               ("sholat", fine["pray_amount"]),
                               ("telat_istirahat", fine["rest_amount"])):
                if val:
                    denda_rows.append({
                        "period": period, "user_id": uid, "tanggal": None,
                        "jenis": jenis, "menit": 0, "nominal": int(round(val)),
                        "rule_trace": None})

            comm = auto_commissions(emp, fine, sched, pres, leave_by.get(uid, []),
                                    bpjs_by.get(uid), bpjs_cfg, d_to)
            ids = AUTO_COMM_IDS.get(bid, AUTO_COMM_IDS["1"])
            for key, mid in ids.items():
                c = comm[key]
                komisi_rows.append({
                    "period": period, "user_id": uid, "insentif_id": mid,
                    "nama": key, "sumber": "auto", "eligible": 1 if c["eligible"] else 0,
                    "nominal": c["amount"],
                    "syarat": json.dumps(c["syarat"], default=str)})

            masters_b = [m for m in ins_masters if str(m.get("branch_id")) == bid]
            ins_total, _ = P.resolve_insentif(masters_b, pi_by.get(uid, {}),
                                              P.full_presence_count(pres))
            dmasters_b = [m for m in ded_masters if str(m.get("branch_id")) == bid]
            ded_total, _ = P.resolve_deduction(dmasters_b, pd_by.get(uid, []))
            ot_hours, ot_amount = P.overtime_total(ot_by.get(uid, []), pres, sched,
                                                   br.get("max_overtime"),
                                                   u.get("overtime_hour_rate"))
            strip = sum(1 for s in sched.values() if s["type"] == "work" and s["is_no_sc"])
            agg = P.aggregate(u["salary"], u["salary_minimum"], fine["amount_in_weekdays"],
                              ot_amount, ins_total, fine["amount"], 0, ded_total, 0,
                              len(days), strip)
            payroll_rows.append({
                "period": period, "user_id": uid,
                "presence_count": P.full_presence_count(pres),
                "presence_max": sum(1 for s in sched.values()
                                    if s["type"] == "work" and not s["is_no_sc"]),
                "salary_in_basic": int(round(agg["payment_receive"])),
                "salary_in_overtime": int(ot_amount),
                "salary_in_insentive": _round_php(ins_total),
                "salary_out_fine": fine["amount"],
                "salary_out_work": 0, "salary_out_together": 0,
                "salary_out_deduction": _round_php(ded_total),
                "salary_basic_out_off_work": int(round(agg["salary_basic_out_off_work"])),
                "salary_basic_out_alfa": int(round(fine["amount_in_weekdays"]
                                                   + fine["amount_in_weekend"])),
                "salary_thp": int(round(agg["salary_thp"])),
                "salary_debt": int(round(agg["salary_debt"])),
                "detail": json.dumps({"ot_hours": ot_hours,
                                      "catatan": "bpjs work/together=0 (input HR saat lock)"}),
            })

        n1 = replace_period(ai, "rekap_denda_harian", period, denda_rows,
                            ["period", "user_id", "tanggal", "jenis", "menit",
                             "nominal", "rule_trace"])
        n2 = replace_period(ai, "rekap_komisi_periode", period, komisi_rows,
                            ["period", "user_id", "insentif_id", "nama", "sumber",
                             "eligible", "nominal", "syarat"])
        n3 = replace_period(ai, "rekap_payroll_periode", period, payroll_rows,
                            ["period", "user_id", "presence_count", "presence_max",
                             "salary_in_basic", "salary_in_overtime", "salary_in_insentive",
                             "salary_out_fine", "salary_out_work", "salary_out_together",
                             "salary_out_deduction", "salary_basic_out_off_work",
                             "salary_basic_out_alfa", "salary_thp", "salary_debt", "detail"])
        stats = {"denda": n1, "komisi": n2, "payroll": n3, "karyawan": len(payroll_rows)}
        finish("ok", stats)
        return stats
    except Exception as e:
        finish("error", {"error": str(e)})
        raise
    finally:
        ai.close()
        tunnel.stop()

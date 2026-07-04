"""Rekonsiliasi L1: rekap_absensi_harian (hasil .dat) vs tabel presence produksi.

Setiap selisih diklasifikasikan; UNCLASSIFIED = perlu investigasi (gerbang
Fase 3 menuntut nol UNCLASSIFIED). Hasil ke tabel rekonsiliasi_diff.
Spec: plan paritas L1 (tests/pipeline/golden/README.md).
"""
import json
from datetime import datetime

from .config import period_range
from .db import SSHTunnel, ai_conn, prod_conn, log_run


def _hm(v):
    """Ambil HH:MM dari datetime/str/timedelta; None bila kosong."""
    if v in (None, ""):
        return None
    if hasattr(v, "strftime"):
        return v.strftime("%H:%M")
    if hasattr(v, "total_seconds"):
        s = int(v.total_seconds()) % 86400
        return f"{s // 3600:02d}:{s % 3600 // 60:02d}"
    s = str(v)
    return s[11:16] if len(s) >= 16 and s[4] == "-" else s[:5]


def run(period):
    d_from, d_to, _ = period_range(period)
    tunnel = SSHTunnel().start()
    ai = ai_conn()
    finish = log_run(ai, "reconcile", period)
    try:
        prod = prod_conn(tunnel)
        with prod.cursor() as cur:
            cur.execute(
                """SELECT p.user_id, p.flow_date, p.entry_time, p.out_time,
                          p.entry_time_late, p.rest_time_in, p.rest_time_out,
                          p.rest_time_late, p.input_by, p.presence_type
                   FROM presence p WHERE p.flow_date BETWEEN %s AND %s""",
                (d_from, d_to),
            )
            prod_rows = {(int(r["user_id"]), str(r["flow_date"])[:10]): r for r in cur.fetchall()}
        prod.close()

        with ai.cursor() as cur:
            cur.execute(
                "SELECT * FROM rekap_absensi_harian WHERE period=%s AND source='dat'", (period,))
            ai_rows = {(int(r["user_id"]), str(r["tanggal"])[:10]): r for r in cur.fetchall()}
            cur.execute(
                "SELECT payload FROM config_snapshot WHERE period=%s AND scope='branch'", (period,))

        fields = [
            ("entry_time", lambda a: _hm(a["entry_time"]), lambda p: _hm(p["entry_time"])),
            ("out_time", lambda a: _hm(a["out_time"]), lambda p: _hm(p["out_time"])),
            ("entry_late", lambda a: int(a["entry_late_m"] or 0), lambda p: int(p["entry_time_late"] or 0)),
            ("rest_in", lambda a: _hm(a["rest_in"]), lambda p: _hm(p["rest_time_in"])),
            ("rest_out", lambda a: _hm(a["rest_out"]), lambda p: _hm(p["rest_time_out"])),
            ("rest_late", lambda a: int(a["rest_late_m"] or 0), lambda p: int(p["rest_time_late"] or 0)),
        ]

        diffs = []
        now = datetime.now()
        both = set(ai_rows) & set(prod_rows)
        only_ai = {k for k, v in ai_rows.items() if (v["entry_time"] or v["out_time"])} - set(prod_rows)
        only_prod = set(prod_rows) - set(ai_rows)

        for key in sorted(both):
            a, p = ai_rows[key], prod_rows[key]
            if p["presence_type"] and p["presence_type"] != "normal":
                continue  # baris izin/sakit/cuti dibuat approval, bukan dari tap
            manual = (p.get("input_by") or "") not in ("system", "machine", "")
            for name, fa, fp in fields:
                va, vp = fa(a), fp(p)
                if va != vp:
                    diffs.append({
                        "period": period, "level": "L1", "user_id": key[0], "tanggal": key[1],
                        "field": name, "nilai_ai": str(va), "nilai_app": str(vp),
                        "klasifikasi": "MANUAL_EDIT" if manual else "UNCLASSIFIED",
                        "keterangan": f"input_by={p.get('input_by')}",
                        "run_at": now,
                    })
        for key in sorted(only_ai):
            diffs.append({
                "period": period, "level": "L1", "user_id": key[0], "tanggal": key[1],
                "field": "row", "nilai_ai": "ADA (dari .dat)", "nilai_app": "TIDAK ADA",
                "klasifikasi": "UNCLASSIFIED", "keterangan": "tap ada tapi presence kosong",
                "run_at": now,
            })
        for key in sorted(only_prod):
            p = prod_rows[key]
            manual = (p.get("input_by") or "") not in ("system", "machine", "")
            diffs.append({
                "period": period, "level": "L1", "user_id": key[0], "tanggal": key[1],
                "field": "row", "nilai_ai": "TIDAK ADA", "nilai_app": "ADA",
                "klasifikasi": "MANUAL_EDIT" if manual else "UNCLASSIFIED",
                "keterangan": f"presence tanpa tap .dat (input_by={p.get('input_by')}, type={p.get('presence_type')})",
                "run_at": now,
            })

        with ai.cursor() as cur:
            cur.execute("DELETE FROM rekonsiliasi_diff WHERE period=%s AND level='L1'", (period,))
            if diffs:
                cur.executemany(
                    "INSERT INTO rekonsiliasi_diff (period, level, user_id, tanggal, field, "
                    "nilai_ai, nilai_app, klasifikasi, keterangan, run_at) VALUES "
                    "(%(period)s,%(level)s,%(user_id)s,%(tanggal)s,%(field)s,%(nilai_ai)s,"
                    "%(nilai_app)s,%(klasifikasi)s,%(keterangan)s,%(run_at)s)",
                    diffs,
                )
        ai.commit()

        summary = {
            "pairs_compared": len(both),
            "diffs": len(diffs),
            "unclassified": sum(1 for d in diffs if d["klasifikasi"] == "UNCLASSIFIED"),
            "manual_edit": sum(1 for d in diffs if d["klasifikasi"] == "MANUAL_EDIT"),
            "only_ai": len(only_ai), "only_prod": len(only_prod),
        }
        finish("ok", summary)
        return summary
    except Exception as e:
        finish("error", {"error": str(e)})
        raise
    finally:
        ai.close()
        tunnel.stop()

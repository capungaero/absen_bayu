"""5 Komisi Otomatis — port murni PayrollSim::_auto_commissions
(application/controllers/PayrollSim.php:676-841). Spec: docs/rules/05-komisi.md.

Sakit dinilai AGREGAT total hari per periode (fix 177aa5d) — bukan per pengajuan.
"""
from datetime import date

from .fines import PRAY_KEYS, hm

# id master insentif per cabang — PayrollSim.php:11-17
AUTO_COMM_IDS = {
    "1": {"disiplin": 12, "transport": 28, "beras": 5, "soskes": 29, "sholat": 9},
    "2": {"disiplin": 18, "transport": 27, "beras": 24, "soskes": 33, "sholat": 21},
}


def masa_months(join_date, ref_date):
    """Masa kerja bulan penuh join_date -> ref_date."""
    if not join_date:
        return 0
    j = date.fromisoformat(str(join_date)[:10])
    m = (ref_date.year - j.year) * 12 + (ref_date.month - j.month)
    if ref_date.day < j.day:
        m -= 1
    return max(0, m)


def auto_commissions(emp, fine_out, schedule, presences, leaves, bpjs_payment,
                     bpjs_config, ref_date):
    """Hitung eligibility+nominal 5 komisi.

    emp: {ptkp_status, join_date, is_pray_system}; fine_out: hasil get_fine();
    leaves: [{leave_type, leave_range, has_proof, status}] overlap periode;
    bpjs_payment: row {pay_mode, status} | None; ref_date: akhir periode.
    Return {key: {eligible, nominal, amount, syarat[]}}.
    """
    mm = masa_months(emp.get("join_date"), ref_date)
    masa_tahun = mm >= 12
    is_pray = str(emp.get("is_pray_system")) == "1"

    # sakit agregat + izin — PayrollSim:691-696 (versi fix agregat)
    sakit_days = sakit_no_proof = izin_cnt = 0
    for l in leaves:
        if (l.get("status") or l.get("leave_status")) != "approve":
            continue
        if l["leave_type"] == "sakit":
            sakit_days += int(l.get("leave_range") or 0)
            if str(l.get("has_proof") or "0") not in ("1", "True"):
                sakit_no_proof += 1
        elif l["leave_type"] == "izin":
            izin_cnt += 1
    sakit_invalid = sakit_days > 2 or sakit_no_proof > 0

    # hadir penuh & rekap sholat — hanya hari non-NO-SC (PayrollSim:698-707)
    pres_cnt = 0
    pray = {k: 0 for k in PRAY_KEYS}
    for diso, att in presences.items():
        s = schedule.get(diso)
        if s and s.get("is_no_sc"):
            continue
        if hm(att.get("entry_time")) and hm(att.get("out_time")):
            pres_cnt += 1
        for k in PRAY_KEYS:
            if hm(att.get(f"{k}_time_in")):
                pray[k] += 1
    dzuhur_eff = pray["dzuhur"] + pray["friday"]
    pray_eff = {"subuh": pray["subuh"], "dzuhur_eff": dzuhur_eff,
                "ashar": pray["ashar"], "maghrib": pray["maghrib"], "isha": pray["isha"]}
    pray_qualify = [k for k, n in pray_eff.items() if n >= 20]

    nosc_days = sum(1 for s in schedule.values() if s.get("is_no_sc"))
    alfa_days = (fine_out["alfa_weekday_days"] + fine_out["alfa_weekend_days"]
                 + fine_out["alfa_special_days"])
    denda_tp = round(fine_out["amount_in_late"] + fine_out["rest_amount"]
                     + fine_out["pray_amount"] + fine_out["amount_early_leave"])

    # ---- DISIPLIN — PayrollSim:724-746
    dis_nom = 150000 if masa_tahun else 100000
    dis_syarat = [
        {"label": "Denda telat+pulang awal ≤ 50rb", "value": denda_tp, "ok": denda_tp <= 50000},
        {"label": "Tidak ada alfa", "value": alfa_days, "ok": alfa_days == 0},
        {"label": "Sakit total ≤2 hari bersurat", "value": sakit_days, "ok": not sakit_invalid},
        {"label": "Tidak ada izin", "value": izin_cnt, "ok": izin_cnt == 0},
        {"label": "Tidak ada NO-SC", "value": nosc_days, "ok": nosc_days == 0},
    ]
    dis_ok = all(c["ok"] for c in dis_syarat)

    # ---- TRANSPORT / BERAS — :748-753
    tr_ok = pres_cnt >= 25
    beras_ok = str(emp.get("ptkp_status") or "").upper().startswith("K")

    # ---- SOSKES — :755-784
    soskes_nom = float((bpjs_config or {}).get("mandiri_insentif") or 0) or 101500
    bp = bpjs_payment or {}
    if bp.get("pay_mode") == "kantor":
        ss_ok = False
    elif bp.get("pay_mode") == "mandiri":
        ss_ok = masa_tahun and bp.get("status") == "approved"
    else:
        ss_ok = False

    # ---- SHOLAT — :721-722, 786-793
    sh_ok = is_pray and len(pray_qualify) >= 2

    def mk(ok, nom, syarat=None):
        return {"eligible": bool(ok), "nominal": int(nom),
                "amount": int(nom) if ok else 0, "syarat": syarat or []}

    return {
        "disiplin": mk(dis_ok, dis_nom, dis_syarat),
        "transport": mk(tr_ok, 100000, [{"label": "hadir penuh ≥25", "value": pres_cnt, "ok": tr_ok}]),
        "beras": mk(beras_ok, 170000),
        "soskes": mk(ss_ok, soskes_nom),
        "sholat": mk(sh_ok, 50000, [{"label": "≥2 jenis ≥20", "value": pray_qualify, "ok": sh_ok}]),
        "_ctx": {"masa_months": mm, "pres_cnt": pres_cnt, "pray": pray},
    }

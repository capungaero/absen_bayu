"""Denda & potongan kehadiran — port murni Presence_model::get_fine
(application/models/Presence_model.php:643-1157). Spec: docs/rules/03-denda.md
& 04-potongan-izin-alfa.md.

Input berbentuk dict polos (loader bebas: tabel absen_ai ATAU golden TSV).
Konvensi nilai: waktu 'HH:MM' string; menit int; rupiah float sampai round akhir.
"""
import calendar
import math
from datetime import date

PRAY_KEYS = ["subuh", "dzuhur", "ashar", "maghrib", "isha", "friday"]


def _round_php(x):
    """PHP round(): half away from zero (Python round = banker's)."""
    return math.floor(x + 0.5) if x >= 0 else math.ceil(x - 0.5)


def tiered(late, base, mult, thr, max_fine):
    """Denda bertingkat — Presence_model.php:939-962 (identik utk rest/pray).
    Termasuk cap: bila max_fine < hasil (termasuk max 0!) hasil = max_fine."""
    fine = float(base or 0)
    late = float(late or 0)
    thr = float(thr or 0)
    if late > thr:
        late -= thr
        count = int(late / thr) if thr > 0 else 0
        fine += count * float(mult or 0)
        if thr > 0 and late % thr > 0:
            fine += float(mult or 0)
    maxf = float(max_fine or 0)
    return maxf if fine > maxf else fine


def hm(v):
    """'YYYY-MM-DD HH:MM:SS'/'HH:MM:SS' -> 'HH:MM'; kosong -> ''."""
    if v in (None, "", "0000-00-00 00:00:00"):
        return ""
    s = str(v)
    if len(s) >= 16 and s[4] == "-":
        return s[11:16]
    return s[:5]


def get_fine(emp, schedule, presences, branch, double_dates, month, year, today=None):
    """Port get_fine. emp: {salary,is_fine_system,is_pray_system}.
    schedule: {date_iso: {type:'work'|'free', code, windows/tarif shift...}}.
    presences: {date_iso: row presence (kolom asli)}. branch: tarif pray.
    double_dates: set(date_iso). Return dict detail paralel struktur PHP.
    """
    today = today or date.today()
    total_day_in_month = calendar.monthrange(year, month)[1]
    salary = float(emp.get("salary") or 0)
    is_fine = str(emp.get("is_fine_system")) == "1"
    is_pray = str(emp.get("is_pray_system")) == "1"

    total_work = sum(1 for s in schedule.values() if s["type"] == "work")
    salary_per_day = (salary / total_day_in_month) if total_work > 0 else 0.0
    salary_per_day_alpha = _round_php(salary / total_day_in_month) if total_work > 0 else 0

    fine = 0.0
    out = {
        "amount_in_late": 0.0, "amount_in_half": 0.0,
        "amount_in_weekend": 0.0, "amount_in_special_double": 0.0,
        "amount_in_weekdays": 0.0, "amount_early_leave": 0,
        "leave_amount": 0.0, "rest_amount": 0.0, "pray_amount": 0.0,
        "early_leave_minutes": 0, "alfa_weekday_days": 0, "alfa_weekend_days": 0,
        "trace": [],
    }

    for diso, att in presences.items():
        adt = schedule.get(diso, {})
        p_type = att.get("presence_type") or ""
        entry = hm(att.get("entry_time"))
        outt = hm(att.get("out_time"))
        entry_late = float(att.get("entry_time_late") or 0)
        rest_in = hm(att.get("rest_time_in"))
        rest_out = hm(att.get("rest_time_out"))
        rest_late = float(att.get("rest_time_late") or 0)

        # ---- PRAY FINE (gate is_pray_system) — :833-899
        if is_pray:
            for key in PRAY_KEYS:
                pl = float(att.get(f"{key}_time_late") or 0)
                pi = hm(att.get(f"{key}_time_in"))
                po = hm(att.get(f"{key}_time_out"))
                pf = 0.0
                pmax = float(branch.get("pray_late_fix_rate") or 0)
                if pl > 0:
                    pf = tiered(pl, branch.get("pray_late_start_rate"),
                                branch.get("pray_late_multiple_rate"),
                                branch.get("pray_late_multiple_count"), pmax)
                elif pi and not po:
                    pf = pmax
                fine += pf
                out["pray_amount"] += _round_php(pf)

        # ---- PRESENCE FINE (gate is_fine_system, normal) — :934-1031
        if is_fine and p_type == "normal":
            if entry_late > 0:
                pf = tiered(entry_late, adt.get("late_amount_start"),
                            adt.get("late_amount_multiple_start"),
                            adt.get("late_multiple_count_start"),
                            adt.get("late_amount_max_start"))
                fine += pf
                out["amount_in_late"] += pf
                out["trace"].append({"jenis": "telat_masuk", "tanggal": diso,
                                     "menit": entry_late, "rp": _round_php(pf)})
            if (entry and not outt) or (not entry and outt):
                half = _round_php(salary_per_day / 2)
                fine += half
                out["amount_in_half"] += half
                out["trace"].append({"jenis": "setengah_hari", "tanggal": diso, "rp": half})
            if rest_late > 0:
                rf = tiered(rest_late, adt.get("late_amount_rest"),
                            adt.get("late_amount_multiple_rest"),
                            adt.get("late_multiple_count_rest"),
                            adt.get("late_amount_max_rest"))
                fine += rf
                out["rest_amount"] += rf
            elif rest_in and not rest_out:
                rf = float(adt.get("late_amount_max_rest") or 0)
                fine += rf
                out["rest_amount"] += rf

            # early leave: akumulasi menit — :1034-1043 (rupiah setelah loop)
            if str(att.get("is_early_leave") or "0") == "1":
                sm = int(att.get("early_leave_short_minutes") or 0)
                if sm > 0:
                    out["early_leave_minutes"] += sm

        # ---- LEAVE FINE — :1045-1071
        if is_fine and p_type not in ("", "normal"):
            pct = 100 - float(att.get("presence_get_paid") or 0)
            if pct > 0:
                amt = _round_php((pct / 100) * salary_per_day)
                if date.fromisoformat(diso).weekday() in (5, 6):  # Sabtu/Minggu
                    amt *= 2
                fine += amt
                out["leave_amount"] += amt
                out["trace"].append({"jenis": "potongan_izin", "tanggal": diso,
                                     "tipe": p_type, "pct": pct, "rp": amt})

    # ---- EARLY LEAVE rupiah — presence_helper.php:118-133, :1075-1080
    if out["early_leave_minutes"] > 0:
        hourly = (salary / total_day_in_month / 10.0) if salary > 0 and total_day_in_month > 0 else 0.0
        amt = int(hourly * (out["early_leave_minutes"] / 60.0))
        out["amount_early_leave"] = amt
        fine += amt

    # ---- ALFA / WEEKEND / SPECIAL DOUBLE — :1097-1144
    for diso, s in schedule.items():
        d = date.fromisoformat(diso)
        is_absent_work = (s["type"] == "work" and diso not in presences
                          and today >= d and not s.get("is_no_sc"))
        is_weekend = d.weekday() in (5, 6)
        is_special = diso in double_dates
        if is_absent_work and (is_weekend or is_special):
            dbl = salary_per_day_alpha * 2
            if is_special:
                out["amount_in_special_double"] += dbl
            else:
                out["alfa_weekend_days"] += 1
            out["amount_in_weekend"] += dbl  # gabungan (lihat catatan :1128-1131)
            fine += dbl
        elif is_absent_work and not is_weekend and not is_special:
            out["alfa_weekday_days"] += 1
            out["amount_in_weekdays"] += salary_per_day_alpha
            fine += salary_per_day_alpha

    out["amount"] = _round_php(fine)
    return out

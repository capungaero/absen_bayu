"""Resolusi insentif/potongan/lembur + agregasi THP.

Port murni dari:
- get_insentif  : application/models/Presence_model.php:1159-1226
- get_deduction : application/models/Presence_model.php:1228-1280
- lembur        : application/models/Presence_model.php:250-252, 349-368
- agregasi THP  : application/controllers/hr/Payroll.php:246-296
Spec: docs/rules/05-komisi.md & 08-payroll-aggregation.md.
"""
from .fines import _round_php, hm

EARLY_LEAVE_AUTO_NOTE = "Potongan Izin Pulang Lebih Awal (auto)"


def full_presence_count(presences):
    """Hadir penuh (entry & out terisi) — dipakai formula per_presence.
    Port :1162-1169 (get_insentif menghitung dari SEMUA baris presence,
    TANPA filter NO-SC — beda dgn Komisi Transport PayrollSim)."""
    n = 0
    for att in presences.values():
        if hm(att.get("entry_time")) and hm(att.get("out_time")):
            n += 1
    return n


def resolve_insentif(masters, manual_by_insentif, presence_full_count):
    """Port get_insentif: baris payroll_insentif MENANG; selain itu formula.
    masters: [{id, insentif_name, formula, nominal}] (branch, is_active=1).
    manual_by_insentif: {insentif_id: amount}. Return (total, list)."""
    total = 0.0
    items = []
    for m in sorted(masters, key=lambda r: (r.get("insentif_name") or "")):
        mid = int(m["id"])
        if mid in manual_by_insentif:
            amount = float(manual_by_insentif[mid] or 0)
        elif (m.get("formula") or "none") != "none":
            amount = (presence_full_count * float(m.get("nominal") or 0)
                      if m["formula"] == "per_presence" else float(m.get("nominal") or 0))
        else:
            amount = 0.0
        items.append({"insentif_id": mid, "name": m.get("insentif_name"),
                      "amount": _round_php(amount)})
        total += amount
    return total, items


def resolve_deduction(masters, manual_rows):
    """Port get_deduction: nilai dari payroll_deduction (0 bila tak ada);
    baris ber-note auto pulang-awal DIKECUALIKAN (:1249-1251)."""
    by_id = {}
    for r in manual_rows:
        if (r.get("deduction_note") or "") == EARLY_LEAVE_AUTO_NOTE:
            continue
        by_id[int(r["deduction_id"])] = float(r.get("deduction_amount") or 0)
    total = 0.0
    items = []
    for m in sorted(masters, key=lambda r: (r.get("deduction_name") or "")):
        amount = by_id.get(int(m["id"]), 0.0)
        items.append({"deduction_id": int(m["id"]), "name": m.get("deduction_name"),
                      "amount": _round_php(amount)})
        total += amount
    return total, items


def _diff_hours(start, end):
    """differenceInHours(shift start, end) — jam desimal."""
    if not start or not end:
        return 0.0
    s = str(start)[:5]
    e = str(end)[:5]
    sm = int(s[:2]) * 60 + int(s[3:5])
    em = int(e[:2]) * 60 + int(e[3:5])
    if em < sm:
        em += 24 * 60
    return (em - sm) / 60.0


def overtime_total(overtime_rows, presences, schedule, max_overtime, rate):
    """Port :250-252 + :349-368.
    total = SUM(min(overtime_hour, max_overtime)) status approve
          + jam shift utk baris presence is_overtime='1' (normal).
    amount = round(total × overtime_hour_rate)."""
    maxo = float(max_overtime or 0)
    total = 0.0
    for r in overtime_rows:
        if (r.get("overtime_status") or "") != "approve":
            continue
        h = float(r.get("overtime_hour") or 0)
        total += maxo if h > maxo else h
    for diso, att in presences.items():
        if (att.get("presence_type") or "") == "normal" and str(att.get("is_overtime") or "0") == "1":
            s = schedule.get(diso, {})
            total += _diff_hours(s.get("start_time"), s.get("end_time"))
    total = round(total, 2)
    return total, _round_php(total * float(rate or 0))


def aggregate(salary, salary_minimum, alpha_weekdays_amount, overtime_amount,
              insentif_total, fine_total, bpjs_work, deduction_total,
              bpjs_together, period_total_day, strip):
    """Port hr/Payroll.php:246-296 (Lock Gaji per karyawan)."""
    salary = float(salary or 0)
    tmp_receive = salary - float(alpha_weekdays_amount or 0)
    # ⚠️ fallback ke SALARY penuh (bukan tmp) — perilaku produksi apa adanya
    payment_receive = float(salary_minimum or 0) if tmp_receive < float(salary_minimum or 0) else salary

    salary_per_day = (salary / period_total_day) if period_total_day > 0 else 0.0
    off_work = salary_per_day * (strip or 0)

    thp = (payment_receive + float(overtime_amount or 0) + float(insentif_total or 0)) - (
        float(fine_total or 0) + float(bpjs_work or 0) + float(deduction_total or 0)
        + float(bpjs_together or 0) + off_work)
    salary_debt = thp if thp < 0 else 0.0
    thp = 0.0 if thp < 0 else thp
    return {
        "payment_receive": payment_receive,
        "salary_basic_out_off_work": off_work,
        "salary_thp": thp,
        "salary_debt": salary_debt,
    }

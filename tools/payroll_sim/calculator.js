// Replikasi logika get_fine() dari Presence_model.php

const PRAY_KEYS = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];
const PRAY_LABEL = { subuh:'Subuh', dzuhur:'Dzuhur', ashar:'Ashar', maghrib:'Maghrib', isha:'Isya', friday:'Jumat' };
const NO_SCHEDULE_CODES = ['-', 'NO-SC', 'NO SCHEDULE'];

// Master insentif id per komisi otomatis (id branch SDR=1 & GBR=2)
const AUTO_COMM = {
  disiplin:  { ids: [12, 18], canonical: 'Komisi Disiplin Kehadiran' },
  transport: { ids: [27, 28], canonical: 'Komisi Transport' },
  beras:     { ids: [5, 24],  canonical: 'Komisi Beras' },
  soskes:    { ids: [29, 33], canonical: 'Komisi Soskes' },
  sholat:    { ids: [9, 21],  canonical: 'Komisi Sholat' },
};
const AUTO_COMM_BY_ID = {};
for (const [key, cfg] of Object.entries(AUTO_COMM)) {
  for (const id of cfg.ids) AUTO_COMM_BY_ID[id] = { key, canonical: cfg.canonical };
}

function isNoSchedule(code) {
  return NO_SCHEDULE_CODES.includes(String(code || '').toUpperCase().trim());
}

function daysInMonth(month, year) {
  return new Date(year, month, 0).getDate();
}

// Periode payroll: 26 bulan lalu s/d 25 bulan ini
function getPayrollPeriod(month, year) {
  const prevMonth = month === 1 ? 12 : month - 1;
  const prevYear  = month === 1 ? year - 1 : year;
  const from = `${prevYear}-${String(prevMonth).padStart(2,'0')}-26`;
  const to   = `${year}-${String(month).padStart(2,'0')}-25`;
  const list = [];
  let cur = new Date(from + 'T00:00:00');
  const end = new Date(to   + 'T00:00:00');
  while (cur <= end) {
    list.push(cur.toISOString().slice(0,10));
    cur.setDate(cur.getDate() + 1);
  }
  return { from, to, list };
}

// Denda berjenjang (dipakai untuk entry late, rest late, pray late)
function tieredFine(lateMin, base, mult, threshold, maxFine) {
  if (lateMin <= 0 || base <= 0) return 0;
  let fine = base;
  if (threshold > 0 && lateMin > threshold) {
    const rem = lateMin - threshold;
    fine += Math.ceil(rem / threshold) * mult;
  }
  return Math.min(fine, maxFine || Infinity);
}

// Gaji per jam = salary / days / 10
function hourlyRate(salary, days) {
  if (!salary || !days) return 0;
  return salary / days / 10;
}

export async function calculateSalary(db, employeeId, month, year, customItems = []) {
  const totalDaysInMonth = daysInMonth(month, year);
  const period = getPayrollPeriod(month, year);
  const today = new Date().toISOString().slice(0,10);

  // 1. Employee + position + branch
  const [empRows] = await db.query(`
    SELECT u.*, p.position_name,
           b.id AS branch_id, b.branch_name,
           b.is_fine_system, b.is_pray_system, b.max_overtime,
           b.pray_late_fix_rate, b.pray_late_multiple_count,
           b.pray_late_start_rate, b.pray_late_multiple_rate
    FROM users u
    JOIN position p ON p.id = u.position_id
    JOIN branch  b ON b.id = p.branch_id
    WHERE u.id = ?
  `, [employeeId]);

  if (!empRows.length) throw new Error('Mitra Kerja tidak ditemukan');
  const emp = empRows[0];
  const salary = Number(emp.salary) || 0;
  const salaryMin = Number(emp.salary_minimum) || 0;

  // Masa kerja (bulan) dihitung ke akhir periode payroll
  let masaKerjaMonths = 0;
  if (emp.join_date && emp.join_date !== '0000-00-00') {
    const jd  = new Date(emp.join_date);
    const ref = new Date(`${year}-${String(month).padStart(2,'0')}-25T00:00:00`);
    masaKerjaMonths = Math.max(0, Math.floor((ref - jd) / (1000 * 60 * 60 * 24 * 30.44)));
  }
  // Status menikah dari PTKP: 'K/x' = kawin, 'TK/x' = tidak kawin
  const ptkpUp  = String(emp.ptkp_status || '').trim().toUpperCase();
  const isMarried = ptkpUp.startsWith('K');

  // 2. Shift schedule (additional attendance) dalam periode payroll
  const [addRows] = await db.query(`
    SELECT DATE_FORMAT(usa.additional_date, '%Y-%m-%d') AS additional_date,
           usa.additional_type, usa.shift_id,
           s.shift_code, s.shift_name,
           s.late_amount_start, s.late_amount_multiple_start, s.late_multiple_count_start, s.late_amount_max_start,
           s.late_amount_rest,  s.late_amount_multiple_rest,  s.late_multiple_count_rest,  s.late_amount_max_rest
    FROM users_shift_additional usa
    JOIN shift s ON s.id = usa.shift_id
    WHERE usa.user_id = ? AND usa.additional_date BETWEEN ? AND ?
  `, [employeeId, period.from, period.to]);

  const additionalMap = {};
  let totalWork = 0, strip = 0;
  for (const r of addRows) {
    additionalMap[r.additional_date] = r;
    if (r.additional_type === 'work') {
      totalWork++;
      if (isNoSchedule(r.shift_code)) strip++;
    }
  }
  const totalDayPeriod = period.list.length;
  const salaryPerDay     = totalDayPeriod > 0 ? salary / totalDayPeriod : 0;
  const salaryPerDayAlpha = totalDaysInMonth > 0 ? salary / totalDaysInMonth : 0;

  // 3. Attendance records dalam periode payroll
  const [attRows] = await db.query(`
    SELECT * FROM presence
    WHERE user_id = ? AND flow_date BETWEEN ? AND ?
      AND presence_status = 'approved'
  `, [employeeId, period.from, period.to]);

  const attMap = {};
  for (const r of attRows) attMap[r.flow_date] = r;

  // 4. Double deduction dates (tanggal khusus denda ganda)
  const [dblRows] = await db.query(`
    SELECT DATE_FORMAT(special_date, '%Y-%m-%d') AS special_date, description
    FROM double_deduction_date
    WHERE branch_id = ? AND special_date BETWEEN ? AND ? AND is_active = 1
  `, [emp.branch_id, period.from, period.to]);
  const doubleDeductDates = {};
  for (const r of dblRows) doubleDeductDates[r.special_date] = r.description;

  // 5. Hitung denda per hari
  let fineTotal = 0;
  const fineBreakdown = {
    lateFine: 0, halfFine: 0, restFine: 0, prayFine: 0,
    leaveFine: 0, alfaWeekday: 0, alfaWeekend: 0, alfaSpecial: 0,
    earlyLeaveFine: 0,
  };
  const fineLog = { late: [], rest: [], pray: [], alfa: [], leave: [] };

  let totalEarlyLeaveMinutes = 0;

  const isFineSystem = String(emp.is_fine_system) === '1';
  const isPraySystem = String(emp.is_pray_system) === '1';

  for (const att of Object.values(attMap)) {
    const date = att.flow_date;
    const adt  = additionalMap[date];
    if (!adt) continue;
    if (isNoSchedule(adt.shift_code)) continue;

    const entryLate = Number(att.entry_time_late) || 0;
    const restLate  = Number(att.rest_time_late)  || 0;

    // --- DENDA KEHADIRAN ---
    if (isFineSystem && att.presence_type === 'normal') {
      // Terlambat masuk
      if (entryLate > 0) {
        const f = tieredFine(entryLate,
          Number(adt.late_amount_start), Number(adt.late_amount_multiple_start),
          Number(adt.late_multiple_count_start), Number(adt.late_amount_max_start));
        fineBreakdown.lateFine += f;
        fineTotal += f;
        fineLog.late.push({ date, minutes: entryLate, amount: f });
      }

      // Setengah hadir (entry XOR out)
      const hasEntry = att.entry_time && att.entry_time !== '';
      const hasOut   = att.out_time   && att.out_time   !== '';
      if ((hasEntry && !hasOut) || (!hasEntry && hasOut)) {
        const half = Math.round(salaryPerDay / 2);
        fineBreakdown.halfFine += half;
        fineTotal += half;
        fineLog.late.push({ date, minutes: 0, amount: half, note: '½ hari' });
      }

      // Terlambat istirahat
      if (restLate > 0) {
        const f = tieredFine(restLate,
          Number(adt.late_amount_rest), Number(adt.late_amount_multiple_rest),
          Number(adt.late_multiple_count_rest), Number(adt.late_amount_max_rest));
        fineBreakdown.restFine += f;
        fineTotal += f;
        fineLog.rest.push({ date, minutes: restLate, amount: f });
      } else if (att.rest_time_in && !att.rest_time_out) {
        const f = Number(adt.late_amount_max_rest) || 0;
        fineBreakdown.restFine += f;
        fineTotal += f;
        fineLog.rest.push({ date, minutes: 0, amount: f, note: 'Keluar istirahat tak tercatat' });
      }

      // Pulang awal
      const earlyMin = Number(att.early_leave_short_minutes) || 0;
      if (att.is_early_leave && earlyMin > 0) {
        totalEarlyLeaveMinutes += earlyMin;
      }
    }

    // --- DENDA SHOLAT ---
    if (isPraySystem) {
      const prayBase  = Number(emp.pray_late_start_rate)    || 0;
      const prayMult  = Number(emp.pray_late_multiple_rate) || 0;
      const prayThres = Number(emp.pray_late_multiple_count)|| 0;
      const prayMax   = Number(emp.pray_late_fix_rate)      || 0;

      for (const key of PRAY_KEYS) {
        const prayLate = Number(att[`${key}_time_late`]) || 0;
        const prayIn   = att[`${key}_time_in`]  || '';
        const prayOut  = att[`${key}_time_out`] || '';
        let pf = 0;
        if (prayLate > 0) {
          pf = tieredFine(prayLate, prayBase, prayMult, prayThres, prayMax);
        } else if (prayIn && !prayOut) {
          pf = prayMax;
        }
        if (pf > 0) {
          fineBreakdown.prayFine += pf;
          fineTotal += pf;
          fineLog.pray.push({ date, waktu: key, minutes: prayLate, amount: pf });
        }
      }
    }

    // --- DENDA CUTI/IZIN ---
    if (isFineSystem && att.presence_type !== 'normal') {
      const pct = 100 - (Number(att.presence_get_paid) || 100);
      if (pct > 0) {
        const isWeekend = [0, 6].includes(new Date(date + 'T00:00:00').getDay());
        let lf = Math.round((pct / 100) * salaryPerDay) * (isWeekend ? 2 : 1);
        fineBreakdown.leaveFine += lf;
        fineTotal += lf;
        fineLog.leave.push({ date, type: att.presence_type, pct, amount: lf });
      }
    }
  }

  // Pulang awal (kumulatif)
  if (totalEarlyLeaveMinutes > 0) {
    const earlyFine = Math.floor(hourlyRate(salary, totalDaysInMonth) * (totalEarlyLeaveMinutes / 60));
    fineBreakdown.earlyLeaveFine = earlyFine;
    fineTotal += earlyFine;
  }

  // --- ALFA ---
  for (const date of period.list) {
    if (date > today) continue;
    const adt = additionalMap[date];
    if (!adt || adt.additional_type !== 'work' || isNoSchedule(adt.shift_code)) continue;
    if (attMap[date]) continue; // hadir

    const isWeekend  = [0, 6].includes(new Date(date + 'T00:00:00').getDay());
    const isSpecial  = !!doubleDeductDates[date];

    if (isWeekend || isSpecial) {
      const fine = Math.round(salaryPerDayAlpha * 2);
      fineTotal += fine;
      fineBreakdown[isSpecial ? 'alfaSpecial' : 'alfaWeekend'] += fine;
      fineLog.alfa.push({ date, type: isSpecial ? 'special' : 'weekend', amount: fine });
    } else {
      const fine = Math.round(salaryPerDayAlpha);
      fineTotal += fine;
      fineBreakdown.alfaWeekday += fine;
      fineLog.alfa.push({ date, type: 'weekday', amount: fine });
    }
  }

  // 6. Insentif
  const [insentifMaster] = await db.query(
    `SELECT *, id AS master_id FROM insentif WHERE branch_id = ? AND is_active = '1' ORDER BY insentif_name`,
    [emp.branch_id]
  );
  const [payrollIns] = await db.query(
    `SELECT pi.* FROM payroll_insentif pi
     JOIN users u ON u.id = pi.user_id
     JOIN position p ON p.id = u.position_id
     WHERE p.branch_id = ? AND pi.user_id = ? AND pi.insentif_month = ? AND pi.insentif_year = ?`,
    [emp.branch_id, employeeId, month, year]
  );
  const insOverride = {};
  for (const r of payrollIns) insOverride[r.insentif_id] = Number(r.insentif_amount);

  const insentifList = [];
  let insentifTotal = 0;
  // Hadir penuh (masuk+pulang) — kecualikan tanggal berjadwal NO-SC, sama seperti
  // perhitungan denda. Tap yang kejadian saat NO-SC tak boleh ikut menaikkan
  // kelayakan komisi per-hadir (Transport, dll).
  let presenceCount = 0;
  for (const att of Object.values(attMap)) {
    const adt = additionalMap[att.flow_date];
    if (adt && isNoSchedule(adt.shift_code)) continue;
    if (att.entry_time && att.out_time) presenceCount++;
  }
  const periodStr = `${year}-${String(month).padStart(2,'0')}`;

  // ── Komisi otomatis: hitung kelayakan & nominal dari aturan ──
  const autoCommissions = await buildAutoCommissions(db, {
    employeeId, period, attMap, additionalMap, fineBreakdown, fineLog,
    presenceCount, masaKerjaMonths, isMarried, isPraySystem,
  });

  for (const ins of insentifMaster) {
    const auto = AUTO_COMM_BY_ID[ins.master_id]
      ? autoCommissions[AUTO_COMM_BY_ID[ins.master_id].key]
      : null;

    // ── Item komisi otomatis (override semua logika manual) ──
    if (auto) {
      const canonical = AUTO_COMM_BY_ID[ins.master_id].canonical;
      insentifList.push({
        id: ins.master_id, name: canonical, formula: 'auto_commission',
        nominal: auto.nominal, amount: auto.amount, active: auto.eligible,
        isAuto: true, autoCalc: true, hasOverride: false,
        eligible: auto.eligible, conditions: auto.conditions,
        formulaDetail: auto.formulaDetail, source: auto.source,
        logItems: auto.logItems || [],
      });
      insentifTotal += auto.amount;
      continue;
    }

    const hasOverride = insOverride[ins.master_id] !== undefined;
    let amount = 0;
    if (hasOverride) {
      amount = insOverride[ins.master_id];
    } else if (ins.formula === 'per_presence') {
      amount = presenceCount * Number(ins.nominal);
    } else if (ins.formula !== 'none') {
      amount = Number(ins.nominal);
    }
    amount = Math.round(amount);

    // Tentukan detail formula untuk display
    let formulaDetail, source;
    if (ins.formula === 'per_presence' && !hasOverride) {
      source = 'Auto — formula per hadir';
      formulaDetail = `Rp ${Number(ins.nominal).toLocaleString('id-ID')} × ${presenceCount} hadir = Rp ${amount.toLocaleString('id-ID')}`;
    } else if (ins.formula !== 'none' && !hasOverride) {
      source = 'Auto — nominal tetap';
      formulaDetail = `Nominal tetap: Rp ${Number(ins.nominal).toLocaleString('id-ID')}`;
    } else if (hasOverride) {
      source = `HR input ${periodStr}`;
      formulaDetail = `Rp ${amount.toLocaleString('id-ID')} (diisi HR periode ini)`;
    } else {
      source = 'Tidak ada nilai';
      formulaDetail = 'Tidak ada nilai untuk periode ini';
    }

    insentifList.push({
      id: ins.master_id, name: ins.insentif_name, formula: ins.formula,
      nominal: Number(ins.nominal), amount, active: true,
      isAuto: true,                          // semua item DB = auto (read-only)
      autoCalc: ins.formula !== 'none' && !hasOverride,  // dihitung formula sistem
      hasOverride, formulaDetail, source,
    });
    insentifTotal += amount;
  }

  // 7. Deduction
  const [deductMaster] = await db.query(
    `SELECT *, id AS master_id FROM deduction WHERE branch_id = ? AND is_active = '1' ORDER BY deduction_name`,
    [emp.branch_id]
  );
  const [payrollDed] = await db.query(
    `SELECT pd.* FROM payroll_deduction pd
     JOIN users u ON u.id = pd.user_id
     JOIN position p ON p.id = u.position_id
     WHERE p.branch_id = ? AND pd.user_id = ? AND pd.deduction_month = ? AND pd.deduction_year = ?`,
    [emp.branch_id, employeeId, month, year]
  );
  const dedOverride = {};
  for (const r of payrollDed) dedOverride[r.deduction_id] = { amount: Number(r.deduction_amount), note: r.deduction_note };

  const deductionList = [];
  let deductionTotal = 0;
  for (const ded of deductMaster) {
    const ov = dedOverride[ded.master_id];
    const amount = ov ? Math.round(ov.amount) : 0;
    const note   = ov ? (ov.note || '') : '';
    const formulaDetail = ov
      ? `Rp ${amount.toLocaleString('id-ID')} (diisi HR periode ini)${note ? ` — ${note}` : ''}`
      : 'Tidak ada entry untuk periode ini';
    deductionList.push({
      id: ded.master_id, name: ded.deduction_name, amount, note, active: true,
      isAuto: true, autoCalc: false, hasOverride: !!ov,
      formulaDetail, source: ov ? `HR input ${periodStr}` : 'Tidak ada entry',
    });
    deductionTotal += amount;
  }

  // 8. Overtime
  const maxOt = Number(emp.max_overtime) || 9999;
  const otRate = Number(emp.overtime_hour_rate) || 0;
  const [otRow] = await db.query(`
    SELECT SUM(CASE WHEN overtime_hour > ? THEN ? ELSE overtime_hour END) AS hrs
    FROM overtime
    WHERE user_id = ? AND overtime_date BETWEEN ? AND ? AND overtime_status = 'approve'
  `, [maxOt, maxOt, employeeId, period.from, period.to]);
  const overtimeHours  = parseFloat(otRow[0].hrs) || 0;
  const overtimeAmount = Math.round(overtimeHours * otRate);

  // 9. Strip potongan (no-schedule days) — pakai salaryPerDayAlpha (gaji / hari kalender
  // sebulan), sama seperti Presence_model::get_fine() ($salary_per_day = salary / totalDayInMonth).
  const stripDeduction = Math.round(salaryPerDayAlpha * strip);

  // 10. Payment receive (floor ke salary_minimum jika alfa berat)
  const alfaWeekdays = fineBreakdown.alfaWeekday;
  const paymentReceive = (salary - alfaWeekdays) < salaryMin ? salaryMin : salary;

  // 11. Custom items dari UI
  let customBonusTotal = 0, customDeductTotal = 0;
  const customBonus  = customItems.filter(x => x.type === 'bonus');
  const customDeduct = customItems.filter(x => x.type === 'deduction');
  for (const x of customBonus)  customBonusTotal  += Number(x.amount) || 0;
  for (const x of customDeduct) customDeductTotal += Number(x.amount) || 0;

  // 12. THP
  const totalIncome  = paymentReceive + overtimeAmount + insentifTotal + customBonusTotal;
  const totalOutcome = fineTotal + deductionTotal + stripDeduction + customDeductTotal;
  const thpRaw = totalIncome - totalOutcome;
  const thp    = Math.max(0, thpRaw);
  const debt   = thpRaw < 0 ? Math.abs(thpRaw) : 0;

  // Masa kerja (string) — konsisten dgn masaKerjaMonths (acuan akhir periode)
  const masaKerja = emp.join_date && emp.join_date !== '0000-00-00'
    ? `${Math.floor(masaKerjaMonths / 12)} thn ${masaKerjaMonths % 12} bln`
    : '-';

  return {
    employee: {
      id: emp.id, name: `${emp.first_name} ${emp.last_name || ''}`.trim(),
      code: emp.employee_code, position: emp.position_name,
      branch: emp.branch_name, joinDate: emp.join_date, masaKerja,
      salary, salaryMin, ptkpStatus: emp.ptkp_status,
      isFineSystem, isPraySystem,
    },
    period: { month, year, from: period.from, to: period.to },
    income: {
      gajiPokok: paymentReceive,
      overtime: overtimeAmount, overtimeHours,
      insentif: insentifTotal, insentifList,
      customBonus: customBonusTotal,
      total: totalIncome,
    },
    outcome: {
      fine: fineTotal, fineBreakdown, fineLog,
      deduction: deductionTotal, deductionList,
      strip: stripDeduction,
      customDeduct: customDeductTotal,
      total: totalOutcome,
    },
    summary: { thp, debt, totalIncome, totalOutcome },
  };
}

const rp = n => 'Rp ' + Math.round(n || 0).toLocaleString('id-ID');

// ── Hitung kelayakan & nominal 5 komisi otomatis ──
async function buildAutoCommissions(db, ctx) {
  const {
    employeeId, period, attMap, additionalMap, fineBreakdown, fineLog,
    presenceCount, masaKerjaMonths, isMarried, isPraySystem,
  } = ctx;

  const masaTahun = masaKerjaMonths >= 12;
  const masaStr   = `${Math.floor(masaKerjaMonths / 12)} thn ${masaKerjaMonths % 12} bln`;

  // 1. Cuti/izin/sakit (approved) yang overlap periode
  const [leaveRows] = await db.query(
    `SELECT leave_type, leave_range,
            (leave_proof IS NOT NULL AND leave_proof != '') AS has_proof
     FROM \`leave\`
     WHERE user_id = ? AND leave_status = 'approve'
       AND leave_start <= ? AND leave_end >= ?`,
    [employeeId, period.to, period.from]
  );
  // Sakit ditoleransi hanya bila TOTAL hari sakit dlm periode maksimal 2 hari + semua
  // ada bukti — dihitung agregat, bukan per pengajuan (cegah lolos dgn memecah sakit
  // jadi beberapa pengajuan ≤2 hari yg masing2 valid).
  const sakitRows = leaveRows.filter(l => l.leave_type === 'sakit');
  const sakitDays = sakitRows.reduce((sum, l) => sum + Number(l.leave_range), 0);
  const sakitNoProof = sakitRows.some(l => Number(l.has_proof) !== 1);
  const sakitInvalid = (sakitDays > 2 || sakitNoProof) ? sakitRows : [];
  const izinRows = leaveRows.filter(l => l.leave_type === 'izin');

  // 2. Rekap sholat per jenis dari finger — kecualikan tanggal berjadwal NO-SC,
  // sama seperti perhitungan denda & hadir (tap saat NO-SC tak boleh ikut hitung).
  const prayCounts = { subuh:0, dzuhur:0, ashar:0, maghrib:0, isha:0, friday:0 };
  for (const att of Object.values(attMap)) {
    const adt = additionalMap[att.flow_date];
    if (adt && isNoSchedule(adt.shift_code)) continue;
    for (const key of PRAY_KEYS) {
      const v = att[`${key}_time_in`];
      if (v && v !== '') prayCounts[key]++;
    }
  }
  const prayQualify = PRAY_KEYS.filter(k => prayCounts[k] >= 20);
  const sholatEligible = isPraySystem && prayQualify.length >= 2;

  // ── DISIPLIN KEHADIRAN ──
  // Akumulasi denda keterlambatan: kehadiran + istirahat + sholat + pulang awal
  const dendaTelatPulang = Math.round(
    (fineBreakdown.lateFine       || 0) +
    (fineBreakdown.restFine       || 0) +
    (fineBreakdown.prayFine       || 0) +
    (fineBreakdown.earlyLeaveFine || 0)
  );
  const alfaDays = (fineLog.alfa || []).length;
  const noscDays = period.list.filter(date => {
    const adt = additionalMap[date];
    return adt && isNoSchedule(adt.shift_code);
  }).length;
  const disNominal = masaTahun ? 150000 : 100000;
  const disCond = [
    { label: `Akumulasi denda telat (hadir+istirahat+sholat) + pulang awal ≤ Rp50.000`, value: `aktual ${rp(dendaTelatPulang)}`, ok: dendaTelatPulang <= 50000 },
    { label: `Tidak ada alfa`, value: `${alfaDays} hari alfa`, ok: alfaDays === 0 },
    { label: `Sakit terdokumentasi (maksimal 2 hari, wajib disertai surat ijin)`, value: sakitInvalid.length ? `${sakitDays} hari sakit${sakitNoProof ? ' (ada tanpa surat)' : ''}` : 'OK', ok: sakitInvalid.length === 0 },
    { label: `Tidak ada izin (no-schedule)`, value: izinRows.length ? `${izinRows.length} izin` : 'OK', ok: izinRows.length === 0 },
    { label: `Tidak ada NO-SC`, value: noscDays ? `${noscDays} hari NO-SC` : 'OK', ok: noscDays === 0 },
  ];
  const disEligible = disCond.every(c => c.ok);

  // ── TRANSPORT ──
  const trCond = [
    { label: `Hadir penuh (masuk+pulang) ≥ 25×`, value: `aktual ${presenceCount}×`, ok: presenceCount >= 25 },
  ];
  const trEligible = trCond.every(c => c.ok);

  // ── BERAS ──
  const brCond = [
    { label: `Status menikah (PTKP K/…)`, value: isMarried ? 'menikah' : 'belum menikah', ok: isMarried },
  ];
  const brEligible = brCond.every(c => c.ok);

  // ── SOSKES ──
  const ssCond = [
    { label: `Masa kerja ≥ 1 tahun`, value: `aktual ${masaStr}`, ok: masaTahun },
    { label: `Iuran BPJS TK BPU dibayar ≤ tgl 15`, value: 'diasumsikan (tak ada data sistem)', ok: true, assumed: true },
  ];
  const ssEligible = masaTahun; // syarat BPJS diasumsikan terpenuhi

  // ── SHOLAT ──
  const shCond = [
    { label: `≥2 jenis sholat, masing-masing ≥20 record (≥40 total)`,
      value: prayQualify.length >= 2 ? `${prayQualify.map(k => PRAY_LABEL[k]).join(' + ')}` : `${prayQualify.length} jenis ≥20`,
      ok: sholatEligible },
  ];
  const shLog = PRAY_KEYS
    .filter(k => prayCounts[k] > 0)
    .map(k => ({ date: PRAY_LABEL[k], note: `${prayCounts[k]} record`, count: prayCounts[k], qualify: prayCounts[k] >= 20 }));

  const mk = (key, eligible, nominal, conditions, extra = {}) => ({
    key, eligible, nominal,
    amount: eligible ? nominal : 0,
    conditions,
    source: eligible ? 'Auto — syarat terpenuhi' : 'Auto — syarat tidak terpenuhi',
    formulaDetail: eligible
      ? `Memenuhi syarat → ${rp(nominal)}`
      : `Tidak memenuhi syarat → Rp 0`,
    ...extra,
  });

  return {
    disiplin:  mk('disiplin',  disEligible, disNominal, disCond, {
      formulaDetail: `${disEligible ? 'Memenuhi' : 'Tidak memenuhi'} syarat · masa kerja ${masaTahun ? '≥1th → Rp150.000' : '<1th → Rp100.000'} → ${disEligible ? rp(disNominal) : 'Rp 0'}`,
    }),
    transport: mk('transport', trEligible, 100000, trCond),
    beras:     mk('beras',     brEligible, 170000, brCond),
    soskes:    mk('soskes',    ssEligible, 101500, ssCond),
    sholat:    mk('sholat',    sholatEligible, 50000, shCond, { logItems: shLog }),
  };
}

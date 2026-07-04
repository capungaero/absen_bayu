# 08 — Agregasi Payroll, THP, dan Lock Gaji

Source of truth: `application/controllers/hr/Payroll.php::generate()`
(:169-392). Dipicu tombol "Lock Gaji" (AJAX `generate_payroll/<m>/<y>`,
gate password `SYNC_GATE_HASH`, role admin/admin-branch/hr, cabang divalidasi).

## Urutan proses Lock Gaji

1. Cek belum ada payroll (branch, month, year) — plus UNIQUE KEY
   `uq_payroll_period` di DB (anti dobel-lock race).
2. `PayrollSim::apply_auto_to_payroll()` — tulis 5 komisi otomatis ke
   `payroll_insentif` (instansiasi WAJIB via
   `ReflectionClass::newInstanceWithoutConstructor()` + suntik db/input/load —
   `new PayrollSim()` polos = fatal `CI_Template` (commit f8d2e6d)).
3. `get_attendance_by_branch()` — rekap per karyawan (fine, insentif,
   deduction, overtime).
4. Insert `payroll` → `payroll_id = insert_id()`.
5. Loop karyawan → kumpulkan baris `payroll_detail` → `insert_batch`.
6. Update ringkasan ke `payroll` + `payroll_code =
   'TRX-{branch_code}-PYR-{payroll_id}'`.
7. Transaksi commit; baris `payroll` yang ada = **kunci periode** (semua
   tulis presence/jadwal/izin periode itu ditolak server-side; helper
   `payroll_locked_*` di `generic_helper.php`).

## Rumus inti per karyawan

```
tmpReceive       = salary − alpha_weekdays_amount
payment_receive  = (tmpReceive < salary_minimum) ? salary_minimum : salary   # ⚠️ fallback ke SALARY penuh, bukan tmpReceive
salary_per_day   = salary / max_day_work         # max_day_work = max_for_generate = hari kalender periode 26–25
salary_basic_out_off_work = salary_per_day × strip          # potongan hari NO-SC

THP = (payment_receive + overtime_amount + insentif_total)
    − (fine + bpjs_work + deduction_total + bpjs_together + salary_basic_out_off_work)

salary_debt = (THP < 0) ? THP : 0    # dihitung SEBELUM clamp (urutan penting, commit 2 Jul 2026)
THP         = max(THP, 0)
```

Source: `hr/Payroll.php:246-296`.

⚠️ Dua keanehan yang DIPERTAHANKAN apa adanya (parity dulu, perbaikan nanti
atas keputusan user):
1. `payment_receive` jatuh kembali ke **salary penuh** (bukan `tmpReceive`)
   bila `tmpReceive ≥ salary_minimum` — artinya potongan alfa weekday
   TIDAK mengurangi payment_receive pada cabang jalur ini; alfa dipotong
   lewat komponen fine. **[VERIFIKASI Fase 4 dgn payroll_detail]**
2. Pembagi `salary_per_day` di generate() = hari periode (bukan
   totalDayInMonth spt di Presence_model) — dua konteks berbeda, keduanya
   benar sesuai perannya.

## Kolom penting `payroll_detail`

| Kolom | Sumber |
|---|---|
| salary_in_basic | payment_receive |
| salary_in_overtime / total_overtime_hour | rekap lembur approved |
| salary_in_insentive | `row['insentif']['total']` (get_insentif) |
| salary_out_fine | `row['fine']` (get_fine total) |
| salary_out_work / salary_out_together | input HR (lihat 07) |
| salary_out_deduction | get_deduction total |
| salary_basic_out_off_work | potongan NO-SC (strip) |
| salary_basic_out_alfa(_weekend/_weekdays) | alfa (lihat 04) |
| salary_thp / salary_debt | rumus di atas |
| payroll_fine / payroll_insentive / payroll_deduction | JSON detail penuh (audit) |
| presence_count*, *_count (sholat) | rekap kehadiran & sholat |

## Rollback

`rollback()`: transaksi hapus `payroll_detail` lalu `payroll` (urutan wajib;
dulu meninggalkan orphan). Setelah rollback, periode terbuka lagi dan
`payroll_code` tidak akan tabrakan (kode dari payroll_id).

### Contoh (bentuk data)
Gaji 2.000.000, lembur 60.000, insentif 310.000, fine 272.746 (termasuk
komponen izin/alfa), bpjs_work 0, deduction 0, bpjs_together 0, strip 0:
THP = 2.000.000+60.000+310.000 − 272.746 = **2.097.254**; debt 0.
(Selaras kasus riil Miftahul Juni 2026 di simulator: THP 1.537.254 dengan
gaji 1.500.000.)

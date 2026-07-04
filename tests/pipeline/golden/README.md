# Golden Fixtures — Paritas Engine Pipeline

Export **read-only** dari DB produksi `tifx3722_newtiffa_timesheet`
pada **4 Jul 2026** via `mysql --defaults-extra-file` (server tiffany.my.id).
Data ini TERKUNCI: jangan di-regenerate diam-diam; perubahan harus disengaja,
tercatat di git, dan disetujui user.

## Isi per periode

| Folder | payroll_id | branch | Catatan otoritas |
|---|---|---|---|
| `2026-06/` | **771** | 1 (SDR) | **GOLDEN UTAMA.** Di-lock ulang 3 Jul 2026 21:20 SETELAH deploy Presence_model versi commit 63ce6cd — angka mencerminkan aturan kode SAAT INI (pembagi salary_per_day = totalDayInMonth; early-leave gated is_fine_system) |
| `2026-05/` | 761 | 1 | Sekunder. Di-generate 7 Jun dgn engine LAMA (pembagi $total_day; early-leave tanpa gate) → paritas boleh punya delta TERDOKUMENTASI pada denda ½ hari, potongan izin, & pulang awal |
| `2026-04/` | 749 | 1 | Sekunder, kondisi sama dgn 2026-05 |

File per periode: `payroll.tsv`, `payroll_detail.tsv` (target paritas L2/L3),
`payroll_insentif.tsv`, `payroll_deduction.tsv` (L2), dan INPUT untuk replay
engine: `presence.tsv`, `schedule.tsv` (users_shift_additional + shift_code),
`leave.tsv`, `overtime.tsv`, `bpjs_payment.tsv`.

## `config_snapshot_now/`

Snapshot master **per 4 Jul 2026** (branch, shift, insentif, deduction,
bpjs_config, double_deduction_date, users, position). ⚠️ Anakronisme:
bila tarif berubah antara periode golden dan 4 Jul, paritas periode lama bisa
meleset "karena config", bukan karena engine — klasifikasikan delta tsb
sebagai CONFIG_DRIFT, jangan utak-atik engine untuk memaksa cocok.

## Yang BELUM ada di sini

- **File .dat mentah** periode-periode ini (utk paritas L1 penuh dari raw).
  Rencana: Fase 3 `absen-fetch` menarik ulang dari Solution Cloud bila
  riwayatnya masih tersedia; bila tidak, L1 memakai `presence.tsv` sebagai
  proxy dan L1-raw baru berlaku utk periode berjalan.
- Data cabang 2 (GBR) — lock terakhirnya Feb/Mar 2026 (id 702/701), terlalu
  lama utk jadi golden; tambah setelah GBR punya lock baru.

## Aturan pakai

1. Paritas L2/L3 wajib 100% di `2026-06` sebelum engine dipercaya.
2. Delta di 2026-04/05 harus punya penjelasan tertulis (file
   `deltas-explained.md` di folder periode ybs) — kandidat sah:
   perubahan aturan 63ce6cd, CONFIG_DRIFT, edit manual pasca-lock.
3. Format TSV: baris pertama = header kolom, nilai `NULL` literal.

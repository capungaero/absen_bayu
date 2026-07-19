# 02 — File .dat, Parsing, dan Klasifikasi Tap

## Format file .dat (mesin fingerprint Solution)

Satu baris = satu tap:

```
<finger_id>\t<YYYY-MM-DD>\t<HH:MM:SS>[\t<status>\t<verify>]
```

Kolom 4-5 diabaikan. Pemisah = whitespace apa pun (`preg_split('/\s+/')`).
Source of truth: `application/libraries/Attlog_parser.php:36-82` (`parse_taps`).

Aturan parse:
- Baris kosong dilewati; baris <3 kolom atau datetime tak valid → `invalid_count++`.
- Tap dengan tanggal di luar `[period_from, period_to]` di-skip **tanpa**
  menambah invalid.
- Output: `rows[finger_id][date] = {date, time: ['HH:MM:SS', ...]}`
  + stats (`total_lines`, `raw_count`, `invalid_count`, `unique_finger_ids`).

Sumber file: upload manual, atau download **Solution Cloud** via
`application/libraries/Cloud_attlog_client.php` (`download_batch()`);
kredensial cloud per cabang.

## Resolusi finger_id → karyawan

- `attendance_employee_resolver::build_by_finger_date($row_data, $from, $to)`
  memetakan `finger_id` (= `users.employee_code`) → `user_id` **per tanggal**
  (karyawan bisa pindah/nonaktif di tengah periode).
- ⚠️ **Gotcha duplikat**: dua user dengan `employee_code` sama menghasilkan
  "phantom" (absen nyangkut ke user salah). Pipeline WAJIB mendeteksi
  duplikat employee_code dan menerbitkan warning keras, jangan diam-diam.
  (Lihat memory proyek `sholat-sync-gotchas`.)

## Klasifikasi tap → kolom presence: DUA metode berbeda

### A. Window-based (dipakai jalur import resmi → tabel `presence`)

Source of truth: `Attlog_parser::classify_taps()`
(`application/libraries/Attlog_parser.php:110-200`).

Tap diurutkan naik, lalu dicocokkan ke **window shift** karyawan-tanggal itu:

| Window shift | Kolom presence | Catatan |
|---|---|---|
| `start_time_in` … `start_time_out` | `entry_time` (tap PERTAMA yang masuk window) | `entry_time_late = late_minutes(start_time_late, tap)` |
| `end_time_in` … `end_time_out` | `out_time` (tap pertama di window, sisanya diabaikan) | |
| `start_time_rest` … `end_time_rest` | tap ke-1 → `rest_time_in`, tap ke-2 → `rest_time_out` | `rest_time_late = late_minutes(rest_in + rest_time_range menit, tap_out)` |

- Tanpa jadwal (`use_schedule=false`) → fallback `attlog_apply_fallback()`
  (semua tap direkam apa adanya).
- Karyawan tak ter-resolve → `missing_employee`; semua tap di luar window →
  `no_window_match` (baris di-skip).
- Sholat pakai window TERPISAH per cabang — lihat [06-sholat.md](06-sholat.md).

### B. Posisional (dipakai tool DAT Reader, tabel dat_reader_*)

Source of truth: `application/controllers/DatReader.php:262-321`
(`_sync_core`, `_classify_positional`).

Tap diurut kronologis: **pertama = datang, kedua = keluar istirahat,
kedua-terakhir = masuk istirahat, terakhir = pulang** (asumsi pola 4 tap).
Dipakai untuk inspeksi/koreksi manusia sebelum "Dorong ke presence"
(aditif: skip baris presence yang sudah ada & periode terkunci).

**Pipeline absen_ai memakai metode A (window-based)** karena itulah yang
menentukan angka di `presence` → payroll (target paritas). Metode B tetap
didokumentasikan karena tabel `raw_taps` menyimpan SEMUA tap mentah sehingga
keduanya bisa direkonstruksi.

## Semantik merge sync: PROVENANCE-AWARE (8 Jul 2026)

Saat sync menemukan baris `presence` yang SUDAH ada utk (user, tanggal),
perlakuannya ditentukan **penulis terakhir baris** (`presence.input_by`):

| `input_by` existing | Perlakuan sync | Alasan |
|---|---|---|
| `system` (tulisan sync/mesin) | **Latest scan wins** — tap terbaru menimpa per kolom | Jadwal sering di-upload telat → data fallback salah klasifikasi WAJIB bisa diperbaiki dgn sync ulang |
| `manual` (pernah diedit manusia) | **Hanya isi kolom kosong** (`presence_merge_preserve_existing`, presence_helper.php:157-177) | Koreksi manual (mesin error dll) tidak boleh tertimbah mesin |

Berlaku SERAGAM di 4 jalur: PHP sync kerja (`hr/Presence::_import_presence_sheet`),
PHP sync sholat (`_import_pray_sheet` — baris manual: per-sholat isi hanya bila
`X_time_in` kosong; baris system: tetap clear-then-set authoritative),
Python cron kerja (`absen_sync.py::upsert_presence`) dan Python cron sholat.

Penanda `input_by='manual'` DIJAMIN di-set oleh semua endpoint edit manusia:
`update_presence`, `update_workhour`, `update_workpray`, setting-shift-recalc,
approve izin (Leave.php/M.php), dan DAT Reader `push()`. Endpoint baru yang
menulis presence atas nama manusia WAJIB ikut men-set penanda ini.

## Trigger provenance: DEFAULT-DENY OVERWRITE (19 Jul 2026)

Kelemahan skema di atas: penanda `manual` bersifat opt-in — edit langsung ke DB
(mysql CLI oleh AI agent, script ad-hoc spt `sync_dump_wins_may.sql` /
`backfill_sakit_presence_get_paid.sql`) tidak men-set `input_by`, sehingga baris
tetap dianggap milik mesin dan tertimpa sync berikutnya.

Solusi permanen: trigger `presence_provenance_bi` / `presence_provenance_bu`
(`database/add_provenance_triggers.sql`) MEMBALIK default-nya di level DB:

- **Setiap** INSERT/UPDATE yang mengubah kolom data pada `presence` otomatis
  ditandai `input_by='manual'`, KECUALI koneksi men-set "kartu sync":
  `SET @absen_sync_ctx = 1`.
- Pemegang kartu sync HANYA jalur sync mesin resmi: `hr/Presence.php`
  (`upload`, `upload_pray`, `sync_cloud`, `sync_pray_cloud`, `sync_cron`),
  `Wa.php::_sync_today_attendance`, dan `scripts/vps/absen_sync.py`
  (`init_command` pymysql). **Penulis sync BARU wajib ikut set variable ini** —
  kalau lupa, tulisannya membeku sebagai manual (fail-safe: data terlindungi).
- Update no-op / bump `updated_at` saja TIDAK mengubah provenance (perbandingan
  kolom NULL-safe `<=>`; `created_at`/`updated_at`/`flag`/`input_by*` dikecualikan).
- Ratchet: koneksi ber-kartu-sync tidak pernah bisa menurunkan `manual`→`system`.
  Escape hatch bila admin SENGAJA mau reset baris ke milik mesin:
  `SET @absen_sync_ctx = 'admin_reset';` sebelum UPDATE-nya.
- `Api_admin.php` `update_workhour`/`update_shift` kini menulis
  `input_by='manual'` (itu endpoint koreksi, bukan sync).

**Lock gaji di cron Python** (8 Jul 2026): `absen_sync.py` kini melewati
cabang yang payroll periodenya sudah dikunci (`get_locked_branches` — paralel
`_payroll_locked` PHP), termasuk pada `recalc_period_lateness`. Sebelumnya
pengecekan ini hanya ada di jalur PHP.

## Aturan keterlambatan (floor)

`late_minutes($limit, $time)` — Source of truth:
`application/helpers/late_helper.php:11-24`:

```
menit = max(0, (jam*60+menit)time − (jam*60+menit)limit)   # detik DIBUANG
```

### Contoh
- Batas telat `07:50:00`, tap `07:50:59` → **0 menit** (floor, bukan bulat).
- Batas `07:50`, tap `08:03:41` → **13 menit**.

## Contoh klasifikasi window

Shift RG: `start_time_in=06:00`, `start_time_out=10:00`, `start_time_late=07:50`,
`start_time_rest=12:00`, `end_time_rest=14:00`, `rest_time_range=60`,
`end_time_in=17:00`, `end_time_out=23:00`.
Tap: `07:45:10, 12:05:00, 13:20:30, 18:02:07`.

→ `entry_time=07:45:10` (telat 0), `rest_time_in=12:05:00`,
`rest_time_out=13:20:30` (batas = 12:05+60m = 13:05; telat = 13:20−13:05 =
**15 menit**), `out_time=18:02:07`.

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

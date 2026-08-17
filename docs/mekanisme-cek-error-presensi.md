# Mekanisme Cek Error Presensi

Disusun dari investigasi 17 Agustus 2026 (3 bug sinkronisasi + 1 gap trigger,
lihat `docs/rekap-insiden-absensi-2026-08-17.md` dan commit `d925fe6`,
`9a6fbec`, `4a89656`, `fa96d22`). Dipakai buat deteksi dini kalau kelas bug
yang sama (atau turunannya) muncul lagi.

Semua query jalan di server produksi:
```bash
ssh tiffany.my.id
mysql --defaults-extra-file=$HOME/.absen.cnf tifx3722_newtiffa_timesheet
```

Ganti `'2026-07-26'` (tanggal mulai) sesuai periode yang mau dicek — default
pakai periode payroll berjalan.

---

## 1. Flag manual palsu (artefak ctx-loss)

Baris ke-flag `input_by='manual'` padahal TIDAK PERNAH disentuh manusia asli
— tanda trigger provenance salah kira sync sebagai edit manual (biasanya
karena koneksi DB putus-nyambung di tengah sync panjang, server overload).

```sql
SELECT COUNT(*) AS kandidat
FROM presence p
WHERE p.flow_date >= '2026-07-26' AND p.input_by='manual'
  AND (p.rest_time_in IS NOT NULL OR p.entry_time IS NOT NULL)
  AND NOT EXISTS (
    SELECT 1 FROM audit_log a
    WHERE a.table_name='presence' AND a.record_id=p.id AND a.user_id IS NOT NULL
  );
```

**Kalau > 0:** cek beberapa sampel manual — pastikan memang gak ada aktor
manusia asli (audit_log kosong user_id). Kalau iya, flag ini palsu, aman
di-reset balik ke `system` (lewat `admin_reset`, lihat bagian 5) supaya sync
berikutnya berani mengoreksi.

---

## 2. Data sholat kecampur jam kerja — exact match

`entry_time`/`rest_time_in`/`rest_time_out` PERSIS sama (ke detik) dengan
salah satu kolom sholat di baris yang sama.

```sql
SELECT COUNT(*) AS kecampur_exact
FROM presence p
WHERE p.flow_date >= '2026-07-26'
  AND ((p.entry_time IS NOT NULL AND p.entry_time IN (p.dzuhur_time_in,p.dzuhur_time_out,p.ashar_time_in,p.ashar_time_out,p.subuh_time_in,p.subuh_time_out,p.maghrib_time_in,p.maghrib_time_out,p.isha_time_in,p.isha_time_out,p.friday_time_in,p.friday_time_out))
    OR (p.rest_time_in IS NOT NULL AND p.rest_time_in IN (p.dzuhur_time_in,p.dzuhur_time_out,p.ashar_time_in,p.ashar_time_out,p.subuh_time_in,p.subuh_time_out,p.maghrib_time_in,p.maghrib_time_out,p.isha_time_in,p.isha_time_out,p.friday_time_in,p.friday_time_out))
    OR (p.rest_time_out IS NOT NULL AND p.rest_time_out IN (p.dzuhur_time_in,p.dzuhur_time_out,p.ashar_time_in,p.ashar_time_out,p.subuh_time_in,p.subuh_time_out,p.maghrib_time_in,p.maghrib_time_out,p.isha_time_in,p.isha_time_out,p.friday_time_in,p.friday_time_out)));
```

**Kalau > 0:** ini kontaminasi PASTI (bukan koinsiden) — nilai sama sampai
ke detik gak mungkin kebetulan. Cross-check ke bagian 4 sebelum remediasi.

---

## 3. Data sholat kecampur jam kerja — near match (offset detik/menit)

Varian yang lolos dari cek #2 (kolom sholat sendiri gak ke-isi, atau ada
selisih beberapa detik karena beda mesin/proses). Toleransi 3 menit.

```sql
SELECT p.id, p.user_id, p.flow_date, p.entry_time, p.rest_time_in, p.rest_time_out,
       p.entry_time_late, p.rest_time_late
FROM presence p
WHERE p.flow_date >= '2026-07-26'
  AND (
    (p.entry_time IS NOT NULL AND (
        ABS(TIMESTAMPDIFF(SECOND,p.entry_time,p.dzuhur_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.entry_time,p.dzuhur_time_out))<=180 OR
        ABS(TIMESTAMPDIFF(SECOND,p.entry_time,p.ashar_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.entry_time,p.ashar_time_out))<=180 OR
        ABS(TIMESTAMPDIFF(SECOND,p.entry_time,p.subuh_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.entry_time,p.subuh_time_out))<=180 OR
        ABS(TIMESTAMPDIFF(SECOND,p.entry_time,p.maghrib_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.entry_time,p.maghrib_time_out))<=180 OR
        ABS(TIMESTAMPDIFF(SECOND,p.entry_time,p.isha_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.entry_time,p.isha_time_out))<=180 OR
        ABS(TIMESTAMPDIFF(SECOND,p.entry_time,p.friday_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.entry_time,p.friday_time_out))<=180
    ))
    OR (p.rest_time_in IS NOT NULL AND (
        ABS(TIMESTAMPDIFF(SECOND,p.rest_time_in,p.dzuhur_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.rest_time_in,p.dzuhur_time_out))<=180 OR
        ABS(TIMESTAMPDIFF(SECOND,p.rest_time_in,p.ashar_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.rest_time_in,p.ashar_time_out))<=180 OR
        ABS(TIMESTAMPDIFF(SECOND,p.rest_time_in,p.subuh_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.rest_time_in,p.subuh_time_out))<=180 OR
        ABS(TIMESTAMPDIFF(SECOND,p.rest_time_in,p.maghrib_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.rest_time_in,p.maghrib_time_out))<=180 OR
        ABS(TIMESTAMPDIFF(SECOND,p.rest_time_in,p.isha_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.rest_time_in,p.isha_time_out))<=180 OR
        ABS(TIMESTAMPDIFF(SECOND,p.rest_time_in,p.friday_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.rest_time_in,p.friday_time_out))<=180
    ))
    OR (p.rest_time_out IS NOT NULL AND (
        ABS(TIMESTAMPDIFF(SECOND,p.rest_time_out,p.dzuhur_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.rest_time_out,p.dzuhur_time_out))<=180 OR
        ABS(TIMESTAMPDIFF(SECOND,p.rest_time_out,p.ashar_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.rest_time_out,p.ashar_time_out))<=180 OR
        ABS(TIMESTAMPDIFF(SECOND,p.rest_time_out,p.subuh_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.rest_time_out,p.subuh_time_out))<=180 OR
        ABS(TIMESTAMPDIFF(SECOND,p.rest_time_out,p.maghrib_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.rest_time_out,p.maghrib_time_out))<=180 OR
        ABS(TIMESTAMPDIFF(SECOND,p.rest_time_out,p.isha_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.rest_time_out,p.isha_time_out))<=180 OR
        ABS(TIMESTAMPDIFF(SECOND,p.rest_time_out,p.friday_time_in))<=180 OR ABS(TIMESTAMPDIFF(SECOND,p.rest_time_out,p.friday_time_out))<=180
    ))
  );
```

**⚠️ JANGAN langsung simpulkan bug.** Near-match bisa KOINSIDEN WAJAR — orang
jalan ke masjid = tap absensi & tap sholat natural berdekatan waktu (lihat
kasus id 371130, 17 Agu: dicurigai kontaminasi, ternyata raw data mesin
absensi ASLI beneran ada 2 tap 83 detik terpisah pas mau Jumatan). **WAJIB**
cross-check ke bagian 4 sebelum ambil kesimpulan atau remediasi apa pun.

---

## 4. Wajib: cross-check ke raw data mesin ASLI

Sebelum simpulkan bug dari #2/#3, verifikasi ke `.dat` mentah — ini SATU-
SATUNYA sumber kebenaran final.

```bash
# cari file .dat terbaru per mesin
find ~/public_html/absen/uploads/attendance/<tahun>/<bulan> -iname '*.dat'

# cek tap finger tertentu tanggal tertentu (ganti <finger> = employee_code, <tanggal>)
grep -P '^\s*<finger>\s' attlog_<SN_MESIN_ABSENSI>.dat | grep '<tanggal>'
```

- Kalau nilai presence yang dicurigai **ADA** di file mesin ABSENSI asli
  (bukan mesin sholat) → **organik**, bukan bug, walau kebetulan dekat jam
  sholat. Cek juga apa itu representasi tap ganda / anomali wajar (lihat
  bagian 6).
- Kalau nilai presence **TIDAK ADA** di raw absensi tapi **ADA** di raw
  mesin sholat → **kontaminasi terkonfirmasi**, lanjut ke remediasi (bagian 5).

---

## 5. Remediasi baris terkonfirmasi salah

```sql
SET @absen_sync_ctx = 'admin_reset';
UPDATE presence SET rest_time_in=NULL, rest_time_out=NULL, rest_time_late=0
WHERE id IN (<daftar id>);
-- atau entry_time kalau itu yang salah:
-- UPDATE presence SET entry_time=NULL, entry_time_late=0 WHERE id IN (...);
```

Lalu paksa resync biar keisi ulang dari mesin absensi yang benar:
```bash
cd ~/public_html/absen && php index.php hr/presence/sync_cron
```

Verifikasi ulang query #2/#3 → harus 0 (atau turun drastis) setelah resync.

**Catatan teknis:** kalau query gabung baca+tulis `audit_log` dalam 1
statement, MySQL error `Can't update table 'audit_log' in stored function/
trigger`. Pisahkan pakai `CREATE TEMPORARY TABLE` dulu buat kandidat ID,
baru `UPDATE ... JOIN` ke temp table itu.

---

## 6. Durasi istirahat gak masuk akal (negatif atau sangat pendek)

Baik dari kontaminasi maupun batas heuristik classifier (tap ganda).

```sql
SELECT id, user_id, flow_date, rest_time_in, rest_time_out,
       TIMESTAMPDIFF(MINUTE,rest_time_in,rest_time_out) AS durasi_menit, rest_time_late
FROM presence
WHERE flow_date >= '2026-07-26'
  AND rest_time_in IS NOT NULL AND rest_time_out IS NOT NULL
  AND TIMESTAMPDIFF(MINUTE,rest_time_in,rest_time_out) < 10;
```

- **Durasi negatif** (rest_time_out < rest_time_in) → SELALU bug, gak ada
  penjelasan organik. Trace `audit_log` (bagian 7) buat cari kapan mulai
  salah, lalu remediasi (bagian 5).
- **Durasi positif tapi sangat pendek** (< 10 menit) → cek raw data (bagian
  4) dulu. Bisa jadi tap ganda asli (organik, classifier ambil 2 tap
  pertama dalam window padahal istirahat sesungguhnya belakangan) — kalau
  begitu JANGAN direset, datanya sudah benar apa adanya.

---

## 7. Trace riwayat sebuah baris (audit_log)

Buat lacak KAPAN dan SIAPA yang nulis nilai tertentu — penting sebelum
simpulkan akar masalah.

```sql
SELECT record_id, user_id AS aktor, action, changed_at
FROM audit_log
WHERE table_name='presence' AND record_id IN (<id>)
ORDER BY changed_at;

-- diff nilai spesifik antar waktu:
SELECT changed_at, user_id AS aktor,
  JSON_EXTRACT(before_data,'$.rest_time_in') AS rin_before, JSON_EXTRACT(after_data,'$.rest_time_in') AS rin_after,
  JSON_EXTRACT(before_data,'$.rest_time_out') AS rout_before, JSON_EXTRACT(after_data,'$.rest_time_out') AS rout_after,
  JSON_EXTRACT(after_data,'$.input_by') AS ib_after
FROM audit_log WHERE table_name='presence' AND record_id=<id> ORDER BY changed_at;
```

`user_id` (aktor) NULL = tulisan otomatis (sync). Aktor id kecil/dikenal =
manusia asli. Pola "flip-flop" (nilai bolak-balik benar/salah berkali-kali
dengan aktor NULL) = tanda proses sync saling menimpa, bukan 1 kejadian.

---

## 8. Keterlambatan tinggi — organik vs error

Scan umum (ganti `10`/`30` sesuai ambang yang mau dicek):

```sql
SELECT p.id, u.employee_code, CONCAT(u.first_name,' ',IFNULL(u.last_name,'')) AS nama,
  p.flow_date, DAYNAME(p.flow_date) AS hari,
  p.entry_time_late, p.rest_time_late,
  p.subuh_time_late, p.dzuhur_time_late, p.ashar_time_late, p.maghrib_time_late, p.isha_time_late, p.friday_time_late,
  p.input_by
FROM presence p JOIN users u ON u.id = p.user_id
WHERE p.flow_date >= '2026-07-26' AND p.flow_date <= '2026-08-25'
  AND (p.entry_time_late > 10 OR p.rest_time_late > 10 OR p.subuh_time_late > 10
       OR p.dzuhur_time_late > 10 OR p.ashar_time_late > 10 OR p.maghrib_time_late > 10
       OR p.isha_time_late > 10 OR p.friday_time_late > 10)
ORDER BY p.flow_date, u.employee_code;
```

Checklist buat tiap baris mencurigakan:
1. Jalankan query #2/#3 khusus baris itu — near/exact match ke kolom sholat?
2. Kalau match → cross-check raw mesin (#4) sebelum simpulkan.
3. Cek konfigurasi shift (`shift.start_time_late`) masuk akal buat label
   shift-nya (PAGI/MIDDLE/SIANG/dst) — kalau threshold aneh, itu masalah
   config bukan sync.
4. Kalau semua bersih & pola berulang di 1 karyawan → **organik**, itu
   temuan HR (pola kedisiplinan), bukan bug sistem. Jangan disentuh datanya.

---

## 9. Jadwal jalan

- **Tiap ada laporan user** "karyawan tiba-tiba telat/kosong aneh" → jalankan
  bagian 1-4 dulu buat scope masalahnya sebelum sentuh data apa pun.
- **Setelah deploy perubahan apa pun ke jalur sync** (PHP `hr/Presence.php`,
  Python `scripts/vps/absen_sync.py`, `DatReader.php`) → jalankan bagian
  1-3 buat 7 hari terakhir, pastikan 0 sebelum & sesudah deploy.
- **Kalau server hosting kena resource exhaustion lagi** (load tinggi,
  `exec request failed` di SSH) → jalankan bagian 1 begitu server pulih,
  karena itu pemicu utama Insiden #1.

## Referensi

- Trigger provenance: `database/add_provenance_triggers.sql` (v4, commit `fa96d22`)
- Fix ctx-loss: commit `d925fe6`
- Fix mesin sholat salah label (Python): commit `9a6fbec`
- Fix upload .dat bebas (DatReader): commit `4a89656`
- Rekap insiden lengkap: `docs/rekap-insiden-absensi-2026-08-17.md`

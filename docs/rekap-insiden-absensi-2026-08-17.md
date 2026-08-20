# Rekap Insiden: Absensi Massal Tidak Lengkap & Data Salah
**Tanggal kejadian ditemukan:** 17 Agustus 2026
**Periode terdampak:** 26 Juli – 17 Agustus 2026

Ada **dua masalah terpisah** yang ditemukan hari ini, kebetulan meledak di periode yang sama (26 Juli). Keduanya sudah diperbaiki dan diverifikasi hari ini juga.

---

## Masalah #1: Absensi Kosong Massal (Alpha Palsu)

### Apa yang terjadi
Banyak karyawan tiba-tiba muncul "Tidak Hadir/Alpha" di kalender absensi secara massal dan tiba-tiba, padahal mereka sebenarnya sudah tap absen di mesin fingerprint. Total **~900 baris data absensi** dari sekitar **20 karyawan cabang Sudirman (RG)** terdampak.

### Penyebab
Beberapa minggu lalu, sistem ditambahkan fitur pengaman baru: kalau ada yang mengedit data absensi **langsung ke database** (bukan lewat aplikasi), sistem otomatis menguncinya supaya tidak ditimpa sinkronisasi mesin berikutnya. Ini untuk melindungi koreksi manual.

Masalahnya, pengaman ini punya "kartu identitas" yang dipasang di awal proses sinkronisasi untuk mengenali "apakah ini sinkronisasi resmi atau bukan" — dan kartu itu **hanya dipasang sekali di awal**, padahal proses sinkronisasi bisa berjalan lama.

Server hosting (tiffany.my.id) belakangan mengalami **kelebihan beban** (dikonfirmasi langsung: beban server 26–33x lebih tinggi dari batas wajar). Saat itu terjadi, koneksi ke database bisa **putus-nyambung sendiri** di tengah sinkronisasi — dan "kartu identitas" tadi ikut hilang tanpa pemberitahuan error.

Akibatnya: saat sinkronisasi mencatat "karyawan ini belum ketemu tap" (proses normal, biasanya sementara), sistem pengaman salah mengira ini "edit manual tanpa izin" dan **mengunci data itu kosong permanen** — sinkronisasi berikutnya yang seharusnya membetulkan pun ikut diblokir.

Bukti: audit log menunjukkan **1.508 dari 1.509 kejadian pengosongan data sejak 26 Juli tidak punya "pelaku" manusia sama sekali** — murni bug otomatis.

### Solusi & Penyelesaian
| Langkah | Status |
|---|---|
| Kunci salah di 892 baris data dilepas | ✅ Selesai |
| Perbaikan kode — kartu identitas dipasang berulang (bukan cuma sekali di awal) | ✅ Selesai & aktif di server |
| Data terisi ulang otomatis oleh sinkronisasi | ✅ Selesai — 833 baris terisi ulang saat sinkronisasi dipaksa jalan manual |
| Verifikasi hasil | ✅ Terkonfirmasi — 0 baris baru ikut terkunci, sisa "alpha" tinggal wajar/asli |

Sinkronisasi dipaksa jalan manual meski server masih kelebihan beban (atas persetujuan), hasilnya bersih: 833 dari 892 baris terkunci langsung terisi ulang otomatis dan cocok persis dengan data mesin fingerprint asli.

---

## Masalah #2: Data Salah — Waktu Sholat Kecampur Jam Kerja

### Apa yang terjadi
Ditemukan saat menelusuri riwayat data untuk memastikan tidak ada masalah lain: beberapa karyawan yang **sebenarnya tidak terlambat** tiba-tiba tercatat **terlambat puluhan-ratusan menit**. Contoh nyata: Bayu Putra tanggal 28 Juli tercatat masuk jam 14:14 (telat 144 menit) — padahal jam masuk aslinya 11:47 (tidak telat sama sekali).

Total **777 baris data** (26 Juli – 17 Agustus) terdampak — jam masuk, jam istirahat masuk, dan jam istirahat selesai karyawan ternyata **persis sama** dengan jam sholat Dzuhur/Ashar mereka di hari yang sama. Bukan kebetulan — datanya identik sampai ke detik.

### Penyebab
Sistem sinkronisasi otomatis (berjalan di server terpisah) punya daftar mesin absensi yang **tersimpan manual** di dalam kode program, bukan diambil langsung dari pengaturan resmi di database. Daftar manual ini **sudah lama tidak diperbarui** (drift/usang) — satu mesin yang sebenarnya adalah **mesin pencatat sholat** ("Mesin Sholat Gambir") salah tercatat dalam daftar itu sebagai **mesin pencatat jam kerja**.

Akibatnya, data catatan sholat dari mesin itu ikut diproses seolah-olah itu data jam masuk/istirahat kerja — dan karena kebetulan waktu tapnya jatuh di rentang jam yang mirip jam kerja, sistem salah menyimpannya sebagai jam masuk/istirahat karyawan.

### Solusi & Penyelesaian
| Langkah | Status |
|---|---|
| Perbaikan kode — daftar mesin sekarang diambil langsung dari database resmi tiap kali sinkronisasi jalan, tidak lagi pakai daftar manual yang bisa usang | ✅ Selesai & aktif di server |
| Data yang salah (777 baris) dikembalikan ke kosong lalu diisi ulang dari mesin absensi yang benar | ✅ Selesai |
| Verifikasi hasil | ✅ Terkonfirmasi — Bayu Putra 28 Juli sekarang benar (masuk 11:47, tidak telat); 0 baris tersisa yang masih kecampur |

---

## Bisa terjadi lagi? Bagaimana penanganannya?

**Kemungkinan terjadi lagi untuk kedua masalah ini: kecil.** Akar masalah keduanya sama-sama sudah ditutup di level kode:
- Masalah #1: kartu identitas sinkronisasi sekarang dipasang berulang, tahan terhadap koneksi putus-nyambung.
- Masalah #2: daftar mesin sekarang selalu diambil langsung dari database resmi, tidak bisa lagi "usang" tanpa ketahuan.

**Tapi ada benang merah yang perlu diperhatikan ke depan:** kedua masalah sama-sama muncul dari kebiasaan menyimpan informasi penting (kartu identitas, daftar mesin) secara **sementara/manual** alih-alih selalu mengacu ke sumber resminya. Kalau ada pengembangan sistem baru ke depan, prinsip "selalu ambil dari sumber resmi, jangan simpan salinan manual" ini sudah dicatat sebagai pelajaran standar.

**Rekomendasi penanganan ke depan:**

1. **Sisi hosting (perlu tindakan dari pemilik akun/tim IT):** beban server 26–33x di atas wajar (pemicu Masalah #1) adalah masalah serius berdiri sendiri — perlu dicek ke penyedia hosting, kemungkinan perlu upgrade paket atau ada proses lain yang membebani server bersama-sama.
2. **Sisi sistem (sudah berjalan):** ada catatan otomatis (log) di server untuk melacak kejadian serupa lebih cepat kalau terulang.
3. **Kalau muncul lagi gejala serupa** — "banyak karyawan tiba-tiba kosong/alpha secara massal" atau "banyak karyawan tiba-tiba terlambat padahal biasanya tidak" — itu pola yang gampang dikenali (bukan satu-dua orang, biasanya dimulai di tanggal tertentu). Laporkan dengan ciri yang sama, penelusurannya sekarang jauh lebih cepat karena sudah ada peta masalahnya.

---
*Disusun otomatis berdasarkan investigasi teknis 17 Agustus 2026. File teknis: commit `d925fe6` (perbaikan Masalah #1), commit `9a6fbec` (perbaikan Masalah #2). Lihat juga `database/add_provenance_triggers.sql` dan `scripts/vps/absen_sync.py` untuk detail.*

# Sumber Data & Rumus — Workbook "REKAP PERHITUNGAN PPh 21 DESEMBER GROSS UP"

**Untuk:** konsultan pajak — verifikasi asal-usul setiap angka.
**Dihasilkan oleh:** aplikasi E-Absensi Tiffany, menu Tools → Export PPh21 → tombol "PPH Des & MPT (Konsultan)". Satu file per CV per tahun pajak.
**Kode:** `application/controllers/Pph21Export.php` (`export_des`, `_des_rows`, `_annual_iter`, `_masa`) + `application/libraries/Pph21_des_workbook.php`.
**Tanggal dokumen:** 24 Juli 2026.

---

## 1. Sumber Data Hulu (tabel database)

| Sumber | Isi yang dipakai |
|---|---|
| `payroll` + `payroll_detail` | Periode payroll per bulan; **THP final** per karyawan (`payroll_detail.salary_thp`) |
| `payroll_deduction` + `deduction` | Potongan bernama, per bulan: `CASHBON`, `PIUTANG KANVAS` (pinjaman — ditambahkan kembali ke bruto), `BPJS KESEHATAN` / `BPJS KETENAGAKERJAAN` (penanda kepesertaan BPJS), `BPJS KETENAGAKERJAAN` (nilai potongan karyawan = JHT/JP bayar sendiri) |
| `pph21_manual` (diisi via Tools → Input Manual PPh21) | Data di luar absensi per karyawan per payroll: `tunjangan` (uang jalan kanvas), `insentif` (uang konsumsi), `subsidi` (subsidi pajak/lembur), `bonus` (bonus/THR) |
| `users` | Nama (`first_name`+`last_name`), NIK (`npwp_number`), status PTKP (`ptkp_status`), jabatan (`position_id` → `position.position_name`), tanggal masuk (`join_date`), status aktif (`active`) & tanggal nonaktif (`last_status`) |
| `subdivision` | Nama CV / badan usaha |
| `pph21_roster` | Urutan baris karyawan per CV, tetap setahun (kertas kerja awal tahun; resign tetap tampil, karyawan baru di bawah) |
| `application/config/pph21_export.php` | NPWP per CV; nilai **default** premi JKK / JKM / BPJS Kes dibayar perusahaan |
| `pph21_settings` | **Override** premi JKK & JKM bila pernah disimpan lewat form di halaman Export PPh21 (menang atas config) |

Nilai premi berlaku saat dokumen ini ditulis: JKK 7.639,10 + JKM 8.548,90 + BPJS Kes 127.318,00 = **Rp 143.506,00** per peserta per bulan.

---

## 2. Sheet `DAFTAR GAJI`

Baris data mulai baris 6; urutan mengikuti `pph21_roster`.

| Kolom | Judul | Sumber / Rumus |
|---|---|---|
| A | NO. | Nomor urut roster |
| B | NAMA PEGAWAI | `users.first_name + last_name` (atau `pph21_roster.name` utk karyawan resign) |
| C | Jabatan | `position.position_name` |
| D | NPWP / NIK | `users.npwp_number` (NIK 16 digit, ditulis sebagai teks) |
| E | STATUS | `users.ptkp_status` (TK/0, K/1, dst; kosong → TK/0) |
| F | MASA KERJA awal | Bulan periode masuk — dari `users.join_date` dengan **cut-off periode payroll 26–25**: tanggal 26–31 terhitung periode bulan berikutnya (mis. masuk 27 Juni → periode Juli = 7). Masuk tahun sebelumnya (atau tanggal kosong) → 1 |
| G | MASA KERJA akhir | Bulan periode nonaktif — dari `users.last_status` saat `active=0`, cut-off 26–25 sama. Masih aktif → 12 |
| H | (jumlah masa) | Rumus Excel `=(G-F)+1` |

---

## 3. Sheet Bulanan `JAN` … `DES` (12 sheet)

Baris data mulai baris 5; baris ke-i = karyawan ke-i DAFTAR GAJI (baris i+1). Sel dikosongkan bila nilainya 0 / tidak ada payroll bulan itu.

| Kolom | Judul | Sumber / Rumus |
|---|---|---|
| A–D | NO / NAMA / JABATAN / STATUS | Rumus Excel referensi ke `DAFTAR GAJI` (ikut berubah bila DAFTAR GAJI diedit) |
| E | GAJI POKOK | `payroll_detail.salary_thp` (THP final bulan tsb) |
| F | TUNJANGAN LAINNYA | Potongan `CASHBON` + `PIUTANG KANVAS` bulan tsb (pinjaman, penambah bruto) **+** `pph21_manual.tunjangan` (uang jalan kanvas) **+** `pph21_manual.insentif` (uang konsumsi) **+** `pph21_manual.subsidi` (subsidi/lembur) |
| G | TUNJANGAN (tanpa keterangan) | **Kosong** — di template asli tidak berlabel & tidak dijumlahkan REKAP |
| H | TUNJANGAN PAJAK | Hasil hitung: **PPh gross-up masa** = Bruto GU − Bruto (lihat §5.2). Informasional — REKAP tidak menjumlahkannya (tunjangan PPh setahun dihitung terpisah, §5.3) |
| I | LEMBUR | **Kosong** — sengaja: rumus REKAP template tidak menjumlahkan kolom ini, maka nilai lembur dititipkan ke F agar tidak hilang dari hitungan setahun |
| J | JKK, JKM JPK DIBAYARKAN | Premi dibayar perusahaan (JKK+JKM+BPJS Kes, §1) — diisi bila karyawan bulan itu punya potongan `BPJS KESEHATAN` / `BPJS KETENAGAKERJAAN` > 0 |
| K | THR / BONUS | `pph21_manual.bonus` (penghasilan tidak teratur) |
| L | JAMSOSTEK TK | Potongan `BPJS KETENAGAKERJAAN` yang dipotong dari gaji karyawan bulan tsb (JHT/JP bayar sendiri — pengurang neto) |
| Baris TOTAL | | Rumus Excel `=SUM(...)` per kolom |

**Definisi Bruto masa** (dasar TER bulanan, konsisten dengan pelaporan Coretax bulanan):
`Bruto = E + F + J + K` (THP + tunjangan lainnya + premi perusahaan + bonus).

---

## 4. Sheet `REKAP` (perhitungan setahun + PPh Desember/MPT)

Baris data mulai baris 5. Rumus ditulis **hidup** di Excel persis pola template konsultan — bisa diperiksa langsung di sel.

| Kolom | Judul | Sumber / Rumus |
|---|---|---|
| A–G | NO / NAMA / NPWP / STATUS / MASA / JUMLAH | Rumus referensi ke `DAFTAR GAJI` (G = jumlah masa kerja, dipakai cap biaya jabatan) |
| H | GAJI POKOK | `=+JAN!E5+FEB!E5+…+DES!E5` (Σ 12 bulan kolom E) |
| I | TUNJANGAN PPh | **NILAI** hasil iterasi server (§5.3) — pengganti rumus circular `=AB` milik template yang mensyaratkan *iterative calculation* Excel |
| J | TUNJANGAN LAINNYA | Σ 12 bulan kolom F |
| K | HONOR DAN LAINNYA | Konstanta `0` (mengikuti template) |
| L | PREMI ASS DIBAYARKAN | Σ 12 bulan kolom J |
| M | NATURA BUKAN OBJEK | Konstanta `0` (mengikuti template) |
| N | JUMLAH PENGH TERATUR | `=H+I+J+K+L+M` |
| O | TDK TERATUR | Σ 12 bulan kolom K (THR/bonus) |
| P | TOTAL PENGHASILAN | `=N+O` |
| Q | BIAYA JABATAN (teratur) | `=IF(5%*N>=G*500000,G*500000,5%*N)` — 5% dgn cap Rp 500 rb × masa kerja |
| R | BIAYA JABATAN (tdk teratur) | `=IF(5%*N>=G*500000,0,IF((5%*N)+(5%*O)>=G*500000,(G*500000)-(5%*N),5%*O))` |
| S | PENSIUN/JHT BAYAR SENDIRI | Σ 12 bulan kolom L (Jamsostek TK) |
| T | JUMLAH PENGURANG | `=Q+R+S` |
| U | NETO SETAHUN | `=P-T` |
| V | PTKP | Rumus IF status: K/3=72 jt; K/2 & TK/3=67,5 jt; K/1 & TK/2=63 jt; K/0 & TK/1=58,5 jt; TK/0 & lainnya=54 jt |
| W | PKP | `=ROUNDDOWN(IF(U-V>0,U-V,0),-3)` — dibulatkan ke bawah ribuan penuh |
| X | PPh SETAHUN (ada NPWP) | Progresif Ps.17: 5% s.d. 60 jt; 15% s.d. 250 jt; 25% s.d. 500 jt; 30% s.d. 5 M; 35% di atasnya (rumus IF berlapis persis template) |
| Y | PPh SETAHUN (tanpa NPWP) | Sama × 120% (rumus template) |
| Z / AA | Pemisah ada/tanpa NPWP | `=IF(AA=0,X,0)` / `=IF(C="",Y,0)` — C kosong = tanpa NIK/NPWP |
| AB | PPh TERUTANG SETAHUN | `=IF(C="",Y,X)` |
| **AC** | **PPh DIPOTONG JAN–NOV** *(kolom tambahan)* | **NILAI**: Σ PPh gross-up masa Januari–November (= Σ kolom H sheet JAN..NOV; angka yang sama dengan yang dilaporkan bulanan ke Coretax) |
| **AD** | **PPh DES (MPT)** *(kolom tambahan)* | `=AB-AC` — kurang (positif) / lebih (negatif) potong masa pajak terakhir |
| Baris TOTAL | | `=SUM(...)` per kolom |

---

## 5. Rumus Perhitungan Inti (dihitung server)

### 5.1 Tarif TER bulanan
Tabel Tarif Efektif Rata-rata PP 58/2023 jo. PMK 168/2023 (Buku DJP hal. 40–43), kategori dari PTKP: **A** = TK/0, TK/1, K/0; **C** = K/3; **B** = lainnya. Tabel yang sama dipakai semua export (satu sumber di `Pph21_workbook.php`).

### 5.2 Gross-up bulanan (kolom H sheet bulanan; sumber kolom AC)
PPh ditanggung perusahaan, tarif dicari iteratif 3 tahap supaya bracket ikut naik:
```
t1 = TER(Bruto);  t2 = TER(Bruto/(1−t1));  tarif = TER(Bruto/(1−t2))
Bruto GU   = Bruto / (1 − tarif)
PPh GU masa = Bruto GU − Bruto   (= tarif × Bruto GU)
```
Identik dengan kolom U/V/W kertas kerja bulanan dan angka `Gross`/`Rate` XML Coretax.

### 5.3 Tunjangan PPh setahun (kolom I REKAP)
Menggantikan rumus circular template (`I = AB`, padahal AB tergantung N yang memuat I). Server mengulang perhitungan yang sama dengan rumus REKAP sampai stabil (selisih < Rp 0,50, maks 50 putaran):
```
I₀ = 0
ulangi: N = H + I + J + L;  Q,R = biaya jabatan (rumus §4);  U = (N+O) − (Q+R+S)
        W = rounddown(U − PTKP, ribuan);  X = progresif Ps.17(W);  I = X
```
Hasil akhir ditulis sebagai angka; kolom N/X/AB Excel tetap rumus hidup — bila konsultan mengubah suatu komponen, seluruh kolom menghitung ulang **kecuali I** (perlu generate ulang dari aplikasi, atau aktifkan iterative calculation dan ganti I dengan `=AB` bila ingin persis pola lama).

### 5.4 Masa kerja (kolom F/G DAFTAR GAJI)
Cut-off periode payroll Tiffany **26–25** (konfirmasi konsultan 24 Jul 2026): tanggal 26–31 dihitung periode bulan berikutnya, termasuk lintas tahun (26–31 Des tahun lalu → periode Januari tahun pajak).

---

## 6. Hal yang Perlu Dipastikan Konsultan

1. Kolom **LEMBUR & TUNJANGAN PAJAK bulanan tidak dijumlahkan** rumus REKAP template — karena itu subsidi/lembur kami satukan ke TUNJANGAN LAINNYA (F). Setuju, atau REKAP perlu diubah ikut menjumlahkan kolom I?
2. **Jamsostek TK** diambil dari potongan BPJS Ketenagakerjaan karyawan (JHT+JP). Bila hanya JHT 2% yang boleh jadi pengurang (tanpa JP 1%), perlu pemisahan nilai — saat ini potongan tercatat satu angka gabungan.
3. **PPh Des (AD) bisa negatif** = lebih potong; ditampilkan apa adanya.
4. Sebelum payroll Desember terbit, sheet DES kosong dan AC/AD bersifat proyeksi; generate ulang setelah payroll Desember final.
5. Akurasi masa kerja bergantung `join_date` terisi dan karyawan resign dinonaktifkan tepat waktu di aplikasi.

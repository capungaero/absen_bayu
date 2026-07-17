# Analisa & Perencanaan — PPh 21 Pegawai Tidak Tetap & Tenaga Ahli (Bukan Pegawai)

**Tanggal:** 17 Juli 2026
**Referensi:** Buku PPh 21/26 DJP Release 08-01-2024 (`Buku_PPh2126_Release_20240108.pdf`), PP 58/2023, PMK 168/2023
**Konteks:** melengkapi tool Export PPh21 (pegawai tetap, sudah produksi) dengan import + hitung + XML untuk dua kategori penerima penghasilan lain yang datanya berubah-ubah tiap bulan per CV.

---

## 1. Ringkasan Aturan dari Buku DJP

### 1.1 Pegawai Tidak Tetap (Bab 10, hal. 88–94)

Definisi: pegawai (termasuk tenaga kerja lepas) yang hanya menerima penghasilan
apabila bekerja — berdasarkan jumlah hari kerja, jumlah unit hasil pekerjaan,
atau penyelesaian suatu pekerjaan.

Skema baru (PP 58/2023 jo. PMK 168/2023), **3 jalur perhitungan**:

| Cara dibayar | Dasar (DPP) | Tarif | PPh 21 |
|---|---|---|---|
| Tidak bulanan (harian/mingguan/satuan/borongan), bruto ≤ Rp 2,5 jt/hari | Bruto sehari (atau rata-rata bruto sehari bila bukan harian) | **TER Harian** (Tabel 6.4): ≤ Rp 450 rb → **0%**; > Rp 450 rb s.d. Rp 2,5 jt → **0,5%** | TER harian × bruto sehari |
| Tidak bulanan, bruto > Rp 2,5 jt/hari | 50% × bruto | Tarif Pasal 17 progresif | Ps.17 × (50% × bruto) |
| Dibayar **bulanan** | Bruto sebulan | **TER Bulanan** kategori A/B/C sesuai PTKP (sama seperti pegawai tetap masa Jan–Nov) | TER bulanan × bruto bulanan |

Catatan penting dari contoh buku (hal. 90–93):
- Upah harian: bukti potong dibuat **per hari kerja** (contoh Tuan K, 20 hari → 20 bupot), "sepanjang sistem informasi perpajakan belum mengakomodasi bupot gabungan".
- Upah borongan/satuan: DPP = rata-rata bruto per hari (total ÷ jumlah hari).
- Pegawai tidak tetap bulanan (contoh Tuan N pemetik teh): murni TER bulanan × bruto, kategori dari PTKP — **tidak ada** pengurang biaya jabatan/PTKP bulanan.

### 1.2 Bukan Pegawai / Tenaga Ahli (Bab 11, hal. 95–101)

Definisi: orang pribadi selain pegawai tetap/tidak tetap yang menerima imbalan
atas **pekerjaan bebas atau jasa** (tenaga ahli: dokter, pengacara, konsultan,
teknisi, dsb.).

Perubahan besar PMK 168/2023: dulu dibedakan berkesinambungan / tidak
berkesinambungan dengan DPP kumulatif — sekarang **rumus tunggal, tidak
kumulatif**:

```
PPh 21 = Tarif Pasal 17 × (50% × Penghasilan Bruto)      … per pembayaran
```

Tarif Pasal 17 ayat (1) a (Tabel 6.1, atas DPP = 50% bruto):

| Lapisan DPP | Tarif |
|---|---|
| s.d. Rp 60 jt | 5% |
| > Rp 60 jt – 250 jt | 15% |
| > Rp 250 jt – 500 jt | 25% |
| > Rp 500 jt – 5 M | 30% |
| > Rp 5 M | 35% |

Catatan dari contoh buku:
- Progresif dihitung berlapis atas DPP per pembayaran (contoh pengacara hal. 97–98: DPP 200 jt → 5%×60 jt + 15%×140 jt).
- Untuk skala CV Tiffany (imbalan per bulan hampir pasti < Rp 120 jt bruto → DPP < 60 jt), praktisnya tarif efektif = **5% × 50% = 2,5% dari bruto**, tapi engine tetap harus implement progresif.
- Bila bukan pegawai mempekerjakan orang lain / menyerahkan material dengan bukti faktur (hal. 101): bruto = tagihan **dikurangi** upah pihak lain + material. Kolom bruto di template harus diisi nilai jasa bersihnya.
- Bupot dibuat **per pembayaran/bulan** (contoh dokter: 1 bupot per bulan).

### 1.3 Apakah dipisah dari pegawai tetap? Bagaimana pelaporannya?

**Perhitungan: YA, terpisah** — tiga rezim berbeda. Kode objek di bawah
**terkonfirmasi dari file pelaporan Juni 2026 CV Brilliant** (sampel
`06 Juni - PPH21 TDK TETAP CV Brilliant.xml/.xlsx`, diterima 17 Jul 2026):

| Kategori | Metode | Kode Objek (praktik Juni 2026) | Deemed |
|---|---|---|---|
| Pegawai tetap | TER bulanan + gross-up (sudah jalan) | `21-100-01` (XML `MmPayrollBulk`) | – |
| Pegawai tidak tetap dibayar bulanan | TER bulanan × bruto | `21-100-35` | 100 |
| Pegawai tidak tetap harian ≤ 2,5 jt/hari | TER harian (0% / 0,5%) | `21-100-24` | 100 |
| Tenaga ahli | Ps.17 × 50% bruto, **gross-up** | `21-100-07` | 50 |
| Pemberi jasa lain (bukan pegawai) | Ps.17 × 50% bruto | `21-100-20` dst. | 50 |

Daftar lengkap 32 kode objek + Deemed + jenis tarif ada di sheet `REF`
template Coretax (akan direplikasi ke tabel referensi tool).

**Pelaporan: SATU SPT Masa PPh 21/26 yang sama** per CV per masa pajak.
Praktik Juni 2026: bupot pegawai tidak tetap + tenaga ahli **digabung dalam
1 file XML `Bp21Bulk`** per CV — persis seperti keinginan "menjadi 1 kesatuan";
pegawai tetap tetap file `MmPayrollBulk` terpisah.

**✅ Gate skema XML SUDAH TERJAWAB** — struktur `Bp21Bulk` dari sampel:

```xml
<Bp21Bulk xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <TIN>0854470671204000</TIN>                <!-- NPWP CV pemotong -->
  <ListOfBp21>
    <Bp21>
      <TaxPeriodMonth>6</TaxPeriodMonth>
      <TaxPeriodYear>2026</TaxPeriodYear>
      <CounterpartTin>1376026002580001</CounterpartTin>            <!-- NIK 16 digit -->
      <IDPlaceOfBusinessActivityOfIncomeRecipient>1376026002580001000000</IDPlaceOfBusinessActivityOfIncomeRecipient> <!-- NIK+000000 -->
      <StatusTaxExemption>K/1</StatusTaxExemption>                 <!-- PTKP -->
      <TaxCertificate>N/A</TaxCertificate>
      <TaxObjectCode>21-100-35</TaxObjectCode>
      <Gross>1758000</Gross>
      <Deemed>100</Deemed>                                         <!-- % DPP -->
      <Rate>0</Rate>
      <Document>PaymentProof</Document>
      <DocumentNumber>58/EKS/BRL/VI/2026</DocumentNumber>          <!-- no. dok internal -->
      <DocumentDate>2026-06-26</DocumentDate>                      <!-- tgl bayar -->
      <IDPlaceOfBusinessActivity>0854470671204000000000</IDPlaceOfBusinessActivity> <!-- ID TKU CV -->
      <WithholdingDate>2026-06-30</WithholdingDate>                <!-- akhir masa -->
    </Bp21>
  </ListOfBp21>
</Bp21Bulk>
```

Fakta penting lain dari sampel:
- **Gross-up tenaga ahli terkonfirmasi**: `Gross` = pembayaran bersih ÷ (1 − 5%×50%)
  — contoh 5.128.205,128205 = Rp 5.000.000 ÷ 0,975. PPh ditanggung perusahaan.
- **1 bupot per orang per pembayaran** (bukan per hari kerja) dengan
  `Document=PaymentProof` + nomor dokumen internal (`58/EKS/BRL/VI/2026`) +
  tanggal pembayaran → template import wajib punya kolom **No. Dok** & **Tgl Dok**.
- Praktik tim pajak: semua pegawai tidak tetap Juni dilaporkan dgn `21-100-35`
  (upah bulanan, TER bulanan) — bukan TER harian. Tool harus dukung pilihan
  kode objek per baris (dropdown dari REF); tarif mengikuti jenis di REF
  (TER bulanan / HARIAN / PS17).
- Kualitas data nyata: ada baris NPWP 15 digit + trailing space
  (`"140313123204000 "`) yang lolos sampai XML — importer wajib **trim** dan
  **validasi panjang 15/16 digit** dengan warning.
- `Rate` di XML adalah satu angka (bukan progresif berlapis). Untuk DPP > Rp 60 jt
  (lapisan Ps.17 kedua) format 1-rate tidak memadai — engine beri warning manual
  (kasus hampir mustahil di skala imbalan CV).

Validasi lain yang wajib: NIK 16 digit valid/padan NPWP — penerima tanpa
NPWP/NIK tidak padan dikenai tarif **20% lebih tinggi** (Pasal 21 ayat 5a UU
PPh). Template harus menandai baris NIK tidak valid.

---

## 2. Analisa Gap Sistem Sekarang

| Aspek | Kondisi sekarang | Kebutuhan baru |
|---|---|---|
| Sumber data | `payroll_detail` (DB absensi) — pegawai tetap saja | Orangnya **tidak ada di DB**; data datang dari Excel bulanan, komposisi per CV berubah-ubah (kadang ada, kadang tidak) |
| Identitas | `users` (NIK, PTKP, jabatan) | Identitas harus dibawa di file import (NIK, nama, PTKP utk yang bulanan) |
| Perhitungan | TER bulanan + gross-up iteratif | 3 jalur pegawai tidak tetap + progresif Ps.17 bukan pegawai; **tanpa gross-up** (dipotong dari imbalan, kecuali dikonfirmasi lain) |
| Output | Excel kertas kerja per CV; XML pegawai tetap dibuat terpisah (pola Mei) | Excel kertas kerja + **1 file XML gabungan** (tidak tetap + tenaga ahli) per CV per masa |
| Roster | Urutan tetap setahun | **Tidak berlaku** — daftar bebas per bulan sesuai import |

Kesimpulan desain: model yang tepat adalah **import per masa** (bukan master
permanen), disimpan bertabel sendiri, dihitung otomatis saat import, lalu
di-export Excel + XML kapan pun.

---

## 3. Desain Fitur — "PPh21 Tidak Tetap & Tenaga Ahli"

### 3.1 Tabel baru `pph21_nonpegawai`

```sql
CREATE TABLE pph21_nonpegawai (
  id INT AUTO_INCREMENT PRIMARY KEY,
  month INT NOT NULL, year INT NOT NULL,          -- masa pajak (bukan payroll_id:
  subdivision_id INT NOT NULL,                    --  orangnya tak terikat payroll)
  kode_objek VARCHAR(12) NOT NULL,                -- 21-100-35 / 21-100-24 / 21-100-07 / dst (REF)
  nik VARCHAR(32) NOT NULL, nama VARCHAR(200) NOT NULL,
  keterangan VARCHAR(255) DEFAULT '',             -- jenis jasa / posisi
  ptkp VARCHAR(8) DEFAULT 'TK/0',                 -- StatusTaxExemption
  hari_kerja INT DEFAULT 0,                       -- utk kode tarif HARIAN
  neto DECIMAL(14,2) NOT NULL,                    -- pembayaran bersih (input admin)
  gross_up TINYINT(1) NOT NULL DEFAULT 1,         -- 1 = PPh ditanggung perusahaan
  bruto DECIMAL(14,2) NOT NULL,                   -- hasil hitung (= neto/(1-tarif_efektif) bila gross-up)
  deemed INT NOT NULL,                            -- % DPP dari REF (100/50)
  tarif DECIMAL(6,3) NOT NULL,                    -- Rate final utk XML
  pph DECIMAL(14,2) NOT NULL,                     -- hasil hitung
  doc_number VARCHAR(60) NOT NULL,                -- DocumentNumber (58/EKS/BRL/VI/2026)
  doc_date DATE NOT NULL,                         -- DocumentDate (tgl pembayaran)
  created_by INT UNSIGNED, created_at DATETIME,
  KEY k_masa (year, month, subdivision_id)
);
```

Tabel referensi kode objek (`pph21_kode_objek`) diisi dari sheet REF template
Coretax: kode, nama, deemed (100/50), jenis tarif (`TER`/`HARIAN`/`PS17`/fixed).

Import per masa bersifat **replace-per-CV** (hapus baris masa+CV lalu insert
ulang) supaya re-import file revisi tidak dobel.

### 3.2 Template Excel import (pola template Input Manual PPh21)

Kolom (mengikuti kebutuhan XML `Bp21Bulk` + gaya template Coretax yang sudah
dikenal tim — sheet DATA + sheet REF berisi daftar kode objek sebagai dropdown):

| Kolom | Wajib | Keterangan |
|---|---|---|
| CV | ✔ | Nama subdivision persis (dropdown dari sheet REF-CV) |
| NIK | ✔ | 16 digit — kunci bupot (trim + validasi panjang) |
| NAMA | ✔ | |
| KODE OBJEK | ✔ | dropdown dari REF: `21-100-35` (TT bulanan), `21-100-24` (TT harian), `21-100-07` (tenaga ahli), `21-100-20` (jasa lain), dst. |
| PTKP | ✔ | TK/0 dst. — kategori TER utk kode bertarif TER |
| JUMLAH HARI | utk kode HARIAN | pembagi rata-rata harian |
| PEMBAYARAN (NETO) | ✔ | nilai yang benar-benar dibayarkan (tenaga ahli: nilai jasa, exclude material/upah pihak lain) |
| NO. DOKUMEN | ✔ | nomor bukti pembayaran internal → `DocumentNumber` |
| TGL DOKUMEN | ✔ | tanggal pembayaran → `DocumentDate` |
| KETERANGAN | – | jenis jasa / posisi (arsip internal, tidak masuk XML) |

Engine menghitung Deemed/bruto gross-up/tarif/PPh saat import → preview di
layar (per CV, dengan kolom hasil) → tombol Simpan.

### 3.3 Engine perhitungan (server-side, PHP — satu library `Pph21_np_calc`)

Tarif ditentukan oleh **jenis tarif kode objek** (kolom REF), dengan gross-up
bila `gross_up=1` (default, sesuai praktik Juni — PPh ditanggung perusahaan):

```
TER    (mis. 21-100-35): tarif = TER_bulanan(kategori(ptkp), bruto)
                         gross-up iteratif 3 tahap spt pegawai tetap
HARIAN (mis. 21-100-24): rata = bruto / hari_kerja
                         rata ≤ 450 rb → 0% ; ≤ 2,5 jt → 0,5%
                         (> 2,5 jt/hari → pindah kode 21-100-30, Deemed 50, PS17)
PS17   (mis. 21-100-07): tarif XML = 5 (lapisan I), Deemed = 50
                         tarif_efektif = 5% × 50% = 2,5%
                         bruto = neto / (1 − 2,5%)      ← pola terbukti di sampel
                         PPh   = bruto × 2,5%
                         (DPP > 60 jt → warning, hitung manual)
NIK tidak valid (bukan 15/16 digit setelah trim) → flag warning di preview
```

Tabel TER bulanan & fungsi kategori PTKP **direuse** dari `Pph21_workbook.php`
(diekstrak ke helper bersama agar tidak dobel). Verifikasi angka terhadap
sampel Juni: `5.000.000 → Gross 5.128.205,128205 / PPh 128.205,13` harus
direproduksi persis oleh engine.

### 3.4 Output

1. **Excel kertas kerja** per CV per masa, format meniru template Coretax yang
   sudah dikenal tim (sheet `DATA` + `BP21` petunjuk + `REF`): kolom persis
   mirror XML + kolom bantu neto/DPP/tarif — untuk arsip & pencocokan pembayaran.
2. **XML Coretax `Bp21Bulk` — 1 file per CV per masa** berisi SEMUA baris
   non-pegawai-tetap (TT + tenaga ahli digabung, kode objek beda per baris),
   sesuai permintaan "menjadi 1 kesatuan" dan terbukti diterima Coretax
   (pola pelaporan Juni 2026). Field mengikuti sampel §1.3 persis, termasuk
   `WithholdingDate` = tanggal akhir masa.
   Tombol: per-CV, ZIP semua CV, mengikuti pola halaman Export PPh21.

### 3.5 Halaman & menu

- `tools/pph21_nonpegawai/index.html` — pilih masa (bulan/tahun, bebas — tidak
  terikat payroll) → download template → upload → preview hasil hitung →
  Simpan → tombol export Excel/XML.
- Controller `Pph21Nonpegawai.php` (pola auth Pph21Manual): `template`,
  `import` (parse+hitung, preview), `save`, `list`, `export_excel`,
  `export_xml`, `export_xml_all`.
- Menu **Tools → PPh21 Tidak Tetap & Tenaga Ahli**, di bawah Input Manual PPh21.
- Routes + CSRF exclude `pph21_nonpegawai/(.*)`.

---

## 4. Keputusan yang Perlu Dikonfirmasi (sebelum/selama implementasi)

Status setelah sampel Juni 2026 diterima (17 Jul 2026):

| # | Pertanyaan | Status |
|---|---|---|
| 1 | ~~Sampel XML Coretax~~ | ✅ **Terjawab** — skema `Bp21Bulk`, lihat §1.3 |
| 2 | ~~Kode objek persis~~ | ✅ **Terjawab** — `21-100-35` (TT bulanan), `21-100-07` (tenaga ahli); daftar lengkap di sheet REF |
| 3 | ~~Bupot per hari atau per orang per masa?~~ | ✅ **Terjawab** — 1 baris per orang per pembayaran, dgn No./Tgl dokumen internal |
| 4 | ~~Gross-up atau dipotong?~~ | ✅ **Terjawab** — gross-up (PPh ditanggung perusahaan); terbukti Gross = neto ÷ 0,975 di sampel |
| 5 | Penerima tanpa NIK padan (mis. baris NPWP 15 digit di sampel): tarif normal atau 120%? | ⏳ Terbuka — sampel Juni memakai tarif normal; default tool: **warning saja, tarif normal** (ikut praktik), konfirmasi ke konsultan bila mau ketat |
| 6 | Penomoran dokumen (`58/EKS/BRL/VI/2026`): diketik admin di template, atau tool bantu auto-nomor berkelanjutan per CV? | ⏳ Terbuka — default: diketik admin (fleksibel); auto-nomor bisa menyusul |

---

## 5. Rencana Implementasi Bertahap

**Fase 1 — Import + hitung + kertas kerja Excel**
1. `scripts/pph21_nonpegawai.sql` — tabel data + tabel referensi kode objek (seed dari sheet REF).
2. Helper hitung `application/libraries/Pph21_np_calc.php` (+ ekstrak tabel TER bersama dari `Pph21_workbook.php`).
3. Controller + halaman + template + import preview + simpan (replace per masa+CV).
4. Export Excel kertas kerja (format sheet DATA/BP21/REF seperti template Coretax).
5. Uji hitung terhadap: (a) semua contoh buku DJP (Tuan K/L/M/Z/N hal. 90–93; pengacara/dokter/jasa hal. 97–101), dan (b) **file real Juni 2026 CV Brilliant** — 18 baris harus tereproduksi persis (termasuk Gross 5.128.205,128205).

**Fase 2 — XML `Bp21Bulk`** (gate sudah terbuka — sampel diterima)
6. Builder XML gabungan per CV mengikuti sampel §1.3 byte-per-byte (urutan tag, format desimal, tab-indent, `standalone="yes"`); uji regresi: input Juni → diff dengan `06 Juni - PPH21 TDK TETAP CV Brilliant.xml` harus identik (kecuali koreksi NIK 15 digit).

**Fase 3 — Integrasi & rapikan**
7. Rekap gabungan per CV (tetap + TT + tenaga ahli) di sheet RINCIAN / halaman rekap.
8. Deploy (pola scp + backup), commit, catat di memory.

Sampel acuan tersimpan di `E:\VIBECODING\PAJAK_TIFFANY\sample_coretax\`
(`06 Juni - PPH21 TDK TETAP CV Brilliant.{xml,xlsx}` + versi pegawai tetap) —
berisi NIK, jangan masukkan ke repo git.

Estimasi: Fase 1+2 ± 1 sesi kerja (gate XML sudah terjawab).

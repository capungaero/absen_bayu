# Analisa Mekanisme Gaji, Bonus, dan Potongan
*Berdasarkan source code aplikasi absen_bayu (CodeIgniter)*

Berikut adalah penjelasan detail mengenai mekanisme penggajian yang diterapkan:

## 1. Mekanisme Gaji Pokok (Basic Salary)
Mekanisme ini menghitung seberapa banyak gaji pokok yang didapatkan karyawan sebelum ditambah bonus/lembur atau dikurangi denda keterlambatan/BPJS:
- **Nilai Awal:** Diambil dari nilai gaji pokok bulanan (`employee.salary`).
- **Gaji Per Hari (`salary_per_day`):** Dihitung dengan rumus `salary / total hari kerja (max_day_work)`. Terdapat juga penghitungan gaji per hari berdasarkan kalender bulanan (`salaryPerDayForAlpha`) untuk keperluan perhitungan potongan alpha.
- **Nilai Minimal:** Karyawan memiliki batas `salary_minimum`. Jika Gaji Pokok - Potongan Alpha Hari Biasa (`alpha_weekdays_amount`) jatuh di bawah `salary_minimum`, maka gaji pokok yang didapat menjadi sebesar `salary_minimum`. Sebaliknya, karyawan akan mendapat gaji penuh yang nantinya akan dipotong oleh potongan lain di tahap akhir.
- **Absen Kosong / Tidak Ada Jadwal (Strip):** Jika karyawan tidak bekerja karena jadwal kosong (`strip`), gajinya akan dipotong sebesar `salary_per_day * strip`.

## 2. Mekanisme Bonus (Insentif)
Penghitungan bonus atau insentif dilakukan berdasarkan komponen yang aktif pada sistem. Alurnya:
- **Master Insentif:** Sistem akan mengambil data komponen insentif aktif yang berlaku pada cabang (branch) tersebut.
- **Pengecekan Manual Input (`payroll_insentif`):** Jika HR/Admin sudah menginputkan besaran insentif secara manual/import untuk karyawan tertentu pada bulan tersebut, sistem akan langsung memakai nominal tersebut.
- **Rumus Otomatis (Formula):** Jika tidak ada input manual, sistem akan mengecek jenis formulanya:
  - Jika formula = `per_presence` (berdasarkan kehadiran): `Total Hadir (In) x Nominal Insentif`.
  - Jika formula = `none` atau tipe lainnya: Karyawan akan otomatis mendapatkan `Nominal Insentif` secara penuh (flat rate).
- Total dari keseluruhan komponen insentif akan dikalkulasi dan ditambahkan ke Take Home Pay (THP).

## 3. Mekanisme Potongan dan Denda (Deduction & Fines)
Potongan dipisahkan menjadi dua kategori besar: Potongan Pribadi/Manual dan Denda Sistem (Fines).

### A. Potongan Pribadi / Administratif (`deduction`)
- Mirip dengan Insentif, komponennya ditarik dari data master `deduction`.
- Terdiri dari potongan yang dicatat secara manual per bulan oleh HR/Admin, misalnya cicilan kasbon, potongan koperasi, dsb (`payroll_deduction`).

### B. Denda Sistem Otomatis (`fines`)
Kalkulasi denda ini sangat kompleks dan otomatis dihitung dari rekaman kehadiran:
- **Keterlambatan Datang (`late_start`):** Dihitung berdasarkan durasi menit keterlambatan. Sistem memakai tarif denda lipat (multiple rate). Jika terlambat melebihi ambang batas (`late_multiple_count_start`), denda akan berlipat ganda, dan nilainya memiliki maksimal (fix rate maksimal).
- **Keterlambatan Istirahat (`late_rest`):** Dihitung jika melebihi waktu istirahat yang ditentukan, dengan mekanisme pelipatan denda seperti keterlambatan masuk.
- **Potongan Izin Pulang Cepat (`early_leave`):** Dihitung otomatis secara proporsional atau fix berdasar menit pulang lebih awal (`early_leave_short_minutes`).
- **Denda Alpha / Tidak Hadir:**
  - Hari Biasa (Weekdays): Dipotong senilai upah 1 hari (`salaryPerDayForAlpha`).
  - Akhir Pekan (Weekend) atau Hari Khusus: Jika Alpha di hari Sabtu/Minggu (saat dijadwalkan masuk) atau saat ada setting *Double Deduction Date*, dendanya akan dikali 2 (Double Fine).
- **Potongan Cuti / Izin Berbayar Parsial:** Jika jenis kehadiran adalah izin dengan `presence_get_paid` < 100%, maka akan dipotong proporsional per harinya.
- **Denda Disiplin Ibadah (Pray System):** Untuk karyawan yang diaktifkan `is_pray_system`, terdapat deteksi presensi untuk Subuh, Dzuhur, Ashar, Maghrib, Isya, dan Jumat. Jika telat menekan absen shalat, akan dikenakan denda *late rate*, dan jika bolos sama sekali akan dikenakan denda maksimal ibadah.

## 4. Komponen BPJS & Lembur
- **BPJS:** Terdapat dua variabel potongan untuk BPJS, yaitu BPJS Tenaga Kerja (`bpjs_work`) dan BPJS gabungan (`bpjs_together`).
- **Lembur (Overtime):** Jam lembur yang sudah **di-approve** akan diakumulasikan dan dikali dengan *rate* per jam karyawan (`overtime_hour_rate`). Dibatasi juga oleh batas waktu maksimal lembur per hari (`max_overtime`).

## 5. Kalkulasi Akhir (Take Home Pay / THP)
Sistem menyatukan seluruh variabel tersebut dengan rumus:
```php
$thp = ($payment_receive + $overtime_amount + $insentif_total) 
       - ($fine + $bpjs_work + $deduction_total + $bpjs_together + $salary_basic_out_off_work);
```

**Catatan Potensi Isu pada Kode:**
Terdapat celah pada kalkulasi hutang gaji ke perusahaan jika total potongan melebihi total pendapatan:
```php
$thp = $thp < 0 ? 0 : $thp;
$salary_debt = $thp < 0 ? $thp : 0;
```
Karena nilai variabel `$thp` dipaksa menjadi `0` apabila minus (pada baris pertama), maka kondisi di baris kedua tidak akan pernah benar. Hal ini menyebabkan nilai hutang gaji (`$salary_debt`) akan selalu tercatat `0`.

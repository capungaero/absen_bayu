# Delta paritas 2026-05 (dan 2026-04) — DIJELASKAN

Periode ini di-generate produksi dgn engine SEBELUM commit 63ce6cd (3 Jul 2026):
1. `salary_per_day` dibagi jumlah hari PERIODE 26-25 (31 utk Mei/Apr), bukan
   `totalDayInMonth` — memengaruhi denda ½ hari & potongan izin (engine baru
   menghasilkan angka lebih besar utk bulan 30 hari).
2. Denda pulang awal belum di-gate `is_fine_system` (semua karyawan kena).
Engine pipeline memakai aturan SEKARANG (= golden utama 2026-06, paritas 100%).
Klasifikasi: RULE_CHANGE. Bukan bug. Jangan menyetel engine agar cocok ke
periode lama.

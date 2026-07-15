# Changelog rename Karyawan -> Mitra Kerja
# Tanggal: 2026-07-15
# Mode: APPLIED (lokal; belum deploy)
#
# SCOPE: HANYA teks tampilan Indonesia yang dilihat operator/mitra kerja
#   (halaman web app, PWA, 3 tool React: Payroll Sim, DAT Reader, Payroll Importer).
# Peta kapitalisasi: KARYAWAN->MITRA KERJA | Karyawan->Mitra Kerja | karyawan->mitra kerja.
#
# TIDAK disentuh (by design):
#   - Identifier kode/DB: 'employee', employee_code, employee_id, tabel, class, rute /employee.
#   - Komentar kode (hanya segmen non-komentar yang diganti).
#   - Subsistem PPh21 (Pph21Export.php, Pph21_workbook.php, config/pph21_export.php) -> tetap 'Karyawan'.
#   - docs/, tests/, pipeline/, scripts/, tools/sholat_recap/, application/config/.
#
# TITIK FUNGSIONAL YANG DILINDUNGI (nilai role/DB & key/properti — TIDAK diubah):
#   - Nilai role DB 'karyawan' (lowercase): Employee.php in_list[karyawan,...] (L614),
#     if($row['access']=='karyawan') (L722), $access='karyawan' (L989).
#   - Key JSON 'karyawan' => (DatReader.php L364) yang dibaca recap.karyawan (dat_reader App.jsx L248).
#   - Deteksi kolom Excel: strpos($n,'karyawan') (PayrollImporter.php L182) & .includes('karyawan')
#     (payroll_importer App.jsx L87).
#   - Nama file asset: Template_Tambah_Karyawan.xlsx.
#
# CATATAN DEPLOY:
#   - tools/payroll_sim/dist GITIGNORED -> build & sinkron dist terpisah ke server.
#   - tools/dat_reader & payroll_importer dist SUDAH di-rebuild (asset hash baru).
#   - PWA: nama app diganti (E-Karyawan -> E-Mitra Kerja); user perlu refresh/reinstall.
#
# ROLLBACK: `git revert <commit>` atau kembalikan per-baris dari daftar +/- di bawah.
#

## application\controllers\Bpjs.php  (2 baris)
  L110
    - return $this->_json(['status' => false, 'message' => 'Karyawan tidak valid']);
    + return $this->_json(['status' => false, 'message' => 'Mitra Kerja tidak valid']);
  L137
    - return $this->_json(['status' => false, 'message' => 'Karyawan tidak valid']);
    + return $this->_json(['status' => false, 'message' => 'Mitra Kerja tidak valid']);

## application\controllers\Branch.php  (2 baris)
  L90
    - $title = 'Cabang masih berisi karyawan. Pindahkan karyawan sebelum cabang dinonaktifkan.';
    + $title = 'Cabang masih berisi mitra kerja. Pindahkan mitra kerja sebelum cabang dinonaktifkan.';
  L197
    - 'message' => 'Cabang masih berisi karyawan. Pindahkan karyawan sebelum cabang dinonaktifkan.'
    + 'message' => 'Cabang masih berisi mitra kerja. Pindahkan mitra kerja sebelum cabang dinonaktifkan.'

## application\controllers\DatReader.php  (2 baris)
  L167
    - if (empty($edits)) return $this->_json(['error' => 'Karyawan yang diedit tidak ada di cabang ini'], 422);
    + if (empty($edits)) return $this->_json(['error' => 'Mitra Kerja yang diedit tidak ada di cabang ini'], 422);
  L297
    - return ['ok' => false, 'msg' => 'Tap ditemukan tapi tidak ada yang cocok karyawan. Tidak dikenal: '.implode(', ', $missing_fingers)];
    + return ['ok' => false, 'msg' => 'Tap ditemukan tapi tidak ada yang cocok mitra kerja. Tidak dikenal: '.implode(', ', $missing_fingers)];

## application\controllers\Employee.php  (14 baris)
  L97
    - $this->form_validation->set_rules('first_name', 'Nama karyawan', 'required');
    + $this->form_validation->set_rules('first_name', 'Nama mitra kerja', 'required');
  L98
    - $this->form_validation->set_rules('employee_code', 'Kode karyawan', 'required');
    + $this->form_validation->set_rules('employee_code', 'Kode mitra kerja', 'required');
  L257
    - $this->form_validation->set_rules('first_name', 'Nama karyawan', 'required');
    + $this->form_validation->set_rules('first_name', 'Nama mitra kerja', 'required');
  L258
    - $this->form_validation->set_rules('employee_code', 'Kode karyawan', 'required');
    + $this->form_validation->set_rules('employee_code', 'Kode mitra kerja', 'required');
  L438
    - 'message' => 'Tidak bisa mengaktifkan: sudah ada karyawan aktif lain dengan Kode/NIK '.$current['employee_code'].'. Nonaktifkan dulu yang itu.'
    + 'message' => 'Tidak bisa mengaktifkan: sudah ada mitra kerja aktif lain dengan Kode/NIK '.$current['employee_code'].'. Nonaktifkan dulu yang itu.'
  L459
    - 'message' => 'Status karyawan berhasil diubah'
    + 'message' => 'Status mitra kerja berhasil diubah'
  L472
    - 'message' => 'ID Karyawan tidak diketahui'
    + 'message' => 'ID Mitra Kerja tidak diketahui'
  L619
    - $this->form_validation->set_rules('first_name', 'Nama Karyawan', 'required');
    + $this->form_validation->set_rules('first_name', 'Nama Mitra Kerja', 'required');
  L676
    - 'label' => 'KODE KARYAWAN',
    + 'label' => 'KODE MITRA KERJA',
  L766
    - 'message' => 'Data karyawan berhasil diupload'
    + 'message' => 'Data mitra kerja berhasil diupload'
  L773
    - 'message' => 'Data karyawan gagal diupload'
    + 'message' => 'Data mitra kerja gagal diupload'
  L875
    - $sheet->setCellValue('A1', 'DAFTAR KARYAWAN');
    + $sheet->setCellValue('A1', 'DAFTAR MITRA KERJA');
  L918
    - $sheet->setCellValue('C3', 'NAMA KARYAWAN');
    + $sheet->setCellValue('C3', 'NAMA MITRA KERJA');
  L1008
    - $title = "Daftar Karyawan ".$title_scope;
    + $title = "Daftar Mitra Kerja ".$title_scope;

## application\controllers\PayrollImporter.php  (3 baris)
  L107
    - 'label'    => 'Karyawan (ID / Nama)',
    + 'label'    => 'Mitra Kerja (ID / Nama)',
  L221
    - return $this->_json(['error' => 'Kolom kunci "Karyawan" belum dipetakan'], 422);
    + return $this->_json(['error' => 'Kolom kunci "Mitra Kerja" belum dipetakan'], 422);
  L284
    - return $this->_json(['error' => 'Tidak ada karyawan yang cocok. Cek pemetaan kolom kunci & isi data.',
    + return $this->_json(['error' => 'Tidak ada mitra kerja yang cocok. Cek pemetaan kolom kunci & isi data.',

## application\controllers\PayrollSim.php  (2 baris)
  L311
    - if (!isset($this->emp_batch_cache[$eid])) throw new Exception('Karyawan tidak ditemukan');
    + if (!isset($this->emp_batch_cache[$eid])) throw new Exception('Mitra Kerja tidak ditemukan');
  L325
    - if (!$emp_q->num_rows()) throw new Exception('Karyawan tidak ditemukan');
    + if (!$emp_q->num_rows()) throw new Exception('Mitra Kerja tidak ditemukan');

## application\controllers\Wa.php  (2 baris)
  L360
    - $this->session->set_flashdata('rekap_info', 'Semua karyawan sudah hadir hari ini.');
    + $this->session->set_flashdata('rekap_info', 'Semua mitra kerja sudah hadir hari ini.');
  L388
    - $this->session->set_flashdata('rekap_success', "Notifikasi tidak hadir dikirim ke {$success_count} nomor. ({$count} karyawan tidak hadir)");
    + $this->session->set_flashdata('rekap_success', "Notifikasi tidak hadir dikirim ke {$success_count} nomor. ({$count} mitra kerja tidak hadir)");

## application\controllers\hr\Deduction.php  (2 baris)
  L269
    - 'message' => 'Data karyawan tidak ditemukan'
    + 'message' => 'Data mitra kerja tidak ditemukan'
  L307
    - 'message' => 'Data karyawan tidak ditemukan'
    + 'message' => 'Data mitra kerja tidak ditemukan'

## application\controllers\hr\Insentif.php  (2 baris)
  L282
    - 'message' => 'Data karyawan tidak ditemukan'
    + 'message' => 'Data mitra kerja tidak ditemukan'
  L322
    - 'message' => 'Data karyawan tidak ditemukan'
    + 'message' => 'Data mitra kerja tidak ditemukan'

## application\controllers\hr\Leave.php  (1 baris)
  L66
    - $this->form_validation->set_rules('user_id', 'Karyawan', 'required');
    + $this->form_validation->set_rules('user_id', 'Mitra Kerja', 'required');

## application\controllers\hr\Overtime.php  (3 baris)
  L61
    - $this->form_validation->set_rules('user_id[]', 'Karyawan', 'required');
    + $this->form_validation->set_rules('user_id[]', 'Mitra Kerja', 'required');
  L80
    - 'message' => 'Lengkapi karyawan, lama lembur, dan tanggal lembur pada setiap baris.'
    + 'message' => 'Lengkapi mitra kerja, lama lembur, dan tanggal lembur pada setiap baris.'
  L166
    - 'message' => 'Karyawan belum dipilih'
    + 'message' => 'Mitra Kerja belum dipilih'

## application\controllers\hr\Payroll.php  (9 baris)
  L370
    - $profile('loop per-karyawan selesai ('.$n.' karyawan)');
    + $profile('loop per-mitra kerja selesai ('.$n.' mitra kerja)');
  L580
    - 'message' => 'Data karyawan tidak diketahui'
    + 'message' => 'Data mitra kerja tidak diketahui'
  L664
    - 'message' => 'Data karyawan tidak diketahui'
    + 'message' => 'Data mitra kerja tidak diketahui'
  L690
    - $headers = ['ID Fingerprint', 'Nama Karyawan'];
    + $headers = ['ID Fingerprint', 'Nama Mitra Kerja'];
  L729
    - $sheet->fromArray(['ID Fingerprint', 'Nama Karyawan', 'Tanggal Lembur', 'Jam Lembur'], null, 'A1');
    + $sheet->fromArray(['ID Fingerprint', 'Nama Mitra Kerja', 'Tanggal Lembur', 'Jam Lembur'], null, 'A1');
  L825
    - $message = 'Import komisi/potongan selesai. Karyawan diproses: '.$imported.', komisi: '.count($insentif_rows).', potongan: '.count($deduction_rows).', ID tidak cocok: '.$missing.'.';
    + $message = 'Import komisi/potongan selesai. Mitra Kerja diproses: '.$imported.', komisi: '.count($insentif_rows).', potongan: '.count($deduction_rows).', ID tidak cocok: '.$missing.'.';
  L960
    - 'message' => 'Total THP Karyawan tidak boleh kurang dari Rp. 0'
    + 'message' => 'Total THP Mitra Kerja tidak boleh kurang dari Rp. 0'
  L1037
    - 'message' => 'Total THP Karyawan tidak boleh kurang dari Rp. 0'
    + 'message' => 'Total THP Mitra Kerja tidak boleh kurang dari Rp. 0'
  L1392
    - $sheet->setCellValue('A4', 'NAMA KARYAWAN');
    + $sheet->setCellValue('A4', 'NAMA MITRA KERJA');

## application\controllers\hr\Presence.php  (7 baris)
  L1598
    - 'message' => 'Data karyawan tidak ditemukan'
    + 'message' => 'Data mitra kerja tidak ditemukan'
  L1687
    - $messages[] = 'Preview periode '.$sync_from_date.' s/d '.$sync_to_date.' ('.$excel['total_rows'].' log, '.$excel['mapped_rows'].' cocok karyawan, '.$excel['missing_rows'].' tidak cocok).';
    + $messages[] = 'Preview periode '.$sync_from_date.' s/d '.$sync_to_date.' ('.$excel['total_rows'].' log, '.$excel['mapped_rows'].' cocok mitra kerja, '.$excel['missing_rows'].' tidak cocok).';
  L1945
    - $messages[] = 'File Excel sholat otomatis: '.basename($excel['path']).' ('.$excel['total_rows'].' log dari '.$excel['from'].' s/d '.$excel['to'].', '.$excel['mapped_rows'].' cocok karyawan, '.$excel['missing_rows'].' tidak cocok).';
    + $messages[] = 'File Excel sholat otomatis: '.basename($excel['path']).' ('.$excel['total_rows'].' log dari '.$excel['from'].' s/d '.$excel['to'].', '.$excel['mapped_rows'].' cocok mitra kerja, '.$excel['missing_rows'].' tidak cocok).';
  L2938
    - <li>ID Fingerprint Karyawan</li>
    + <li>ID Fingerprint Mitra Kerja</li>
  L3220
    - $sheet->setCellValue('A1', 'TEMPLATE JADWAL KERJA KARYAWAN');
    + $sheet->setCellValue('A1', 'TEMPLATE JADWAL KERJA MITRA KERJA');
  L3246
    - $sheet->setCellValue('A3', 'ID FINGERPRINT KARYAWAN');
    + $sheet->setCellValue('A3', 'ID FINGERPRINT MITRA KERJA');
  L3247
    - $sheet->setCellValue('B3', 'NAMA KARYAWAN');
    + $sheet->setCellValue('B3', 'NAMA MITRA KERJA');

## application\models\Deduction_model.php  (1 baris)
  L89
    - ->column('<b>NAMA POTONGAN KARYAWAN</b>', 'deduction_name')
    + ->column('<b>NAMA POTONGAN MITRA KERJA</b>', 'deduction_name')

## application\models\Leave_model.php  (2 baris)
  L106
    - ->column('<b>KARYAWAN</b>', 'first_name', function($data, $row){
    + ->column('<b>MITRA KERJA</b>', 'first_name', function($data, $row){
  L165
    - ->column('<b>KARYAWAN</b>', 'first_name', function($data, $row){
    + ->column('<b>MITRA KERJA</b>', 'first_name', function($data, $row){

## application\models\Overtime_model.php  (2 baris)
  L106
    - ->column('<b>KARYAWAN</b>', 'first_name', function($data, $row){
    + ->column('<b>MITRA KERJA</b>', 'first_name', function($data, $row){
  L169
    - ->column('<b>KARYAWAN</b>', 'first_name', function($data, $row){
    + ->column('<b>MITRA KERJA</b>', 'first_name', function($data, $row){

## application\models\User_model.php  (1 baris)
  L248
    - <a href="javascript:void(0)" '.$edit.' class="dropdown-item edit"><i class="dripicons-pencil"></i> Ubah Data Karyawan</a>
    + <a href="javascript:void(0)" '.$edit.' class="dropdown-item edit"><i class="dripicons-pencil"></i> Ubah Data Mitra Kerja</a>

## application\views\index.php  (6 baris)
  L49
    - <p class="text-muted mb-0">Karyawan Aktif</p>
    + <p class="text-muted mb-0">Mitra Kerja Aktif</p>
  L104
    - <h6 class="card-title">NOTIFIKASI TANGGAL KONTRAK KARYAWAN</h6>
    + <h6 class="card-title">NOTIFIKASI TANGGAL KONTRAK MITRA KERJA</h6>
  L110
    - ** Harap <b>Perpanjang</b> Atau <b>Nonaktifkan Manual</b> status karyawan jika ada masa kontrak yang sudah habis
    + ** Harap <b>Perpanjang</b> Atau <b>Nonaktifkan Manual</b> status mitra kerja jika ada masa kontrak yang sudah habis
  L116
    - <th>KARYAWAN</th>
    + <th>MITRA KERJA</th>
  L159
    - <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">Status Karyawan</h5>
    + <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">Status Mitra Kerja</h5>
  L170
    - Status data karyawan akan diubah
    + Status data mitra kerja akan diubah

## application\views\attendance\daily_report.php  (3 baris)
  L113
    - <th>Nama Karyawan</th>
    + <th>Nama Mitra Kerja</th>
  L198
    - <th>Nama Karyawan</th>
    + <th>Nama Mitra Kerja</th>
  L209
    - <td colspan="8" class="text-center text-muted py-4">Tidak ada karyawan terjadwal yang belum terdeteksi mesin absensi.</td>
    + <td colspan="8" class="text-center text-muted py-4">Tidak ada mitra kerja terjadwal yang belum terdeteksi mesin absensi.</td>

## application\views\attendance\early_leave_report.php  (4 baris)
  L81
    - <small class="text-muted">Karyawan PLA</small>
    + <small class="text-muted">Mitra Kerja PLA</small>
  L105
    - Rumus per karyawan:
    + Rumus per mitra kerja:
  L116
    - <p class="text-muted">Belum ada karyawan dengan flag Izin Pulang Cepat di periode payroll yang dipilih.</p>
    + <p class="text-muted">Belum ada mitra kerja dengan flag Izin Pulang Cepat di periode payroll yang dipilih.</p>
  L124
    - <th>Karyawan</th>
    + <th>Mitra Kerja</th>

## application\views\attendance\index.php  (1 baris)
  L85
    - <th>Nama Karyawan</th>
    + <th>Nama Mitra Kerja</th>

## application\views\attendance\machine_report.php  (2 baris)
  L111
    - <th>ID Karyawan</th>
    + <th>ID Mitra Kerja</th>
  L112
    - <th>Nama Karyawan</th>
    + <th>Nama Mitra Kerja</th>

## application\views\audit\index.php  (1 baris)
  L65
    - <th>Karyawan / Tanggal</th>
    + <th>Mitra Kerja / Tanggal</th>

## application\views\audit\mass_rollback.php  (3 baris)
  L46
    - <label class="form-label font-weight-bold">Filter Karyawan (Opsional)</label>
    + <label class="form-label font-weight-bold">Filter Mitra Kerja (Opsional)</label>
  L48
    - <option value="">-- Semua Karyawan yang Terdampak --</option>
    + <option value="">-- Semua Mitra Kerja yang Terdampak --</option>
  L148
    - <th>Karyawan</th>
    + <th>Mitra Kerja</th>

## application\views\bpjs\config.php  (7 baris)
  L27
    - <p class="text-muted">Isi <b>beban karyawan</b> & <b>beban perusahaan</b> (total &amp; persen terhitung otomatis), atau isi <b>total</b> &amp; <b>persen karyawan</b> (beban terhitung otomatis).</p>
    + <p class="text-muted">Isi <b>beban mitra kerja</b> & <b>beban perusahaan</b> (total &amp; persen terhitung otomatis), atau isi <b>total</b> &amp; <b>persen mitra kerja</b> (beban terhitung otomatis).</p>
  L34
    - <label class="form-label">% Ditanggung Karyawan</label>
    + <label class="form-label">% Ditanggung Mitra Kerja</label>
  L38
    - <label class="form-label">Beban Karyawan (Potongan)</label>
    + <label class="form-label">Beban Mitra Kerja (Potongan)</label>
  L55
    - <p class="text-muted">Isi <b>beban karyawan</b> & <b>beban perusahaan</b> (total &amp; persen terhitung otomatis), atau isi <b>total</b> &amp; <b>persen karyawan</b> (beban terhitung otomatis).</p>
    + <p class="text-muted">Isi <b>beban mitra kerja</b> & <b>beban perusahaan</b> (total &amp; persen terhitung otomatis), atau isi <b>total</b> &amp; <b>persen mitra kerja</b> (beban terhitung otomatis).</p>
  L62
    - <label class="form-label">% Ditanggung Karyawan</label>
    + <label class="form-label">% Ditanggung Mitra Kerja</label>
  L66
    - <label class="form-label">Beban Karyawan (Potongan)</label>
    + <label class="form-label">Beban Mitra Kerja (Potongan)</label>
  L83
    - <p class="text-muted">Diberikan ke karyawan yang membayar BPJS sendiri (setelah bukti di-ACC admin).</p>
    + <p class="text-muted">Diberikan ke mitra kerja yang membayar BPJS sendiri (setelah bukti di-ACC admin).</p>

## application\views\bpjs\list.php  (3 baris)
  L20
    - <h6 class="card-title mb-0">Riwayat Pembayaran BPJS Karyawan</h6>
    + <h6 class="card-title mb-0">Riwayat Pembayaran BPJS Mitra Kerja</h6>
  L26
    - <i class="mdi mdi-information"></i> Karyawan <b>"Dibayar Kantor"</b> otomatis masuk potongan BPJS di fee; karyawan <b>mandiri yang sudah di-ACC</b> otomatis masuk insentif. Tombol <b>Sinkron ke Fee</b> menerapkan ulang semua data periode ini (mis. setelah ubah konfigurasi). Tidak berlaku bila pembagian fee periode sudah final.
    + <i class="mdi mdi-information"></i> Mitra Kerja <b>"Dibayar Kantor"</b> otomatis masuk potongan BPJS di fee; mitra kerja <b>mandiri yang sudah di-ACC</b> otomatis masuk insentif. Tombol <b>Sinkron ke Fee</b> menerapkan ulang semua data periode ini (mis. setelah ubah konfigurasi). Tidak berlaku bila pembagian fee periode sudah final.
  L71
    - <th>Karyawan</th>
    + <th>Mitra Kerja</th>

## application\views\hr\leave\acc\detail.php  (1 baris)
  L42
    - <td style="width: 33.3%"><i class="dripicons-tags"></i> Karyawan<br><b><?= $leave['first_name'] ?><br><small class="text-muted">Kode : <?= $leave['employee_code'] ?></small></b></td>
    + <td style="width: 33.3%"><i class="dripicons-tags"></i> Mitra Kerja<br><b><?= $leave['first_name'] ?><br><small class="text-muted">Kode : <?= $leave['employee_code'] ?></small></b></td>

## application\views\hr\leave\list\detail.php  (1 baris)
  L42
    - <td style="width: 33.3%"><i class="dripicons-tags"></i> Karyawan<br><b><?= $leave['first_name'] ?><br><small class="text-muted">Kode : <?= $leave['employee_code'] ?></small></b></td>
    + <td style="width: 33.3%"><i class="dripicons-tags"></i> Mitra Kerja<br><b><?= $leave['first_name'] ?><br><small class="text-muted">Kode : <?= $leave['employee_code'] ?></small></b></td>

## application\views\hr\leave\list\index.php  (1 baris)
  L85
    - <label class="form-label" for="formrow-password-input">Karyawan</label>
    + <label class="form-label" for="formrow-password-input">Mitra Kerja</label>

## application\views\hr\overtime\acc\detail.php  (1 baris)
  L42
    - <td><i class="dripicons-tags"></i> Karyawan<br><b><?= $overtime['first_name'] ?><br><small class="text-muted">Kode : <?= $overtime['employee_code'] ?></small></b></td>
    + <td><i class="dripicons-tags"></i> Mitra Kerja<br><b><?= $overtime['first_name'] ?><br><small class="text-muted">Kode : <?= $overtime['employee_code'] ?></small></b></td>

## application\views\hr\overtime\list\detail.php  (1 baris)
  L42
    - <td><i class="dripicons-tags"></i> Karyawan<br><b><?= $overtime['first_name'] ?><br><small class="text-muted">Kode : <?= $overtime['employee_code'] ?></small></b></td>
    + <td><i class="dripicons-tags"></i> Mitra Kerja<br><b><?= $overtime['first_name'] ?><br><small class="text-muted">Kode : <?= $overtime['employee_code'] ?></small></b></td>

## application\views\hr\overtime\list\index.php  (5 baris)
  L80
    - <label class="form-label" for="formrow-password-input">Karyawan</label>
    + <label class="form-label" for="formrow-password-input">Mitra Kerja</label>
  L96
    - <th>Karyawan</th>
    + <th>Mitra Kerja</th>
  L115
    - <td colspan="4" class="text-muted text-center">Pilih karyawan untuk membuat baris lembur.</td>
    + <td colspan="4" class="text-muted text-center">Pilih mitra kerja untuk membuat baris lembur.</td>
  L153
    - <tr><th>Karyawan</th><td id="e_employee_label"></td></tr>
    + <tr><th>Mitra Kerja</th><td id="e_employee_label"></td></tr>
  L227
    - tbody.html('<tr class="overtime-empty-row"><td colspan="4" class="text-muted text-center">Pilih karyawan untuk membuat baris lembur.</td></tr>');
    + tbody.html('<tr class="overtime-empty-row"><td colspan="4" class="text-muted text-center">Pilih mitra kerja untuk membuat baris lembur.</td></tr>');

## application\views\hr\payroll\detail.php  (7 baris)
  L70
    - Total Karyawan
    + Total Mitra Kerja
  L173
    - <input id="autocomplete" type="text" class="form-control" placeholder="Cari Karyawan...">
    + <input id="autocomplete" type="text" class="form-control" placeholder="Cari Mitra Kerja...">
  L238
    - <small class="text-muted d-block mt-2">Gunakan template dari tombol Download Template. Nilai pada file akan mengganti komisi dan potongan karyawan yang ada di file untuk periode ini.</small>
    + <small class="text-muted d-block mt-2">Gunakan template dari tombol Download Template. Nilai pada file akan mengganti komisi dan potongan mitra kerja yang ada di file untuk periode ini.</small>
  L308
    - <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">EXPORT PDF SLIP PEMBAGIAN FEE KARYAWAN</h5>
    + <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">EXPORT PDF SLIP PEMBAGIAN FEE MITRA KERJA</h5>
  L514
    - alert('Silahkan pilih minimal 1 karyawan')
    + alert('Silahkan pilih minimal 1 mitra kerja')
  L695
    - ? 'Hitung ulang 5 komisi otomatis (Disiplin/Transport/Beras/Soskes/Sholat) berdasarkan data presensi terkini?\n\nNilai auto akan menimpa entri manual HR pada 5 item ini; THP per karyawan akan diperbarui.'
    + ? 'Hitung ulang 5 komisi otomatis (Disiplin/Transport/Beras/Soskes/Sholat) berdasarkan data presensi terkini?\n\nNilai auto akan menimpa entri manual HR pada 5 item ini; THP per mitra kerja akan diperbarui.'
  L696
    - : 'Hitung otomatis 5 komisi (Disiplin/Transport/Beras/Soskes/Sholat) dari data presensi terkini? Nilai akan tertulis ke kolom Komisi Lainnya untuk semua karyawan periode ini.';
    + : 'Hitung otomatis 5 komisi (Disiplin/Transport/Beras/Soskes/Sholat) dari data presensi terkini? Nilai akan tertulis ke kolom Komisi Lainnya untuk semua mitra kerja periode ini.';

## application\views\hr\payroll\index.php  (2 baris)
  L85
    - <th class="text-center" style="width: 18%">JUMLAH KARYAWAN</th>
    + <th class="text-center" style="width: 18%">JUMLAH MITRA KERJA</th>
  L196
    - <th class="text-center" style="width: 13%">JML KARYAWAN</th>
    + <th class="text-center" style="width: 13%">JML MITRA KERJA</th>

## application\views\hr\payroll\_payrollTable.php  (4 baris)
  L214
    - <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Insentif Karyawan</h5>
    + <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Insentif Mitra Kerja</h5>
  L257
    - <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Potongan Pribadi Karyawan</h5>
    + <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Potongan Pribadi Mitra Kerja</h5>
  L296
    - <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Denda Karyawan</h5>
    + <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Denda Mitra Kerja</h5>
  L340
    - <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Presensi Karyawan</h5>
    + <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Presensi Mitra Kerja</h5>

## application\views\hr\payroll\_payrollTableDone.php  (4 baris)
  L259
    - <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-search"></i> Insentif Karyawan</h5>
    + <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-search"></i> Insentif Mitra Kerja</h5>
  L296
    - <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-search"></i> Pemotongan Fee Karyawan</h5>
    + <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-search"></i> Pemotongan Fee Mitra Kerja</h5>
  L333
    - <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-search"></i> Presensi Karyawan</h5>
    + <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-search"></i> Presensi Mitra Kerja</h5>
  L485
    - <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-search"></i> Denda Karyawan</h5>
    + <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-search"></i> Denda Mitra Kerja</h5>

## application\views\hr\presence\detail.php  (6 baris)
  L408
    - <input id="autocomplete" type="text" class="form-control" placeholder="Cari Karyawan...">
    + <input id="autocomplete" type="text" class="form-control" placeholder="Cari Mitra Kerja...">
  L746
    - <th style="width: 15%; background-color: #eee">Karyawan</th>
    + <th style="width: 15%; background-color: #eee">Mitra Kerja</th>
  L1051
    - Data penjadwalan kerja atau shift karyawan akan dikembalikan seperti semula sesuai settingan default dari system.
    + Data penjadwalan kerja atau shift mitra kerja akan dikembalikan seperti semula sesuai settingan default dari system.
  L1121
    - <th>Karyawan</th>
    + <th>Mitra Kerja</th>
  L1882
    - ' | <b>Cocok karyawan:</b> ' + (summary.mapped_rows || 0) +
    + ' | <b>Cocok mitra kerja:</b> ' + (summary.mapped_rows || 0) +
  L1911
    - : '<span class="badge bg-warning text-dark">Karyawan tidak ditemukan</span>';
    + : '<span class="badge bg-warning text-dark">Mitra Kerja tidak ditemukan</span>';

## application\views\hr\presence\work_schedule.php  (11 baris)
  L193
    - <label>Cari Karyawan</label>
    + <label>Cari Mitra Kerja</label>
  L222
    - <strong>Roster Karyawan</strong>
    + <strong>Roster Mitra Kerja</strong>
  L223
    - <div class="small text-muted">Drag foto/icon karyawan ke shift. Drop ke OFF untuk menghapus jadwal hari itu.</div>
    + <div class="small text-muted">Drag foto/icon mitra kerja ke shift. Drop ke OFF untuk menghapus jadwal hari itu.</div>
  L344
    - <small class="text-muted">Total karyawan: <?= count($employees) ?>. Jadwal kosong akan menghapus jadwal pada tanggal tersebut.</small>
    + <small class="text-muted">Total mitra kerja: <?= count($employees) ?>. Jadwal kosong akan menghapus jadwal pada tanggal tersebut.</small>
  L361
    - <p class="text-muted">Kosongkan jadwal (no schedule) untuk satu karyawan pada rentang tanggal tertentu. Perubahan baru tersimpan setelah klik <b>Simpan Jadwal</b>.</p>
    + <p class="text-muted">Kosongkan jadwal (no schedule) untuk satu mitra kerja pada rentang tanggal tertentu. Perubahan baru tersimpan setelah klik <b>Simpan Jadwal</b>.</p>
  L363
    - <label>Karyawan</label>
    + <label>Mitra Kerja</label>
  L365
    - <option value="">-- Pilih karyawan --</option>
    + <option value="">-- Pilih mitra kerja --</option>
  L654
    - $('#gameRosterList').html(html || '<div class="text-muted small p-2">Tidak ada karyawan yang cocok.</div>');
    + $('#gameRosterList').html(html || '<div class="text-muted small p-2">Tidak ada mitra kerja yang cocok.</div>');
  L757
    - html += '<div class="game-empty-lane">Drop karyawan ke sini</div>';
    + html += '<div class="game-empty-lane">Drop mitra kerja ke sini</div>';
  L1433
    - if(!empId){ alert('Pilih karyawan dulu.'); return; }
    + if(!empId){ alert('Pilih mitra kerja dulu.'); return; }
  L1438
    - if(!$row.length){ alert('Karyawan tidak ditemukan di tabel.'); return; }
    + if(!$row.length){ alert('Mitra Kerja tidak ditemukan di tabel.'); return; }

## application\views\hr\schedule\shift.php  (2 baris)
  L124
    - <small class="text-muted">Merupakan rentang waktu kerja karyawan</small>
    + <small class="text-muted">Merupakan rentang waktu kerja mitra kerja</small>
  L347
    - <small class="text-muted">Merupakan rentang waktu kerja karyawan</small>
    + <small class="text-muted">Merupakan rentang waktu kerja mitra kerja</small>

## application\views\layout\admin.php  (2 baris)
  L246
    - <a href="<?= site_url('master_data/employee') ?>" class="dropdown-item"><i class="dripicons-user"></i> Karyawan</a>
    + <a href="<?= site_url('master_data/employee') ?>" class="dropdown-item"><i class="dripicons-user"></i> Mitra Kerja</a>
  L358
    - <i class="mdi mdi-flag-outline"></i> Laporan Karyawan
    + <i class="mdi mdi-flag-outline"></i> Laporan Mitra Kerja

## application\views\m\home.php  (1 baris)
  L20
    - <div style="font-size:16px;font-weight:600"><?= htmlspecialchars($emp['first_name'] ?? 'Karyawan') ?></div>
    + <div style="font-size:16px;font-weight:600"><?= htmlspecialchars($emp['first_name'] ?? 'Mitra Kerja') ?></div>

## application\views\master_data\deduction\index.php  (9 baris)
  L5
    - <h4 class="mb-0"><i class="dripicons-biefcase"></i>Potongan Karyawan</h4>
    + <h4 class="mb-0"><i class="dripicons-biefcase"></i>Potongan Mitra Kerja</h4>
  L10
    - <li class="breadcrumb-item active"><a href="<?= site_url('master_data/deduction') ?>">Potongan Karyawan</a></li>
    + <li class="breadcrumb-item active"><a href="<?= site_url('master_data/deduction') ?>">Potongan Mitra Kerja</a></li>
  L23
    - <h6 class="card-title">Daftar Potongan Karyawan</h6>
    + <h6 class="card-title">Daftar Potongan Mitra Kerja</h6>
  L30
    - <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAdd" class="btn btn-primary"><i class="dripicons-plus"></i> Tambah Potongan Karyawan</a>
    + <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAdd" class="btn btn-primary"><i class="dripicons-plus"></i> Tambah Potongan Mitra Kerja</a>
  L71
    - <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Tambah Potongan Karyawan</h5>
    + <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Tambah Potongan Mitra Kerja</h5>
  L81
    - <input type="text" required="" autocomplete="off" placeholder="Nama Potongan Karyawan" class="form-control" name="deduction_name">
    + <input type="text" required="" autocomplete="off" placeholder="Nama Potongan Mitra Kerja" class="form-control" name="deduction_name">
  L114
    - <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Ubah Potongan Karyawan</h5>
    + <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Ubah Potongan Mitra Kerja</h5>
  L124
    - <input type="text" required="" autocomplete="off" placeholder="Nama Potongan Karyawan" class="form-control" name="deduction_name" id="e_name">
    + <input type="text" required="" autocomplete="off" placeholder="Nama Potongan Mitra Kerja" class="form-control" name="deduction_name" id="e_name">
  L157
    - <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">Hapus Potongan Karyawan</h5>
    + <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">Hapus Potongan Mitra Kerja</h5>

## application\views\master_data\employee\index.php  (25 baris)
  L6
    - <h4 class="mb-0"><i class="dripicons-inbox"></i> Karyawan</h4>
    + <h4 class="mb-0"><i class="dripicons-inbox"></i> Mitra Kerja</h4>
  L11
    - <li class="breadcrumb-item active"><a href="<?= site_url('master_data/employee') ?>">Karyawan</a></li>
    + <li class="breadcrumb-item active"><a href="<?= site_url('master_data/employee') ?>">Mitra Kerja</a></li>
  L24
    - <h6 class="card-title">Daftar Karyawan</h6>
    + <h6 class="card-title">Daftar Mitra Kerja</h6>
  L32
    - <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAdd" class="btn btn-primary"><i class="dripicons-plus"></i> Tambah Karyawan</a> &emsp;
    + <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAdd" class="btn btn-primary"><i class="dripicons-plus"></i> Tambah Mitra Kerja</a> &emsp;
  L36
    - <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalExportEmployee" class="btn btn-outline-danger"><i class="fa fa-file-excel"></i> Download Daftar Karyawan</a>
    + <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalExportEmployee" class="btn btn-outline-danger"><i class="fa fa-file-excel"></i> Download Daftar Mitra Kerja</a>
  L57
    - <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalExportEmployee" class="btn btn-outline-danger"><i class="fa fa-file-excel"></i> Download Daftar Karyawan</a>
    + <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalExportEmployee" class="btn btn-outline-danger"><i class="fa fa-file-excel"></i> Download Daftar Mitra Kerja</a>
  L77
    - <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Tambah Karyawan</h5>
    + <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Tambah Mitra Kerja</h5>
  L87
    - <input type="text" required="" autocomplete="off" placeholder="ID Fingerprint karyawan" class="form-control" name="employee_code">
    + <input type="text" required="" autocomplete="off" placeholder="ID Fingerprint mitra kerja" class="form-control" name="employee_code">
  L93
    - <input type="text" required="" autocomplete="off" placeholder="Nama karyawan" class="form-control" name="first_name">
    + <input type="text" required="" autocomplete="off" placeholder="Nama mitra kerja" class="form-control" name="first_name">
  L261
    - <option value="2">Karyawan</option>
    + <option value="2">Mitra Kerja</option>
  L289
    - <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Ubah Karyawan</h5>
    + <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Ubah Mitra Kerja</h5>
  L305
    - <input type="text" required="" autocomplete="off" placeholder="Nama karyawan" class="form-control" name="first_name" id="e_name">
    + <input type="text" required="" autocomplete="off" placeholder="Nama mitra kerja" class="form-control" name="first_name" id="e_name">
  L474
    - <option value="2">Karyawan</option>
    + <option value="2">Mitra Kerja</option>
  L506
    - <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">Hapus Karyawan</h5>
    + <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">Hapus Mitra Kerja</h5>
  L517
    - Data yang telah dihapus tidak dapat dikembalikan lagi dan semua data yang berhubungan dengan karyawan ini juga akan terhapus
    + Data yang telah dihapus tidak dapat dikembalikan lagi dan semua data yang berhubungan dengan mitra kerja ini juga akan terhapus
  L537
    - <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">Status Karyawan</h5>
    + <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">Status Mitra Kerja</h5>
  L548
    - Status data karyawan akan diubah
    + Status data mitra kerja akan diubah
  L605
    - <h5 class="modal-title" id="modalExportEmployeeLabel"><i class="fa fa-file-excel"></i> Export Data Karyawan</h5>
    + <h5 class="modal-title" id="modalExportEmployeeLabel"><i class="fa fa-file-excel"></i> Export Data Mitra Kerja</h5>
  L630
    - <label class="form-label">Status Karyawan</label>
    + <label class="form-label">Status Mitra Kerja</label>
  L632
    - <option value="all">Semua Karyawan</option>
    + <option value="all">Semua Mitra Kerja</option>
  L633
    - <option value="active">Karyawan Aktif</option>
    + <option value="active">Mitra Kerja Aktif</option>
  L634
    - <option value="inactive">Karyawan Tidak Aktif</option>
    + <option value="inactive">Mitra Kerja Tidak Aktif</option>
  L678
    - <h5 class="modal-title" id="staticBackdropLabel">Upload Karyawan</h5>
    + <h5 class="modal-title" id="staticBackdropLabel">Upload Mitra Kerja</h5>
  L698
    - <button class="btn btn-success" id="btnUpload">Upload Karyawan</button> &nbsp;
    + <button class="btn btn-success" id="btnUpload">Upload Mitra Kerja</button> &nbsp;
  L1042
    - btn.html('Upload Karyawan').removeAttr('disabled');
    + btn.html('Upload Mitra Kerja').removeAttr('disabled');

## application\views\report\index.php  (3 baris)
  L5
    - <h4 class="mb-0"><i class="mdi mdi-flag-outline"></i> Laporan Karyawan (PWA)</h4>
    + <h4 class="mb-0"><i class="mdi mdi-flag-outline"></i> Laporan Mitra Kerja (PWA)</h4>
  L8
    - <li class="breadcrumb-item active">Laporan Karyawan</li>
    + <li class="breadcrumb-item active">Laporan Mitra Kerja</li>
  L49
    - $empName = htmlspecialchars(trim($r['emp_name']) ?: 'Karyawan #'.$r['user_id']);
    + $empName = htmlspecialchars(trim($r['emp_name']) ?: 'Mitra Kerja #'.$r['user_id']);

## application\views\sync\index.php  (1 baris)
  L616
    - html += '<div class="mb-3"><h6>✅ Karyawan Cocok (' + res.matched_count + ')</h6>';
    + html += '<div class="mb-3"><h6>✅ Mitra Kerja Cocok (' + res.matched_count + ')</h6>';

## application\views\wa\config.php  (1 baris)
  L158
    - <i class="mdi mdi-account-alert text-danger"></i> Notif Karyawan Tidak Hadir
    + <i class="mdi mdi-account-alert text-danger"></i> Notif Mitra Kerja Tidak Hadir

## application\libraries\Kirimi_wa.php  (4 baris)
  L164
    - $msg .= "👥 Total Karyawan : {$row['total_employee']}\n";
    + $msg .= "👥 Total Mitra Kerja : {$row['total_employee']}\n";
  L192
    - $msg .= "Total: {$count} karyawan\n";
    + $msg .= "Total: {$count} mitra kerja\n";
  L281
    - $msg .= "Total: {$count} karyawan\n";
    + $msg .= "Total: {$count} mitra kerja\n";
  L285
    - $msg .= "\nSemua karyawan yang bertugas pada shift hari ini sudah memiliki presensi.\n";
    + $msg .= "\nSemua mitra kerja yang bertugas pada shift hari ini sudah memiliki presensi.\n";

## pwa\index.html  (2 baris)
  L7
    - <title>Karyawan Tiffany</title>
    + <title>Mitra Kerja Tiffany</title>
  L18
    - <div class="brand"><div class="brand-logo">T</div><h1>E-Karyawan</h1><p>Tiffany Houseware &amp; Mart</p></div>
    + <div class="brand"><div class="brand-logo">T</div><h1>E-Mitra Kerja</h1><p>Tiffany Houseware &amp; Mart</p></div>

## pwa\manifest.json  (3 baris)
  L2
    - "name": "E-Karyawan Tiffany",
    + "name": "E-Mitra Kerja Tiffany",
  L3
    - "short_name": "E-Karyawan",
    + "short_name": "E-Mitra Kerja",
  L4
    - "description": "Jadwal shift & slip pembagian fee karyawan Tiffany Houseware & Mart",
    + "description": "Jadwal shift & slip pembagian fee mitra kerja Tiffany Houseware & Mart",

## tools\payroll_sim\src\App.jsx  (1 baris)
  L161
    - placeholder="Cari nama atau kode karyawan..."
    + placeholder="Cari nama atau kode mitra kerja..."

## tools\payroll_sim\src\components\FlowCanvas.jsx  (1 baris)
  L119
    - <p>Cari karyawan → pilih periode → klik <strong>Hitung</strong></p>
    + <p>Cari mitra kerja → pilih periode → klik <strong>Hitung</strong></p>

## tools\dat_reader\src\App.jsx  (1 baris)
  L248
    - <Stat label="Karyawan" value={recap.karyawan} />
    + <Stat label="Mitra Kerja" value={recap.karyawan} />

## tools\payroll_importer\src\App.jsx  (2 baris)
  L207
    - {hasKey ? <span className="ok">✓ Kunci karyawan dipetakan</span> : <span className="no">✗ Kolom kunci karyawan belum dipetakan</span>}
    + {hasKey ? <span className="ok">✓ Kunci mitra kerja dipetakan</span> : <span className="no">✗ Kolom kunci mitra kerja belum dipetakan</span>}
  L224
    - <li><b>{result.matched}</b> karyawan cocok</li>
    + <li><b>{result.matched}</b> mitra kerja cocok</li>

## tools\payroll_sim\calculator.js  (1 baris)
  L79
    - if (!empRows.length) throw new Error('Karyawan tidak ditemukan');
    + if (!empRows.length) throw new Error('Mitra Kerja tidak ditemukan');


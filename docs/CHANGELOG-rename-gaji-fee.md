# Changelog rename Gaji->Fee / Penggajian->Pembagian Fee
# Tanggal: 2026-07-15
# Mode: APPLIED
#
# SCOPE: HANYA label yang tampil ke user (interface + laporan + file export).
# TIDAK disentuh: nama tabel/kolom DB, rute/URL, nama class/file, variabel,
# ID elemen JS (btnSaveGaji/btnSyncGaji/btnRollbackLock — dilindungi word-boundary),
# komentar kode, dan subsistem PPh21 (istilah pajak tetap 'Gaji').
#
# Peta frasa: Gaji->Fee | Penggajian->Pembagian Fee | Gaji Pokok->Fee Pokok |
#             Slip Gaji->Slip Pembagian Fee | potongan gaji->Potongan Fee.
#
# ROLLBACK: `git revert <commit>` atau kembalikan per-baris dari daftar +/- di
# bawah (baris '-' = nilai lama, '+' = nilai baru). 96 baris di 23 file.
# Semua 23 file PHP sudah lulus `php -l` di server. Live belum di-deploy.

## application\views\attendance\early_leave_report.php  (2 baris)
  L106
    - <code>(Gaji Pokok &divide; <?= (int) $days_in_month ?> hari &divide; 10 jam)</code> &times;
    + <code>(Fee Pokok &divide; <?= (int) $days_in_month ?> hari &divide; 10 jam)</code> &times;
  L128
    - <th class="text-end">Gaji / jam</th>
    + <th class="text-end">Fee / jam</th>

## application\views\bpjs\list.php  (3 baris)
  L21
    - <button id="btnSyncGaji" class="btn btn-sm btn-success"><i class="mdi mdi-sync"></i> Sinkron ke Gaji</button>
    + <button id="btnSyncGaji" class="btn btn-sm btn-success"><i class="mdi mdi-sync"></i> Sinkron ke Fee</button>
  L26
    - <i class="mdi mdi-information"></i> Karyawan <b>"Dibayar Kantor"</b> otomatis masuk potongan BPJS di gaji; karyawan <b>mandiri yang sudah di-ACC</b> otomatis masuk insentif. Tombol <b>Sinkron ke Gaji</b> menerapkan ulang semua data periode ini (mis. setelah ubah konfigurasi). Tidak berlaku bila penggajian periode sudah final.
    + <i class="mdi mdi-information"></i> Karyawan <b>"Dibayar Kantor"</b> otomatis masuk potongan BPJS di fee; karyawan <b>mandiri yang sudah di-ACC</b> otomatis masuk insentif. Tombol <b>Sinkron ke Fee</b> menerapkan ulang semua data periode ini (mis. setelah ubah konfigurasi). Tidak berlaku bila pembagian fee periode sudah final.
  L176
    - .always(function(){ $b.removeAttr('disabled').html('<i class="mdi mdi-sync"></i> Sinkron ke Gaji'); });
    + .always(function(){ $b.removeAttr('disabled').html('<i class="mdi mdi-sync"></i> Sinkron ke Fee'); });

## application\views\employee\index.php  (1 baris)
  L59
    - <p class="mb-0 mt-2" style="color:#333">Penggajian</p>
    + <p class="mb-0 mt-2" style="color:#333">Pembagian Fee</p>

## application\views\hr\leave\list\index.php  (2 baris)
  L144
    - <label class="form-label" for="formrow-password-input">Besar Potongan Gaji Harian</label>
    + <label class="form-label" for="formrow-password-input">Besar Potongan Fee Harian</label>
  L146
    - <small class="text-muted">*Besaran gaji harian yang dipotong saat rentang perizinan</small>
    + <small class="text-muted">*Besaran fee harian yang dipotong saat rentang perizinan</small>

## application\views\hr\payroll\_payrollTableDone.php  (1 baris)
  L296
    - <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-search"></i> Pemotongan Gaji Karyawan</h5>
    + <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-search"></i> Pemotongan Fee Karyawan</h5>

## application\views\hr\payroll\detail.php  (27 baris)
  L5
    - <h4 class="mb-0"><i class="dripicons-experiments"></i> Penggajian</h4>
    + <h4 class="mb-0"><i class="dripicons-experiments"></i> Pembagian Fee</h4>
  L9
    - <li class="breadcrumb-item"><a href="javascript:void(0);">Penggajian</a></li>
    + <li class="breadcrumb-item"><a href="javascript:void(0);">Pembagian Fee</a></li>
  L10
    - <li class="breadcrumb-item"><a href="<?= site_url('hr/payroll') ?>">Daftar Penggajian</a></li>
    + <li class="breadcrumb-item"><a href="<?= site_url('hr/payroll') ?>">Daftar Pembagian Fee</a></li>
  L24
    - <h6 class="card-title">Detail Penggajian</h6>
    + <h6 class="card-title">Detail Pembagian Fee</h6>
  L110
    - Total Penggajian
    + Total Fee
  L124
    - <a class="dropdown-item" target="_blank" href="<?= site_url('hr/payroll/'.$payroll['month'].'/'.$payroll['year'].'/print?branch_id='.$branch_id) ?>"><i class="fa fa-file"></i> Rangkuman Gaji</a>
    + <a class="dropdown-item" target="_blank" href="<?= site_url('hr/payroll/'.$payroll['month'].'/'.$payroll['year'].'/print?branch_id='.$branch_id) ?>"><i class="fa fa-file"></i> Rangkuman Fee</a>
  L125
    - <a id="btnShowPayrollSlip" class="dropdown-item" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalExportPayrollSlip"><i class="fa fa-file-pdf"></i> Export Slip Gaji</a>
    + <a id="btnShowPayrollSlip" class="dropdown-item" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalExportPayrollSlip"><i class="fa fa-file-pdf"></i> Export Slip Pembagian Fee</a>
  L205
    - <button class="btn btn-warning" id="btnGenerate"><i class="fa fa-lock"></i> Lock Gaji</button>
    + <button class="btn btn-warning" id="btnGenerate"><i class="fa fa-lock"></i> Lock Fee</button>
  L209
    - <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalRollback"><i class="fa fa-refresh"></i> Rollback Gaji Ke Tahap Awal</button> &emsp;
    + <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalRollback"><i class="fa fa-refresh"></i> Rollback Fee Ke Tahap Awal</button> &emsp;
  L210
    - <button class="btn btn-success" id="btnSaveGaji"><i class="fa fa-check-circle"></i> Simpan Gaji</button>
    + <button class="btn btn-success" id="btnSaveGaji"><i class="fa fa-check-circle"></i> Simpan Fee</button>
  L213
    - <button class="btn btn-outline-danger" id="btnRollbackLock"><i class="fa fa-refresh"></i> Rollback Gaji Ke Tahap Lock</button>
    + <button class="btn btn-outline-danger" id="btnRollbackLock"><i class="fa fa-refresh"></i> Rollback Fee Ke Tahap Lock</button>
  L260
    - <small class="text-muted d-block mt-2">Isi ID Fingerprint, Tanggal Lembur, dan Jam Lembur. Data akan masuk sebagai lembur approved dan ikut dihitung saat Lock Gaji.</small>
    + <small class="text-muted d-block mt-2">Isi ID Fingerprint, Tanggal Lembur, dan Jam Lembur. Data akan masuk sebagai lembur approved dan ikut dihitung saat Lock Fee.</small>
  L280
    - <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">ROLLBACK GAJI TAHAP AWAL</h5>
    + <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">ROLLBACK FEE TAHAP AWAL</h5>
  L290
    - <h6><b>Rollback Tahap Awal / Batalkan Penggajian Periode Ini ?</b></h6>
    + <h6><b>Rollback Tahap Awal / Batalkan Pembagian Fee Periode Ini ?</b></h6>
  L291
    - <div id="modal-info-msg">Rollback Tahap Awal merupakan pembatalan penggajian bulan ini yang mengakibatkan data gaji yang sudah diinput saat ini hilang. <br><br><b class="text-danger">Pastikan anda mempunyai Copy / PDF / Salinan dari hasil penggajian saat ini !</b></div>
    + <div id="modal-info-msg">Rollback Tahap Awal merupakan pembatalan pembagian fee bulan ini yang mengakibatkan data fee yang sudah diinput saat ini hilang. <br><br><b class="text-danger">Pastikan anda mempunyai Copy / PDF / Salinan dari hasil pembagian fee saat ini !</b></div>
  L296
    - <button class="btn btn-danger" id="btnRollback">Batalkan Penggajian</button>
    + <button class="btn btn-danger" id="btnRollback">Batalkan Pembagian Fee</button>
  L308
    - <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">EXPORT PDF SLIP GAJI KARYAWAN</h5>
    + <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">EXPORT PDF SLIP PEMBAGIAN FEE KARYAWAN</h5>
  L380
    - btn.html('Batalkan Penggajian').removeAttr('disabled');
    + btn.html('Batalkan Pembagian Fee').removeAttr('disabled');
  L389
    - var r = confirm('Apakah anda yakin menyimpan gaji periode ini ?')
    + var r = confirm('Apakah anda yakin menyimpan fee periode ini ?')
  L408
    - btn.html('<i class="fa fa-check-circle"></i> Simpan Gaji').removeAttr('disabled');
    + btn.html('<i class="fa fa-check-circle"></i> Simpan Fee').removeAttr('disabled');
  L419
    - var r = confirm('Apakah anda yakin mengembalikan proses gaji ke tahap lock ?')
    + var r = confirm('Apakah anda yakin mengembalikan proses fee ke tahap lock ?')
  L441
    - btn.html('<i class="fa fa-check-circle"></i> Simpan Gaji').removeAttr('disabled');
    + btn.html('<i class="fa fa-check-circle"></i> Simpan Fee').removeAttr('disabled');
  L551
    - title: 'Password Lock Gaji',
    + title: 'Password Lock Fee',
  L566
    - r = confirm('Apakah anda yakin melakukan LOCK penggajian bulan ini ?');
    + r = confirm('Apakah anda yakin melakukan LOCK pembagian fee bulan ini ?');
  L593
    - btn.html('<i class="fa fa-check-circle"></i> Generate Gaji').removeAttr('disabled');
    + btn.html('<i class="fa fa-check-circle"></i> Generate Fee').removeAttr('disabled');
  L597
    - btn.html('<i class="fa fa-lock"></i> Lock Gaji').removeAttr('disabled');
    + btn.html('<i class="fa fa-lock"></i> Lock Fee').removeAttr('disabled');
  L611
    - btn.html('<i class="fa fa-check-circle"></i> Generate Gaji').removeAttr('disabled');
    + btn.html('<i class="fa fa-check-circle"></i> Generate Fee').removeAttr('disabled');

## application\views\hr\payroll\employee_view\index.php  (4 baris)
  L5
    - <h4 class="mb-0"><i class="dripicons-experiments"></i> Penggajian</h4>
    + <h4 class="mb-0"><i class="dripicons-experiments"></i> Pembagian Fee</h4>
  L9
    - <li class="breadcrumb-item"><a href="javascript:void(0);">Penggajian</a></li>
    + <li class="breadcrumb-item"><a href="javascript:void(0);">Pembagian Fee</a></li>
  L10
    - <li class="breadcrumb-item active"><a href="<?= site_url('hr/payroll') ?>">Daftar Penggajian</a></li>
    + <li class="breadcrumb-item active"><a href="<?= site_url('hr/payroll') ?>">Daftar Pembagian Fee</a></li>
  L23
    - <h6 class="card-title">Daftar Penggajian</h6>
    + <h6 class="card-title">Daftar Pembagian Fee</h6>

## application\views\hr\payroll\index.php  (6 baris)
  L5
    - <h4 class="mb-0"><i class="dripicons-experiments"></i> Penggajian</h4>
    + <h4 class="mb-0"><i class="dripicons-experiments"></i> Pembagian Fee</h4>
  L9
    - <li class="breadcrumb-item"><a href="javascript:void(0);">Penggajian</a></li>
    + <li class="breadcrumb-item"><a href="javascript:void(0);">Pembagian Fee</a></li>
  L10
    - <li class="breadcrumb-item active"><a href="<?= site_url('hr/payroll') ?>">Daftar Penggajian</a></li>
    + <li class="breadcrumb-item active"><a href="<?= site_url('hr/payroll') ?>">Daftar Pembagian Fee</a></li>
  L23
    - <h6 class="card-title">Daftar Penggajian</h6>
    + <h6 class="card-title">Daftar Pembagian Fee</h6>
  L86
    - <th style="width: 20%" class="text-center">TOTAL PENGGAJIAN</th>
    + <th style="width: 20%" class="text-center">TOTAL FEE</th>
  L197
    - <th style="width: 18%" class="text-center">TOTAL PENGGAJIAN</th>
    + <th style="width: 18%" class="text-center">TOTAL FEE</th>

## application\views\hr\payroll\print.php  (5 baris)
  L35
    - <span style="font-size: 16px"><b>LAPORAN PENGGAJIAN</b></span>
    + <span style="font-size: 16px"><b>LAPORAN PEMBAGIAN FEE</b></span>
  L40
    - <td><span style="color:#444; font-size: 12px">Kode Penggajian</span> <br>#<?= $payroll['payroll_code'] ?></td>
    + <td><span style="color:#444; font-size: 12px">Kode Pembagian Fee</span> <br>#<?= $payroll['payroll_code'] ?></td>
  L42
    - <span style="color:#444; font-size: 12px">Periode Penggajian</span><br>
    + <span style="color:#444; font-size: 12px">Periode Pembagian Fee</span><br>
  L50
    - <span style="color:#444; font-size: 12px">Total Penggajian</span><br>
    + <span style="color:#444; font-size: 12px">Total Fee</span><br>
  L69
    - <th colspan="3" class="text-center" style="background-color:#eee">URAIAN GAJI</th>
    + <th colspan="3" class="text-center" style="background-color:#eee">URAIAN FEE</th>

## application\views\hr\payroll\print_export.php  (1 baris)
  L69
    - <th style="text-align:left">Total Gaji</th>
    + <th style="text-align:left">Total Fee</th>

## application\views\hr\presence\detail.php  (1 baris)
  L869
    - Kekurangan jam dihitung otomatis dan jadi potongan gaji.
    + Kekurangan jam dihitung otomatis dan jadi potongan fee.

## application\views\layout\admin.php  (1 baris)
  L308
    - <a href="<?= site_url('hr/payroll') ?>" class="dropdown-item"><i class="dripicons-wallet"></i> Penggajian</a>
    + <a href="<?= site_url('hr/payroll') ?>" class="dropdown-item"><i class="dripicons-wallet"></i> Pembagian Fee</a>

## application\views\layout\mobile.php  (1 baris)
  L26
    - ['m/payroll',  'payroll',  'account_balance_wallet',  'Gaji'],
    + ['m/payroll',  'payroll',  'account_balance_wallet',  'Fee'],

## application\views\m\home.php  (1 baris)
  L120
    - <i class="material-icons">account_balance_wallet</i><span>Slip Gaji</span>
    + <i class="material-icons">account_balance_wallet</i><span>Slip Pembagian Fee</span>

## application\views\m\payroll.php  (2 baris)
  L12
    - <div class="m-card"><div class="empty-state"><i class="material-icons">account_balance_wallet</i><p>Belum ada slip gaji final di tahun ini</p></div></div>
    + <div class="m-card"><div class="empty-state"><i class="material-icons">account_balance_wallet</i><p>Belum ada slip pembagian fee final di tahun ini</p></div></div>
  L31
    - <div class="payslip-row"><span class="ps-label">Gaji Pokok</span><span class="ps-value"><?= format_rp($s['salary_in_basic']) ?></span></div>
    + <div class="payslip-row"><span class="ps-label">Fee Pokok</span><span class="ps-value"><?= format_rp($s['salary_in_basic']) ?></span></div>

## application\views\master_data\employee\index.php  (4 baris)
  L176
    - <label class="form-label" for="formrow-password-input">Gaji Pokok</label>
    + <label class="form-label" for="formrow-password-input">Fee Pokok</label>
  L183
    - <label class="form-label" for="formrow-password-input">Gaji Minimum</label>
    + <label class="form-label" for="formrow-password-input">Fee Minimum</label>
  L388
    - <label class="form-label" for="formrow-password-input">Gaji Pokok</label>
    + <label class="form-label" for="formrow-password-input">Fee Pokok</label>
  L395
    - <label class="form-label" for="formrow-password-input">Gaji Minimum</label>
    + <label class="form-label" for="formrow-password-input">Fee Minimum</label>

## application\views\master_data\insentif\index.php  (2 baris)
  L90
    - <option value="per_payroll">Diberikan saat penggajian</option>
    + <option value="per_payroll">Diberikan saat pembagian fee</option>
  L152
    - <option value="per_payroll">Diberikan saat penggajian</option>
    + <option value="per_payroll">Diberikan saat pembagian fee</option>

## application\controllers\hr\Payroll.php  (14 baris)
  L399
    - $this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> Penggajian berhasil digenerate', 'success'));
    + $this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> Pembagian Fee berhasil digenerate', 'success'));
  L402
    - 'message' => 'Penggajian berhasil digenerate'
    + 'message' => 'Pembagian Fee berhasil digenerate'
  L416
    - 'message' => 'Gaji sudah pernah digenerate'
    + 'message' => 'Fee sudah pernah digenerate'
  L1080
    - $this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> Penggajian berhasil dirollback', 'success'));
    + $this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> Pembagian Fee berhasil dirollback', 'success'));
  L1129
    - $this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> Penggajian berhasil dirollback', 'success'));
    + $this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> Pembagian Fee berhasil dirollback', 'success'));
  L1132
    - 'message' => 'Penggajian berhasil dikembalikan ke tahap lock'
    + 'message' => 'Pembagian Fee berhasil dikembalikan ke tahap lock'
  L1241
    - . ($payroll ? '' : ' Klik "Lock Gaji" untuk lanjut rekap.'),
    + . ($payroll ? '' : ' Klik "Lock Fee" untuk lanjut rekap.'),
  L1296
    - $this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> Data penggajian periode ini berhasil disimpan', 'success'));
    + $this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> Data pembagian fee periode ini berhasil disimpan', 'success'));
  L1299
    - 'message' => 'Data penggajian periode ini berhasil disimpan'
    + 'message' => 'Data pembagian fee periode ini berhasil disimpan'
  L1360
    - $sheet->setCellValue('A1', 'DAFTAR PENGGAJIAN');
    + $sheet->setCellValue('A1', 'DAFTAR PEMBAGIAN FEE');
  L1404
    - $sheet->setCellValue('F4', 'URAIAN GAJI');
    + $sheet->setCellValue('F4', 'URAIAN FEE');
  L1699
    - $title = "Daftar Penggajian Periode ".get_monthname($month)." ".$year." ".$branch_detail['branch_name']." - Kota ".$branch_detail['city'];
    + $title = "Daftar Pembagian Fee Periode ".get_monthname($month)." ".$year." ".$branch_detail['branch_name']." - Kota ".$branch_detail['city'];
  L1739
    - 'message' => 'Data penggajian ini belum digenerate',
    + 'message' => 'Data pembagian fee ini belum digenerate',
  L1803
    - $zipName = 'Slip Gaji - '.$data['branch_detail']['branch_name'].' - '.get_monthname($data['payroll']['month']).' '.$data['payroll']['year'].'.zip';
    + $zipName = 'Slip Pembagian Fee - '.$data['branch_detail']['branch_name'].' - '.get_monthname($data['payroll']['month']).' '.$data['payroll']['year'].'.zip';

## application\controllers\M.php  (1 baris)
  L515
    - return $this->_json(['status' => false, 'message' => 'Tidak bisa menyetujui izin: tanggal masuk periode penggajian terkunci ('.implode(', ', $locked).'). Rollback penggajian periode tersebut dulu.']);
    + return $this->_json(['status' => false, 'message' => 'Tidak bisa menyetujui izin: tanggal masuk periode pembagian fee terkunci ('.implode(', ', $locked).'). Rollback pembagian fee periode tersebut dulu.']);

## application\controllers\Bpjs.php  (6 baris)
  L122
    - ? '. CATATAN: penggajian periode ini sudah final — rollback dulu agar BPJS masuk ke gaji.'
    + ? '. CATATAN: pembagian fee periode ini sudah final — rollback dulu agar BPJS masuk ke fee.'
  L123
    - : ' & disinkron ke gaji.'),
    + : ' & disinkron ke fee.'),
  L148
    - ? '. CATATAN: penggajian periode ini sudah final — rollback dulu agar insentif masuk ke gaji.'
    + ? '. CATATAN: pembagian fee periode ini sudah final — rollback dulu agar insentif masuk ke fee.'
  L149
    - : ' & insentif disinkron ke gaji.'),
    + : ' & insentif disinkron ke fee.'),
  L166
    - 'message' => 'Penggajian periode ini sudah final. Rollback penggajian dulu agar BPJS bisa disinkron ke gaji.']);
    + 'message' => 'Pembagian Fee periode ini sudah final. Rollback pembagian fee dulu agar BPJS bisa disinkron ke fee.']);
  L170
    - 'message' => $res['count'].' dari '.$res['total'].' data BPJS disinkron ke gaji untuk periode ini.',
    + 'message' => $res['count'].' dari '.$res['total'].' data BPJS disinkron ke fee untuk periode ini.',

## application\controllers\Employee.php  (8 baris)
  L88
    - $this->form_validation->set_rules('salary', 'Gaji', 'required|numeric|greater_than[0]|less_than['.$p['salary'].']');
    + $this->form_validation->set_rules('salary', 'Fee', 'required|numeric|greater_than[0]|less_than['.$p['salary'].']');
  L109
    - $this->form_validation->set_rules('salary', 'Gaji', 'required|numeric|greater_than[0]');
    + $this->form_validation->set_rules('salary', 'Fee', 'required|numeric|greater_than[0]');
  L248
    - $this->form_validation->set_rules('salary', 'Gaji', 'required|numeric|greater_than[0]|less_than['.$p['salary'].']');
    + $this->form_validation->set_rules('salary', 'Fee', 'required|numeric|greater_than[0]|less_than['.$p['salary'].']');
  L261
    - $this->form_validation->set_rules('salary', 'Gaji', 'required|numeric|greater_than[0]');
    + $this->form_validation->set_rules('salary', 'Fee', 'required|numeric|greater_than[0]');
  L609
    - $this->form_validation->set_rules('salary', 'Gaji Pokok', 'required|numeric|greater_than[0]');
    + $this->form_validation->set_rules('salary', 'Fee Pokok', 'required|numeric|greater_than[0]');
  L610
    - $this->form_validation->set_rules('salary_minimum', 'Gaji Minimal', 'required|numeric|greater_than[-1]');
    + $this->form_validation->set_rules('salary_minimum', 'Fee Minimal', 'required|numeric|greater_than[-1]');
  L922
    - $sheet->setCellValue('G3', 'GAJI POKOK');
    + $sheet->setCellValue('G3', 'FEE POKOK');
  L923
    - $sheet->setCellValue('H3', 'GAJI MINIMUM');
    + $sheet->setCellValue('H3', 'FEE MINIMUM');

## application\controllers\hr\Leave.php  (2 baris)
  L347
    - echo json_encode(['status'=>false, 'message'=>'Tidak bisa menyetujui izin: tanggal masuk periode penggajian terkunci ('.implode(', ', $locked).'). Rollback penggajian periode tersebut dulu.']);
    + echo json_encode(['status'=>false, 'message'=>'Tidak bisa menyetujui izin: tanggal masuk periode pembagian fee terkunci ('.implode(', ', $locked).'). Rollback pembagian fee periode tersebut dulu.']);
  L504
    - echo json_encode(['status'=>false, 'message'=>'Tidak bisa mengubah izin: tanggal masuk periode penggajian terkunci ('.implode(', ', $locked).'). Rollback penggajian periode tersebut dulu.']);
    + echo json_encode(['status'=>false, 'message'=>'Tidak bisa mengubah izin: tanggal masuk periode pembagian fee terkunci ('.implode(', ', $locked).'). Rollback pembagian fee periode tersebut dulu.']);

## application\helpers\generic_helper.php  (1 baris)
  L250
    - $txt = 'Diberikan saat penggajian';
    + $txt = 'Diberikan saat pembagian fee';

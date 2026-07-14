<?php $role = $this->ion_auth->get_users_groups()->row()->name; ?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="dripicons-experiments"></i> Pembagian Fee</h4>

            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Pembagian Fee</a></li>
                    <li class="breadcrumb-item"><a href="<?= site_url('hr/payroll') ?>">Daftar Pembagian Fee</a></li>
                    <li class="breadcrumb-item active">Detail</li>
                </ol>
            </div>

        </div>
    </div>
</div>
<!-- end page title -->

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title">Detail Pembagian Fee</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <?= $this->session->flashdata('alert_message') ?>
                    </div>
                </div>

                <?php if($role == 'admin'){ ?>
                    <form>
                    <div class="row mb-4">
                        <div class="col-md-4">
                            Pilih Cabang
                            <select class="form-control" name="branch_id" id="branch">
                                <?php foreach ($branch as $row) { ?>
                                    <option <?= $branch_id == $row['id'] ? 'selected="selected"' : '' ?> value="<?= $row['id'] ?>"><?= $row['branch_code']." / ".$row['branch_name'] ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <br>
                            <button class="btn btn-primary"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                    </form>
                <?php } ?>

                <div class="row">
                    <div class="col-md-1">
                        <a href="<?= site_url('hr/payroll') ?>" class="btn btn-light"><i class="fa fa-arrow-left"></i> <small>Back</small></a> &emsp;
                    </div>

                    <div class="col-md-3">
                        Cabang
                        <h6><?= $branch_detail['branch_code']." / ".$branch_detail['branch_name'] ?></h6>
                    </div>
                    

                    <div class="col-md-2">
                        Tanggal Generate
                        <h6><?= !empty($payroll) ? indonesian_date($payroll['created_at'], true) : ' <h6 class="text-danger"><i class="fa fa-times-circle"></i> Belum Digenerate</h6>' ?></h6>
                    </div>

                    <div class="col-md-2">
                        <?php if($role != 'employee' && $role != 'supervisor') { ?>
                            Total Karyawan
                            <h6><?= !empty($payroll) ? $payroll['total_employee'] : '-' ?></h6>
                        <?php } ?>
                    </div>
                    
                    <?php 
                        $get = '';
                        if($role == 'admin'){
                            $get = $this->input->get('branch_id') ? '?branch_id='.(int)$this->input->get('branch_id') : '';
                        }
                    ?>

                    <div class="col-md-4 text-center">
                        <div class="row">
                            <div class="col-md-3">
                                <?php if($role != 'employee' && $role != 'supervisor'){ ?>
                                    <?php $prev = date('m', strtotime('-1 month', strtotime($year.'-'.$month.'-16')))."/".date('Y', strtotime('-1 month', strtotime($year.'-'.$month.'-16'))); ?>
                                    <a href="<?= site_url('hr/payroll/'.$prev.$get) ?>" class="btn btn-light"><i class="fa fa-chevron-left"></i></a>
                                <?php } ?>
                                
                            </div>
                            <div class="col-md-6">
                                Waktu
                                <h5><?= get_monthname($month)." ".$year ?></h5>
                            </div>
                            <div class="col-md-3">
                                <?php if($role != 'employee' && $role != 'supervisor'){ ?>
                                    <?php $next = date('m', strtotime('+1 month', strtotime($year.'-'.$month.'-16')))."/".date('Y', strtotime('+1 month', strtotime($year.'-'.$month.'-16'))); ?>
                                    <a href="<?= site_url('hr/payroll/'.$next.$get) ?>" class="btn btn-light"><i class="fa fa-chevron-right"></i></a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if($role != 'employee' && $role != 'supervisor'){ ?>
                    <div class="row">
                        <div class="col-md-1"></div>

                        <div class="col-md-2">
                            Total Fee
                            <h6><?= !empty($payroll) ? format_rp($payroll['total_salary_thp']) : '-' ?></h6>
                        </div>

                        <div class="col-md-3 text-center">
                            <?php if(!empty($payroll)){ 
                                    if($payroll['is_final'] == '1'){
                            ?>
                                    <div class="btn-group" role="group">
                                        <button id="btnGroupVerticalDrop1" type="button" class="btn btn btn-primary dropdown-toggle"
                                            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="fa fa-print"></i> Cetak <i class="fa fa-chevron-down"></i>
                                        </button> &nbsp;
                                        <div class="dropdown-menu" aria-labelledby="btnGroupVerticalDrop1">
                                            <a class="dropdown-item" target="_blank" href="<?= site_url('hr/payroll/'.$payroll['month'].'/'.$payroll['year'].'/print?branch_id='.$branch_id) ?>"><i class="fa fa-file"></i> Rangkuman Fee</a>
                                            <a id="btnShowPayrollSlip" class="dropdown-item" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalExportPayrollSlip"><i class="fa fa-file-pdf"></i> Export Slip Pembagian Fee</a>
                                        </div>
                                    </div>

                                    <a href="<?= site_url('hr/payroll/'.$payroll['month'].'/'.$payroll['year'].'/excel?branch_id='.$branch_id) ?>" class="btn btn-success"><i class="fa fa-file-excel"></i> Export Excel</a>

                            <?php   }else{ ?>
                                        <span class="badge bg-warning">TAHAP LOCK</span>
                            <?php
                                    }
                            ?>
                                
                            <?php } ?>
                        </div>

                    </div>

                    <div class="row mt-2">
                        <div class="col-md-3"></div>
                        <div class="col-md-2">
                            <small><em class="fa fa-map-marker-alt"></em> By Penempatan</small>
                            <select class="select-plugin" style="width: 100%" id="locationFilter">
                                <option value="all">Semua</option>
                                <?php foreach ($location_list as $loc) { ?>
                                    <option value="<?= htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <small><em class="fa fa-id-badge"></em> By Posisi</small>
                            <select class="select-plugin" style="width: 100%" id="positionFilter">
                                <option value="all">Semua</option>
                                <?php foreach ($position_list as $pos) { ?>
                                    <option value="<?= htmlspecialchars($pos, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($pos, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <small><em class="fa fa-users"></em> By CV</small>
                            <select class="select-plugin" style="width: 100%" id="subdivision">
                                <option value="all">Semua</option>
                                <?php foreach ($subdivision as $row) { ?>
                                    <option value="<?= $row['subdivision_name'] ?>"><?= $row['subdivision_name'] ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <small><em class="fa fa-search"></em> Pencarian</small>
                            <input id="autocomplete" type="text" class="form-control" placeholder="Cari Karyawan...">
                        </div>
                    </div>

                <?php } ?>

                <div class="row mt-4">
                    <div class="col-md-12">
                        <?= $payrollTable ?>
                    </div>

                    <div class="col-md-12 mt-3 text-end">
                        <?php if(empty($payroll) && ($role == 'admin' || $role == 'admin-branch')){ ?>
                                <button class="btn btn-outline-primary me-2" id="btnRecalcAuto" title="Hitung ulang Komisi Disiplin/Transport/Beras/Soskes/Sholat dari data presensi terkini ke kolom Komisi Lainnya"><i class="fa fa-calculator"></i> Hitung Komisi Otomatis</button>
                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fa fa-file-excel"></i> Import Komisi/Denda
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="<?= site_url('payroll_template_component/'.$month.'/'.$year.$get) ?>"><i class="fa fa-download"></i> Download Template</a>
                                        <a class="dropdown-item" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalImportComponent"><i class="fa fa-upload"></i> Upload File</a>
                                    </div>
                                </div>
                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fa fa-clock"></i> Import Lembur
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="<?= site_url('payroll_template_overtime/'.$month.'/'.$year.$get) ?>"><i class="fa fa-download"></i> Download Template</a>
                                        <a class="dropdown-item" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalImportOvertime"><i class="fa fa-upload"></i> Upload File</a>
                                    </div>
                                </div>
                                <button class="btn btn-warning" id="btnGenerate"><i class="fa fa-lock"></i> Lock Fee</button>
                        <?php }else if(!empty($payroll) && ($role == 'admin' || $role == 'admin-branch')){
                                    if($payroll['is_final'] == '0'){ ?>
                                        <button class="btn btn-outline-primary" id="btnRecalcAuto" title="Hitung ulang Komisi Disiplin/Transport/Beras/Soskes/Sholat dari data presensi terkini"><i class="fa fa-calculator"></i> Hitung Ulang Komisi Otomatis</button> &emsp;
                                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalRollback"><i class="fa fa-refresh"></i> Rollback Fee Ke Tahap Awal</button> &emsp;
                                        <button class="btn btn-success" id="btnSaveGaji"><i class="fa fa-check-circle"></i> Simpan Fee</button>

                        <?php       }else{ ?>
                                        <button class="btn btn-outline-danger" id="btnRollbackLock"><i class="fa fa-refresh"></i> Rollback Fee Ke Tahap Lock</button>
                        <?php       }?>
                                
                        <?php } ?>
                    </div>
                </div>

            </div>
        </div>
        
    </div>
</div>

<?php if(empty($payroll) && ($role == 'admin' || $role == 'admin-branch')){ ?>
<form id="formImportComponent" method="POST" enctype="multipart/form-data" action="<?= site_url('payroll_import_component/'.$month.'/'.$year.$get) ?>">
    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
    <div class="modal fade" id="modalImportComponent" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title" style="color:#fff">Import Komisi dan Potongan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls" required>
                    <small class="text-muted d-block mt-2">Gunakan template dari tombol Download Template. Nilai pada file akan mengganti komisi dan potongan karyawan yang ada di file untuk periode ini.</small>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" id="btnImportComponent"><i class="fa fa-upload"></i> Import</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</form>

<form id="formImportOvertime" method="POST" enctype="multipart/form-data" action="<?= site_url('payroll_import_overtime/'.$month.'/'.$year.$get) ?>">
    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
    <div class="modal fade" id="modalImportOvertime" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <h5 class="modal-title" style="color:#fff">Import Lembur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls" required>
                    <small class="text-muted d-block mt-2">Isi ID Fingerprint, Tanggal Lembur, dan Jam Lembur. Data akan masuk sebagai lembur approved dan ikut dihitung saat Lock Fee.</small>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-info" id="btnImportOvertime"><i class="fa fa-upload"></i> Import</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</form>
<?php } ?>

<?php if(!empty($payroll)){ ?>
<form id="formRollback">
    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
    <input type="hidden" name="payroll_id" value="<?= $payroll['id'] ?>">
    <div class="modal fade" id="modalRollback" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">ROLLBACK FEE TAHAP AWAL</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <img src="<?= base_url('assets/images/icon/megaphone.png') ?>" style="width: 50px">
                        </div>
                        <div class="col-md-9">
                            <h6><b>Rollback Tahap Awal / Batalkan Pembagian Fee Periode Ini ?</b></h6>
                            <div id="modal-info-msg">Rollback Tahap Awal merupakan pembatalan pembagian fee bulan ini yang mengakibatkan data fee yang sudah diinput saat ini hilang. <br><br><b class="text-danger">Pastikan anda mempunyai Copy / PDF / Salinan dari hasil pembagian fee saat ini !</b></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-danger" id="btnRollback">Batalkan Pembagian Fee</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</form>

<div class="modal fade" id="modalExportPayrollSlip" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">EXPORT PDF SLIP PEMBAGIAN FEE KARYAWAN</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <table class="table">
                            <tr>
                                <th>Periode</th>
                                <td><?= get_monthname($month)." ".$year ?></td>
                            </tr>
                            <tr>
                                <th>Cabang</th>
                                <td><?= $branch_detail['branch_code']." / ".$branch_detail['branch_name'] ?></td>
                            </tr>
                        </table>
                    </div>

                    <div class="col-md-12">
                        <table class="table datatable">
                            <tr class="bg-light">
                                <th></th>
                                <th colspan="2">
                                    <input type="text" id="searchEmployee" class="form-control" placeholder="Cari nama atau posisi...">
                                </th>
                                <th style="width:35%" class="text-center">
                                    <button class="btn btn-outline-primary" id="btnSelectAllEmployee" disabled><i class="fa fa-check"></i> Pilih Semua</button>
                                </th>
                            </tr>
                            <tbody id="selectEmployeeExportPayrollBody">
                                <tr>
                                    <td colspan="4" class="text-center">
                                        <i class="fa fa-spinner fa-spin"></i> Mengambil data...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" disabled id="btnExportPayrollSlip"> Export Sekarang</button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<script type="text/javascript">
    <?php if(!empty($payroll)){ 
        if($payroll['is_final'] == '0'){
    ?>
            $(document).on('submit', '#formRollback', function(e){
                e.preventDefault();
                var btn = $('#btnRollback');

                $.ajax({
                    url      : "<?= site_url('payroll_rollback') ?>",
                    method   : "POST",
                    dataType : "json",
                    data     : $('#formRollback').serialize(),
                    beforeSend : function(){
                        btn.html('<i class="fa fa-spinner fa-spin"></i> Proses...').attr('disabled', 'disabled');
                    },
                    success : function(res){
                        if(res.status){
                            window.location.reload();

                        }else{
                            alert(res.message);
                            btn.html('Batalkan Pembagian Fee').removeAttr('disabled');
                        }
                    }
                })

                return false;
            });

            $(document).on('click', '#btnSaveGaji', function(){
                var r = confirm('Apakah anda yakin menyimpan fee periode ini ?')
                if(r){
                    var btn = $('#btnSaveGaji')
                    $.ajax({
                        url      : "<?= site_url('save_payroll/'.$payroll['id']) ?>",
                        method   : "POST",
                        dataType : "json",
                        data     : {
                            myToken : "<?php echo $this->security->get_csrf_hash() ?>"
                        },
                        beforeSend : function(){
                            btn.html('<i class="fa fa-spinner fa-spin"></i> Proses...').attr('disabled', 'disabled');
                        },
                        success : function(res){
                            if(res.status){
                                window.location.reload();

                            }else{
                                alert(res.message);
                                btn.html('<i class="fa fa-check-circle"></i> Simpan Fee').removeAttr('disabled');
                            }
                        }
                    })

                }
            })

    <?php }else{ ?>

            $(document).on('click', '#btnRollbackLock', function(){
                var r = confirm('Apakah anda yakin mengembalikan proses fee ke tahap lock ?')
                if(r){
                    var btn = $('#btnRollbackLock')
                    $.ajax({
                        url      : "<?= site_url('payroll_rollback_to_lock/'.$payroll['id']) ?>",
                        method   : "POST",
                        dataType : "json",
                        data     : {
                            myToken : "<?php echo $this->security->get_csrf_hash() ?>"
                        },
                        beforeSend : function(){
                            btn.html('<i class="fa fa-spinner fa-spin"></i> Proses...').attr('disabled', 'disabled');
                        },
                        success : function(res){
                            if(res.status){
                                window.location.reload();

                            }else{
                                alert(res.message);
                            }
                        },
                        complete : function(){
                            btn.html('<i class="fa fa-check-circle"></i> Simpan Fee').removeAttr('disabled');
                        }
                    })

                }
            })

            $(document).on('click', '#btnShowPayrollSlip', function(){
                $.ajax({
                    url      : "<?= site_url('get_employee_with_payroll/'.$payroll['id']) ?>",
                    method   : "POST",
                    dataType : "json",
                    data     : {
                        myToken : "<?php echo $this->security->get_csrf_hash() ?>"
                    },
                    beforeSend : function(){
                        $('#btnExportPayrollSlip').attr('disabled')
                        $('#btnSelectAllEmployee').attr('disabled')
                        $('#selectEmployeeExportPayrollBody').html('<tr><td colspan="4" class="text-center"><i class="fa fa-spinner fa-spin"></i> Memanggil data...</td></tr>')
                    },
                    success : function(res){
                        if(res.status){
                            var data = ''
                            var n = 0 
                            $.each(res.data, function(index, val){ n++
                                data += "<tr>"
                                data += "<td class='text-center'>"+n+"</td>"
                                data += "<td>"+val.first_name+"</td>"
                                data += "<td>"+val.position_name + "<br><small class='text-muted'>"+val.subdivision_name+"</small></td>"
                                data += "<td class='text-center'><input type='checkbox' class='employee_select' name='employee_id[]' value='"+val.user_id+"'></td>"
                                data += "</tr>"
                            })
                            $('#selectEmployeeExportPayrollBody').html(data)
                            $('#btnExportPayrollSlip').removeAttr('disabled')
                            $('#btnSelectAllEmployee').removeAttr('disabled')

                        }else{
                            alert(res.message);
                        }
                    },
                    complete : function(){
                       
                    }
                })
            })

            var selectAll = false
            $(document).on('click', '#btnSelectAllEmployee', function(){
                var btn = $('#btnSelectAllEmployee')

                if(selectAll){
                    selectAll = false
                    $('.employee_select').prop('checked', false);
                    btn.removeClass('btn-primary').addClass('btn-outline-primary').html('<i class="fa fa-check"></i> Pilih Semua')
                }else{
                    selectAll = true
                    $('.employee_select').prop('checked', true);
                    btn.removeClass('btn-outline-primary').addClass('btn-primary').html('<i class="fa fa-check-circle"></i> Pilih Semua')
                }
            })

            $(document).on('click', '#btnExportPayrollSlip', function(){
         
                var btn = $('#btnExportPayrollSlip')

                var employeeIDs = []
                $('.employee_select').each(function(i, obj) {
                    if($(this).prop('checked')){
                        employeeIDs.push($(this).val())
                    }
                });

                if(employeeIDs.length == 0){
                    alert('Silahkan pilih minimal 1 karyawan')
                    return
                }

                $.ajax({
                    url      : "<?= site_url('payroll_multiple_export_pdf/'.$payroll['id'].'?branch_id='.$branch_detail['id']) ?>",
                    method   : "POST",
                    dataType : "json",
                    data     : {
                        myToken : "<?php echo $this->security->get_csrf_hash() ?>",
                        employee_ids : employeeIDs
                    },
                    beforeSend : function(){
                        btn.html('<i class="fa fa-spinner fa-spin"></i> Proses...').attr('disabled', 'disabled');
                    },
                    success : function(res){
                        if(res.status){
                            window.location.href = res.data.file_url

                        }else{
                            alert(res.message);
                        }
                    },
                    complete : function(){
                        btn.html('Export Sekarang').removeAttr('disabled');
                    }
                })

            })

    <?php } ?>

    <?php }else{ ?>
        // Password gate lock gaji — sama seperti gate sync/hapus presensi.
        var lockGatePass = '';
        function promptLockPassword(onOk){
            Swal.fire({
                title: 'Password Lock Fee',
                input: 'password',
                inputLabel: 'Masukkan password untuk melanjutkan',
                inputPlaceholder: 'Password',
                inputAttributes: { autocomplete: 'off', autocapitalize: 'off' },
                showCancelButton: true,
                confirmButtonText: 'Lanjutkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#f1b44c'
            }).then(function(r){
                if(r.isConfirmed){ onOk(r.value || ''); }
            });
        }

        $(document).on('click', '#btnGenerate', function(){
            r = confirm('Apakah anda yakin melakukan LOCK pembagian fee bulan ini ?');
            if(r){
                promptLockPassword(function(pass){
                    lockGatePass = pass;
                    $('#formGenerate').submit();
                });
            }
        });

        $(document).on('submit', '#formGenerate', function(e){
            e.preventDefault();
            var btn = $('#btnGenerate');

            $.ajax({
                url      : "<?= site_url('generate_payroll/'.$month.'/'.$year) ?>",
                method   : "POST",
                dataType : "json",
                data     : $('#formGenerate').serialize() + '&sync_gate_password=' + encodeURIComponent(lockGatePass),
                timeout  : 180000,
                beforeSend : function(){
                    btn.html('<i class="fa fa-spinner fa-spin"></i> Proses...').attr('disabled', 'disabled');
                },
                error : function(jqXHR, textStatus){
                    var msg = textStatus === 'timeout'
                        ? 'Proses terlalu lama (lebih dari 3 menit) dan dihentikan browser. Muat ulang halaman untuk cek apakah lock sudah tersimpan sebelum coba lagi.'
                        : 'Terjadi kesalahan jaringan/server (' + (jqXHR.status || '?') + '). Muat ulang halaman untuk cek status sebelum coba lagi.';
                    alert(msg);
                    btn.html('<i class="fa fa-check-circle"></i> Generate Fee').removeAttr('disabled');
                },
                success : function(res){
                    if(res.need_password){
                        btn.html('<i class="fa fa-lock"></i> Lock Fee').removeAttr('disabled');
                        Swal.fire({ icon:'error', title:'Password salah', text: res.message }).then(function(){
                            promptLockPassword(function(pass){
                                lockGatePass = pass;
                                $('#formGenerate').submit();
                            });
                        });
                        return;
                    }
                    if(res.status){
                        window.location.reload();

                    }else{
                        alert(res.message);
                        btn.html('<i class="fa fa-check-circle"></i> Generate Fee').removeAttr('disabled');
                    }
                }
            })

            return false;
        });
        
    <?php } ?>

    $(document).ready(function(){
        if(typeof $.fn.select2 === 'function'){
            $('#locationFilter, #positionFilter').trigger('change');
        }
    });

    function runPayrollFilter(){
        EmployeeFilter($('#autocomplete').val(), $('#subdivision').val(), $('#positionFilter').val(), $('#locationFilter').val());
    }

    $(document).on('keyup', '#autocomplete', function(){ runPayrollFilter(); });
    $(document).on('change', '#subdivision',    function(){ runPayrollFilter(); });
    $(document).on('change', '#positionFilter', function(){ runPayrollFilter(); });
    $(document).on('change', '#locationFilter', function(){ runPayrollFilter(); });

    $(document).on('keyup', '#searchEmployee', function(){
        var keyword = $(this).val();
        var subdivision = $(this).val();
        EmployeeFilterExport(keyword, 'all'); 
    });

    function EmployeeFilter(keyword, subdivision, position, location){
        position = position || 'all';
        location = location || 'all';
        $('#listPayroll tr').each(function(){
            var searchKeyword = $(this).text().search(new RegExp(keyword, "i")) >= 0;

            var rowSubdiv = $(this).attr('data-subdivision');
            var rowPos    = $(this).attr('data-position');
            var rowLoc    = $(this).attr('data-location');

            // Rows without data-subdivision are grand-total rows — always show
            var passSubdiv = (rowSubdiv === undefined) || (subdivision === 'all') || (rowSubdiv === subdivision);
            // Rows without data-position are subdivision-header or total rows — skip position/location check
            var isEmployeeRow = (rowPos !== undefined);
            var passPos = !isEmployeeRow || (position === 'all') || (rowPos === position);
            var passLoc = !isEmployeeRow || (location === 'all') || (rowLoc === location);

            if(searchKeyword && passSubdiv && passPos && passLoc){
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }

    function EmployeeFilterExport(keyword, subdivision){
        $('#selectEmployeeExportPayrollBody tr').each(function(){
            var count = 0;
            var searchKeyword = $(this).text().search(new RegExp(keyword, "i"));
            var searchSubdivision = false;

            if($(this).data('subdivision') == subdivision || subdivision == 'all'){
                searchSubdivision = true;
            }

            if(searchKeyword < 0 || !searchSubdivision) {
              $(this).hide(); 

            } else {
              $(this).show();
              count++;
            }
        });
    }
</script>

<?php if($role == 'admin' || $role == 'admin-branch'){ ?>
<script type="text/javascript">
    // Handler "Hitung Komisi Otomatis" — bisa dijalankan PRE-generate (payroll belum ada)
    // maupun POST-generate saat TAHAP LOCK. Tombolnya disisipkan di kedua state.
    $(document).on('click', '#btnRecalcAuto', function(){
        var hasPayroll = <?= !empty($payroll) ? 'true' : 'false' ?>;
        var msg = hasPayroll
            ? 'Hitung ulang 5 komisi otomatis (Disiplin/Transport/Beras/Soskes/Sholat) berdasarkan data presensi terkini?\n\nNilai auto akan menimpa entri manual HR pada 5 item ini; THP per karyawan akan diperbarui.'
            : 'Hitung otomatis 5 komisi (Disiplin/Transport/Beras/Soskes/Sholat) dari data presensi terkini? Nilai akan tertulis ke kolom Komisi Lainnya untuk semua karyawan periode ini.';
        if(!confirm(msg)) return;
        var btn = $('#btnRecalcAuto');
        var label = btn.html();
        $.ajax({
            url      : "<?= site_url('recalc_auto_insentif/'.$branch_detail['id'].'/'.$month.'/'.$year) ?>",
            method   : "POST",
            dataType : "json",
            timeout  : 600000, // 10 menit — hitung 100+ karyawan bisa lama
            data     : { myToken : "<?php echo $this->security->get_csrf_hash() ?>" },
            beforeSend : function(){
                btn.html('<i class="fa fa-spinner fa-spin"></i> Proses... (mohon tunggu, bisa beberapa menit)').attr('disabled', 'disabled');
            },
            success : function(res){
                if(res.status){
                    alert(res.message || ('Berhasil. '+res.updated+' baris insentif diperbarui.'));
                    window.location.reload();
                }else{
                    alert(res.message || 'Gagal hitung ulang.');
                    btn.html(label).removeAttr('disabled');
                }
            },
            error : function(){
                alert('Gagal menghubungi server.');
                btn.html(label).removeAttr('disabled');
            }
        });
    });
</script>
<?php } ?>

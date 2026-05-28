<?php $role = $this->ion_auth->get_users_groups()->row()->name; ?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="dripicons-experiments"></i> Detail Pengajuan Lembur</h4>

            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Lembur</a></li>
                    <li class="breadcrumb-item"><a href="<?= site_url('hr/overtime/acc') ?>">Daftar Pengajuan</a></li>
                    <li class="breadcrumb-item active"><a href="javascript:void(0)">Konfirmasi</a></li>
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
                <h6 class="card-title">Detail</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <a href="<?= site_url('hr/overtime/acc') ?>" class="btn btn-light"><i class="fa fa-arrow-left"></i> Kembali</a><br><br>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title">Detail</h6>
                            </div>
                            <div class="card-body">
                                <table class="table">
                                    <tr>
                                        <td><i class="dripicons-tags"></i> Karyawan<br><b><?= $overtime['first_name'] ?><br><small class="text-muted">Kode : <?= $overtime['employee_code'] ?></small></b></td>

                                        <td><i class="dripicons-briefcase"></i> Jabatan<br><b><?= $overtime['position_name'] ?></b></td>
                                    </tr>

                                    <tr>
                                        <td><i class="dripicons-clock"></i> Lama Jam Lembur<br><b><?= $overtime['overtime_hour'] ?> Jam</b></td>
                                        <td><i class="dripicons-store"></i> Cabang<br><b><?= $overtime['branch_code']." / ".$overtime['branch_name'] ?></b></td>
                                    </tr>

                                    <tr>
                                        <td><i class="dripicons-clock"></i> Tanggal Lembur<br><b><?= indonesian_date($overtime['overtime_date']) ?></b></td>
                                        <td><i class="dripicons-clock"></i> Waktu Pengajuan<br><b><?= indonesian_date($overtime['created_at'], true) ?></b></td>
                                        
                                    </tr>
                                    
                                    <tr>
                                        <td><i class="dripicons-ticket"></i> Status<br>
                                          <?= transaction_status($overtime['overtime_status']); ?>
                                        </td>

                                        <td><i class="dripicons-clock"></i> Waktu Konfirmasi<br><b> <?= $overtime['confirm_at'] != '' ? indonesian_date($overtime['confirm_at'], true) : '-' ?></b></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-5">
                                <a href="javascript:void(0)" data-bs-target="#modalPreview" data-bs-toggle="modal" class="btn btn-primary"><i class="fa fa-image"></i> Lihat Bukti Lembur</a>
                            </div>

                            <div class="col-md-7 text-end">
                                <?php if($overtime['overtime_status'] == 'pending'){ ?>
                                    <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalApprove" class="btn btn-success text-end bodyStatus"><i class="fa fa-check-circle"></i> Setujui</a> &nbsp;
                                    <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalDeny" class="btn btn-outline-danger text-end bodyStatus"><i class="fa fa-ban"></i> Tolak</a>
                                   
                                <?php }else if($overtime['overtime_status'] == 'approve'){ ?>
                                    <a href="javascript:void(0)" id="btnCancel" class="btn btn-outline-danger"><i class="fa fa-ban"></i> Batalkan Pengajuan Lembur</a>
                                <?php } ?>
                            </div>
                        </div>

                        <?php if($overtime['overtime_status'] != 'pending'){ ?>
                            <div class="row">
                                <div class="col-md-12">
                                    <br>
                                    <?php 

                                        if($overtime['overtime_status'] == 'deny'){
                                            $status = 'danger';
                                            $title  = '<b><i class="fa fa-times-circle"></i> Pengajuan ditolak</b>';
                                            $message = 'Alasan Penolakan : <br>'.nl2br($overtime['reject_reason']);

                                        }else if($overtime['overtime_status'] == 'approve'){
                                            $status = 'success';
                                            $title  = '<b><i class="fa fa-check-circle"></i> Pengajuan diterima</b>';
                                            $message = 'Pengajuan lembur berhasil diterima';

                                        }else{
                                            $status = 'warning';
                                            $title  = '<b><i class="fa fa-ban"></i> Pengajuan dibatalkan</b>';
                                            $message = 'Pengajuan lembur dibatalkan oleh Admin, silahkan kontak admin anda untuk informasi lebih lanjut';

                                        }

                                        echo show_alert_border($title, $message, $status);
                                    ?>
                                </div>
                            </div>
                        <?php } ?>
                         
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="modalPreview" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mt-0 trans_sub" id="myModalLabel">Bukti Lembur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                
                <div class="row">
                  <div class="col-md-12">
                    <center id="proof_transaction">
                      <img class="img-fluid" src="<?= base_url('assets/images/hr/overtime/'.$overtime['overtime_proof']) ?>">
                    </center>
                  </div>
                </div>
                    
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<?php if($overtime['overtime_status'] == 'pending'){ ?>

<form id="formDeny">
<input type="hidden" name="status" value="deny">
<div class="modal fade" id="modalDeny" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="staticBackdropLabel" aria-hidden="true">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" name="transaction_id" value="<?= $overtime['id'] ?>">

    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">Tolak Pengajuan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-3">
                        <img src="<?= base_url('assets/images/icon/question.png') ?>" class="img-fluid">
                    </div>
                    <div class="col-md-9">
                        <h6>Apakah anda yakin menolak pengajuan ini ?</h6>
                        
                        <label><b>Alasan Penolakan</b></label>
                        <textarea class="form-control" name="reject_reason" required="" placeholder="Ketik alasan penolakan disini"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                <button class="btn btn-danger" id="btnDeny">Tolak</button>
            </div>
        </div>
    </div>
</div>
</form>


<form id="formApprove">
<input type="hidden" name="status" value="approve">
<div class="modal fade" id="modalApprove" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="staticBackdropLabel" aria-hidden="true">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" name="transaction_id" value="<?= $overtime['id'] ?>">

    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success">
                <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">Terima Pengajuan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-3">
                        <img src="<?= base_url('assets/images/icon/success.png') ?>" class="img-fluid">
                    </div>
                    <div class="col-md-9">
                        <h6>Apakah anda yakin menerima pengajuan ini ?</h6>
                        
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                <button class="btn btn-success" id="btnApprove">Terima</button>
            </div>
        </div>
    </div>
</div>
</form>

<?php } ?>

<script type="text/javascript">
<?php if($overtime['overtime_status'] == 'pending'){ ?>
    $(document).on('submit', '#formDeny', function(e){
        e.preventDefault();
        var status  = true;
        var btn     = $('#btnDeny');
        var message = "";

        var formData = new FormData(this);

        $.ajax({
            url         : "<?= site_url('change_status_overtime/'.$overtime['id']) ?>",
            dataType    : "json",
            method      : "POST",
            data        : formData,
            processData : false,
            contentType : false,
            beforeSend  : function(){
                btn.html(show_loading()).attr('disabled', 'disabled');
            },
            success : function(res){
                $('#modalDeny').modal('hide');
                if(res.status){
                    window.location.reload();
                }else{
                    alert(res.message);
                }
            },
            complete : function(){
                btn.html('Tolak').removeAttr('disabled');
            }
        });

        return false;
    });

    $(document).on('submit', '#formApprove', function(e){
        e.preventDefault();
        var status  = true;
        var btn     = $('#btnApprove');
        var message = "";

        var formData = new FormData(this);

        $.ajax({
            url         : "<?= site_url('change_status_overtime/'.$overtime['id']) ?>",
            dataType    : "json",
            method      : "POST",
            data        : formData,
            processData : false,
            contentType : false,
            beforeSend  : function(){
                btn.html(show_loading()).attr('disabled', 'disabled');
            },
            success : function(res){
                $('#modalApprove').modal('hide');
                if(res.status){
                    window.location.reload();

                }else{
                    alert(res.message);
                }
            },
            complete : function(){
                btn.html('Terima').removeAttr('disabled');
            }
        });

        return false;
    });
<?php }?>

<?php if($overtime['overtime_status'] == 'approve'){ ?>
    $(document).on('click', '#btnCancel', function(){
        var c = confirm('Apakah anda yakin membatalkan pengajuan ini ?');
        var btn = $('#btnCancel');

        if(c){
            $.ajax({
                url         : "<?= site_url('cancel_status_overtime/'.$overtime['id']) ?>",
                dataType    : "json",
                method      : "POST",
                data        : {
                    myToken : "<?php echo $this->security->get_csrf_hash() ?>"
                },
                beforeSend  : function(){
                    btn.html('<i class="fa fa-spinner fa-spin"></i> Proses...').addClass('disabled')
                },
                success : function(res){
                    if(res.status){
                        window.location.reload();

                    }else{
                        alert(res.message);
                        btn.html('<i class="fa fa-ban"></i> Batalkan Pengajuan Lembur').removeClass('disabled');
                    }
                }
            });
        }
    });

<?php } ?>
</script>

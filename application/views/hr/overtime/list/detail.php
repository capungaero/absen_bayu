<?php $role = $this->ion_auth->get_users_groups()->row()->name; ?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="dripicons-experiments"></i> Detail Pengajuan Lembur</h4>

            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Lembur</a></li>
                    <li class="breadcrumb-item"><a href="<?= site_url('hr/overtime/list') ?>">Daftar</a></li>
                    <li class="breadcrumb-item active"><a href="javascript:void(0)">Detail</a></li>
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
                <h6 class="card-title">Detail Pegajuan</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <a href="<?= site_url('hr/overtime/list') ?>" class="btn btn-light"><i class="fa fa-arrow-left"></i> Kembali</a><br><br>
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
                        <a href="javascript:void(0)" data-bs-target="#modalPreview" data-bs-toggle="modal" class="btn btn-primary"><i class="fa fa-image"></i> Lihat Bukti Lembur</a>

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
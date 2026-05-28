<?php $role = $this->ion_auth->get_users_groups()->row()->name; ?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="dripicons-experiments"></i> Detail Pengajuan Izin</h4>

            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Izin</a></li>
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
                        <a href="<?= site_url('hr/leave/list') ?>" class="btn btn-light"><i class="fa fa-arrow-left"></i> Kembali</a><br><br>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title">Detail</h6>
                            </div>
                            <div class="card-body">
                                <table class="table">
                                    <tr>
                                        <td style="width: 33.3%"><i class="dripicons-tags"></i> Karyawan<br><b><?= $leave['first_name'] ?><br><small class="text-muted">Kode : <?= $leave['employee_code'] ?></small></b></td>

                                        <td style="width: 33.3%"><i class="dripicons-briefcase"></i> Jabatan<br><b><?= $leave['position_name'] ?></b></td>
                                        <td style="width: 33.3%">Default Potongan Kompensasi Izin<br><b><?= $leave['default_potongan'] ?>%</b></td>
                                    </tr>

                                    <?php 
                                        if($leave['leave_start'] == $leave['leave_end']){
                                          $date = $leave['leave_start'];
                                        }else{
                                          $date = date('d-m-Y', strtotime($leave['leave_start']))." s/d ".date('d-m-Y', strtotime($leave['leave_end']));
                                        }
                                    ?>

                                    <tr>
                                        <td><i class="dripicons-archive"></i> Jenis Izin<br><b><?= ucfirst($leave['leave_type']) ?></b></td>
                                        <td><i class="dripicons-store"></i> Cabang<br><b><?= $leave['branch_code']." / ".$leave['branch_name'] ?></b></td>
                                        <td>
                                            Pengajuan Keringanan Potongan<br>
                                            <b><?= $leave['request_potongan'] != '' ? $leave['request_potongan']."%" : "<span class='text-danger'><i class='fa fa-times-circle'></i> Tidak Ada</span>" ?></b>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td><i class="dripicons-clock"></i> Rentang Izin<br><b><?= $date."<br>( ".$leave['leave_range']." Hari )" ?></b></td>
                                        <td><i class="dripicons-clock"></i> Waktu Pengajuan<br><b><?= indonesian_date($leave['created_at'], true) ?></b></td>
                                        <td>Potongan Yang Diterima<br>
                                            <b><?= $leave['acc_potongan'] != '' ? $leave['acc_potongan']."%" : "<span class='text-danger'><i class='fa fa-times-circle'></i> Belum Diterima</span>" ?></b>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <td><i class="dripicons-ticket"></i> Status<br>
                                          <?= transaction_status($leave['leave_status']); ?>
                                        </td>

                                        <td><i class="dripicons-clock"></i> Waktu Konfirmasi<br><b> <?= $leave['confirm_at'] != '' ? indonesian_date($leave['confirm_at'], true) : '-' ?></b></td>

                                        <td><i class="dripicons-calendar"></i> Jumlah Hari Potongan Yang Berlaku<br><b><?= $leave['jumlah_hari_potongan'] ?> Hari Kerja</b></td>
                                    </tr>

                                    <tr>
                                        <td colspan="3"><b><i class="dripicons-list"></i>Alasan Izin</b> <br><?= nl2br($leave['leave_reason']) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>


                    <div class="col-md-6">
                        <a href="javascript:void(0)" data-bs-target="#modalPreview" data-bs-toggle="modal" class="btn btn-primary"><i class="fa fa-image"></i> Lihat Bukti Izin</a>

                        <?php if($leave['leave_status'] != 'pending'){ ?>
                            <div class="row">
                                <div class="col-md-12">
                                    <br>
                                    <?php 

                                        if($leave['leave_status'] == 'deny'){
                                            $status = 'danger';
                                            $title  = '<b><i class="fa fa-times-circle"></i> Pengajuan ditolak</b>';
                                            $message = 'Alasan Penolakan : <br>'.nl2br($leave['reject_reason']);

                                        }else if($leave['leave_status'] == 'approve'){
                                            $status = 'success';
                                            $title  = '<b><i class="fa fa-check-circle"></i> Pengajuan diterima</b>';
                                            $message = 'Pengajuan Izin berhasil diterima';

                                        }else{
                                            $status = 'warning';
                                            $title  = '<b><i class="fa fa-ban"></i> Pengajuan dibatalkan</b>';
                                            $message = 'Pengajuan Izin dibatalkan oleh Admin, silahkan kontak admin anda untuk informasi lebih lanjut';

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
                <h5 class="modal-title mt-0 trans_sub" id="myModalLabel">Bukti Izin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                
                <div class="row">
                  <div class="col-md-12">
                    <center id="proof_transaction">
                        <?php if($leave['leave_proof'] == ''){ ?>
                            <h6 class="text-danger"><i class="fa fa-times-circle"></i> Berkas tidak ada</h6>
                        <?php }else{ ?>
                            <img class="img-fluid" src="<?= base_url('assets/images/hr/leave/'.$leave['leave_proof']) ?>">
                        <?php } ?>
                      
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
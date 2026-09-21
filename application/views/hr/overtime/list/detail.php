<?php
$role = $this->ion_auth->get_users_groups()->row()->name;
// overtime_proof kadang diisi TEKS ALASAN (bukan path foto) oleh jalur input
// otomatis (agen WA "Fany"), bukan cuma path gambar upload manual -- kalau
// dipaksa render sbg <img> hasilnya ikon gambar rusak. Deteksi dari ekstensi.
$overtime_proof_is_image = !empty($overtime['overtime_proof'])
    && preg_match('/\.(jpe?g|png|gif|pdf)$/i', $overtime['overtime_proof']);
?>
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
                                        <td><i class="dripicons-tags"></i> Mitra Kerja<br><b><?= $overtime['first_name'] ?><br><small class="text-muted">Kode : <?= $overtime['employee_code'] ?></small></b></td>

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

                                    <?php if(!empty($overtime['overtime_proof']) && !$overtime_proof_is_image){ ?>
                                    <tr>
                                        <td colspan="2"><i class="dripicons-article"></i> Alasan Lembur<br><b><?= htmlspecialchars($overtime['overtime_proof']) ?></b></td>
                                    </tr>
                                    <?php } ?>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <?php if($overtime_proof_is_image){ ?>
                        <a href="javascript:void(0)" data-bs-target="#modalPreview" data-bs-toggle="modal" class="btn btn-primary"><i class="fa fa-image"></i> Lihat Bukti Lembur</a>
                        <?php } else { ?>
                        <span class="text-muted"><i class="fa fa-image"></i> Tidak ada foto bukti (lembur ini dicatat dengan alasan teks, lihat kolom "Alasan Lembur")</span>
                        <?php } ?>
                        <?php if($role == 'admin'){ ?>
                        &nbsp;<a href="javascript:void(0)" id="btnDeleteOvertime" class="btn btn-outline-danger"><i class="fa fa-trash"></i> Hapus Pengajuan</a>
                        <?php } ?>

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

<?php if($role == 'admin'){ ?>
<script type="text/javascript">
$(document).on('click', '#btnDeleteOvertime', function(){
    if(!confirm('Hapus pengajuan lembur ini? Bonus lembur terkait akan ikut dibatalkan (kalau penggajian periode ini belum dibuat).')) return;
    var btn = $(this);
    $.ajax({
        url      : "<?= site_url('delete_overtime/'.$overtime['id']) ?>",
        dataType : "json",
        method   : "POST",
        data     : { <?php echo $this->security->get_csrf_token_name() ?>: "<?php echo $this->security->get_csrf_hash() ?>" },
        beforeSend: function(){ btn.html('<i class="fa fa-spinner fa-spin"></i> Menghapus...').addClass('disabled'); },
        success  : function(res){
            if(res.status){
                window.location.href = "<?= site_url('hr/overtime/list') ?>";
            }else{
                alert(res.message || 'Gagal menghapus.');
                btn.html('<i class="fa fa-trash"></i> Hapus Pengajuan').removeClass('disabled');
            }
        },
        error : function(){
            alert('Terjadi kesalahan, coba lagi nanti');
            btn.html('<i class="fa fa-trash"></i> Hapus Pengajuan').removeClass('disabled');
        }
    });
});
</script>
<?php } ?>
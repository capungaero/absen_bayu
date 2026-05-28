<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="dripicons-inbox"></i> Cabang</h4>

            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Master Data</a></li>
                    <li class="breadcrumb-item active"><a href="<?= site_url('master_data/branch') ?>">Cabang</a></li>
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
                <h6 class="card-title">Daftar Asset</h6>
            </div>
            <div class="card-body">
                <!--<a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAdd" class="btn btn-primary"><i class="dripicons-plus"></i> Tambah Cabang</a><br><br>-->

                <div class="table-responsive">
                    <?php $this->datatables->generate('tableContent'); ?>
                </div>
            </div>
        </div>
        
    </div>
</div>



<form id="formUpdate">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" name="id_branch" id="e_id">
<div id="modalEdit" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Ubah Cabang</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs nav-tabs-custom nav-justified" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#home1" role="tab">
                            <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                            <span class="d-none d-sm-block">Detail Cabang</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#profile1" role="tab">
                            <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                            <span class="d-none d-sm-block">Jadwal Sholat</span>
                        </a>
                    </li>
                </ul>

                <!-- Tab panes -->
                <div class="tab-content text-muted">
                    <div class="tab-pane active" id="home1" role="tabpanel">
                        <div class="row mt-3">                                                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label" for="formrow-email-input">Kode</label>
                                    <input type="text" required="" autocomplete="off" placeholder="Kode cabang" class="form-control" id="e_code" name="branch_code">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label" for="formrow-password-input">Nama</label>
                                    <input type="text" id="e_name" required="" autocomplete="off" placeholder="Nama cabang" class="form-control" name="branch_name">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label" for="formrow-password-input">Kota</label>
                                    <input type="text" id="e_city" required="" name="city" autocomplete="off" placeholder="Nama kota" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label" for="formrow-password-input">Kontak ( No. Telp )</label>
                                    <input type="text" id="e_phone" required="" autocomplete="off" placeholder="Nomor kontak cabang" class="form-control" name="branch_phone">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label" for="formrow-password-input">Alamat Lengkap</label>
                                    <input type="text" id="e_address" class="form-control" required="" placeholder="Ketik alamat lengkap disini..." name="address">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label" for="e_status">Status</label>
                                    <select class="form-control" id="e_status" name="is_active" required>
                                        <option value="1">Aktif</option>
                                        <option value="0">Nonaktif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="profile1" role="tabpanel">
                        <div class="row mt-3">
                            <div class="col-md-12">
                              <b><i class="dripicons-card"></i> DENDA <br></b>
                              <small class="text-muted">Denda yang berlaku pada jadwal sholat</small>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-md-4">
                                <div class="mb-3">
                                   <label>Nominal Pertama</label>
                                   <input type="text" name="pray_late_start_rate" class="form-control rupiah" id="pray_late_start_rate" placeholder="Rp. 0" autocomplete="off" required />
                                </div>
                              </div>

                              <div class="col-md-4">
                                <div class="mb-3">
                                   <label>Kelipatan Waktu</label>
                                   <input type="number" name="pray_late_multiple_count" class="form-control" placeholder="Menit" id="pray_late_multiple_count" autocomplete="off" required />
                                </div>
                              </div>

                              <div class="col-md-4">
                                <div class="mb-3">
                                   <label>Nominal Kelipatan</label>
                                   <input type="text" name="pray_late_multiple_rate" class="form-control rupiah" id="pray_late_multiple_rate" placeholder="Rp. 0" autocomplete="off" required />
                                </div>
                              </div>

                              <div class="col-md-4">
                                <div class="mb-3">
                                   <label>Nominal Maksimal</label>
                                   <input type="text" name="pray_late_fix_rate" id="pray_late_fix_rate" class="form-control rupiah" placeholder="Rp. 0" autocomplete="off" required />
                                </div>
                              </div>    
                        </div>

                        <div class="row mt-3">
                          <div class="col-md-12">
                              <b><i class="dripicons-clock"></i> JADWAL SHOLAT <br></b>
                              <small class="text-muted">Waktu mulai izin dan selesai izin, merupakan rentang waktu yang diperbolehkan untuk melakukan tapping pada mesin fingerprint</small>
                          </div>

                          <?php $param = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday']; ?>

                          <?php 
                          foreach ($param as $row) { $title = $row == 'friday' ? 'Jum\'at' : $row; ?>
                                    <div class="col-md-12 mt-2">
                                          <label><?= strtoupper($title) ?></label>
                                      </div>

                                      <div class="col-md-4">
                                        <div class="mb-3">
                                           <label>Waktu Sholat</label>
                                           <input type="time" name="<?= $row."_pray_time" ?>" id="<?= "e_".$row."_pray_time" ?>"  readonly="" class="timepicker" placeholder="Waktu Mulai Sholat" autocomplete="off" required />
                                        </div>
                                      </div>

                                      <div class="col-md-4">
                                        <div class="mb-3">
                                           <label>Mulai Izin</label>
                                           <input type="time" name="<?= $row."_pray_time_in" ?>" id="<?= "e_".$row."_pray_time_in" ?>"  readonly="" class="timepicker" placeholder="Waktu Mulai Sholat" autocomplete="off" required />
                                        </div>
                                      </div>

                                      <div class="col-md-4">
                                        <div class="mb-3">
                                           <label>Selesai Izin</label>
                                           <input type="time" name="<?= $row."_pray_time_out" ?>" id="<?= "e_".$row."_pray_time_out" ?>" readonly="" class="timepicker" placeholder="Waktu Mulai Sholat" autocomplete="off" required />
                                        </div>
                                      </div>

                                      <div class="col-md-12">
                                        <div class="mb-3">
                                           <label>Rentang Izin</label>
                                           <input type="number" id="<?= "e_".$row."_pray_time_range" ?>" name="<?= $row."_pray_time_range" ?>" class="form-control" placeholder="Menit" autocomplete="off" required />
                                           <small>*rentang waktu yang diperbolehkan untuk izin sholat</small>
                                        </div>
                                      </div>

                                      <hr>
                          <?php } ?>
                        </div>
                    </div>
                </div>
                        
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>
                <button id="btnUpdate" class="btn btn-warning waves-effect waves-light"><i class="fa fa-pencil"></i> Ubah</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
</form>


<form id="formDelete">
<div class="modal fade" id="modalDelete" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="staticBackdropLabel" aria-hidden="true">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" name="id_branch" id="delete-id">

    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">Hapus Cabang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-3">
                        <img src="<?= base_url('assets/images/icon/question.png') ?>" class="img-fluid">
                    </div>
                    <div class="col-md-9">
                        <h6>Apakah anda yakin menghapus data ini ?</h6>
                        Data yang telah dihapus tidak dapat dikembalikan lagi dan semua data yang berhubungan dengan cabang ini juga akan terhapus
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tidak</button>
                <button class="btn btn-danger" id="btnDelete">Ya, Hapus Data ini</button>
            </div>
        </div>
    </div>
</div>
</form>


<?php $this->datatables->jquery('tableContent'); ?>

<script type="text/javascript">

    $(document).on('submit', '#formUpdate', function(e){
        e.preventDefault();
        var status  = true;
        var btn     = $('#btnUpdate');
        var message = "";

        var formData = new FormData(this);

        $.ajax({
            url         : "<?= site_url('update_branch') ?>",
            dataType    : "json",
            method      : "POST",
            data        : formData,
            processData : false,
            contentType : false,
            beforeSend  : function(){
                btn.html(show_loading()).attr('disabled', 'disabled');
            },
            success : function(res){
                $('#modalEdit').modal('hide');
                if(res.status){
                    $('#formUpdate')[0].reset();
                    erTable_tableContent.ajax.reload(null, false);
                }

                var type = (res.status) ? 'success' : 'info';
                show_modal(type, res.message);
            },
            complete : function(){
                btn.html('<em class="icon ni ni-check"></em> Ubah').removeAttr('disabled');
            }
        });

        return false;
    });

    $(document).on('click', '.edit', function(){
        var a = $(this);
        var id   = a.attr('data-id');

        $('#e_name').val(a.attr('data-name'));
        $('#e_code').val(a.attr('data-code'));
        $('#e_city').val(a.attr('data-city'));
        $('#e_address').val(a.attr('data-address'));
        $('#e_phone').val(a.attr('data-phone'));
        $('#e_status').val(a.attr('data-status') || '1');
        $('#percentage').val(a.attr('data-percentage'));
        $('#max_overtime').val(a.attr('data-max-overtime'));
        $('#bpjs_health').val(a.attr('data-bpjs-health'));
        $('#bpjs_work').val(a.attr('data-bpjs-work'));
        $('#pray_late_start_rate').val(format_rp(a.attr('data-pray-late-start-rate')));
        $('#pray_late_fix_rate').val(format_rp(a.attr('data-pray-late-fix-rate')));
        $('#pray_late_multiple_rate').val(format_rp(a.attr('data-pray-late-multiple-rate')));
        $('#pray_late_multiple_count').val(a.attr('data-pray-late-multiple-count'));

        $('#e_id').val(id);
        $('#modalEdit').modal('show');

        var param = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];
        for (var i = param.length - 1; i >= 0; i--){
            $('#e_'+param[i]+'_pray_time').val(a.attr('data-'+param[i]+'_pray_time'));
            $('#e_'+param[i]+'_pray_time_in').val(a.attr('data-'+param[i]+'_pray_time_in'));
            $('#e_'+param[i]+'_pray_time_out').val(a.attr('data-'+param[i]+'_pray_time_out'));
            $('#e_'+param[i]+'_pray_time_range').val(a.attr('data-'+param[i]+'_pray_time_range'));
        }
    });

</script>

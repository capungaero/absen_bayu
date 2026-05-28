<?php $role = $this->ion_auth->get_users_groups()->row()->name; ?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="dripicons-experiments"></i> Shift</h4>

            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Jadwal Kerja</a></li>
                    <li class="breadcrumb-item active"><a href="<?= site_url('hr/shift') ?>">Shift</a></li>
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
                <h6 class="card-title">Daftar Shift</h6>
            </div>
            <div class="card-body">

              <form>
                <div class="row">
                  <?php if($role != 'supervisor'){ ?>
                    <div class="col-md-2 mb-2">
                      <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAdd" class="btn btn-primary"><i class="dripicons-plus"></i> Tambah Shift</a>
                    </div>
                  <?php } ?>

                  <?php if($role == 'admin'){ ?>
                      <div class="col-md-5"></div>
                      <div class="col-md-4">
                          <label>Pilih Cabang</label>
                          <select class="form-control" name="branch_id" id="branch">
                              <?php foreach ($branch as $row) { ?>
                                  <option <?= $branch_id == $row['id'] ? 'selected="selected"' : '' ?> value="<?= $row['id'] ?>"><?= $row['branch_code']." / ".$row['branch_name'] ?></option>
                              <?php } ?>
                          </select>
                      </div>
                      <div class="col-md-1">
                        <br>
                        <button class="btn btn-primary mt-2"><i class="fa fa-search"></i></button>
                      </div>
                  <?php } ?>
                </div>
              </form>

              <div class="row align-items-end mt-3">
                <?php if($role != 'supervisor'){ ?>
                  <div class="col-md-5 mb-2">
                    <button type="button" class="btn btn-outline-success bulk-status" data-active="1"><i class="fa fa-check-circle"></i> Aktifkan Semua</button>
                    <button type="button" class="btn btn-outline-dark bulk-status ms-1" data-active="0"><i class="fa fa-times-circle"></i> Nonaktifkan Semua</button>
                  </div>
                <?php } ?>
                <div class="<?= $role != 'supervisor' ? 'col-md-4 offset-md-3' : 'col-md-4 offset-md-8' ?> mb-2">
                  <label>Cari Shift</label>
                  <div class="input-group">
                    <input type="text" id="shiftSearch" class="form-control" placeholder="Cari kode / nama shift">
                    <button type="button" id="btnClearShiftSearch" class="btn btn-light"><i class="fa fa-times"></i></button>
                  </div>
                </div>
              </div>

                <br>

                <div class="table-responsive">
                    <?php $this->datatables->generate('tableContent'); ?>
                </div>
            </div>
        </div>
        
    </div>
</div>

<?php if($role != 'supervisor'){ ?>

<form id="formAdd">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" name="branch_id" value="<?= $branch_id ?>">
<div id="modalAdd" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Tambah Shift</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                           <label>Kode Shift</label>
                           <input type="text" name="shift_code" class="form-control" placeholder="Kode Shift" autocomplete="off"  required />
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="mb-3">
                           <label>Nama Shift</label>
                           <input type="text" name="shift_name"  class="form-control" placeholder="Nama Shift" autocomplete="off" required />
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                           <label>Status</label>
                           <select name="is_active" class="form-control" required>
                              <option value="1" selected>Aktif</option>
                              <option value="0">Nonaktif</option>
                           </select>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="row">
                  <div class="col-md-12">
                      <b><i class="dripicons-briefcase"></i> WAKTU KERJA</b><br>
                      <small class="text-muted">Merupakan rentang waktu kerja karyawan</small>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                       <label>Jam Mulai</label>
                       <input type="time" id="timepicker1" name="start_time" readonly="" class="timepicker" placeholder="Jam Mulai" autocomplete="off" required />
                       <small class="text-muted">*Klik icon jam untuk setting waktu</small>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="mb-3">
                       <label>Jam Selesai</label>
                       <input type="time" id="timepicker2" name="end_time" readonly="" class="timepicker" placeholder="Jam Selesai" autocomplete="off" required />
                    </div>
                  </div>
                </div>

                <hr>

                <div class="row">
                  <div class="col-md-12">
                      <b><i class="dripicons-clock"></i> WAKTU KEHADIRAN <br></b>
                      <small class="text-muted">Merupakan rentang waktu yang diperbolehkan untuk melakukan tapping / absen pada waktu masuk</small>
                  </div>

                  <div class="col-md-12 mt-2">
                      <label>MASUK</label>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Jam Mulai</label>
                       <input type="time" name="start_time_in" id="timepicker3"  readonly="" class="timepicker" placeholder="Jam Mulai" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Batas Keterlambatan</label>
                       <input type="time" name="start_time_late" id="timepicker7" readonly="" class="timepicker" placeholder="Jam Selesai" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Jam Selesai</label>
                       <input type="time" name="start_time_out" id="timepicker4" readonly="" class="timepicker" placeholder="Jam Selesai" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-12 mt-2">
                      <label>DENDA</label>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Nominal Pertama</label>
                       <input type="text" name="late_amount_start" class="form-control rupiah" placeholder="Rp. 0" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Kelipatan Waktu</label>
                       <input type="number" name="late_multiple_count_start" class="form-control" placeholder="Tiap Per - Menit" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Nominal Kelipatan</label>
                       <input type="text" name="late_amount_multiple_start" class="form-control rupiah" placeholder="Rp. 0" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Nominal Maksimal</label>
                       <input type="text" name="late_amount_max_start" class="form-control rupiah" placeholder="Rp. 0" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-12">
                      <b><i class="dripicons-clock"></i> WAKTU ISTIRAHAT <br></b>
                      <small class="text-muted">Merupakan rentang waktu yang diperbolehkan untuk melakukan tapping / absen pada waktu istirahat</small>
                  </div>

                  <div class="col-md-12 mt-2">
                      <label style="font-weight: 500">ISTIRAHAT</label>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Jam Mulai</label>
                       <input type="time" name="start_time_rest" id="timepicker10" readonly="" class="timepicker" placeholder="Jam Mulai" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Jam Selesai</label>
                       <input type="time" name="end_time_rest" id="timepicker11" readonly="" class="timepicker" placeholder="Jam Selesai" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Total Jam Istirahat</label>
                       <input type="number" name="rest_time_range" class="form-control" placeholder="Menit" autocomplete="off" required />
                    </div>
                  </div>


                  <div class="col-md-12 mt-1">
                      <label>DENDA</label>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Nominal Pertama</label>
                       <input type="text" name="late_amount_rest" class="form-control rupiah" placeholder="Rp. 0" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Kelipatan Waktu</label>
                       <input type="number" name="late_multiple_count_rest" class="form-control" placeholder="Tiap Per - Menit" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Nominal Kelipatan</label>
                       <input type="text" name="late_amount_multiple_rest" class="form-control rupiah" placeholder="Rp. 0" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Nominal Maksimal</label>
                       <input type="text" name="late_amount_max_rest" class="form-control rupiah" placeholder="Rp. 0" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-12">
                      <b><i class="dripicons-clock"></i> WAKTU PULANG <br></b>
                      <small class="text-muted">Merupakan rentang waktu yang diperbolehkan untuk melakukan tapping / absen pada waktu pulang atau keluar</small>
                  </div>

                  <div class="col-md-12 mt-2">
                      <label>KELUAR</label>
                  </div>

                  <div class="col-md-6">
                    <div class="mb-3">
                       <label>Jam Mulai</label>
                       <input type="time" name="end_time_in" id="timepicker5" readonly="" class="timepicker" placeholder="Jam Mulai" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="mb-3">
                       <label>Jam Selesai</label>
                       <input type="time" name="end_time_out" id="timepicker6" readonly="" class="timepicker" placeholder="Jam Selesai" autocomplete="off" required />
                    </div>
                  </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>
                <button id="btnSave" class="btn btn-success waves-effect waves-light"><i class="fa fa-check"></i> Simpan</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
</form>


<form id="formUpdate">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" name="id_shift" id="e_id">
<input type="hidden" name="branch_id" value="<?= $branch_id ?>">
<div id="modalEdit" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-edit"></i> Ubah Shift</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                           <label>Kode Shift</label>
                           <input type="text" name="shift_code" id="code" class="form-control" placeholder="Kode Shift" autocomplete="off"  required />
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="mb-3">
                           <label>Nama Shift</label>
                           <input type="text" name="shift_name" id="name" class="form-control" placeholder="Nama Shift" autocomplete="off" required />
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                           <label>Status</label>
                           <select name="is_active" id="is_active" class="form-control" required>
                              <option value="1">Aktif</option>
                              <option value="0">Nonaktif</option>
                           </select>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="row">
                  <div class="col-md-12">
                      <b><i class="dripicons-briefcase"></i> WAKTU KERJA</b><br>
                      <small class="text-muted">Merupakan rentang waktu kerja karyawan</small>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                       <label>Jam Mulai</label>
                       <input type="time" name="start_time" id="start_time" readonly="" class="timepicker" placeholder="Jam Mulai" autocomplete="off" required />
                       <small class="text-muted">*Klik icon jam untuk setting waktu</small>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="mb-3">
                       <label>Jam Selesai</label>
                       <input type="time" name="end_time" id="end_time" readonly="" class="timepicker" placeholder="Jam Selesai" autocomplete="off" required />
                    </div>
                  </div>
                </div>

                <hr>

                <div class="row">
                  <div class="col-md-12">
                      <b><i class="dripicons-clock"></i> WAKTU KEHADIRAN <br></b>
                      <small class="text-muted">Merupakan rentang waktu yang diperbolehkan untuk melakukan tapping / absen pada sistem</small>
                  </div>

                  <div class="col-md-12 mt-2">
                      <label>MASUK</label>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Jam Mulai</label>
                       <input type="time" name="start_time_in" id="start_time_in" readonly="" class="timepicker" placeholder="Jam Mulai" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Batas Keterlambatan</label>
                       <input type="time" id="start_time_late" name="start_time_late" readonly="" class="timepicker" placeholder="Jam Selesai" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Jam Selesai</label>
                       <input type="time" name="start_time_out" id="start_time_out" readonly="" class="timepicker" placeholder="Jam Selesai" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-12 mt-2">
                      <label>DENDA</label>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Nominal Pertama</label>
                       <input type="text" id="late_amount_start" name="late_amount_start" class="form-control rupiah" placeholder="Rp. 0" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Kelipatan Waktu</label>
                       <input type="number" id="late_multiple_count_start" name="late_multiple_count_start" class="form-control" placeholder="Tiap Per - Menit" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Nominal Kelipatan</label>
                       <input type="text" id="late_amount_multiple_start" name="late_amount_multiple_start" class="form-control rupiah" placeholder="Rp. 0" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Nominal Maksimal</label>
                       <input type="text" id="late_amount_max_start" name="late_amount_max_start" class="form-control rupiah" placeholder="Rp. 0" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-12">
                      <b><i class="dripicons-clock"></i> WAKTU ISTIRAHAT <br></b>
                      <small class="text-muted">Merupakan rentang waktu yang diperbolehkan untuk melakukan tapping / absen pada waktu istirahat</small>
                  </div>

                  <div class="col-md-12 mt-2">
                      <label style="font-weight: 500">ISTIRAHAT</label>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Jam Mulai</label>
                       <input type="time" name="start_time_rest" id="start_time_rest" readonly="" class="timepicker" placeholder="Jam Mulai" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Jam Selesai</label>
                       <input type="time" name="end_time_rest" id="end_time_rest" readonly="" class="timepicker" placeholder="Jam Selesai" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Total Jam Istirahat</label>
                       <input type="number" id="rest_time_range" name="rest_time_range" class="form-control" placeholder="Menit" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-12 mt-1">
                      <label>DENDA</label>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Nominal Pertama</label>
                       <input type="text" name="late_amount_rest" id="late_amount_rest" class="form-control rupiah" placeholder="Rp. 0" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Kelipatan Waktu</label>
                       <input type="number" name="late_multiple_count_rest" id="late_multiple_count_rest" class="form-control" placeholder="Tiap Per - Menit" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Nominal Kelipatan</label>
                       <input type="text" name="late_amount_multiple_rest" id="late_amount_multiple_rest" class="form-control rupiah" placeholder="Rp. 0" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-4">
                    <div class="mb-3">
                       <label>Nominal Maksimal</label>
                       <input type="text" id="late_amount_max_rest" name="late_amount_max_rest" class="form-control rupiah" placeholder="Rp. 0" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-12">
                      <b><i class="dripicons-clock"></i> WAKTU PULANG <br></b>
                      <small class="text-muted">Merupakan rentang waktu yang diperbolehkan untuk melakukan tapping / absen pada waktu pulang atau keluar</small>
                  </div>

                  <div class="col-md-12 mt-2">
                      <label>KELUAR</label>
                  </div>

                  <div class="col-md-6">
                    <div class="mb-3">
                       <label>Jam Mulai</label>
                       <input type="time" name="end_time_in" id="end_time_in" readonly="" class="timepicker" placeholder="Jam Mulai" autocomplete="off" required />
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="mb-3">
                       <label>Jam Selesai</label>
                       <input type="time" name="end_time_out" id="end_time_out" readonly="" class="timepicker" placeholder="Jam Selesai" autocomplete="off" required />
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
<input type="hidden" name="id_location" id="delete-id">

    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">Hapus Lokasi</h5>
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
                        Data yang telah dihapus tidak dapat dikembalikan lagi dan semua data yang berhubungan dengan lokasi ini juga akan terhapus
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

<?php } ?>


<?php $this->datatables->jquery('tableContent'); ?>

<style>
    #tableContent tbody tr.shift-row-inactive td,
    #tableContent tbody tr.shift-row-inactive td a:not(.dropdown-item) {
        color: #8c98a4 !important;
    }
    #tableContent .shift-status-btn {
        width: 28px;
        height: 28px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #e3e7eb;
        border-radius: 4px;
        line-height: 1;
    }
    #tableContent .shift-status-btn i {
        font-size: 13px;
        line-height: 1;
    }
    #tableContent .shift-status-btn.shift-status-active i {
        color: #34c38f !important;
    }
    #tableContent .shift-status-btn.shift-status-inactive i {
        color: #000 !important;
    }
    #tableContent .shift-action-buttons {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        white-space: nowrap;
    }
    #tableContent .shift-action-btn {
        width: 28px;
        height: 28px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #e3e7eb;
        border-radius: 4px;
        line-height: 1;
    }
    #tableContent .shift-action-btn i {
        font-size: 14px;
        line-height: 1;
    }
    #tableContent_filter {
        display: none;
    }
</style>

<script type="text/javascript">
    $(document).on('change', '#tipe', function(){
    if($(this).val() == 'ganti'){
      $('.shift_change').show();
    }else{
      $('.shift_change').hide();
    }
   })


   $(document).on('change', '#e_tipe', function(){
    if($(this).val() == 'ganti'){
      $('.e_shift_change').show();
    }else{
      $('.e_shift_change').hide();
    }
   })

    $(document).on('submit', '#formAdd', function(e){
        e.preventDefault();
        var status  = true;
        var btn     = $('#btnSave');
        var message = "";

        var formData = new FormData(this);

        $.ajax({
            url         : "<?= site_url('insert_shift') ?>",
            dataType    : "json",
            method      : "POST",
            data        : formData,
            processData : false,
            contentType : false,
            beforeSend  : function(){
                btn.html(show_loading()).attr('disabled', 'disabled');
            },
            success : function(res){
                $('#modalAdd').modal('hide');
                if(res.status){
                    $('#formAdd')[0].reset();
                    erTable_tableContent.ajax.reload(null, false);
                }

                var type = (res.status) ? 'success' : 'info';
                show_modal(type, res.message);
            },
            complete : function(){
                btn.html('<i class="fa fa-check"></i> Simpan').removeAttr('disabled');
            }
        });

        return false;
    });


    $(document).on('submit', '#formUpdate', function(e){
        e.preventDefault();
        var status  = true;
        var btn     = $('#btnUpdate');
        var message = "";

        var formData = new FormData(this);

        $.ajax({
            url         : "<?= site_url('update_shift') ?>",
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
                btn.html('Ubah').removeAttr('disabled');
            }
        });

        return false;
    });


    $(document).on('submit', '#formDelete', function(e){
        e.preventDefault();
        var status  = true;
        var btn     = $('#btnDelete');
        var message = "";

        var formData = new FormData(this);

        $.ajax({
            url         : "<?= site_url('delete_shift') ?>",
            dataType    : "json",
            method      : "POST",
            data : {
                myToken : "<?php echo $this->security->get_csrf_hash() ?>",
                id         : $('#delete-id').val()
            },
            beforeSend  : function(){
                btn.html(show_loading()).attr('disabled', 'disabled');
            },
            success : function(res){
                if(res.status){
                    erTable_tableContent.ajax.reload(null, false);
                }
                $('#modalDelete').modal('hide');
                var type = (res.status) ? 'success' : 'info';
                show_modal(type, res.message);
            },
            complete : function(){
                btn.html('Ya, Hapus Data ini').removeAttr('disabled');
            }
        });

        return false;
    });

    $(document).on('click', '.toggle-active', function(){
        var btn = $(this);
        $.ajax({
            url         : "<?= site_url('change_status_shift') ?>",
            dataType    : "json",
            method      : "POST",
            data        : {
                myToken : "<?php echo $this->security->get_csrf_hash() ?>",
                id      : btn.attr('data-id')
            },
            beforeSend  : function(){
                btn.attr('disabled', 'disabled');
            },
            success : function(res){
                if(res.status){
                    erTable_tableContent.ajax.reload(null, false);
                    return;
                }

                show_modal('info', res.message);
            },
            complete : function(){
                btn.removeAttr('disabled');
            }
        });
    });

    $(document).on('click', '.bulk-status', function(){
        var btn = $(this);
        $.ajax({
            url         : "<?= site_url('bulk_status_shift') ?>",
            dataType    : "json",
            method      : "POST",
            data        : {
                myToken   : "<?php echo $this->security->get_csrf_hash() ?>",
                branch_id : "<?= $branch_id ?>",
                is_active : btn.attr('data-active')
            },
            beforeSend  : function(){
                $('.bulk-status').attr('disabled', 'disabled');
                btn.data('html', btn.html()).html(show_loading());
            },
            success : function(res){
                if(res.status){
                    erTable_tableContent.ajax.reload(null, false);
                    return;
                }

                show_modal('info', res.message);
            },
            complete : function(){
                btn.html(btn.data('html'));
                $('.bulk-status').removeAttr('disabled');
            }
        });
    });

    $(document).on('keyup', '#shiftSearch', function(){
        erTable_tableContent.search($(this).val()).draw();
    });

    $(document).on('click', '#btnClearShiftSearch', function(){
        $('#shiftSearch').val('');
        erTable_tableContent.search('').draw();
    });


    $(document).on('click', '.delete', function(){
        var id   = $(this).attr('data-id');
        $('#delete-id').val(id);
    });

    $(document).on('click', '.edit', function(){
        var a = $(this);
        var id   = a.attr('data-id');

        $('#e_id').val(id);
        $('#name').val(a.attr('data-name'));
        $('#code').val(a.attr('data-code'));
        $('#is_active').val(a.attr('data-active'));
        $('#start_time').val(a.attr('data-start-time'));
        $('#end_time').val(a.attr('data-end-time'));

        $('#start_time_in').val(a.attr('data-start-time-in'));
        $('#start_time_late').val(a.attr('data-start-time-late'));
        $('#start_time_out').val(a.attr('data-start-time-out'));

        $('#end_time_in').val(a.attr('data-end-time-in'));
        $('#end_time_out').val(a.attr('data-end-time-out'));

        $('#late_amount_start').val(format_rp(a.attr('data-late-amount')));
        $('#late_amount_multiple_start').val(format_rp(a.attr('data-late-amount-multiple')));
        $('#late_multiple_count_start').val(a.attr('data-late-multiple-count'));
        $('#late_amount_max_start').val(format_rp(a.attr('data-late-amount-max-start')));

        $('#rest_time_range').val(a.attr('data-rest-time-range'));
        $('#start_time_rest').val(a.attr('data-start-time-rest'));
        $('#end_time_rest').val(a.attr('data-end-time-rest'));

        $('#late_amount_rest').val(format_rp(a.attr('data-late-amount-rest')));
        $('#late_amount_multiple_rest').val(format_rp(a.attr('data-late-amount-multiple-rest')));
        $('#late_multiple_count_rest').val(a.attr('data-late-multiple-count-rest'));
        $('#late_amount_max_rest').val(format_rp(a.attr('data-late-amount-max-rest')));

        $('#modalEdit').modal('show');
    });

</script>

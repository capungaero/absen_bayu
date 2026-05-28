<?php $role = $this->ion_auth->get_users_groups()->row()->name; ?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="dripicons-experiments"></i> Jadwal Cluster</h4>

            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Jadwal Kerja</a></li>
                    <li class="breadcrumb-item active"><a href="<?= site_url('hr/cluster') ?>">Jadwal Cluster</a></li>
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
                <h6 class="card-title">Daftar Jadwal Cluster</h6>
            </div>
            <div class="card-body">
                <form>
                    <div class="row">
                        <div class="col-md-2">
                            <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAdd" class="btn btn-primary"><i class="dripicons-plus"></i> Tambah Cluster</a>
                        </div>

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

                <br><br>

                <div class="table-responsive">
                    <?php $this->datatables->generate('tableContent'); ?>
                </div>
            </div>
        </div>
        
    </div>
</div>

<div id="modalAdd" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Tambah Cluster</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <form id="formAdd" class="repeater" enctype="multipart/form-data">
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
                    <input type="hidden" name="branch_id" value="<?= $branch_id ?>">

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                               <label>Kode Cluster</label>
                               <input type="text" name="cluster_code" class="form-control" placeholder="Kode Shift" autocomplete="off"  required />
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                               <label>Nama Cluster</label>
                               <input type="text" name="cluster_name"  class="form-control" placeholder="Nama Shift" autocomplete="off" required />
                            </div>
                        </div>
                    </div>

                    <div class="row">
                      <div class="col-md-12">
                        <label><i class="dripicons-clock"></i> Rotasi</label><br>
                        <small>*Urutan dimulai dari shift paling atas</small>
                      </div>

                      <div class="col-md-12 mt-1">

                        <div data-repeater-list="shift">
                            <div data-repeater-item class="row">
                                <div  class="col-md-8 mb-3">
                                    <label class="form-label" for="name">Shift</label>
                                    <select class="form-control" name="shift_id" required="">
                                        <option value="">Pilih</option>
                                        <option value="free">LIBUR</option>
                                        <?php foreach ($shift as $row) { ?>
                                            <option 
                                            data-start-time="<?= date('H:i', strtotime($row['start_time'])) ?>" 
                                            data-end-time="<?= date('H:i', strtotime($row['end_time'])) ?>"
                                            value="<?= $row['id'] ?>">
                                                <?= $row['shift_code']." / ".$row['shift_name'] ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-1 mt-2 align-self-center d-grid">
                                    <button data-repeater-delete type="button" class="btn btn-danger btn-sm"/><i class="dripicons-trash"></i></button>
                                </div>
                            </div>
                        </div>

                      </div>
                    </div>

                    
                    <button data-repeater-create type="button" class="btn btn-primary mt-3 mt-lg-0"/><i class="fa fa-plus"></i> Tambah Hari</button>

                    <div class="row">
                        <div class="col-md-12 text-end">
                            <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button> &nbsp;
                            <button id="btnSave" class="btn btn-success waves-effect waves-light"><i class="fa fa-check"></i> Simpan</button>
                        </div>
                    </div>
                    
                </form>
                
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<div id="modalEdit" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-edit"></i> Ubah Cluster</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <form id="formUpdate" class="repeater" enctype="multipart/form-data">
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
                    <input type="hidden" id="e_id" name="id_cluster">
                    <input type="hidden" name="branch_id" value="<?= $branch_id ?>">

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                               <label>Kode Cluster</label>
                               <input type="text" id="e_code" name="cluster_code" class="form-control" placeholder="Kode Cluster" autocomplete="off"  required />
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                               <label>Nama Cluster</label>
                               <input type="text" id="e_name" name="cluster_name"  class="form-control" placeholder="Nama Cluster" autocomplete="off" required />
                            </div>
                        </div>
                    </div>

                    <div class="row">
                      <div class="col-md-12">
                        <label><i class="dripicons-clock"></i> Rotasi</label><br>
                        <small>*Urutan dimulai dari shift paling atas</small>
                      </div>

                      <div class="col-md-12 mt-1">

                        <div data-repeater-list="shift" id="repeat-shift">
                            <div data-repeater-item class="row">
                                <div  class="col-md-8 mb-3">
                                    <label class="form-label" for="name">Shift</label>
                                    <select class="form-control" name="shift_id" required="">
                                        <option value="">Pilih</option>
                                        <option value="free">LIBUR</option>
                                        <?php foreach ($shift as $row) { ?>
                                            <option 
                                            data-start-time="<?= date('H:i', strtotime($row['start_time'])) ?>" 
                                            data-end-time="<?= date('H:i', strtotime($row['end_time'])) ?>"
                                            value="<?= $row['id'] ?>">
                                                <?= $row['shift_code']." / ".$row['shift_name'] ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-1 mt-2 align-self-center d-grid">
                                    <button data-repeater-delete type="button" class="btn btn-danger btn-sm"/><i class="dripicons-trash"></i></button>
                                </div>
                            </div>
                        </div>

                      </div>
                    </div>

                    
                    <button data-repeater-create type="button" class="btn btn-primary mt-3 mt-lg-0"/><i class="fa fa-plus"></i> Tambah Hari</button>

                    <div class="row bEdit">
                        <div class="col-md-12 text-end">
                            <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button> &nbsp;
                            <button id="btnUpdate" class="btn btn-warning waves-effect waves-light"><i class="fa fa-check"></i> Ubah</button>
                        </div>
                    </div>
                    
                </form>
                
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->


<form id="formDelete">
<div class="modal fade" id="modalDelete" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="staticBackdropLabel" aria-hidden="true">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" name="id_location" id="delete-id">

    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">Hapus Cluster</h5>
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
                        Data yang telah dihapus tidak dapat dikembalikan lagi dan semua data yang berhubungan dengan cluster ini juga akan terhapus
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

<div id="modalShift" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-clock"></i> Detail Shift</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body text-center">
                <div class="row">
                    <div class="col-md-12">
                        Shift
                        <h6 id="p_name"></h6>
                    </div>
                    <div class="col-md-12">
                        Waktu
                        <h6 id="p_time"></h6>
                    </div>
                </div>
                
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<?php $this->datatables->jquery('tableContent'); ?>

<script type="text/javascript">

    $(document).on('submit', '#formAdd', function(e){
        e.preventDefault();
        var status  = true;
        var btn     = $('#btnSave');
        var message = "";

        var formData = new FormData(this);

        $.ajax({
            url         : "<?= site_url('insert_cluster') ?>",
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
            url         : "<?= site_url('update_cluster') ?>",
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
            url         : "<?= site_url('delete_cluster') ?>",
            dataType    : "json",
            method      : "POST",
            data : {
                myToken : "<?php echo $this->security->get_csrf_hash() ?>",
                id        : $('#delete-id').val(),
                branch_id : '<?= $branch_id ?>'
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


    $(document).on('click', '.delete', function(){
        var id   = $(this).attr('data-id');
        $('#delete-id').val(id);
    });

    $(document).on('click', '.edit', function(){
        var a = $(this);
        var id   = a.attr('data-id');

        $('#e_id').val(id);
        $('#e_name').val(a.attr('data-name'));
        $('#e_code').val(a.attr('data-code'));
        $('#e_date').val(a.attr('data-date'));

        <?php if($this->input->get('branch_id')){ ?>
            var branch_id = '?branch_id=<?= $this->input->get("branch_id") ?>';
        <?php }else{ ?>
            var branch_id = '';
        <?php } ?>

        $.ajax({
            url         : "<?= site_url('get_rotation/') ?>" + id + branch_id,
            dataType    : "json",
            method      : "GET",
            processData : false,
            contentType : false,
            beforeSend  : function(){
                $('#repeat-shift').html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Mengambil Data...</div>');
                $('.bEdit').hide();
            },
            success : function(res){
                if(res.status){
                    $('#repeat-shift').html(res.data);
                    $('.bEdit').show();

                }else{
                    alert(res.message);
                }
            }
        });

        $('#modalEdit').modal('show');
    });

    $(document).on('click', '.shift', function(){
        var a = $(this);

        $('#p_name').text(a.attr('data-name'));
        $('#p_time').text(a.attr('data-time'));

        $('#modalShift').modal('show');
    });

</script>

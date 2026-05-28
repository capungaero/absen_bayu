<?php $role = $this->ion_auth->get_users_groups()->row()->name; ?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="dripicons-biefcase"></i> Sub Departement</h4>

            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Master Data</a></li>
                    <li class="breadcrumb-item active"><a href="<?= site_url('master_data/subdivision') ?>">Posisi</a></li>
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
                <h6 class="card-title">Daftar Sub Departement</h6>
            </div>
            <div class="card-body">

                <form>
                    <div class="row">
                        <div class="col-md-3">
                            <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAdd" class="btn btn-primary"><i class="dripicons-plus"></i> Tambah Sub Departement</a>
                        </div>

                        <?php if($role == 'admin'){ ?>
                            <div class="col-md-4"></div>
                            <div class="col-md-4">
                                <label>Pilih Cabang</label>
                                <select class="form-control select-plugin" name="branch_id" id="branch" style="width: 100%">
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

<form id="formAdd">
<input type="hidden" name="branch_id" value="<?= $branch_id ?>">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<div id="modalAdd" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Tambah Sub Departement</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">                                                            
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-email-input">Kode</label>
                            <input type="text" required="" autocomplete="off" placeholder="Kode Sub Departement" class="form-control" name="subdivision_code">
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Nama</label>
                            <input type="text" required="" autocomplete="off" placeholder="Nama Sub Departement" class="form-control" name="subdivision_name">
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
<input type="hidden" name="branch_id" value="<?= $branch_id ?>">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" name="id_subdivision" id="e_id">
<div id="modalEdit" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Ubah Sub Departement</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">                                                            
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-email-input">Kode</label>
                            <input type="text" required="" autocomplete="off" placeholder="Kode Sub Departement" class="form-control" name="subdivision_code" id="e_code">
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Nama</label>
                            <input type="text" required="" autocomplete="off" placeholder="Nama Sub Departement" class="form-control" name="subdivision_name" id="e_name">
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
<input type="hidden" name="id_subdivision" id="delete-id">

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
    $(document).on('submit', '#formAdd', function(e){
        e.preventDefault();
        var status  = true;
        var btn     = $('#btnSave');
        var message = "";

        var formData = new FormData(this);

        $.ajax({
            url         : "<?= site_url('insert_subdivision') ?>",
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
            url         : "<?= site_url('update_subdivision') ?>",
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


    $(document).on('submit', '#formDelete', function(e){
        e.preventDefault();
        var status  = true;
        var btn     = $('#btnDelete');
        var message = "";

        var formData = new FormData(this);

        $.ajax({
            url         : "<?= site_url('delete_subdivision') ?>",
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


    $(document).on('click', '.delete', function(){
        var id   = $(this).attr('data-id');
        $('#delete-id').val(id);
    });

    $(document).on('click', '.edit', function(){
        var a = $(this);
        var id   = a.attr('data-id');

        $('#e_name').val(a.attr('data-name'));
        $('#e_code').val(a.attr('data-code'));
        
        $('#e_branch option').removeAttr('selected');
        $('#e_branch option[value="'+ a.attr('data-branch-id') +'"]').attr('selected', 'selected');

        $('#e_id').val(id);
        $('#modalEdit').modal('show');
    });

</script>

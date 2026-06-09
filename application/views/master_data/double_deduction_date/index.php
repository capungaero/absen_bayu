<?php $role = $this->ion_auth->get_users_groups()->row()->name; ?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="dripicons-calendar"></i> Tanggal Potongan Double</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Master Data</a></li>
                    <li class="breadcrumb-item active"><a href="<?= site_url('master_data/double_deduction_date') ?>">Tanggal Potongan Double</a></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title">Daftar Tanggal Potongan Double</h6>
            </div>
            <div class="card-body">
                <form>
                    <div class="row">
                        <div class="col-md-3">
                            <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAdd" class="btn btn-primary"><i class="dripicons-plus"></i> Tambah Tanggal</a>
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
<div id="modalAdd" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0"><i class="fa fa-plus"></i> Tambah Tanggal Potongan Double</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Tanggal</label>
                            <input type="text" required autocomplete="off" placeholder="Tanggal khusus" class="form-control mdate" name="special_date">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="is_active" class="form-control">
                                <option value="1">Aktif</option>
                                <option value="0">Non Aktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <input type="text" autocomplete="off" placeholder="Contoh: Hari Besar" class="form-control" name="description">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>
                <button id="btnSave" class="btn btn-success waves-effect waves-light"><i class="fa fa-check"></i> Simpan</button>
            </div>
        </div>
    </div>
</div>
</form>

<form id="formUpdate">
<input type="hidden" name="branch_id" value="<?= $branch_id ?>">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" name="id_double_date" id="e_id">
<div id="modalEdit" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 style="color: #fff" class="modal-title mt-0"><i class="fa fa-pencil"></i> Ubah Tanggal Potongan Double</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Tanggal</label>
                            <input type="text" required autocomplete="off" placeholder="Tanggal khusus" class="form-control mdate" name="special_date" id="e_date">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="is_active" class="form-control" id="e_active">
                                <option value="1">Aktif</option>
                                <option value="0">Non Aktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <input type="text" autocomplete="off" placeholder="Contoh: Hari Besar" class="form-control" name="description" id="e_description">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>
                <button id="btnUpdate" class="btn btn-warning waves-effect waves-light"><i class="fa fa-pencil"></i> Ubah</button>
            </div>
        </div>
    </div>
</div>
</form>

<form id="formDelete">
<div class="modal fade" id="modalDelete" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-hidden="true">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" id="delete-id">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title" style="color: #fff">Hapus Tanggal Khusus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-3">
                        <img src="<?= base_url('assets/images/icon/question.png') ?>" class="img-fluid">
                    </div>
                    <div class="col-md-9">
                        <h6>Apakah anda yakin menghapus data ini ?</h6>
                        Tanggal ini tidak akan dihitung lagi sebagai potongan double.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tidak</button>
                <button class="btn btn-danger" id="btnDelete">Ya, Hapus</button>
            </div>
        </div>
    </div>
</div>
</form>

<?php $this->datatables->jquery('tableContent'); ?>

<script type="text/javascript">
    $(document).on('submit', '#formAdd', function(e){
        e.preventDefault();
        var btn = $('#btnSave');
        var formData = new FormData(this);

        $.ajax({
            url: "<?= site_url('insert_double_deduction_date') ?>",
            dataType: "json",
            method: "POST",
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(){ btn.html(show_loading()).attr('disabled', 'disabled'); },
            success: function(res){
                $('#modalAdd').modal('hide');
                if(res.status){
                    $('#formAdd')[0].reset();
                    erTable_tableContent.ajax.reload(null, false);
                }
                show_modal(res.status ? 'success' : 'info', res.message);
            },
            complete: function(){ btn.html('<i class="fa fa-check"></i> Simpan').removeAttr('disabled'); }
        });
    });

    $(document).on('submit', '#formUpdate', function(e){
        e.preventDefault();
        var btn = $('#btnUpdate');
        var formData = new FormData(this);

        $.ajax({
            url: "<?= site_url('update_double_deduction_date') ?>",
            dataType: "json",
            method: "POST",
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(){ btn.html(show_loading()).attr('disabled', 'disabled'); },
            success: function(res){
                $('#modalEdit').modal('hide');
                if(res.status){
                    $('#formUpdate')[0].reset();
                    erTable_tableContent.ajax.reload(null, false);
                }
                show_modal(res.status ? 'success' : 'info', res.message);
            },
            complete: function(){ btn.html('<i class="fa fa-pencil"></i> Ubah').removeAttr('disabled'); }
        });
    });

    $(document).on('submit', '#formDelete', function(e){
        e.preventDefault();
        var btn = $('#btnDelete');

        $.ajax({
            url: "<?= site_url('delete_double_deduction_date') ?>",
            dataType: "json",
            method: "POST",
            data: {
                myToken: "<?php echo $this->security->get_csrf_hash() ?>",
                id: $('#delete-id').val()
            },
            beforeSend: function(){ btn.html(show_loading()).attr('disabled', 'disabled'); },
            success: function(res){
                $('#modalDelete').modal('hide');
                if(res.status){
                    erTable_tableContent.ajax.reload(null, false);
                }
                show_modal(res.status ? 'success' : 'info', res.message);
            },
            complete: function(){ btn.html('Ya, Hapus').removeAttr('disabled'); }
        });
    });

    $(document).on('click', '.toggle-double-date-status', function(){
        var btn = $(this);

        $.ajax({
            url: "<?= site_url('change_status_double_deduction_date') ?>",
            dataType: "json",
            method: "POST",
            data: {
                myToken: "<?php echo $this->security->get_csrf_hash() ?>",
                id: btn.attr('data-id')
            },
            beforeSend: function(){ btn.attr('disabled', 'disabled'); },
            success: function(res){
                if(res.status){
                    erTable_tableContent.ajax.reload(null, false);
                    return;
                }
                show_modal('info', res.message);
            },
            complete: function(){ btn.removeAttr('disabled'); }
        });
    });

    $(document).on('click', '.delete', function(){
        $('#delete-id').val($(this).attr('data-id'));
    });

    $(document).on('click', '.edit', function(){
        var a = $(this);
        $('#e_id').val(a.attr('data-id'));
        $('#e_date').val(a.attr('data-date'));
        $('#e_description').val(a.attr('data-description'));
        $('#e_active').val(a.attr('data-active'));
        $('#modalEdit').modal('show');
    });
</script>

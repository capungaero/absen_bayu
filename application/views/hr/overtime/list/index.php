<?php $role = $this->ion_auth->get_users_groups()->row()->name; ?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="dripicons-biefcase"></i> Lembur</h4>

            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Lembur</a></li>
                    <li class="breadcrumb-item active"><a href="<?= site_url('hr/overtime/list') ?>">Pengajuan</a></li>
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
                <h6 class="card-title">Daftar Pengajuan Lembur</h6>
            </div>
            <div class="card-body">

                <form>
                    <div class="row">
                        <div class="col-md-3">
                            <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAdd" class="btn btn-primary"><i class="dripicons-plus"></i> Pengajuan Lembur</a>
                        </div>

                        <?php if($role == 'admin'){ ?>
                            <div class="col-md-4"></div>
                            <div class="col-md-4">
                                <label>Pilih Cabang</label>
                                <select class="form-control select-plugin" name="branch_id" id="branch">
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

<form id="formAdd" enctype="multipart/form-data">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<div id="modalAdd" class="modal fade" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Pengajuan Lembur</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">
                    <?php if(in_array($role, ['admin', 'admin-branch', 'hr', 'supervisor'])){ ?>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label" for="formrow-password-input">Karyawan</label>
                                <select style="width: 100%" class="form-control select-plugin" id="overtimeEmployeeSelect" required="" multiple>
                                    <option value="">Pilih</option>
                                    <?php foreach ($employee as $row) { ?>
                                        <option value="<?= $row['id'] ?>"><?= $row['employee_code']." / ".$row['first_name'] ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="col-md-12">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead>
                                    <tr>
                                        <th>Karyawan</th>
                                        <th style="width: 140px">Lama Lembur</th>
                                        <th style="width: 180px">Tanggal Lembur</th>
                                        <th style="width: 60px"></th>
                                    </tr>
                                </thead>
                                <tbody id="overtimeRows">
                                    <?php if(!in_array($role, ['admin', 'admin-branch', 'hr', 'supervisor'])){ ?>
                                        <tr>
                                            <td>
                                                <?= $this->userdata->first_name ?>
                                                <input type="hidden" name="user_id[]" value="<?= $this->userdata->user_id ?>">
                                            </td>
                                            <td><input type="text" name="overtime_hour[]" class="form-control" placeholder="Jam..." required autocomplete="off"></td>
                                            <td><input type="date" name="overtime_date[]" class="form-control" required autocomplete="off"></td>
                                            <td></td>
                                        </tr>
                                    <?php }else{ ?>
                                        <tr class="overtime-empty-row">
                                            <td colspan="4" class="text-muted text-center">Pilih karyawan untuk membuat baris lembur.</td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Bukti Lembur</label><br>
                            <input type="file" name="overtime_proof" required="" class="btn btn-light">
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

<form id="formUpdate" enctype="multipart/form-data">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" name="overtime_id" id="e_overtime_id">
<div id="modalEdit" class="modal fade" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 style="color:#fff" class="modal-title">Edit Lembur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table">
                    <tr><th>Karyawan</th><td id="e_employee_label"></td></tr>
                </table>
                <div class="row">
                    <div class="col-md-3">
                        <label>Lama Lembur</label>
                        <input type="text" name="overtime_hour" id="e_overtime_hour" class="form-control" required>
                    </div>
                    <div class="col-md-9">
                        <label>Tanggal Lembur</label>
                        <input type="text" name="overtime_date" id="e_overtime_date" class="form-control mdate-max" required>
                    </div>
                    <div class="col-md-12 mt-3">
                        <label>Ganti Bukti Lembur</label>
                        <input type="file" name="overtime_proof" class="btn btn-light">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                <button id="btnUpdate" class="btn btn-warning"><i class="fa fa-save"></i> Save Ulang</button>
            </div>
        </div>
    </div>
</div>
</form>


<form id="formDelete">
<div class="modal fade" id="modalDelete" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="staticBackdropLabel" aria-hidden="true">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" name="id_position" id="delete-id">

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
    function escapeHtml(text){
        return $('<div>').text(text).html();
    }

    function syncOvertimeRows(){
        if($('#overtimeEmployeeSelect').length == 0){
            return;
        }
        var selected = $('#overtimeEmployeeSelect option:selected');
        var tbody = $('#overtimeRows');
        if(selected.length == 0){
            tbody.html('<tr class="overtime-empty-row"><td colspan="4" class="text-muted text-center">Pilih karyawan untuk membuat baris lembur.</td></tr>');
            return;
        }

        selected.each(function(){
            var option = $(this);
            var userId = option.val();
            if(userId == '' || tbody.find('tr[data-user-id="'+userId+'"]').length > 0){ return; }
            tbody.find('.overtime-empty-row').remove();
            tbody.append(
                '<tr data-user-id="'+escapeHtml(userId)+'">' +
                    '<td>'+escapeHtml(option.text())+'<input type="hidden" name="user_id[]" value="'+escapeHtml(userId)+'"></td>' +
                    '<td><input type="text" name="overtime_hour[]" class="form-control" placeholder="Jam..." required autocomplete="off"></td>' +
                    '<td><input type="date" name="overtime_date[]" class="form-control" required autocomplete="off"></td>' +
                    '<td class="text-center"><a href="javascript:void(0)" class="btn btn-light btn-sm remove-overtime-row"><i class="fa fa-times"></i></a></td>' +
                '</tr>'
            );
        });

        tbody.find('tr[data-user-id]').each(function(){
            var userId = $(this).attr('data-user-id');
            if($('#overtimeEmployeeSelect option[value="'+userId+'"]:selected').length == 0){
                $(this).remove();
            }
        });
    }

    $(document).on('change', '#overtimeEmployeeSelect', syncOvertimeRows);

    $(document).on('click', '.remove-overtime-row', function(){
        var row = $(this).closest('tr');
        var userId = row.attr('data-user-id');
        row.remove();
        $('#overtimeEmployeeSelect option[value="'+userId+'"]').prop('selected', false);
        $('#overtimeEmployeeSelect').trigger('change');
        syncOvertimeRows();
    });

    $(document).on('submit', '#formAdd', function(e){
        e.preventDefault();
        var status  = true;
        var btn     = $('#btnSave');
        var message = "";

        var formData = new FormData(this);

        $.ajax({
            url         : "<?= site_url('insert_overtime') ?>",
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
                    $('#overtimeEmployeeSelect').val(null).trigger('change');
                    syncOvertimeRows();
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
            url         : "<?= site_url('update_overtime') ?>",
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
            url         : "<?= site_url('delete_position') ?>",
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
        $('#e_overtime_id').val(a.attr('data-id'));
        $('#e_overtime_hour').val(a.attr('data-hour'));
        $('#e_overtime_date').val(a.attr('data-date'));
        $('#e_employee_label').html(a.attr('data-code') + ' / ' + a.attr('data-name'));
        $('#modalEdit').modal('show');
    });


    $(document).on('click', '.approval', function(){
        var id   = $(this).attr('data-id');
        var tipe = $(this).attr('data-tipe');

        swal({
            title: "Apakah kamu yakin ?",
            text: "Ubah status kategori",
            icon: "warning",
            closeOnClickOutside: false,
            closeOnEsc: false,
            dangerMode : true,
            buttons:{
                confirm : {
                    text:'Iya',
                    className:'sweet-warning',
                    closeModal:false
                },
                cancel : 'Tidak, jangan ubah'
            }
        }).then((confirmed)=>{
            if(confirmed){
                var update = $.ajax({
                    url         : "<?= site_url('change_status_category') ?>/" + tipe,
                    dataType    : "json",
                    method      : "POST",
                    data : {
                        myToken : "<?php echo $this->security->get_csrf_hash() ?>",
                        id         : id,
                        tipe       : tipe
                    },
                    success : function(res){
                        if(res.status){
                            erTable_tableCategory.ajax.reload(null, false);
                            swal("Success", res.message, "success");
                        }else{
                            swal("Failure", res.message, "error");
                        }
                    }
                });
            }
        });

    });
</script>

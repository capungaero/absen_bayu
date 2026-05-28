<?php 
    $role = $this->ion_auth->get_users_groups()->row()->name;
    $userdata = $this->ion_auth->user()->row();
?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="dripicons-biefcase"></i> Izin</h4>

            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Izin</a></li>
                    <li class="breadcrumb-item active"><a href="<?= site_url('hr/leave/list') ?>">Pengajuan</a></li>
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
                <h6 class="card-title">Daftar Pengajuan Izin</h6>
            </div>
            <div class="card-body">

                <form>
                    <div class="row">
                        <div class="col-md-3">
                            <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAdd" class="btn btn-primary"><i class="dripicons-plus"></i> Pengajuan Izin</a>
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

<form id="formAdd">
<input type="hidden" id="status_work" value="<?= !in_array($role, ['admin', 'admin-branch']) ? $userdata->status_work : '' ?>">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">

<div id="modalAdd" class="modal fade" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Pengajuan Izin</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">
                    <?php if(in_array($role, ['admin', 'admin-branch', 'supervisor'])){ ?>
                        <div class="col-md-9">
                            <div class="mb-3">
                                <label class="form-label" for="formrow-password-input">Karyawan</label>
                                <select style="width: 100%" class="form-control select-plugin" required="" name="user_id" id="employee">
                                    <option value="">Pilih</option>
                                    <?php foreach ($employee as $row) { ?>
                                        <option data-status-work="<?= $row['status_work'] ?>" value="<?= $row['id'] ?>"><?= $row['employee_code']." / ".$row['first_name'] ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label" for="formrow-password-input">Jenis Izin</label>
                                <select style="width: 100%" class="form-control" required="" name="leave_type">
                                    <option value="">Pilih</option>
                                    <option value="izin">Izin</option>
                                    <option value="sakit">Sakit</option>
                                    <option value="cuti">Cuti</option>
                                </select>
                            </div>
                        </div>

                    <?php }else{ ?>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label" for="formrow-password-input">Jenis Izin</label>
                                <select style="width: 100%" class="form-control" required="" name="leave_type">
                                    <option value="">Pilih</option>
                                    <option value="izin">Izin</option>
                                    <option value="sakit">Sakit</option>
                                    <option value="cuti">Cuti</option>
                                </select>
                            </div>
                        </div>
                    <?php } ?>
                    

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Tanggal Mulai</label>
                            <input type="text" class="form-control mdate" id="leave_start" name="leave_start" placeholder="Tanggal..." required="" autocomplete="off">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Tanggal Selesai</label>
                            <input type="text" class="form-control mdate" id="leave_end" name="leave_end" placeholder="Tanggal..." required="" autocomplete="off">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3" id="alert-potongan">
                            
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Besar Potongan Gaji Harian</label>
                            <input type="text" disabled="" class="form-control" id="taxGaji">
                            <small class="text-muted">*Besaran gaji harian yang dipotong saat rentang perizinan</small>
                        </div>
                    </div>

                    <div class="col-md-7">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input"> 
                                <input type="checkbox" id="checkPotongan" name="keringanan_potongan"> Ajukan Keringan Potongan
                            </label><br>
                            <small class="text-muted">*Ketik jumlah potongan yang anda inginkan</small>
                           
                        </div>
                    </div>

                    <div class="col-5" id="keringanan_potonganBody">
                        <div class="mb-3">
                            <label class="visually-hidden" for="specificSizeInputGroupUsername">Keringan Potongan</label>
                            <div class="input-group">
                                <input type="number" name="request_potongan" class="form-control" id="specificSizeInputGroupUsername" placeholder="0">
                                <div class="input-group-text">%</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Alasan Izin</label><br>
                            <textarea class="form-control" required="" name="leave_reason" placeholder="Ketik alasan"></textarea>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Bukti Izin</label><br>
                            <input type="file" name="leave_proof" class="btn btn-light"><br>
                            <small>*Digunakan untuk validasi approval izin oleh admin, tidak wajib diisi</small>
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



<?php $this->datatables->jquery('tableContent'); ?>

<script type="text/javascript">
    $('#keringanan_potonganBody').hide();

    <?php if(!in_array($role, ['admin', 'admin-branch'])){ ?>
         CalcaluatePotongan($('#status_work').val());

    <?php }else{ ?>

        $(document).on('change', '#status_work', function(){
            CalcaluatePotongan($(this).val())
        })

    <?php } ?>

    $(document).on('change', '#employee', function(){
        CalcaluatePotongan($('#employee option:selected').attr('data-status-work'))
    })

    $(document).ready(function(){
        $('#checkPotongan').click(function(){
            if($(this).prop("checked") == true){
                $('#keringanan_potonganBody').show();
            }else{
                $('#keringanan_potonganBody').hide();
            }
        });
    });

    $(document).on('submit', '#formAdd', function(e){
        e.preventDefault();
        var status  = true;
        var btn     = $('#btnSave');
        var message = "";

        var formData = new FormData(this);

        $.ajax({
            url         : "<?= site_url('insert_leave') ?>",
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
                    $('#keringanan_potonganBody').hide();
                    $('#alert-potongan').html('');
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

    $(document).on('change', '#leave_start, #leave_end', function(){
        var start = $('#leave_start').val();
        var end = $('#leave_end').val();

        if(start != '' && end != ''){
            if(start > end){
                alert('Tanggal mulai harus dibawah tanggal selesai')
                $('#leave_start').val(''); $('#leave_end').val('');

            }else{
                var listDate = allDate(start, end);
                var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                var totalDay = 0;

                $.each(listDate, function(index, row){
                    var d = new Date(row);
                    var dayName = days[d.getDay()];

                    console.log(dayName);
                    if(dayName == 'Saturday' || dayName == 'Sunday'){
                        totalDay += 2;
                    }else{
                        totalDay++;
                    }
                })

                $('#alert-potongan').html('<div class="alert alert-warning">Potongan akan berlaku sebanyak : <b>'+totalDay+' Hari Kerja</b><br><small class="text-muted">*Weekend (Sabtu - Minggu) akan terhitung 2 hari kerja</small></div>')
            }
        }
    })

    var allDate = function(d1, d2){
        var start_date = new Date(d1);
        var end_date = new Date(d2);

        var date_range = new Array();
        var st_date = new Date(start_date);
        while (st_date <= end_date) {
             let month  = ("0" + (st_date.getMonth() + 1)).slice(-2);
             let day   = ("0" + st_date.getDate()).slice(-2);     
             let date = [st_date.getFullYear(), month, day].join("-");
             date_range.push(date);
             st_date.setDate(st_date.getDate() + 1);
        }
        return date_range;
    }

    function CalcaluatePotongan(work_status){
        var tax = 0;
        if(work_status == 'permanent' || work_status == 'contract'){
            tax = 50
        }else{
            tax = 75
        }
        $('#taxGaji').val(tax+"%");
    }
</script>

<?php $role = $this->ion_auth->get_users_groups()->row()->name; ?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">Dashboard</h4>

            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Summary</a></li>
                </ol>
            </div>

        </div>
    </div>
</div>
<!-- end page title -->

<?php if($role == 'admin'){ ?>
    <form>
        <div class="row mb-3">
            <div class="col-md-4">
                <label>Pilih Cabang</label>
                <select style="width: 100%" class="form-control select-plugin" name="branch_id" id="branch">
                    <?php foreach ($branch_list as $row) { ?>
                        <option <?= $branch_id == $row['id'] ? 'selected="selected"' : '' ?> value="<?= $row['id'] ?>"><?= $row['branch_code']." / ".$row['branch_name'] ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="col-md-1">
                <br>
                <button class="btn btn-primary mt-2"><i class="fa fa-search"></i></button>
            </div>
        </div>
    </form>
<?php } ?>

<div class="row">
    <?php if(in_array($role, ['admin', 'admin-branch'])){ ?>
        <div class="col-sm-6 col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="float-end mt-2">
                        <img style="width: 60px" src="<?= base_url('assets/images/icon/team.png') ?>" class="img-fluid">
                    </div>
                    <div>
                        <h4 class="mb-1 mt-1"><?= $count_employee ?></h4>
                        <p class="text-muted mb-0">Karyawan Aktif</p>
                    </div>
                </div>
                <a href="<?= site_url('master_data/employee') ?>">
                    <div class="card-footer text-center">
                        Lihat Data <i class="fa fa-arrow-circle-right"></i>
                    </div>
                </a>
            </div>
        </div>

        <div class="col-sm-6 col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="float-end mt-2">
                        <img style="width: 60px" src="<?= base_url('assets/images/icon/position.png') ?>" class="img-fluid">
                    </div>
                    <div>
                        <h4 class="mb-1 mt-1"><?= $count_position ?></h4>
                        <p class="text-muted mb-0">Posisi</p>
                    </div>
                </div>
                <a href="<?= site_url('master_data/position') ?>">
                    <div class="card-footer text-center">
                        Lihat Data <i class="fa fa-arrow-circle-right"></i>
                    </div>
                </a>
            </div>
        </div>
    <?php } ?>

    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="float-end mt-2">
                    <img style="width: 100px" src="<?= base_url('assets/images/logo.png') ?>">
                </div>
                <div>
                    <h5 class="mb-1 mt-1"><?= $branch['branch_name'] ?></h5>
                    <p class="text-muted mb-0"><?= $branch['branch_code']." / ".$branch['city'] ?></p>
                </div>

                <p class="text-muted mt-3 mb-0"><span class="me-1">
                    <?= $branch['address']." <br>".$branch['branch_phone'] ?>
                </span>
                </p>
            </div>
        </div>
    </div>
</div> <!-- end row-->

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title">NOTIFIKASI TANGGAL KONTRAK KARYAWAN</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    Note :<br>
                    * Notifikasi berjalan jika tanggal selesai kontrak akan habis dibawah 1 bulan <br>
                    ** Harap <b>Perpanjang</b> Atau <b>Nonaktifkan Manual</b> status karyawan jika ada masa kontrak yang sudah habis
                </div>
                <table class="table">
                    <thead style="background-color: #eee">
                        <tr>
                            <th style="width: 5%">NO</th>
                            <th>KARYAWAN</th>
                            <th>NIK</th>
                            <th>JABATAN / POSISI</th>
                            <th>STATUS KERJA</th>
                            <th>TANGGAL SELESAI KONTRAK</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $n=0; 
                        foreach ($employee_expire as $row) { $n++;
                            $now = date('Y-m-d');

                            if($now > $row['status_work_expiration']){
                                $status = '<button class="btn btn-danger btn-sm approval" data-id="'.$row['user_id'].'">Masa Kerja Selesai</button>';
                            }else{
                                $diff = diffInDays($now, $row['status_work_expiration']);
                                $status = '<span class="btn btn-outline-danger btn-sm">'.$diff.'  Hari Lagi</span>';
                            }
                        ?>
                            <tr>
                                <td><?= $n ?></td>
                                <td><?= $row['first_name'] ?></td>
                                <td><?= $row['contract_number'] ?></td>
                                <td><?= $row['position_name'] ?></td>
                                <td><?= $row['status_work'] == 'contract' ? 'Kontrak' : 'Training' ?></td>
                                <td><?= indonesian_date($row['status_work_expiration']) ?></td>
                                <td><?= $status ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalActive" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="staticBackdropLabel" aria-hidden="true">
<input type="hidden" id="approval-id">

    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">Status Karyawan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-3">
                        <img src="<?= base_url('assets/images/icon/question.png') ?>" class="img-fluid">
                    </div>
                    <div class="col-md-9">
                        <h6>Apakah anda yakin mengubah status data ini ?</h6>
                        Status data karyawan akan diubah
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tidak</button>
                <button class="btn btn-danger" id="btnStatus">Ya, ubah</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).on('click', '.approval', function(){
        var id   = $(this).attr('data-id');
        $('#approval-id').val(id);
        $('#modalActive').modal('show');
    });

    $(document).on('click', '#btnStatus', function(e){
        var status  = true;
        var btn     = $('#btnStatus');
        var message = "";

        $.ajax({
            url         : "<?= site_url('change_status_employee') ?>",
            dataType    : "json",
            method      : "POST",
            data : {
                myToken : "<?php echo $this->security->get_csrf_hash() ?>",
                id      : $('#approval-id').val()
            },
            beforeSend  : function(){
                btn.html(show_loading()).attr('disabled', 'disabled');
            },
            success : function(res){
                if(res.status){
                    window.location.reload();
                }

                $('#modalActive').modal('hide');
                var type = (res.status) ? 'success' : 'info';
                show_modal(type, res.message);
            },
            complete : function(){
                btn.html('Ya, Ubah').removeAttr('disabled');
            }
        });
    });
</script>
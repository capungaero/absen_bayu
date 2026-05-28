<?php $role = $this->ion_auth->get_users_groups()->row()->name; ?>

<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="dripicons-inbox"></i> Karyawan</h4>

            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Master Data</a></li>
                    <li class="breadcrumb-item active"><a href="<?= site_url('master_data/employee') ?>">Karyawan</a></li>
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
                <h6 class="card-title">Daftar Karyawan</h6>
            </div>
            <div class="card-body">
                <?php if($role == 'admin'){ ?>

                    <form>
                        <div class="row">
                            <div class="col-md-7">
                                <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAdd" class="btn btn-primary"><i class="dripicons-plus"></i> Tambah Karyawan</a> &emsp;

                                <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalUpload" class="btn btn-success"><i class="fa fa-clock"></i> Upload</a> &emsp;

                                <a href="<?= site_url('export_employee?branch_id='.$branch_id) ?>" class="btn btn-outline-danger"><i class="fa fa-file-excel"></i> Download Daftar Karyawan</a>
                            </div>

                            <div class="col-md-4">
                                <label>Pilih Cabang</label>
                                <select style="width: 100%" class="form-control select-plugin" name="branch_id" id="branch">
                                    <?php foreach ($branch as $row) { ?>
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

                    <br><br>
                <?php } ?>
                <div class="table-responsive">
                    <?php $this->datatables->generate('tableContent'); ?>
                </div>
            </div>
        </div>
        
    </div>
</div>

<?php if($role == 'admin'){ ?>
<form id="formAdd">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" name="branch_id" value="<?= $branch_id ?>">
<div id="modalAdd" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Tambah Karyawan</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">                                                            
                    <div class="col-md-5">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-email-input">ID Fingerprint</label>
                            <input type="text" required="" autocomplete="off" placeholder="ID Fingerprint karyawan" class="form-control" name="employee_code">
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Nama</label>
                            <input type="text" required="" autocomplete="off" placeholder="Nama karyawan" class="form-control" name="first_name">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">NIK</label>
                            <input type="text" required="" autocomplete="off" placeholder="NIK" class="form-control" name="contract_number">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">No KK</label>
                            <input type="text" autocomplete="off" placeholder="No KK" class="form-control" name="npwp_number">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Status PTKP</label>
                            <select class="form-control" required name="ptkp_status">
                                <option value="">Pilih</option>
                                <?php foreach (PTKP_STATUS as $row) { ?>
                                    <option value="<?= $row ?>"><?= $row ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Nama Bank</label>
                            <input type="text" required="" autocomplete="off" placeholder="Nama Bank" class="form-control" name="account_bank">
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Nomor Rekening</label>
                            <input type="text" required="" autocomplete="off" placeholder="Nomor Rekening" class="form-control" name="account_number">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Pemilik Rekening</label>
                            <input type="text" required="" autocomplete="off" placeholder="Nama Pemilik" class="form-control" name="account_name">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Tanggal Mulai Kerja</label>
                            <input type="text" required="" name="join_date" autocomplete="off" placeholder="Tanggal" class="form-control mdate">
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label">Jabatan</label>
                            <select class="form-control" required="" name="position_id">
                                <option value="">Pilih</option>
                                <?php foreach ($position as $row) { ?>
                                    <option value="<?= $row['id'] ?>"><?= $row['position_code']." / ".$row['position_name'] ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label">Sub Departement</label>
                            <select class="form-control" required="" name="subdivision_id">
                                <option value="">Pilih</option>
                                <?php foreach ($subdivision as $row) { ?>
                                    <option value="<?= $row['id'] ?>"><?= $row['subdivision_code']." / ".$row['subdivision_name'] ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Gaji Pokok</label>
                            <input type="text" required="" name="salary" autocomplete="off" placeholder="Rp. 0" class="form-control rupiah">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Gaji Minimum</label>
                            <input type="text" required="" name="salary_minimum" autocomplete="off" placeholder="Rp. 0" class="form-control rupiah">
                            <small>*Isi 0 jika tidak ada</small>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Upah Lembur / Jam</label>
                            <input type="text" required="" name="overtime_hour_rate" autocomplete="off" placeholder="Rp. 0" class="form-control rupiah">
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Foto</label>
                            <input type="file" name="photo" class="form-control">
                            <small>* Dapat diinputkan nanti</small>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Nomor Handphone</label>
                            <input type="text" class="form-control" required="" placeholder="Masukkan Nomor handpone..." name="phone">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Email</label>
                            <input type="email" class="form-control" required="" placeholder="Masukkan email..." name="email">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Password</label>
                            <input type="password" class="form-control" required="" placeholder="Masukkan password..." name="password" autocomplete="new-password">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Alamat Lengkap</label>
                            <input type="text" class="form-control" required="" placeholder="Ketik alamat lengkap disini..." name="employee_address">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label">Lokasi</label>
                            <input type="text" class="form-control" placeholder="Contoh: HOUSEWARE GAMBIR" name="location">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Status Kerja</label>
                            <select class="form-control" required="" name="status_work" id="status_work">
                                <option value="permanent">Tetap</option>
                                <option value="contract">Kontrak</option>
                                <option value="training">Training</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-4" id="status_work_dateBody">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Tanggal Selesai</label>
                            <input type="text" name="status_work_expiration" autocomplete="off" placeholder="Tanggal" class="form-control mdate">
                        </div>
                    </div>                    

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Hak Akses</label>
                            <select class="form-control" required="" name="access">
                                <option value="2">Karyawan</option>
                                <option value="7">Supervisor</option>
                                <option value="6">Admin Cabang</option>
                                <option value="1">Superadmin</option>
                            </select>
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
<?php } ?>

<form id="formUpdate">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" name="branch_id" value="<?= $branch_id ?>">
<input type="hidden" name="id_employee" id="e_id">
<div id="modalEdit" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Ubah Karyawan</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">                                                            
                    <div class="col-md-5">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-email-input">ID Fingerprint</label>
                            <input type="text" required="" autocomplete="off" placeholder="ID Fingerprint" class="form-control" name="employee_code" id="e_code">
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Nama</label>
                            <input type="text" required="" autocomplete="off" placeholder="Nama karyawan" class="form-control" name="first_name" id="e_name">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">NIK</label>
                            <input type="text" id="contract_number" required="" autocomplete="off" placeholder="NIK" class="form-control" name="contract_number">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">No KK</label>
                            <input type="text" id="npwp_number" autocomplete="off" placeholder="No KK" class="form-control" name="npwp_number">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Status PTKP</label>
                            <select id="ptkp_status" class="form-control" required name="ptkp_status">
                                <option value="">Pilih</option>
                                <?php foreach (PTKP_STATUS as $row) { ?>
                                    <option value="<?= $row ?>"><?= $row ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Nama Bank</label>
                            <input type="text" id="account_bank" required="" autocomplete="off" placeholder="Nama Bank" class="form-control" name="account_bank">
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Nomor Rekening</label>
                            <input type="text" id="account_number" required="" autocomplete="off" placeholder="Nomor Rekening" class="form-control" name="account_number">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Pemilik Rekening</label>
                            <input type="text" required="" id="account_name" autocomplete="off" placeholder="Nama Pemilik" class="form-control" name="account_name">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Tanggal Mulai Kerja</label>
                            <input type="text" required="" name="join_date" autocomplete="off" placeholder="Tanggal" class="form-control mdate" id="e_join_date">
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label">Jabatan</label>
                            <select class="form-control" required="" name="position_id" id="e_position">
                                <option value="">Pilih</option>
                                <?php foreach ($position as $row) { ?>
                                    <option value="<?= $row['id'] ?>"><?= $row['position_code']." / ".$row['position_name'] ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label">Sub Departement</label>
                            <select class="form-control" required="" id="e_subdivision" name="subdivision_id">
                                <option value="">Pilih</option>
                                <?php foreach ($subdivision as $row) { ?>
                                    <option value="<?= $row['id'] ?>"><?= $row['subdivision_code']." / ".$row['subdivision_name'] ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Gaji Pokok</label>
                            <input type="text" required="" id="salary" name="salary" autocomplete="off" placeholder="Rp. 0" class="form-control rupiah">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Gaji Minimum</label>
                            <input type="text" required="" id="salary_minimum" name="salary_minimum" autocomplete="off" placeholder="Rp. 0" class="form-control rupiah">
                            <small>*Isi 0 jika tidak ada</small>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Upah Lembur / Jam</label>
                            <input type="text" required="" id="overtime_hour_rate" name="overtime_hour_rate" autocomplete="off" placeholder="Rp. 0" class="form-control rupiah">
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Foto</label>
                            <input type="file" name="photo" class="form-control">
                            <small>*Upload gambar, jika ingin mengubah</small>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Nomor Handphone</label>
                            <input type="text" class="form-control" required="" placeholder="Masukkan Nomor handpone..." name="phone" id="e_phone">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Email</label>
                            <input type="email" class="form-control" required="" placeholder="Masukkan email..." name="email" id="e_email">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Password</label>
                            <input type="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah" name="password" id="e_password" autocomplete="new-password">
                            <small>*Ketik password baru hanya jika ingin mengubah. Jangan biarkan browser autofill.</small>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Alamat Lengkap</label>
                            <input type="text" class="form-control" required="" placeholder="Ketik alamat lengkap disini..." name="employee_address" id="e_address">
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label">Lokasi</label>
                            <input type="text" class="form-control" placeholder="Contoh: HOUSEWARE GAMBIR" name="location" id="e_location">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Status Kerja</label>
                            <select class="form-control" required="" name="status_work" id="e_status_work">
                                <option value="permanent">Tetap</option>
                                <option value="contract">Kontrak</option>
                                <option value="training">Training</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-4" id="e_status_work_dateBody">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Tanggal Selesai</label>
                            <input type="text" id="e_status_work_date" name="status_work_expiration" autocomplete="off" placeholder="Tanggal" class="form-control mdate">
                        </div>
                    </div> 

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label" for="formrow-password-input">Hak Akses</label>
                            <select class="form-control" required="" id="e_access" name="access">
                                <option value="2">Karyawan</option>
                                <option value="7">Supervisor</option>
                                <option value="6">Admin Cabang</option>

                                <?php if($role == 'admin'){ ?>
                                    <option value="1">Superadmin</option>
                                <?php } ?>
                                
                            </select>
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
                <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">Hapus Karyawan</h5>
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
                        Data yang telah dihapus tidak dapat dikembalikan lagi dan semua data yang berhubungan dengan karyawan ini juga akan terhapus
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

<form id="formShift">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" id="user_id" name="user_id">
<div id="modalShift" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-calendar"></i> Ganti Jadwal</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <label>Jadwal Cluster</label>
                        <select class="form-control" name="shift_cluster_id" id="cluster" required="">
                            <option value="required">Pilih</option>
                            <?php foreach ($cluster as $row){ ?>
                                <option value="<?= $row['id'] ?>"><?= $row['cluster_code']." / ".$row['cluster_name'] ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Tanggal Berlaku</label>
                        <input type="text" class="form-control mdate" id="applies" required="" name="shift_applies" placeholder="Tanggal...">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mt-2">
                        <button class="btn btn-warning mt-2" id="btnShift">Ubah</button>
                    </div>
                </div>
                
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
</form>


<div class="modal fade" id="modalUpload" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="staticBackdropLabel" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staticBackdropLabel">Upload Karyawan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
               
               <form id="formUpload" method="POST">
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">

                    <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
                    <div class="row">
                        <div class="col-md-3">
                            <center>
                                <img style="width: 70px" src="<?= base_url('assets/images/icon/team.png') ?>">
                            </center>
                        </div>
                        <div class="col-md-9">
                            <h6>File Excel</h6>
                            <input type="file" required="" name="excel_file"> <br>
                            <small class="text-muted">Silahkan upload file excel yang sudah didownload dari sistem</small> <br><br>
                            <button class="btn btn-success" id="btnUpload">Upload Karyawan</button> &nbsp;
                            <a href="<?= base_url('assets/Template_Tambah_Karyawan.xlsx') ?>" class="btn btn-outline-danger"><i class="fa fa-download"></i> Download Template</a>
                        </div>
                    </div>
                </form>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php $this->datatables->jquery('tableContent'); ?>

<script type="text/javascript">

$('#e_status_work_dateBody').hide();
$('#status_work_dateBody').hide();

$(document).on('change', '#status_work', function(){
    if($(this).val() == 'permanent'){
        $('#status_work_dateBody').hide();
    }else{
        $('#status_work_dateBody').show();
    }
})

$(document).on('change', '#e_status_work', function(){
    if($(this).val() == 'permanent'){
        $('#e_status_work_dateBody').hide();
    }else{
        $('#e_status_work_dateBody').show();
    }
})

<?php if($role == 'admin'){ ?>
    $(document).on('submit', '#formAdd', function(e){
        e.preventDefault();
        var status  = true;
        var btn     = $('#btnSave');
        var message = "";

        var formData = new FormData(this);

        $.ajax({
            url         : "<?= site_url('insert_employee') ?>",
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
<?php } ?>

    $(document).on('submit', '#formUpdate', function(e){
        e.preventDefault();
        var status  = true;
        var btn     = $('#btnUpdate');
        var message = "";

        var formData = new FormData(this);

        $.ajax({
            url         : "<?= site_url('update_employee') ?>",
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
            url         : "<?= site_url('delete_employee') ?>",
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
        $('#e_email').val(a.attr('data-email'));
        $('#e_join_date').val(a.attr('data-join-date'));
        $('#e_address').val(a.attr('data-address'));
        $('#e_location').val(a.attr('data-location'));
        $('#e_phone').val(a.attr('data-phone'));
        $('#salary').val(format_rp(a.attr('data-salary')));
        $('#salary_minimum').val(format_rp(a.attr('data-salary-minimum')));
        $('#contract_number').val(a.attr('data-contract-number'));
        $('#e_status_work_date').val(a.attr('data-status-work-date'));
        $('#overtime_hour_rate').val(format_rp(a.data('overtime-hour-rate')));
        $('#npwp_number').val(a.attr('data-npwp-number'));

        $('#account_number').val(a.attr('data-account-number'));
        $('#account_name').val(a.attr('data-account-name'));
        $('#account_bank').val(a.attr('data-account-bank'));

        $('#e_position option').removeAttr('selected');
        $('#e_position option[value="'+ a.attr('data-position') +'"]').attr('selected', 'selected');

        $('#e_subdivision option').removeAttr('selected');
        $('#e_subdivision option[value="'+ a.attr('data-subdivision') +'"]').attr('selected', 'selected');

        $('#e_access option').removeAttr('selected');
        $('#e_access option[value="'+ a.attr('data-access') +'"]').attr('selected', 'selected');

        $('#e_status_work option').removeAttr('selected');
        $('#e_status_work option[value="'+ a.attr('data-status-work') +'"]').attr('selected', 'selected');

        if(a.attr('data-status-work') == 'permanent'){
            $('#e_status_work_dateBody').hide();
        }else{
            $('#e_status_work_dateBody').show();
        }

        $('#ptkp_status option').removeAttr('selected');
        $('#ptkp_status option[value="'+ a.attr('data-ptkp-status') +'"]').attr('selected', 'selected');

        $('#e_id').val(id);
        $('#modalEdit').modal('show');
    });


    $(document).on('click', '.shift', function(){
        var a = $(this);
        var id         = a.attr('data-id');
        var cluster_id = a.attr('data-cluster-id');

        $('#cluster option').removeAttr('selected');
        $('#cluster option[value="'+ cluster_id +'"]').attr('selected', 'selected');
        $('#applies').val(a.attr('data-applies'));

        $('#user_id').val(id);
        $('#modalShift').modal('show');
    });

    $(document).on('submit', '#formShift', function(e){
        e.preventDefault();
        var status  = true;
        var btn     = $('#btnShift');
        var message = "";

        var formData = new FormData(this);

        $.ajax({
            url         : "<?= site_url('update_employee_cluster') ?>",
            dataType    : "json",
            method      : "POST",
            data        : formData,
            processData : false,
            contentType : false,
            beforeSend  : function(){
                btn.html(show_loading()).attr('disabled', 'disabled');
            },
            success : function(res){
                $('#modalShift').modal('hide');
                if(res.status){
                    $('#formShift')[0].reset();
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
                    erTable_tableContent.ajax.reload(null, false);
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


    $(document).on('submit', '#formUpload', function(e){
        e.preventDefault();
        var status  = true;
        var btn     = $('#btnUpload');
        var message = "";

        var formData = new FormData(this);

        $.ajax({
            url         : "<?= site_url('upload_employee') ?>",
            dataType    : "json",
            method      : "POST",
            data        : formData,
            processData : false,
            contentType : false,
            beforeSend  : function(){
                btn.html(show_loading()).attr('disabled', 'disabled');
            },
            success : function(res){
                $('#modalUpload').modal('hide');

                var type = (res.status) ? 'success' : 'info';
                show_modal(type, res.message);

                if(res.status){
                    erTable_tableContent.ajax.reload(null, false);
                }
            },
            complete : function(){
                btn.html('Upload Karyawan').removeAttr('disabled');
            }
        });

        return false;
    });
</script>

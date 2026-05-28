<?php $role   = $this->ion_auth->get_users_groups()->row()->name; ?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0">Quick Menu</h4>

        </div>
    </div>
</div>

<div class="row">
    <?php if($role == 'supervisor'){ ?>
        <div class="col-sm-6 col-md-6 col-xl-3">
            <a href="<?= site_url('hr/overtime/list') ?>">
                <div class="card">
                    <div class="card-body">
                        <div class="text-center">
                            <img style="width: 60px" src="<?= base_url('assets/images/icon/times.png') ?>" class="img-fluid">
                            <p class="mb-0 mt-2" style="color:#333">Pengajuan Lembur</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    <?php } ?>

    <div class="col-sm-6 col-md-6 col-xl-3">
        <a href="<?= site_url('hr/leave/list') ?>">
            <div class="card">
                <div class="card-body">
                    <div class="text-center">
                        <img style="width: 60px" src="<?= base_url('assets/images/icon/finishing.png') ?>" class="img-fluid">
                        <p class="mb-0 mt-2" style="color:#333">Pengajuan Izin</p>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-md-6 col-xl-3">
        <a href="<?= site_url('hr/presence') ?>">
            <div class="card">
                <div class="card-body">
                    <div class="text-center">
                        <img style="width: 60px" src="<?= base_url('assets/images/icon/schedule.png') ?>" class="img-fluid">
                        <p class="mb-0 mt-2" style="color:#333">Presensi</p>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-sm-6 col-md-6 col-xl-3">
        <a href="<?= site_url('hr/payroll') ?>">
            <div class="card">
                <div class="card-body">
                    <div class="text-center">
                        <img style="width: 60px" src="<?= base_url('assets/images/icon/income.png') ?>" class="img-fluid">
                        <p class="mb-0 mt-2" style="color:#333">Penggajian</p>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>
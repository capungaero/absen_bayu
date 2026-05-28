<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="dripicons-calendar"></i> Konfirmasi Pengajuan Izin</h4>

            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Izin</a></li>
                    <li class="breadcrumb-item active"><a href="<?= site_url('hr/leave/acc') ?>">Daftar Konfirmasi</a></li>
                </ol>
            </div>

        </div>
    </div>
</div>
<!-- end page title -->



<div class="row">
    <div class="col-md-12">
        <?= $this->session->flashdata('alert_message') ?>
        <div class="card">
            <div class="card-header">
                <h6 class="card-title">Daftar Pengajuan</h6>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#home1" role="tab">
                            <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                            <span class="d-none d-sm-block"><img src="<?= base_url('assets/images/icon/times.png') ?>" style="width: 20px"> &nbsp; Menunggu Konfirmasi</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#profile1" role="tab">
                            <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                            <span class="d-none d-sm-block"><img src="<?= base_url('assets/images/icon/megaphone.png') ?>" style="width: 20px"> &nbsp; Sudah Konfirmasi</span>
                        </a>
                    </li>
                </ul>

                <!-- Tab panes -->
                <div class="tab-content p-3 text-muted">
                    <div class="tab-pane active" id="home1" role="tabpanel">
                        <p class="mb-0">
                            <div class="table-responsive">
                                <?php $this->datatables->generate('tablePending'); ?>
                            </div>
                        </p>
                    </div>
                    <div class="tab-pane" id="profile1" role="tabpanel">
                        <p class="mb-0">
                            <?php $this->datatables->generate('tableUnPending'); ?>
                        </p>
                    </div>
                </div>
                
            </div>
        </div>
        
    </div>
</div>


<?php $this->datatables->jquery('tablePending'); ?>
<?php $this->datatables->jquery('tableUnPending'); ?>
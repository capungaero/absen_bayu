
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="dripicons-gear"></i>Daftar Request Transaksi</h4>

            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Dashboard</a></li>
                    <li class="breadcrumb-item active"><a href="javascript:void(0)">Request</a></li>
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
                            <span class="d-none d-sm-block">
                            Pemasukan</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#profile1" role="tab">
                            <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                            <span class="d-none d-sm-block">Pengeluaran</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#profile2" role="tab">
                            <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                            <span class="d-none d-sm-block">Pengembalian Dana</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#overtime" role="tab">
                            <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                            <span class="d-none d-sm-block">Lembur</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#damaged" role="tab">
                            <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                            <span class="d-none d-sm-block">Kerusakan Aset</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#fixed" role="tab">
                            <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                            <span class="d-none d-sm-block">Perbaikan Aset</span>
                        </a>
                    </li>
                </ul>

                <!-- Tab panes -->
                <div class="tab-content p-3 text-muted">
                    <div class="tab-pane active" id="home1" role="tabpanel">
                        <p class="mb-0">
                            <div class="table-responsive">
                                <?php $this->datatables->generate('incomePending'); ?>
                            </div>
                        </p>
                    </div>
                    <div class="tab-pane" id="profile1" role="tabpanel">
                        <p class="mb-0">
                            <?php $this->datatables->generate('outcomePending'); ?>
                        </p>
                    </div>
                    <div class="tab-pane" id="profile2" role="tabpanel">
                        <p class="mb-0">
                            <?php $this->datatables->generate('returnedPending'); ?>
                        </p>
                    </div>

                    <div class="tab-pane" id="damaged" role="tabpanel">
                        <p class="mb-0">
                            <?php $this->datatables->generate('damagedPending'); ?>
                        </p>
                    </div>

                    <div class="tab-pane" id="fixed" role="tabpanel">
                        <p class="mb-0">
                            <?php $this->datatables->generate('fixedPending'); ?>
                        </p>
                    </div>

                    <div class="tab-pane" id="overtime" role="tabpanel">
                        <p class="mb-0">
                            <?php $this->datatables->generate('overtimePending'); ?>
                        </p>
                    </div>

                </div>
                
            </div>
        </div>
        
    </div>
</div>


<?php $this->datatables->jquery('incomePending'); ?>
<?php $this->datatables->jquery('outcomePending'); ?>
<?php $this->datatables->jquery('returnedPending'); ?>
<?php $this->datatables->jquery('damagedPending'); ?>
<?php $this->datatables->jquery('fixedPending'); ?>
<?php $this->datatables->jquery('overtimePending'); ?>
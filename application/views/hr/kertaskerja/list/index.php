<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="mdi mdi-checkbox-marked-outline"></i> Kertas Kerja</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Kertas Kerja</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="card-title mb-0">Daftar Kertas Kerja Mitra Kerja</h6>
                <a href="<?= site_url('hr/kertas_kerja/rekap') ?>" class="btn btn-sm btn-outline-primary">
                    <i class="mdi mdi-clipboard-check-outline"></i> Rekap per Karyawan
                </a>
            </div>
            <div class="card-body">

                <?php if ($role === 'admin'): ?>
                <form method="get" action="<?= site_url('hr/kertas_kerja') ?>" class="row mb-3">
                    <div class="col-md-4">
                        <label>Cabang</label>
                        <select class="form-control select-plugin" name="branch_id" onchange="this.form.submit()">
                            <option value="">Semua Cabang</option>
                            <?php foreach ($branch as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>><?= $b['branch_code']." / ".$b['branch_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                <?php endif; ?>

                <div class="table-responsive">
                    <?php $this->datatables->generate('tableContent'); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->datatables->jquery('tableContent'); ?>

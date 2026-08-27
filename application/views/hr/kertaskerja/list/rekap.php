<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="mdi mdi-clipboard-check-outline"></i> Rekap Kertas Kerja per Karyawan</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= site_url('hr/kertas_kerja') ?>">Kertas Kerja</a></li>
                    <li class="breadcrumb-item active">Rekap</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h3 class="mb-0"><?= count($rows) ?></h3>
                <p class="text-muted mb-0">Wajib Isi</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h3 class="mb-0 text-success"><?= $total_sudah ?></h3>
                <p class="text-muted mb-0">Sudah Membuat</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h3 class="mb-0 text-danger"><?= $total_belum ?></h3>
                <p class="text-muted mb-0">Belum Membuat</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Daftar Karyawan Wajib Kertas Kerja — <?= indonesian_date($date) ?></h6>
            </div>
            <div class="card-body">
                <form method="get" action="<?= site_url('hr/kertas_kerja/rekap') ?>" class="row g-2 mb-3 align-items-end">
                    <div class="col-md-3">
                        <label>Tanggal</label>
                        <input type="date" name="date" class="form-control" value="<?= $date ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
                    </div>
                    <?php if ($role === 'admin'): ?>
                    <div class="col-md-3">
                        <label>Cabang</label>
                        <select class="form-control select-plugin" name="branch_id" onchange="this.form.submit()">
                            <option value="">Semua Cabang</option>
                            <?php foreach ($branch as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>><?= $b['branch_code']." / ".$b['branch_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Mitra Kerja</th>
                                <th>Cabang</th>
                                <th>Status</th>
                                <th>Bukti Foto</th>
                                <th>Waktu Isi</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr><td colspan="7" class="text-center text-muted">Belum ada karyawan yang di-flag wajib Kertas Kerja.</td></tr>
                            <?php else: foreach ($rows as $i => $r): $sudah = !empty($r['kk_id']); ?>
                                <tr class="<?= $sudah ? '' : 'table-light' ?>">
                                    <td><?= $i + 1 ?></td>
                                    <td>
                                        <?= htmlspecialchars($r['first_name']) ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($r['employee_code']) ?> &middot; <?= htmlspecialchars($r['position_name']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($r['branch_name']) ?></td>
                                    <td>
                                        <?php if (!$sudah): ?>
                                            <span class="badge bg-danger">Belum Membuat</span>
                                        <?php elseif ($r['status'] === 'read'): ?>
                                            <span class="badge bg-success">Sudah Dibaca</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Sudah Isi, Belum Dibaca</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($sudah && !empty($r['photo_path'])): ?>
                                            <a href="<?= base_url('assets/images/kertas_kerja/'.$r['photo_path']) ?>" target="_blank">
                                                <img src="<?= base_url('assets/images/kertas_kerja/'.$r['photo_path']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px" alt="foto">
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $sudah ? indonesian_date($r['created_at'], true) : '-' ?></td>
                                    <td>
                                        <?php if ($sudah): ?>
                                            <a class="btn btn-sm btn-primary" href="<?= site_url('hr/kertas_kerja/detail/'.$r['kk_id']) ?>"><i class="fa fa-search"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

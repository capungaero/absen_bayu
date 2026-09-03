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
                    <?php if (in_array($role, ['admin', 'kk-admin'])): ?>
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
                                <th>Slot 1<?php /* label diganti Pagi/Sore per baris kalau count>1 */ ?></th>
                                <th>Slot 2</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr><td colspan="5" class="text-center text-muted">Belum ada karyawan yang di-flag wajib Kertas Kerja.</td></tr>
                            <?php else: foreach ($rows as $i => $r): $count = (int)$r['kertas_kerja_count']; ?>
                                <tr class="<?= $r['done_count'] >= $count ? '' : 'table-light' ?>">
                                    <td><?= $i + 1 ?></td>
                                    <td>
                                        <?= htmlspecialchars($r['first_name']) ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($r['employee_code']) ?> &middot; <?= htmlspecialchars($r['position_name']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($r['branch_name']) ?></td>
                                    <?php for ($slot = 1; $slot <= 2; $slot++): ?>
                                        <td>
                                            <?php if ($slot > $count): ?>
                                                <span class="text-muted">&mdash;</span>
                                            <?php else: $s = $r['slots'][$slot]; ?>
                                                <?php if (empty($s)): ?>
                                                    <span class="badge bg-danger">Belum</span>
                                                <?php else: ?>
                                                    <?php if ($s['submission_type'] === 'teks'): ?>
                                                        <a href="<?= site_url('hr/kertas_kerja/detail/'.$s['id']) ?>" class="badge bg-secondary" title="<?= htmlspecialchars($s['notes']) ?>">TEKS</a>
                                                    <?php else: ?>
                                                        <a href="<?= site_url('hr/kertas_kerja/detail/'.$s['id']) ?>">
                                                            <img src="<?= base_url('assets/images/kertas_kerja/'.$s['photo_path']) ?>" style="width:32px;height:32px;object-fit:cover;border-radius:4px" alt="foto">
                                                        </a>
                                                    <?php endif; ?>
                                                    <span class="badge <?= $s['status'] === 'read' ? 'bg-success' : 'bg-warning' ?>"><?= $s['status'] === 'read' ? 'Dibaca' : 'Baru' ?></span>
                                                    <?php if (!empty($s['uploaded_by_name'])): ?>
                                                        <br><small class="text-muted"><i class="fa fa-user-friends"></i> oleh <?= htmlspecialchars($s['uploaded_by_name']) ?></small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

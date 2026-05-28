<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0 font-size-18"><i class="mdi mdi-fingerprint me-2"></i>Report Absen Mesin</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Report Absen Mesin</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body py-3">
                <form method="get" class="d-flex flex-wrap align-items-center gap-2" id="machineReportFilter">
                    <label class="mb-0 fw-semibold me-1">Dari:</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                        value="<?= htmlspecialchars($date_from) ?>" style="width:160px">

                    <label class="mb-0 fw-semibold me-1">Sampai:</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                        value="<?= htmlspecialchars($date_to) ?>" style="width:160px">

                    <?php if ($is_admin): ?>
                    <label class="mb-0 fw-semibold me-1">Lokasi:</label>
                    <select name="branch_id" class="form-select form-select-sm" style="width:190px"
                            onchange="document.getElementById('machineReportFilter').submit()">
                        <option value="">Semua Cabang</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>"
                            <?= ((int)$branch_id === (int)$b['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['branch_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>

                    <label class="mb-0 fw-semibold me-1">Mesin:</label>
                    <select name="machine_sn" class="form-select form-select-sm" style="width:220px">
                        <option value="">Semua Mesin</option>
                        <?php foreach ($machines as $m): ?>
                        <option value="<?= htmlspecialchars($m['machine_sn']) ?>"
                            <?= $machine_sn === $m['machine_sn'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['machine_sn']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="mdi mdi-magnify me-1"></i>Tampilkan
                    </button>

                    <?php if ($shift_schedule_exists): ?>
                    <span class="d-inline-flex align-items-center gap-1 ms-2">
                        <i class="mdi mdi-circle text-primary font-size-14" title="Jadwal shift tersedia"></i>
                        <small class="text-primary fw-semibold">
                            Jadwal shift tersedia (<?= number_format($shift_schedule_count) ?> entri)
                        </small>
                    </span>
                    <?php else: ?>
                    <span class="d-inline-flex align-items-center gap-1 ms-2">
                        <i class="mdi mdi-circle text-danger font-size-14" title="Jadwal shift belum ada"></i>
                        <small class="text-danger fw-semibold">
                            Jadwal shift belum ada untuk periode ini
                        </small>
                    </span>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Tabel -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">
                    <i class="mdi mdi-table me-1"></i>
                    Log Tap Mesin
                    <span class="badge bg-secondary ms-2"><?= count($logs) ?> record</span>
                </h5>
                <small class="text-muted">
                    Periode: <?= date('d/m/Y', strtotime($date_from)) ?>
                    <?php if ($date_from !== $date_to): ?>
                        &ndash; <?= date('d/m/Y', strtotime($date_to)) ?>
                    <?php endif; ?>
                </small>
            </div>
            <div class="card-body p-0">
                <?php if (empty($logs)): ?>
                <div class="text-center text-muted py-5">
                    <i class="mdi mdi-database-off font-size-48 d-block mb-2"></i>
                    Tidak ada data log untuk filter yang dipilih.
                    <br><small>Pastikan file .dat sudah tersimpan di folder uploads/attendance.</small>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table id="tblMachineReport" class="table table-sm table-hover table-bordered mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center" style="width:45px">#</th>
                                <th>ID Mesin</th>
                                <th>Nama Mesin</th>
                                <th>ID Karyawan</th>
                                <th>Nama Karyawan</th>
                                <th>Jam Login</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $i => $log): ?>
                            <tr>
                                <td class="text-center text-muted"><?= $i + 1 ?></td>
                                <td><code><?= htmlspecialchars($log['machine_sn']) ?></code></td>
                                <td><?= htmlspecialchars($log['machine_name']) ?></td>
                                <td><code><?= htmlspecialchars($log['employee_code']) ?></code></td>
                                <td><?= htmlspecialchars($log['employee_name']) ?></td>
                                <td>
                                    <span class="badge bg-info text-dark">
                                        <?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($log['tap_datetime']))) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($log['keterangan'])): ?>
                                    <?php
                                        $badgeClass = 'bg-secondary';
                                        if ($log['status'] === 'ontime')      $badgeClass = 'bg-success';
                                        elseif ($log['status'] === 'late')    $badgeClass = 'bg-danger';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($log['keterangan']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($logs)): ?>
<script>
$(function () {
    $('#tblMachineReport').DataTable({
        pageLength: 25,
        order: [[5, 'asc']],
        language: {
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
        },
        responsive: true
    });
});
</script>
<?php endif; ?>

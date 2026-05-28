<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0 font-size-18"><i class="mdi mdi-account-check me-2"></i>Data Kehadiran Berdasarkan Shift</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Data Kehadiran</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <form method="get" class="d-flex flex-wrap align-items-center gap-2" id="attendanceFilter">
            <select name="mode" id="filterMode" class="form-select form-select-sm" style="width:auto">
                <option value="date" <?= $filter_mode == 'date' ? 'selected' : '' ?>>Tanggal</option>
                <option value="week" <?= $filter_mode == 'week' ? 'selected' : '' ?>>Minggu</option>
            </select>

            <?php if (!empty($is_admin)): ?>
            <select name="branch_id" class="form-select form-select-sm" style="width:auto">
                <option value="">Semua Cabang</option>
                <?php foreach ($branches as $branch): ?>
                <option value="<?= $branch['id'] ?>" <?= (string)$branch_id === (string)$branch['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($branch['branch_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <input type="date" name="date" id="dateFilter" class="form-control form-control-sm" value="<?= htmlspecialchars($date_value) ?>" style="width:160px">
            <input type="week" name="week" id="weekFilter" class="form-control form-control-sm" value="<?= htmlspecialchars($week_value) ?>" style="width:160px">
            <button type="submit" class="btn btn-primary btn-sm"><i class="mdi mdi-magnify me-1"></i>Cari</button>
            <small class="text-muted ms-sm-2">Periode: <?= date('d/m/Y', strtotime($from_date)) ?> - <?= date('d/m/Y', strtotime($to_date)) ?></small>
        </form>
    </div>
</div>

<?php if (empty($shifts)): ?>
<div class="card">
    <div class="card-body text-center text-muted py-5">
        <i class="mdi mdi-account-off font-size-48 d-block mb-2"></i>
        Tidak ada data kehadiran untuk periode ini.
    </div>
</div>
<?php else: ?>

<?php 
$shift_data = [];
foreach ($shifts as $row) {
    if (!isset($shift_data[$row['id']])) {
        $shift_data[$row['id']] = [];
    }
    $shift_data[$row['id']][] = $row;
}
?>

<?php foreach ($shift_data as $shift_id => $employees): ?>
<?php $shift = $employees[0]; ?>
<div class="card mb-3">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="mdi mdi-clock me-1"></i>
            <strong><?= htmlspecialchars($shift['shift_name']) ?></strong>
            <span class="badge bg-info"><?= $shift['time_in'] ?> - <?= $shift['time_out'] ?></span>
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Kode</th>
                        <th>Nama Karyawan</th>
                        <th>Posisi</th>
                        <th>Cabang</th>
                        <th class="text-center">
                            <span class="badge bg-dark">Jadwal</span>
                        </th>
                        <th class="text-center">
                            <span class="badge bg-success">Hadir</span>
                        </th>
                        <th class="text-center">
                            <span class="badge bg-warning">Terlambat</span>
                        </th>
                        <th class="text-center">
                            <span class="badge bg-info">Sakit</span>
                        </th>
                        <th class="text-center">
                            <span class="badge bg-secondary">Izin</span>
                        </th>
                        <th class="text-center">
                            <span class="badge bg-danger">Alpha</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($emp['employee_code']) ?></code></td>
                        <td><strong><?= htmlspecialchars($emp['name']) ?></strong></td>
                        <td><?= htmlspecialchars($emp['position_name'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($emp['branch_name'] ?: '-') ?></td>
                        <td class="text-center">
                            <span class="badge bg-dark"><?= $emp['total_days'] ?: 0 ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success"><?= $emp['present'] ?: 0 ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-warning"><?= $emp['late'] ?: 0 ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info"><?= $emp['sick'] ?: 0 ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary"><?= $emp['permit'] ?: 0 ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-danger"><?= $emp['absent'] ?: 0 ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<script>
(function(){
    var mode = document.getElementById('filterMode');
    var dateFilter = document.getElementById('dateFilter');
    var weekFilter = document.getElementById('weekFilter');

    function toggleFilter(){
        var isWeek = mode.value === 'week';
        dateFilter.style.display = isWeek ? 'none' : '';
        weekFilter.style.display = isWeek ? '' : 'none';
    }

    mode.addEventListener('change', toggleFilter);
    toggleFilter();
})();
</script>

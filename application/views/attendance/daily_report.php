<?php
function daily_report_time($value) {
    return !empty($value) ? date('H:i', strtotime($value)) : '-';
}

function daily_report_pray($row, $key) {
    $in = daily_report_time($row[$key.'_time_in']);
    $out = daily_report_time($row[$key.'_time_out']);
    $late = isset($row[$key.'_time_late']) ? (int) $row[$key.'_time_late'] : 0;

    if ($in === '-' && $out === '-') {
        return '<span class="text-muted">-</span>';
    }

    $badge = $late > 0
        ? '<span class="badge bg-warning text-dark ms-1">'.$late.' mnt</span>'
        : '<span class="badge bg-success ms-1">OK</span>';

    return '<span class="text-nowrap">'.$in.' - '.$out.'</span>'.$badge;
}

$query_base = $_GET;
?>

<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0 font-size-18"><i class="mdi mdi-calendar-check me-2"></i>Rekap Absensi Harian</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Rekap Absensi Harian</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <form method="get" class="d-flex flex-wrap align-items-center gap-2">
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

            <select name="division_id" class="form-select form-select-sm" style="width:auto">
                <option value="">Semua Divisi</option>
                <?php foreach ($divisions as $division): ?>
                <option value="<?= $division['id'] ?>" <?= (string)$division_id === (string)$division['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($division['subdivision_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($date_from) ?>" style="width:160px">
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($date_to) ?>" style="width:160px">
            <input type="text" name="q" class="form-control form-control-sm" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama / ID" style="width:190px">
            <button type="submit" class="btn btn-primary btn-sm"><i class="mdi mdi-magnify me-1"></i>Cari</button>
            <a href="<?= site_url('attendance/daily_report') ?>" class="btn btn-light btn-sm"><i class="mdi mdi-refresh me-1"></i>Reset</a>
        </form>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3 col-6 mb-2">
        <div class="card mb-0"><div class="card-body py-2">
            <small class="text-muted">Total Catatan</small>
            <h5 class="mb-0"><?= (int) $summary['total'] ?></h5>
        </div></div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="card mb-0"><div class="card-body py-2">
            <small class="text-muted">Hadir</small>
            <h5 class="mb-0 text-success"><?= (int) $summary['present'] ?></h5>
        </div></div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="card mb-0"><div class="card-body py-2">
            <small class="text-muted">Terlambat</small>
            <h5 class="mb-0 text-warning"><?= (int) $summary['late'] ?></h5>
        </div></div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="card mb-0"><div class="card-body py-2">
            <small class="text-muted">Ada Istirahat</small>
            <h5 class="mb-0 text-info"><?= (int) $summary['rest_count'] ?></h5>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Detail Harian</h5>
        <small class="text-muted">
            <?= date('d/m/Y', strtotime($date_from)) ?> - <?= date('d/m/Y', strtotime($date_to)) ?>,
            halaman <?= (int) $page ?> / <?= (int) $total_pages ?>
        </small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>ID</th>
                        <th>Nama Karyawan</th>
                        <th>Cabang</th>
                        <th>Divisi</th>
                        <th>Kedatangan</th>
                        <th class="text-center">Terlambat</th>
                        <th>Mulai Istirahat</th>
                        <th>Login Setelah Istirahat</th>
                        <th>Pulang</th>
                        <th>Sholat Dzuhur</th>
                        <th>Sholat Ashar</th>
                        <th>Sholat Maghrib</th>
                        <th>Sholat Isya</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="14" class="text-center text-muted py-4">Tidak ada data report untuk filter ini.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="text-nowrap"><?= date('d/m/Y', strtotime($row['flow_date'])) ?></td>
                        <td><code><?= htmlspecialchars($row['employee_code'] ?: $row['user_id']) ?></code></td>
                        <td>
                            <strong><?= htmlspecialchars($row['employee_name'] ?: '-') ?></strong>
                            <?php if (!empty($row['position_name'])): ?>
                            <div class="text-muted small"><?= htmlspecialchars($row['position_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['branch_name'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($row['division_name'] ?: '-') ?></td>
                        <td class="text-nowrap"><?= daily_report_time($row['entry_time']) ?></td>
                        <td class="text-center">
                            <?php if ((int)$row['entry_time_late'] > 0): ?>
                            <span class="badge bg-warning text-dark"><?= (int)$row['entry_time_late'] ?> menit</span>
                            <?php else: ?>
                            <span class="badge bg-success">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= daily_report_time($row['rest_time_in']) ?></td>
                        <td class="text-nowrap"><?= daily_report_time($row['rest_time_out']) ?></td>
                        <td class="text-nowrap"><?= daily_report_time($row['out_time']) ?></td>
                        <td><?= daily_report_pray($row, 'dzuhur') ?></td>
                        <td><?= daily_report_pray($row, 'ashar') ?></td>
                        <td><?= daily_report_pray($row, 'maghrib') ?></td>
                        <td><?= daily_report_pray($row, 'isha') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <?php
            $prev_query = array_merge($query_base, ['page' => max(1, $page - 1)]);
            $next_query = array_merge($query_base, ['page' => min($total_pages, $page + 1)]);
        ?>
        <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= site_url('attendance/daily_report?'.http_build_query($prev_query)) ?>">
            <i class="mdi mdi-chevron-left"></i> Sebelumnya
        </a>
        <span class="text-muted small">Menampilkan maksimal <?= (int)$per_page ?> baris per halaman dari <?= (int)$total_rows ?> catatan.</span>
        <a class="btn btn-sm btn-light <?= $page >= $total_pages ? 'disabled' : '' ?>" href="<?= site_url('attendance/daily_report?'.http_build_query($next_query)) ?>">
            Berikutnya <i class="mdi mdi-chevron-right"></i>
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="mdi mdi-account-off me-1 text-danger"></i>Tidak Hadir
        </h5>
        <span class="badge bg-danger"><?= count($absent_rows) ?> orang</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>ID</th>
                        <th>Nama Karyawan</th>
                        <th>Cabang</th>
                        <th>Divisi</th>
                        <th>Shift</th>
                        <th>Jam Shift</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($absent_rows)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Tidak ada karyawan terjadwal yang belum terdeteksi mesin absensi.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($absent_rows as $row): ?>
                    <tr>
                        <td class="text-nowrap"><?= date('d/m/Y', strtotime($row['flow_date'])) ?></td>
                        <td><code><?= htmlspecialchars($row['employee_code'] ?: $row['user_id']) ?></code></td>
                        <td>
                            <strong><?= htmlspecialchars($row['employee_name'] ?: '-') ?></strong>
                            <?php if (!empty($row['position_name'])): ?>
                            <div class="text-muted small"><?= htmlspecialchars($row['position_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['branch_name'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($row['division_name'] ?: '-') ?></td>
                        <td>
                            <span class="badge bg-dark"><?= htmlspecialchars($row['shift_code'] ?: '-') ?></span>
                            <?= htmlspecialchars($row['shift_name'] ?: '') ?>
                        </td>
                        <td class="text-nowrap"><?= daily_report_time($row['flow_date'].' '.$row['start_time']) ?> - <?= daily_report_time($row['flow_date'].' '.$row['end_time']) ?></td>
                        <td><span class="badge bg-danger">Tidak terdeteksi mesin</span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

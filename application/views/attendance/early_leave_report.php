<?php
function el_minutes_to_hour_minute($minutes) {
    $minutes = (int) $minutes;
    if ($minutes <= 0) { return '0m'; }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h <= 0) { return $m.'m'; }
    return $m > 0 ? $h.'j '.$m.'m' : $h.'j';
}

function el_time($value) {
    return !empty($value) ? date('H:i', strtotime($value)) : '-';
}

function el_rp($n) {
    return number_format((int) $n, 0, ',', '.');
}

$selected_year = (int) $year;
$selected_month = (int) $month;
?>

<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0 font-size-18"><i class="mdi mdi-clock-alert-outline me-2"></i>Rekap Izin Pulang Cepat (PLA)</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Rekap PLA</li>
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

            <select name="month" class="form-select form-select-sm" style="width:auto">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $selected_month === $m ? 'selected' : '' ?>>
                    <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                </option>
                <?php endfor; ?>
            </select>

            <select name="year" class="form-select form-select-sm" style="width:auto">
                <?php $current_year = (int) date('Y'); for ($y = $current_year - 2; $y <= $current_year + 1; $y++): ?>
                <option value="<?= $y ?>" <?= $selected_year === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>

            <button type="submit" class="btn btn-primary btn-sm"><i class="mdi mdi-magnify me-1"></i>Tampilkan</button>
            <a href="<?= site_url('attendance/early_leave_report') ?>" class="btn btn-light btn-sm"><i class="mdi mdi-refresh me-1"></i>Reset</a>
        </form>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3 col-6 mb-2">
        <div class="card mb-0"><div class="card-body py-2">
            <small class="text-muted">Periode Payroll</small>
            <h6 class="mb-0"><?= htmlspecialchars($period['from']) ?> <small class="text-muted">s/d</small> <?= htmlspecialchars($period['to']) ?></h6>
            <small class="text-muted">Hari dlm bulan: <?= (int) $days_in_month ?></small>
        </div></div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="card mb-0"><div class="card-body py-2">
            <small class="text-muted">Karyawan PLA</small>
            <h5 class="mb-0"><?= (int) $report['total_users'] ?></h5>
        </div></div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="card mb-0"><div class="card-body py-2">
            <small class="text-muted">Total Kekurangan</small>
            <h5 class="mb-0"><?= el_minutes_to_hour_minute($report['total_short_minutes']) ?></h5>
            <small class="text-muted"><?= (int) $report['total_short_minutes'] ?> menit</small>
        </div></div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="card mb-0"><div class="card-body py-2">
            <small class="text-muted">Total Potongan</small>
            <h5 class="mb-0 text-danger">Rp <?= el_rp($report['total_amount']) ?></h5>
        </div></div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <small class="text-muted">
                    Rumus per karyawan:
                    <code>(Gaji Pokok &divide; <?= (int) $days_in_month ?> hari &divide; 10 jam)</code> &times;
                    <code>(Kekurangan menit &divide; 60)</code>.
                    Hanya hari dengan flag <span class="badge bg-primary">PLA</span> dan status approved yang dihitung.
                    Hari dengan jam kerja efektif &lt; 5 jam ditandai sebagai TIDAK HADIR di sumber data dan tidak masuk perhitungan ini.
                </small>

                <?php if (empty($report['per_user'])): ?>
                <div class="text-center py-5">
                    <i class="mdi mdi-clock-check-outline" style="font-size:42px; color:#6c757d"></i>
                    <h5 class="mt-2">Tidak ada PLA</h5>
                    <p class="text-muted">Belum ada karyawan dengan flag Izin Pulang Cepat di periode payroll yang dipilih.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:32px">#</th>
                                <th>Karyawan</th>
                                <th>Cabang / Divisi</th>
                                <th>Rincian Tanggal PLA</th>
                                <th class="text-end">Kekurangan</th>
                                <th class="text-end">Gaji / jam</th>
                                <th class="text-end">Potongan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 0; foreach ($report['per_user'] as $u): $i++; ?>
                            <tr>
                                <td><?= $i ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($u['name']) ?></strong><br>
                                    <small class="text-muted">ID FP: <?= htmlspecialchars($u['employee_code']) ?> &middot; GP: Rp <?= el_rp($u['salary']) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($u['branch_name']) ?>
                                    <?php if (!empty($u['subdivision'])): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($u['subdivision']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php foreach ($u['dates'] as $d): ?>
                                    <div class="text-nowrap">
                                        <span class="badge bg-light text-dark border"><?= date('d/m', strtotime($d['flow_date'])) ?></span>
                                        <small class="text-muted"><?= el_time($d['entry_time']) ?> &ndash; <?= el_time($d['out_time']) ?></small>
                                        <span class="badge bg-warning text-dark ms-1">-<?= el_minutes_to_hour_minute($d['short_minutes']) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </td>
                                <td class="text-end">
                                    <strong><?= el_minutes_to_hour_minute($u['total_short_minutes']) ?></strong><br>
                                    <small class="text-muted"><?= (int) $u['total_short_minutes'] ?> mnt</small>
                                </td>
                                <td class="text-end">
                                    Rp <?= el_rp((int) round($u['hourly_rate'])) ?>
                                </td>
                                <td class="text-end">
                                    <strong class="text-danger">Rp <?= el_rp($u['deduction_amount']) ?></strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end"><strong>Total</strong></td>
                                <td class="text-end"><strong><?= el_minutes_to_hour_minute($report['total_short_minutes']) ?></strong></td>
                                <td></td>
                                <td class="text-end"><strong class="text-danger">Rp <?= el_rp($report['total_amount']) ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

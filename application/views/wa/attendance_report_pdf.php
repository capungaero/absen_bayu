<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 28px 30px 42px; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #203047; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
    .header { margin-bottom: 14px; padding: 18px 20px; color: #fff; background: #2861d7; border-radius: 8px; }
    .header h1 { margin: 0 0 7px; font-size: 19px; }
    .header .brand { margin-bottom: 6px; color: #dbe7ff; font-size: 9px; }
    .header .date { color: #eaf1ff; font-size: 9px; }
    .stats { width: 100%; margin-bottom: 14px; }
    .stat { display: table; width: 100%; margin-bottom: 6px; padding: 8px 14px; background: #fff; border: 1px solid #e3e8f0; border-top: 3px solid #999; border-radius: 4px; }
    .stat .value-cell { display: table-cell; text-align: center; }
    .stat strong { display: block; font-size: 19px; }
    .stat span { color: #60718a; font-size: 7px; }
    .branch { margin: 0 0 13px; page-break-inside: auto; }
    .branch-title { display: table; width: 100%; margin-bottom: 6px; padding: 8px 12px; background: #edf2f8; border-left: 4px solid #3279ef; border-radius: 5px; font-size: 9px; }
    .branch-title .name-cell { display: table-cell; font-weight: bold; text-transform: uppercase; }
    .branch-title .ratio-cell { display: table-cell; text-align: right; color: #5e7089; }
    .position { margin: 0 0 9px; page-break-inside: avoid; }
    .position-title { display: table; width: 100%; margin-bottom: 4px; padding: 5px 10px; background: #dce9ff; border-left: 3px solid #3279ef; font-size: 8px; }
    .position-title .name-cell { display: table-cell; font-weight: bold; text-transform: uppercase; color: #174bac; }
    .position-title .ratio-cell { display: table-cell; text-align: right; color: #5e7089; }
    table.details { width: 100%; border-collapse: collapse; }
    .details thead { display: table-header-group; }
    .details tr { page-break-inside: avoid; }
    .details th { padding: 6px; background: #dfe6ef; text-align: left; font-size: 8px; text-transform: uppercase; color: #5e7089; }
    .details td { padding: 5px 6px; border-bottom: 1px solid #dbe3ed; vertical-align: middle; }
    .details tbody tr:nth-child(even) { background: #f6f8fb; }
    .details tbody tr.row-terlambat { background: #fff8e0; }
    .details tbody tr.row-hadir_gps { background: #e9faf3; }
    .num { width: 24px; }
    .code { width: 52px; }
    .status-col { width: 90px; }
    .badge { display: inline-block; padding: 3px 7px; border-radius: 5px; font-size: 8px; font-weight: bold; }
    .status-hadir { color: #078743; background: #d9f8e7; }
    .status-hadir_gps { color: #0b7d6f; background: #d7f5f0; }
    .status-terlambat { color: #9b5400; background: #fff0c5; }
    .status-off { color: #61718a; background: #e9edf3; }
    .status-alfa { color: #d22c2c; background: #ffdede; }
    .status-izin, .status-sakit { color: #315f9f; background: #dfeaff; }
    .warning { margin: 12px 0; padding: 12px 14px; color: #754c00; background: #fff3cd; border: 1px solid #efce70; border-radius: 6px; page-break-inside: avoid; }
    .warning h2 { margin: 0 0 7px; font-size: 11px; }
    .warning p { margin: 0 0 6px; }
    .warning ul { margin: 0; padding-left: 17px; columns: 2; }
    .footer { margin-top: 16px; padding-top: 10px; color: #64758e; border-top: 1px solid #d6deea; text-align: center; font-size: 7px; }
</style>
</head>
<body>
<?php
$totals = $report['totals'];
$status_labels = ['hadir' => 'Hadir', 'hadir_gps' => 'Hadir GPS', 'terlambat' => 'Terlambat', 'off' => 'OFF / Libur', 'izin' => 'Izin/Cuti', 'sakit' => 'Sakit', 'alfa' => 'Alfa'];
$stats = [
    ['color' => '#2861d7', 'value' => $totals['scheduled'], 'label' => 'Terjadwal'],
    ['color' => '#0b9348', 'value' => $totals['hadir'], 'label' => 'Hadir'],
    ['color' => '#a75500', 'value' => $totals['terlambat'], 'label' => 'Terlambat'],
    ['color' => '#6a3fc7', 'value' => $totals['izin'] + $totals['sakit'], 'label' => 'Izin/Sakit'],
    ['color' => '#0b7d6f', 'value' => $totals['hadir_gps'], 'label' => 'Hadir GPS'],
    ['color' => '#61718a', 'value' => $totals['off'], 'label' => 'OFF/Libur'],
    ['color' => '#d62929', 'value' => $totals['belum'], 'label' => 'Alfa'],
];
?>
<div class="header">
    <h1>Rekap Absensi - <?= htmlspecialchars($label) ?></h1>
    <div class="brand">Tiffany Houseware</div>
    <div class="date"><?= htmlspecialchars($report_date) ?></div>
</div>
<div class="stats"><?php foreach ($stats as $stat): ?>
    <div class="stat" style="border-top-color: <?= htmlspecialchars($stat['color']) ?>;">
        <div class="value-cell">
            <strong style="color: <?= htmlspecialchars($stat['color']) ?>;"><?= (int)$stat['value'] ?></strong>
            <span><?= htmlspecialchars($stat['label']) ?></span>
        </div>
    </div>
<?php endforeach; ?></div>

<?php foreach ($report['branches'] as $branch): ?>
<div class="branch">
    <div class="branch-title">
        <div class="name-cell"><?= htmlspecialchars(strtoupper($branch['branch_name'])) ?></div>
        <div class="ratio-cell"><?= (int)($branch['hadir'] + $branch['terlambat'] + $branch['hadir_gps']) ?>/<?= (int)$branch['total'] ?> hadir</div>
    </div>

    <?php foreach ($branch['positions'] as $position): ?>
    <div class="position">
        <div class="position-title">
            <div class="name-cell"><?= htmlspecialchars($position['position_name']) ?></div>
            <div class="ratio-cell"><?= (int)($position['hadir'] + $position['terlambat'] + $position['hadir_gps']) ?>/<?= (int)$position['total'] ?> hadir</div>
        </div>
        <table class="details">
            <thead><tr><th class="num">No</th><th>Nama</th><th class="code">Code</th><th class="status-col">Status</th><th>Keterangan</th></tr></thead>
            <tbody><?php foreach ($position['details'] as $index => $employee):
                $s = $employee['status'];
                if ($s === 'terlambat') {
                    $status_text = 'Terlambat +'.(int)$employee['late_minutes'].'m';
                } else {
                    $status_text = $status_labels[$s] ?? ucfirst($s);
                }
                if ($s === 'hadir_gps') {
                    $time = $employee['entry_time'] ? date('H:i', strtotime($employee['entry_time'])) : '-';
                    $note = $time.($employee['vehicle'] ? ' | '.$employee['vehicle'] : '');
                } elseif ($s === 'hadir' || $s === 'terlambat') {
                    $note = $employee['entry_time'] ? date('H:i', strtotime($employee['entry_time'])) : '-';
                } else {
                    $note = '-';
                }
            ?><tr class="row-<?= htmlspecialchars($s) ?>">
                <td><?= $index + 1 ?></td>
                <td><?= htmlspecialchars($employee['name']) ?></td>
                <td><?= htmlspecialchars($employee['employee_code']) ?></td>
                <td><span class="badge status-<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($status_text) ?></span></td>
                <td><?= htmlspecialchars($note) ?></td>
            </tr><?php endforeach; ?></tbody>
        </table>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php if (!empty($report['missing_shift'])): ?>
<div class="warning">
    <h2>WARNING: BELUM ADA SHIFT</h2>
    <p>Karyawan berikut tidak dihitung sebagai alfa karena belum memiliki shift valid:</p>
    <ul><?php foreach ($report['missing_shift'] as $employee): ?>
        <li><?= htmlspecialchars($employee['name'].' - '.$employee['branch_name']) ?></li>
    <?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="footer">
    Tiffany Houseware &mdash; <?= htmlspecialchars($report_date_only) ?> | Generated by Fany AI Agent
</div>
</body>
</html>

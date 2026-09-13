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
    .stats { width: 100%; margin-bottom: 14px; border-collapse: separate; border-spacing: 3px 0; }
    .stats td { width: 20%; padding: 12px 4px; text-align: center; border-radius: 6px; }
    .stats strong { display: block; margin-bottom: 4px; font-size: 20px; }
    .stats span { color: #60718a; font-size: 8px; }
    .blue { background: #dce9ff; color: #174bac; }
    .green { background: #dcf8e8; color: #0b9348; }
    .yellow { background: #fff2c8; color: #a75500; }
    .gray { background: #e9edf3; color: #61718a; }
    .red { background: #ffe0e0; color: #d62929; }
    .branch { margin: 0 0 13px; page-break-inside: auto; }
    .branch-title { margin-bottom: 6px; padding: 8px 12px; background: #edf2f8; border-left: 4px solid #3279ef; border-radius: 5px; font-size: 9px; }
    .branch-title strong { margin-right: 12px; }
    .branch-title span { color: #5e7089; }
    table.details { width: 100%; border-collapse: collapse; }
    .details thead { display: table-header-group; }
    .details tr { page-break-inside: avoid; }
    .details th { padding: 7px 6px; background: #dfe6ef; text-align: left; font-size: 8px; }
    .details td { padding: 6px; border-bottom: 1px solid #dbe3ed; vertical-align: middle; }
    .details tbody tr:nth-child(even) { background: #f6f8fb; }
    .num { width: 24px; }
    .code { width: 52px; }
    .position { width: 100px; }
    .time { width: 58px; }
    .status-col { width: 70px; }
    .badge { display: inline-block; padding: 3px 7px; border-radius: 5px; font-size: 8px; font-weight: bold; }
    .status-hadir { color: #078743; background: #d9f8e7; }
    .status-terlambat { color: #9b5400; background: #fff0c5; }
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
<?php $totals = $report['totals']; ?>
<div class="header">
    <h1>Rekap Absensi - <?= htmlspecialchars($label) ?></h1>
    <div class="brand">Tiffany Houseware</div>
    <div class="date"><?= htmlspecialchars($report_date) ?></div>
</div>
<table class="stats"><tr>
    <td class="blue"><strong><?= (int)$totals['scheduled'] ?></strong><span>Terjadwal</span></td>
    <td class="green"><strong><?= (int)($totals['hadir'] + $totals['terlambat']) ?></strong><span>Hadir</span></td>
    <td class="yellow"><strong><?= (int)$totals['terlambat'] ?></strong><span>Terlambat</span></td>
    <td class="gray"><strong><?= (int)$totals['off'] ?></strong><span>OFF/Libur</span></td>
    <td class="red"><strong><?= (int)$totals['belum'] ?></strong><span>Alfa/Belum</span></td>
</tr></table>

<?php foreach ($report['branches'] as $branch): ?>
<div class="branch">
    <div class="branch-title">
        <strong><?= htmlspecialchars(strtoupper($branch['branch_name'])) ?></strong>
        <span><?= (int)$branch['total'] ?> terjadwal | <?= (int)($branch['hadir'] + $branch['terlambat']) ?> hadir | <?= (int)$branch['belum'] ?> belum absen</span>
    </div>
    <table class="details">
        <thead><tr><th class="num">No</th><th>Nama</th><th class="code">Code</th><th class="position">Posisi</th><th class="time">Jam Finger</th><th class="status-col">Status</th><th>Keterangan</th></tr></thead>
        <tbody><?php foreach ($branch['details'] as $index => $employee):
            $labels = ['hadir' => 'Hadir', 'terlambat' => 'Terlambat', 'alfa' => 'Alfa', 'izin' => 'Izin/Cuti', 'sakit' => 'Sakit'];
            $time = $employee['entry_time'] ? date('H:i', strtotime($employee['entry_time'])) : '-';
            $note = $employee['status'] === 'hadir' ? ($employee['source'] ?: 'Hadir') : ($employee['status'] === 'terlambat' ? $employee['late_minutes'].' menit - '.($employee['source'] ?: 'Fingerprint') : ($employee['status'] === 'alfa' ? 'Belum absen' : $labels[$employee['status']]));
        ?><tr>
            <td><?= $index + 1 ?></td>
            <td><?= htmlspecialchars($employee['name']) ?></td>
            <td><?= htmlspecialchars($employee['employee_code']) ?></td>
            <td><?= htmlspecialchars(strtoupper($employee['position_name'])) ?></td>
            <td><?= htmlspecialchars($time) ?></td>
            <td><span class="badge status-<?= htmlspecialchars($employee['status']) ?>"><?= htmlspecialchars($labels[$employee['status']]) ?></span></td>
            <td><?= htmlspecialchars($note) ?></td>
        </tr><?php endforeach; ?></tbody>
    </table>
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
    Tiffany Houseware - Rekap Absensi <?= htmlspecialchars($label) ?> | <?= htmlspecialchars($printed_datetime) ?><br>
    Data dari: absen.4dm1n.my.id + Lacak GPS
</div>
</body>
</html>

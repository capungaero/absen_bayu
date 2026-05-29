<?php
/** Mobile Presence — Riwayat absensi (view-only, sumber: mesin fingerprint) */
$today = date('Y-m-d');
?>

<!-- Navigasi bulan -->
<div class="month-picker">
    <a href="<?= site_url('m/presence?month=' . $prev['month'] . '&year=' . $prev['year']) ?>" class="nav-btn">
        <i class="material-icons">chevron_left</i>
    </a>
    <span class="month-label"><?= $month_name ?> <?= $year ?></span>
    <a href="<?= site_url('m/presence?month=' . $next['month'] . '&year=' . $next['year']) ?>" class="nav-btn">
        <i class="material-icons">chevron_right</i>
    </a>
</div>

<?php
// Peta presensi per tanggal
$pmap = [];
foreach ($presences as $p) { $pmap[$p['flow_date']] = $p; }

// Gabungkan semua tanggal dari jadwal + presensi, urut menurun
$dates = array_unique(array_merge(array_keys($schedule_map), array_keys($pmap)));
rsort($dates);

// Ringkasan
$sum = ['H'=>0,'T'=>0,'A'=>0,'I'=>0];
foreach ($dates as $d) {
    if (isset($pmap[$d])) {
        $p = $pmap[$d];
        if ($p['presence_type'] !== 'normal') $sum['I']++;
        elseif ($p['entry_time']) { (int)$p['entry_time_late'] > 0 ? $sum['T']++ : $sum['H']++; }
    } elseif (isset($schedule_map[$d]) && $schedule_map[$d]['additional_type'] === 'work' && $d <= $today) {
        $sum['A']++;
    }
}
?>

<div class="stat-grid">
    <div class="stat-card success"><div class="stat-value"><?= $sum['H'] ?></div><div class="stat-label">Hadir</div></div>
    <div class="stat-card warning"><div class="stat-value"><?= $sum['T'] ?></div><div class="stat-label">Terlambat</div></div>
    <div class="stat-card danger"><div class="stat-value"><?= $sum['A'] ?></div><div class="stat-label">Alpha</div></div>
    <div class="stat-card primary"><div class="stat-value"><?= $sum['I'] ?></div><div class="stat-label">Izin/Cuti</div></div>
</div>

<div class="section-title mt-3"><i class="material-icons">list</i> Detail Presensi</div>

<?php if (empty($dates)): ?>
    <div class="m-card"><div class="empty-state"><i class="material-icons">event_busy</i><p>Belum ada data periode ini</p></div></div>
<?php else: ?>
    <div class="m-card">
        <?php foreach ($dates as $d):
            $dow = get_dayname($d);
            $normal = false; $time = ''; $bc=''; $bt=''; $ic=''; $icbg='#ccc';

            if (isset($pmap[$d])) {
                $p = $pmap[$d];
                if ($p['presence_type'] !== 'normal') {
                    $bc='info'; $bt=ucfirst($p['presence_type']); $ic='event_note'; $icbg='var(--primary)';
                } elseif ($p['entry_time']) {
                    $late = (int)$p['entry_time_late'] > 0;
                    $bc = $late?'warning':'success'; $bt=$late?'Terlambat':'Hadir';
                    $ic = $late?'watch_later':'check'; $icbg=$late?'var(--warning)':'var(--success)';
                    $time = 'Masuk '.($p['entry_time']?date('H:i',strtotime($p['entry_time'])):'-').
                            ' · Keluar '.($p['out_time']?date('H:i',strtotime($p['out_time'])):'-');
                    if ($p['is_early_leave']) $bt='Pulang Cepat';
                }
            }
            if ($bt === '') {
                if (isset($schedule_map[$d]) && $schedule_map[$d]['additional_type'] === 'free') {
                    $bc=''; $bt='Libur'; $ic='hotel'; $icbg='#bbb';
                } elseif ($d <= $today) {
                    $bc='danger'; $bt='Alpha'; $ic='close'; $icbg='var(--danger)';
                } else {
                    $bc='primary'; $bt='Terjadwal'; $ic='event'; $icbg='var(--primary-light)';
                }
            }
        ?>
            <div class="list-item">
                <div class="list-icon" style="background:<?= $icbg ?>"><i class="material-icons"><?= $ic ?></i></div>
                <div class="list-body">
                    <div class="list-title"><?= date('d M Y', strtotime($d)) ?> <span class="text-muted" style="font-size:12px">(<?= $dow ?>)</span></div>
                    <?php if ($time): ?><div class="list-desc"><?= $time ?></div><?php endif; ?>
                </div>
                <span class="m-badge <?= $bc ?>"><?= $bt ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

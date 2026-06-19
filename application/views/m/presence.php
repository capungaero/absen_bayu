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
$sum = ['H'=>0,'T'=>0,'A'=>0,'I'=>0,'TL'=>0,'PLA'=>0];
foreach ($dates as $d) {
    if (isset($pmap[$d])) {
        $p = $pmap[$d];
        if ($p['presence_type'] !== 'normal') $sum['I']++;
        elseif ($p['entry_time']) {
            if ($p['is_early_leave']) { $sum['PLA']++; }
            elseif (empty($p['out_time']) && $d < $today) { $sum['TL']++; }
            elseif ((int)$p['entry_time_late'] > 0) { $sum['T']++; }
            else { $sum['H']++; }
        }
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
    <?php if ($sum['TL'] > 0): ?><div class="stat-card warning"><div class="stat-value"><?= $sum['TL'] ?></div><div class="stat-label">Tdk Lengkap</div></div><?php endif; ?>
    <?php if ($sum['PLA'] > 0): ?><div class="stat-card warning"><div class="stat-value"><?= $sum['PLA'] ?></div><div class="stat-label">PLA</div></div><?php endif; ?>
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
                    if ($p['is_early_leave']) {
                        $bc='warning'; $bt='PLA'; $ic='directions_run'; $icbg='var(--warning)';
                    } elseif (empty($p['out_time']) && $d < $today) {
                        $bc='warning'; $bt='Tidak Lengkap'; $ic='warning'; $icbg='var(--warning)';
                    }
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
            $has_presence = isset($pmap[$d]) && $pmap[$d]['presence_type'] === 'normal' && !empty($pmap[$d]['entry_time']);
            $prayers = ['subuh'=>'Subuh','dzuhur'=>'Dzuhur','ashar'=>'Ashar','maghrib'=>'Maghrib','isha'=>'Isya'];
            if (isset($pmap[$d]) && date('N', strtotime($d)) == 5) $prayers['friday'] = 'Jumat';
        ?>
            <div class="list-item <?= $has_presence ? 'expandable' : '' ?>" <?= $has_presence ? 'onclick="toggleDetail(this)"' : '' ?>>
                <div class="list-icon" style="background:<?= $icbg ?>"><i class="material-icons"><?= $ic ?></i></div>
                <div class="list-body">
                    <div class="list-title"><?= date('d M Y', strtotime($d)) ?> <span class="text-muted" style="font-size:12px">(<?= $dow ?>)</span></div>
                    <?php if ($time): ?><div class="list-desc"><?= $time ?></div><?php endif; ?>
                </div>
                <span class="m-badge <?= $bc ?>"><?= $bt ?></span>
                <?php if ($has_presence): ?><i class="material-icons expand-arrow">expand_more</i><?php endif; ?>
            </div>
            <?php if ($has_presence):
                $pr = $pmap[$d];
            ?>
            <div class="detail-panel" style="display:none">
                <div class="detail-section-title"><i class="material-icons" style="font-size:14px;vertical-align:middle">access_time</i> Absen Kerja</div>
                <div class="detail-grid">
                    <div class="detail-cell"><span class="detail-label">Masuk</span><span class="detail-val"><?= !empty($pr['entry_time']) ? date('H:i', strtotime($pr['entry_time'])) : '-' ?></span></div>
                    <div class="detail-cell"><span class="detail-label">Pulang</span><span class="detail-val"><?= !empty($pr['out_time']) ? date('H:i', strtotime($pr['out_time'])) : '<span style="color:var(--danger);font-style:italic">—</span>' ?></span></div>
                    <div class="detail-cell"><span class="detail-label">Ist. Keluar</span><span class="detail-val"><?= !empty($pr['rest_time_out']) ? date('H:i', strtotime($pr['rest_time_out'])) : '-' ?></span></div>
                    <div class="detail-cell"><span class="detail-label">Ist. Masuk</span><span class="detail-val"><?= !empty($pr['rest_time_in']) ? date('H:i', strtotime($pr['rest_time_in'])) : '-' ?></span></div>
                </div>
                <?php if ((int)$pr['entry_time_late'] > 0 || (int)$pr['rest_time_late'] > 0 || $pr['is_early_leave'] || (empty($pr['out_time']) && $d < $today)): ?>
                <div class="detail-badges">
                    <?php if ((int)$pr['entry_time_late'] > 0): ?><span class="m-badge warning" style="font-size:11px">Telat masuk <?= (int)$pr['entry_time_late'] ?> menit</span><?php endif; ?>
                    <?php if ((int)$pr['rest_time_late'] > 0): ?><span class="m-badge warning" style="font-size:11px">Telat istirahat <?= (int)$pr['rest_time_late'] ?> menit</span><?php endif; ?>
                    <?php if ($pr['is_early_leave']): ?><span class="m-badge warning" style="font-size:11px">Pulang Lebih Awal</span><?php endif; ?>
                    <?php if (empty($pr['out_time']) && $d < $today): ?><span class="m-badge danger" style="font-size:11px">Finger pulang tidak ada</span><?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="detail-section-title" style="margin-top:8px"><i class="material-icons" style="font-size:14px;vertical-align:middle">person</i> Absen Sholat</div>
                <?php foreach ($prayers as $key => $label):
                    $tin  = $pr[$key.'_time_in'];
                    $tout = $pr[$key.'_time_out'];
                    $tlate = (int)$pr[$key.'_time_late'];
                    $has_scan = !empty($tin) || !empty($tout);
                ?>
                <div class="pray-row">
                    <span class="pray-name"><?= $label ?></span>
                    <?php if ($has_scan): ?>
                        <span class="pray-time"><?= date('H:i', strtotime($tin)) ?> – <?= !empty($tout) ? date('H:i', strtotime($tout)) : '—' ?></span>
                        <span class="pray-badges">
                            <?php if ($tlate > 0): ?><span class="m-badge warning pray-badge">Telat <?= $tlate ?> mnt</span><?php endif; ?>
                            <?php if (!empty($tin) && empty($tout)): ?><span class="m-badge danger pray-badge">Tidak finger</span><?php endif; ?>
                        </span>
                    <?php else: ?>
                        <span class="pray-nofinger">Tidak absen</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
.list-item.expandable{cursor:pointer;position:relative}
.expand-arrow{font-size:18px;color:var(--text-muted);transition:transform .2s;margin-left:4px}
.list-item.expanded .expand-arrow{transform:rotate(180deg)}
.detail-panel{background:var(--bg-card,#f8f9fa);border-radius:8px;padding:10px 12px;margin:-4px 0 8px 52px;border:1px solid var(--border,#eee)}
.detail-section-title{font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px 12px;margin-bottom:6px}
.detail-cell{display:flex;flex-direction:column}
.detail-label{font-size:11px;color:var(--text-muted)}
.detail-val{font-size:13px;font-weight:500;color:var(--text)}
.detail-badges{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:4px}
.pray-row{display:flex;align-items:center;flex-wrap:wrap;padding:4px 0;border-bottom:1px solid var(--border,#eee);font-size:13px}
.pray-row:last-child{border-bottom:none}
.pray-name{width:60px;font-weight:500;color:var(--text);flex-shrink:0}
.pray-time{color:var(--text)}
.pray-nofinger{color:var(--text-muted);font-style:italic;font-size:12px}
.pray-badges{display:flex;gap:4px;margin-left:auto;flex-shrink:0}
.pray-badge{font-size:10px!important;padding:1px 6px!important;white-space:nowrap}
</style>
<script>
function toggleDetail(el){
    var panel=el.nextElementSibling;
    while(panel && !panel.classList.contains('detail-panel')) panel=panel.nextElementSibling;
    if(!panel) return;
    var open=panel.style.display!=='none';
    panel.style.display=open?'none':'block';
    el.classList.toggle('expanded',!open);
}
</script>

<?php
/** Mobile Home — Dashboard karyawan / atasan */
$emp   = $employee ?: [];
$photo = !empty($emp['photo']) && $emp['photo'] != 'default-photo.jpg';
?>

<!-- Profil -->
<div class="m-card" style="background:linear-gradient(135deg,#4e73df,#224abe);color:#fff">
    <div class="d-flex align-items-center">
        <?php if ($photo): ?>
            <img src="<?= base_url('assets/images/users/' . $emp['photo']) ?>"
                 class="rounded-circle me-3" width="48" height="48" style="object-fit:cover">
        <?php else: ?>
            <div class="rounded-circle me-3 d-flex align-items-center justify-content-center"
                 style="width:48px;height:48px;background:rgba(255,255,255,.2)">
                <i class="material-icons" style="font-size:28px;color:#fff">person</i>
            </div>
        <?php endif; ?>
        <div>
            <div style="font-size:16px;font-weight:600"><?= htmlspecialchars($emp['first_name'] ?? 'Karyawan') ?></div>
            <div style="font-size:12px;opacity:.85">
                <?= htmlspecialchars($emp['position_name'] ?? '') ?><?= !empty($emp['branch_name']) ? ' — ' . htmlspecialchars($emp['branch_name']) : '' ?>
            </div>
        </div>
    </div>
</div>

<?php if ($is_approver && $pending_total > 0): ?>
<a href="<?= site_url('m/approvals') ?>" class="m-card d-flex align-items-center justify-content-between"
   style="text-decoration:none;border-left:4px solid var(--warning)">
    <div class="d-flex align-items-center">
        <i class="material-icons" style="color:var(--warning);font-size:28px;margin-right:10px">notifications_active</i>
        <div>
            <div style="font-weight:600;color:var(--text)"><?= $pending_total ?> pengajuan menunggu</div>
            <div style="font-size:12px;color:var(--text-muted)">Ketuk untuk meninjau lembur & izin</div>
        </div>
    </div>
    <i class="material-icons" style="color:var(--text-muted)">chevron_right</i>
</a>
<?php endif; ?>

<!-- Status hari ini -->
<div class="m-card">
    <div class="m-card-header">
        <h6 class="m-card-title"><i class="material-icons" style="font-size:18px;color:var(--primary)">today</i> Hari Ini</h6>
        <?php if ($today_schedule): ?>
            <span class="m-badge primary"><?= htmlspecialchars($today_schedule['shift_code'] ?? 'Kerja') ?></span>
        <?php else: ?>
            <span class="m-badge"><?= 'Libur' ?></span>
        <?php endif; ?>
    </div>
    <?php
    $tp = $today_presence;
    if ($tp && $tp['presence_type'] !== 'normal'):
        $lbl = ['sakit'=>'Sakit','izin'=>'Izin','cuti'=>'Cuti'][$tp['presence_type']] ?? ucfirst($tp['presence_type']);
    ?>
        <div class="text-center py-2">
            <span class="m-badge info" style="font-size:13px"><?= $lbl ?></span>
        </div>
    <?php elseif ($tp): ?>
        <div class="d-flex justify-content-between">
            <div>
                <div class="text-muted" style="font-size:12px">Masuk</div>
                <div style="font-size:18px;font-weight:700"><?= $tp['entry_time'] ? date('H:i', strtotime($tp['entry_time'])) : '-' ?></div>
                <?php if ((int)($tp['entry_time_late'] ?? 0) > 0): ?>
                    <span class="m-badge warning">Telat <?= (int)$tp['entry_time_late'] ?>m</span>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <div class="text-muted" style="font-size:12px">Keluar</div>
                <div style="font-size:18px;font-weight:700"><?= $tp['out_time'] ? date('H:i', strtotime($tp['out_time'])) : '-' ?></div>
            </div>
        </div>
    <?php else: ?>
        <div class="text-center text-muted py-2" style="font-size:13px">
            <i class="material-icons" style="font-size:32px;opacity:.5">schedule</i>
            <div class="mt-1">Belum ada data presensi hari ini</div>
        </div>
    <?php endif; ?>
</div>

<!-- Rekap bulan -->
<div class="section-title"><i class="material-icons">bar_chart</i> Rekap <?= $month_name ?> <?= date('Y') ?></div>
<div class="stat-grid">
    <div class="stat-card success">
        <div class="stat-icon"><i class="material-icons" style="color:var(--success)">check_circle</i></div>
        <div class="stat-value"><?= $stats['present'] ?></div>
        <div class="stat-label">Hadir</div>
    </div>
    <div class="stat-card warning">
        <div class="stat-icon"><i class="material-icons" style="color:var(--warning)">watch_later</i></div>
        <div class="stat-value"><?= $stats['late'] ?></div>
        <div class="stat-label">Terlambat</div>
    </div>
    <div class="stat-card danger">
        <div class="stat-icon"><i class="material-icons" style="color:var(--danger)">cancel</i></div>
        <div class="stat-value"><?= $stats['absent'] ?></div>
        <div class="stat-label">Alpha</div>
    </div>
    <div class="stat-card primary">
        <div class="stat-icon"><i class="material-icons" style="color:var(--primary)">event_note</i></div>
        <div class="stat-value"><?= $stats['permit'] ?></div>
        <div class="stat-label">Izin/Cuti</div>
    </div>
</div>

<!-- Menu cepat -->
<div class="section-title"><i class="material-icons">grid_view</i> Menu Cepat</div>
<div class="quick-menu-grid">
    <a href="<?= site_url('m/overtime') ?>" class="quick-menu-item">
        <i class="material-icons">more_time</i><span>Lembur</span>
    </a>
    <a href="<?= site_url('m/leave') ?>" class="quick-menu-item">
        <i class="material-icons">event_busy</i><span>Izin</span>
    </a>
    <a href="<?= site_url('m/schedule') ?>" class="quick-menu-item">
        <i class="material-icons">calendar_month</i><span>Jadwal</span>
    </a>
    <a href="<?= site_url('m/payroll') ?>" class="quick-menu-item">
        <i class="material-icons">account_balance_wallet</i><span>Slip Gaji</span>
    </a>
</div>

<!-- Presensi terakhir -->
<div class="section-title"><i class="material-icons">history</i> Presensi Terakhir</div>
<div class="m-card">
    <?php if (empty($recent)): ?>
        <div class="empty-state"><i class="material-icons">event_busy</i><p>Belum ada data presensi</p></div>
    <?php else: foreach ($recent as $att):
        $normal = $att['presence_type'] === 'normal';
        $late   = (int)($att['entry_time_late'] ?? 0) > 0;
        if (!$normal)      { $bc='info';    $bt=ucfirst($att['presence_type']); $ic='event_note'; $icbg='var(--warning)'; }
        elseif ($late)     { $bc='warning'; $bt='Terlambat'; $ic='watch_later'; $icbg='var(--warning)'; }
        else               { $bc='success'; $bt='Hadir';     $ic='check';       $icbg='var(--success)'; }
    ?>
        <div class="list-item">
            <div class="list-icon" style="background:<?= $icbg ?>"><i class="material-icons"><?= $ic ?></i></div>
            <div class="list-body">
                <div class="list-title"><?= date('d M Y', strtotime($att['flow_date'])) ?></div>
                <div class="list-desc">
                    <?php if ($normal): ?>
                        Masuk <?= $att['entry_time'] ? date('H:i', strtotime($att['entry_time'])) : '-' ?>
                        · Keluar <?= $att['out_time'] ? date('H:i', strtotime($att['out_time'])) : '-' ?>
                    <?php else: ?>
                        <?= ucfirst($att['presence_type']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <span class="m-badge <?= $bc ?>"><?= $bt ?></span>
        </div>
    <?php endforeach; endif; ?>
</div>

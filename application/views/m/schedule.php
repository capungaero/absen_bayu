<?php
/** Mobile Schedule — Jadwal shift bulanan */
$today = date('Y-m-d');
?>

<div class="month-picker">
    <a href="<?= site_url('m/schedule?month=' . $prev['month'] . '&year=' . $prev['year']) ?>" class="nav-btn">
        <i class="material-icons">chevron_left</i>
    </a>
    <span class="month-label"><?= $month_name ?> <?= $year ?></span>
    <a href="<?= site_url('m/schedule?month=' . $next['month'] . '&year=' . $next['year']) ?>" class="nav-btn">
        <i class="material-icons">chevron_right</i>
    </a>
</div>

<?php if (empty($schedules)): ?>
    <div class="m-card"><div class="empty-state"><i class="material-icons">event_busy</i><p>Belum ada jadwal bulan ini</p></div></div>
<?php else: ?>
    <div class="m-card">
        <?php foreach ($schedules as $s):
            $d       = $s['additional_date'];
            $free    = $s['additional_type'] === 'free';
            $isToday = ($d === $today);
        ?>
            <div class="sched-item <?= $free ? 'is-free' : '' ?>">
                <div class="sched-date">
                    <div class="d-num" style="<?= $isToday ? 'color:var(--primary)' : '' ?>"><?= date('d', strtotime($d)) ?></div>
                    <div class="d-dow"><?= substr(get_dayname($d), 0, 3) ?></div>
                </div>
                <div class="sched-info">
                    <?php if ($free): ?>
                        <div class="s-shift text-muted"><i class="material-icons" style="font-size:14px;vertical-align:-2px">hotel</i> Libur</div>
                    <?php else: ?>
                        <div class="s-shift"><?= htmlspecialchars($s['shift_name'] ?: ($s['shift_code'] ?: 'Kerja')) ?></div>
                        <?php if (!empty($s['start_time'])): ?>
                            <div class="s-time"><?= date('H:i', strtotime($s['start_time'])) ?> – <?= $s['end_time'] ? date('H:i', strtotime($s['end_time'])) : '' ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if (!$free && !empty($s['shift_code'])): ?>
                    <span class="m-badge primary"><?= htmlspecialchars($s['shift_code']) ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
/** Mobile Payroll — Slip gaji per bulan (hanya payroll yang sudah final) */
?>

<div class="month-picker">
    <a href="<?= site_url('m/payroll?year=' . $prev_year) ?>" class="nav-btn"><i class="material-icons">chevron_left</i></a>
    <span class="month-label">Tahun <?= $year ?></span>
    <a href="<?= site_url('m/payroll?year=' . $next_year) ?>" class="nav-btn"><i class="material-icons">chevron_right</i></a>
</div>

<?php if (empty($slips)): ?>
    <div class="m-card"><div class="empty-state"><i class="material-icons">account_balance_wallet</i><p>Belum ada slip gaji final di tahun ini</p></div></div>
<?php else: foreach ($slips as $i => $s):
    $deduction = (int)$s['salary_out_fine'] + (int)$s['salary_out_deduction'] + (int)$s['salary_out_health']
               + (int)$s['salary_out_work'] + (int)$s['salary_out_together'];
?>
    <div class="m-card">
        <button class="slip-toggle" type="button" data-target="#slip-<?= $i ?>">
            <div>
                <div style="font-weight:600;font-size:15px"><?= get_monthname($s['month']) ?> <?= $s['year'] ?></div>
                <div style="font-size:12px;color:var(--text-muted)">Take Home Pay</div>
            </div>
            <div class="d-flex align-items-center">
                <span style="font-weight:700;color:var(--success);font-size:16px"><?= format_rp($s['salary_thp']) ?></span>
                <i class="material-icons" style="color:var(--text-muted)">expand_more</i>
            </div>
        </button>

        <div class="slip-body" id="slip-<?= $i ?>">
            <div class="payslip-row"><span class="ps-label">Gaji Pokok</span><span class="ps-value"><?= format_rp($s['salary_in_basic']) ?></span></div>
            <div class="payslip-row"><span class="ps-label">Lembur (<?= rtrim(rtrim((string)$s['total_overtime_hour'],'0'),'.') ?: 0 ?> jam)</span><span class="ps-value"><?= format_rp($s['salary_in_overtime']) ?></span></div>
            <div class="payslip-row"><span class="ps-label">Insentif</span><span class="ps-value"><?= format_rp($s['salary_in_insentive']) ?></span></div>
            <?php if ((int)$s['salary_out_fine'] > 0): ?>
                <div class="payslip-row minus"><span class="ps-label">Denda</span><span class="ps-value">- <?= format_rp($s['salary_out_fine']) ?></span></div>
            <?php endif; ?>
            <?php if ((int)$s['salary_out_deduction'] > 0): ?>
                <div class="payslip-row minus"><span class="ps-label">Potongan</span><span class="ps-value">- <?= format_rp($s['salary_out_deduction']) ?></span></div>
            <?php endif; ?>
            <?php if ((int)$s['salary_out_health'] > 0): ?>
                <div class="payslip-row minus"><span class="ps-label">Kesehatan</span><span class="ps-value">- <?= format_rp($s['salary_out_health']) ?></span></div>
            <?php endif; ?>
            <?php if ((int)$s['salary_out_work'] > 0): ?>
                <div class="payslip-row minus"><span class="ps-label">Kasbon Kerja</span><span class="ps-value">- <?= format_rp($s['salary_out_work']) ?></span></div>
            <?php endif; ?>
            <div class="payslip-row"><span class="ps-label">Hadir / Telat</span><span class="ps-value"><?= (int)$s['presence_count'] ?> / <?= (int)$s['presence_count_on_late'] ?></span></div>
            <div class="payslip-row total"><span class="ps-label">Take Home Pay</span><span class="ps-value"><?= format_rp($s['salary_thp']) ?></span></div>
        </div>
    </div>
<?php endforeach; endif; ?>

<script>
$(function(){
    $('.slip-toggle').on('click', function(){
        var t = $($(this).data('target'));
        t.toggleClass('open');
        $(this).find('.material-icons').text(t.hasClass('open') ? 'expand_less' : 'expand_more');
    });
});
</script>

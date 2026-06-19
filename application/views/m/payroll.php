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
    $fine_data = !empty($s['payroll_fine']) ? json_decode($s['payroll_fine'], true) : null;
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
                <div class="payslip-row minus clickable" onclick="showFineDetail(<?= $i ?>)">
                    <span class="ps-label">Denda <i class="material-icons" style="font-size:14px;vertical-align:middle;color:var(--primary)">info</i></span>
                    <span class="ps-value">- <?= format_rp($s['salary_out_fine']) ?></span>
                </div>
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

    <?php if ($fine_data):
        $fd = $fine_data['detail'];
        $entry_late  = isset($fd['entry']['day']['late']) ? $fd['entry']['day']['late'] : [];
        $entry_half  = isset($fd['entry']['day']['half']) ? $fd['entry']['day']['half'] : [];
        $entry_early = isset($fd['entry']['day']['early_leave']) ? $fd['entry']['day']['early_leave'] : [];
        $entry_wknd  = isset($fd['entry']['day']['weekend']) ? $fd['entry']['day']['weekend'] : [];
        $entry_special = isset($fd['entry']['day']['special_double']) ? $fd['entry']['day']['special_double'] : [];
        $rest_late   = isset($fd['rest']['late']) ? $fd['rest']['late'] : [];
        $pray_detail = isset($fd['pray']['detail']) ? $fd['pray']['detail'] : [];
        $pray_names  = ['subuh'=>'Subuh','dzuhur'=>'Dzuhur','ashar'=>'Ashar','maghrib'=>'Maghrib','isha'=>'Isya','friday'=>'Jumat'];
    ?>
    <div class="fine-modal" id="fine-modal-<?= $i ?>" style="display:none">
        <div class="fine-modal-content">
            <div class="fine-modal-header">
                <span class="fine-modal-title"><i class="material-icons">receipt_long</i> Detail Denda — <?= get_monthname($s['month']) ?> <?= $s['year'] ?></span>
                <button class="fine-modal-close" onclick="closeFineDetail(<?= $i ?>)"><i class="material-icons">close</i></button>
            </div>
            <div class="fine-modal-body">
                <div class="fine-total-box">
                    <div>Total Denda</div>
                    <div class="fine-total-amount"><?= format_rp($s['salary_out_fine']) ?></div>
                </div>

                <div class="fine-tabs">
                    <button class="fine-tab active" onclick="switchFineTab(this,<?= $i ?>,'kehadiran')">Kehadiran</button>
                    <button class="fine-tab" onclick="switchFineTab(this,<?= $i ?>,'istirahat')">Istirahat</button>
                    <button class="fine-tab" onclick="switchFineTab(this,<?= $i ?>,'sholat')">Sholat</button>
                    <button class="fine-tab" onclick="switchFineTab(this,<?= $i ?>,'izin')">Izin</button>
                </div>

                <!-- TAB: Kehadiran -->
                <div class="fine-tab-panel" id="fine-<?= $i ?>-kehadiran">
                    <?php if (!empty($entry_late)): ?>
                    <div class="fine-section-title">Terlambat <span class="fine-count"><?= count($entry_late) ?>x</span> <span class="fine-subtotal"><?= format_rp($fd['entry']['amount_in_late']) ?></span></div>
                    <table class="fine-table">
                        <?php foreach ($entry_late as $el): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($el['date'])) ?></td>
                            <td class="text-center"><?= (int)$el['in_minute'] ?> Menit</td>
                            <td class="text-end"><?= format_rp($el['amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php endif; ?>

                    <?php if (!empty($entry_half)): ?>
                    <div class="fine-section-title">Finger Tidak Lengkap <span class="fine-count"><?= count($entry_half) ?>x</span> <span class="fine-subtotal"><?= format_rp($fd['entry']['amount_in_half']) ?></span></div>
                    <table class="fine-table">
                        <?php foreach ($entry_half as $el): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($el['date'])) ?></td>
                            <td class="text-center">—</td>
                            <td class="text-end"><?= format_rp($el['amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php endif; ?>

                    <?php if (!empty($entry_early)): ?>
                    <div class="fine-section-title">Pulang Lebih Awal <span class="fine-count"><?= count($entry_early) ?>x</span> <span class="fine-subtotal"><?= format_rp($fd['entry']['amount_early_leave']) ?></span></div>
                    <table class="fine-table">
                        <?php foreach ($entry_early as $el): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($el['date'])) ?></td>
                            <td class="text-center"><?= (int)$el['in_minute'] ?> Menit</td>
                            <td class="text-end"><?= format_rp($el['amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php endif; ?>

                    <?php if (!empty($entry_wknd)): ?>
                    <div class="fine-section-title">Alpha Weekend <span class="fine-count"><?= count($entry_wknd) ?>x</span> <span class="fine-subtotal"><?= format_rp($fd['entry']['amount_in_weekend'] - $fd['entry']['amount_in_special_double']) ?></span></div>
                    <table class="fine-table">
                        <?php foreach ($entry_wknd as $el): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($el['date'])) ?></td>
                            <td class="text-center"><?= $el['in_count'] ?>x</td>
                            <td class="text-end"><?= format_rp($el['amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php endif; ?>

                    <?php if (!empty($entry_special)): ?>
                    <div class="fine-section-title">Alpha Tanggal Khusus <span class="fine-count"><?= count($entry_special) ?>x</span> <span class="fine-subtotal"><?= format_rp($fd['entry']['amount_in_special_double']) ?></span></div>
                    <table class="fine-table">
                        <?php foreach ($entry_special as $el): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($el['date'])) ?></td>
                            <td class="text-center"><?= $el['in_count'] ?>x</td>
                            <td class="text-end"><?= format_rp($el['amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php endif; ?>

                    <?php if (empty($entry_late) && empty($entry_half) && empty($entry_early) && empty($entry_wknd) && empty($entry_special)): ?>
                        <div class="fine-empty">Tidak ada denda kehadiran</div>
                    <?php endif; ?>
                </div>

                <!-- TAB: Istirahat -->
                <div class="fine-tab-panel" id="fine-<?= $i ?>-istirahat" style="display:none">
                    <?php if (!empty($rest_late)): ?>
                    <div class="fine-section-title">Telat Istirahat <span class="fine-count"><?= count($rest_late) ?>x</span> <span class="fine-subtotal"><?= format_rp($fd['rest']['total']['in_fine']) ?></span></div>
                    <table class="fine-table">
                        <?php foreach ($rest_late as $rl): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($rl['date'])) ?></td>
                            <td class="text-center"><?= !empty($rl['half']) ? 'Tdk finger' : (int)$rl['in_minute'].' Menit' ?></td>
                            <td class="text-end"><?= format_rp($rl['amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php else: ?>
                        <div class="fine-empty">Tidak ada denda istirahat</div>
                    <?php endif; ?>
                </div>

                <!-- TAB: Sholat -->
                <div class="fine-tab-panel" id="fine-<?= $i ?>-sholat" style="display:none">
                    <?php
                    $has_pray_fine = false;
                    foreach ($pray_detail as $pk => $pv):
                        if (empty($pv['late'])) continue;
                        $has_pray_fine = true;
                        $plabel = isset($pray_names[$pk]) ? $pray_names[$pk] : ucfirst($pk);
                    ?>
                    <div class="fine-section-title"><?= $plabel ?> <span class="fine-count"><?= count($pv['late']) ?>x</span> <span class="fine-subtotal"><?= format_rp($pv['total']['amount']) ?></span></div>
                    <table class="fine-table">
                        <?php foreach ($pv['late'] as $pl): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($pl['date'])) ?></td>
                            <td class="text-center"><?= !empty($pl['half']) ? 'Tdk finger' : (int)$pl['in_minute'].' Menit' ?></td>
                            <td class="text-end"><?= format_rp($pl['amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php endforeach; ?>
                    <?php if (!$has_pray_fine): ?>
                        <div class="fine-empty">Tidak ada denda sholat</div>
                    <?php endif; ?>
                </div>

                <!-- TAB: Izin -->
                <div class="fine-tab-panel" id="fine-<?= $i ?>-izin" style="display:none">
                    <?php
                    $leave_detail = isset($fd['leave']) ? $fd['leave'] : [];
                    $leave_types  = isset($leave_detail['type']) ? $leave_detail['type'] : [];
                    $has_leave = false;
                    $leave_names = ['izin'=>'Izin','sakit'=>'Sakit','cuti'=>'Cuti'];
                    foreach ($leave_types as $lk => $lv):
                        if (empty($lv['day'])) continue;
                        $has_leave = true;
                    ?>
                    <div class="fine-section-title"><?= isset($leave_names[$lk]) ? $leave_names[$lk] : ucfirst($lk) ?> <span class="fine-count"><?= count($lv['day']) ?>x</span> <span class="fine-subtotal"><?= format_rp($lv['total_amount']) ?></span></div>
                    <table class="fine-table">
                        <?php foreach ($lv['day'] as $ld): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($ld['date'])) ?></td>
                            <td class="text-center"><?= $ld['percent'] ?>%</td>
                            <td class="text-end"><?= format_rp($ld['amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php endforeach; ?>
                    <?php if (!$has_leave): ?>
                        <div class="fine-empty">Tidak ada denda izin</div>
                    <?php endif; ?>
                </div>

            </div>
            <div class="fine-modal-footer">
                <button class="fine-modal-btn" onclick="closeFineDetail(<?= $i ?>)">Tutup</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php endforeach; endif; ?>

<style>
.payslip-row.clickable{cursor:pointer;position:relative}
.payslip-row.clickable:active{background:rgba(0,0,0,.04)}

.fine-modal{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:999;display:flex;align-items:flex-end;justify-content:center;animation:fadeIn .2s}
.fine-modal-content{background:var(--bg-card,#fff);border-radius:16px 16px 0 0;width:100%;max-width:480px;max-height:85vh;display:flex;flex-direction:column}
.fine-modal-header{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid var(--border,#eee)}
.fine-modal-title{font-weight:600;font-size:15px;display:flex;align-items:center;gap:6px;color:var(--text)}
.fine-modal-title .material-icons{font-size:20px;color:var(--primary)}
.fine-modal-close{background:none;border:none;padding:4px;cursor:pointer;color:var(--text-muted)}
.fine-modal-body{overflow-y:auto;padding:12px 16px;flex:1}
.fine-modal-footer{padding:12px 16px;border-top:1px solid var(--border,#eee)}
.fine-modal-btn{width:100%;padding:10px;border:none;border-radius:8px;background:var(--primary);color:#fff;font-weight:600;font-size:14px;cursor:pointer}

.fine-total-box{text-align:center;background:var(--bg-main,#f5f6fa);border-radius:10px;padding:10px;margin-bottom:12px}
.fine-total-box div:first-child{font-size:12px;color:var(--text-muted)}
.fine-total-amount{font-size:20px;font-weight:700;color:var(--danger)}

.fine-tabs{display:flex;gap:4px;margin-bottom:12px;border-bottom:2px solid var(--border,#eee);padding-bottom:0}
.fine-tab{background:none;border:none;padding:8px 12px;font-size:12px;font-weight:500;color:var(--text-muted);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px}
.fine-tab.active{color:var(--primary);border-bottom-color:var(--primary);font-weight:600}

.fine-section-title{font-size:13px;font-weight:600;color:var(--text);padding:8px 0 4px;display:flex;align-items:center;gap:6px;border-bottom:1px solid var(--border,#eee)}
.fine-count{font-size:11px;background:var(--warning);color:#fff;border-radius:10px;padding:1px 8px;font-weight:500}
.fine-subtotal{margin-left:auto;font-size:12px;color:var(--danger);font-weight:600}

.fine-table{width:100%;font-size:12px;margin-bottom:8px}
.fine-table td{padding:6px 4px;border-bottom:1px solid var(--border,#f0f0f0);color:var(--text)}
.fine-table td.text-center{text-align:center}
.fine-table td.text-end{text-align:right;font-weight:500;color:var(--danger)}

.fine-empty{text-align:center;color:var(--text-muted);font-size:13px;padding:20px 0;font-style:italic}

@keyframes fadeIn{from{opacity:0}to{opacity:1}}
</style>

<script>
$(function(){
    $('.slip-toggle').on('click', function(){
        var t = $($(this).data('target'));
        t.toggleClass('open');
        $(this).find('.material-icons').text(t.hasClass('open') ? 'expand_less' : 'expand_more');
    });
});

function showFineDetail(i) {
    $('#fine-modal-' + i).show();
    $('body').css('overflow', 'hidden');
}
function closeFineDetail(i) {
    $('#fine-modal-' + i).hide();
    $('body').css('overflow', '');
}
function switchFineTab(btn, i, tab) {
    $(btn).siblings().removeClass('active');
    $(btn).addClass('active');
    $('[id^="fine-' + i + '-"]').filter('.fine-tab-panel').hide();
    $('#fine-' + i + '-' + tab).show();
}
</script>

<?php
/** Mobile Approvals — Panel atasan: ACC/Tolak lembur & izin (scope cabang) */
$type_lbl = ['izin'=>'Izin','cuti'=>'Cuti','sakit'=>'Sakit'];
$st_map = [
    'approve' => ['success','Disetujui'], 'deny' => ['danger','Ditolak'],
    'cancel'  => ['warning','Dibatalkan'], 'pending' => ['warning','Menunggu'],
];
?>

<div class="section-title"><i class="material-icons">fact_check</i> Persetujuan</div>

<div class="seg-tabs">
    <button class="seg-tab active" data-tab="tab-ot">
        Lembur <?php if (!empty($ot_pending)): ?><span class="count-pill"><?= count($ot_pending) ?></span><?php endif; ?>
    </button>
    <button class="seg-tab" data-tab="tab-lv">
        Izin <?php if (!empty($lv_pending)): ?><span class="count-pill"><?= count($lv_pending) ?></span><?php endif; ?>
    </button>
</div>

<!-- ============ TAB LEMBUR ============ -->
<div class="tab-pane active" id="tab-ot">
    <?php if (empty($ot_pending)): ?>
        <div class="m-card"><div class="empty-state"><i class="material-icons">done_all</i><p>Tidak ada lembur menunggu</p></div></div>
    <?php else: foreach ($ot_pending as $ot): ?>
        <div class="approve-card" id="ot-card-<?= $ot['overtime_id'] ?>">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="ac-name"><?= htmlspecialchars($ot['first_name']) ?></div>
                    <div class="ac-meta"><?= htmlspecialchars($ot['employee_code']) ?> · <?= htmlspecialchars($ot['position_name']) ?></div>
                </div>
                <span class="m-badge warning">Lembur</span>
            </div>
            <div class="ac-detail">
                <div><b><?= rtrim(rtrim((string)$ot['overtime_hour'],'0'),'.') ?> jam</b> · <?= date('d M Y', strtotime($ot['overtime_date'])) ?></div>
                <div class="text-muted" style="font-size:11px">Diajukan: <?= $ot['created_at_string'] ?></div>
                <?php if (!empty($ot['overtime_proof'])): ?>
                    <img class="proof-thumb" src="<?= base_url('assets/images/hr/overtime/' . $ot['overtime_proof']) ?>" loading="lazy">
                <?php endif; ?>
            </div>
            <div class="approve-actions">
                <button class="m-btn m-btn-danger js-act" data-kind="overtime" data-id="<?= $ot['overtime_id'] ?>" data-status="deny">Tolak</button>
                <button class="m-btn m-btn-success js-act" data-kind="overtime" data-id="<?= $ot['overtime_id'] ?>" data-status="approve">Setujui</button>
            </div>
        </div>
    <?php endforeach; endif; ?>

    <?php if (!empty($ot_history)): ?>
        <div class="section-title mt-3"><i class="material-icons">history</i> Riwayat Lembur</div>
        <div class="m-card">
            <?php foreach ($ot_history as $ot): $s = $st_map[$ot['overtime_status']] ?? $st_map['pending']; ?>
                <div class="list-item">
                    <div class="list-body">
                        <div class="list-title"><?= htmlspecialchars($ot['first_name']) ?> — <?= rtrim(rtrim((string)$ot['overtime_hour'],'0'),'.') ?>j</div>
                        <div class="list-desc"><?= date('d M Y', strtotime($ot['overtime_date'])) ?></div>
                    </div>
                    <span class="m-badge <?= $s[0] ?>"><?= $s[1] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ============ TAB IZIN ============ -->
<div class="tab-pane" id="tab-lv">
    <?php if (empty($lv_pending)): ?>
        <div class="m-card"><div class="empty-state"><i class="material-icons">done_all</i><p>Tidak ada izin menunggu</p></div></div>
    <?php else: foreach ($lv_pending as $lv):
        $range = $lv['leave_start'] == $lv['leave_end']
            ? date('d M Y', strtotime($lv['leave_start']))
            : date('d M', strtotime($lv['leave_start'])) . ' – ' . date('d M Y', strtotime($lv['leave_end']));
    ?>
        <div class="approve-card" id="lv-card-<?= $lv['leave_id'] ?>">
            <div class="d-flex justify-content-between">
                <div>
                    <div class="ac-name"><?= htmlspecialchars($lv['first_name']) ?></div>
                    <div class="ac-meta"><?= htmlspecialchars($lv['employee_code']) ?> · <?= htmlspecialchars($lv['position_name']) ?></div>
                </div>
                <span class="m-badge info"><?= $type_lbl[$lv['leave_type']] ?? ucfirst($lv['leave_type']) ?></span>
            </div>
            <div class="ac-detail">
                <div><b><?= $range ?></b> <span class="text-muted">(<?= $lv['leave_range'] ?> hari)</span></div>
                <?php if (!empty($lv['leave_reason'])): ?><div style="margin-top:4px"><?= htmlspecialchars($lv['leave_reason']) ?></div><?php endif; ?>
                <div class="text-muted" style="font-size:11px">Diajukan: <?= $lv['created_at_string'] ?></div>
                <?php if (!empty($lv['leave_proof'])): ?>
                    <img class="proof-thumb" src="<?= base_url('assets/images/hr/leave/' . $lv['leave_proof']) ?>" loading="lazy">
                <?php endif; ?>
            </div>
            <div class="approve-actions">
                <button class="m-btn m-btn-danger js-act" data-kind="leave" data-id="<?= $lv['leave_id'] ?>" data-status="deny">Tolak</button>
                <button class="m-btn m-btn-success js-act" data-kind="leave" data-id="<?= $lv['leave_id'] ?>" data-status="approve">Setujui</button>
            </div>
        </div>
    <?php endforeach; endif; ?>

    <?php if (!empty($lv_history)): ?>
        <div class="section-title mt-3"><i class="material-icons">history</i> Riwayat Izin</div>
        <div class="m-card">
            <?php foreach ($lv_history as $lv): $s = $st_map[$lv['leave_status']] ?? $st_map['pending']; ?>
                <div class="list-item">
                    <div class="list-body">
                        <div class="list-title"><?= htmlspecialchars($lv['first_name']) ?> — <?= $type_lbl[$lv['leave_type']] ?? ucfirst($lv['leave_type']) ?></div>
                        <div class="list-desc"><?= date('d M', strtotime($lv['leave_start'])) ?> – <?= date('d M Y', strtotime($lv['leave_end'])) ?></div>
                    </div>
                    <span class="m-badge <?= $s[0] ?>"><?= $s[1] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
$(function(){
    // Tab switch
    $('.seg-tab').on('click', function(){
        $('.seg-tab').removeClass('active'); $(this).addClass('active');
        $('.tab-pane').removeClass('active'); $('#' + $(this).data('tab')).addClass('active');
    });

    // Approve / Reject
    $('.js-act').on('click', function(){
        var btn = $(this), kind = btn.data('kind'), id = btn.data('id'), status = btn.data('status');
        var url = kind === 'overtime' ? '<?= site_url("m/approve_overtime") ?>' : '<?= site_url("m/approve_leave") ?>';
        var card = $('#' + (kind === 'overtime' ? 'ot' : 'lv') + '-card-' + id);

        function send(reason){
            card.css('opacity', .5);
            mPost(url, { id:id, status:status, reject_reason: reason || '' }, function(res){
                if (res.status){ card.slideUp(200, function(){ $(this).remove(); }); toast(true, res.message, false);
                    setTimeout(function(){ location.reload(); }, 900);
                } else { card.css('opacity', 1); toast(false, res.message); }
            }).fail(function(){ card.css('opacity', 1); });
        }

        if (status === 'deny'){
            Swal.fire({ title:'Alasan penolakan', input:'textarea', inputPlaceholder:'Tulis alasan...',
                showCancelButton:true, confirmButtonText:'Tolak', cancelButtonText:'Batal', confirmButtonColor:'#e74a3b',
                inputValidator:function(v){ if(!v) return 'Alasan wajib diisi'; }
            }).then(function(r){ if (r.isConfirmed) send(r.value); });
        } else {
            Swal.fire({ title:'Setujui pengajuan?', icon:'question', showCancelButton:true,
                confirmButtonText:'Ya, setujui', cancelButtonText:'Batal', confirmButtonColor:'#1cc88a'
            }).then(function(r){ if (r.isConfirmed) send(); });
        }
    });
});
</script>

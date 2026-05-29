<?php
/** Mobile Overtime — Riwayat & pengajuan lembur */
$map = [
    'approve' => ['approved','success','Disetujui'],
    'deny'    => ['rejected','danger','Ditolak'],
    'cancel'  => ['rejected','warning','Dibatalkan'],
    'pending' => ['pending','warning','Menunggu'],
];
?>

<div class="section-title"><i class="material-icons">more_time</i> Lembur</div>

<?php if (empty($overtimes)): ?>
    <div class="m-card"><div class="empty-state"><i class="material-icons">more_time</i><p>Belum ada pengajuan lembur</p></div></div>
<?php else: foreach ($overtimes as $ot):
    $st = $map[$ot['overtime_status']] ?? $map['pending'];
?>
    <div class="status-card <?= $st[0] ?>">
        <div class="d-flex justify-content-between align-items-start">
            <div style="flex:1;min-width:0">
                <div class="status-title"><?= rtrim(rtrim((string)$ot['overtime_hour'], '0'), '.') ?> Jam</div>
                <div class="status-date mt-1"><i class="material-icons" style="font-size:14px;vertical-align:-2px">event</i>
                    <?= date('d M Y', strtotime($ot['overtime_date'])) ?>
                </div>
                <?php if ($ot['overtime_status'] === 'deny' && !empty($ot['reject_reason'])): ?>
                    <div style="font-size:12px;color:var(--danger);margin-top:4px">Ditolak: <?= htmlspecialchars($ot['reject_reason']) ?></div>
                <?php endif; ?>
            </div>
            <span class="m-badge <?= $st[1] ?>"><?= $st[2] ?></span>
        </div>
    </div>
<?php endforeach; endif; ?>

<button class="fab" id="fab-ot"><i class="material-icons">add</i></button>

<!-- Modal -->
<div class="modal fade" id="modal-ot" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Ajukan Lembur</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-ot" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="m-form-group">
                        <label class="m-form-label">Tanggal Lembur</label>
                        <input type="date" name="overtime_date" class="m-form-control" required>
                    </div>
                    <div class="m-form-group">
                        <label class="m-form-label">Lama Lembur (jam)</label>
                        <input type="number" name="overtime_hour" class="m-form-control" step="0.5" min="0.5" placeholder="contoh: 2" required>
                    </div>
                    <div class="m-form-group">
                        <label class="m-form-label">Foto Bukti <span class="text-muted" style="font-weight:400">— wajib</span></label>
                        <label class="photo-input">
                            <i class="material-icons">add_a_photo</i>
                            <span id="ot-proof-text">Ambil / pilih foto</span>
                            <input type="file" name="overtime_proof" id="ot_proof" accept="image/*" capture="environment" required>
                        </label>
                        <img id="ot_preview" class="photo-preview">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn m-btn-primary m-btn-sm" style="width:auto">Kirim</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(function(){
    var modal = new bootstrap.Modal('#modal-ot');
    $('#fab-ot').on('click', function(){ modal.show(); });

    $('#ot_proof').on('change', function(){
        var f = this.files[0];
        if (f) {
            $('#ot-proof-text').text(f.name);
            var r = new FileReader();
            r.onload = function(e){ $('#ot_preview').attr('src', e.target.result).show(); };
            r.readAsDataURL(f);
        }
    });

    $('#form-ot').on('submit', function(e){
        e.preventDefault();
        var f = this;
        var btn = $(f).find('button[type=submit]').prop('disabled', true).text('Mengirim...');
        mPostForm('<?= site_url("m/submit_overtime") ?>', f, function(res){
            if (res.status){ modal.hide(); toast(true, res.message, true); }
            else { toast(false, res.message); }
        }).always(function(){ btn.prop('disabled', false).text('Kirim'); });
    });
});
</script>

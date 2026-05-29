<?php
/** Mobile Leave — Riwayat & pengajuan izin/cuti/sakit */
$map = [
    'approve' => ['approved','success','Disetujui'],
    'deny'    => ['rejected','danger','Ditolak'],
    'cancel'  => ['rejected','warning','Dibatalkan'],
    'pending' => ['pending','warning','Menunggu'],
];
$type_lbl = ['izin'=>'Izin','cuti'=>'Cuti','sakit'=>'Sakit'];
?>

<div class="section-title"><i class="material-icons">event_busy</i> Izin, Cuti & Sakit</div>

<?php if (empty($leaves)): ?>
    <div class="m-card"><div class="empty-state"><i class="material-icons">event_available</i><p>Belum ada pengajuan izin</p></div></div>
<?php else: foreach ($leaves as $lv):
    $st = $map[$lv['leave_status']] ?? $map['pending'];
    $range = $lv['leave_start'] == $lv['leave_end']
        ? date('d M Y', strtotime($lv['leave_start']))
        : date('d M', strtotime($lv['leave_start'])) . ' – ' . date('d M Y', strtotime($lv['leave_end']));
?>
    <div class="status-card <?= $st[0] ?>">
        <div class="d-flex justify-content-between align-items-start">
            <div style="flex:1;min-width:0">
                <span class="m-badge info"><?= $type_lbl[$lv['leave_type']] ?? ucfirst($lv['leave_type']) ?></span>
                <div class="status-date mt-1"><i class="material-icons" style="font-size:14px;vertical-align:-2px">date_range</i>
                    <?= $range ?> <span class="text-muted">(<?= $lv['leave_range'] ?> hari)</span>
                </div>
                <?php if (!empty($lv['leave_reason'])): ?>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:4px"><?= htmlspecialchars($lv['leave_reason']) ?></div>
                <?php endif; ?>
                <?php if ($lv['leave_status'] === 'deny' && !empty($lv['reject_reason'])): ?>
                    <div style="font-size:12px;color:var(--danger);margin-top:4px">Ditolak: <?= htmlspecialchars($lv['reject_reason']) ?></div>
                <?php endif; ?>
            </div>
            <span class="m-badge <?= $st[1] ?>"><?= $st[2] ?></span>
        </div>
    </div>
<?php endforeach; endif; ?>

<button class="fab" id="fab-leave"><i class="material-icons">add</i></button>

<!-- Modal -->
<div class="modal fade" id="modal-leave" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Ajukan Izin / Cuti / Sakit</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-leave" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="m-form-group">
                        <label class="m-form-label">Jenis</label>
                        <select name="leave_type" id="leave_type" class="m-form-control" required>
                            <option value="">Pilih jenis...</option>
                            <option value="izin">Izin</option>
                            <option value="cuti">Cuti</option>
                            <option value="sakit">Sakit</option>
                        </select>
                    </div>
                    <div class="m-form-group">
                        <label class="m-form-label">Tanggal Mulai</label>
                        <input type="date" name="leave_start" class="m-form-control" required>
                    </div>
                    <div class="m-form-group">
                        <label class="m-form-label">Tanggal Selesai</label>
                        <input type="date" name="leave_end" class="m-form-control" required>
                    </div>
                    <div class="m-form-group">
                        <label class="m-form-label">Alasan</label>
                        <textarea name="leave_reason" class="m-form-control" rows="3" placeholder="Jelaskan alasan..." required></textarea>
                    </div>
                    <div class="m-form-group">
                        <label class="m-form-label" id="proof-label">Bukti (foto) <span class="text-muted" style="font-weight:400">— wajib untuk sakit</span></label>
                        <label class="photo-input">
                            <i class="material-icons">add_a_photo</i>
                            <span id="proof-text">Ambil / pilih foto</span>
                            <input type="file" name="leave_proof" id="leave_proof" accept="image/*" capture="environment">
                        </label>
                        <img id="leave_preview" class="photo-preview">
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
    var modal = new bootstrap.Modal('#modal-leave');
    $('#fab-leave').on('click', function(){ modal.show(); });

    $('#leave_proof').on('change', function(){
        var f = this.files[0];
        if (f) {
            $('#proof-text').text(f.name);
            var r = new FileReader();
            r.onload = function(e){ $('#leave_preview').attr('src', e.target.result).show(); };
            r.readAsDataURL(f);
        }
    });

    $('#form-leave').on('submit', function(e){
        e.preventDefault();
        var f = this;
        var btn = $(f).find('button[type=submit]').prop('disabled', true).text('Mengirim...');
        mPostForm('<?= site_url("m/submit_leave") ?>', f, function(res){
            if (res.status){ modal.hide(); toast(true, res.message, true); }
            else { toast(false, res.message); }
        }).always(function(){ btn.prop('disabled', false).text('Kirim'); });
    });
});
</script>

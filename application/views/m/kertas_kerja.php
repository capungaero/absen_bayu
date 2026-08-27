<?php
/** Mobile Kertas Kerja — bukti = FOTO kertas kerja tulisan tangan (bukan input teks). */
?>

<div class="section-title"><i class="material-icons">checklist</i> Kertas Kerja Hari Ini</div>

<div class="m-card">
    <form id="form-kertas-kerja" enctype="multipart/form-data">
        <div class="m-form-group">
            <label class="m-form-label">Tanggal</label>
            <input type="date" name="kerja_date" id="kk-date" class="m-form-control" value="<?= $today ?>" max="<?= $today ?>" required>
        </div>

        <div class="m-form-group">
            <label class="m-form-label">Foto Kertas Kerja <span class="text-muted" style="font-weight:400">— wajib</span></label>
            <p style="font-size:12px;color:var(--text-muted);margin-bottom:8px">Tulis tugas hari ini di kertas, lalu foto sebagai bukti.</p>
            <label class="photo-input">
                <i class="material-icons">add_a_photo</i>
                <span id="kk-photo-text"><?= !empty($current['photo_path']) ? 'Ganti foto' : 'Ambil / pilih foto' ?></span>
                <input type="file" name="kk_photo" id="kk_photo" accept="image/*" capture="environment" <?= empty($current['photo_path']) ? 'required' : '' ?>>
            </label>
            <img id="kk_preview" class="photo-preview" <?php if (!empty($current['photo_path'])): ?>src="<?= base_url('assets/images/kertas_kerja/'.$current['photo_path']) ?>" style="display:block" <?php endif; ?>>
        </div>

        <button type="submit" class="btn m-btn-primary m-btn-sm" style="width:100%">
            <?= empty($current) ? 'Simpan' : 'Perbarui' ?> Kertas Kerja
        </button>
    </form>
</div>

<div class="section-title"><i class="material-icons">history</i> Riwayat</div>
<div class="m-card">
    <?php if (empty($history)): ?>
        <div class="empty-state"><i class="material-icons">event_busy</i><p>Belum ada riwayat kertas kerja</p></div>
    <?php else: foreach ($history as $h): ?>
        <div class="status-card <?= $h['status'] === 'read' ? 'approved' : 'pending' ?>">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center" style="gap:10px">
                    <?php if (!empty($h['photo_path'])): ?>
                        <a href="<?= base_url('assets/images/kertas_kerja/'.$h['photo_path']) ?>" target="_blank">
                            <img src="<?= base_url('assets/images/kertas_kerja/'.$h['photo_path']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px" alt="foto kertas kerja">
                        </a>
                    <?php endif; ?>
                    <div class="status-date"><?= date('d M Y', strtotime($h['kerja_date'])) ?></div>
                </div>
                <span class="m-badge <?= $h['status'] === 'read' ? 'success' : 'warning' ?>">
                    <?= $h['status'] === 'read' ? 'Sudah Dibaca' : 'Baru' ?>
                </span>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>

<script>
$(function(){
    $('#kk_photo').on('change', function(){
        var f = this.files[0];
        if (f) {
            $('#kk-photo-text').text(f.name);
            var r = new FileReader();
            r.onload = function(e){ $('#kk_preview').attr('src', e.target.result).show(); };
            r.readAsDataURL(f);
        }
    });

    $('#form-kertas-kerja').on('submit', function(e){
        e.preventDefault();
        var f = this;
        var btn = $(f).find('button[type=submit]').prop('disabled', true);
        mPostForm('<?= site_url("m/submit_kertas_kerja") ?>', f, function(res){
            if (res.status){ toast(true, res.message, true); }
            else { toast(false, res.message); }
        }).always(function(){ btn.prop('disabled', false); });
    });
});
</script>

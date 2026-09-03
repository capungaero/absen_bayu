<?php
/**
 * Mobile Kertas Kerja — bukti = FOTO kertas kerja tulisan tangan ATAU teks rencana
 * kerja langsung (karyawan pilih salah satu tiap submit). Dua mode ditampilkan
 * bersamaan: "Kertas Kerja Saya" (kalau wajib_kertas_kerja) dan "Kertas Kerja
 * Anggota Tim" (kalau user ini leader team -- upload/isi mewakili anggota).
 */
$slot_label = function ($slot, $count) {
    if ($count <= 1) { return 'Kertas Kerja'; }
    return $slot == 1 ? 'Pagi' : 'Sore';
};
$kertas_kerja_count = isset($kertas_kerja_count) ? $kertas_kerja_count : 1;

/** Blok toggle Foto/Teks + kedua field-nya, dipakai bareng slot sendiri & anggota tim. */
$kk_mode_fields = function ($existing) {
    $mode = !empty($existing) ? $existing['submission_type'] : 'foto';
    ?>
    <div class="d-flex kk-mode-toggle" style="gap:6px;margin-bottom:8px">
        <button type="button" class="btn m-btn-sm kk-mode-btn <?= $mode === 'foto' ? 'm-btn-primary' : 'm-btn-outline' ?>" data-mode="foto" style="flex:1">📷 Foto</button>
        <button type="button" class="btn m-btn-sm kk-mode-btn <?= $mode === 'teks' ? 'm-btn-primary' : 'm-btn-outline' ?>" data-mode="teks" style="flex:1">✍️ Teks</button>
    </div>
    <input type="hidden" name="submission_type" class="kk-mode-input" value="<?= $mode ?>">

    <div class="kk-mode-block-foto" style="display:<?= $mode === 'foto' ? 'block' : 'none' ?>">
        <div class="d-flex" style="gap:8px">
            <label class="photo-input" style="flex:1">
                <i class="material-icons">add_a_photo</i>
                <span>Ambil Foto</span>
                <input type="file" class="kk-input-camera" accept="image/*" capture="environment">
            </label>
            <label class="photo-input" style="flex:1">
                <i class="material-icons">upload_file</i>
                <span>Upload File</span>
                <input type="file" class="kk-input-gallery" accept="image/*">
            </label>
        </div>
        <div class="kk-photo-name text-muted" style="font-size:12px;margin-top:6px"><?= (!empty($existing) && $existing['submission_type'] === 'foto') ? 'Foto tersimpan -- pilih ulang utk ganti' : '' ?></div>
        <input type="file" name="kk_photo" class="kk-input-real" style="display:none">
        <img class="kk-preview photo-preview" <?php if (!empty($existing) && $existing['submission_type'] === 'foto' && !empty($existing['photo_path'])): ?>src="<?= base_url('assets/images/kertas_kerja/'.$existing['photo_path']) ?>" style="display:block" <?php endif; ?>>
    </div>

    <div class="kk-mode-block-teks" style="display:<?= $mode === 'teks' ? 'block' : 'none' ?>">
        <textarea name="notes" class="m-form-control" rows="4" placeholder="Tulis rencana kerja hari ini..."><?= (!empty($existing) && $existing['submission_type'] === 'teks') ? htmlspecialchars($existing['notes']) : '' ?></textarea>
    </div>
    <?php
};
?>

<div class="section-title"><i class="material-icons">checklist</i> Kertas Kerja Hari Ini</div>

<?php if ($wajib_kertas_kerja): ?>
<div class="m-card">
    <div class="m-form-group">
        <label class="m-form-label">Tanggal</label>
        <input type="date" id="kk-date" class="m-form-control" value="<?= $today ?>" max="<?= $today ?>" required>
    </div>

    <?php for ($slot = 1; $slot <= $kertas_kerja_count; $slot++): $existing = $slots[$slot]; ?>
        <form class="kk-slot-form" data-self="1" style="margin-top:<?= $slot > 1 ? '16px' : '0' ?>;border-top:<?= $slot > 1 ? '1px solid #eee;padding-top:16px' : 'none' ?>">
            <input type="hidden" name="target_user_id" value="<?= $userdata->user_id ?>">
            <input type="hidden" name="slot_no" value="<?= $slot ?>">
            <input type="hidden" name="kerja_date" value="<?= $today ?>">

            <label class="m-form-label"><?= $kertas_kerja_count > 1 ? 'Kertas Kerja &mdash; '.$slot_label($slot, $kertas_kerja_count) : 'Kertas Kerja' ?> <span class="text-muted" style="font-weight:400">— wajib</span></label>
            <p style="font-size:12px;color:var(--text-muted);margin-bottom:8px">Foto kertas tulisan tangan, atau tulis langsung rencana kerja <?= $kertas_kerja_count > 1 ? strtolower($slot_label($slot, $kertas_kerja_count)).' ini' : 'hari ini' ?>.</p>

            <?php $kk_mode_fields($existing); ?>

            <button type="submit" class="btn m-btn-primary m-btn-sm" style="width:100%;margin-top:8px">
                <?= empty($existing) ? 'Simpan' : 'Perbarui' ?> <?= $kertas_kerja_count > 1 ? $slot_label($slot, $kertas_kerja_count) : '' ?>
            </button>
        </form>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php if ($is_leader): ?>
<div class="section-title"><i class="material-icons">groups</i> Kertas Kerja Anggota Tim</div>
<div class="m-card">
    <?php if (empty($members)): ?>
        <div class="empty-state"><i class="material-icons">group_off</i><p>Belum ada anggota tim yang wajib Kertas Kerja</p></div>
    <?php else: foreach ($members as $m): ?>
        <div style="border:1px solid #eee;border-radius:8px;padding:12px;margin-bottom:12px">
            <div style="font-weight:600;margin-bottom:8px"><i class="material-icons" style="font-size:16px;vertical-align:-3px">person</i> <?= htmlspecialchars($m['first_name']) ?> <small class="text-muted">(<?= htmlspecialchars($m['employee_code']) ?>)</small></div>

            <?php for ($slot = 1; $slot <= $m['kertas_kerja_count']; $slot++): $existing = $m['slots'][$slot]; ?>
                <form class="kk-slot-form" style="margin-top:<?= $slot > 1 ? '10px' : '0' ?>">
                    <input type="hidden" name="target_user_id" value="<?= $m['id'] ?>">
                    <input type="hidden" name="slot_no" value="<?= $slot ?>">
                    <input type="hidden" name="kerja_date" value="<?= $today ?>">

                    <div class="d-flex align-items-center justify-content-between" style="margin-bottom:6px">
                        <small style="font-weight:600"><?= $slot_label($slot, $m['kertas_kerja_count']) ?></small>
                        <?php if (!empty($existing)): ?>
                            <span class="m-badge success" style="font-size:10px">Sudah</span>
                        <?php else: ?>
                            <span class="m-badge warning" style="font-size:10px">Belum</span>
                        <?php endif; ?>
                    </div>

                    <?php $kk_mode_fields($existing); ?>

                    <button type="submit" class="btn m-btn-primary m-btn-sm" style="width:100%;margin-top:6px">
                        <?= empty($existing) ? 'Simpan' : 'Perbarui' ?> utk <?= htmlspecialchars($m['first_name']) ?>
                    </button>
                </form>
            <?php endfor; ?>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php endif; ?>

<div class="section-title"><i class="material-icons">history</i> Riwayat Saya</div>
<div class="m-card">
    <?php if (empty($history)): ?>
        <div class="empty-state"><i class="material-icons">event_busy</i><p>Belum ada riwayat kertas kerja</p></div>
    <?php else: foreach ($history as $h): ?>
        <div class="status-card <?= $h['status'] === 'read' ? 'approved' : 'pending' ?>">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center" style="gap:10px">
                    <?php if ($h['submission_type'] === 'teks'): ?>
                        <div style="width:40px;height:40px;border-radius:6px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="material-icons" style="font-size:20px;color:var(--text-muted)">notes</i>
                        </div>
                    <?php elseif (!empty($h['photo_path'])): ?>
                        <a href="<?= base_url('assets/images/kertas_kerja/'.$h['photo_path']) ?>" target="_blank">
                            <img src="<?= base_url('assets/images/kertas_kerja/'.$h['photo_path']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px" alt="foto kertas kerja">
                        </a>
                    <?php endif; ?>
                    <div class="status-date">
                        <?= date('d M Y', strtotime($h['kerja_date'])) ?>
                        <?php if ((int)$h['slot_no'] > 1 || $kertas_kerja_count > 1): ?><small class="text-muted"> &middot; <?= $slot_label($h['slot_no'], max($kertas_kerja_count, (int)$h['slot_no'])) ?></small><?php endif; ?>
                        <?php if ($h['submission_type'] === 'teks' && !empty($h['notes'])): ?>
                            <div class="text-muted" style="font-size:12px;margin-top:2px"><?= htmlspecialchars(mb_strimwidth($h['notes'], 0, 60, '...')) ?></div>
                        <?php endif; ?>
                    </div>
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
    function useFile($form, f){
        if (!f) return;
        var dt = new DataTransfer();
        dt.items.add(f);
        $form.find('.kk-input-real')[0].files = dt.files;
        $form.find('.kk-photo-name').text(f.name);
        var r = new FileReader();
        r.onload = function(e){ $form.find('.kk-preview').attr('src', e.target.result).show(); };
        r.readAsDataURL(f);
    }

    $(document).on('change', '.kk-input-camera, .kk-input-gallery', function(){
        useFile($(this).closest('form'), this.files[0]);
    });

    $(document).on('click', '.kk-mode-btn', function(){
        var $btn = $(this), $form = $btn.closest('form'), mode = $btn.data('mode');
        $form.find('.kk-mode-input').val(mode);
        $form.find('.kk-mode-btn').removeClass('m-btn-primary').addClass('m-btn-outline');
        $btn.removeClass('m-btn-outline').addClass('m-btn-primary');
        $form.find('.kk-mode-block-foto').toggle(mode === 'foto');
        $form.find('.kk-mode-block-teks').toggle(mode === 'teks');
    });

    $(document).on('submit', '.kk-slot-form', function(e){
        e.preventDefault();
        var f = this;
        if ($(f).data('self')) { $(f).find('input[name=kerja_date]').val($('#kk-date').val()); }
        var mode = $(f).find('.kk-mode-input').val();
        if (mode === 'teks' && !$.trim($(f).find('textarea[name=notes]').val())) {
            toast(false, 'Tulis rencana kerja dulu'); return;
        }
        if (mode === 'foto' && !$(f).find('.kk-input-real')[0].files.length) {
            toast(false, 'Ambil/pilih foto dulu -- foto wajib dipilih ulang tiap submit'); return;
        }
        var btn = $(f).find('button[type=submit]').prop('disabled', true);
        mPostForm('<?= site_url("m/submit_kertas_kerja") ?>', f, function(res){
            if (res.status){ toast(true, res.message, true); }
            else { toast(false, res.message); }
        }).always(function(){ btn.prop('disabled', false); });
    });
});
</script>

<?php
/** Mobile Kertas Kerja — to-do list harian (checklist + catatan). */
$current_items = !empty($current['items']) ? $current['items'] : [];
if (empty($current_items)) {
    $current_items = [['item_text' => '', 'is_done' => 0]];
}
?>

<div class="section-title"><i class="material-icons">checklist</i> Kertas Kerja Hari Ini</div>

<div class="m-card">
    <form id="form-kertas-kerja">
        <div class="m-form-group">
            <label class="m-form-label">Tanggal</label>
            <input type="date" name="kerja_date" id="kk-date" class="m-form-control" value="<?= $today ?>" max="<?= $today ?>" required>
        </div>

        <label class="m-form-label">Checklist Tugas</label>
        <div id="kk-items"></div>
        <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="kk-add-item">
            <i class="material-icons" style="font-size:16px;vertical-align:-3px">add</i> Tambah Item
        </button>

        <div class="m-form-group">
            <label class="m-form-label">Catatan</label>
            <textarea name="notes" class="m-form-control" rows="3" placeholder="Catatan tambahan (opsional)..."><?= htmlspecialchars($current['notes'] ?? '') ?></textarea>
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
                <div>
                    <div class="status-date"><?= date('d M Y', strtotime($h['kerja_date'])) ?></div>
                    <div style="font-size:12px;color:var(--text-muted)"><?= (int)$h['total_done'] ?> / <?= (int)$h['total_item'] ?> item selesai</div>
                </div>
                <span class="m-badge <?= $h['status'] === 'read' ? 'success' : 'warning' ?>">
                    <?= $h['status'] === 'read' ? 'Sudah Dibaca' : 'Baru' ?>
                </span>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>

<template id="kk-item-template">
    <div class="kk-item d-flex align-items-center mb-2" style="gap:8px">
        <input type="checkbox" class="kk-item-done" style="width:20px;height:20px;flex-shrink:0">
        <input type="text" class="m-form-control kk-item-text" placeholder="Tugas ke-__" style="flex:1">
        <button type="button" class="btn btn-sm btn-outline-danger kk-item-remove" style="flex-shrink:0"><i class="material-icons" style="font-size:16px">close</i></button>
    </div>
</template>

<script>
$(function(){
    var seed = <?= json_encode($current_items) ?>;

    function addRow(text, done){
        var tpl = document.getElementById('kk-item-template');
        var $row = $(tpl.content.firstElementChild.cloneNode(true));
        $row.find('.kk-item-text').val(text || '');
        // Bukan !!done -- is_done dari PHP/DB berupa string "0"/"1", dan string
        // "0" itu truthy di JS (!!"0" === true), jadi checkbox salah render
        // checked utk item yg belum selesai kalau pakai !!done.
        $row.find('.kk-item-done').prop('checked', done == 1);
        $('#kk-items').append($row);
        renumber();
    }

    function renumber(){
        $('#kk-items .kk-item').each(function(i){
            $(this).find('.kk-item-text').attr('name', 'item_text[' + i + ']')
                .attr('placeholder', 'Tugas ke-' + (i + 1));
            $(this).find('.kk-item-done').attr('name', 'is_done[' + i + ']').val('1');
        });
    }

    seed.forEach(function(it){ addRow(it.item_text, it.is_done); });
    if (seed.length === 0) { addRow('', false); }

    $('#kk-add-item').on('click', function(){ addRow('', false); });

    $(document).on('click', '.kk-item-remove', function(){
        if ($('#kk-items .kk-item').length <= 1) { return; }
        $(this).closest('.kk-item').remove();
        renumber();
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

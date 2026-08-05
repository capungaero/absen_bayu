<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="mdi mdi-checkbox-marked-outline"></i> Detail Kertas Kerja</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= site_url('hr/kertas_kerja') ?>">Kertas Kerja</a></li>
                    <li class="breadcrumb-item active">Detail</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="card-title mb-0">
                    <?= htmlspecialchars($employee['first_name']) ?>
                    <small class="text-muted">(<?= htmlspecialchars($employee['employee_code']) ?>)</small>
                    &mdash; <?= indonesian_date($header['kerja_date']) ?>
                </h6>
                <?php if ($header['status'] === 'read'): ?>
                    <span class="badge bg-success">Sudah Dibaca</span>
                <?php else: ?>
                    <button class="btn btn-sm btn-primary" id="btnMarkRead" data-id="<?= $header['id'] ?>"><i class="fa fa-check"></i> Tandai Dibaca</button>
                <?php endif; ?>
            </div>
            <div class="card-body">

                <h6>Checklist Tugas</h6>
                <?php if (empty($items)): ?>
                    <p class="text-muted">Tidak ada item.</p>
                <?php else: ?>
                    <ul class="list-group mb-3">
                        <?php foreach ($items as $it): ?>
                            <li class="list-group-item d-flex align-items-center">
                                <?php if ($it['is_done']): ?>
                                    <i class="mdi mdi-checkbox-marked text-success me-2"></i>
                                    <span class="text-decoration-line-through text-muted"><?= htmlspecialchars($it['item_text']) ?></span>
                                <?php else: ?>
                                    <i class="mdi mdi-checkbox-blank-outline text-muted me-2"></i>
                                    <span><?= htmlspecialchars($it['item_text']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h6>Catatan</h6>
                <p><?= nl2br(htmlspecialchars($header['notes'] ?: '-')) ?></p>

                <?php if (!empty($header['status']) && $header['status'] === 'read'): ?>
                    <small class="text-muted">Dibaca <?= indonesian_date($header['read_at'], true) ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
(function(){
    var TOKEN = "<?= $this->security->get_csrf_hash() ?>";
    $(document).on('click', '#btnMarkRead', function(){
        var $btn = $(this);
        $.post("<?= site_url('hr/kertas_kerja/mark_read') ?>", {
            myToken: TOKEN, id: $btn.data('id')
        }, null, 'json').done(function(res){
            if(res.status){ location.reload(); } else { show_modal('info', res.message); }
        });
    });
})();
</script>

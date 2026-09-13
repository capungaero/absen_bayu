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
                    <?php if ((int)$employee['kertas_kerja_count'] > 1): ?>
                        <span class="badge bg-secondary"><?= $header['slot_no'] == 1 ? 'Pagi' : 'Sore' ?></span>
                    <?php endif; ?>
                </h6>
                <span class="badge <?= $header['status'] === 'read' ? 'bg-success' : 'bg-warning text-dark' ?>">
                    <?= $header['status'] === 'read' ? 'Sudah Dibaca' : 'Baru' ?>
                </span>
            </div>
            <div class="card-body">

                <?php if (!empty($header['uploaded_by_name'])): ?>
                    <div class="alert alert-info py-2" style="font-size:13px">
                        <i class="mdi mdi-account-supervisor"></i> Diupload oleh leader team: <b><?= htmlspecialchars($header['uploaded_by_name']) ?></b> (mewakili <?= htmlspecialchars($employee['first_name']) ?>)
                    </div>
                <?php endif; ?>

                <?php if ($header['submission_type'] === 'teks'): ?>
                    <h6>Rencana Kerja (Teks)</h6>
                    <?php if (empty($header['notes'])): ?>
                        <p class="text-muted">Belum ada teks.</p>
                    <?php else: ?>
                        <div class="border rounded p-3 mb-3" style="white-space:pre-wrap;background:#f8f9fa"><?= htmlspecialchars($header['notes']) ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <h6>Bukti Foto Kertas Kerja</h6>
                    <?php if (empty($header['photo_path'])): ?>
                        <p class="text-muted">Belum ada foto.</p>
                    <?php else: ?>
                        <a
                            href="<?= base_url('assets/images/kertas_kerja/'.$header['photo_path']) ?>"
                            target="_blank"
                            class="js-mark-read-photo"
                            data-id="<?= $header['id'] ?>"
                            data-status="<?= htmlspecialchars($header['status']) ?>"
                        >
                            <img src="<?= base_url('assets/images/kertas_kerja/'.$header['photo_path']) ?>" class="img-fluid rounded mb-3" style="max-height:500px" alt="foto kertas kerja">
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

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
    $(document).on('click', '.js-mark-read-photo', function(){
        var $link = $(this);
        if ($link.data('status') !== 'new') {
            return;
        }

        $.post("<?= site_url('hr/kertas_kerja/mark_read') ?>", {
            myToken: TOKEN, id: $link.data('id')
        }, null, 'json').done(function(res){
            if(res.status){ location.reload(); } else { show_modal('info', res.message); }
        });
    });
})();
</script>

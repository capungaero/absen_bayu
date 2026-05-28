<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0 font-size-18"><i class="mdi mdi-history me-2"></i>Log Pengiriman WA</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= site_url('wa') ?>">WA Agent</a></li>
                    <li class="breadcrumb-item active">Log</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">
                        Total: <strong><?= $total ?></strong> log
                    </h5>
                    <a href="<?= site_url('wa') ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="mdi mdi-arrow-left me-1"></i>Kembali
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">#</th>
                                <th width="15%">Waktu</th>
                                <th width="15%">Tipe</th>
                                <th width="15%">Nomor</th>
                                <th width="10%">Status</th>
                                <th width="8%">HTTP</th>
                                <th>Pesan</th>
                                <th width="15%">Response</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $i => $log): ?>
                            <tr>
                                <td><?= ($page - 1) * $per_page + $i + 1 ?></td>
                                <td class="small"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                                <td>
                                    <?php
                                    $type_labels = [
                                        'rekap_pagi'  => '<span class="badge bg-success">Rekap Pagi</span>',
                                        'rekap_siang' => '<span class="badge bg-warning text-dark">Rekap Siang</span>',
                                        'notif_absen' => '<span class="badge bg-danger">Notif Absen</span>',
                                        'manual'      => '<span class="badge bg-info">Manual</span>',
                                    ];
                                    echo isset($type_labels[$log['type']]) ? $type_labels[$log['type']] : htmlspecialchars($log['type']);
                                    ?>
                                </td>
                                <td class="small"><?= htmlspecialchars($log['phone']) ?></td>
                                <td>
                                    <?php if ($log['status'] === 'success'): ?>
                                        <span class="badge bg-success"><i class="mdi mdi-check"></i> Terkirim</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="mdi mdi-close"></i> Gagal</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-center">
                                    <span class="badge <?= $log['http_code'] >= 200 && $log['http_code'] < 300 ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $log['http_code'] ?>
                                    </span>
                                </td>
                                <td class="small">
                                    <span class="d-inline-block text-truncate" style="max-width:200px"
                                          title="<?= htmlspecialchars($log['message']) ?>">
                                        <?= htmlspecialchars($log['message']) ?>
                                    </span>
                                    <a href="#" onclick="showMessage(<?= $i ?>)" class="ms-1 small">
                                        <i class="mdi mdi-eye"></i>
                                    </a>
                                    <div id="msg_<?= $i ?>" class="d-none"><?= htmlspecialchars($log['message']) ?></div>
                                </td>
                                <td class="small">
                                    <span class="d-inline-block text-truncate" style="max-width:120px"
                                          title="<?= htmlspecialchars($log['response']) ?>">
                                        <?= htmlspecialchars($log['response']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Belum ada log pengiriman.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php
                $total_pages = ceil($total / $per_page);
                if ($total_pages > 1):
                ?>
                <nav>
                    <ul class="pagination pagination-sm justify-content-end mt-3">
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= site_url('wa/logs?page=' . $p) ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pesan -->
<div class="modal fade" id="msgModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Isi Pesan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="msg_content" class="bg-light p-3 rounded small" style="white-space:pre-wrap;"></pre>
            </div>
        </div>
    </div>
</div>

<script>
function showMessage(idx) {
    var content = document.getElementById('msg_' + idx).textContent;
    document.getElementById('msg_content').textContent = content;
    var modal = new bootstrap.Modal(document.getElementById('msgModal'));
    modal.show();
    return false;
}
</script>

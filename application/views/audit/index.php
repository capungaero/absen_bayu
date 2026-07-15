<style>
.audit-badge-INSERT  { background: #198754; }
.audit-badge-UPDATE  { background: #0d6efd; }
.audit-badge-DELETE  { background: #dc3545; }
.audit-diff-before   { color: #dc3545; text-decoration: line-through; }
.audit-diff-after    { color: #198754; }
.audit-diff-arrow    { color: #6c757d; margin: 0 4px; }
</style>

<div class="page-content">
  <div class="container-fluid">

    <div class="row">
      <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
          <h4 class="mb-0">Log Perubahan Data Absensi</h4>
          <a href="<?= site_url('audit_log/mass_rollback') ?>" class="btn btn-sm btn-danger">
            <i class="mdi mdi-backup-restore"></i> Rollback Masal (Point-in-Time)
          </a>
        </div>
      </div>
    </div>

    <!-- Filter -->
    <div class="card">
      <div class="card-body py-3">
        <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
          <div>
            <label class="form-label mb-1 small">Dari Tanggal</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($date_from) ?>">
          </div>
          <div>
            <label class="form-label mb-1 small">Sampai Tanggal</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($date_to) ?>">
          </div>
          <div>
            <label class="form-label mb-1 small">Aksi</label>
            <select name="action" class="form-select form-select-sm">
              <option value="">Semua</option>
              <option value="INSERT" <?= $filter_action === 'INSERT' ? 'selected' : '' ?>>INSERT</option>
              <option value="UPDATE" <?= $filter_action === 'UPDATE' ? 'selected' : '' ?>>UPDATE</option>
              <option value="DELETE" <?= $filter_action === 'DELETE' ? 'selected' : '' ?>>DELETE</option>
            </select>
          </div>
          <div>
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
            <a href="<?= site_url('audit_log') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
          </div>
          <div class="ms-auto text-muted small pt-3">
            Total: <b><?= number_format($total) ?></b> perubahan
          </div>
        </form>
      </div>
    </div>

    <!-- Table -->
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0" style="font-size:13px">
            <thead class="table-light">
              <tr>
                <th style="width:140px">Waktu</th>
                <th style="width:80px">Aksi</th>
                <th>Mitra Kerja / Tanggal</th>
                <th>Diubah Oleh</th>
                <th>Perubahan</th>
                <th style="width:80px"></th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($logs)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data untuk filter ini.</td></tr>
              <?php else: ?>
                <?php foreach ($logs as $log): ?>
                  <?php
                    $emp_name = trim(($log['pres_first'] ?? '') . ' ' . ($log['pres_last'] ?? ''));
                    $pres_date = $log['pres_date'] ?? '';
                  ?>
                  <tr>
                    <td class="text-nowrap">
                      <?= date('d M Y', strtotime($log['changed_at'])) ?><br>
                      <small class="text-muted"><?= date('H:i:s', strtotime($log['changed_at'])) ?></small>
                    </td>
                    <td>
                      <span class="badge audit-badge-<?= $log['action'] ?>"><?= $log['action'] ?></span>
                    </td>
                    <td>
                      <?= htmlspecialchars($emp_name ?: '—') ?><br>
                      <small class="text-muted"><?= $pres_date ? date('d M Y', strtotime($pres_date)) : '—' ?></small>
                    </td>
                    <td><?= htmlspecialchars(trim($log['changer_name'] ?? '') ?: 'System') ?></td>
                    <td><?= $log['changes_summary'] ?></td>
                    <td>
                      <?php if ($log['record_id'] && $log['table_name'] === 'presence'): ?>
                        <button class="btn btn-xs btn-outline-primary py-0 px-1"
                          onclick="openAuditHistory(<?= $log['record_id'] ?>, '<?= htmlspecialchars($emp_name) ?>', '<?= $pres_date ?>')">
                          <i class="mdi mdi-history"></i> Riwayat
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php
          $pages = (int)ceil($total / $per_page);
          if ($pages > 1):
            $qs = http_build_query(['date_from' => $date_from, 'date_to' => $date_to, 'action' => $filter_action]);
        ?>
        <div class="d-flex justify-content-center py-3 gap-1">
          <?php for ($p = 1; $p <= $pages; $p++): ?>
            <a href="?<?= $qs ?>&page=<?= $p ?>"
               class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>">
              <?= $p ?>
            </a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?= $this->load->view('audit/_history_modal', [], true) ?>

<script>
function openAuditHistory(presenceId, empName, presDate) {
  auditHistoryOpen(presenceId, empName, presDate);
}
</script>

<!-- ── Modal Riwayat Perubahan Absensi ── -->
<div class="modal fade" id="modalAuditHistory" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title mb-0">
          <i class="mdi mdi-history me-1"></i>
          Riwayat Perubahan — <span id="auditHistoryTitle">...</span>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3" id="auditHistoryBody" style="min-height:160px">
        <div class="text-center py-4 text-muted" id="auditHistoryLoading">
          <i class="mdi mdi-loading mdi-spin me-1"></i> Memuat...
        </div>
        <div id="auditHistoryContent" style="display:none"></div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var CSRF = '<?php echo $this->security->get_csrf_hash() ?>';
  var ROLLBACK_URL = '<?= site_url("audit_log/rollback") ?>';
  var HISTORY_BASE = '<?= site_url("audit_log/history") ?>';

  var ACTION_LABEL = { INSERT: 'Buat baru', UPDATE: 'Perubahan', DELETE: 'Hapus' };
  var ACTION_CLASS = { INSERT: 'bg-success', UPDATE: 'bg-primary', DELETE: 'bg-danger' };

  window.auditHistoryOpen = function(presenceId, empName, presDate) {
    var fmt = presDate ? new Date(presDate).toLocaleDateString('id-ID', {day:'2-digit',month:'short',year:'numeric'}) : '';
    document.getElementById('auditHistoryTitle').textContent = (empName||'') + (fmt ? ' — ' + fmt : '');
    document.getElementById('auditHistoryLoading').style.display = '';
    document.getElementById('auditHistoryContent').style.display = 'none';
    document.getElementById('auditHistoryContent').innerHTML = '';

    var modal = new bootstrap.Modal(document.getElementById('modalAuditHistory'));
    modal.show();

    fetch(HISTORY_BASE + '/' + presenceId, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
      document.getElementById('auditHistoryLoading').style.display = 'none';
      if (!d.status) {
        document.getElementById('auditHistoryContent').innerHTML = '<div class="alert alert-warning">' + (d.message||'Gagal memuat.') + '</div>';
        document.getElementById('auditHistoryContent').style.display = '';
        return;
      }
      if (!d.history || !d.history.length) {
        document.getElementById('auditHistoryContent').innerHTML = '<div class="text-muted text-center py-3">Belum ada riwayat perubahan.</div>';
        document.getElementById('auditHistoryContent').style.display = '';
        return;
      }

      var html = '<div class="timeline">';
      d.history.forEach(function(h, idx){
        var dt = new Date(h.changed_at);
        var dtStr = dt.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}) + ' '
                  + dt.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit',second:'2-digit'});

        html += '<div class="card mb-2 border-0 shadow-none" style="background:#f8f9fa;border-left:3px solid #'
              + (h.action==='INSERT'?'198754':h.action==='DELETE'?'dc3545':'0d6efd') + '!important">';
        html += '<div class="card-body py-2 px-3">';
        html += '<div class="d-flex align-items-center justify-content-between flex-wrap gap-1">';
        html += '<div>';
        html += '<span class="badge me-1 ' + (ACTION_CLASS[h.action]||'bg-secondary') + '">' + (ACTION_LABEL[h.action]||h.action) + '</span>';
        html += '<small class="text-muted">' + dtStr + '</small>';
        html += ' &nbsp;<small class="text-muted">oleh <b>' + escH(h.changed_by) + '</b></small>';
        html += '</div>';
        if (h.can_rollback) {
          html += '<button class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:11px" '
                + 'onclick="auditRollback(' + h.id + ', this)" title="Kembalikan ke versi ini">'
                + '<i class="mdi mdi-restore"></i> Kembalikan ke versi ini</button>';
        }
        html += '</div>';

        if (h.changes && h.changes.length) {
          html += '<ul class="mb-0 mt-1 ps-3" style="font-size:12px">';
          h.changes.forEach(function(c){
            html += '<li><b>' + escH(c.label) + '</b>: '
                  + '<span class="audit-diff-before">' + escH(c.before) + '</span>'
                  + '<span class="audit-diff-arrow">→</span>'
                  + '<span class="audit-diff-after">' + escH(c.after) + '</span></li>';
          });
          html += '</ul>';
        } else if (h.action !== 'INSERT') {
          html += '<div class="text-muted mt-1" style="font-size:12px">Tidak ada perubahan terdeteksi.</div>';
        }
        html += '</div></div>';
      });
      html += '</div>';

      document.getElementById('auditHistoryContent').innerHTML = html;
      document.getElementById('auditHistoryContent').style.display = '';
    })
    .catch(function(){
      document.getElementById('auditHistoryLoading').style.display = 'none';
      document.getElementById('auditHistoryContent').innerHTML = '<div class="alert alert-danger">Gagal memuat riwayat.</div>';
      document.getElementById('auditHistoryContent').style.display = '';
    });
  };

  window.auditRollback = function(logId, btn) {
    if (!confirm('Yakin kembalikan data ke versi ini?')) return;
    btn.disabled = true;
    btn.textContent = 'Memproses...';

    fetch(ROLLBACK_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: 'log_id=' + logId + '&myToken=' + encodeURIComponent(CSRF),
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
      btn.disabled = false;
      btn.innerHTML = '<i class="mdi mdi-restore"></i> Kembalikan ke versi ini';
      if (d.status) {
        btn.closest('.card').style.borderLeftColor = '#198754';
        var ok = document.createElement('span');
        ok.className = 'badge bg-success ms-2'; ok.textContent = '✓ Diterapkan';
        btn.after(ok);
        // reload halaman setelah 1.5 detik agar tabel absensi ter-update
        setTimeout(function(){ location.reload(); }, 1500);
      } else {
        alert('Gagal: ' + (d.message || 'Terjadi kesalahan.'));
      }
    })
    .catch(function(){
      btn.disabled = false;
      btn.innerHTML = '<i class="mdi mdi-restore"></i> Kembalikan ke versi ini';
      alert('Gagal terhubung ke server.');
    });
  };

  function escH(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
})();
</script>

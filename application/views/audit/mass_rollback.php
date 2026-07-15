<style>
.rollback-card { border-left: 4px solid #dc3545; }
.badge-UPDATE { background: #0d6efd; }
.badge-DELETE { background: #dc3545; }
.badge-REINSERT { background: #198754; }
</style>

<div class="page-content">
  <div class="container-fluid">

    <div class="row">
      <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
          <h4 class="mb-0">Rollback Masal Data Absensi (Point-in-Time Recovery)</h4>
          <a href="<?= site_url('audit_log') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="mdi mdi-arrow-left"></i> Kembali ke Log
          </a>
        </div>
      </div>
    </div>

    <!-- Peringatan & Panduan -->
    <div class="alert alert-warning d-flex align-items-center" role="alert">
      <i class="mdi mdi-alert-circle-outline font-size-24 me-3"></i>
      <div>
        <b>Perhatian:</b> Fitur ini akan mengembalikan seluruh data absensi kerja maupun sholat ke kondisi tepat pada waktu (jam/menit) yang Anda tentukan di bawah ini.
        Sangat disarankan untuk menekan tombol <b>Simulasi (Dry Run)</b> terlebih dahulu guna memeriksa pratinjau data sebelum melakukan eksekusi langsung ke database.
      </div>
    </div>

    <!-- Pilih Waktu -->
    <div class="card rollback-card">
      <div class="card-header bg-transparent border-bottom">
        <h5 class="card-title mb-0">1. Tentukan Waktu Target Rollback</h5>
      </div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label font-weight-bold">Waktu Tujuan (YYYY-MM-DD HH:MM:SS)</label>
            <input type="text" id="targetTime" class="form-control" placeholder="2026-06-30 08:50:00" value="<?= date('Y-m-d 08:50:00') ?>">
            <small class="text-muted d-block mt-1">
              Batas waktu audit tersedia: <b><?= $min_time ? date('d M Y H:i', strtotime($min_time)) : '—' ?></b> s/d <b><?= $max_time ? date('d M Y H:i', strtotime($max_time)) : '—' ?></b>
            </small>
          </div>
          <div class="col-md-4">
            <label class="form-label font-weight-bold">Filter Mitra Kerja (Opsional)</label>
            <select id="filterUser" class="form-select">
              <option value="">-- Semua Mitra Kerja yang Terdampak --</option>
              <?php if (!empty($users)): foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars(trim($u['first_name'] . ' ' . ($u['last_name'] ?? ''))) ?> (<?= htmlspecialchars($u['employee_code'] ?? '') ?>)</option>
              <?php endforeach; endif; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label d-block text-muted small">Waktu Cepat (Preset):</label>
            <div class="d-flex flex-wrap gap-2">
              <button type="button" class="btn btn-sm btn-outline-primary" onclick="setPreset('<?= date('Y-m-d 08:50:00') ?>')">
                Pagi Ini 08:50
              </button>
              <button type="button" class="btn btn-sm btn-outline-primary" onclick="setPreset('<?= date('Y-m-d 20:50:00', strtotime('-1 day')) ?>')">
                Kemarin 20:50
              </button>
            </div>
          </div>
        </div>

        <div class="mt-4 pt-2 border-top d-flex gap-2">
          <button type="button" id="btnSimulate" class="btn btn-primary" onclick="runSimulate()">
            <i class="mdi mdi-calculator"></i> 2. Simulasi & Preview Data (Dry Run)
          </button>
        </div>
      </div>
    </div>

    <!-- Loading Area -->
    <div id="loadingBox" class="text-center py-5 d-none">
      <div class="spinner-border text-primary" role="status"></div>
      <p class="mt-2 text-muted">Menganalisa ribuan rekam jejak audit log...</p>
    </div>

    <!-- Preview Area -->
    <div id="previewBox" class="d-none">
      <div class="card">
        <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">3. Hasil Simulasi Rollback ke <span id="lblTargetTime" class="text-primary font-weight-bold"></span></h5>
        </div>
        <div class="card-body">
          
          <!-- Summary Counters -->
          <div class="row text-center mb-4">
            <div class="col-md-3">
              <div class="p-3 border rounded bg-light">
                <h3 id="cntUpd" class="text-primary mb-1">0</h3>
                <span class="text-muted small font-weight-bold">AKAN DI-RESTORE (UPDATE)</span>
              </div>
            </div>
            <div class="col-md-3">
              <div class="p-3 border rounded bg-light">
                <h3 id="cntDel" class="text-danger mb-1">0</h3>
                <span class="text-muted small font-weight-bold">AKAN DI-DELETE (DATA BARU)</span>
              </div>
            </div>
            <div class="col-md-3">
              <div class="p-3 border rounded bg-light">
                <h3 id="cntIns" class="text-success mb-1">0</h3>
                <span class="text-muted small font-weight-bold">AKAN DI-PULIHKAN (RE-INSERT)</span>
              </div>
            </div>
            <div class="col-md-3">
              <div class="p-3 border rounded bg-dark text-white">
                <h3 id="cntTotal" class="text-white mb-1">0</h3>
                <span class="small font-weight-bold">TOTAL BARIS BERUBAH</span>
              </div>
            </div>
          </div>

          <!-- Action Button -->
          <div id="actionExecuteWrapper" class="alert alert-danger d-flex align-items-center justify-content-between">
            <div>
              <b>Siap dieksekusi?</b> Perubahan di atas akan segera diterapkan langsung ke database live.
            </div>
            <button type="button" id="btnExecute" class="btn btn-danger font-weight-bold px-4" onclick="confirmExecute()">
              <i class="mdi mdi-database-export"></i> 4. Update & Rollback Langsung ke Database
            </button>
          </div>

          <div id="noChangeAlert" class="alert alert-info d-none">
            Tidak ada perubahan data yang ditemukan setelah waktu target tersebut. Data sudah sesuai.
          </div>

          <!-- Sample Table -->
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Daftar Baris Data yang Akan Di-rollback (<span id="lblVisibleRows">0</span> baris):</h6>
            <div>
              <button type="button" class="btn btn-xs btn-outline-primary me-1" onclick="selectAllRows(true)">☑️ Pilih Semua</button>
              <button type="button" class="btn btn-xs btn-outline-secondary" onclick="selectAllRows(false)">⬜ Lepas Semua</button>
            </div>
          </div>
          <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
            <table class="table table-sm table-bordered table-hover mb-0" style="font-size:13px">
              <thead class="table-light sticky-top">
                <tr>
                  <th style="width:40px" class="text-center">
                    <input type="checkbox" id="checkAll" checked onclick="toggleCheckAll(this)">
                  </th>
                  <th style="width:70px">ID</th>
                  <th style="width:90px">Aksi</th>
                  <th>Mitra Kerja</th>
                  <th style="width:120px">Tanggal Absen</th>
                  <th>Keterangan Pemulihan</th>
                  <th style="width:130px" class="text-center">Perbandingan</th>
                </tr>
              </thead>
              <tbody id="previewTableBody">
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>

  </div>
</div>

<!-- Modal Diff -->
<div class="modal fade" id="modalRollbackDiff" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Perbandingan Sebelum vs Sesudah Rollback <span id="diffModalSubtitle" class="text-muted small"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0" style="font-size:13px">
            <thead class="table-light">
              <tr>
                <th>Nama Kolom</th>
                <th style="width:35%" class="text-danger">Kondisi Saat Ini (Sebelum Rollback)</th>
                <th style="width:35%" class="text-success">Kondisi Setelah Rollback</th>
              </tr>
            </thead>
            <tbody id="diffTableBody">
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
window._previewRows = [];

function setPreset(timeStr) {
  document.getElementById('targetTime').value = timeStr;
  runSimulate();
}

function runSimulate() {
  var target = document.getElementById('targetTime').value.trim();
  var filterUser = document.getElementById('filterUser').value;
  if (!target) {
    alert('Harap isi waktu target rollback.');
    return;
  }

  document.getElementById('loadingBox').classList.remove('d-none');
  document.getElementById('previewBox').classList.add('d-none');
  document.getElementById('btnSimulate').disabled = true;

  $.ajax({
    url: '<?= site_url("audit_log/mass_rollback_simulate") ?>',
    type: 'POST',
    data: { target_time: target, filter_user_id: filterUser },
    dataType: 'json',
    success: function(res) {
      document.getElementById('loadingBox').classList.add('d-none');
      document.getElementById('btnSimulate').disabled = false;

      if (!res.status) {
        alert('Error: ' + res.message);
        return;
      }

      var plan = res.plan;
      window._previewRows = plan.preview || [];
      document.getElementById('lblTargetTime').textContent = plan.target_time;
      document.getElementById('lblVisibleRows').textContent = window._previewRows.length;

      var html = '';
      if (window._previewRows.length === 0) {
        html = '<tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr>';
      } else {
        window._previewRows.forEach(function(row, idx) {
          html += '<tr>' +
            '<td class="text-center"><input type="checkbox" class="row-check form-check-input" value="' + row.id + '" data-type="' + row.type + '" checked onchange="recalcSelection()"></td>' +
            '<td>#' + row.id + '</td>' +
            '<td><span class="badge badge-' + row.type + '">' + row.type + '</span></td>' +
            '<td><b>' + row.emp + '</b></td>' +
            '<td>' + row.date + '</td>' +
            '<td>' + row.info + '</td>' +
            '<td class="text-center"><button type="button" class="btn btn-xs btn-outline-info py-1 px-2" onclick="showDiffModal(' + idx + ')"><i class="mdi mdi-eye"></i> Cek Perubahan</button></td>' +
            '</tr>';
        });
      }
      document.getElementById('previewTableBody').innerHTML = html;
      document.getElementById('checkAll').checked = true;
      document.getElementById('previewBox').classList.remove('d-none');
      recalcSelection();
    },
    error: function(xhr) {
      document.getElementById('loadingBox').classList.add('d-none');
      document.getElementById('btnSimulate').disabled = false;
      alert('Terjadi kesalahan koneksi server.');
    }
  });
}

function toggleCheckAll(master) {
  var cbs = document.querySelectorAll('.row-check');
  cbs.forEach(function(cb) {
    cb.checked = master.checked;
  });
  recalcSelection();
}

function selectAllRows(status) {
  var cbs = document.querySelectorAll('.row-check');
  cbs.forEach(function(cb) {
    cb.checked = status;
  });
  document.getElementById('checkAll').checked = status;
  recalcSelection();
}

function recalcSelection() {
  var cbs = document.querySelectorAll('.row-check:checked');
  var u = 0, d = 0, i = 0;
  cbs.forEach(function(cb) {
    var t = cb.getAttribute('data-type');
    if (t === 'UPDATE') u++;
    else if (t === 'DELETE') d++;
    else if (t === 'REINSERT') i++;
  });
  var total = u + d + i;
  document.getElementById('cntUpd').textContent = u;
  document.getElementById('cntDel').textContent = d;
  document.getElementById('cntIns').textContent = i;
  document.getElementById('cntTotal').textContent = total;

  if (total === 0) {
    document.getElementById('actionExecuteWrapper').classList.add('d-none');
    document.getElementById('noChangeAlert').classList.remove('d-none');
  } else {
    document.getElementById('actionExecuteWrapper').classList.remove('d-none');
    document.getElementById('noChangeAlert').classList.add('d-none');
  }
}

function showDiffModal(idx) {
  var row = window._previewRows[idx];
  if (!row) return;

  document.getElementById('diffModalSubtitle').textContent = '(ID #' + row.id + ' - ' + row.emp + ' / ' + row.date + ')';
  
  var diff = row.diff || [];
  var html = '';
  if (diff.length === 0) {
    html = '<tr><td colspan="3" class="text-center text-muted py-3">Tidak ada perbedaan nilai pada kolom utama.</td></tr>';
  } else {
    diff.forEach(function(d) {
      html += '<tr>' +
        '<td><b>' + d.label + '</b></td>' +
        '<td class="text-danger font-weight-bold">' + d.before + '</td>' +
        '<td class="text-success font-weight-bold">' + d.after + '</td>' +
        '</tr>';
    });
  }
  document.getElementById('diffTableBody').innerHTML = html;
  var myModal = new bootstrap.Modal(document.getElementById('modalRollbackDiff'));
  myModal.show();
}

function confirmExecute() {
  var target = document.getElementById('targetTime').value.trim();
  var filterUser = document.getElementById('filterUser').value;
  var total = document.getElementById('cntTotal').textContent;

  if (parseInt(total) === 0) {
    alert('Pilih minimal 1 baris data untuk di-rollback.');
    return;
  }

  var selectedIds = Array.from(document.querySelectorAll('.row-check:checked')).map(function(cb) {
    return cb.value;
  });
  var selectedStr = selectedIds.join(',');

  if (!confirm('PERINGATAN KRUSIAL:\n\nApakah Anda sungguh yakin ingin melakukan rollback untuk ' + total + ' baris data absensi yang terpilih langsung ke database pada kondisi ' + target + '?\n\nTindakan ini akan disimpan dalam transaksi.')) {
    return;
  }

  var btn = document.getElementById('btnExecute');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sedang memulihkan database...';

  $.ajax({
    url: '<?= site_url("audit_log/mass_rollback_execute") ?>',
    type: 'POST',
    data: { target_time: target, filter_user_id: filterUser, selected_ids: selectedStr },
    dataType: 'json',
    success: function(res) {
      btn.disabled = false;
      btn.innerHTML = '<i class="mdi mdi-database-export"></i> 4. Update & Rollback Langsung ke Database';

      if (!res.status) {
        alert('Gagal: ' + res.message);
        return;
      }

      alert('SUKSES!\n\n' + res.message);
      window.location.href = '<?= site_url("audit_log") ?>';
    },
    error: function(xhr) {
      btn.disabled = false;
      btn.innerHTML = '<i class="mdi mdi-database-export"></i> 4. Update & Rollback Langsung ke Database';
      var msg = 'Terjadi kesalahan sistem saat mengeksekusi rollback.';
      if (xhr && xhr.responseText) {
        msg += '\n\nDetail: ' + xhr.responseText.substring(0, 200);
      }
      alert(msg);
    }
  });
}
</script>

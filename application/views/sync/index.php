<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0 font-size-18"><i class="mdi mdi-sync me-2"></i>Data Sync — Mesin Absensi</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Data Sync</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php if ($this->session->flashdata('success')): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="mdi mdi-check-circle me-2"></i><?= $this->session->flashdata('success') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($this->session->flashdata('error')): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="mdi mdi-alert-circle me-2"></i><?= $this->session->flashdata('error') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- ====================================================
         KOLOM KIRI: Daftar Mesin
    ===================================================== -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0"><i class="mdi mdi-server me-1"></i>Daftar Mesin Absensi</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="mdi mdi-plus me-1"></i>Tambah Mesin
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (empty($machines)): ?>
                <div class="text-center text-muted py-5">
                    <i class="mdi mdi-server-off font-size-48 d-block mb-2"></i>
                    Belum ada mesin absensi yang terdaftar.<br>
                    <button class="btn btn-outline-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#modalTambah">
                        Tambah Mesin Pertama
                    </button>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Mesin</th>
                                <th>ID Mesin (SN)</th>
                                <th>Tipe</th>
                                <th>Cabang</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Auto Sync</th>
                                <th class="text-center">Terakhir Sync</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($machines as $m): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
                                <td><code><?= htmlspecialchars($m['machine_sn'] ?? '') ?></code></td>
                                <td>
                                    <?php if (($m['machine_type'] ?? 'attendance') === 'pray'): ?>
                                        <span class="badge bg-warning text-dark"><i class="mdi mdi-hands-pray"></i> Sholat</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary"><i class="mdi mdi-fingerprint"></i> Absensi</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($m['branch_name'] ?? '-') ?></td>
                                <td class="text-center">
                                    <?php if ($m['is_active']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($m['auto_sync_enabled']): ?>
                                        <span class="badge bg-info">ON</span>
                                        <?php if (!empty($m['sync_times'])): ?>
                                        <div class="mt-1">
                                            <?php foreach (explode(',', $m['sync_times']) as $t): ?>
                                                <span class="badge bg-soft-primary text-primary"><?= trim(htmlspecialchars($t)) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark">OFF</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center small text-muted">
                                    <?php if (!empty($m['last_sync_at'])): ?>
                                        <?= date('d/m/Y H:i', strtotime($m['last_sync_at'])) ?>
                                        <?php if ($m['last_sync_status'] === 'success'): ?>
                                            <span class="badge bg-success ms-1">OK</span>
                                        <?php elseif ($m['last_sync_status'] === 'failed'): ?>
                                            <span class="badge bg-danger ms-1">Gagal</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Belum pernah</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center" style="white-space:nowrap">
                                    <button class="btn btn-sm btn-outline-success"
                                            onclick="syncSekarang(<?= $m['id'] ?>)"
                                            title="Tarik data sekarang">
                                        <i class="mdi mdi-cloud-download"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-info ms-1"
                                            onclick="checkFingers('<?= htmlspecialchars($m['machine_sn']) ?>')"
                                            title="Cek finger IDs">
                                        <i class="mdi mdi-fingerprint"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary ms-1"
                                            onclick="editMesin(<?= $m['id'] ?>)" title="Edit">
                                        <i class="mdi mdi-pencil"></i>
                                    </button>
                                    <?php if ($this->ion_auth->is_admin()): ?>
                                    <a href="<?= site_url('sync/delete/' . $m['id']) ?>"
                                       class="btn btn-sm btn-outline-danger ms-1"
                                       onclick="return confirm('Hapus mesin <?= htmlspecialchars(addslashes($m['name'])) ?>?')"
                                       title="Hapus">
                                        <i class="mdi mdi-delete"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Log Sync -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="mdi mdi-history me-1"></i>Log Sync Terakhir</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($logs)): ?>
                <p class="text-muted text-center py-4">Belum ada log sync.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mesin</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Records</th>
                                <th>Keterangan</th>
                                <th>Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['machine_name'] ?? '-') ?></td>
                                <td class="text-center">
                                    <?php if ($log['status'] === 'success'): ?>
                                        <span class="badge bg-success">Berhasil</span>
                                    <?php elseif ($log['status'] === 'failed'): ?>
                                        <span class="badge bg-danger">Gagal</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($log['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= (int)$log['records'] ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($log['message'] ?? '') ?></td>
                                <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ====================================================
         KOLOM KANAN: Panduan + Sync Manual
    ===================================================== -->
    <div class="col-lg-4">
        <!-- Sync Manual -->
        <div class="card border-success">
            <div class="card-header bg-soft-success">
                <h5 class="card-title mb-0 text-success"><i class="mdi mdi-cloud-download me-1"></i>Tarik Data Manual</h5>
            </div>
            <div class="card-body">
                <p class="small text-muted">Pilih mesin dan periode untuk menarik data absensi sekarang.</p>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Mesin</label>
                    <select class="form-select form-select-sm" id="quick_machine_id">
                        <option value="">— Pilih Mesin —</option>
                        <?php foreach ($machines as $m): ?>
                        <?php if ($m['is_active']): ?>
                        <option value="<?= $m['id'] ?>">
                            <?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['machine_sn'] ?? '') ?>)
                        </option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Bulan</label>
                        <select class="form-select form-select-sm" id="quick_month">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $i == date('n') ? 'selected' : '' ?>>
                                <?= date('F', mktime(0,0,0,$i,1)) ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Tahun</label>
                        <input type="number" class="form-control form-control-sm" id="quick_year"
                               value="<?= date('Y') ?>" min="2020" max="2099">
                    </div>
                </div>
                <button class="btn btn-success w-100" onclick="syncManual()" id="btnSyncManual">
                    <i class="mdi mdi-cloud-download me-1"></i>Tarik Data Sekarang
                </button>
                <div id="syncResult" class="mt-2 d-none"></div>
            </div>
        </div>

        <!-- Panduan -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="mdi mdi-help-circle me-1"></i>Panduan Konfigurasi</h5>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2 fw-semibold">Cara kerja sistem:</p>
                <ol class="small text-muted ps-3 mb-3">
                    <li class="mb-1">Login ke <strong>Solution Cloud</strong> pakai ID Mesin (SN) dan Password.</li>
                    <li class="mb-1">Download file <code>*.dat</code> berisi log absensi.</li>
                    <li class="mb-1">Data disimpan di <code>uploads/attendance/</code> dan siap diimport ke presensi.</li>
                </ol>

                <p class="small text-muted mb-2 fw-semibold">ID Mesin (SN):</p>
                <ul class="small text-muted ps-3 mb-3">
                    <li>Cek label belakang mesin fingerprint</li>
                    <li>Atau cek di <a href="http://solutioncloud.co.id/" target="_blank">solutioncloud.co.id</a></li>
                    <li>Contoh: <code>BWXP212160931</code></li>
                </ul>

                <p class="small text-muted mb-2 fw-semibold">Password mesin:</p>
                <ul class="small text-muted ps-3 mb-3">
                    <li>Default: <code>solution</code></li>
                    <li>Ditentukan saat registrasi mesin ke Solution Cloud</li>
                </ul>

                <hr>
                <p class="small text-muted mb-1 fw-semibold">Tipe mesin:</p>
                <ul class="small text-muted ps-3 mb-3">
                    <li><span class="badge bg-primary">Absensi</span> — data masuk/keluar kerja</li>
                    <li><span class="badge bg-warning text-dark">Sholat</span> — data sholat berjamaah</li>
                </ul>

                <hr>
                <p class="small text-muted mb-1 fw-semibold">Cron URL auto-sync:</p>
                <div class="bg-light p-2 rounded">
                    <code class="small"><?= site_url('sync/cron') ?></code>
                </div>
                <p class="small text-muted mt-2">Jalankan setiap menit via cron job server.</p>
            </div>
        </div>
    </div>
</div>

<!-- ===================================================================
     MODAL TAMBAH MESIN
=================================================================== -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-plus-circle me-1"></i>Tambah Mesin Absensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?= site_url('sync/add') ?>">
                <input type="hidden" name="myToken" value="<?= $this->security->get_csrf_hash() ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Mesin <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name"
                                   placeholder="Contoh: Mesin Kantor Pusat" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipe Mesin</label>
                            <select class="form-select" name="machine_type">
                                <option value="attendance">Absensi (masuk/keluar kerja)</option>
                                <option value="pray">Sholat</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ID Mesin / SN <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="machine_sn"
                                   placeholder="Contoh: BWXP212160931" required autocomplete="off">
                            <div class="form-text">Serial Number mesin dari Solution Cloud.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password Mesin</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" id="add_password"
                                       value="solution" autocomplete="new-password">
                                <button class="btn btn-outline-secondary" type="button"
                                        onclick="togglePass('add_password', this)">
                                    <i class="mdi mdi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Default: <code>solution</code></div>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Cabang</label>
                            <select class="form-select" name="branch_id">
                                <option value="">— Pilih Cabang —</option>
                                <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <hr class="my-2">
                            <h6 class="mb-3"><i class="mdi mdi-clock-outline me-1"></i>Jadwal Sync Otomatis</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox"
                                               name="auto_sync_enabled" value="1" id="add_auto_sync">
                                        <label class="form-check-label" for="add_auto_sync">
                                            <strong>Aktifkan Auto Sync</strong>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Jadwal <small class="text-muted">(pisah koma)</small></label>
                                    <input type="text" class="form-control" name="sync_times"
                                           placeholder="07:00, 13:00, 18:00">
                                    <div class="form-text">Format HH:MM. Contoh: <code>07:00, 12:30, 17:00</code></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active"
                                       value="1" id="add_is_active" checked>
                                <label class="form-check-label" for="add_is_active">Mesin Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-content-save me-1"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===================================================================
     MODAL EDIT MESIN
=================================================================== -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-pencil me-1"></i>Edit Mesin Absensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_id">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Mesin <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tipe Mesin</label>
                        <select class="form-select" id="edit_machine_type">
                            <option value="attendance">Absensi (masuk/keluar kerja)</option>
                            <option value="pray">Sholat</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">ID Mesin / SN <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_machine_sn" autocomplete="off">
                        <div class="form-text">Serial Number mesin dari Solution Cloud.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Password Mesin</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="edit_password"
                                   autocomplete="new-password" placeholder="Kosongkan jika tidak diubah">
                            <button class="btn btn-outline-secondary" type="button"
                                    onclick="togglePass('edit_password', this)">
                                <i class="mdi mdi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Kosongkan jika tidak ingin mengubah password.</div>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Cabang</label>
                        <select class="form-select" id="edit_branch_id">
                            <option value="">— Pilih Cabang —</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 mb-3">
                        <hr class="my-2">
                        <h6 class="mb-3"><i class="mdi mdi-clock-outline me-1"></i>Jadwal Sync Otomatis</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="edit_auto_sync">
                                    <label class="form-check-label" for="edit_auto_sync">
                                        <strong>Aktifkan Auto Sync</strong>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Jadwal <small class="text-muted">(pisah koma)</small></label>
                                <input type="text" class="form-control" id="edit_sync_times"
                                       placeholder="07:00, 13:00, 18:00">
                                <div class="form-text">Format HH:MM. Contoh: <code>07:00, 12:30, 17:00</code></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="edit_is_active">
                            <label class="form-check-label" for="edit_is_active">Mesin Aktif</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" onclick="simpanEdit()">
                    <i class="mdi mdi-content-save me-1"></i>Simpan Perubahan
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var csrf_name  = 'myToken';
var csrf_value = '<?= $this->security->get_csrf_hash() ?>';
var base_url   = '<?= site_url() ?>';

// Toggle visibility password
function togglePass(inputId, btn) {
    var el = document.getElementById(inputId);
    if (el.type === 'password') {
        el.type = 'text';
        btn.innerHTML = '<i class="mdi mdi-eye-off"></i>';
    } else {
        el.type = 'password';
        btn.innerHTML = '<i class="mdi mdi-eye"></i>';
    }
}

// Load data mesin lalu buka modal edit
function editMesin(id) {
    fetch(base_url + 'sync/get/' + id, {
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (!res.success) { alert('Gagal memuat data mesin.'); return; }
        var d = res.data;
        document.getElementById('edit_id').value            = d.id;
        document.getElementById('edit_name').value          = d.name;
        document.getElementById('edit_machine_sn').value    = d.machine_sn   || '';
        document.getElementById('edit_machine_type').value  = d.machine_type || 'attendance';
        document.getElementById('edit_password').value      = '';   // Jangan tampilkan password lama
        document.getElementById('edit_sync_times').value    = d.sync_times   || '';
        document.getElementById('edit_auto_sync').checked   = d.auto_sync_enabled == 1;
        document.getElementById('edit_is_active').checked   = d.is_active == 1;
        document.getElementById('edit_branch_id').value     = d.branch_id    || '';
        new bootstrap.Modal(document.getElementById('modalEdit')).show();
    });
}

// Simpan edit via AJAX
function simpanEdit() {
    var id = document.getElementById('edit_id').value;
    var formData = new FormData();
    formData.append(csrf_name,           csrf_value);
    formData.append('id',                id);
    formData.append('name',              document.getElementById('edit_name').value);
    formData.append('machine_sn',        document.getElementById('edit_machine_sn').value);
    formData.append('machine_type',      document.getElementById('edit_machine_type').value);
    formData.append('password',          document.getElementById('edit_password').value);
    formData.append('branch_id',         document.getElementById('edit_branch_id').value);
    formData.append('sync_times',        document.getElementById('edit_sync_times').value);
    formData.append('auto_sync_enabled', document.getElementById('edit_auto_sync').checked ? '1' : '0');
    formData.append('is_active',         document.getElementById('edit_is_active').checked ? '1' : '0');

    fetch(base_url + 'sync/update', {
        method:  'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body:    formData
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) { location.reload(); }
        else             { alert('Gagal: ' + res.message); }
    });
}

// Tombol "Sync Sekarang" di baris tabel — isi mesin lalu jalankan sync
function syncSekarang(machineId) {
    document.getElementById('quick_machine_id').value = machineId;
    syncManual();
}

// Tarik data dari Solution Cloud via AJAX
function syncManual() {
    var machineId = document.getElementById('quick_machine_id').value;
    var month     = document.getElementById('quick_month').value;
    var year      = document.getElementById('quick_year').value;
    var resultEl  = document.getElementById('syncResult');
    var btnEl     = document.getElementById('btnSyncManual');

    if (!machineId) {
        resultEl.className = 'mt-2 alert alert-warning';
        resultEl.innerHTML = 'Pilih mesin terlebih dahulu.';
        return;
    }

    btnEl.disabled  = true;
    btnEl.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i>Mengunduh data...';
    resultEl.className = 'mt-2 alert alert-info';
    resultEl.innerHTML = 'Menghubungi Solution Cloud, harap tunggu...';

    var formData = new FormData();
    formData.append(csrf_name,    csrf_value);
    formData.append('machine_id', machineId);
    formData.append('month',      month);
    formData.append('year',       year);

    fetch(base_url + 'sync/do_sync', {
        method:  'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body:    formData
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        btnEl.disabled  = false;
        btnEl.innerHTML = '<i class="mdi mdi-cloud-download me-1"></i>Tarik Data Sekarang';
        if (res.success) {
            resultEl.className = 'mt-2 alert alert-success';
            resultEl.innerHTML = '<strong>Berhasil!</strong><br>' + res.message;
            setTimeout(function() { location.reload(); }, 3000);
        } else {
            resultEl.className = 'mt-2 alert alert-danger';
            resultEl.innerHTML = '<strong>Gagal.</strong><br>' + res.message;
        }
    })
    .catch(function(e) {
        btnEl.disabled  = false;
        btnEl.innerHTML = '<i class="mdi mdi-cloud-download me-1"></i>Tarik Data Sekarang';
        resultEl.className = 'mt-2 alert alert-danger';
        resultEl.innerHTML = 'Terjadi kesalahan jaringan: ' + e.message;
    });
}

// Cek finger IDs dari mesin — query employee yang cocok
function checkFingers(machineSN) {
    var formData = new FormData();
    formData.append(csrf_name, csrf_value);
    formData.append('machine_sn', machineSN);

    var modalEl = document.getElementById('modalCheckFingers');
    var contentEl = document.getElementById('checkFingersContent');
    contentEl.innerHTML = '<div class="text-center py-4"><i class="mdi mdi-loading mdi-spin font-size-24"></i> Mengecek finger IDs...</div>';

    new bootstrap.Modal(modalEl).show();

    fetch(base_url + 'sync/check_fingers', {
        method:  'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body:    formData
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (!res.success) {
            contentEl.innerHTML = '<div class="alert alert-danger">' + res.message + '</div>';
            return;
        }

        var html = '<div class="mb-3">';
        html += '<h6><strong>Mesin:</strong> ' + res.machine_sn + ' | <strong>File:</strong> ' + res.file + '</h6>';
        html += '<p class="text-muted small">Total Finger IDs: ' + res.total_fingers + ' | Cocok: ' + res.matched_count + ' | Tidak cocok: ' + res.missing_count + '</p>';
        html += '</div>';

        if (res.matched_count > 0) {
            html += '<div class="mb-3"><h6>✅ Karyawan Cocok (' + res.matched_count + ')</h6>';
            html += '<div class="table-responsive" style="max-height:300px;overflow-y:auto">';
            html += '<table class="table table-sm table-bordered">';
            html += '<thead class="table-light"><tr><th>Kode</th><th>Nama</th><th>Posisi</th><th>Cabang</th><th>Status</th></tr></thead>';
            html += '<tbody>';
            res.matched.forEach(function(emp) {
                html += '<tr>';
                html += '<td><code>' + emp.code + '</code></td>';
                html += '<td>' + emp.name + '</td>';
                html += '<td>' + emp.position + '</td>';
                html += '<td>' + emp.branch + '</td>';
                html += '<td><span class="badge ' + (emp.active ? 'bg-success' : 'bg-secondary') + '">' + (emp.active ? 'Aktif' : 'Nonaktif') + '</span></td>';
                html += '</tr>';
            });
            html += '</tbody></table></div></div>';
        }

        if (res.missing_count > 0) {
            html += '<div class="alert alert-warning"><strong>⚠️ Finger IDs tidak cocok dengan employee (' + res.missing_count + '):</strong><br>';
            html += '<code>' + res.missing.join(', ') + '</code>';
            html += '<div class="small mt-2">Pastikan <strong>employee_code</strong> di tabel users sesuai dengan finger ID di mesin.</div></div>';
        }

        contentEl.innerHTML = html;
    })
    .catch(function(e) {
        contentEl.innerHTML = '<div class="alert alert-danger">Error: ' + e.message + '</div>';
    });
}
</script>

<!-- Modal Check Fingers Result -->
<div class="modal fade" id="modalCheckFingers" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-fingerprint me-1"></i>Cek Finger IDs Mesin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="checkFingersContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

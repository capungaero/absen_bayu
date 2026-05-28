<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0 font-size-18"><i class="mdi mdi-cog me-2"></i>Konfigurasi WA Agent</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= site_url('wa') ?>">WA Agent</a></li>
                    <li class="breadcrumb-item active">Konfigurasi</li>
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

<?php $cfg = !empty($config) ? $config : []; ?>

<?php if (empty($cfg['target_phones'])): ?>
<div class="alert alert-warning alert-dismissible fade show">
    <i class="mdi mdi-alert me-2"></i><strong>Nomor tujuan rekap belum diisi.</strong>
    Isi field "Nomor Tujuan Rekap" di bawah lalu klik Simpan Konfigurasi.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Form Konfigurasi -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="mdi mdi-webhook me-1"></i>Pengaturan Kirimi.id Webhook</h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= site_url('wa/save_config') ?>">
                    <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">

                    <!-- Status -->
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                <?= (!empty($cfg['is_active'])) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">
                                <strong>Aktifkan WA Agent</strong>
                            </label>
                        </div>
                    </div>

                    <hr>

                    <!-- User Code -->
                    <div class="mb-3">
                        <label class="form-label">User Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="user_code"
                               value="<?= htmlspecialchars($cfg['user_code'] ?? '') ?>"
                               placeholder="Contoh: USER_CODE_ANDA"
                               required>
                        <div class="form-text">User code dari dashboard kirimi.id (menu Profile / API).</div>
                    </div>

                    <!-- Secret Key -->
                    <div class="mb-3">
                        <label class="form-label">Secret Key <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="api_key" name="secret"
                                   value="<?= htmlspecialchars($cfg['secret'] ?? '') ?>"
                                   placeholder="Secret key dari dashboard kirimi.id"
                                   required>
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleApiKey()">
                                <i class="mdi mdi-eye" id="eye_icon"></i>
                            </button>
                        </div>
                        <div class="form-text">Dapatkan secret key dari <a href="https://dash.kirimi.id" target="_blank">dash.kirimi.id</a> &rarr; menu API.</div>
                    </div>

                    <!-- Device ID -->
                    <div class="mb-3">
                        <label class="form-label">Device ID <small class="text-muted">(opsional)</small></label>
                        <input type="text" class="form-control" name="device_id"
                               value="<?= htmlspecialchars($cfg['device_id'] ?? '') ?>"
                               placeholder="Contoh: DEVICE_ID_ANDA">
                        <div class="form-text">ID device dari dashboard kirimi.id. Kosongkan jika hanya punya 1 device.</div>
                    </div>

                    <!-- Nomor Target Rekap -->
                    <div class="mb-3">
                        <label class="form-label">Nomor Tujuan Rekap <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="target_phones" rows="3"
                                  placeholder="628xxxxxxxxxx, 628xxxxxxxxxx"><?= htmlspecialchars($cfg['target_phones'] ?? '') ?></textarea>
                        <div class="form-text">Pisahkan beberapa nomor dengan koma. Format: 628xxx (tanpa +, tanpa spasi).</div>
                    </div>

                    <hr>
                    <h6 class="mb-3"><i class="mdi mdi-clock-outline me-1"></i>Jadwal Pengiriman Otomatis</h6>

                    <!-- Rekap Pagi -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="send_morning_enabled"
                                       name="send_morning_enabled" value="1"
                                       <?= (!empty($cfg['send_morning_enabled'])) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="send_morning_enabled">
                                    <i class="mdi mdi-weather-sunny text-success"></i> Rekap Absen Pagi
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="mdi mdi-clock"></i></span>
                                <input type="time" class="form-control" name="morning_time"
                                       value="<?= htmlspecialchars($cfg['morning_time'] ?? '08:00') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Rekap Siang -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="send_afternoon_enabled"
                                       name="send_afternoon_enabled" value="1"
                                       <?= (!empty($cfg['send_afternoon_enabled'])) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="send_afternoon_enabled">
                                    <i class="mdi mdi-weather-partly-cloudy text-warning"></i> Rekap Absen Siang
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="mdi mdi-clock"></i></span>
                                <input type="time" class="form-control" name="afternoon_time"
                                       value="<?= htmlspecialchars($cfg['afternoon_time'] ?? '13:00') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Notif Tidak Hadir -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="notif_absent_enabled"
                                       name="notif_absent_enabled" value="1"
                                       <?= (!empty($cfg['notif_absent_enabled'])) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notif_absent_enabled">
                                    <i class="mdi mdi-account-alert text-danger"></i> Notif Karyawan Tidak Hadir
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="mdi mdi-clock"></i></span>
                                <input type="time" class="form-control" name="absent_notif_time"
                                       value="<?= htmlspecialchars($cfg['absent_notif_time'] ?? '09:00') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-content-save me-1"></i>Simpan Konfigurasi
                        </button>
                        <a href="<?= site_url('wa') ?>" class="btn btn-outline-secondary">Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Panel Kanan: Panduan + Test -->
    <div class="col-lg-5">
        <!-- Test Kirim Pesan -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="mdi mdi-message-text me-1"></i>Test Kirim Pesan</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Nomor HP</label>
                    <input type="text" class="form-control" id="test_phone"
                           placeholder="628xxxxxxxxxx">
                    <div class="form-text">Format: 628xxx (tanpa + atau 0)</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Pesan</label>
                    <textarea class="form-control" id="test_message" rows="3"
                              placeholder="Halo! Ini pesan test dari Sistem Absensi.">Halo! Ini pesan test dari Sistem Absensi <?= date('d/m/Y H:i') ?>.</textarea>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <button type="button" id="btn_test_send" class="btn btn-success flex-fill"
                            onclick="doTestSend()">
                        <i class="mdi mdi-send me-1"></i>Kirim Test
                    </button>
                    <span id="test_result" class="d-none"></span>
                </div>
            </div>
        </div>

        <!-- Panduan kirimi.id -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="mdi mdi-help-circle me-1"></i>Cara Setup Kirimi.id</h5>
            </div>
            <div class="card-body">
                <ol class="small text-muted ps-3">
                    <li class="mb-2">Daftar di <a href="https://kirimi.id" target="_blank">kirimi.id</a></li>
                    <li class="mb-2">Buat device baru, scan QR dengan WhatsApp</li>
                    <li class="mb-2">Copy <strong>User Code</strong> dan <strong>Secret Key</strong> dari menu API</li>
                    <li class="mb-2">Isi Device ID dari dashboard kirimi.id</li>
                    <li class="mb-2">Endpoint yang digunakan otomatis:<br>
                        <code class="small">POST https://api.kirimi.id/v1/send-message</code>
                    </li>
                    <li class="mb-2">Test kirim untuk memastikan koneksi berjalan</li>
                </ol>
                <hr>
                <p class="small text-muted mb-1"><strong>Format Request Body:</strong></p>
                <pre class="small bg-light p-2 rounded"><code>{
  "user_code": "USER_CODE_ANDA",
  "secret": "...",
  "device_id": "DEVICE_ID_ANDA",
  "phone": "628xxx",
  "message": "Teks pesan"
}</code></pre>
            </div>
        </div>
    </div>
</div>

<script>
function toggleApiKey() {
    var input = document.getElementById('api_key');
    var icon  = document.getElementById('eye_icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'mdi mdi-eye-off';
    } else {
        input.type = 'password';
        icon.className = 'mdi mdi-eye';
    }
}

function doTestSend() {
    var phone   = document.getElementById('test_phone').value.trim();
    var message = document.getElementById('test_message').value.trim();
    var btn     = document.getElementById('btn_test_send');
    var result  = document.getElementById('test_result');

    if (!phone || !message) {
        result.className = 'badge bg-danger fs-6';
        result.textContent = 'Gagal: isi nomor dan pesan';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Mengirim...';
    result.className = 'd-none';

    var csrf_name  = '<?= $this->security->get_csrf_token_name() ?>';
    var csrf_value = '<?= $this->security->get_csrf_hash() ?>';
    var formData   = new FormData();
    formData.append('test_phone', phone);
    formData.append('test_message', message);
    formData.append(csrf_name, csrf_value);

    fetch('<?= site_url('wa/test_send') ?>', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            result.className = 'badge bg-success fs-6';
            result.innerHTML = '<i class="mdi mdi-check me-1"></i>Terkirim';
        } else {
            result.className = 'badge bg-danger fs-6';
            result.innerHTML = '<i class="mdi mdi-close me-1"></i>Gagal';
            console.error(data.message);
        }
    })
    .catch(function() {
        result.className = 'badge bg-danger fs-6';
        result.innerHTML = '<i class="mdi mdi-close me-1"></i>Gagal';
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="mdi mdi-send me-1"></i>Kirim Test';
    });
}
</script>

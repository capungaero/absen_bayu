<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0 font-size-18"><i class="mdi mdi-whatsapp me-2" style="color:#25D366"></i>Notifikasi WA Absensi</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item">Tools</li>
                    <li class="breadcrumb-item active">Notifikasi WA Absensi</li>
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
    <i class="mdi mdi-alert me-2"></i><strong>Nomor tujuan notifikasi belum diisi.</strong>
    Isi minimal satu nomor tujuan sebelum mengaktifkan pengiriman otomatis.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Form Konfigurasi -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="mdi mdi-bell-cog-outline me-1"></i>Tujuan dan Jadwal</h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= site_url('wa/save_config') ?>">
                    <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                    <input type="hidden" name="user_code" value="<?= htmlspecialchars($cfg['user_code'] ?? '') ?>">
                    <input type="hidden" name="device_id" value="<?= htmlspecialchars($cfg['device_id'] ?? '') ?>">

                    <!-- Status -->
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                <?= (!empty($cfg['is_active'])) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">
                                <strong>Aktifkan pengiriman otomatis</strong>
                            </label>
                        </div>
                    </div>

                    <hr>

                    <!-- Nomor Target Rekap -->
                    <div class="mb-3">
                        <label class="form-label" for="target_phones">Nomor Tujuan <span class="text-muted">(wajib saat aktif)</span></label>
                        <textarea class="form-control" id="target_phones" name="target_phones" rows="3"
                                  inputmode="tel" autocomplete="tel"
                                  placeholder="628xxxxxxxxxx, 628xxxxxxxxxx"><?= htmlspecialchars($cfg['target_phones'] ?? '') ?></textarea>
                        <div class="form-text">Pisahkan beberapa nomor dengan koma atau baris baru. Nomor 08xx akan otomatis diubah ke 628xx.</div>
                    </div>

                    <hr>
                    <h6 class="mb-3"><i class="mdi mdi-clock-outline me-1"></i>Jadwal Pengiriman Otomatis</h6>

                    <!-- Rekap PDF dan pesan WA pagi -->
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
                                <span class="input-group-text">Pukul</span>
                                <input type="time" class="form-control" id="morning_time" name="morning_time" aria-label="Jam kirim rekap pagi" required
                                       value="<?= htmlspecialchars($cfg['morning_time'] ?? '08:50') ?>">
                                <span class="input-group-text">WIB</span>
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
                                <span class="input-group-text">Pukul</span>
                                <input type="time" class="form-control" id="afternoon_time" name="afternoon_time" aria-label="Jam kirim rekap siang" required
                                       value="<?= htmlspecialchars($cfg['afternoon_time'] ?? '12:50') ?>">
                                <span class="input-group-text">WIB</span>
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
                                    <i class="mdi mdi-account-alert text-danger"></i> Peringatan Belum Absen
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Pukul</span>
                                <input type="time" class="form-control" id="absent_notif_time" name="absent_notif_time" aria-label="Jam kirim peringatan belum absen" required
                                       value="<?= htmlspecialchars($cfg['absent_notif_time'] ?? '09:00') ?>">
                                <span class="input-group-text">WIB</span>
                            </div>
                        </div>
                    </div>

                    <details class="border rounded p-3 mt-4 mb-3">
                        <summary class="fw-semibold"><i class="mdi mdi-tune-variant me-1"></i>Pengaturan Gateway</summary>
                        <div class="mt-3">
                            <label class="form-label" for="api_key">API Key Hermes <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="api_key" name="secret"
                                       value="" placeholder="Kosongkan jika tidak diubah" autocomplete="new-password">
                                <button class="btn btn-outline-secondary" type="button" onclick="toggleApiKey()" aria-label="Tampilkan atau sembunyikan API key" title="Tampilkan API key">
                                    <i class="mdi mdi-eye" id="eye_icon"></i>
                                </button>
                            </div>
                        </div>
                    </details>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-content-save me-1"></i>Simpan Pengaturan
                        </button>
                        <a href="<?= site_url('wa') ?>" class="btn btn-outline-secondary"><i class="mdi mdi-view-dashboard me-1"></i>Dashboard Pengiriman</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Panel Kanan -->
    <div class="col-lg-5">
        <!-- Test Kirim Pesan -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="mdi mdi-message-text me-1"></i>Test Kirim Pesan</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Nomor HP</label>
                    <input type="text" class="form-control" id="test_phone" inputmode="tel" autocomplete="tel"
                           placeholder="628xxxxxxxxxx">
                    <div class="form-text">Format: 628xxx (tanpa + atau 0)</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Pesan</label>
                    <textarea class="form-control" id="test_message" rows="3"
                              placeholder="Halo! Ini pesan test dari Sistem Absensi.">Halo! Ini pesan test dari Sistem Absensi <?= date('d/m/Y H:i') ?>.</textarea>
                </div>
                <div>
                    <button type="button" id="btn_test_send" class="btn btn-success w-100"
                            onclick="doTestSend()">
                        <i class="mdi mdi-send me-1"></i>Kirim Test
                    </button>
                    <div id="test_result" class="d-none mt-3" role="status" aria-live="polite"></div>
                </div>
            </div>
        </div>

        <!-- Status -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="mdi mdi-information-outline me-1"></i>Status Pengiriman</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span>Otomatisasi</span>
                    <span class="badge <?= !empty($cfg['is_active']) ? 'bg-success' : 'bg-secondary' ?>"><?= !empty($cfg['is_active']) ? 'Aktif' : 'Nonaktif' ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span>Nomor tujuan</span>
                    <strong><?= count(array_filter(array_map('trim', explode(',', $cfg['target_phones'] ?? '')))) ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span>Gateway</span>
                    <span class="badge <?= !empty($cfg['secret']) ? 'bg-success' : 'bg-danger' ?>"><?= !empty($cfg['secret']) ? 'Dikonfigurasi' : 'Belum diatur' ?></span>
                </div>
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
        result.className = 'alert alert-danger mt-3 mb-0 py-2';
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
            result.className = 'alert alert-success mt-3 mb-0 py-2';
            result.innerHTML = '<i class="mdi mdi-check me-1"></i>' + data.message;
        } else {
            result.className = 'alert alert-danger mt-3 mb-0 py-2';
            result.innerHTML = '<i class="mdi mdi-close me-1"></i>' + data.message;
        }
    })
    .catch(function() {
        result.className = 'alert alert-danger mt-3 mb-0 py-2';
        result.innerHTML = '<i class="mdi mdi-close me-1"></i>Server tidak dapat dihubungi.';
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="mdi mdi-send me-1"></i>Kirim Test';
    });
}
</script>

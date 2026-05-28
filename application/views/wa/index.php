<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0 font-size-18"><i class="mdi mdi-whatsapp me-2 text-success"></i>WA Agent Dashboard</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">WA Agent</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php if ($this->session->flashdata('rekap_success')): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="mdi mdi-check-circle me-2"></i><?= $this->session->flashdata('rekap_success') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($this->session->flashdata('rekap_error')): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="mdi mdi-alert-circle me-2"></i><?= $this->session->flashdata('rekap_error') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($this->session->flashdata('rekap_info')): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="mdi mdi-information me-2"></i><?= $this->session->flashdata('rekap_info') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Status Config -->
<?php $cfg_active = !empty($config) && $config['is_active']; ?>
<div class="row">
    <div class="col-12">
        <div class="alert <?= $cfg_active ? 'alert-success' : 'alert-warning' ?> d-flex align-items-center">
            <i class="mdi <?= $cfg_active ? 'mdi-check-circle' : 'mdi-alert' ?> font-size-20 me-2"></i>
            <div>
                <?php if ($cfg_active): ?>
                    WA Agent <strong>Aktif</strong>. Device: <code><?= htmlspecialchars($config['device_id'] ?? '') ?></code>
                <?php else: ?>
                    WA Agent <strong>belum aktif</strong> atau belum dikonfigurasi.
                    <a href="<?= site_url('wa/config') ?>" class="alert-link">Atur Konfigurasi</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Rekap Hari Ini -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h5 class="card-title mb-0"><i class="mdi mdi-chart-bar me-1"></i>Rekap Absen Hari Ini — <?= date('d/m/Y') ?></h5>
                    <button type="button" id="btnCheckAttendance" class="btn btn-sm btn-primary">
                        <i class="mdi mdi-cloud-sync me-1"></i>Cek Absen
                    </button>
                </div>
                <div id="checkAttendanceAlert" class="alert d-none mb-3" role="alert"></div>
                <?php if (!empty($summary)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Shift</th>
                                <th>Cabang</th>
                                <th class="text-center">Jam</th>
                                <th class="text-center">Total Tugas</th>
                                <th class="text-center text-success">Hadir</th>
                                <th class="text-center text-warning">Terlambat</th>
                                <th class="text-center" style="color:#e83e8c">Sakit</th>
                                <th class="text-center text-info">Izin/Cuti</th>
                                <th class="text-center text-danger">Tidak Hadir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summary as $row): ?>
                            <?php
                                $terlambat   = isset($row['terlambat']) ? (int)$row['terlambat'] : 0;
                                $hadir_total = (int)$row['hadir'] + $terlambat;
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars(($row['shift_code'] ?? '') . ' - ' . ($row['shift_name'] ?? '')) ?></strong>
                                    <div class="text-muted small">Batas telat: <?= !empty($row['start_time_late']) ? date('H:i', strtotime($row['start_time_late'])) : '-' ?></div>
                                </td>
                                <td><?= htmlspecialchars($row['branch_name']) ?></td>
                                <td class="text-center"><?= !empty($row['start_time']) ? date('H:i', strtotime($row['start_time'])) : '-' ?></td>
                                <td class="text-center"><?= $row['total_employee'] ?></td>
                                <td class="text-center"><span class="badge bg-success"><?= $hadir_total ?></span></td>
                                <td class="text-center">
                                    <span class="badge bg-warning text-dark"><?= $terlambat ?></span>
                                    <?php if (!empty($row['late_employees'])): ?>
                                    <div class="text-start small mt-1">
                                        <?php foreach ($row['late_employees'] as $emp): ?>
                                            <div><?= htmlspecialchars($emp['name']) ?> (<?= (int)$emp['late_minutes'] ?> menit)</div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><span class="badge" style="background:#e83e8c"><?= $row['sakit'] ?></span></td>
                                <td class="text-center"><span class="badge bg-info"><?= $row['izin'] ?></span></td>
                                <td class="text-center">
                                    <span class="badge bg-danger"><?= $row['tidak_hadir'] ?></span>
                                    <?php if (!empty($row['absent_employees'])): ?>
                                    <div class="text-start small mt-1">
                                        <?php foreach ($row['absent_employees'] as $emp): ?>
                                            <div><?= htmlspecialchars($emp['name']) ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted">Belum ada data presensi hari ini.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tombol Kirim Manual -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="mdi mdi-send me-1"></i>Kirim Pesan Manual</h5>
                <p class="text-muted small">Kirim pesan sekarang tanpa menunggu jadwal otomatis.</p>
                <div class="d-flex flex-wrap gap-2">
                    <!-- Rekap Pagi -->
                    <form method="post" action="<?= site_url('wa/send_rekap_pagi') ?>" onsubmit="return confirm('Kirim rekap pagi sekarang?')">
                        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                        <button type="submit" class="btn btn-success <?= !$cfg_active ? 'disabled' : '' ?>">
                            <i class="mdi mdi-weather-sunny me-1"></i>Kirim Rekap Pagi
                        </button>
                    </form>

                    <!-- Rekap Siang -->
                    <form method="post" action="<?= site_url('wa/send_rekap_siang') ?>" onsubmit="return confirm('Kirim rekap siang sekarang?')">
                        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                        <button type="submit" class="btn btn-warning <?= !$cfg_active ? 'disabled' : '' ?>">
                            <i class="mdi mdi-weather-partly-cloudy me-1"></i>Kirim Rekap Siang
                        </button>
                    </form>

                    <!-- Notif Tidak Hadir -->
                    <form method="post" action="<?= site_url('wa/send_notif_absen') ?>" onsubmit="return confirm('Kirim notifikasi tidak hadir sekarang?')">
                        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                        <button type="submit" class="btn btn-danger <?= !$cfg_active ? 'disabled' : '' ?>">
                            <i class="mdi mdi-account-alert me-1"></i>Kirim Notif Tidak Hadir
                        </button>
                    </form>
                </div>
                <?php if (!$cfg_active): ?>
                <p class="text-danger small mt-2"><i class="mdi mdi-alert"></i> Aktifkan WA Agent di <a href="<?= site_url('wa/config') ?>">Konfigurasi</a> untuk menggunakan fitur ini.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Jadwal Otomatis Info -->
<?php if ($cfg_active && !empty($config)): ?>
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body text-center">
                <i class="mdi mdi-weather-sunny font-size-28 text-success"></i>
                <h6 class="mt-2">Rekap Pagi</h6>
                <h4 class="text-success"><?= htmlspecialchars($config['morning_time']) ?></h4>
                <span class="badge <?= $config['send_morning_enabled'] ? 'bg-success' : 'bg-secondary' ?>">
                    <?= $config['send_morning_enabled'] ? 'Aktif' : 'Nonaktif' ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-warning">
            <div class="card-body text-center">
                <i class="mdi mdi-weather-partly-cloudy font-size-28 text-warning"></i>
                <h6 class="mt-2">Rekap Siang</h6>
                <h4 class="text-warning"><?= htmlspecialchars($config['afternoon_time']) ?></h4>
                <span class="badge <?= $config['send_afternoon_enabled'] ? 'bg-success' : 'bg-secondary' ?>">
                    <?= $config['send_afternoon_enabled'] ? 'Aktif' : 'Nonaktif' ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-danger">
            <div class="card-body text-center">
                <i class="mdi mdi-account-alert font-size-28 text-danger"></i>
                <h6 class="mt-2">Notif Tidak Hadir</h6>
                <h4 class="text-danger"><?= htmlspecialchars($config['absent_notif_time']) ?></h4>
                <span class="badge <?= $config['notif_absent_enabled'] ? 'bg-success' : 'bg-secondary' ?>">
                    <?= $config['notif_absent_enabled'] ? 'Aktif' : 'Nonaktif' ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Cron URL Info -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="mdi mdi-clock-outline me-1"></i>URL Cron Job (Jadwal Otomatis)</h5>
                <p class="text-muted small">Daftarkan URL ini di cron job server Anda (setiap menit / setiap jam):</p>
                <?php $cron_token = md5(($config['secret'] ?? '') . 'cron_secret'); ?>
                <div class="input-group">
                    <input type="text" id="cron_url" class="form-control font-monospace"
                           value="<?= site_url('wa/cron/' . $cron_token) ?>" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="copyCronUrl()">
                        <i class="mdi mdi-content-copy"></i> Salin
                    </button>
                </div>
                <p class="text-muted small mt-1">
                    <i class="mdi mdi-information"></i>
                    Contoh cron: <code>* * * * * curl -s "<?= site_url('wa/cron/' . $cron_token) ?>" > /dev/null</code>
                </p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Log Terbaru -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0"><i class="mdi mdi-history me-1"></i>Log Pengiriman Terbaru</h5>
                    <a href="<?= site_url('wa/logs') ?>" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                </div>
                <?php if (!empty($logs)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu</th>
                                <th>Tipe</th>
                                <th>Nomor</th>
                                <th>Status</th>
                                <th>HTTP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="small"><?= date('d/m H:i', strtotime($log['created_at'])) ?></td>
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
                                        <span class="badge bg-success">Terkirim</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Gagal</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= $log['http_code'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted">Belum ada log pengiriman.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function copyCronUrl() {
    var input = document.getElementById('cron_url');
    input.select();
    document.execCommand('copy');
    alert('URL Cron berhasil disalin!');
}

$(document).on('click', '#btnCheckAttendance', function(){
    var btn = $(this);
    var alertBox = $('#checkAttendanceAlert');

    $.ajax({
        url: "<?= site_url('wa/check_absen_today') ?>",
        dataType: "json",
        method: "POST",
        data: {
            "<?= $this->security->get_csrf_token_name() ?>": "<?= $this->security->get_csrf_hash() ?>"
        },
        beforeSend: function(){
            btn.attr('disabled', 'disabled').html('<i class="mdi mdi-loading mdi-spin me-1"></i>Mengecek...');
            alertBox.removeClass('d-none alert-success alert-danger alert-info')
                    .addClass('alert-info')
                    .html('Mengambil data fingerprint hari ini dan membandingkan dengan jadwal...');
        },
        success: function(res){
            alertBox.removeClass('alert-info alert-success alert-danger')
                    .addClass(res.success ? 'alert-success' : 'alert-danger')
                    .html(res.message || 'Cek absen selesai.');

            if(res.success){
                setTimeout(function(){ window.location.reload(); }, 900);
            }
        },
        error: function(){
            alertBox.removeClass('alert-info alert-success')
                    .addClass('alert-danger')
                    .html('Cek absen gagal. Coba ulangi atau periksa koneksi Solution Cloud.');
        },
        complete: function(){
            btn.removeAttr('disabled').html('<i class="mdi mdi-cloud-sync me-1"></i>Cek Absen');
        }
    });
});
</script>

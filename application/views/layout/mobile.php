<?php
/**
 * Layout Mobile — kerangka untuk semua halaman /m.
 * Variabel: $contents, $active_menu, $role, $is_approver, $userdata, $pending_total
 */
$is_approver   = isset($is_approver) ? $is_approver : false;
$active_menu   = isset($active_menu) ? $active_menu : '';
$pending_total = isset($pending_total) ? (int)$pending_total : 0;
$first_name    = isset($userdata) ? $userdata->first_name : '';

// Menu bottom nav berbeda untuk atasan (approver) dan karyawan biasa.
if ($is_approver) {
    $nav = [
        ['m',           'home',        'home',     'Home'],
        ['m/approvals', 'approvals',   'fact_check','Approval'],
        ['m/presence',  'presence',    'schedule', 'Absensi'],
        ['m/overtime',  'overtime',    'more_time','Lembur'],
        ['m/leave',     'leave',       'event_busy','Izin'],
    ];
} else {
    $nav = [
        ['m',          'home',     'home',                    'Home'],
        ['m/presence', 'presence', 'schedule',                'Absensi'],
        ['m/overtime', 'overtime', 'more_time',               'Lembur'],
        ['m/leave',    'leave',    'event_busy',              'Izin'],
        ['m/payroll',  'payroll',  'account_balance_wallet',  'Gaji'],
    ];
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>E-ABSENSI Mobile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#4e73df">
    <meta name="csrf-name" content="<?= $this->security->get_csrf_token_name() ?>">
    <meta name="csrf-hash" content="<?= $this->security->get_csrf_hash() ?>">

    <link rel="shortcut icon" href="<?= base_url() ?>assets/images/favicon.ico">
    <link href="<?= base_url() ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= base_url() ?>assets/libs/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="<?= base_url() ?>assets/css/mobile.css" rel="stylesheet">

    <script src="<?= base_url() ?>assets/libs/jquery/jquery.min.js"></script>
</head>
<body>

    <!-- Header -->
    <div class="mobile-header">
        <div class="d-flex align-items-center justify-content-between w-100">
            <b class="brand-logo">E-ABSENSI</b>
            <div class="d-flex align-items-center">
                <span class="user-greeting me-2">Hi, <?= htmlspecialchars($first_name) ?></span>
                <a href="<?= site_url('logout') ?>" class="btn-icon" title="Keluar">
                    <i class="material-icons">logout</i>
                </a>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="mobile-content">
        <?= $contents ?>
    </div>

    <!-- Bottom Nav -->
    <div class="mobile-bottom-nav">
        <?php foreach ($nav as $item): ?>
            <a href="<?= site_url($item[0]) ?>" class="nav-item <?= $active_menu == $item[1] ? 'active' : '' ?>">
                <span class="nav-icon-wrap">
                    <i class="material-icons"><?= $item[2] ?></i>
                    <?php if ($item[1] === 'approvals' && $pending_total > 0): ?>
                        <span class="nav-badge"><?= $pending_total > 99 ? '99+' : $pending_total ?></span>
                    <?php endif; ?>
                </span>
                <span><?= $item[3] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <script src="<?= base_url() ?>assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?= base_url() ?>assets/libs/sweetalert2/sweetalert2.min.js"></script>
    <script>
    // CSRF: token diregenerasi tiap request (csrf_regenerate=TRUE) & cookie httponly,
    // jadi setiap response AJAX mengembalikan hash baru yang kita simpan untuk request berikutnya.
    var CSRF = {
        name: $('meta[name="csrf-name"]').attr('content'),
        hash: $('meta[name="csrf-hash"]').attr('content')
    };
    function csrfUpdate(res){ if (res && res.csrf){ CSRF.hash = res.csrf; } }

    /** POST data biasa (object) + csrf, auto-refresh token. */
    function mPost(url, data, done){
        data = data || {};
        data[CSRF.name] = CSRF.hash;
        return $.ajax({ url:url, type:'POST', data:data, dataType:'json' })
            .done(function(res){ csrfUpdate(res); if(done) done(res); })
            .fail(function(){ Swal.fire({icon:'error', title:'Error', text:'Terjadi kesalahan jaringan. Coba lagi.'}); });
    }

    /** POST FormData (upload file) + csrf, auto-refresh token. */
    function mPostForm(url, formEl, done){
        var fd = new FormData(formEl);
        fd.append(CSRF.name, CSRF.hash);
        return $.ajax({ url:url, type:'POST', data:fd, dataType:'json', processData:false, contentType:false })
            .done(function(res){ csrfUpdate(res); if(done) done(res); })
            .fail(function(){ Swal.fire({icon:'error', title:'Error', text:'Terjadi kesalahan jaringan. Coba lagi.'}); });
    }

    function toast(ok, msg, reload){
        Swal.fire({ icon: ok?'success':'error', title: ok?'Berhasil':'Gagal', text: msg,
            timer: ok?1600:undefined, showConfirmButton: !ok
        }).then(function(){ if(ok && reload) location.reload(); });
    }
    </script>
</body>
</html>

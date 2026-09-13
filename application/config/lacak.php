<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['lacak_attendance'] = [
    'url' => getenv('LACAK_ATTENDANCE_URL') ?: 'http://127.0.0.1:8100/api/driver/logs',
    'username' => getenv('LACAK_ADMIN_USER') ?: '',
    'password' => getenv('LACAK_ADMIN_PASSWORD') ?: '',
    'timeout' => 15,
];

$local = __DIR__.DIRECTORY_SEPARATOR.'lacak.local.php';
if (is_file($local)) {
    require $local;
}

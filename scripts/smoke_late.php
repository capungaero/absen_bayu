<?php
if (!defined('BASEPATH')) define('BASEPATH', '/');
require __DIR__ . '/../application/helpers/late_helper.php';

$cases = [
    ['07:50:00', '07:50:07', 0,  'detik dipotong'],
    ['07:50:00', '07:51:00', 1,  'lewat 1 menit'],
    ['07:50',    '08:05',    15, 'lewat 15 menit'],
    ['07:50',    '07:49',    0,  'sebelum batas'],
    [null,       '08:00',    0,  'limit null'],
    ['07:50',    '',         0,  'time kosong'],
    ['07:50',    '07:50',    0,  'tepat batas'],
];

$pass = $fail = 0;
foreach ($cases as $c) {
    $r = late_minutes($c[0], $c[1]);
    $ok = ($r === $c[2]);
    if ($ok) { $pass++; } else { $fail++; }
    printf("%-10s vs %-10s => %3d expect %3d  %s  (%s)\n",
        var_export($c[0], true), var_export($c[1], true), $r, $c[2],
        $ok ? 'OK' : 'FAIL', $c[3]);
}
printf("\n%d pass / %d fail\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);

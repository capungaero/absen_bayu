<?php
/**
 * Smoke test untuk helper Izin Pulang Cepat (PLA).
 *
 * Cover:
 *   - presence_net_work_minutes()
 *   - presence_expected_net_minutes()
 *   - presence_early_leave_short_minutes()
 *   - presence_hourly_rate()
 *   - presence_early_leave_deduction_amount()
 *
 * Tidak boot CI. Pure PHP.
 *
 * Jalankan: php scripts/smoke_early_leave.php
 */

if (!defined('BASEPATH')) define('BASEPATH', '/');
require __DIR__ . '/../application/helpers/presence_helper.php';

$fail = 0;
$check = function ($label, $actual, $want) use (&$fail) {
    $ok = ($actual === $want);
    $tag = $ok ? 'OK  ' : 'FAIL';
    if (!$ok) {
        $fail++;
        $a = is_array($actual) ? json_encode($actual) : var_export($actual, true);
        $w = is_array($want) ? json_encode($want) : var_export($want, true);
        echo "$tag $label\n  actual: $a\n  want:   $w\n";
    } else {
        echo "$tag $label\n";
    }
};

echo "=== presence_net_work_minutes ===\n";
// Basic: 08:00 -> 17:00, rest 12:00-13:00 -> 9h gross - 1h rest = 8h = 480
$check('shift normal 8 jam net', presence_net_work_minutes('08:00:00', '17:00:00', '12:00:00', '13:00:00'), 480);
// Tanpa istirahat lengkap (rest_out null) -> 9h gross = 540
$check('tanpa rest lengkap',     presence_net_work_minutes('08:00:00', '17:00:00', '12:00:00', null),       540);
// Out_time sebelum entry -> 0
$check('out sebelum entry',      presence_net_work_minutes('17:00:00', '08:00:00'),                        0);
// Entry kosong -> 0
$check('entry kosong',           presence_net_work_minutes('', '17:00:00'),                                0);
// Datetime full format -> 5 jam = 300
$check('datetime format',        presence_net_work_minutes('2026-05-10 08:00:00', '2026-05-10 14:30:00'),  390);

echo "\n=== presence_expected_net_minutes ===\n";
$shift_8h = ['start_time' => '08:00:00', 'end_time' => '17:00:00', 'rest_time_range' => 60];
$shift_4h = ['start_time' => '08:00:00', 'end_time' => '13:00:00', 'rest_time_range' => 60];
$check('shift 8h dengan 60min rest -> 480', presence_expected_net_minutes($shift_8h), 480);
$check('shift 4h dengan 60min rest -> 240', presence_expected_net_minutes($shift_4h), 240);
$check('shift kosong -> 0',                  presence_expected_net_minutes([]),       0);

echo "\n=== presence_early_leave_short_minutes ===\n";
// Pulang 15:00 (2h lebih awal), rest 12:00-13:00 -> actual 6h, expected 8h -> short 2h = 120
$check('pulang 2 jam awal -> short 120',
    presence_early_leave_short_minutes($shift_8h, '08:00:00', '15:00:00', '12:00:00', '13:00:00'),
    120
);
// Pulang persis di shift end -> short 0
$check('pulang tepat waktu -> short 0',
    presence_early_leave_short_minutes($shift_8h, '08:00:00', '17:00:00', '12:00:00', '13:00:00'),
    0
);
// Pulang setelah shift end -> short 0 (tidak negatif)
$check('pulang setelah shift -> short 0',
    presence_early_leave_short_minutes($shift_8h, '08:00:00', '18:00:00', '12:00:00', '13:00:00'),
    0
);

echo "\n=== presence_hourly_rate ===\n";
// GP 3,100,000 / 31 hari / 10 jam = 10000/jam
$check('salary 3,100,000 / 31 / 10 = 10000', presence_hourly_rate(3100000, 31), 10000.0);
// GP 3,000,000 / 30 / 10 = 10000/jam
$check('salary 3,000,000 / 30 / 10 = 10000', presence_hourly_rate(3000000, 30), 10000.0);
// Salary 0
$check('salary 0 -> 0', presence_hourly_rate(0, 31), 0.0);
// Days 0 (defensive)
$check('days 0 -> 0', presence_hourly_rate(3000000, 0), 0.0);

echo "\n=== presence_early_leave_deduction_amount ===\n";
// salary 3,100,000 / 31 / 10 = 10000 per jam. Short 120 menit = 2 jam -> 20000
$check('120 menit short, salary 3.1M, 31 hari -> 20000',
    presence_early_leave_deduction_amount(3100000, 31, 120),
    20000
);
// 90 menit = 1.5 jam -> 15000
$check('90 menit short -> 15000',
    presence_early_leave_deduction_amount(3100000, 31, 90),
    15000
);
// 0 menit -> 0
$check('0 menit short -> 0',
    presence_early_leave_deduction_amount(3100000, 31, 0),
    0
);
// 45 menit = 0.75 jam -> 10000 * 0.75 = 7500
$check('45 menit short -> 7500',
    presence_early_leave_deduction_amount(3100000, 31, 45),
    7500
);

echo "\n";
if ($fail > 0) {
    echo "$fail assertion gagal\n";
    exit(1);
}
echo "Semua assertion lewat\n";
exit(0);

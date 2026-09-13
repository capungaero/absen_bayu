<?php
define('BASEPATH', __DIR__);

function &get_instance() {
    static $instance = null;
    return $instance;
}

require __DIR__.'/../application/libraries/Attendance_daily_report.php';

$report = [
    'date' => '2026-09-13',
    'generated_at' => '2026-09-13 08:50:00',
    'totals' => [
        'scheduled' => 2, 'hadir' => 1, 'terlambat' => 0,
        'izin' => 0, 'sakit' => 0, 'belum' => 1, 'off' => 0,
        'missing_shift' => 1,
    ],
    'branches' => [[
        'branch_name' => 'Sudirman', 'total' => 2, 'hadir' => 1,
        'terlambat' => 0, 'belum' => 1, 'percent' => 50,
        'details' => [
            ['name' => 'Budi', 'position_name' => 'Kasir', 'branch_name' => 'Sudirman',
                'status' => 'hadir', 'entry_time' => '2026-09-13 07:45:00', 'late_minutes' => 0, 'source' => 'Lacak'],
            ['name' => 'Ani', 'position_name' => 'Admin', 'branch_name' => 'Sudirman',
                'status' => 'alfa', 'entry_time' => null, 'late_minutes' => 0, 'source' => ''],
        ],
    ]],
    'missing_shift' => [[
        'name' => 'Citra', 'position_name' => 'Pramuniaga', 'branch_name' => 'Gambir',
    ]],
];

$formatter = new Attendance_daily_report();
$message = $formatter->build_message($report, 'pagi');
$warning = $formatter->build_shift_warning_message($report, 'pagi');

$checks = [
    strpos($message, 'REKAP ABSENSI PAGI') !== false,
    strpos($message, 'ANI (ADMIN)') !== false,
    strpos($message, 'WARNING BELUM ADA SHIFT') !== false,
    strpos($message, 'CITRA (PRAMUNIAGA)') !== false,
    strpos($warning, 'BELUM ADA SHIFT HARI INI') !== false,
    strpos($warning, 'GAMBIR') !== false,
    strpos($message, 'Minggu, 13 September 2026') !== false,
];

if (in_array(false, $checks, true)) {
    fwrite(STDERR, "Attendance daily report test failed.\n");
    exit(1);
}

echo "Attendance daily report test passed.\n";

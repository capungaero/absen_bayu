<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '1');

require __DIR__ . '/../lib/vendor/autoload.php';
require __DIR__ . '/absen_report_data.php';
require __DIR__ . '/absen_report_xlsx.php';

define('BASEPATH', __DIR__ . '/../');
$db = [];
require __DIR__ . '/../application/config/database.php';

$opts = getopt('', ['year::', 'month::', 'branch::', 'employee-codes::', 'scope::', 'as-of::', 'out::']);
$year = isset($opts['year']) ? (int) $opts['year'] : (int) date('Y');
$month = isset($opts['month']) ? (int) $opts['month'] : (int) date('m');

$cfg = $db['default'];
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli($cfg['hostname'], $cfg['username'], $cfg['password'], $cfg['database'], (int) $cfg['port']);
$mysqli->set_charset('utf8');

$result = generateAbsenReport(
    $mysqli,
    $year,
    $month,
    $opts['branch'] ?? null,
    $opts['employee-codes'] ?? null,
    $opts['scope'] ?? null,
    $opts['as-of'] ?? null
);

$out = $opts['out'] ?? sprintf('exports/report_absen_%04d_%02d_%s.xlsx', $year, $month, $result['scope']);
$outPath = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $out);
if (!is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0777, true);
}

writeWorkbook($outPath, $result['report'], $result['meta']);

echo "OK\n";
echo "file=" . $outPath . "\n";
echo "url=http://127.0.0.1:8080/" . str_replace('\\', '/', $out) . "\n";
echo "employees=" . $result['employees'] . "\n";
echo "anomalies=" . $result['anomalies'] . "\n";

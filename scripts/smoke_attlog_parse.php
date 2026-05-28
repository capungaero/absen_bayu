<?php
/**
 * Smoke test untuk Attlog_parser::parse_taps().
 *
 * Tujuan:
 *   - Pastikan parser tetap bisa baca format .dat real (5 kolom tab-separated).
 *   - Pastikan baris invalid/luar periode dihitung dengan benar.
 *   - Pastikan time array dipertahankan urutannya seperti file.
 *
 * Jalankan: php scripts/smoke_attlog_parse.php
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', '/');
}

require __DIR__ . '/../application/libraries/Attlog_parser.php';

$fixture_dir = __DIR__ . '/../tests/fixtures/attlog';
$dat_file = $fixture_dir . '/sample_attlog.dat';
$expected_file = $fixture_dir . '/sample_attlog_expected.json';

if (!is_file($dat_file)) {
    fwrite(STDERR, "Fixture .dat tidak ditemukan: $dat_file\n");
    exit(2);
}
if (!is_file($expected_file)) {
    fwrite(STDERR, "Fixture expected.json tidak ditemukan: $expected_file\n");
    exit(2);
}

$raw = file_get_contents($dat_file);
$expected = json_decode(file_get_contents($expected_file), true);
if (!is_array($expected)) {
    fwrite(STDERR, "expected.json bukan JSON valid\n");
    exit(2);
}

$parser = new Attlog_parser();
$result = $parser->parse_taps($raw, $expected['period']['from'], $expected['period']['to']);

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

$check('stats.total_lines',       $result['stats']['total_lines'],       $expected['stats']['total_lines']);
$check('stats.raw_count',         $result['stats']['raw_count'],         $expected['stats']['raw_count']);
$check('stats.invalid_count',     $result['stats']['invalid_count'],     $expected['stats']['invalid_count']);
$check('stats.unique_finger_ids', $result['stats']['unique_finger_ids'], $expected['stats']['unique_finger_ids']);
$check('rows',                    $result['rows'],                       $expected['rows']);

echo "\n";
if ($fail > 0) {
    echo "$fail assertion gagal\n";
    exit(1);
}
echo "Semua assertion lewat\n";
exit(0);

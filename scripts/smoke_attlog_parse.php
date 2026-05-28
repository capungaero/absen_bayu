<?php
/**
 * Smoke test untuk Attlog_parser::parse_taps() dan classify_taps().
 *
 * Tujuan:
 *   - parse_taps: format .dat real, hitung invalid/luar periode, urutan time.
 *   - classify_taps: payload entry/out/rest hasil window shift, late_minutes,
 *     stats matched/missing/no_schedule/no_window_match/fallback.
 *
 * Jalankan: php scripts/smoke_attlog_parse.php
 */

if (!defined('BASEPATH')) {
    define('BASEPATH', '/');
}

require __DIR__ . '/../application/helpers/late_helper.php';
require __DIR__ . '/../application/helpers/attlog_helper.php';
require __DIR__ . '/../application/libraries/Attlog_parser.php';

$fixture_dir = __DIR__ . '/../tests/fixtures/attlog';
$dat_file        = $fixture_dir . '/sample_attlog.dat';
$parse_expected  = $fixture_dir . '/sample_attlog_expected.json';
$employee_map_f  = $fixture_dir . '/employee_map.json';
$shift_map_f     = $fixture_dir . '/shift_map.json';
$classify_expect = $fixture_dir . '/classify_expected.json';

foreach ([$dat_file, $parse_expected, $employee_map_f, $shift_map_f, $classify_expect] as $f) {
    if (!is_file($f)) {
        fwrite(STDERR, "Fixture tidak ditemukan: $f\n");
        exit(2);
    }
}

$raw = file_get_contents($dat_file);
$parse_want = json_decode(file_get_contents($parse_expected), true);
$employee_map = json_decode(file_get_contents($employee_map_f), true);
$shift_map = json_decode(file_get_contents($shift_map_f), true);
$classify_want = json_decode(file_get_contents($classify_expect), true);

foreach (['parse_want' => $parse_want, 'classify_want' => $classify_want] as $name => $val) {
    if (!is_array($val)) {
        fwrite(STDERR, "$name bukan JSON valid\n");
        exit(2);
    }
}

$parser = new Attlog_parser();

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

echo "=== parse_taps ===\n";
$parsed = $parser->parse_taps($raw, $parse_want['period']['from'], $parse_want['period']['to']);
$check('stats.total_lines',       $parsed['stats']['total_lines'],       $parse_want['stats']['total_lines']);
$check('stats.raw_count',         $parsed['stats']['raw_count'],         $parse_want['stats']['raw_count']);
$check('stats.invalid_count',     $parsed['stats']['invalid_count'],     $parse_want['stats']['invalid_count']);
$check('stats.unique_finger_ids', $parsed['stats']['unique_finger_ids'], $parse_want['stats']['unique_finger_ids']);
$check('rows',                    $parsed['rows'],                       $parse_want['rows']);

echo "\n=== classify_taps (use_schedule=true) ===\n";
$classified = $parser->classify_taps($parsed['rows'], $employee_map, $shift_map, true, '2026-05-12 10:00:00');
$check('stats.matched_employee', $classified['stats']['matched_employee'], $classify_want['stats']['matched_employee']);
$check('stats.missing_employee', $classified['stats']['missing_employee'], $classify_want['stats']['missing_employee']);
$check('stats.no_schedule',      $classified['stats']['no_schedule'],      $classify_want['stats']['no_schedule']);
$check('stats.no_window_match',  $classified['stats']['no_window_match'],  $classify_want['stats']['no_window_match']);
$check('stats.fallback_rows',    $classified['stats']['fallback_rows'],    $classify_want['stats']['fallback_rows']);
$check('to_delete',              $classified['to_delete'],                 $classify_want['to_delete']);
$check('rows',                   $classified['rows'],                      $classify_want['rows']);

echo "\n=== classify_taps (use_schedule=false fallback) ===\n";
$fallback = $parser->classify_taps($parsed['rows'], $employee_map, [], false, '2026-05-12 10:00:00');
$check('fallback stats.matched_employee', $fallback['stats']['matched_employee'], 3);
$check('fallback stats.no_schedule',      $fallback['stats']['no_schedule'],      0);
$check('fallback stats.fallback_rows',    $fallback['stats']['fallback_rows'],    3);
$check('fallback emp 1 / 2026-05-10 entry_time', $fallback['rows'][1]['2026-05-10']['entry_time'], '2026-05-10 07:48:32');
$check('fallback emp 1 / 2026-05-10 out_time',   $fallback['rows'][1]['2026-05-10']['out_time'],   '2026-05-10 17:02:01');

echo "\n=== classify_taps (no_schedule branch) ===\n";
$nosched = $parser->classify_taps($parsed['rows'], $employee_map, [], true, '2026-05-12 10:00:00');
$check('no_schedule stats.matched_employee', $nosched['stats']['matched_employee'], 3);
$check('no_schedule stats.no_schedule',      $nosched['stats']['no_schedule'],      3);
$check('no_schedule rows empty',             $nosched['rows'],                      []);

echo "\n=== classify_taps (missing employee) ===\n";
$missing = $parser->classify_taps($parsed['rows'], [], $shift_map, true, '2026-05-12 10:00:00');
$check('missing stats.matched_employee', $missing['stats']['matched_employee'], 0);
$check('missing stats.missing_employee', $missing['stats']['missing_employee'], 3);
$check('missing rows empty',             $missing['rows'],                      []);

echo "\n";
if ($fail > 0) {
    echo "$fail assertion gagal\n";
    exit(1);
}
echo "Semua assertion lewat\n";
exit(0);

<?php
/**
 * Smoke test: parse .dat -> classify -> simpan ke SQLite -> SELECT verify.
 *
 * Tujuan: pastikan struktur payload hasil Attlog_parser::classify_taps()
 * compatible dengan schema tabel `presence` (kolom, tipe, default, CHECK).
 * Kalau ada kolom missing/extra atau ada CHECK constraint violation,
 * test ini gagal sebelum kode masuk produksi.
 *
 * Tidak boot CodeIgniter. Pure PDO + library yang sudah diekstrak.
 *
 * Jalankan: php scripts/smoke_attlog_save.php
 * Butuh: ekstensi pdo_sqlite.
 */

if (!defined('BASEPATH')) define('BASEPATH', '/');

require __DIR__ . '/../application/helpers/late_helper.php';
require __DIR__ . '/../application/helpers/attlog_helper.php';
require __DIR__ . '/../application/libraries/Attlog_parser.php';

if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "Butuh ekstensi pdo_sqlite. Aktifkan di php.ini.\n");
    exit(2);
}

$fixture_dir = __DIR__ . '/../tests/fixtures/attlog';
$schema_file = __DIR__ . '/../tests/fixtures/db/schema_presence_sqlite.sql';

$raw          = file_get_contents($fixture_dir . '/sample_attlog.dat');
$employee_map = json_decode(file_get_contents($fixture_dir . '/employee_map.json'), true);
$shift_map    = json_decode(file_get_contents($fixture_dir . '/shift_map.json'), true);
$schema       = file_get_contents($schema_file);

// === 1. parse + classify ===
$parser = new Attlog_parser();
$parsed = $parser->parse_taps($raw, '2026-05-01', '2026-05-31');
$classified = $parser->classify_taps($parsed['rows'], $employee_map, $shift_map, true, '2026-05-12 10:00:00');

$payloads = [];
foreach ($classified['rows'] as $by_date) {
    foreach ($by_date as $row) {
        $payloads[] = $row;
    }
}

if (empty($payloads)) {
    fwrite(STDERR, "Tidak ada payload dari classify_taps — fixture rusak?\n");
    exit(1);
}

// === 2. SQLite in-memory + load schema ===
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach (preg_split('/;\s*\n/', $schema) as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '') continue;
    $pdo->exec($stmt);
}

// === 3. Insert tiap payload ===
$inserted = 0;
$insert_errors = [];
foreach ($payloads as $payload) {
    $cols = array_keys($payload);
    $placeholders = array_fill(0, count($cols), '?');
    $sql = 'INSERT INTO presence ('.implode(',', $cols).') VALUES ('.implode(',', $placeholders).')';
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($payload));
        $inserted++;
    } catch (PDOException $e) {
        $insert_errors[] = $e->getMessage().' (payload: '.json_encode($payload).')';
    }
}

// === 4. SELECT back + verify ===
$rows = $pdo->query('SELECT * FROM presence ORDER BY user_id, flow_date')->fetchAll(PDO::FETCH_ASSOC);

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

$check('insert_errors kosong', $insert_errors, []);
$check('jumlah baris terinsert', $inserted, count($payloads));
$check('jumlah baris di tabel',  count($rows), count($payloads));

// Verifikasi field utama untuk emp 1 / 2026-05-10 (entry, rest_in, rest_out, out + lates)
$pdo_row1 = null;
foreach ($rows as $r) {
    if ((int)$r['user_id'] === 1 && $r['flow_date'] === '2026-05-10') { $pdo_row1 = $r; break; }
}
$check('emp 1 / 2026-05-10 ditemukan',           is_array($pdo_row1), true);
if (is_array($pdo_row1)) {
    $check('emp 1 / 2026-05-10 entry_time',      $pdo_row1['entry_time'],        '2026-05-10 07:48:32');
    $check('emp 1 / 2026-05-10 entry_time_late', (int)$pdo_row1['entry_time_late'], 0);
    $check('emp 1 / 2026-05-10 rest_time_in',    $pdo_row1['rest_time_in'],      '2026-05-10 12:01:15');
    $check('emp 1 / 2026-05-10 rest_time_out',   $pdo_row1['rest_time_out'],     '2026-05-10 13:05:42');
    $check('emp 1 / 2026-05-10 rest_time_late',  (int)$pdo_row1['rest_time_late'], 4);
    $check('emp 1 / 2026-05-10 out_time',        $pdo_row1['out_time'],          '2026-05-10 17:02:01');
    $check('emp 1 / 2026-05-10 presence_status', $pdo_row1['presence_status'],   'approved');
    $check('emp 1 / 2026-05-10 input_by',        $pdo_row1['input_by'],          'system');
}

// Verifikasi emp 2 / 2026-05-10 (entry 07:50:07 -> late 0; out 16:30; no rest)
$pdo_row2 = null;
foreach ($rows as $r) {
    if ((int)$r['user_id'] === 2 && $r['flow_date'] === '2026-05-10') { $pdo_row2 = $r; break; }
}
$check('emp 2 / 2026-05-10 ditemukan',           is_array($pdo_row2), true);
if (is_array($pdo_row2)) {
    $check('emp 2 / 2026-05-10 entry_time',      $pdo_row2['entry_time'],        '2026-05-10 07:50:07');
    $check('emp 2 / 2026-05-10 entry_time_late', (int)$pdo_row2['entry_time_late'], 0);
    $check('emp 2 / 2026-05-10 rest_time_in NULL', $pdo_row2['rest_time_in'],     null);
    $check('emp 2 / 2026-05-10 out_time',        $pdo_row2['out_time'],          '2026-05-10 16:30:00');
}

echo "\n";
if ($fail > 0) {
    echo "$fail assertion gagal\n";
    exit(1);
}
echo "Semua assertion lewat\n";
exit(0);

<?php
/**
 * Smoke test untuk presence_merge_preserve_existing().
 *
 * Coverage: logika "fill the gaps without overwriting" yang dipakai di
 * hr/Presence.php saat import dengan flag preserve_existing=true. Tanpa
 * test ini, regresi misalnya "menimpa entry_time manual dengan tap mesin"
 * baru ketahuan setelah data periode berjalan rusak.
 *
 * Jalankan: php scripts/smoke_presence_merge.php
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

// =============================================================================
// Skenario 1: existing kosong (semua field null/0), new lengkap.
// Expect: semua time field + late field di-update.
// =============================================================================
$existing = [
    'id' => 100, 'user_id' => 1, 'flow_date' => '2026-05-10',
    'entry_time' => null, 'entry_time_late' => 0,
    'out_time' => null,
    'rest_time_in' => null, 'rest_time_out' => null, 'rest_time_late' => 0,
];
$new = [
    'entry_time' => '2026-05-10 08:05:30', 'entry_time_late' => 15,
    'out_time' => '2026-05-10 17:00:00',
    'rest_time_in' => '2026-05-10 12:00:00', 'rest_time_out' => '2026-05-10 13:05:42',
    'rest_time_late' => 4,
];
$check('S1: existing kosong, new lengkap -> isi semua', presence_merge_preserve_existing($new, $existing), [
    'entry_time' => '2026-05-10 08:05:30',
    'out_time' => '2026-05-10 17:00:00',
    'rest_time_in' => '2026-05-10 12:00:00',
    'rest_time_out' => '2026-05-10 13:05:42',
    'entry_time_late' => 15,
    'rest_time_late' => 4,
]);

// =============================================================================
// Skenario 2: existing sudah lengkap dengan late > 0 (sudah dihitung).
// Expect: tidak ada yang di-update (preserve existing).
//
// Catatan behavior: late = 0 dianggap "belum dihitung" (default value), jadi
// existing late=0 akan ditimpa kalau new punya late > 0. Lihat S4.
// =============================================================================
$existing = [
    'entry_time' => '2026-05-10 07:55:00', 'entry_time_late' => 5,
    'out_time' => '2026-05-10 17:00:00',
    'rest_time_in' => '2026-05-10 12:00:00', 'rest_time_out' => '2026-05-10 13:00:00',
    'rest_time_late' => 2,
];
$new = [
    'entry_time' => '2026-05-10 08:30:00', 'entry_time_late' => 40,
    'out_time' => '2026-05-10 18:00:00',
    'rest_time_in' => '2026-05-10 11:30:00', 'rest_time_out' => '2026-05-10 13:30:00',
    'rest_time_late' => 30,
];
$check('S2: existing lengkap (late>0), new beda -> tidak ada update', presence_merge_preserve_existing($new, $existing), []);

// =============================================================================
// Skenario 3: existing hanya punya entry, new punya out + rest.
// Expect: update out_time, rest_time_in, rest_time_out. Entry tidak diubah.
// =============================================================================
$existing = [
    'entry_time' => '2026-05-10 08:00:00', 'entry_time_late' => 10,
    'out_time' => null,
    'rest_time_in' => null, 'rest_time_out' => null, 'rest_time_late' => 0,
];
$new = [
    'entry_time' => '2026-05-10 08:05:30', 'entry_time_late' => 15,
    'out_time' => '2026-05-10 17:00:00',
    'rest_time_in' => '2026-05-10 12:00:00', 'rest_time_out' => '2026-05-10 13:05:42',
    'rest_time_late' => 4,
];
$check('S3: existing entry only, new ada out+rest -> isi sisanya', presence_merge_preserve_existing($new, $existing), [
    'out_time' => '2026-05-10 17:00:00',
    'rest_time_in' => '2026-05-10 12:00:00',
    'rest_time_out' => '2026-05-10 13:05:42',
    'rest_time_late' => 4,
]);

// =============================================================================
// Skenario 4: existing entry_time_late = 0 (cek detik dipotong sebelumnya),
// new entry_time_late > 0. Expect: late di-update.
// =============================================================================
$existing = [
    'entry_time' => '2026-05-10 07:50:07', 'entry_time_late' => 0,
    'out_time' => '2026-05-10 17:00:00',
    'rest_time_in' => null, 'rest_time_out' => null, 'rest_time_late' => 0,
];
$new = [
    'entry_time' => '2026-05-10 07:50:07', 'entry_time_late' => 5,
    'out_time' => '2026-05-10 17:00:00',
    'rest_time_in' => null, 'rest_time_out' => null, 'rest_time_late' => 0,
];
$check('S4: existing late=0, new late=5 -> update late', presence_merge_preserve_existing($new, $existing), [
    'entry_time_late' => 5,
]);

// =============================================================================
// Skenario 5: existing punya late > 0, new punya late lain. Expect: tidak update.
// =============================================================================
$existing = [
    'entry_time' => '2026-05-10 08:05:00', 'entry_time_late' => 15,
    'out_time' => null,
    'rest_time_in' => null, 'rest_time_out' => null, 'rest_time_late' => 0,
];
$new = [
    'entry_time' => '2026-05-10 08:10:00', 'entry_time_late' => 20,
    'out_time' => null,
    'rest_time_in' => null, 'rest_time_out' => null, 'rest_time_late' => 0,
];
$check('S5: existing late>0, new late beda -> tidak update', presence_merge_preserve_existing($new, $existing), []);

// =============================================================================
// Skenario 6: new entry_time_late = 0, existing kosong. Expect: tidak update
// (kondisi "is_new_nonzero" false).
// =============================================================================
$existing = [
    'entry_time' => null, 'entry_time_late' => 0,
    'out_time' => null,
    'rest_time_in' => null, 'rest_time_out' => null, 'rest_time_late' => 0,
];
$new = [
    'entry_time' => '2026-05-10 07:48:00', 'entry_time_late' => 0,
    'out_time' => null,
    'rest_time_in' => null, 'rest_time_out' => null, 'rest_time_late' => 0,
];
$check('S6: new late=0 -> hanya update time, tidak update late', presence_merge_preserve_existing($new, $existing), [
    'entry_time' => '2026-05-10 07:48:00',
]);

// =============================================================================
// Skenario 7: tidak modify input. Memastikan pure function.
// =============================================================================
$existing_orig = ['entry_time' => null, 'entry_time_late' => 0];
$new_orig      = ['entry_time' => '2026-05-10 08:00:00', 'entry_time_late' => 10];
$existing      = $existing_orig;
$new           = $new_orig;
presence_merge_preserve_existing($new, $existing);
$check('S7a: existing tidak ter-modify', $existing, $existing_orig);
$check('S7b: new tidak ter-modify',      $new,      $new_orig);

echo "\n";
if ($fail > 0) {
    echo "$fail assertion gagal\n";
    exit(1);
}
echo "Semua assertion lewat\n";
exit(0);

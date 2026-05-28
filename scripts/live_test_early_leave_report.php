<?php
/**
 * Live test untuk Attendance::early_leave_report.
 *
 * Insert 2 user x beberapa hari PLA -> jalankan logika rekap yang sama
 * dengan controller (_get_early_leave_rows + _build_early_leave_report)
 * -> print struktur data + total -> cleanup.
 *
 * Jalankan: php scripts/live_test_early_leave_report.php
 */

if (!defined('BASEPATH')) define('BASEPATH', '/');
require __DIR__ . '/../application/helpers/presence_helper.php';

$DB = ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => '', 'name' => 'newtiffa_timesheet'];

$TARGET_BRANCH_ID = 1;
$TARGET_MONTH    = 7;
$TARGET_YEAR     = 2026;
$DAYS_IN_MONTH   = cal_days_in_month(CAL_GREGORIAN, $TARGET_MONTH, $TARGET_YEAR);

// dua user dengan PLA: 273 (BAYU PUTRA salary 5.625jt), 274 (DAFITRA salary 5.65jt)
$DUMMY = [
    273 => [
        ['flow_date' => '2026-07-01', 'entry' => '08:00:00', 'out' => '16:00:00', 'short' => 60],
        ['flow_date' => '2026-07-02', 'entry' => '08:00:00', 'out' => '15:30:00', 'short' => 90],
        ['flow_date' => '2026-07-03', 'entry' => '08:00:00', 'out' => '15:00:00', 'short' => 120],
    ],
    274 => [
        ['flow_date' => '2026-07-04', 'entry' => '08:00:00', 'out' => '15:00:00', 'short' => 120],
    ],
];

$pdo = new PDO("mysql:host={$DB['host']};port={$DB['port']};dbname={$DB['name']};charset=utf8mb4", $DB['user'], $DB['pass']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ---- preflight ----
echo "=== PREFLIGHT ===\n";
foreach ($DUMMY as $uid => $rows) {
    foreach ($rows as $r) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM presence WHERE user_id = ? AND flow_date = ?');
        $stmt->execute([$uid, $r['flow_date']]);
        if ((int)$stmt->fetchColumn() > 0) {
            fwrite(STDERR, "Sudah ada presence utk user $uid / {$r['flow_date']}. Abort.\n");
            exit(1);
        }
    }
}
printf("Branch %d / periode %d-%d / days_in_month=%d. Tidak ada konflik data.\n", $TARGET_BRANCH_ID, $TARGET_MONTH, $TARGET_YEAR, $DAYS_IN_MONTH);

// ---- insert dummy ----
echo "\n=== INSERT DUMMY ===\n";
$inserted_presence = [];
$ins = $pdo->prepare("INSERT INTO presence
    (user_id, entry_time, out_time, entry_time_late, rest_time_in, rest_time_out, rest_time_late,
     flow_date, created_at, input_by, presence_type, presence_status, is_overtime, flag,
     is_early_leave, early_leave_short_minutes)
    VALUES (?, ?, ?, 0, NULL, NULL, 0, ?, NOW(), 'system', 'normal', 'approved', '0', '0', 1, ?)");
foreach ($DUMMY as $uid => $rows) {
    foreach ($rows as $r) {
        $ins->execute([$uid, $r['flow_date'].' '.$r['entry'], $r['flow_date'].' '.$r['out'], $r['flow_date'], $r['short']]);
        $inserted_presence[] = $pdo->lastInsertId();
    }
}
printf("Inserted %d row presence PLA\n", count($inserted_presence));

// ---- replikasi Attendance::_get_early_leave_rows ----
$raw      = strtotime("$TARGET_YEAR-$TARGET_MONTH-10 -1 months");
$from     = date('Y-m-26', $raw);
$to       = sprintf('%04d-%02d-25', $TARGET_YEAR, $TARGET_MONTH);
printf("\nPeriod range: %s s/d %s\n", $from, $to);

$rows_stmt = $pdo->prepare("SELECT
        presence.id AS presence_id, presence.user_id, presence.flow_date,
        presence.entry_time, presence.out_time, presence.rest_time_in, presence.rest_time_out,
        presence.early_leave_short_minutes, presence.presence_status,
        users.employee_code, users.first_name, users.last_name, users.salary,
        position.position_name, position.branch_id,
        branch.branch_name, subdivision.subdivision_name
    FROM presence
    JOIN users ON users.id = presence.user_id
    JOIN position ON position.id = users.position_id
    JOIN branch ON branch.id = position.branch_id
    LEFT JOIN subdivision ON subdivision.id = users.subdivision_id
    WHERE presence.is_early_leave = 1
      AND presence.presence_status = 'approved'
      AND presence.flow_date >= ? AND presence.flow_date <= ?
      AND position.branch_id = ?
    ORDER BY branch.branch_name, users.first_name, presence.flow_date ASC");
$rows_stmt->execute([$from, $to, $TARGET_BRANCH_ID]);
$rows = $rows_stmt->fetchAll(PDO::FETCH_ASSOC);
printf("Query found %d row(s) di branch %d.\n", count($rows), $TARGET_BRANCH_ID);

// ---- replikasi Attendance::_build_early_leave_report ----
$per_user = [];
foreach ($rows as $row) {
    $uid = (int) $row['user_id'];
    if (!isset($per_user[$uid])) {
        $per_user[$uid] = [
            'user_id' => $uid,
            'employee_code' => $row['employee_code'],
            'name' => trim($row['first_name'].' '.$row['last_name']),
            'salary' => (int) $row['salary'],
            'branch_name' => $row['branch_name'],
            'position_name' => $row['position_name'],
            'subdivision' => $row['subdivision_name'],
            'dates' => [],
            'total_short_minutes' => 0,
        ];
    }
    $per_user[$uid]['dates'][] = [
        'flow_date' => $row['flow_date'],
        'entry_time' => $row['entry_time'],
        'out_time' => $row['out_time'],
        'short_minutes' => (int) $row['early_leave_short_minutes'],
    ];
    $per_user[$uid]['total_short_minutes'] += (int) $row['early_leave_short_minutes'];
}

$total_short = $total_amount = 0;
foreach ($per_user as &$u) {
    $u['hourly_rate'] = presence_hourly_rate($u['salary'], $DAYS_IN_MONTH);
    $u['deduction_amount'] = presence_early_leave_deduction_amount($u['salary'], $DAYS_IN_MONTH, $u['total_short_minutes']);
    $total_short  += $u['total_short_minutes'];
    $total_amount += $u['deduction_amount'];
}
unset($u);

// ---- print rekap ----
echo "\n=== REKAP HASIL ===\n";
printf("%-3s %-22s %-12s %-7s %-12s %-12s\n", '#', 'Karyawan', 'GP', 'Kurang', 'Gaji/jam', 'Potongan');
echo str_repeat('-', 80)."\n";
$n = 0;
foreach ($per_user as $u) {
    $n++;
    printf("%-3d %-22s %12s %4dm   %12s %12s\n",
        $n,
        substr($u['name'], 0, 22),
        number_format($u['salary']),
        $u['total_short_minutes'],
        number_format((int)round($u['hourly_rate'])),
        number_format($u['deduction_amount'])
    );
    foreach ($u['dates'] as $d) {
        printf("    %s  %s-%s  short=%dm\n",
            $d['flow_date'],
            date('H:i', strtotime($d['entry_time'])),
            date('H:i', strtotime($d['out_time'])),
            $d['short_minutes']
        );
    }
}
echo str_repeat('-', 80)."\n";
printf("TOTAL: %d karyawan, kekurangan %d menit (%.1f jam), potongan Rp %s\n",
    count($per_user), $total_short, $total_short/60, number_format($total_amount));

// ---- assert ----
$expected_273 = presence_early_leave_deduction_amount(5625000, 31, 270);
$expected_274 = presence_early_leave_deduction_amount(5650000, 31, 120);
$expected_total = $expected_273 + $expected_274;
printf("\nExpected amounts: bayu=%s dafitra=%s total=%s\n", number_format($expected_273), number_format($expected_274), number_format($expected_total));
$match = (int)$total_amount === (int)$expected_total;
echo "Total match expected? " . ($match ? 'YA' : 'TIDAK') . "\n";

// ---- cleanup ----
echo "\n=== CLEANUP ===\n";
if (!empty($inserted_presence)) {
    $in = implode(',', array_fill(0, count($inserted_presence), '?'));
    $del = $pdo->prepare("DELETE FROM presence WHERE id IN ($in)");
    $del->execute($inserted_presence);
    printf("Deleted %d presence row\n", $del->rowCount());
}

if ($match) { echo "\nLIVE TEST REKAP PASSED\n"; exit(0); }
echo "\nLIVE TEST REKAP FAILED\n"; exit(1);

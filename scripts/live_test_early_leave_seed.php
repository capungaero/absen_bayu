<?php
/**
 * Live test untuk _seed_early_leave_deductions() di Payroll controller.
 *
 * Sengaja tidak boot CodeIgniter. Connect via PDO langsung ke
 * newtiffa_timesheet pakai konfigurasi dari application/config/database.php.
 * Logika seed direplikasi 1:1 supaya kita tahu query terhadap skema produksi
 * benar-benar valid (kolom, index, group_by, dst).
 *
 * Test plan:
 *   1. INSERT 3 baris presence dummy (is_early_leave=1, short 60/90/120 menit)
 *      untuk user yang dipilih di periode payroll target.
 *   2. Jalankan logika seed: query agregat -> hitung amount -> insert
 *      payroll_deduction.
 *   3. SELECT payroll_deduction baru, tampilkan.
 *   4. CLEANUP: DELETE baris payroll_deduction + presence yang dibuat oleh
 *      test ini. Verifikasi state akhir = state awal.
 *
 * Jalankan: php scripts/live_test_early_leave_seed.php
 */

if (!defined('BASEPATH')) define('BASEPATH', '/');
require __DIR__ . '/../application/helpers/presence_helper.php';

// ---- Konfigurasi test ----
$DB_HOST = '127.0.0.1';
$DB_PORT = 3306;
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'newtiffa_timesheet';

$TARGET_USER_ID  = 273;            // Bayu Putra, branch 1
$TARGET_BRANCH_ID = 1;
$TARGET_MONTH    = 7;              // Juli 2026 -> period range 2026-06-26 s/d 2026-07-25
$TARGET_YEAR     = 2026;
$DAYS_IN_MONTH   = cal_days_in_month(CAL_GREGORIAN, $TARGET_MONTH, $TARGET_YEAR); // 31

$DUMMY_PLA = [
    ['flow_date' => '2026-07-01', 'entry' => '08:00:00', 'out' => '16:00:00', 'short' => 60],
    ['flow_date' => '2026-07-02', 'entry' => '08:00:00', 'out' => '15:30:00', 'short' => 90],
    ['flow_date' => '2026-07-03', 'entry' => '08:00:00', 'out' => '15:00:00', 'short' => 120],
];
$TOTAL_SHORT_MIN = array_sum(array_column($DUMMY_PLA, 'short')); // 270

// ---- PDO connect ----
$pdo = new PDO("mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== STATE AWAL ===\n";
$user = $pdo->query("SELECT id, employee_code, first_name, salary FROM users WHERE id = $TARGET_USER_ID")->fetch(PDO::FETCH_ASSOC);
printf("Target user: id=%d code=%s name=%s salary=%s\n", $user['id'], $user['employee_code'], $user['first_name'], number_format($user['salary']));
printf("Target periode: %d/%d (days_in_month=%d)\n", $TARGET_MONTH, $TARGET_YEAR, $DAYS_IN_MONTH);
printf("Total kekurangan menit yang akan ditest: %d (= %.1f jam)\n", $TOTAL_SHORT_MIN, $TOTAL_SHORT_MIN/60);

$expected_amount = presence_early_leave_deduction_amount($user['salary'], $DAYS_IN_MONTH, $TOTAL_SHORT_MIN);
printf("Expected deduction amount: Rp %s\n", number_format($expected_amount));

$master = $pdo->query("SELECT id, branch_id, deduction_name FROM deduction
    WHERE branch_id = $TARGET_BRANCH_ID
      AND (deduction_name LIKE '%Pulang Lebih Awal%' OR deduction_name LIKE '%Kekurangan Jam%')
      AND deleted_at IS NULL
    LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$master) { fwrite(STDERR, "Master deduction PLA tidak ditemukan utk branch $TARGET_BRANCH_ID\n"); exit(1); }
printf("Master deduction PLA: id=%d name='%s'\n", $master['id'], $master['deduction_name']);

$pre_pd = $pdo->query("SELECT COUNT(*) FROM payroll_deduction
    WHERE user_id = $TARGET_USER_ID AND deduction_id = {$master['id']}
      AND deduction_month = $TARGET_MONTH AND deduction_year = $TARGET_YEAR")->fetchColumn();
printf("payroll_deduction existing utk user %d / deduction %d / %d-%d: %d row\n",
    $TARGET_USER_ID, $master['id'], $TARGET_MONTH, $TARGET_YEAR, $pre_pd);
if ($pre_pd > 0) { fwrite(STDERR, "Sudah ada row payroll_deduction utk target. Aborting supaya tidak overwrite.\n"); exit(1); }

$pre_presence = $pdo->query("SELECT COUNT(*) FROM presence
    WHERE user_id = $TARGET_USER_ID AND flow_date BETWEEN '2026-07-01' AND '2026-07-03'")->fetchColumn();
printf("presence existing utk user %d / 2026-07-01..03: %d row\n", $TARGET_USER_ID, $pre_presence);
if ($pre_presence > 0) { fwrite(STDERR, "Sudah ada row presence di tanggal test. Aborting.\n"); exit(1); }

echo "\n=== STEP 1: Insert PLA dummy ===\n";
$inserted_presence_ids = [];
$ins = $pdo->prepare("INSERT INTO presence
    (user_id, entry_time, out_time, entry_time_late, rest_time_in, rest_time_out, rest_time_late,
     flow_date, created_at, input_by, presence_type, presence_status, is_overtime, flag,
     is_early_leave, early_leave_short_minutes)
    VALUES (?, ?, ?, 0, NULL, NULL, 0, ?, NOW(), 'system', 'normal', 'approved', '0', '0', 1, ?)");
foreach ($DUMMY_PLA as $r) {
    $ins->execute([
        $TARGET_USER_ID,
        $r['flow_date'].' '.$r['entry'],
        $r['flow_date'].' '.$r['out'],
        $r['flow_date'],
        $r['short'],
    ]);
    $inserted_presence_ids[] = $pdo->lastInsertId();
    printf("  inserted presence id=%d %s entry=%s out=%s short=%d\n", end($inserted_presence_ids), $r['flow_date'], $r['entry'], $r['out'], $r['short']);
}

echo "\n=== STEP 2: Jalankan seed logic (replikasi _seed_early_leave_deductions) ===\n";
// Period range sesuai attlog_presence_period_range($month, $year):
//   from = START_PAYROLL_DATE bulan sebelumnya, to = END_PAYROLL_DATE bulan ini.
// constants.php: START=26 END=25
$raw      = strtotime("$TARGET_YEAR-$TARGET_MONTH-10 -1 months");
$from     = date('Y-m-26', $raw);
$to       = sprintf('%04d-%02d-25', $TARGET_YEAR, $TARGET_MONTH);
printf("Period range: %s s/d %s\n", $from, $to);

$agg = $pdo->prepare("SELECT presence.user_id, users.salary,
            SUM(presence.early_leave_short_minutes) AS total_short_minutes
        FROM presence
        JOIN users ON users.id = presence.user_id
        JOIN position ON position.id = users.position_id
        WHERE position.branch_id = ?
          AND presence.flow_date >= ? AND presence.flow_date <= ?
          AND presence.is_early_leave = 1
          AND presence.presence_status = 'approved'
        GROUP BY presence.user_id, users.salary");
$agg->execute([$TARGET_BRANCH_ID, $from, $to]);
$rows = $agg->fetchAll(PDO::FETCH_ASSOC);
printf("Agregat menemukan %d user dengan PLA di periode tsb.\n", count($rows));

$inserted_deduction_ids = [];
foreach ($rows as $row) {
    $total_short = (int) $row['total_short_minutes'];
    $amount = presence_early_leave_deduction_amount($row['salary'], $DAYS_IN_MONTH, $total_short);
    printf("  user_id=%d salary=%s short_min=%d -> amount=Rp %s\n",
        $row['user_id'], number_format($row['salary']), $total_short, number_format($amount));

    if ($amount <= 0) { continue; }

    // Cek exists (manual override wins)
    $exists = $pdo->prepare("SELECT COUNT(*) FROM payroll_deduction
        WHERE user_id = ? AND deduction_id = ? AND deduction_month = ? AND deduction_year = ?");
    $exists->execute([$row['user_id'], $master['id'], $TARGET_MONTH, $TARGET_YEAR]);
    if ((int)$exists->fetchColumn() > 0) {
        echo "    -> SKIP (sudah ada payroll_deduction utk user/month/year ini)\n";
        continue;
    }

    $ins_pd = $pdo->prepare("INSERT INTO payroll_deduction
        (user_id, deduction_id, deduction_month, deduction_year, deduction_amount, deduction_note, created_at)
        VALUES (?, ?, ?, ?, ?, 'Potongan Izin Pulang Lebih Awal (auto)', NOW())");
    $ins_pd->execute([$row['user_id'], $master['id'], $TARGET_MONTH, $TARGET_YEAR, $amount]);
    $inserted_deduction_ids[] = $pdo->lastInsertId();
    echo "    -> INSERT payroll_deduction id=" . end($inserted_deduction_ids) . "\n";
}

echo "\n=== STEP 3: Verify payroll_deduction ===\n";
$ver = $pdo->prepare("SELECT id, user_id, deduction_id, deduction_month, deduction_year, deduction_amount, deduction_note, created_at
        FROM payroll_deduction
        WHERE user_id = ? AND deduction_id = ? AND deduction_month = ? AND deduction_year = ?");
$ver->execute([$TARGET_USER_ID, $master['id'], $TARGET_MONTH, $TARGET_YEAR]);
$pd_row = $ver->fetch(PDO::FETCH_ASSOC);

if ($pd_row) {
    echo "payroll_deduction row terbentuk:\n";
    foreach ($pd_row as $k => $v) printf("  %-20s = %s\n", $k, $v);
    $match = (int)$pd_row['deduction_amount'] === $expected_amount;
    printf("Amount match expected (%s)? %s\n", number_format($expected_amount), $match ? 'YA' : 'TIDAK');
} else {
    echo "FAIL: payroll_deduction tidak terbentuk\n";
}

echo "\n=== STEP 4: CLEANUP ===\n";
if (!empty($inserted_deduction_ids)) {
    $in = implode(',', array_fill(0, count($inserted_deduction_ids), '?'));
    $del = $pdo->prepare("DELETE FROM payroll_deduction WHERE id IN ($in)");
    $del->execute($inserted_deduction_ids);
    printf("Deleted %d payroll_deduction row\n", $del->rowCount());
}
if (!empty($inserted_presence_ids)) {
    $in = implode(',', array_fill(0, count($inserted_presence_ids), '?'));
    $del = $pdo->prepare("DELETE FROM presence WHERE id IN ($in)");
    $del->execute($inserted_presence_ids);
    printf("Deleted %d presence row\n", $del->rowCount());
}

echo "\n=== STATE AKHIR ===\n";
$post_pd = $pdo->query("SELECT COUNT(*) FROM payroll_deduction
    WHERE user_id = $TARGET_USER_ID AND deduction_id = {$master['id']}
      AND deduction_month = $TARGET_MONTH AND deduction_year = $TARGET_YEAR")->fetchColumn();
$post_presence = $pdo->query("SELECT COUNT(*) FROM presence
    WHERE user_id = $TARGET_USER_ID AND flow_date BETWEEN '2026-07-01' AND '2026-07-03'")->fetchColumn();
printf("payroll_deduction setelah cleanup: %d (expected 0, match awal %d)\n", $post_pd, $pre_pd);
printf("presence setelah cleanup: %d (expected 0, match awal %d)\n", $post_presence, $pre_presence);

if ((int)$post_pd === (int)$pre_pd && (int)$post_presence === (int)$pre_presence && $pd_row && (int)$pd_row['deduction_amount'] === $expected_amount) {
    echo "\nLIVE TEST PASSED — seed logic OK + cleanup OK\n";
    exit(0);
}
echo "\nLIVE TEST FAILED — periksa output di atas\n";
exit(1);

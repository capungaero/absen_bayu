<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '1');

require __DIR__ . '/../lib/vendor/autoload.php';
require __DIR__ . '/absen_report_xlsx.php';
require __DIR__ . '/absen_report_employee_filter.php';

define('BASEPATH', __DIR__ . '/../');
$db = [];
require __DIR__ . '/../application/config/database.php';

$opts = getopt('', ['year::', 'month::', 'branch::', 'employee-codes::', 'scope::', 'as-of::', 'out::']);
$year = isset($opts['year']) ? (int) $opts['year'] : (int) date('Y');
$month = isset($opts['month']) ? (int) $opts['month'] : (int) date('m');
$periodStart = sprintf('%04d-%02d-01', $year, $month);
$periodEnd = date('Y-m-t', strtotime($periodStart));
$asOf = isset($opts['as-of']) ? $opts['as-of'] : date('Y-m-d');
if ($asOf < $periodStart) {
    $asOf = $periodStart;
}
if ($asOf > $periodEnd) {
    $asOf = $periodEnd;
}

$cfg = $db['default'];
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli($cfg['hostname'], $cfg['username'], $cfg['password'], $cfg['database'], (int) $cfg['port']);
$mysqli->set_charset('utf8');

$employeeCodes = parseEmployeeCodes($opts['employee-codes'] ?? null);
$branchFilter = $employeeCodes ? null : resolveBranchFilter($mysqli, $opts['branch'] ?? null);
$employees = $employeeCodes ? fetchEmployeesByCodes($mysqli, $employeeCodes, $periodStart, $periodEnd) : fetchEmployees($mysqli, $branchFilter);
$employeeIds = array_column($employees, 'id');
$employeeMap = [];
foreach ($employees as $employee) {
    $employeeMap[(int) $employee['id']] = $employee;
}

$schedules = fetchSchedules($mysqli, $periodStart, $periodEnd, $employeeIds);
$presenceRows = fetchPresence($mysqli, $periodStart, $periodEnd, $employeeIds);
$leaves = fetchLeaves($mysqli, $periodStart, $periodEnd, $employeeIds);
$days = makeDays($periodStart, $periodEnd);

$report = buildReport($employees, $days, $asOf, $schedules, $presenceRows, $leaves);
$scopeLabel = $opts['scope'] ?? null;
$scope = $scopeLabel ? slug($scopeLabel) : ($branchFilter ? slug($branchFilter['branch_name']) : ($employeeCodes ? 'custom' : 'all'));
$out = $opts['out'] ?? sprintf('exports/report_absen_%04d_%02d_%s.xlsx', $year, $month, $scope);
$outPath = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $out);
if (!is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0777, true);
}

writeWorkbook($outPath, $report, [
    'year' => $year,
    'month' => $month,
    'period_start' => $periodStart,
    'period_end' => $periodEnd,
    'as_of' => $asOf,
    'branch' => $scopeLabel ? ['branch_name' => $scopeLabel] : $branchFilter,
]);

echo "OK\n";
echo "file=" . $outPath . "\n";
echo "url=http://127.0.0.1:8080/" . str_replace('\\', '/', $out) . "\n";
echo "employees=" . count($employees) . "\n";
echo "anomalies=" . count($report['anomalies']) . "\n";

function resolveBranchFilter(mysqli $db, ?string $branch): ?array
{
    if ($branch === null || trim($branch) === '' || strtolower(trim($branch)) === 'all') {
        return null;
    }

    if (ctype_digit($branch)) {
        $rows = queryAll($db, 'SELECT id, branch_code, branch_name FROM branch WHERE id = ?', 'i', [(int) $branch]);
    } else {
        $needle = '%' . $branch . '%';
        $rows = queryAll(
            $db,
            'SELECT id, branch_code, branch_name FROM branch WHERE branch_code LIKE ? OR branch_name LIKE ? ORDER BY id LIMIT 1',
            'ss',
            [$needle, $needle]
        );
    }

    if (!$rows) {
        throw new RuntimeException('Branch tidak ditemukan: ' . $branch);
    }
    return $rows[0];
}

function queryAll(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchEmployees(mysqli $db, ?array $branch): array
{
    $where = 'u.active = 1';
    $types = '';
    $params = [];
    if ($branch) {
        $where .= ' AND b.id = ?';
        $types = 'i';
        $params[] = (int) $branch['id'];
    }

    return queryAll($db, "
        SELECT
            u.id,
            u.employee_code,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
            b.id AS branch_id,
            b.branch_code,
            b.branch_name,
            COALESCE(sd.subdivision_name, '-') AS division_name,
            COALESCE(p.position_name, '-') AS position_name,
            u.join_date,
            usc.shift_cluster_id,
            sc.cluster_code,
            sc.cluster_name,
            usc.shift_applies
        FROM users u
        JOIN position p ON p.id = u.position_id
        JOIN branch b ON b.id = p.branch_id
        LEFT JOIN subdivision sd ON sd.id = u.subdivision_id
        LEFT JOIN users_shift_cluster usc
            ON usc.user_id = u.id
           AND usc.id = (
                SELECT MAX(usc2.id)
                FROM users_shift_cluster usc2
                WHERE usc2.user_id = u.id
                  AND usc2.deleted_at IS NULL
           )
        LEFT JOIN shift_cluster sc ON sc.id = usc.shift_cluster_id
        WHERE {$where}
        ORDER BY b.branch_name, sd.subdivision_name, employee_name
    ", $types, $params);
}

function fetchSchedules(mysqli $db, string $from, string $to, array $userIds): array
{
    if (!$userIds) {
        return [];
    }
    $rows = queryAll($db, "
        SELECT
            usa.user_id,
            usa.additional_date,
            usa.additional_type,
            usa.shift_id,
            usa.id AS schedule_id,
            s.shift_code,
            s.shift_name,
            s.start_time,
            s.end_time,
            s.start_time_late
        FROM users_shift_additional usa
        JOIN (
            SELECT user_id, additional_date, MAX(id) AS id
            FROM users_shift_additional
            WHERE additional_date BETWEEN ? AND ?
              AND deleted_at IS NULL
            GROUP BY user_id, additional_date
        ) latest ON latest.id = usa.id
        LEFT JOIN shift s ON s.id = usa.shift_id
        WHERE usa.user_id IN (" . idList($userIds) . ")
        ORDER BY usa.user_id, usa.additional_date
    ", 'ss', [$from, $to]);

    return indexByUserDate($rows, 'additional_date');
}

function fetchPresence(mysqli $db, string $from, string $to, array $userIds): array
{
    if (!$userIds) {
        return [];
    }
    $rows = queryAll($db, "
        SELECT *
        FROM presence
        WHERE flow_date BETWEEN ? AND ?
          AND user_id IN (" . idList($userIds) . ")
        ORDER BY user_id, flow_date, id
    ", 'ss', [$from, $to]);

    $out = [];
    foreach ($rows as $row) {
        $key = (int) $row['user_id'] . '|' . $row['flow_date'];
        if (!isset($out[$key])) {
            $out[$key] = ['all' => [], 'approved' => []];
        }
        $out[$key]['all'][] = $row;
        if ($row['presence_status'] === 'approved') {
            $out[$key]['approved'][] = $row;
        }
    }
    return $out;
}

function fetchLeaves(mysqli $db, string $from, string $to, array $userIds): array
{
    if (!$userIds) {
        return [];
    }
    $rows = queryAll($db, "
        SELECT *
        FROM `leave`
        WHERE leave_start <= ?
          AND leave_end >= ?
          AND deleted_at IS NULL
          AND user_id IN (" . idList($userIds) . ")
        ORDER BY user_id, leave_start, id
    ", 'ss', [$to, $from]);

    $out = [];
    foreach ($rows as $row) {
        foreach (makeDays(max($from, $row['leave_start']), min($to, $row['leave_end'])) as $date) {
            $out[(int) $row['user_id'] . '|' . $date][] = $row;
        }
    }
    return $out;
}

function idList(array $ids): string
{
    return implode(',', array_map(static fn($id) => (string) (int) $id, $ids));
}

function indexByUserDate(array $rows, string $dateKey): array
{
    $out = [];
    foreach ($rows as $row) {
        $out[(int) $row['user_id'] . '|' . $row[$dateKey]] = $row;
    }
    return $out;
}

function makeDays(string $from, string $to): array
{
    $days = [];
    for ($ts = strtotime($from); $ts <= strtotime($to); $ts = strtotime('+1 day', $ts)) {
        $days[] = date('Y-m-d', $ts);
    }
    return $days;
}

function buildReport(array $employees, array $days, string $asOf, array $schedules, array $presenceRows, array $leaves): array
{
    $matrix = [];
    $details = [];
    $ranking = [];
    $anomalies = [];

    foreach ($employees as $employee) {
        $totals = initTotals();
        $daily = [];
        foreach ($days as $date) {
            $key = (int) $employee['id'] . '|' . $date;
            $schedule = $schedules[$key] ?? null;
            $presenceBucket = $presenceRows[$key] ?? ['all' => [], 'approved' => []];
            $presence = $presenceBucket['approved'] ? end($presenceBucket['approved']) : null;
            $leaveRows = $leaves[$key] ?? [];
            $notes = [];
            $status = classifyDay($date, $asOf, $schedule, $presence, $presenceBucket, $leaveRows, $notes);
            addAnomalies($anomalies, $employee, $date, $schedule, $presence, $presenceBucket, $leaveRows, $status, $notes);
            incrementTotals($totals, $status, $presence, $date <= $asOf);
            $daily[$date] = statusCode($status);
            $details[] = detailRow($employee, $date, $schedule, $presence, $status, $notes);
        }
        $scoreData = scoreEmployee($totals);
        $ranking[] = array_merge(identityRow($employee), $totals, $scoreData, [
            'catatan' => employeeNotes($employee, $totals),
        ]);
        $matrix[] = array_merge(identityRow($employee), ['daily' => $daily], $totals, $scoreData);
    }

    usort($ranking, 'sortRanking');
    $rank = 0;
    foreach ($ranking as &$row) {
        if ($row['rank_status'] === 'DINILAI') {
            $row['rank'] = ++$rank;
        } else {
            $row['rank'] = '';
        }
    }
    unset($row);

    return compact('matrix', 'details', 'ranking', 'anomalies');
}

function initTotals(): array
{
    return [
        'hari_kerja' => 0, 'hadir' => 0, 'terlambat' => 0, 'menit_telat' => 0,
        'izin' => 0, 'cuti' => 0, 'sakit' => 0, 'absen' => 0, 'tidak_lengkap' => 0,
        'libur' => 0, 'tanpa_jadwal' => 0, 'future' => 0, 'anomali' => 0,
    ];
}

function classifyDay(string $date, string $asOf, ?array $schedule, ?array $presence, array $presenceBucket, array $leaves, array &$notes): string
{
    $isFuture = $date > $asOf;
    if (!$schedule) {
        if ($presence) {
            $notes[] = 'Presensi ada tanpa jadwal';
            return 'PRESENSI_TANPA_JADWAL';
        }
        if (!$isFuture) {
            $notes[] = 'Jadwal tidak ada';
        }
        return $isFuture ? 'FUTURE' : 'TANPA_JADWAL';
    }

    if ($schedule['additional_type'] === 'free') {
        return 'LIBUR';
    }
    if (!$schedule['shift_id'] || !$schedule['shift_code']) {
        $notes[] = 'Shift master tidak ditemukan';
        return $isFuture ? 'FUTURE' : 'TANPA_JADWAL';
    }
    if (!$presence) {
        foreach ($leaves as $leave) {
            if ($leave['leave_status'] === 'approve') {
                $notes[] = 'Cuti/izin approve tanpa row presence';
                return strtoupper($leave['leave_type']);
            }
        }
        if ($presenceBucket['all']) {
            $notes[] = 'Ada presence non-approved';
            return 'TIDAK_LENGKAP';
        }
        return $isFuture ? 'FUTURE' : 'ABSEN';
    }

    if (count($presenceBucket['approved']) > 1) {
        $notes[] = 'Duplikat presence approved';
    }
    if ($presence['presence_type'] !== 'normal') {
        return strtoupper($presence['presence_type']);
    }

    $hasEntry = !empty($presence['entry_time']);
    $hasOut = !empty($presence['out_time']);
    if ($hasEntry xor $hasOut) {
        $notes[] = 'Masuk/pulang tidak lengkap';
        return 'TIDAK_LENGKAP';
    }
    if (!$hasEntry && !$hasOut) {
        return $isFuture ? 'FUTURE' : 'ABSEN';
    }
    if ((!empty($presence['rest_time_in']) xor !empty($presence['rest_time_out']))) {
        $notes[] = 'Istirahat tidak lengkap';
    }
    return ((int) $presence['entry_time_late'] > 0) ? 'TERLAMBAT' : 'HADIR';
}

function addAnomalies(array &$anomalies, array $employee, string $date, ?array $schedule, ?array $presence, array $bucket, array $leaves, string $status, array $notes): void
{
    foreach ($notes as $note) {
        $anomalies[] = anomalyRow($employee, $date, $status, $note);
    }
    foreach ($leaves as $leave) {
        if ($leave['leave_status'] === 'pending') {
            $anomalies[] = anomalyRow($employee, $date, $status, 'Leave pending: ' . $leave['leave_type']);
        }
        if ($leave['leave_status'] === 'approve' && $presence && $presence['presence_type'] !== $leave['leave_type']) {
            $anomalies[] = anomalyRow($employee, $date, $status, 'Leave approve tidak cocok presence');
        }
    }
    if (!$schedule && $presence) {
        $anomalies[] = anomalyRow($employee, $date, $status, 'Presence tidak punya jadwal harian');
    }
    if (count($bucket['approved']) > 1) {
        $anomalies[] = anomalyRow($employee, $date, $status, 'Lebih dari satu presence approved');
    }
}

function incrementTotals(array &$totals, string $status, ?array $presence, bool $countedDate): void
{
    $map = [
        'HADIR' => 'hadir', 'TERLAMBAT' => 'terlambat', 'IZIN' => 'izin',
        'CUTI' => 'cuti', 'SAKIT' => 'sakit', 'ABSEN' => 'absen',
        'TIDAK_LENGKAP' => 'tidak_lengkap', 'LIBUR' => 'libur',
        'TANPA_JADWAL' => 'tanpa_jadwal', 'PRESENSI_TANPA_JADWAL' => 'anomali',
        'FUTURE' => 'future',
    ];
    if (isset($map[$status])) {
        $totals[$map[$status]]++;
    }
    if (in_array($status, ['HADIR', 'TERLAMBAT', 'IZIN', 'CUTI', 'SAKIT', 'ABSEN', 'TIDAK_LENGKAP'], true) && $countedDate) {
        $totals['hari_kerja']++;
    }
    if ($presence && $status === 'TERLAMBAT') {
        $totals['menit_telat'] += (int) $presence['entry_time_late'];
    }
}

function scoreEmployee(array $totals): array
{
    if ($totals['hari_kerja'] <= 0) {
        return ['score' => '', 'rank_status' => 'TIDAK DINILAI'];
    }
    $score = 100 - ($totals['absen'] * 5) - ($totals['tidak_lengkap'] * 3) - ($totals['terlambat'] * 2) - floor($totals['menit_telat'] / 30);
    return ['score' => max(0, $score), 'rank_status' => 'DINILAI'];
}

function sortRanking(array $a, array $b): int
{
    if ($a['rank_status'] !== $b['rank_status']) {
        return $a['rank_status'] === 'DINILAI' ? -1 : 1;
    }
    foreach (['score' => -1, 'absen' => 1, 'tidak_lengkap' => 1, 'menit_telat' => 1, 'hadir' => -1] as $key => $dir) {
        if ($a[$key] == $b[$key]) {
            continue;
        }
        return ($a[$key] <=> $b[$key]) * $dir;
    }
    return strcmp($a['nama'], $b['nama']);
}

function identityRow(array $employee): array
{
    return [
        'user_id' => $employee['id'],
        'id_fingerprint' => $employee['employee_code'],
        'nama' => $employee['employee_name'],
        'cabang' => $employee['branch_name'],
        'divisi' => $employee['division_name'],
        'posisi' => $employee['position_name'],
    ];
}

function detailRow(array $employee, string $date, ?array $schedule, ?array $presence, string $status, array $notes): array
{
    return array_merge(identityRow($employee), [
        'tanggal' => $date,
        'shift' => $schedule ? (($schedule['shift_code'] ?? '-') . ' / ' . ($schedule['shift_name'] ?? '-')) : '-',
        'jam_shift' => $schedule ? (($schedule['start_time'] ?? '-') . ' - ' . ($schedule['end_time'] ?? '-')) : '-',
        'masuk' => timeOnly($presence['entry_time'] ?? null),
        'pulang' => timeOnly($presence['out_time'] ?? null),
        'telat_menit' => (int) ($presence['entry_time_late'] ?? 0),
        'status' => $status,
        'catatan' => implode('; ', $notes),
    ]);
}

function anomalyRow(array $employee, string $date, string $status, string $note): array
{
    return array_merge(identityRow($employee), ['tanggal' => $date, 'status' => $status, 'catatan' => $note]);
}

function employeeNotes(array $employee, array $totals): string
{
    $notes = [];
    if (!$employee['shift_cluster_id']) {
        $notes[] = 'Cluster shift belum dipilih';
    }
    if ($totals['hari_kerja'] === 0) {
        $notes[] = 'Tidak ada hari kerja terjadwal sampai as-of';
    }
    if ($totals['tanpa_jadwal'] > 0) {
        $notes[] = 'Ada tanggal tanpa jadwal';
    }
    return implode('; ', $notes);
}

function statusCode(string $status): string
{
    return [
        'HADIR' => 'H', 'TERLAMBAT' => 'T', 'IZIN' => 'I', 'CUTI' => 'C',
        'SAKIT' => 'S', 'ABSEN' => 'A', 'TIDAK_LENGKAP' => 'TL',
        'LIBUR' => 'OFF', 'TANPA_JADWAL' => 'TJ',
        'PRESENSI_TANPA_JADWAL' => 'PTJ', 'FUTURE' => 'F',
    ][$status] ?? $status;
}

function timeOnly(?string $datetime): string
{
    return $datetime ? date('H:i', strtotime($datetime)) : '';
}

function slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim($value, '_') ?: 'branch';
}

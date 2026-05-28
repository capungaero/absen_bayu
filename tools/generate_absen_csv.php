<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '1');

define('BASEPATH', __DIR__ . '/../');
$db = [];
require __DIR__ . '/../application/config/database.php';

$opts = getopt('', ['from::', 'to::', 'as-of::', 'out::']);
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week', strtotime($today)));
$weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($today)));
$from = $opts['from'] ?? $weekStart;
$to = $opts['to'] ?? $weekEnd;
$asOf = $opts['as-of'] ?? $today;
$out = $opts['out'] ?? 'absen.csv';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from . '') || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to . '') || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf . '')) {
    throw new InvalidArgumentException('Format tanggal wajib YYYY-MM-DD.');
}
if ($from > $to) {
    throw new InvalidArgumentException('--from tidak boleh lebih besar dari --to.');
}
if ($asOf < $from) {
    $asOf = $from;
}
if ($asOf > $to) {
    $asOf = $to;
}

$cfg = $db['default'];
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli($cfg['hostname'], $cfg['username'], $cfg['password'], $cfg['database'], (int) $cfg['port']);
$mysqli->set_charset('utf8');

$employees = fetchEmployees($mysqli);
$employeeIds = array_map(static fn(array $row): int => (int) $row['id'], $employees);
$schedules = fetchSchedules($mysqli, $from, $to, $employeeIds);
$presenceRows = fetchPresence($mysqli, $from, $to, $employeeIds);
$leaves = fetchLeaves($mysqli, $from, $to, $employeeIds);
$days = makeDays($from, $to);

$outPath = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $out);
if (!is_dir(dirname($outPath))) {
    mkdir(dirname($outPath), 0777, true);
}

$handle = fopen($outPath, 'wb');
if (!$handle) {
    throw new RuntimeException('Gagal tulis file: ' . $outPath);
}

fputcsv($handle, [
    'periode_mulai', 'periode_selesai', 'as_of', 'user_id', 'id_fingerprint', 'nama',
    'cabang', 'divisi', 'posisi', 'tanggal', 'tipe_jadwal', 'shift', 'jam_shift',
    'masuk', 'pulang', 'telat_menit', 'status', 'kode_status', 'catatan',
]);

$summary = initSummary();
foreach ($employees as $employee) {
    foreach ($days as $date) {
        $key = (int) $employee['id'] . '|' . $date;
        $schedule = $schedules[$key] ?? null;
        $bucket = $presenceRows[$key] ?? ['all' => [], 'approved' => []];
        $presence = $bucket['approved'] ? end($bucket['approved']) : null;
        $notes = [];
        $status = classifyDay($date, $asOf, $schedule, $presence, $bucket, $leaves[$key] ?? [], $notes);
        incrementSummary($summary, $status, $date <= $asOf);

        fputcsv($handle, [
            $from,
            $to,
            $asOf,
            $employee['id'],
            $employee['employee_code'],
            $employee['employee_name'],
            $employee['branch_name'],
            $employee['division_name'],
            $employee['position_name'],
            $date,
            $schedule['additional_type'] ?? '',
            $schedule ? (($schedule['shift_code'] ?? '-') . ' / ' . ($schedule['shift_name'] ?? '-')) : '',
            $schedule ? (($schedule['start_time'] ?? '-') . ' - ' . ($schedule['end_time'] ?? '-')) : '',
            timeOnly($presence['entry_time'] ?? null),
            timeOnly($presence['out_time'] ?? null),
            (int) ($presence['entry_time_late'] ?? 0),
            $status,
            statusCode($status),
            implode('; ', $notes),
        ]);
    }
}

fclose($handle);

echo "OK\n";
echo "file={$outPath}\n";
echo "periode={$from}..{$to}\n";
echo "as_of={$asOf}\n";
echo "employees=" . count($employees) . "\n";
foreach ($summary as $key => $value) {
    echo "{$key}={$value}\n";
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

function fetchEmployees(mysqli $db): array
{
    return queryAll($db, "
        SELECT
            u.id,
            u.employee_code,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
            b.branch_name,
            COALESCE(sd.subdivision_name, '-') AS division_name,
            COALESCE(p.position_name, '-') AS position_name
        FROM users u
        JOIN position p ON p.id = u.position_id
        JOIN branch b ON b.id = p.branch_id
        LEFT JOIN subdivision sd ON sd.id = u.subdivision_id
        WHERE u.active = 1
        ORDER BY b.branch_name, sd.subdivision_name, employee_name
    ");
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
            s.shift_code,
            s.shift_name,
            s.start_time,
            s.end_time
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
        $out[$key] ??= ['all' => [], 'approved' => []];
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

function classifyDay(string $date, string $asOf, ?array $schedule, ?array $presence, array $bucket, array $leaves, array &$notes): string
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
        if ($bucket['all']) {
            $notes[] = 'Ada presence non-approved';
            return 'TIDAK_LENGKAP';
        }
        return $isFuture ? 'FUTURE' : 'ABSEN';
    }
    if (count($bucket['approved']) > 1) {
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

function initSummary(): array
{
    return [
        'hari_kerja' => 0, 'hadir' => 0, 'terlambat' => 0, 'izin' => 0,
        'cuti' => 0, 'sakit' => 0, 'absen' => 0, 'tidak_lengkap' => 0,
        'libur' => 0, 'tanpa_jadwal' => 0, 'presensi_tanpa_jadwal' => 0, 'future' => 0,
    ];
}

function incrementSummary(array &$summary, string $status, bool $countedDate): void
{
    $map = [
        'HADIR' => 'hadir', 'TERLAMBAT' => 'terlambat', 'IZIN' => 'izin',
        'CUTI' => 'cuti', 'SAKIT' => 'sakit', 'ABSEN' => 'absen',
        'TIDAK_LENGKAP' => 'tidak_lengkap', 'LIBUR' => 'libur',
        'TANPA_JADWAL' => 'tanpa_jadwal', 'PRESENSI_TANPA_JADWAL' => 'presensi_tanpa_jadwal',
        'FUTURE' => 'future',
    ];
    if (isset($map[$status])) {
        $summary[$map[$status]]++;
    }
    if (in_array($status, ['HADIR', 'TERLAMBAT', 'IZIN', 'CUTI', 'SAKIT', 'ABSEN', 'TIDAK_LENGKAP'], true) && $countedDate) {
        $summary['hari_kerja']++;
    }
}

function idList(array $ids): string
{
    return implode(',', array_map(static fn($id): string => (string) (int) $id, $ids));
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

<?php
/**
 * Terima absen GPS dari aplikasi lacak_fittany (4dm1n.my.id/lacak).
 * Versi produksi VPS (absen.4dm1n.my.id / DB absen_copy) — porting dari
 * tiffany.my.id/absen/gps_presence_sync.php.
 *
 * POST JSON: {date, otThresholdHours?, dryRun?, entries: [{employeeId, name, entryTime, outTime, overtimeHours?}]}
 * Auth: header X-Sync-Token harus cocok dengan isi /etc/absen-gps-sync-token
 *
 * Aturan:
 * - User dicari via employee_code (active=1) dan nama harus cocok; kalau nama beda,
 *   fallback cari via nama persis. Tidak ketemu -> entry dilewati (dilaporkan).
 * - Presence: baris user+flow_date yang sudah ada TIDAK ditimpa; hanya mengisi
 *   entry_time/out_time yang masih NULL. Baris baru ditandai input_by='system'.
 * - Koneksi set @absen_sync_ctx=1 supaya trigger provenance tidak mem-flag 'manual'.
 * - Lembur: pakai overtimeHours kiriman lacak (sudah aturan Tiffany + pembulatan),
 *   fallback threshold; insert pengajuan pending kalau belum ada di tanggal itu.
 */
header('Content-Type: application/json');

$tokenFile = '/etc/absen-gps-sync-token';
$given = $_SERVER['HTTP_X_SYNC_TOKEN'] ?? '';
$expected = trim(@file_get_contents($tokenFile) ?: '');
if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(401);
    echo json_encode(['error' => 'token salah']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST saja']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['date']) || !is_array($input['entries'] ?? null)) {
    http_response_code(400);
    echo json_encode(['error' => 'payload tidak valid']);
    exit;
}

$db = new mysqli(
    getenv('ABSEN_DB_HOST') ?: '127.0.0.1',
    getenv('ABSEN_DB_USER') ?: '',
    getenv('ABSEN_DB_PASS') ?: '',
    getenv('ABSEN_DB_NAME') ?: 'absen_copy',
    (int)(getenv('ABSEN_DB_PORT') ?: 3306)
);
if ($db->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'koneksi database gagal']);
    exit;
}
$db->set_charset('utf8mb4');
// Tandai koneksi ini jalur sync resmi (trigger provenance biarkan input_by='system')
$db->query('SET @absen_sync_ctx = 1');

$date = $input['date'];
$threshold = (float)($input['otThresholdHours'] ?? 10);
$dryRun = !empty($input['dryRun']);
$results = [];

function find_user($db, $employeeId, $name)
{
    $name = mb_strtoupper(trim($name));
    $stmt = $db->prepare("SELECT id, employee_code, UPPER(TRIM(first_name)) AS nm FROM users WHERE employee_code = ? AND active = 1 ORDER BY id DESC");
    $stmt->bind_param('s', $employeeId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $row) {
        if ($name === '' || $row['nm'] === $name) {
            return [$row['id'], 'employee_code'];
        }
    }
    if ($name !== '') {
        $stmt = $db->prepare("SELECT id FROM users WHERE UPPER(TRIM(first_name)) = ? AND active = 1 ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            return [$row['id'], 'nama'];
        }
    }
    if ($rows) {
        return [null, 'kode cocok tapi nama beda: ' . ($rows[0]['nm'] ?: '?')];
    }
    return [null, 'tidak ditemukan'];
}

foreach ($input['entries'] as $entry) {
    $eid = trim((string)($entry['employeeId'] ?? ''));
    $name = trim((string)($entry['name'] ?? ''));
    $in = trim((string)($entry['entryTime'] ?? ''));
    $out = trim((string)($entry['outTime'] ?? ''));
    $res = ['employeeId' => $eid, 'name' => $name];

    $tIn = strtotime($in);
    $tOut = strtotime($out);
    if (!$eid || !$tIn || !$tOut || $tOut <= $tIn) {
        $res['status'] = 'dilewati';
        $res['reason'] = 'waktu tidak valid';
        $results[] = $res;
        continue;
    }

    [$userId, $how] = find_user($db, $eid, $name);
    if (!$userId) {
        $res['status'] = 'dilewati';
        $res['reason'] = 'user: ' . $how;
        $results[] = $res;
        continue;
    }
    $res['userId'] = (int)$userId;
    $res['matchedBy'] = $how;

    // Presence: isi yang kosong saja, jangan timpa data fingerprint/manual
    $stmt = $db->prepare("SELECT id, entry_time, out_time FROM presence WHERE user_id = ? AND flow_date = ? ORDER BY id LIMIT 1");
    $stmt->bind_param('is', $userId, $date);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    if ($existing) {
        $sets = [];
        if ($existing['entry_time'] === null) $sets[] = "entry_time = '" . $db->real_escape_string($in) . "'";
        if ($existing['out_time'] === null) $sets[] = "out_time = '" . $db->real_escape_string($out) . "'";
        if ($sets) {
            $res['presence'] = 'dilengkapi (' . count($sets) . ' kolom)';
            if (!$dryRun) {
                $db->query("UPDATE presence SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = " . (int)$existing['id']);
            }
        } else {
            $res['presence'] = 'sudah lengkap, dilewati';
        }
    } else {
        $res['presence'] = 'baris baru';
        if (!$dryRun) {
            $stmt = $db->prepare("INSERT INTO presence (user_id, entry_time, out_time, flow_date, created_at, updated_at, input_by, presence_type, presence_status) VALUES (?, ?, ?, ?, NOW(), NOW(), 'system', 'normal', 'approved')");
            $stmt->bind_param('isss', $userId, $in, $out, $date);
            $stmt->execute();
        }
    }

    // Lembur: utamakan jam kiriman lacak (aturan Tiffany, sudah dibulatkan),
    // fallback hitung threshold untuk payload lama tanpa field overtimeHours.
    $hours = ($tOut - $tIn) / 3600;
    $res['workHours'] = round($hours, 2);
    if (array_key_exists('overtimeHours', $entry) && is_numeric($entry['overtimeHours'])) {
        $excess = round((float)$entry['overtimeHours'], 1);
        $res['overtimeBasis'] = 'aturan tiffany (lacak)';
    } else {
        $excess = $hours > $threshold ? round($hours - $threshold, 1) : 0.0;
        $res['overtimeBasis'] = 'threshold';
    }
    if ($excess > 0) {
        // Satu pengajuan aktif (pending/approve) per karyawan per tanggal --
        // konvensi seragam dgn jalur manual aplikasi (M.php/Api.php/hr Overtime,
        // 21 Agu 2026). deny/cancel TIDAK menghalangi pengajuan baru (dulu deny
        // ikut memblokir di sini -- beda aturan dgn jalur manual). Backstop
        // terakhir: unique index uniq_active_overtime di DB (error 1062 kalau
        // race dgn pengajuan manual bersamaan -> ditangkap sbg 'sudah ada').
        $stmt = $db->prepare("SELECT id FROM overtime WHERE user_id = ? AND overtime_date = ? AND deleted_at IS NULL AND overtime_status IN ('pending','approve') LIMIT 1");
        $stmt->bind_param('is', $userId, $date);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $res['overtime'] = "pengajuan lembur a.n. {$entry['name']} untuk tanggal {$date} sudah ada, dilewati";
        } else {
            $res['overtime'] = "pengajuan {$excess} jam";
            if (!$dryRun) {
                $proof = 'GPS lacak_fittany otomatis';
                $stmt = $db->prepare("INSERT INTO overtime (user_id, overtime_proof, overtime_hour, overtime_date, overtime_status, created_at, updated_at) VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())");
                $stmt->bind_param('isds', $userId, $proof, $excess, $date);
                if (!$stmt->execute()) {
                    $res['overtime'] = "pengajuan lembur a.n. {$entry['name']} untuk tanggal {$date} sudah ada, dilewati";
                }
            }
        }
    } else {
        $res['overtime'] = 'tidak ada';
    }
    $res['status'] = $dryRun ? 'dry-run' : 'ok';
    $results[] = $res;
}

$db->close();
echo json_encode([
    'ok' => true,
    'date' => $date,
    'dryRun' => $dryRun,
    'results' => $results,
]);

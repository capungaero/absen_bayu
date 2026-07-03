<?php
/**
 * rollback_presence.php — Skrip CLI untuk Rollback Masal Absensi (Point-in-Time Recovery)
 *
 * Mengembalikan kondisi tabel `presence` tepat ke waktu tertentu di masa lalu
 * berdasarkan rekaman jejak pada tabel `audit_log`.
 *
 * Penggunaan (via CLI):
 *   php scripts/rollback_presence.php --target="2026-06-29 21:00:00" --dry-run
 *   php scripts/rollback_presence.php --target="2026-06-29 21:00:00" --execute
 */

error_reporting(E_ALL & ~E_DEPRECATED);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Error: Skrip ini hanya boleh dijalankan melalui CLI.\n");
    exit(1);
}

// 1. Parse Argumen
$options = getopt('', ['target:', 'dry-run', 'execute', 'help']);

if (isset($options['help']) || !isset($options['target'])) {
    echo "=================================================================\n";
    echo "  E-ABSENSI Presence Point-in-Time Rollback Tool\n";
    echo "=================================================================\n";
    echo "Opsi:\n";
    echo "  --target=\"YYYY-MM-DD HH:MM:SS\"  Waktu target rollback (wajib)\n";
    echo "  --dry-run                       Simulasi rollback tanpa ubah DB\n";
    echo "  --execute                       Jalankan rollback di DB (dalam transaksi)\n";
    echo "  --help                          Tampilkan panduan ini\n";
    echo "=================================================================\n";
    exit(isset($options['help']) ? 0 : 1);
}

$target_time = trim($options['target']);
$timestamp = strtotime($target_time);
if (!$timestamp) {
    fwrite(STDERR, "Error: Format waktu --target tidak valid. Gunakan format \"YYYY-MM-DD HH:MM:SS\".\n");
    exit(1);
}
$target_time = date('Y-m-d H:i:s', $timestamp);
$is_execute  = isset($options['execute']);
$is_dry_run  = isset($options['dry-run']) || !$is_execute;

echo "=================================================================\n";
echo "  PRESENCE ROLLBACK TOOL\n";
echo "=================================================================\n";
echo "Target Waktu : {$target_time}\n";
echo "Mode         : " . ($is_execute ? "EXECUTE (Perubahan akan disimpan)" : "DRY-RUN (Simulasi saja)") . "\n";
echo "=================================================================\n\n";

// 2. Koneksi Database (Mendukung live server ~/.absen.cnf atau dev lokal)
$db = null;
$cnf_path = getenv('HOME') . '/.absen.cnf';
if (file_exists($cnf_path)) {
    $cnf = parse_ini_file($cnf_path, true);
    $c   = isset($cnf['client']) ? $cnf['client'] : $cnf;
    $host = isset($c['host']) ? $c['host'] : '127.0.0.1';
    $user = isset($c['user']) ? $c['user'] : 'root';
    $pass = isset($c['password']) ? $c['password'] : '';
    $dbname = 'tifx3722_newtiffa_timesheet';
    $db = @new mysqli($host, $user, $pass, $dbname);
}

if (!$db || $db->connect_errno) {
    // Fallback coba lokal XAMPP
    $db = @new mysqli('127.0.0.1', 'root', '', 'newtiffa_timesheet');
    if ($db->connect_errno) {
        fwrite(STDERR, "Error Koneksi DB: Gagal terhubung ke database live maupun lokal.\n");
        exit(1);
    }
}
$db->set_charset('utf8mb4');

// 3. Validasi Batas Waktu Audit
$res = $db->query("SELECT MIN(changed_at) AS min_time FROM audit_log WHERE table_name = 'presence'");
$row = $res->fetch_assoc();
$min_time = $row['min_time'] ?? null;

if (!$min_time || $target_time < $min_time) {
    fwrite(STDERR, "Error: Target waktu ({$target_time}) lebih awal dari rekam audit tertua ({$min_time}).\n");
    exit(1);
}

// 4. Identifikasi Semua Record yang Berubah Setelah Waktu Target
echo "Mencari log perubahan setelah {$target_time}...\n";
$stmt = $db->prepare("
    SELECT al.record_id, al.id AS first_log_id, al.action, al.before_data
    FROM audit_log al
    INNER JOIN (
        SELECT record_id, MIN(id) AS min_id
        FROM audit_log
        WHERE table_name = 'presence' AND changed_at > ?
        GROUP BY record_id
    ) first_logs ON al.id = first_logs.min_id
");
$stmt->bind_param('s', $target_time);
$stmt->execute();
$result = $stmt->get_result();

$actions_plan = [
    'to_update'   => [], // Record ada, kembalikan ke before_data
    'to_delete'   => [], // Record dibuat setelah target, hapus
    'to_reinsert' => [], // Record sempat terhapus, kembalikan
];

$allowed_fields = [
    'entry_time', 'entry_time_late', 'out_time',
    'rest_time_in', 'rest_time_out', 'rest_time_late',
    'subuh_time_in', 'subuh_time_out', 'subuh_time_late',
    'dzuhur_time_in', 'dzuhur_time_out', 'dzuhur_time_late',
    'ashar_time_in', 'ashar_time_out', 'ashar_time_late',
    'maghrib_time_in', 'maghrib_time_out', 'maghrib_time_late',
    'isha_time_in', 'isha_time_out', 'isha_time_late',
    'friday_time_in', 'friday_time_out', 'friday_time_late',
    'presence_type', 'presence_status', 'presence_get_paid',
    'input_by', 'input_by_user_id', 'is_overtime', 'flag',
    'is_early_leave', 'early_leave_short_minutes'
];

while ($row = $result->fetch_assoc()) {
    $rec_id = (int)$row['record_id'];
    $action = $row['action'];
    $before = json_decode($row['before_data'] ?? '{}', true) ?: [];

    // Cek eksistensi record di tabel presence saat ini
    $chk = $db->query("SELECT id FROM presence WHERE id = {$rec_id}");
    $exists = $chk->num_rows > 0;

    if ($action === 'INSERT') {
        // Log pertama adalah INSERT setelah target waktu -> berarti sebelum target waktu, record ini belum ada.
        if ($exists) {
            $actions_plan['to_delete'][] = $rec_id;
        }
    } elseif (in_array($action, ['UPDATE', 'DELETE'])) {
        // Sebelum target waktu, record ini ada dengan isi $before.
        $restore_data = [];
        foreach ($allowed_fields as $f) {
            if (array_key_exists($f, $before)) {
                $restore_data[$f] = $before[$f];
            }
        }

        if ($exists) {
            $actions_plan['to_update'][$rec_id] = $restore_data;
        } else {
            // Record terhapus saat ini, perlu re-insert lengkap dengan user_id & flow_date
            $meta_res = $db->query("
                SELECT before_data, after_data FROM audit_log
                WHERE table_name = 'presence' AND record_id = {$rec_id}
                AND (before_data LIKE '%\"user_id\"%' OR after_data LIKE '%\"user_id\"%')
                LIMIT 1
            ");
            if ($meta_row = $meta_res->fetch_assoc()) {
                $b_json = json_decode($meta_row['before_data'] ?? '{}', true) ?: [];
                $a_json = json_decode($meta_row['after_data'] ?? '{}', true) ?: [];
                $uid  = $before['user_id'] ?? $b_json['user_id'] ?? $a_json['user_id'] ?? null;
                $fdate = $before['flow_date'] ?? $b_json['flow_date'] ?? $a_json['flow_date'] ?? null;

                if ($uid && $fdate) {
                    $restore_data['id'] = $rec_id;
                    $restore_data['user_id'] = (int)$uid;
                    $restore_data['flow_date'] = $fdate;
                    $actions_plan['to_reinsert'][$rec_id] = $restore_data;
                }
            }
        }
    }
}
$stmt->close();

$c_upd = count($actions_plan['to_update']);
$c_del = count($actions_plan['to_delete']);
$c_ins = count($actions_plan['to_reinsert']);

echo "\nRingkasan Rencana Rollback:\n";
echo "  - Akan dikembalikan nilainya (UPDATE) : {$c_upd} baris\n";
echo "  - Akan dihapus (DELETE)               : {$c_del} baris\n";
echo "  - Akan di-insert ulang (REINSERT)     : {$c_ins} baris\n";
echo "-----------------------------------------------------------------\n";

if ($c_upd + $c_del + $c_ins === 0) {
    echo "Tidak ada perubahan yang diperlukan. Data sudah sesuai target waktu.\n";
    $db->close();
    exit(0);
}

if ($is_dry_run) {
    echo "MODE DRY-RUN: Tidak ada eksekusi ke database.\n";
    echo "Gunakan flag --execute untuk menjalankan rollback secara nyata.\n";
    $db->close();
    exit(0);
}

// 5. Eksekusi Rollback dalam Transaksi
echo "Mulai mengeksekusi rollback di dalam transaksi...\n";
$db->begin_transaction();

try {
    // Set audit user ID (0 = system rollback)
    $db->query("SET @audit_user_id = 0");

    $exec_upd = 0;
    foreach ($actions_plan['to_update'] as $rec_id => $data) {
        $sets = [];
        foreach ($data as $k => $v) {
            $val_sql = ($v === null) ? 'NULL' : "'" . $db->real_escape_string($v) . "'";
            $sets[] = "`{$k}` = {$val_sql}";
        }
        if (!empty($sets)) {
            $sql = "UPDATE presence SET " . implode(', ', $sets) . " WHERE id = {$rec_id}";
            if (!$db->query($sql)) {
                throw new Exception("Gagal update record {$rec_id}: " . $db->error);
            }
            $exec_upd++;
        }
    }

    $exec_del = 0;
    if (!empty($actions_plan['to_delete'])) {
        $ids = implode(',', array_map('intval', $actions_plan['to_delete']));
        if (!$db->query("DELETE FROM presence WHERE id IN ({$ids})")) {
            throw new Exception("Gagal delete record baru: " . $db->error);
        }
        $exec_del = $db->affected_rows;
    }

    $exec_ins = 0;
    foreach ($actions_plan['to_reinsert'] as $rec_id => $data) {
        $cols = array_keys($data);
        $vals = array_map(function($v) use ($db) {
            return ($v === null) ? 'NULL' : "'" . $db->real_escape_string($v) . "'";
        }, array_values($data));

        $sql = "INSERT INTO presence (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $vals) . ")";
        if (!$db->query($sql)) {
            throw new Exception("Gagal reinsert record {$rec_id}: " . $db->error);
        }
        $exec_ins++;
    }

    $db->commit();
    echo "\nSUKSES! Rollback selesai dieksekusi:\n";
    echo "  - Berhasil di-update : {$exec_upd} baris\n";
    echo "  - Berhasil di-delete : {$exec_del} baris\n";
    echo "  - Berhasil di-insert : {$exec_ins} baris\n";

} catch (Exception $e) {
    $db->rollback();
    fwrite(STDERR, "\nERROR: Rollback dibatalkan karena kesalahan: " . $e->getMessage() . "\n");
    $db->close();
    exit(1);
}

$db->close();
exit(0);

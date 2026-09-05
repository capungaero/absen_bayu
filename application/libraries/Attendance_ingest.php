<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Attendance_ingest
 *
 * Memasukkan isi file .dat mesin Solution Cloud ke lapis RAW:
 *   attendance_tap_file : arsip file, dedup sha256
 *   attendance_tap      : 1 baris = 1 tap, immutable, dedup
 *                         UNIQUE (machine_sn, finger_id, tap_at)
 *   attendance_day      : ditandai needs_reclass=1 untuk (user, tanggal) tersentuh
 *
 * Sifat INKREMENTAL & IDEMPOTEN: penulisan tap memakai INSERT IGNORE, jadi
 * mengulang file yang sama, file superset, atau file yang tumpang tindih
 * periodenya tidak menghasilkan baris ganda dan tidak butuh watermark.
 * affected_rows() sesudah INSERT IGNORE = jumlah tap yang benar-benar baru.
 *
 * Library ini TIDAK menyentuh tabel `presence` sama sekali.
 *
 * Pemakaian:
 *   $this->load->library('attendance_ingest');
 *   $res = $this->attendance_ingest->ingest_raw($raw, $sn, [
 *       'machine_id' => 3, 'origin' => 'cloud', 'stored_path' => $path,
 *   ]);
 */
class Attendance_ingest
{
    /** Sekali INSERT maksimal sekian baris tap. */
    const CHUNK = 500;

    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->library('attlog_parser');
        $this->CI->load->library('attendance_employee_resolver');
        $this->CI->load->helper('attlog');
    }

    // =====================================================================
    // INGEST
    // =====================================================================

    /**
     * Ingest isi file .dat.
     *
     * $meta: machine_id, machine_type, origin (cloud|upload|cli), stored_path,
     *        downloaded_at, created_by, keep_raw (bool, default TRUE).
     *
     * Return:
     *   [
     *     'ok' => bool, 'status' => 'ingested'|'duplicate'|'invalid',
     *     'file_id' => int|null, 'new_taps' => int, 'dup_taps' => int,
     *     'total_lines' => int, 'invalid_count' => int,
     *     'dirty_days' => int,   // jumlah baris attendance_day ditandai needs_reclass
     *     'unresolved_fingers' => [finger_id, ...],
     *     'message' => string,
     *   ]
     */
    public function ingest_raw($raw, $machine_sn, $meta = [])
    {
        $machine_sn = attlog_sanitize_machine_sn($machine_sn);
        if ($machine_sn === '') {
            return $this->_fail('SN mesin kosong / tidak valid.');
        }

        $raw = (string) $raw;
        if (trim($raw) === '') {
            return $this->_fail('Isi file .dat kosong.');
        }

        $now    = date('Y-m-d H:i:s');
        $sha    = hash('sha256', $raw);
        $origin = isset($meta['origin']) ? $meta['origin'] : 'cloud';

        // Tipe mesin menentukan apakah tap ini jam kerja atau sholat. Master
        // sync_machine yang berwenang -- pemanggil (mis. backfill CLI) sering
        // tidak tahu tipenya, dan salah label akan mencemari klasifikasi.
        $machine_type = $this->machine_type_of($machine_sn,
            isset($meta['machine_type']) ? $meta['machine_type'] : NULL);

        // -- 1. Arsip file. sha256 sama = sudah pernah diproses, berhenti di sini.
        $existing = $this->CI->db->select('id, status')
            ->where('sha256', $sha)->get('attendance_tap_file')->row_array();
        if (!empty($existing) && $existing['status'] === 'ingested') {
            return [
                'ok' => TRUE, 'status' => 'duplicate', 'file_id' => (int) $existing['id'],
                'new_taps' => 0, 'dup_taps' => 0, 'total_lines' => 0, 'invalid_count' => 0,
                'dirty_days' => 0, 'unresolved_fingers' => [],
                'message' => 'File identik sudah pernah di-ingest (file_id '.$existing['id'].'), dilewati.',
            ];
        }

        $parsed = $this->CI->attlog_parser->parse_tap_list($raw);
        $taps   = $parsed['taps'];
        $stats  = $parsed['stats'];

        $file_row = [
            'machine_id'    => isset($meta['machine_id']) ? (int) $meta['machine_id'] : NULL,
            'machine_sn'    => $machine_sn,
            'machine_type'  => $machine_type,
            'sha256'        => $sha,
            'byte_size'     => strlen($raw),
            'line_count'    => $stats['total_lines'],
            'invalid_count' => $stats['invalid_count'],
            'first_tap_at'  => $stats['first_tap_at'],
            'last_tap_at'   => $stats['last_tap_at'],
            'stored_path'   => isset($meta['stored_path']) ? $this->_relative_path($meta['stored_path']) : NULL,
            'raw_gz'        => (!isset($meta['keep_raw']) || $meta['keep_raw']) ? gzencode($raw, 6) : NULL,
            'origin'        => $origin,
            'status'        => 'pending',
            'created_by'    => isset($meta['created_by']) ? (int) $meta['created_by'] : NULL,
            'downloaded_at' => isset($meta['downloaded_at']) ? $meta['downloaded_at'] : $now,
        ];

        if (!empty($existing)) {
            $this->CI->db->where('id', $existing['id'])->update('attendance_tap_file', $file_row);
            $file_id = (int) $existing['id'];
        } else {
            $this->CI->db->insert('attendance_tap_file', $file_row);
            $file_id = (int) $this->CI->db->insert_id();
        }

        if (empty($taps)) {
            $msg = 'Tidak ada tap valid. Baris terbaca: '.$stats['total_lines'].', tidak valid: '.$stats['invalid_count'].'.';
            $this->CI->db->where('id', $file_id)->update('attendance_tap_file', [
                'status' => 'invalid', 'message' => $msg, 'ingested_at' => $now,
            ]);
            return $this->_fail($msg, $file_id);
        }

        // -- 2. Tap: INSERT IGNORE, hanya yang belum ada yang masuk.
        $new_taps = $this->_insert_taps($taps, $machine_sn, $machine_type, $file_id, $now);
        $dup_taps = count($taps) - $new_taps;

        // -- 3. Resolusi finger -> user untuk rentang tanggal yang tersentuh.
        $from = substr($stats['first_tap_at'], 0, 10);
        $to   = substr($stats['last_tap_at'], 0, 10);
        $resolved = $this->resolve_taps($from, $to);

        // -- 4. Tandai hari yang perlu diklasifikasi ulang — hanya yang tapnya
        //       benar-benar masuk lewat file ini, bukan seluruh rentang tanggal.
        $dirty = $new_taps > 0 ? $this->mark_dirty_days($from, $to, $file_id) : 0;

        $msg = 'Baris file: '.$stats['total_lines']
             .', tap valid: '.$stats['raw_count']
             .', tidak valid: '.$stats['invalid_count']
             .', tap baru: '.$new_taps
             .', sudah ada: '.$dup_taps
             .', finger unik: '.$stats['unique_finger_ids']
             .', tap ter-resolve: '.$resolved['resolved']
             .', finger tak dikenal: '.count($resolved['unresolved_fingers'])
             .', hari ditandai: '.$dirty.'.';

        $this->CI->db->where('id', $file_id)->update('attendance_tap_file', [
            'status' => 'ingested', 'new_tap_count' => $new_taps, 'dup_tap_count' => $dup_taps,
            'message' => $msg, 'ingested_at' => $now,
        ]);

        return [
            'ok' => TRUE, 'status' => 'ingested', 'file_id' => $file_id,
            'new_taps' => $new_taps, 'dup_taps' => $dup_taps,
            'total_lines' => $stats['total_lines'], 'invalid_count' => $stats['invalid_count'],
            'dirty_days' => $dirty, 'unresolved_fingers' => $resolved['unresolved_fingers'],
            'message' => $msg,
        ];
    }

    /** Ingest satu file .dat dari disk. SN ditebak dari nama file bila tidak diberi. */
    public function ingest_file($path, $machine_sn = NULL, $meta = [])
    {
        if (!is_file($path) || !is_readable($path)) {
            return $this->_fail('File tidak terbaca: '.$path);
        }
        if ($machine_sn === NULL) {
            $machine_sn = $this->sn_from_filename($path);
        }
        $meta['stored_path'] = $path;
        if (!isset($meta['downloaded_at'])) {
            $meta['downloaded_at'] = date('Y-m-d H:i:s', filemtime($path));
        }
        return $this->ingest_raw(file_get_contents($path), $machine_sn, $meta);
    }

    /**
     * Tipe mesin (attendance|pray) untuk sebuah SN.
     *
     * Master `sync_machine` yang berwenang; $fallback baru dipakai kalau SN-nya
     * tidak terdaftar (mis. file upload lepas). Salah tipe berakibat fatal:
     * window sholat (dzuhur ~11:45-13:00) tumpang tindih dengan window masuk
     * shift siang, sehingga tap sholat yang dikira tap kerja akan menggeser
     * jam masuk dan menit telat.
     */
    public function machine_type_of($machine_sn, $fallback = NULL)
    {
        $row = $this->CI->db->select('machine_type')
            ->where('machine_sn', $machine_sn)
            ->get('sync_machine')->row_array();
        if (!empty($row['machine_type'])) { return $row['machine_type']; }
        return $fallback !== NULL ? $fallback : 'attendance';
    }

    /** attlog_<SN>_<Ymd>_<His>.dat -> SN. String kosong kalau pola tidak cocok. */
    public function sn_from_filename($path)
    {
        if (preg_match('/attlog_(.+)_\d{8}_\d{6}\.dat$/', basename($path), $m)) {
            return attlog_sanitize_machine_sn($m[1]);
        }
        return '';
    }

    // =====================================================================
    // RESOLUSI & PENANDAAN
    // =====================================================================

    /**
     * Isi attendance_tap.user_id untuk tap yang belum ter-resolve pada rentang.
     * Resolusi PER TANGGAL (karyawan bisa pindah / employee_code dipakai ulang).
     * Return ['resolved' => int, 'unresolved_fingers' => [...]].
     */
    public function resolve_taps($from, $to)
    {
        $rows = $this->CI->db->select('DISTINCT finger_id, tap_date', FALSE)
            ->where('user_id IS NULL', NULL, FALSE)
            ->where('tap_date >=', $from)->where('tap_date <=', $to)
            ->get('attendance_tap')->result_array();

        if (empty($rows)) {
            return ['resolved' => 0, 'unresolved_fingers' => []];
        }

        // Bentuk ulang jadi struktur yang dimengerti resolver.
        $row_data = [];
        foreach ($rows as $r) {
            $row_data[$r['finger_id']][$r['tap_date']] = ['date' => $r['tap_date'], 'time' => []];
        }

        $matcher = $this->CI->attendance_employee_resolver->build_by_finger_date($row_data, $from, $to);
        $map = $matcher['map'];

        $resolved = 0;
        $unresolved = [];
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $r) {
            $finger = $r['finger_id'];
            $date   = $r['tap_date'];
            if (empty($map[$finger][$date]['id'])) {
                if (!in_array($finger, $unresolved, TRUE)) { $unresolved[] = $finger; }
                continue;
            }
            $this->CI->db->where('finger_id', $finger)
                ->where('tap_date', $date)
                ->where('user_id IS NULL', NULL, FALSE)
                ->update('attendance_tap', [
                    'user_id' => (int) $map[$finger][$date]['id'], 'resolved_at' => $now,
                ]);
            $resolved += $this->CI->db->affected_rows();
        }

        return ['resolved' => $resolved, 'unresolved_fingers' => $unresolved];
    }

    /**
     * Pastikan ada baris attendance_day untuk setiap (user, tanggal) yang punya
     * tap JAM KERJA pada rentang, dan tandai needs_reclass=1. Tap sholat tidak
     * membuat baris harian — lapis harian ini khusus jam kerja.
     *
     * $file_id dibatasi ke tap yang MASUK lewat file itu, supaya ingest tidak
     * menandai ulang seluruh riwayat tiap kali dijalankan — hanya hari yang
     * benar-benar berubah yang perlu diklasifikasi ulang.
     *
     * Return jumlah baris (user, tanggal) berbeda yang ditandai.
     */
    public function mark_dirty_days($from, $to, $file_id = NULL)
    {
        $now = $this->CI->db->escape(date('Y-m-d H:i:s'));
        $params = [$from, $to];
        $scope = '';
        if ($file_id !== NULL) {
            $scope = ' AND t.file_id = ?';
            $params[] = (int) $file_id;
        }

        $sql = "INSERT INTO attendance_day (user_id, flow_date, needs_reclass, created_at)
                SELECT t.user_id, t.tap_date, 1, $now
                FROM attendance_tap t
                WHERE t.user_id IS NOT NULL AND t.machine_type = 'attendance'
                      AND t.tap_date >= ? AND t.tap_date <= ?$scope
                GROUP BY t.user_id, t.tap_date
                ON DUPLICATE KEY UPDATE needs_reclass = 1, updated_at = $now";
        $this->CI->db->query($sql, $params);

        // affected_rows() menghitung 2 untuk baris yang ter-update (MySQL),
        // jadi hitung ulang biar angkanya jujur.
        $this->CI->db->select('COUNT(*) AS n', FALSE)
            ->from('(SELECT t.user_id, t.tap_date FROM attendance_tap t
                     WHERE t.user_id IS NOT NULL AND t.machine_type = "attendance"
                       AND t.tap_date >= '.$this->CI->db->escape($from).'
                       AND t.tap_date <= '.$this->CI->db->escape($to).
                     ($file_id !== NULL ? ' AND t.file_id = '.(int) $file_id : '').'
                     GROUP BY t.user_id, t.tap_date) d', FALSE);
        $row = $this->CI->db->get()->row_array();
        return isset($row['n']) ? (int) $row['n'] : 0;
    }

    // =====================================================================
    // TAP MANUAL & VOID
    // =====================================================================

    /**
     * Tambah tap atas nama manusia (mis. lupa finger scan). Masuk tabel yang
     * sama dengan machine_sn='MANUAL' supaya ikut diklasifikasi seperti tap mesin.
     * Return ['ok' => bool, 'tap_id' => int|null, 'message' => string].
     */
    public function add_manual_tap($user_id, $datetime, $created_by, $note = '')
    {
        $user_id = (int) $user_id;
        $timestamp = strtotime((string) $datetime);
        if ($user_id <= 0 || !$timestamp) {
            return ['ok' => FALSE, 'tap_id' => NULL, 'message' => 'Mitra kerja / waktu tap tidak valid.'];
        }

        $user = $this->CI->db->select('employee_code')->where('id', $user_id)
            ->get('users')->row_array();
        if (empty($user['employee_code'])) {
            return ['ok' => FALSE, 'tap_id' => NULL, 'message' => 'Mitra kerja tidak punya employee_code.'];
        }

        $tap_at = date('Y-m-d H:i:s', $timestamp);
        $now = date('Y-m-d H:i:s');
        $this->CI->db->query(
            'INSERT IGNORE INTO attendance_tap
                (source, machine_sn, finger_id, tap_at, tap_date, user_id, resolved_at, created_by, note, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            ['manual', 'MANUAL', $user['employee_code'], $tap_at, date('Y-m-d', $timestamp),
             $user_id, $now, (int) $created_by, $note, $now]
        );
        if ($this->CI->db->affected_rows() < 1) {
            return ['ok' => FALSE, 'tap_id' => NULL, 'message' => 'Tap manual dengan waktu itu sudah ada.'];
        }

        $tap_id = (int) $this->CI->db->insert_id();
        $this->mark_dirty_days(date('Y-m-d', $timestamp), date('Y-m-d', $timestamp));
        return ['ok' => TRUE, 'tap_id' => $tap_id, 'message' => 'Tap manual '.$tap_at.' ditambahkan.'];
    }

    /** Batalkan tap tanpa mengubah barisnya. */
    public function void_tap($tap_id, $actor_id, $reason = '')
    {
        $tap = $this->CI->db->select('id, tap_date')->where('id', (int) $tap_id)
            ->get('attendance_tap')->row_array();
        if (empty($tap)) {
            return ['ok' => FALSE, 'message' => 'Tap tidak ditemukan.'];
        }
        $this->CI->db->query(
            'INSERT INTO attendance_tap_void (tap_id, voided_by, voided_at, reason)
             VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE reason = VALUES(reason)',
            [(int) $tap_id, (int) $actor_id, date('Y-m-d H:i:s'), $reason]
        );
        $this->mark_dirty_days($tap['tap_date'], $tap['tap_date']);
        return ['ok' => TRUE, 'message' => 'Tap dibatalkan.'];
    }

    /** Kembalikan tap yang sebelumnya dibatalkan. */
    public function unvoid_tap($tap_id)
    {
        $tap = $this->CI->db->select('id, tap_date')->where('id', (int) $tap_id)
            ->get('attendance_tap')->row_array();
        if (empty($tap)) {
            return ['ok' => FALSE, 'message' => 'Tap tidak ditemukan.'];
        }
        $this->CI->db->where('tap_id', (int) $tap_id)->delete('attendance_tap_void');
        $this->mark_dirty_days($tap['tap_date'], $tap['tap_date']);
        return ['ok' => TRUE, 'message' => 'Pembatalan tap dicabut.'];
    }

    // =====================================================================
    // BACA
    // =====================================================================

    /**
     * Tap pada rentang, dikelompokkan per "user_id|tap_date".
     * Default hanya tap jam kerja yang tidak dibatalkan.
     *
     * $machine_type WAJIB diisi saat mengklasifikasi: mencampur tap sholat ke
     * jam kerja menggeser jam masuk (window dzuhur menabrak window shift siang).
     * NULL = semua tipe, khusus untuk tampilan rincian tap di UI.
     */
    public function taps_for_range($user_ids, $from, $to, $include_voided = FALSE, $machine_type = 'attendance')
    {
        $this->CI->db->select('t.id, t.user_id, t.finger_id, t.tap_date, t.tap_at, t.source,
                               t.machine_sn, t.machine_type, t.note, t.created_by,
                               v.tap_id AS voided, v.reason AS void_reason', FALSE)
            ->from('attendance_tap t')
            ->join('attendance_tap_void v', 'v.tap_id = t.id', 'left')
            ->where('t.user_id IS NOT NULL', NULL, FALSE)
            ->where('t.tap_date >=', $from)
            ->where('t.tap_date <=', $to);

        if ($machine_type !== NULL) {
            $this->CI->db->where('t.machine_type', $machine_type);
        }
        if (!empty($user_ids)) {
            $this->CI->db->where_in('t.user_id', $user_ids);
        }
        if (!$include_voided) {
            $this->CI->db->where('v.tap_id IS NULL', NULL, FALSE);
        }

        $rows = $this->CI->db->order_by('t.tap_at', 'ASC')->get()->result_array();

        $out = [];
        foreach ($rows as $r) {
            $out[$r['user_id'].'|'.$r['tap_date']][] = $r;
        }
        return $out;
    }

    // =====================================================================
    // INTERNAL
    // =====================================================================

    /** INSERT IGNORE per potongan. Return jumlah baris yang benar-benar baru. */
    private function _insert_taps($taps, $machine_sn, $machine_type, $file_id, $now)
    {
        $new = 0;
        foreach (array_chunk($taps, self::CHUNK) as $chunk) {
            $place = [];
            $vals  = [];
            foreach ($chunk as $t) {
                $place[] = '(?,?,?,?,?,?,?,?,?,?)';
                array_push($vals,
                    'machine', $machine_sn, $machine_type, $t['finger_id'], $t['tap_at'], $t['tap_date'],
                    $t['raw_status'], $t['raw_verify'], $file_id, $now);
            }
            $this->CI->db->query(
                'INSERT IGNORE INTO attendance_tap
                    (source, machine_sn, machine_type, finger_id, tap_at, tap_date, raw_status, raw_verify, file_id, created_at)
                 VALUES '.implode(',', $place),
                $vals
            );
            $new += $this->CI->db->affected_rows();
        }
        return $new;
    }

    /** Simpan path relatif terhadap FCPATH supaya tidak bocor struktur server. */
    private function _relative_path($path)
    {
        $path = str_replace('\\', '/', (string) $path);
        $root = str_replace('\\', '/', FCPATH);
        if (strpos($path, $root) === 0) {
            $path = substr($path, strlen($root));
        }
        return substr($path, 0, 255);
    }

    private function _fail($message, $file_id = NULL)
    {
        return [
            'ok' => FALSE, 'status' => 'invalid', 'file_id' => $file_id,
            'new_taps' => 0, 'dup_taps' => 0, 'total_lines' => 0, 'invalid_count' => 0,
            'dirty_days' => 0, 'unresolved_fingers' => [], 'message' => $message,
        ];
    }
}

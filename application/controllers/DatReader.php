<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * DatReader — modul absensi berbasis LOG MENTAH.
 *
 * Arsitektur (sejak rebuild raw-log, Agu 2026):
 *   attendance_tap       tap mentah dari mesin, immutable, tidak pernah ditimpa
 *   attendance_tap_void  pembatalan tap (tanpa mengubah baris tap)
 *   attendance_day       hasil klasifikasi per (user, tanggal); kolom m_* =
 *                        cerminan mesin, kolom efektif = yang menurunkan presence
 *
 * Alur: ingest (cloud/upload) -> klasifikasi window shift -> admin lihat/koreksi
 * -> turunkan ke `presence`. Koreksi manusia hidup di lapis mentah (tambah tap)
 * atau di lapis harian (geser slot), sehingga sync berikutnya tidak menimpanya.
 *
 * Sebelumnya modul ini memakai dat_reader_mirror/dat_reader_work dengan
 * klasifikasi POSISIONAL. Sekarang klasifikasi utama = window shift, sama
 * dengan jalur import resmi; posisional tinggal jadi fallback berlabel untuk
 * hari yang jadwalnya belum diupload.
 *
 * Auth: ion_auth session + role (admin/admin-branch/hr). CSRF dikecualikan
 * (lihat application/config/config.php), jadi cek role + cabang di controller
 * ini adalah satu-satunya penjaga — jangan dilewat di endpoint baru.
 */
class DatReader extends CI_Controller {

    private $role;
    private $userdata;

    public function __construct() {
        parent::__construct();

        // index() adalah halaman HTML biasa; sisanya endpoint JSON untuk SPA.
        // Jangan balas JSON 401 ke browser yang minta halaman — redirect saja.
        $is_page = $this->router->fetch_method() === 'index';

        if (!$this->ion_auth->logged_in()) {
            if ($is_page) { redirect(''); return; }
            $this->_die(['error' => 'Unauthorized'], 401);
        }
        $this->role     = $this->ion_auth->get_users_groups()->row()->name;
        $this->userdata = $this->ion_auth->user()->row();
        if (!in_array($this->role, ['admin', 'admin-branch', 'hr'])) {
            if ($is_page) { redirect('dashboard'); return; }
            $this->_die(['error' => 'Forbidden'], 403);
        }
        $this->load->library('attlog_parser');
        $this->load->library('attendance_ingest');
        $this->load->library('attendance_classifier');
        $this->load->library('cloud_attlog_client');
        $this->load->model('payroll_model', 'payroll');
        $this->load->model('Sync_model', 'sync_machines');
        $this->load->model('Attendance_day_model', 'days');
    }

    private function _die($data, $code) {
        $this->output->set_status_header($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data); exit;
    }
    private function _json($data, $code = 200) {
        $this->output->set_status_header($code)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
    private function _body() {
        $j = json_decode(file_get_contents('php://input'), true);
        return is_array($j) ? $j : [];
    }
    private function _allowed_branch($branch_id) {
        $branch_id = (int)$branch_id;
        if ($this->role === 'admin') return $branch_id ?: null;
        $own = (int)$this->userdata->branch_id;
        return ($branch_id === 0 || $branch_id === $own) ? $own : false;
    }
    private function _locked($branch_id, $month, $year) {
        return $this->payroll->get_detail([
            'branch_id' => (int)$branch_id, 'month' => (int)$month, 'year' => (int)$year,
        ])->num_rows() > 0;
    }

    // ───────────────────────── GET absensi_mentah (halaman) ────────────────────
    /** Halaman di dalam aplikasi utama; SPA-nya dimuat oleh view. */
    public function index() {
        $this->template->load('layout/admin', 'hr/attendance_raw', []);
    }

    // ───────────────────────── GET dat_reader/branches ─────────────────────────
    public function branches() {
        if ($this->role === 'admin') {
            $rows = $this->db->query("SELECT id, branch_name FROM branch WHERE is_active=1 ORDER BY branch_name")->result_array();
        } else {
            $rows = $this->db->query("SELECT id, branch_name FROM branch WHERE id = ? AND is_active=1",
                [(int)$this->userdata->branch_id])->result_array();
        }
        $this->_json($rows);
    }

    // ───────────────────────── GET dat_reader/period ───────────────────────────
    public function period() {
        $branch_id = $this->_allowed_branch($this->input->get('branch_id'));
        $month = (int)date('m'); $year = (int)date('Y');
        $range = attlog_presence_period_range($month, $year);
        $this->_json([
            'month' => $month, 'year' => $year,
            'from' => $range['from'], 'to' => $range['to'], 'today' => date('Y-m-d'),
            'locked' => ($branch_id && $branch_id !== false) ? $this->_locked($branch_id, $month, $year) : false,
        ]);
    }

    // ───────────────────── GET dat_reader/attendance_machines ───────────────────
    // Dropdown sumber upload — HANYA mesin type=attendance yang boleh dipilih,
    // supaya admin tidak bisa keliru mengunggah dump mesin sholat (lihat
    // investigasi tap sholat kecampur jam kerja, 17 Agu 2026).
    public function attendance_machines() {
        $out = [];
        foreach ($this->sync_machines->get_active_by_type('attendance') as $row) {
            $sn = attlog_sanitize_machine_sn($row['machine_sn']);
            if ($sn === '') continue;
            $out[] = ['sn' => $sn, 'name' => $row['name']];
        }
        $this->_json($out);
    }

    // ─────────────── POST dat_reader/sync_upload (multipart: file) ──────────────
    // Ingest satu file .dat yang diupload admin, lalu klasifikasi rentangnya.
    public function sync_upload() {
        if ($this->input->method() !== 'post') return $this->_json(['error' => 'POST required'], 405);
        $branch_id = $this->_allowed_branch($this->input->post('branch_id'));
        if ($branch_id === false || $branch_id === null) return $this->_json(['error' => 'Cabang tidak valid'], 422);

        list($from, $to, $mode) = $this->_resolve_range(
            $this->input->post('mode'), $this->input->post('from'), $this->input->post('to'));
        if (!$from) return $this->_json(['error' => 'Rentang tanggal tidak valid (maks 92 hari)'], 422);

        // Wajib pilih SN mesin ABSENSI aktif dari dropdown — cegah dump mesin
        // sholat masuk sebagai jam kerja tanpa disadari.
        $sn = attlog_sanitize_machine_sn($this->input->post('machine_sn'));
        $known = array_column($this->_active_attendance_machines(), 'sn');
        if ($sn === '' || !in_array($sn, $known, true)) {
            return $this->_json(['error' => 'Pilih mesin absensi sumber file dulu (harus mesin absensi aktif).'], 422);
        }

        if (empty($_FILES['file']['name']))  return $this->_json(['error' => 'File .dat belum dipilih'], 422);
        if (!empty($_FILES['file']['error'])) return $this->_json(['error' => 'Upload gagal (kode '.$_FILES['file']['error'].')'], 422);
        $raw = file_get_contents($_FILES['file']['tmp_name']);
        if ($raw === false || trim($raw) === '') return $this->_json(['error' => 'File .dat kosong / tidak terbaca'], 422);

        $res = $this->attendance_ingest->ingest_raw($raw, $sn, [
            'origin' => 'upload', 'created_by' => (int)$this->userdata->id,
        ]);
        if (!$res['ok']) return $this->_json(['error' => $res['message']], 422);

        $classify = $this->_classify_branch($branch_id, $from, $to);
        $this->_json($this->_rows_response($branch_id, $from, $to,
            ['mode' => $mode, 'ingest' => [$sn => $res['message']], 'classify' => $classify, 'source' => 'upload']));
    }

    // ─────────────── POST dat_reader/sync_cloud (json) ──────────────────────────
    // Unduh semua mesin attendance aktif, ingest, lalu klasifikasi.
    public function sync_cloud() {
        if ($this->input->method() !== 'post') return $this->_json(['error' => 'POST required'], 405);
        $b = $this->_body();
        $branch_id = $this->_allowed_branch(isset($b['branch_id']) ? $b['branch_id'] : 0);
        if ($branch_id === false || $branch_id === null) return $this->_json(['error' => 'Cabang tidak valid'], 422);

        list($from, $to, $mode) = $this->_resolve_range($b['mode'] ?? null, $b['from'] ?? null, $b['to'] ?? null);
        if (!$from) return $this->_json(['error' => 'Rentang tanggal tidak valid (maks 92 hari)'], 422);

        $machines = $this->_active_attendance_machines();
        $machine_ids = [];
        foreach ($this->sync_machines->get_active_by_type('attendance') as $row) {
            $machine_ids[attlog_sanitize_machine_sn($row['machine_sn'])] = (int)$row['id'];
        }
        if (empty($machines)) return $this->_json(['error' => 'Tidak ada mesin Solution Cloud aktif (tipe attendance).'], 422);

        $download = $this->cloud_attlog_client->download_batch($machines);
        if ($download === false) return $this->_json(['error' => 'Gagal download dari semua mesin. Cek koneksi/kredensial cloud.'], 502);

        // Ingest SEMUA mesin yang berhasil diunduh, termasuk dump basi: tap lama
        // tetap sah dan penulisan idempoten, jadi tidak ada alasan membuangnya.
        $ingest = [];
        $new_taps = 0;
        foreach ($download['machines'] as $m) {
            $res = $this->attendance_ingest->ingest_raw($m['raw'], $m['sn'], [
                'machine_id' => isset($machine_ids[$m['sn']]) ? $machine_ids[$m['sn']] : null,
                'origin' => 'cloud', 'created_by' => (int)$this->userdata->id,
            ]);
            $ingest[$m['sn']] = $res['message'];
            $new_taps += $res['new_taps'];
        }

        $classify = $this->_classify_branch($branch_id, $from, $to);
        $this->_json($this->_rows_response($branch_id, $from, $to, [
            'mode' => $mode, 'ingest' => $ingest, 'new_taps' => $new_taps,
            'classify' => $classify, 'source' => 'cloud', 'failed' => $download['failed'],
        ]));
    }

    // ─────────────── GET dat_reader/data?branch_id=&mode=&from=&to= ─────────────
    public function data() {
        $branch_id = $this->_allowed_branch($this->input->get('branch_id'));
        if ($branch_id === false || $branch_id === null) return $this->_json(['error' => 'Cabang tidak valid'], 422);
        list($from, $to, $mode) = $this->_resolve_range(
            $this->input->get('mode'), $this->input->get('from'), $this->input->get('to'));
        if (!$from) return $this->_json(['error' => 'Rentang tanggal tidak valid (maks 92 hari)'], 422);
        $this->_json($this->_rows_response($branch_id, $from, $to, ['mode' => $mode]));
    }

    // ─────────────── POST dat_reader/reclassify (json) ──────────────────────────
    // Hitung ulang slot dari tap. Dipakai setelah jadwal shift telat diupload,
    // atau setelah tap ditambah/dibatalkan.
    public function reclassify() {
        if ($this->input->method() !== 'post') return $this->_json(['error' => 'POST required'], 405);
        $b = $this->_body();
        $branch_id = $this->_allowed_branch(isset($b['branch_id']) ? $b['branch_id'] : 0);
        if ($branch_id === false || $branch_id === null) return $this->_json(['error' => 'Cabang tidak valid'], 422);
        list($from, $to, $mode) = $this->_resolve_range($b['mode'] ?? null, $b['from'] ?? null, $b['to'] ?? null);
        if (!$from) return $this->_json(['error' => 'Rentang tanggal tidak valid'], 422);

        $only_dirty = !empty($b['only_dirty']);
        $classify = $this->_classify_branch($branch_id, $from, $to, $only_dirty);
        $this->_json($this->_rows_response($branch_id, $from, $to, ['mode' => $mode, 'classify' => $classify]));
    }

    // ─────────────── GET dat_reader/taps?user_id=&date= ─────────────────────────
    // Rincian tap satu karyawan-tanggal, termasuk yang sudah dibatalkan.
    public function taps() {
        $user_id = (int)$this->input->get('user_id');
        $date    = (string)$this->input->get('date');
        if (!$user_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->_json(['error' => 'user_id / date tidak valid'], 422);
        }
        if (!$this->_user_allowed($user_id)) {
            return $this->_json(['error' => 'Mitra kerja di luar cabang Anda'], 403);
        }

        // NULL = semua tipe mesin: rincian UI perlu menampilkan tap sholat juga,
        // supaya jelas tap mana yang tidak dipakai menghitung jam kerja.
        $grouped = $this->attendance_ingest->taps_for_range([$user_id], $date, $date, true, null);
        $rows = isset($grouped[$user_id.'|'.$date]) ? $grouped[$user_id.'|'.$date] : [];

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'        => (int)$r['id'],
                'time'      => substr($r['tap_at'], 11, 8),
                'source'    => $r['source'],
                'machine'   => $r['machine_sn'],
                'machine_type' => $r['machine_type'],
                'note'      => $r['note'],
                'voided'    => !empty($r['voided']),
                'void_reason' => $r['void_reason'],
            ];
        }
        $this->_json(['status' => true, 'user_id' => $user_id, 'date' => $date, 'taps' => $out]);
    }

    // ─────────────── POST dat_reader/tap_add (json) ─────────────────────────────
    // Tambah tap atas nama manusia (mis. lupa finger scan).
    public function tap_add() {
        if ($this->input->method() !== 'post') return $this->_json(['error' => 'POST required'], 405);
        $b = $this->_body();
        $user_id = (int)($b['user_id'] ?? 0);
        $date    = (string)($b['date'] ?? '');
        $time    = (string)($b['time'] ?? '');
        if (!$user_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time)) {
            return $this->_json(['error' => 'Mitra kerja / tanggal / jam tidak valid'], 422);
        }
        if (!$this->_user_allowed($user_id)) {
            return $this->_json(['error' => 'Mitra kerja di luar cabang Anda'], 403);
        }
        if ($this->_locked_for_user_date($user_id, $date)) {
            return $this->_json(['error' => 'Periode penggajian tanggal '.$date.' sudah dikunci.'], 422);
        }

        $res = $this->attendance_ingest->add_manual_tap(
            $user_id, $date.' '.$time, (int)$this->userdata->id, (string)($b['note'] ?? ''));
        if (!$res['ok']) return $this->_json(['error' => $res['message']], 422);

        $this->attendance_classifier->classify_range([$user_id], $date, $date, 'auto');
        $this->_json(['status' => true, 'message' => $res['message'], 'tap_id' => $res['tap_id']]);
    }

    // ─────────────── POST dat_reader/tap_void | tap_unvoid (json) ───────────────
    public function tap_void()   { $this->_tap_void_toggle(true); }
    public function tap_unvoid() { $this->_tap_void_toggle(false); }

    private function _tap_void_toggle($void) {
        if ($this->input->method() !== 'post') return $this->_json(['error' => 'POST required'], 405);
        $b = $this->_body();
        $tap_id = (int)($b['tap_id'] ?? 0);
        if (!$tap_id) return $this->_json(['error' => 'tap_id tidak valid'], 422);

        $tap = $this->db->select('user_id, tap_date')->where('id', $tap_id)
            ->get('attendance_tap')->row_array();
        if (empty($tap) || empty($tap['user_id'])) return $this->_json(['error' => 'Tap tidak ditemukan'], 404);
        if (!$this->_user_allowed((int)$tap['user_id'])) {
            return $this->_json(['error' => 'Mitra kerja di luar cabang Anda'], 403);
        }
        if ($this->_locked_for_user_date((int)$tap['user_id'], $tap['tap_date'])) {
            return $this->_json(['error' => 'Periode penggajian tanggal '.$tap['tap_date'].' sudah dikunci.'], 422);
        }

        $res = $void
            ? $this->attendance_ingest->void_tap($tap_id, (int)$this->userdata->id, (string)($b['reason'] ?? ''))
            : $this->attendance_ingest->unvoid_tap($tap_id);
        if (!$res['ok']) return $this->_json(['error' => $res['message']], 422);

        $this->attendance_classifier->classify_range(
            [(int)$tap['user_id']], $tap['tap_date'], $tap['tap_date'], 'auto');
        $this->_json(['status' => true, 'message' => $res['message']]);
    }

    // ─────────────── POST dat_reader/save (json: branch_id, from, to, edits[]) ──
    public function save() {
        if ($this->input->method() !== 'post') return $this->_json(['error' => 'POST required'], 405);
        $b = $this->_body();
        $branch_id = $this->_allowed_branch(isset($b['branch_id']) ? $b['branch_id'] : 0);
        if ($branch_id === false || $branch_id === null) return $this->_json(['error' => 'Cabang tidak valid'], 422);
        list($from, $to, $mode) = $this->_resolve_range($b['mode'] ?? null, $b['from'] ?? null, $b['to'] ?? null);
        if (!$from) return $this->_json(['error' => 'Rentang tanggal tidak valid'], 422);

        $edits = isset($b['edits']) && is_array($b['edits']) ? $b['edits'] : [];
        if (empty($edits)) return $this->_json(['error' => 'Tidak ada perubahan untuk disimpan'], 422);

        // Hanya karyawan cabang terpilih, dan hanya tanggal yang belum terkunci.
        $uids = array_values(array_unique(array_map(function($e){ return (int)$e['user_id']; }, $edits)));
        $allowed = $this->_branch_user_ids($branch_id, $uids);
        $locked = 0;
        $edits = array_values(array_filter($edits, function($e) use ($allowed, &$locked){
            if (!isset($allowed[(int)$e['user_id']])) return false;
            if ($this->_locked_for_user_date((int)$e['user_id'], $e['flow_date'])) { $locked++; return false; }
            return true;
        }));
        if (empty($edits)) {
            return $this->_json(['error' => $locked > 0
                ? 'Semua baris yang diedit ada di periode penggajian yang sudah dikunci.'
                : 'Mitra Kerja yang diedit tidak ada di cabang ini'], 422);
        }

        $saved = $this->days->save_edits($edits, (int)$this->userdata->id, date('Y-m-d H:i:s'));
        $this->_json($this->_rows_response($branch_id, $from, $to,
            ['mode' => $mode, 'saved' => $saved, 'skipped_locked' => $locked]));
    }

    // ─────────────── POST dat_reader/derive (json) ──────────────────────────────
    /**
     * Turunkan lapis harian ke `presence` (insert + update).
     *
     * force=true ("Regenerate periode") menulis atas nama operator sehingga
     * baris presence lama yang ter-stempel manual ikut diperbarui — satu-satunya
     * cara menembus proteksi trigger, dan sengaja dibuat sebagai tindakan
     * eksplisit. Lock penggajian tetap tidak bisa ditembus.
     * dry_run=true hanya menghitung, tidak menulis apa pun.
     */
    public function derive() {
        if ($this->input->method() !== 'post') return $this->_json(['error' => 'POST required'], 405);
        $b = $this->_body();
        $branch_id = $this->_allowed_branch(isset($b['branch_id']) ? $b['branch_id'] : 0);
        if ($branch_id === false || $branch_id === null) return $this->_json(['error' => 'Cabang tidak valid'], 422);
        list($from, $to, $mode) = $this->_resolve_range($b['mode'] ?? null, $b['from'] ?? null, $b['to'] ?? null);
        if (!$from) return $this->_json(['error' => 'Rentang tanggal tidak valid'], 422);

        $this->load->model('Presence_deriver_model', 'deriver');
        $res = $this->deriver->derive($branch_id, $from, $to, [
            'force'    => !empty($b['force']),
            'dry_run'  => !empty($b['dry_run']),
            'actor_id' => (int)$this->userdata->id,
        ]);

        if (!empty($b['dry_run'])) {
            return $this->_json(['status' => true, 'from' => $from, 'to' => $to, 'derive' => $res]);
        }
        $this->_json($this->_rows_response($branch_id, $from, $to, ['mode' => $mode, 'derive' => $res]));
    }

    // ════════════════════════ INTERNAL ════════════════════════

    /** Resolusi rentang dari mode. period=periode payroll berjalan; date=satu hari; range=from..to. */
    private function _resolve_range($mode, $from, $to) {
        $mode = in_array($mode, ['period', 'range', 'date'], true) ? $mode : 'period';
        if ($mode === 'period') {
            $r = attlog_presence_period_range((int)date('m'), (int)date('Y'));
            return [$r['from'], $r['to'], 'period'];
        }
        $ok = function($d){ return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) ? $d : null; };
        $f = $ok($from); $t = ($mode === 'date') ? $f : $ok($to);
        if (!$f || !$t) return [null, null, $mode];
        if ($t < $f) { $tmp = $f; $f = $t; $t = $tmp; }
        if ((strtotime($t) - strtotime($f)) / 86400 > 92) return [null, null, $mode];
        return [$f, $t, $mode];
    }

    /** Mesin absensi aktif yang kredensialnya lengkap. */
    private function _active_attendance_machines() {
        $machines = [];
        foreach ($this->sync_machines->get_active_by_type('attendance') as $row) {
            $sn = attlog_sanitize_machine_sn($row['machine_sn']);
            if ($sn === '' || $row['password'] === '') continue;
            $machines[] = ['sn' => $sn, 'pass' => $row['password']];
        }
        return $machines;
    }

    /** Klasifikasi ulang semua karyawan cabang pada rentang. */
    private function _classify_branch($branch_id, $from, $to, $only_dirty = false) {
        $uids = $this->days->user_ids_in_range($branch_id, $from, $to);
        if (empty($uids)) return ['days' => 0, 'window' => 0, 'positional' => 0, 'empty' => 0, 'skipped_edited' => 0];
        return $this->attendance_classifier->classify_range($uids, $from, $to, 'auto', $only_dirty);
    }

    private function _rows_response($branch_id, $from, $to, $extra = []) {
        $rows = $this->days->get_range($branch_id, $from, $to);
        $uids = array_values(array_unique(array_map(function($r){ return (int)$r['user_id']; }, $rows)));
        $existing = $this->days->presence_existing_pairs($uids, $from, $to);

        $out = [];
        foreach ($rows as $r) {
            $key = $r['user_id'].'|'.$r['flow_date'];
            $pres = isset($existing[$key]) ? $existing[$key] : null;
            $out[] = [
                'key' => $key,
                'user_id' => (int)$r['user_id'],
                'employee_code' => $r['employee_code'],
                'employee_name' => trim($r['first_name'].' '.$r['last_name']),
                'date' => $r['flow_date'], 'weekday' => get_dayname($r['flow_date']),
                'entry_time' => $r['entry_time'] ?: '', 'rest_in' => $r['rest_in'] ?: '',
                'rest_out' => $r['rest_out'] ?: '', 'out_time' => $r['out_time'] ?: '',
                'machine' => [
                    'entry_time' => $r['m_entry_time'] ?: '', 'rest_in' => $r['m_rest_in'] ?: '',
                    'rest_out' => $r['m_rest_out'] ?: '', 'out_time' => $r['m_out_time'] ?: '',
                ],
                'entry_late' => (int)$r['entry_late'], 'rest_late' => (int)$r['rest_late'],
                'tap_count' => (int)$r['tap_count'], 'all_taps' => $r['all_taps'],
                'classify_method' => $r['classify_method'], 'shift_code' => $r['shift_code'],
                'needs_reclass' => (int)$r['needs_reclass'],
                'is_edited' => (int)$r['is_edited'], 'edit_note' => $r['edit_note'],
                'derived_at' => $r['derived_at'], 'derive_status' => $r['derive_status'],
                'in_presence' => $pres !== null,
                'presence_input_by' => $pres ? $pres['input_by'] : null,
                'presence_type' => $pres ? $pres['presence_type'] : null,
            ];
        }
        return array_merge([
            'status' => true, 'from' => $from, 'to' => $to,
            'rows' => $out, 'recap' => $this->_recap($out),
        ], $extra);
    }

    private function _recap($rows) {
        $emp = []; $tap = 0; $lengkap = 0; $tidak = 0; $edited = 0; $inpres = 0;
        $positional = 0; $dirty = 0;
        foreach ($rows as $r) {
            $emp[$r['user_id']] = true;
            $tap += $r['tap_count'];
            if ($r['entry_time'] !== '' && $r['out_time'] !== '') $lengkap++; else $tidak++;
            if ($r['is_edited']) $edited++;
            if ($r['in_presence']) $inpres++;
            if ($r['classify_method'] === 'positional') $positional++;
            if ($r['needs_reclass']) $dirty++;
        }
        return [
            'karyawan' => count($emp), 'hari_absen' => count($rows), 'total_tap' => $tap,
            'hadir_lengkap' => $lengkap, 'tidak_lengkap' => $tidak,
            'diedit' => $edited, 'sudah_di_presence' => $inpres,
            'tanpa_jadwal' => $positional, 'perlu_klasifikasi_ulang' => $dirty,
        ];
    }

    private function _branch_user_ids($branch_id, $uids) {
        if (empty($uids)) return [];
        $rows = $this->db->select('u.id')->from('users u')->join('position p', 'p.id = u.position_id', 'left')
            ->where('p.branch_id', (int)$branch_id)->where_in('u.id', $uids)->get()->result_array();
        $out = [];
        foreach ($rows as $r) { $out[(int)$r['id']] = true; }
        return $out;
    }

    /** TRUE kalau user ada di cabang yang boleh diakses role ini. */
    private function _user_allowed($user_id) {
        if ($this->role === 'admin') return true;
        $row = $this->db->select('p.branch_id')->from('users u')
            ->join('position p', 'p.id = u.position_id', 'left')
            ->where('u.id', (int)$user_id)->get()->row_array();
        return $row && (int)$row['branch_id'] === (int)$this->userdata->branch_id;
    }

    /** Lock penggajian memakai cabang MILIK user, bukan cabang yang diminta. */
    private function _locked_for_user_date($user_id, $date) {
        $row = $this->db->select('p.branch_id')->from('users u')
            ->join('position p', 'p.id = u.position_id', 'left')
            ->where('u.id', (int)$user_id)->get()->row_array();
        if (empty($row['branch_id'])) return false;

        $pp = $this->_period_of_date($date);
        return $this->_locked((int)$row['branch_id'], $pp['month'], $pp['year']);
    }

    /** Periode payroll yang memuat $date (identitas = bulan akhir periode). */
    private function _period_of_date($date) {
        $d = (int)substr($date, 8, 2); $m = (int)substr($date, 5, 2); $y = (int)substr($date, 0, 4);
        if ($d >= START_PAYROLL_DATE) { $m++; if ($m > 12) { $m = 1; $y++; } }
        return ['month' => $m, 'year' => $y];
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wa extends CI_Controller {

    function __construct() {
        parent::__construct();
        $this->load->model('wa_model', 'wa');
        $this->load->model('Attendance_daily_report_model', 'daily_report');
        $this->load->model('branch_model', 'branch');
        $this->load->model('sync_model', 'sync');
        $this->load->library('hermes_wa');
        $this->load->library('cloud_attlog_client');
        $this->load->library('attendance_ingest');
        $this->load->library('attendance_daily_report');
        $this->load->library('lacak_attendance_client');

        $method = $this->router->fetch_method();
        $is_report_cli = in_array($method, ['test_report_cli', 'preview_report_cli'], true) && is_cli();
        $is_cron = $method === 'cron' || $is_report_cli;

        if (in_array($method, ['test_report_cli', 'preview_report_cli'], true) && !is_cli()) {
            show_error('CLI only', 403);
            return;
        }

        if (!$is_cron && !$this->ion_auth->logged_in()) {
            redirect('');
        }

        $this->role     = $is_cron ? null : $this->ion_auth->get_users_groups()->row()->name;
        $this->userdata = $is_cron ? null : $this->ion_auth->user()->row();

        // Hanya admin yang bisa akses fitur WA Agent
        if (!$is_cron && !in_array($this->role, ['admin', 'admin-branch'])) {
            redirect('dashboard');
        }
    }

    // =========================================================================
    // DASHBOARD WA AGENT
    // =========================================================================

    public function index() {
        $data['logs']    = $this->wa->get_logs(30);
        $data['config']  = $this->wa->get_config();
        $data['summary'] = $this->wa->get_today_shift_report();

        $this->template->load('layout/admin', 'wa/index', $data);
    }

    // =========================================================================
    // CONFIG
    // =========================================================================

    public function config() {
        $data['config'] = $this->wa->get_config();
        $this->template->load('layout/admin', 'wa/config', $data);
    }

    public function save_config() {
        if ($this->input->method() !== 'post') {
            redirect('wa/config');
        }

        $data = [
            'user_code'                => $this->input->post('user_code', true),
            'secret'                   => $this->input->post('secret', true),
            'device_id'                => $this->input->post('device_id', true),
            'is_active'                => $this->input->post('is_active') ? 1 : 0,
            'send_morning_enabled'     => $this->input->post('send_morning_enabled') ? 1 : 0,
            'morning_time'             => $this->input->post('morning_time', true),
            'send_afternoon_enabled'   => $this->input->post('send_afternoon_enabled') ? 1 : 0,
            'afternoon_time'           => $this->input->post('afternoon_time', true),
            'notif_absent_enabled'     => $this->input->post('notif_absent_enabled') ? 1 : 0,
            'absent_notif_time'        => $this->input->post('absent_notif_time', true),
            'target_phones'            => $this->input->post('target_phones', true),
            'updated_at'               => date('Y-m-d H:i:s'),
        ];

        // Validasi waktu format HH:MM
        $time_fields = ['morning_time', 'afternoon_time', 'absent_notif_time'];
        foreach ($time_fields as $field) {
            if (!preg_match('/^\d{2}:\d{2}$/', $data[$field])) {
                $this->session->set_flashdata('error', 'Format waktu tidak valid. Gunakan format HH:MM.');
                redirect('wa/config');
            }
        }

        // Validasi nomor target (hanya digit, koma, spasi)
        if (!empty($data['target_phones'])) {
            if (!preg_match('/^[0-9,\s+\-]+$/', $data['target_phones'])) {
                $this->session->set_flashdata('error', 'Nomor target tidak valid. Gunakan format 628xxx, pisahkan dengan koma.');
                redirect('wa/config');
            }
        }

        $this->wa->save_config($data);
        $this->session->set_flashdata('success', 'Konfigurasi WA berhasil disimpan.');
        redirect('wa/config');
    }

    // =========================================================================
    // TEST KIRIM
    // =========================================================================

    public function test_send() {
        if ($this->input->method() !== 'post') {
            redirect('wa/config');
        }

        $phone   = $this->input->post('test_phone', true);
        $message = $this->input->post('test_message', true);
        $is_ajax = $this->input->is_ajax_request();

        if (empty($phone) || empty($message)) {
            if ($is_ajax) {
                $this->output->set_content_type('application/json')
                             ->set_output(json_encode(['success' => false, 'message' => 'Nomor HP dan pesan tidak boleh kosong.']));
                return;
            }
            $this->session->set_flashdata('error', 'Nomor HP dan pesan tidak boleh kosong.');
            redirect('wa/config');
        }

        $config = $this->wa->get_config();
        if (empty($config) || empty($config['secret'])) {
            if ($is_ajax) {
                $this->output->set_content_type('application/json')
                             ->set_output(json_encode(['success' => false, 'message' => 'Config WA belum diatur.']));
                return;
            }
            $this->session->set_flashdata('error', 'Config WA belum diatur. Simpan config terlebih dahulu.');
            redirect('wa/config');
        }

        $wa = new Hermes_wa(['api_key' => $config['secret']]);

        $result = $wa->send($phone, $message);

        $this->wa->insert_log([
            'type'      => 'manual',
            'phone'     => $wa->normalize_phone($phone),
            'message'   => $message,
            'status'    => $result['success'] ? 'success' : 'failed',
            'http_code' => $result['http_code'],
            'response'  => $result['response'],
            'created_at'=> date('Y-m-d H:i:s'),
        ]);

        if ($is_ajax) {
            $this->output->set_content_type('application/json')
                         ->set_output(json_encode([
                             'success' => $result['success'],
                             'message' => $result['success']
                                 ? 'Terkirim ke ' . $wa->normalize_phone($phone)
                                 : 'Gagal: ' . $result['response'],
                         ]));
            return;
        }

        if ($result['success']) {
            $this->session->set_flashdata('success', 'Pesan berhasil terkirim ke ' . $wa->normalize_phone($phone));
        } else {
            $this->session->set_flashdata('error', 'Gagal kirim: ' . $result['response']);
        }

        redirect('wa/config');
    }

    // =========================================================================
    // KIRIM MANUAL (dari halaman dashboard)
    // =========================================================================

    public function send_rekap_pagi() {
        $this->_send_rekap('rekap_pagi', true);
        redirect('wa');
    }

    public function send_rekap_siang() {
        $this->_send_rekap('rekap_siang', true);
        redirect('wa');
    }

    public function send_notif_absen() {
        $this->_send_absent_notif();
        redirect('wa');
    }

    public function check_absen_today() {
        if (!$this->input->is_ajax_request() || $this->input->method() !== 'post') {
            show_error('Bad Request', 400);
            return;
        }

        $result = $this->_sync_today_attendance();
        $summary = $this->wa->get_today_shift_report();

        $this->output->set_content_type('application/json')
                     ->set_output(json_encode([
                         'success' => $result['success'],
                         'message' => $result['message'],
                         'summary' => $summary,
                     ]));
    }

    /** Uji operasional dari shell VPS; tidak dapat dipanggil melalui web. */
    public function test_report_cli($phone = '', $date = '') {
        if (!is_cli()) {
            show_error('CLI only', 403);
            return;
        }
        $phone = preg_replace('/[^0-9]/', '', (string)$phone);
        if (!preg_match('/^62[0-9]{8,13}$/', $phone)) {
            fwrite(STDERR, "Nomor tujuan tidak valid. Gunakan format 62xxxxxxxxxx.\n");
            return;
        }
        $parsed_date = $date !== '' ? DateTime::createFromFormat('!Y-m-d', $date) : null;
        if ($date !== '' && (!$parsed_date || $parsed_date->format('Y-m-d') !== $date)) {
            fwrite(STDERR, "Tanggal tidak valid. Gunakan format YYYY-MM-DD.\n");
            return;
        }
        $result = $this->_send_rekap('test_rekap_pagi', true, [$phone], $date ?: date('Y-m-d'));
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
    }

    /** Pratinjau laporan dari shell VPS tanpa sinkronisasi dan tanpa kirim WA. */
    public function preview_report_cli($date = '', $type = 'pagi') {
        if (!is_cli()) {
            show_error('CLI only', 403);
            return;
        }
        $date = $date ?: date('Y-m-d');
        $parsed_date = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$parsed_date || $parsed_date->format('Y-m-d') !== $date || !in_array($type, ['pagi', 'siang'], true)) {
            fwrite(STDERR, "Parameter tidak valid. Gunakan: YYYY-MM-DD [pagi|siang].\n");
            return;
        }

        $lacak_attendance = [];
        if ($type === 'pagi') {
            $lacak = $this->lacak_attendance_client->fetch($date);
            if (empty($lacak['success'])) {
                fwrite(STDERR, $lacak['message'].PHP_EOL);
                return;
            }
            $lacak_attendance = $lacak['attendance'];
        }
        $report = $this->daily_report->build($date, $type, $lacak_attendance);
        $config = $this->wa->get_config();
        $report_time = $type === 'pagi' ? $config['morning_time'] : $config['afternoon_time'];
        $pdf = $this->attendance_daily_report->render_pdf($report, $type, $report_time);
        $message = $this->attendance_daily_report->build_message($report, $type, '', $report_time);
        echo json_encode([
            'date' => $report['date'],
            'totals' => $report['totals'],
            'branches' => array_map(function ($branch) {
                return array_diff_key($branch, ['details' => true]);
            }, $report['branches']),
            'lacak_codes' => array_keys($lacak_attendance),
            'pdf' => $pdf['path'],
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
    }

    // =========================================================================
    // CRON ENDPOINT (akses via URL tanpa login, dilindungi token)
    // =========================================================================

    public function cron($token = '') {
        // Cek token dari config
        $config     = $this->wa->get_config();
        $cron_token = isset($config['cron_token']) ? (string)$config['cron_token'] : '';

        $valid_token = function_exists('hash_equals') ? hash_equals($cron_token, (string)$token) : ((string)$token === $cron_token);
        if ($cron_token === '' || !$valid_token) {
            show_error('Forbidden', 403);
            return;
        }

        if (empty($config) || !$config['is_active']) {
            echo json_encode(['status' => 'inactive']);
            return;
        }

        $lock = $this->_cron_lock();
        if ($lock === false) {
            echo json_encode(['status' => 'busy', 'time' => date('H:i')]);
            return;
        }

        $now = date('H:i');
        $results = [];

        // Rekap pagi
        if ($config['send_morning_enabled'] && $this->_is_due($config['morning_time'])) {
            $results['rekap_pagi'] = $this->_send_rekap('rekap_pagi');
        }

        // Rekap siang
        if ($config['send_afternoon_enabled'] && $this->_is_due($config['afternoon_time'])) {
            $results['rekap_siang'] = $this->_send_rekap('rekap_siang');
        }

        // Notif tidak hadir
        if ($config['notif_absent_enabled'] && $this->_is_due($config['absent_notif_time'])) {
            if (!$this->wa->was_sent_today('notif_absen')) {
                $this->_send_absent_notif();
                $results['notif_absen'] = ['attempted' => true];
            }
        }

        flock($lock, LOCK_UN);
        fclose($lock);
        echo json_encode(['status' => 'ok', 'results' => $results, 'time' => $now]);
    }

    // =========================================================================
    // LOGS
    // =========================================================================

    public function logs() {
        $page   = (int)($this->input->get('page') ?: 1);
        $limit  = 50;
        $offset = ($page - 1) * $limit;

        $data['logs']      = $this->wa->get_logs($limit, $offset);
        $data['total']     = $this->wa->count_logs();
        $data['page']      = $page;
        $data['per_page']  = $limit;

        $this->template->load('layout/admin', 'wa/logs', $data);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function _get_wa_instance() {
        $config = $this->wa->get_config();
        if (empty($config) || empty($config['secret'])) {
            return null;
        }
        return new Hermes_wa(['api_key' => $config['secret']]);
    }

    private function _send_rekap($type, $force = false, $phone_override = null, $report_date = null) {
        $wa     = $this->_get_wa_instance();
        $config = $this->wa->get_config();

        if (!$wa || !$config) {
            $this->session->set_flashdata('error', 'Config WA belum diatur.');
            return ['success' => false, 'message' => 'Config WA belum diatur.'];
        }

        $phones = $phone_override !== null ? $phone_override : $this->_parse_phones($config['target_phones']);
        if (empty($phones)) {
            $this->session->set_flashdata('rekap_error', 'Belum ada nomor tujuan rekap di konfigurasi.');
            return ['success' => false, 'message' => 'Belum ada nomor tujuan rekap.'];
        }

        if (!$force && $this->_report_components_complete($wa, $phones, $type)) {
            return ['success' => true, 'sent' => 0, 'attempted' => 0, 'message' => 'Laporan sudah lengkap terkirim hari ini.'];
        }

        $report_type = strpos($type, 'pagi') !== false ? 'pagi' : 'siang';
        $report_date = $report_date ?: date('Y-m-d');
        $sync_result = $report_date === date('Y-m-d')
            ? $this->_sync_today_attendance()
            : ['success' => true, 'partial' => false, 'message' => 'Data historis memakai hasil sinkronisasi tersimpan.'];
        if (empty($sync_result['success'])) {
            $warning = "⚠️ *WARNING SINKRONISASI ABSEN*\n".$sync_result['message']
                ."\nLaporan belum dikirim untuk mencegah penggunaan data lama.";
            $sent = $this->_send_text_component($wa, $phones, $type.'_sync_warning', $warning, $force);
            return ['success' => false, 'sent' => $sent, 'message' => $sync_result['message']];
        }

        $lacak_attendance = [];
        if ($report_type === 'pagi') {
            $lacak_result = $this->lacak_attendance_client->fetch($report_date);
            if (empty($lacak_result['success'])) {
                $warning = "⚠️ *WARNING SINKRONISASI ABSEN LACAK*\n".$lacak_result['message']
                    ."\nLaporan belum dikirim untuk mencegah data hadir dinilai alfa.";
                $sent = $this->_send_text_component($wa, $phones, $type.'_sync_warning', $warning, $force);
                return ['success' => false, 'sent' => $sent, 'message' => $lacak_result['message']];
            }
            $lacak_attendance = $lacak_result['attendance'];
        }

        $report = $this->daily_report->build($report_date, $report_type, $lacak_attendance);
        $report_time = $report_type === 'pagi' ? $config['morning_time'] : $config['afternoon_time'];
        if ((int)$report['totals']['scheduled'] === 0) {
            $warning = $this->attendance_daily_report->build_shift_warning_message($report, $report_type, $report_time);
            $sent = $this->_send_text_component($wa, $phones, $type.'_shift_warning', $warning, $force);
            return ['success' => false, 'sent' => $sent, 'message' => 'Belum ada shift valid hari ini.'];
        }

        $sync_warning = !empty($sync_result['partial']) ? $sync_result['message'] : '';
        $message = $this->attendance_daily_report->build_message($report, $report_type, $sync_warning, $report_time);
        $is_correction = strpos($type, 'test_') === 0;
        if ($is_correction) {
            $message = "🔄 *KOREKSI LAPORAN*\n".$message;
        }
        try {
            $pdf = $this->attendance_daily_report->render_pdf($report, $report_type, $report_time);
        } catch (Throwable $error) {
            log_message('error', 'Gagal membuat PDF rekap absensi: '.$error->getMessage());
            return ['success' => false, 'message' => 'Gagal membuat PDF laporan.'];
        }

        $success_count = 0;
        $attempted = 0;
        foreach ($phones as $phone) {
            $normalized = $wa->normalize_phone($phone);
            $text_type = $type.'_text';
            if ($force || !$this->wa->was_sent_today($text_type, $normalized)) {
                $attempted++;
                $result = $wa->send($phone, $message);
                $this->_log_delivery($text_type, $normalized, $message, $result);
                if ($result['success']) $success_count++;
            }

            $pdf_type = $type.'_pdf';
            if ($force || !$this->wa->was_sent_today($pdf_type, $normalized)) {
                $attempted++;
                $caption = ($is_correction ? 'KOREKSI - ' : '').'Rekap Absensi '.strtoupper($report_type);
                $result = $wa->send_document($phone, $pdf['path'], $caption, $pdf['filename']);
                $this->_log_delivery($pdf_type, $normalized, $pdf['filename'], $result);
                if ($result['success']) $success_count++;
            }
        }

        $label = $report_type === 'pagi' ? 'Rekap Pagi' : 'Rekap Siang';
        $message_result = $attempted === 0
            ? "{$label} sudah lengkap terkirim hari ini."
            : "{$label}: {$success_count} dari {$attempted} komponen berhasil dikirim.";
        $this->session->set_flashdata($success_count === $attempted ? 'rekap_success' : 'rekap_error', $message_result);
        return ['success' => $attempted === 0 || $success_count === $attempted, 'sent' => $success_count, 'attempted' => $attempted, 'message' => $message_result];
    }

    private function _send_text_component($wa, $phones, $type, $message, $force) {
        $success = 0;
        foreach ($phones as $phone) {
            $normalized = $wa->normalize_phone($phone);
            if (!$force && $this->wa->was_sent_today($type, $normalized)) continue;
            $result = $wa->send($phone, $message);
            $this->_log_delivery($type, $normalized, $message, $result);
            if ($result['success']) $success++;
        }
        return $success;
    }

    private function _report_components_complete($wa, $phones, $type) {
        foreach ($phones as $phone) {
            $normalized = $wa->normalize_phone($phone);
            if (!$this->wa->was_sent_today($type.'_pdf', $normalized)
                || !$this->wa->was_sent_today($type.'_text', $normalized)) {
                return false;
            }
        }
        return true;
    }

    private function _log_delivery($type, $phone, $message, $result) {
        $this->wa->insert_log([
            'type' => $type,
            'phone' => $phone,
            'message' => $message,
            'status' => $result['success'] ? 'success' : 'failed',
            'http_code' => $result['http_code'],
            'response' => $result['response'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function _is_due($scheduled_time, $window_minutes = 15) {
        if (!preg_match('/^\d{2}:\d{2}$/', (string)$scheduled_time)) return false;
        $scheduled = strtotime(date('Y-m-d').' '.$scheduled_time.':00');
        $difference = time() - $scheduled;
        return $difference >= 0 && $difference < ($window_minutes * 60);
    }

    private function _cron_lock() {
        $path = APPPATH.'cache'.DIRECTORY_SEPARATOR.'wa_daily_report.lock';
        $handle = fopen($path, 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) fclose($handle);
            return false;
        }
        return $handle;
    }

    private function _send_absent_notif() {
        $wa     = $this->_get_wa_instance();
        $config = $this->wa->get_config();

        if (!$wa || !$config) {
            $this->session->set_flashdata('error', 'Config WA belum diatur.');
            return;
        }

        $summary = $this->wa->get_today_shift_report();
        $absent_count = 0;
        foreach ($summary as $row) {
            $absent_count += isset($row['absent_employees']) ? count($row['absent_employees']) : 0;
        }

        if ($absent_count === 0) {
            $this->session->set_flashdata('rekap_info', 'Semua mitra kerja sudah hadir hari ini.');
            return;
        }

        $message = $wa->build_shift_absent_message($summary);
        $phones  = $this->_parse_phones($config['target_phones']);

        if (empty($phones)) {
            $this->session->set_flashdata('rekap_error', 'Belum ada nomor tujuan di konfigurasi.');
            return;
        }

        $success_count = 0;
        foreach ($phones as $phone) {
            $result = $wa->send($phone, $message);
            $this->wa->insert_log([
                'type'       => 'notif_absen',
                'phone'      => $wa->normalize_phone($phone),
                'message'    => $message,
                'status'     => $result['success'] ? 'success' : 'failed',
                'http_code'  => $result['http_code'],
                'response'   => $result['response'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if ($result['success']) $success_count++;
        }

        $count = $absent_count;
        $this->session->set_flashdata('rekap_success', "Notifikasi tidak hadir dikirim ke {$success_count} nomor. ({$count} mitra kerja tidak hadir)");
    }

    private function _parse_phones($phones_string) {
        if (empty($phones_string)) return [];
        $phones = explode(',', $phones_string);
        return array_filter(array_map('trim', $phones));
    }

    /**
     * Refresh attendance_recap utk hari ini: download tap mesin absen ->
     * arsipkan raw -> klasifikasi attendance_day -> susun ulang
     * attendance_recap. Tidak lagi parse manual & tulis presence sendiri
     * (jalur lama, digantikan proses independen attendance_recap -- lihat
     * hr/Presence::_run_attendance_recap_core() utk versi periode penuh yg
     * dipanggil cron 30 menit; di sini cukup hari ini saja utk tombol "Cek
     * Absen"/rekap pagi).
     */
    private function _sync_today_attendance() {
        $today = date('Y-m-d');
        $machines = $this->sync->get_active_by_type('attendance');

        if (empty($machines)) {
            return [
                'success' => false,
                'message' => 'Tidak ada mesin absensi aktif untuk dicek.',
            ];
        }

        $downloaded = 0;
        $failed = [];

        foreach ($machines as $machine) {
            $machine_sn = attlog_sanitize_machine_sn($machine['machine_sn']);
            if ($machine_sn === '' || $machine['password'] === '') {
                $failed[] = $machine['name'].' (SN/password tidak valid)';
                continue;
            }

            $raw = $this->_download_cloud_attlog($machine_sn, $machine['password']);

            if ($raw === false) {
                $failed[] = $machine['name'].' ('.$machine_sn.')';
                $this->sync->update($machine['id'], [
                    'last_sync_at' => date('Y-m-d H:i:s'),
                    'last_sync_status' => 'failed',
                ]);
                $this->sync->insert_log([
                    'machine_id' => $machine['id'],
                    'machine_name' => $machine['name'],
                    'status' => 'failed',
                    'records' => 0,
                    'message' => 'Cek absen WA gagal download data hari ini.',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                continue;
            }

            $downloaded++;
            $ingest = $this->attendance_ingest->ingest_raw($raw, $machine_sn, [
                'machine_type' => 'attendance',
                'origin'       => 'wa_agent',
                'created_by'   => null,
            ]);

            $this->sync->update($machine['id'], [
                'last_sync_at' => date('Y-m-d H:i:s'),
                'last_sync_status' => 'success',
            ]);
            $this->sync->insert_log([
                'machine_id' => $machine['id'],
                'machine_name' => $machine['name'],
                'status' => 'success',
                'records' => isset($ingest['new_taps']) ? $ingest['new_taps'] : 0,
                'message' => 'Cek absen WA berhasil download data hari ini.',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($downloaded === 0) {
            $message = 'Semua mesin absensi gagal diakses.';
            if (!empty($failed)) { $message .= ' ('.implode(', ', $failed).')'; }
            return ['success' => false, 'message' => $message];
        }

        $this->load->library('attendance_classifier');
        $classify = $this->attendance_classifier->classify_range([], $today, $today, 'auto', true);

        $this->load->model('Attendance_recap_model', 'attendance_recap');
        $res = $this->attendance_recap->refresh($today, $today);

        $message = 'Cek absen selesai. Mesin sukses: '.$downloaded.', hari diklasifikasi: '.$classify['days'].', baris recap: '.$res['days'].'.';
        if (!empty($failed)) { $message .= ' Mesin gagal: '.implode(', ', $failed).'.'; }

        return [
            'success' => true,
            'partial' => !empty($failed),
            'message' => $message,
        ];
    }

    private function _download_cloud_attlog($sn, $password) {
        return $this->cloud_attlog_client->download_single($sn, $password);
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wa extends CI_Controller {

    function __construct() {
        parent::__construct();
        $this->load->model('wa_model', 'wa');
        $this->load->model('branch_model', 'branch');
        $this->load->model('sync_model', 'sync');
        $this->load->library('hermes_wa');
        $this->load->library('cloud_attlog_client');
        $this->load->library('attendance_ingest');

        $is_cron = $this->router->fetch_method() === 'cron';

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
        $this->_send_rekap('rekap_pagi');
        redirect('wa');
    }

    public function send_rekap_siang() {
        $this->_send_rekap('rekap_siang');
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

        $now  = date('H:i');
        $sent = [];

        // Rekap pagi
        if ($config['send_morning_enabled'] && $config['morning_time'] === $now) {
            if (!$this->wa->was_sent_today('rekap_pagi')) {
                $this->_send_rekap('rekap_pagi');
                $sent[] = 'rekap_pagi';
            }
        }

        // Rekap siang
        if ($config['send_afternoon_enabled'] && $config['afternoon_time'] === $now) {
            if (!$this->wa->was_sent_today('rekap_siang')) {
                $this->_send_rekap('rekap_siang');
                $sent[] = 'rekap_siang';
            }
        }

        // Notif tidak hadir
        if ($config['notif_absent_enabled'] && $config['absent_notif_time'] === $now) {
            if (!$this->wa->was_sent_today('notif_absen')) {
                $this->_send_absent_notif();
                $sent[] = 'notif_absen';
            }
        }

        echo json_encode(['status' => 'ok', 'sent' => $sent, 'time' => $now]);
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

    private function _send_rekap($type) {
        $wa     = $this->_get_wa_instance();
        $config = $this->wa->get_config();

        if (!$wa || !$config) {
            $this->session->set_flashdata('error', 'Config WA belum diatur.');
            return;
        }

        $phones = $this->_parse_phones($config['target_phones']);
        if (empty($phones)) {
            $this->session->set_flashdata('rekap_error', 'Belum ada nomor tujuan rekap di konfigurasi.');
            return;
        }

        $info_messages = [];
        if ($type === 'rekap_pagi') {
            $sync_result = $this->_sync_today_attendance();
            if (empty($sync_result['success'])) {
                $info_messages[] = 'Cek absen sebelum Rekap Pagi belum sukses: '.$sync_result['message'];
            }
        }

        $summary  = $this->wa->get_today_shift_report();
        $tipe     = ($type === 'rekap_pagi') ? 'pagi' : 'siang';
        $message  = $wa->build_shift_rekap_message($summary, $tipe);

        $success_count = 0;
        foreach ($phones as $phone) {
            $result = $wa->send($phone, $message);
            $this->wa->insert_log([
                'type'       => $type,
                'phone'      => $wa->normalize_phone($phone),
                'message'    => $message,
                'status'     => $result['success'] ? 'success' : 'failed',
                'http_code'  => $result['http_code'],
                'response'   => $result['response'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if ($result['success']) $success_count++;
        }

        $label = $type === 'rekap_pagi' ? 'Rekap Pagi' : 'Rekap Siang';
        $total_count = count($phones);

        if ($success_count === 0) {
            $this->session->set_flashdata('rekap_error', "{$label} gagal dikirim ke semua nomor. Cek log WA untuk detail.");
        } else {
            $this->session->set_flashdata('rekap_success', "{$label} berhasil dikirim ke {$success_count} dari {$total_count} nomor.");

            if ($success_count < $total_count) {
                $info_messages[] = "{$label} gagal dikirim ke ".($total_count - $success_count)." nomor. Cek log WA untuk detail.";
            }
        }

        if (!empty($info_messages)) {
            $this->session->set_flashdata('rekap_info', implode(' ', $info_messages));
        }
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

        return ['success' => true, 'message' => $message];
    }

    private function _download_cloud_attlog($sn, $password) {
        return $this->cloud_attlog_client->download_single($sn, $password);
    }
}

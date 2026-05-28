<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Wa extends CI_Controller {

    function __construct() {
        parent::__construct();
        $this->load->model('wa_model', 'wa');
        $this->load->model('branch_model', 'branch');
        $this->load->model('sync_model', 'sync');
        $this->load->library('kirimi_wa');
        $this->load->library('attendance_employee_resolver');
        $this->load->library('cloud_attlog_client');

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
        if (empty($config) || empty($config['user_code'])) {
            if ($is_ajax) {
                $this->output->set_content_type('application/json')
                             ->set_output(json_encode(['success' => false, 'message' => 'Config WA belum diatur.']));
                return;
            }
            $this->session->set_flashdata('error', 'Config WA belum diatur. Simpan config terlebih dahulu.');
            redirect('wa/config');
        }

        $wa = new Kirimi_wa([
            'user_code' => $config['user_code'],
            'secret'    => $config['secret'],
            'device_id' => $config['device_id'],
        ]);

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
        if (empty($config) || empty($config['user_code'])) {
            return null;
        }
        return new Kirimi_wa([
            'user_code' => $config['user_code'],
            'secret'    => $config['secret'],
            'device_id' => $config['device_id'],
        ]);
    }

    private function _send_rekap($type) {
        $wa     = $this->_get_wa_instance();
        $config = $this->wa->get_config();

        if (!$wa || !$config) {
            $this->session->set_flashdata('error', 'Config WA belum diatur.');
            return;
        }

        $summary  = $this->wa->get_today_shift_report();
        $tipe     = ($type === 'rekap_pagi') ? 'pagi' : 'siang';
        $message  = $wa->build_shift_rekap_message($summary, $tipe);

        $phones = $this->_parse_phones($config['target_phones']);
        if (empty($phones)) {
            $this->session->set_flashdata('rekap_error', 'Belum ada nomor tujuan rekap di konfigurasi.');
            return;
        }

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
        $this->session->set_flashdata('rekap_success', "{$label} berhasil dikirim ke {$success_count} nomor.");
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
            $this->session->set_flashdata('rekap_info', 'Semua karyawan sudah hadir hari ini.');
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
        $this->session->set_flashdata('rekap_success', "Notifikasi tidak hadir dikirim ke {$success_count} nomor. ({$count} karyawan tidak hadir)");
    }

    private function _parse_phones($phones_string) {
        if (empty($phones_string)) return [];
        $phones = explode(',', $phones_string);
        return array_filter(array_map('trim', $phones));
    }

    private function _sync_today_attendance() {
        $today = date('Y-m-d');
        $machines = $this->sync->get_active_by_type('attendance');

        if (empty($machines)) {
            return [
                'success' => false,
                'message' => 'Tidak ada mesin absensi aktif untuk dicek.',
            ];
        }

        $logs_by_employee = [];
        $downloaded = 0;
        $failed = [];
        $raw_rows = 0;

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
            $lines = preg_split('/\r\n|\r|\n/', $raw);
            $machine_rows = 0;

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') { continue; }

                $cols = preg_split('/\s+/', $line);
                if (count($cols) < 3) { continue; }

                $finger_id = trim($cols[0]);
                $timestamp = strtotime($cols[1].' '.$cols[2]);
                if ($finger_id === '' || !$timestamp || date('Y-m-d', $timestamp) !== $today) {
                    continue;
                }

                $time = date('H:i:s', $timestamp);
                $logs_by_employee[$finger_id][$time] = $time;
                $machine_rows++;
                $raw_rows++;
            }

            $this->sync->update($machine['id'], [
                'last_sync_at' => date('Y-m-d H:i:s'),
                'last_sync_status' => 'success',
            ]);
            $this->sync->insert_log([
                'machine_id' => $machine['id'],
                'machine_name' => $machine['name'],
                'status' => 'success',
                'records' => $machine_rows,
                'message' => 'Cek absen WA berhasil download data hari ini.',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (empty($logs_by_employee)) {
            $message = 'Tidak ada log fingerprint hari ini yang berhasil dibaca.';
            if (!empty($failed)) {
                $message .= ' Mesin gagal: '.implode(', ', $failed).'.';
            }
            return ['success' => false, 'message' => $message];
        }

        $employee_match = $this->_get_employee_map_by_finger(array_keys($logs_by_employee), $today);
        $employees = $employee_match['map'];
        $duplicate_stats = $employee_match['stats'];
        $saved = 0;
        $updated = 0;
        $skipped = 0;
        $missing = 0;
        $no_schedule = 0;
        $no_window = 0;

        foreach ($logs_by_employee as $finger_id => $times) {
            if (!isset($employees[$finger_id])) {
                $missing++;
                continue;
            }

            $employee = $employees[$finger_id];
            $shift = $this->_get_today_shift($employee['id'], $today);
            if (empty($shift)) {
                $no_schedule++;
                continue;
            }

            sort($times);
            $payload = $this->_presence_payload($employee['id'], $today);
            foreach ($times as $time) {
                $datetime = $today.' '.$time;

                if (attlog_time_between($time, $shift['start_time_in'], $shift['start_time_out']) && empty($payload['entry_time'])) {
                    $payload['entry_time'] = $datetime;
                    $payload['entry_time_late'] = $this->_minutes_between($shift['start_time_late'], $time);
                    continue;
                }

                if (attlog_time_between($time, $shift['end_time_in'], $shift['end_time_out']) && empty($payload['out_time'])) {
                    $payload['out_time'] = $datetime;
                    continue;
                }

                if (attlog_time_between($time, $shift['start_time_rest'], $shift['end_time_rest'])) {
                    if (empty($payload['rest_time_in'])) {
                        $payload['rest_time_in'] = $datetime;
                    } elseif (empty($payload['rest_time_out'])) {
                        $payload['rest_time_out'] = $datetime;
                        $limit = date('H:i:s', strtotime($payload['rest_time_in'].' +'.(int)$shift['rest_time_range'].' minutes'));
                        $payload['rest_time_late'] = $this->_minutes_between($limit, $time);
                    }
                }
            }

            if (empty($payload['entry_time']) && empty($payload['out_time']) && empty($payload['rest_time_in']) && empty($payload['rest_time_out'])) {
                $no_window++;
                continue;
            }

            $status = $this->_upsert_today_presence($payload);
            if ($status === 'inserted') {
                $saved++;
            } elseif ($status === 'updated') {
                $updated++;
            } else {
                $skipped++;
            }
        }

        $message = 'Cek absen selesai. Mesin sukses: '.$downloaded.', log hari ini: '.$raw_rows.', presensi baru: '.$saved.', diperbarui: '.$updated.', dilewati: '.$skipped.'.';
        if ($missing > 0) { $message .= ' Finger tidak cocok: '.$missing.'.'; }
        if ($no_schedule > 0) { $message .= ' Tanpa jadwal: '.$no_schedule.'.'; }
        if ($no_window > 0) { $message .= ' Di luar jam shift: '.$no_window.'.'; }
        $message .= $this->attendance_employee_resolver->message($duplicate_stats);
        if (!empty($failed)) { $message .= ' Mesin gagal: '.implode(', ', $failed).'.'; }

        return [
            'success' => ($saved + $updated + $skipped) > 0,
            'message' => $message,
        ];
    }

    private function _get_employee_map_by_finger($finger_ids, $date) {
        return $this->attendance_employee_resolver->map_for_date($finger_ids, $date);
    }

    private function _get_today_shift($user_id, $date) {
        return $this->db->select('users_shift_additional.*, shift.*')
                        ->join('shift', 'shift.id = users_shift_additional.shift_id')
                        ->where([
                            'users_shift_additional.user_id' => $user_id,
                            'users_shift_additional.additional_date' => $date,
                            'users_shift_additional.additional_type' => 'work',
                        ])
                        ->where(latest_schedule_subquery(), null, false)
                        ->get('users_shift_additional')
                        ->row_array();
    }

    private function _presence_payload($user_id, $date) {
        return [
            'user_id' => $user_id,
            'entry_time' => null,
            'entry_time_late' => 0,
            'out_time' => null,
            'rest_time_in' => null,
            'rest_time_out' => null,
            'rest_time_late' => 0,
            'flow_date' => $date,
            'created_at' => date('Y-m-d H:i:s'),
            'input_by' => 'system',
            'presence_status' => 'approved',
            'presence_type' => 'normal',
            'is_overtime' => '0',
        ];
    }

    private function _upsert_today_presence($row) {
        $existing = $this->db->where([
            'user_id' => $row['user_id'],
            'flow_date' => $row['flow_date'],
        ])->get('presence')->row_array();

        if (empty($existing)) {
            $this->db->insert('presence', $row);
            return $this->db->affected_rows() > 0 ? 'inserted' : 'skipped';
        }

        $update = ['updated_at' => date('Y-m-d H:i:s')];
        foreach (['entry_time', 'out_time', 'rest_time_in', 'rest_time_out'] as $field) {
            if (empty($existing[$field]) && !empty($row[$field])) {
                $update[$field] = $row[$field];
            }
        }

        foreach (['entry_time_late', 'rest_time_late'] as $field) {
            if ((empty($existing[$field]) || (int)$existing[$field] === 0) && !empty($row[$field])) {
                $update[$field] = $row[$field];
            }
        }

        if (count($update) === 1) {
            return 'skipped';
        }

        $this->db->where('id', $existing['id'])->update('presence', $update);
        return 'updated';
    }

    private function _minutes_between($from, $to) {
        if ($from === null || $from === '' || $to === null || $to === '') {
            return 0;
        }

        $from = date('H:i', strtotime($from));
        $to = date('H:i', strtotime($to));
        $from_minutes = ((int) substr($from, 0, 2) * 60) + (int) substr($from, 3, 2);
        $to_minutes = ((int) substr($to, 0, 2) * 60) + (int) substr($to, 3, 2);

        return max(0, $to_minutes - $from_minutes);
    }

    private function _download_cloud_attlog($sn, $password) {
        return $this->cloud_attlog_client->download_single($sn, $password);
    }
}

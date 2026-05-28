<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Sync extends CI_Controller {

    function __construct() {
        parent::__construct();
        $this->load->model('sync_model', 'sync');
        $this->load->model('branch_model', 'branch');
        $this->load->library('cloud_attlog_client');

        if (!$this->ion_auth->logged_in()) {
            redirect('');
        }

        $this->role     = $this->ion_auth->get_users_groups()->row()->name;
        $this->userdata = $this->ion_auth->user()->row();

        if (!in_array($this->role, ['admin', 'admin-branch'])) {
            redirect('dashboard');
        }
    }

    // =========================================================================
    // INDEX — daftar mesin
    // =========================================================================

    public function index() {
        $branch_id = ($this->role === 'admin') ? null : $this->_get_branch_id();

        $data['machines'] = $this->sync->get_all($branch_id);
        $data['branches'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
        $data['logs']     = $this->sync->get_logs(null, 20);

        $this->template->load('layout/admin', 'sync/index', $data);
    }

    // =========================================================================
    // CHECK FINGERS — query employee yang cocok dengan finger_id di .dat mesin
    // =========================================================================

    public function check_fingers() {
        if (!$this->input->is_ajax_request() || $this->input->method() !== 'post') {
            show_error('Bad Request', 400);
        }

        $sn = $this->_sanitize_machine_sn($this->input->post('machine_sn', true));
        if (empty($sn)) {
            echo json_encode(['success' => false, 'message' => 'Machine SN diperlukan dan hanya boleh berisi huruf, angka, underscore, atau strip.']);
            return;
        }

        $machine = $this->db->where('machine_sn', $sn)->get('sync_machine')->row_array();
        if (!$machine || !$this->_can_access_machine($machine)) {
            echo json_encode(['success' => false, 'message' => 'Mesin tidak ditemukan atau tidak sesuai akses user.']);
            return;
        }

        // Cari file .dat terbaru dari mesin ini
        $uploadDir = FCPATH.'uploads'.DIRECTORY_SEPARATOR.'attendance';
        $files = glob($uploadDir.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'attlog_'.$sn.'_*.dat');
        if (empty($files)) {
            echo json_encode(['success' => false, 'message' => 'Tidak ada file .dat untuk mesin ini']);
            return;
        }

        // Ambil file terbaru
        usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
        $latestFile = $files[0];

        // Parse finger IDs dari .dat
        $raw = file_get_contents($latestFile);
        $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw)));
        $fingerIds = [];
        foreach ($lines as $line) {
            $cols = preg_split('/\s+/', $line);
            if (count($cols) >= 1) {
                $fingerIds[] = trim($cols[0]);
            }
        }
        $fingerIds = array_unique(array_filter($fingerIds));
        sort($fingerIds);

        // Query employee yang cocok
        $query = $this->db->select('u.id, u.employee_code, u.first_name, u.last_name, p.position_name, b.branch_name, u.active')
                          ->from('users u')
                          ->join('position p', 'p.id = u.position_id', 'left')
                          ->join('branch b', 'b.id = p.branch_id', 'left')
                          ->where_in('u.employee_code', $fingerIds)
                          ->order_by('b.branch_name', 'ASC')
                          ->order_by('u.employee_code', 'ASC');
        $result = $query->get()->result_array();

        $matched = [];
        $missing = array_diff($fingerIds, array_column($result, 'employee_code'));

        foreach ($result as $row) {
            $matched[] = [
                'code'     => $row['employee_code'],
                'name'     => trim($row['first_name'].' '.$row['last_name']),
                'position' => $row['position_name'] ?: '(no position)',
                'branch'   => $row['branch_name'] ?: '(no branch)',
                'active'   => (bool)$row['active'],
                'id'       => $row['id'],
            ];
        }

        echo json_encode([
            'success' => true,
            'machine_sn' => $sn,
            'file' => basename($latestFile),
            'finger_ids' => $fingerIds,
            'total_fingers' => count($fingerIds),
            'matched' => $matched,
            'matched_count' => count($matched),
            'missing' => array_values($missing),
            'missing_count' => count($missing),
        ], JSON_PRETTY_PRINT);
    }

    // =========================================================================
    // TAMBAH MESIN
    // =========================================================================

    public function add() {
        if ($this->input->method() !== 'post') {
            redirect('sync');
        }

        $sync_times = $this->input->post('sync_times', true);

        $branch_id = $this->role === 'admin' ? ($this->input->post('branch_id') ?: null) : $this->_get_branch_id();
        $branch_id = $this->_active_branch_id($branch_id);

        $data = [
            'name'              => $this->input->post('name', true),
            'machine_sn'        => $this->_sanitize_machine_sn($this->input->post('machine_sn', true)),
            'machine_type'      => $this->input->post('machine_type', true) === 'pray' ? 'pray' : 'attendance',
            'password'          => $this->input->post('password', true),
            'branch_id'         => $branch_id,
            'is_active'         => $this->input->post('is_active') ? 1 : 0,
            'auto_sync_enabled' => $this->input->post('auto_sync_enabled') ? 1 : 0,
            'sync_times'        => $sync_times,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ];

        if (empty($data['name']) || empty($data['machine_sn'])) {
            $this->session->set_flashdata('error', 'Nama mesin dan ID Mesin (SN) wajib diisi. SN hanya boleh berisi huruf, angka, underscore, atau strip.');
            redirect('sync');
        }

        $this->sync->insert($data);
        $this->session->set_flashdata('success', 'Mesin absensi berhasil ditambahkan.');
        redirect('sync');
    }

    // =========================================================================
    // UPDATE MESIN (AJAX)
    // =========================================================================

    public function update() {
        if (!$this->input->is_ajax_request() || $this->input->method() !== 'post') {
            show_error('Bad Request', 400);
        }

        $id = (int) $this->input->post('id');
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
            return;
        }

        $machine = $this->sync->get_by_id($id);
        if (!$machine || !$this->_can_access_machine($machine)) {
            echo json_encode(['success' => false, 'message' => 'Mesin tidak ditemukan.']);
            return;
        }

        $branch_id = $this->role === 'admin' ? ($this->input->post('branch_id') ?: null) : $this->_get_branch_id();
        $branch_id = $this->_active_branch_id($branch_id);

        $data = [
            'name'              => $this->input->post('name', true),
            'machine_sn'        => $this->_sanitize_machine_sn($this->input->post('machine_sn', true)),
            'machine_type'      => $this->input->post('machine_type', true) === 'pray' ? 'pray' : 'attendance',
            'password'          => $this->input->post('password', true),
            'branch_id'         => $branch_id,
            'is_active'         => $this->input->post('is_active') ? 1 : 0,
            'auto_sync_enabled' => $this->input->post('auto_sync_enabled') ? 1 : 0,
            'sync_times'        => $this->input->post('sync_times', true),
            'updated_at'        => date('Y-m-d H:i:s'),
        ];

        if (empty($data['name']) || empty($data['machine_sn'])) {
            echo json_encode(['success' => false, 'message' => 'Nama mesin dan ID Mesin (SN) wajib diisi. SN hanya boleh berisi huruf, angka, underscore, atau strip.']);
            return;
        }

        $this->sync->update($id, $data);
        $this->output->set_content_type('application/json')
                     ->set_output(json_encode(['success' => true, 'message' => 'Mesin berhasil diperbarui.']));
    }

    // =========================================================================
    // HAPUS MESIN
    // =========================================================================

    public function delete($id) {
        if ($this->role !== 'admin') {
            redirect('sync');
        }

        $machine = $this->sync->get_by_id((int) $id);
        if (!$machine) {
            $this->session->set_flashdata('error', 'Mesin tidak ditemukan.');
            redirect('sync');
        }

        $this->sync->delete((int) $id);
        $this->session->set_flashdata('success', 'Mesin berhasil dihapus.');
        redirect('sync');
    }

    // =========================================================================
    // GET MESIN (AJAX — untuk form edit)
    // =========================================================================

    public function get($id) {
        if (!$this->input->is_ajax_request()) {
            show_error('Bad Request', 400);
        }

        $machine = $this->sync->get_by_id((int) $id);
        if (!$machine || !$this->_can_access_machine($machine)) {
            echo json_encode(['success' => false]);
            return;
        }

        $this->output->set_content_type('application/json')
                     ->set_output(json_encode(['success' => true, 'data' => $machine]));
    }

    // =========================================================================
    // DO SYNC — tarik data .dat dari Solution Cloud (AJAX)
    // =========================================================================

    public function do_sync() {
        if (!$this->input->is_ajax_request() || $this->input->method() !== 'post') {
            show_error('Bad Request', 400);
        }

        $id    = (int) $this->input->post('machine_id');
        $month = str_pad((int) $this->input->post('month') ?: date('n'), 2, '0', STR_PAD_LEFT);
        $year  = (int) $this->input->post('year') ?: (int) date('Y');

        $machine = $this->sync->get_by_id($id);
        $machine_sn = $machine ? $this->_sanitize_machine_sn($machine['machine_sn']) : '';
        if (!$machine || !$this->_can_access_machine($machine) || empty($machine_sn)) {
            echo json_encode(['success' => false, 'message' => 'Mesin tidak ditemukan atau SN belum diisi.']);
            return;
        }

        $raw = $this->_cloud_download($machine_sn, $machine['password']);
        if ($raw === false) {
            $this->sync->update($id, ['last_sync_at' => date('Y-m-d H:i:s'), 'last_sync_status' => 'failed']);
            $this->sync->insert_log([
                'machine_id'   => $id,
                'machine_name' => $machine['name'],
                'status'       => 'failed',
                'records'      => 0,
                'message'      => 'Gagal login ke Solution Cloud. Cek SN dan password.',
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            echo json_encode(['success' => false, 'message' => 'Gagal login ke Solution Cloud. Cek SN / password mesin.']);
            return;
        }

        // Simpan file .dat
        $dir   = FCPATH.'uploads'.DIRECTORY_SEPARATOR.'attendance'.DIRECTORY_SEPARATOR.$year.DIRECTORY_SEPARATOR.$month;
        if (!is_dir($dir)) { mkdir($dir, 0777, true); }
        $stamp = date('Ymd_His');
        $path  = $dir.DIRECTORY_SEPARATOR.'attlog_'.$machine_sn.'_'.$stamp.'.dat';
        file_put_contents($path, $raw);

        // Hitung baris .dat
        $lines  = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw)));
        $records = count($lines);

        $status = 'success';
        $msg    = 'File DAT berhasil didownload ('.$records.' baris). File disimpan: '.basename($path).'.';

        $this->sync->update($id, [
            'last_sync_at'     => date('Y-m-d H:i:s'),
            'last_sync_status' => $status,
        ]);
        $this->sync->insert_log([
            'machine_id'   => $id,
            'machine_name' => $machine['name'],
            'status'       => $status,
            'records'      => $records,
            'message'      => $msg,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->output->set_content_type('application/json')
                     ->set_output(json_encode([
                         'success' => true,
                         'records' => $records,
                         'file'    => basename($path),
                         'message' => $msg,
                     ]));
    }

    // -------------------------------------------------------------------------
    // cURL helper — login ke Solution Cloud lalu download .dat
    // -------------------------------------------------------------------------

    private function _cloud_download($sn, $password) {
        return $this->cloud_attlog_client->download_single($sn, $password);
    }

    // =========================================================================
    // HELPER
    // =========================================================================

    private function _get_branch_id() {
        // Ambil branch_id berdasarkan user — sesuaikan dengan struktur tabelmu
        $user_id = $this->userdata->id;
        $row = $this->db->select('position.branch_id')
                        ->from('users')
                        ->join('position', 'position.id = users.position_id', 'left')
                        ->where('users.id', $user_id)
                        ->get()->row_array();
        return $row ? $row['branch_id'] : null;
    }

    private function _active_branch_id($branch_id) {
        if (empty($branch_id)) {
            return null;
        }

        $branch = $this->branch->get_active_detail('id', (int) $branch_id)->row_array();
        return !empty($branch) ? (int) $branch['id'] : null;
    }

    private function _sanitize_machine_sn($sn) {
        $sn = trim((string) $sn);
        return preg_match('/^[A-Za-z0-9_-]+$/', $sn) ? $sn : '';
    }

    private function _can_access_machine($machine) {
        if ($this->role === 'admin') {
            return true;
        }

        return isset($machine['branch_id']) && (int)$machine['branch_id'] === (int)$this->_get_branch_id();
    }
}

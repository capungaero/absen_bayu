<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Bpjs — modul pembayaran BPJS (Kesehatan & Ketenagakerjaan).
 * Dua halaman: Konfigurasi (global) & List Pembayaran (per cabang/bulan).
 * Penyimpanan di tabel bpjs_config & bpjs_payment saja (tidak menulis ke
 * payroll_insentif/deduction — integrasi gaji menyusul/manual).
 */
class Bpjs extends CI_Controller {

    function __construct() {
        parent::__construct();
        if (!$this->ion_auth->logged_in()) {
            redirect('');
        }
        $this->role     = $this->ion_auth->get_users_groups()->row()->name;
        $this->userdata = $this->ion_auth->user()->row();

        if (!in_array($this->role, ['admin', 'admin-branch'])) {
            redirect('dashboard');
        }

        $this->load->model('bpjs_model', 'bpjs');
        $this->load->model('branch_model', 'branch');
    }

    private function _json($res) {
        $this->output->set_content_type('application/json')
                     ->set_output(json_encode($res));
    }

    // ---- KONFIGURASI (admin saja) -----------------------------------

    public function index() {
        $this->config();
    }

    public function config() {
        if ($this->role !== 'admin') {
            redirect('bpjs/list');
        }
        $data['config'] = $this->bpjs->get_config();
        $this->template->load('layout/admin', 'bpjs/config', $data);
    }

    public function save_config() {
        if (!$this->input->is_ajax_request() || $this->role !== 'admin') {
            return $this->_json(['status' => false, 'message' => 'Akses ditolak']);
        }

        $data = [
            'kesehatan_total'             => format_angka($this->input->post('kesehatan_total')),
            'kesehatan_pct_employee'      => (float)$this->input->post('kesehatan_pct_employee'),
            'kesehatan_employee'          => format_angka($this->input->post('kesehatan_employee')),
            'kesehatan_company'           => format_angka($this->input->post('kesehatan_company')),
            'ketenagakerjaan_total'       => format_angka($this->input->post('ketenagakerjaan_total')),
            'ketenagakerjaan_pct_employee'=> (float)$this->input->post('ketenagakerjaan_pct_employee'),
            'ketenagakerjaan_employee'    => format_angka($this->input->post('ketenagakerjaan_employee')),
            'ketenagakerjaan_company'     => format_angka($this->input->post('ketenagakerjaan_company')),
            'mandiri_insentif'            => format_angka($this->input->post('mandiri_insentif')),
            'updated_at'                  => date('Y-m-d H:i:s'),
            'updated_by'                  => (int)$this->userdata->user_id,
        ];

        $this->bpjs->save_config($data);
        $this->_json(['status' => true, 'message' => 'Konfigurasi BPJS berhasil disimpan']);
    }

    // ---- LIST PEMBAYARAN --------------------------------------------

    /** Cabang yang dipakai untuk filter. admin = bebas pilih; admin-branch = cabangnya. */
    private function _resolve_branch() {
        if ($this->role === 'admin') {
            return (int)($this->input->get('branch_id') ?: $this->userdata->branch_id);
        }
        return (int)$this->userdata->branch_id;
    }

    public function list_payment() {
        $branch_id = $this->_resolve_branch();
        $month = (int)($this->input->get('month') ?: date('n'));
        $year  = (int)($this->input->get('year')  ?: date('Y'));

        $data['role']        = $this->role;
        $data['branch_id']   = $branch_id;
        $data['month']       = $month;
        $data['year']        = $year;
        $data['config']      = $this->bpjs->get_config();
        $data['payments']    = $this->bpjs->get_payments($branch_id, $month, $year);

        if ($this->role === 'admin') {
            $data['branch'] = $this->branch->get_data(['branch_name', 'ASC'])->result_array();
        }

        $this->template->load('layout/admin', 'bpjs/list', $data);
    }

    public function toggle_office() {
        if (!$this->input->is_ajax_request()) {
            return $this->_json(['status' => false, 'message' => 'Akses ditolak']);
        }
        $user_id   = (int)$this->input->post('user_id');
        $month     = (int)$this->input->post('month');
        $year      = (int)$this->input->post('year');
        $mode      = $this->input->post('mode') === 'kantor' ? 'kantor' : 'mandiri';
        $branch_id = $this->_resolve_branch();

        if (!$this->bpjs->user_in_branch($user_id, $branch_id)) {
            return $this->_json(['status' => false, 'message' => 'Karyawan tidak valid']);
        }

        $cfg = $this->bpjs->get_config();
        $this->bpjs->set_pay_mode($user_id, $month, $year, $mode, $cfg);
        $sync = $this->bpjs->sync_payroll($user_id, $month, $year);

        $base = $mode === 'kantor' ? 'Ditandai dibayar kantor' : 'Diubah ke bayar mandiri';
        $this->_json([
            'status'  => true,
            'locked'  => $sync['locked'],
            'message' => $base . ($sync['locked']
                ? '. CATATAN: penggajian periode ini sudah final — rollback dulu agar BPJS masuk ke gaji.'
                : ' & disinkron ke gaji.'),
        ]);
    }

    public function acc() {
        if (!$this->input->is_ajax_request()) {
            return $this->_json(['status' => false, 'message' => 'Akses ditolak']);
        }
        $user_id   = (int)$this->input->post('user_id');
        $month     = (int)$this->input->post('month');
        $year      = (int)$this->input->post('year');
        $branch_id = $this->_resolve_branch();

        if (!$this->bpjs->user_in_branch($user_id, $branch_id)) {
            return $this->_json(['status' => false, 'message' => 'Karyawan tidak valid']);
        }

        $cfg = $this->bpjs->get_config();
        $this->bpjs->acc_payment($user_id, $month, $year, $cfg, $this->userdata->user_id);
        $sync = $this->bpjs->sync_payroll($user_id, $month, $year);

        $this->_json([
            'status'  => true,
            'locked'  => $sync['locked'],
            'message' => 'Bukti pembayaran mandiri di-ACC' . ($sync['locked']
                ? '. CATATAN: penggajian periode ini sudah final — rollback dulu agar insentif masuk ke gaji.'
                : ' & insentif disinkron ke gaji.'),
        ]);
    }

    /** Sinkron seluruh karyawan cabang+periode ke payroll (pakai config terbaru). */
    public function sync_period() {
        if (!$this->input->is_ajax_request()) {
            return $this->_json(['status' => false, 'message' => 'Akses ditolak']);
        }
        $month     = (int)$this->input->post('month');
        $year      = (int)$this->input->post('year');
        $branch_id = $this->_resolve_branch();
        $cfg = $this->bpjs->get_config();
        $res = $this->bpjs->sync_payroll_period($branch_id, $month, $year, $cfg);

        if ($res['locked']) {
            return $this->_json(['status' => false,
                'message' => 'Penggajian periode ini sudah final. Rollback penggajian dulu agar BPJS bisa disinkron ke gaji.']);
        }
        $this->_json([
            'status'  => true,
            'message' => $res['count'].' dari '.$res['total'].' data BPJS disinkron ke gaji untuk periode ini.',
        ]);
    }
}

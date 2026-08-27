<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Review Kertas Kerja — admin/admin-branch (scope cabang) & supervisor
 * (scope via kertas_kerja_spv_assignment, penugasan manual per karyawan).
 * Triad sama hr/Overtime.php.
 */
class KertasKerja extends CI_Controller {

	function __construct() {
		parent::__construct();
		$this->load->model('kertas_kerja_model', 'kk');
		$this->load->model('branch_model', 'branch');

		if (!$this->ion_auth->logged_in()) {
			redirect('');
		}
		$this->role     = $this->ion_auth->get_users_groups()->row()->name;
		$this->userdata = $this->ion_auth->user()->row();

		if (!in_array($this->role, ['admin', 'admin-branch', 'supervisor'])) {
			redirect('dashboard');
		}
	}

	public function index() {
		$find = [];
		$spv_user_id = null;

		if ($this->role === 'admin') {
			$branch_id = $this->input->get('branch_id') ? (int)$this->input->get('branch_id') : 0;
			if ($branch_id) { $find['position.branch_id'] = $branch_id; }
			$data['branch'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
			$data['branch_id'] = $branch_id;
		} elseif ($this->role === 'admin-branch') {
			$find['position.branch_id'] = (int)$this->userdata->branch_id;
		} else {
			$spv_user_id = (int)$this->userdata->user_id;
		}

		$this->kk->get_dataTable($find, $spv_user_id);
		$data['role'] = $this->role;

		$this->template->load('layout/admin', 'hr/kertaskerja/list/index', $data);
	}

	/** Rekap kepatuhan PER KARYAWAN yang wajib isi -- siapa sudah/belum bikin di tanggal terpilih. */
	public function rekap() {
		$date = $this->input->get('date') ?: date('Y-m-d');
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = date('Y-m-d'); }

		$find = [];
		$spv_user_id = null;

		if ($this->role === 'admin') {
			$branch_id = $this->input->get('branch_id') ? (int)$this->input->get('branch_id') : 0;
			if ($branch_id) { $find['position.branch_id'] = $branch_id; }
			$data['branch'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
			$data['branch_id'] = $branch_id;
		} elseif ($this->role === 'admin-branch') {
			$find['position.branch_id'] = (int)$this->userdata->branch_id;
		} else {
			$spv_user_id = (int)$this->userdata->user_id;
		}

		$data['rows'] = $this->kk->get_rekap($date, $find, $spv_user_id);
		$data['date'] = $date;
		$data['role'] = $this->role;
		$data['total_sudah'] = count(array_filter($data['rows'], function ($r) { return !empty($r['kk_id']); }));
		$data['total_belum'] = count($data['rows']) - $data['total_sudah'];

		$this->template->load('layout/admin', 'hr/kertaskerja/list/rekap', $data);
	}

	public function detail($id) {
		$header = $this->db->where('id', $id)->get('kertas_kerja')->row_array();
		if (empty($header)) { show_404(); return; }

		if (!$this->_can_view($header['user_id'])) { show_404(); return; }

		$data['header']   = $header;
		$data['employee'] = $this->db->where('id', $header['user_id'])->get('users')->row_array();

		$this->template->load('layout/admin', 'hr/kertaskerja/list/detail', $data);
	}

	public function mark_read() {
		if (!$this->input->is_ajax_request()) {
			return $this->_json(['status' => false, 'message' => 'Akses ditolak']);
		}
		$id = (int)$this->input->post('id');
		$header = $this->db->where('id', $id)->get('kertas_kerja')->row_array();
		if (empty($header) || !$this->_can_view($header['user_id'])) {
			return $this->_json(['status' => false, 'message' => 'Data tidak ditemukan']);
		}

		$this->kk->mark_read($id, (int)$this->userdata->user_id);
		$this->_json(['status' => true, 'message' => 'Ditandai sudah dibaca']);
	}

	private function _json($res) {
		$this->output->set_content_type('application/json')
					 ->set_output(json_encode($res));
	}

	/** Cek scope: admin=semua, admin-branch=cabangnya, supervisor=assignment-nya. */
	private function _can_view($employee_user_id) {
		if ($this->role === 'admin') { return true; }

		if ($this->role === 'admin-branch') {
			return $this->db->where('users.id', $employee_user_id)
				->where('position.branch_id', (int)$this->userdata->branch_id)
				->join('position', 'position.id = users.position_id')
				->get('users')->num_rows() > 0;
		}

		// supervisor
		return $this->db->where([
			'spv_user_id'      => (int)$this->userdata->user_id,
			'employee_user_id' => $employee_user_id,
		])->get('kertas_kerja_spv_assignment')->num_rows() > 0;
	}
}

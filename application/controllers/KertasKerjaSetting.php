<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Setting Kertas Kerja — admin toggle per-karyawan "wajib isi kertas kerja"
 * + penugasan manual SPV -> karyawan yang diawasi. Pola sama Bpjs.php.
 */
class KertasKerjaSetting extends CI_Controller {

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

		$this->load->model('user_model', 'employee');
		$this->load->model('branch_model', 'branch');
	}

	private function _json($res) {
		$this->output->set_content_type('application/json')
					 ->set_output(json_encode($res));
	}

	/** Cabang filter. admin = bebas pilih; admin-branch = cabangnya sendiri. */
	private function _resolve_branch() {
		if ($this->role === 'admin') {
			return (int)($this->input->get('branch_id') ?: $this->userdata->branch_id);
		}
		return (int)$this->userdata->branch_id;
	}

	public function index() {
		$branch_id = $this->_resolve_branch();

		$data['role']      = $this->role;
		$data['branch_id'] = $branch_id;
		$data['employee']  = $this->employee->get_detail([
			'position.branch_id' => $branch_id,
			'users.active'       => 1,
		], '', '', ['first_name' => 'ASC'])->result_array();

		if ($this->role === 'admin') {
			$data['branch'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
		}

		// Penugasan SPV: admin saja (lintas-cabang), admin-branch tak lihat panel ini.
		if ($this->role === 'admin') {
			$data['spv_list'] = $this->db->select('users.id, users.first_name, users.employee_code')
				->join('users_groups', 'users_groups.user_id = users.id')
				->join('groups', 'groups.id = users_groups.group_id')
				->where_in('groups.name', ['supervisor', 'admin-branch'])
				->where('users.active', 1)
				->order_by('users.first_name', 'ASC')
				->get('users')->result_array();

			$data['assignment'] = $this->db->select('kertas_kerja_spv_assignment.id, spv_user_id, employee_user_id,
					spv.first_name AS spv_name, emp.first_name AS employee_name')
				->join('users spv', 'spv.id = kertas_kerja_spv_assignment.spv_user_id')
				->join('users emp', 'emp.id = kertas_kerja_spv_assignment.employee_user_id')
				->order_by('spv.first_name', 'ASC')
				->get('kertas_kerja_spv_assignment')->result_array();
		}

		$this->template->load('layout/admin', 'kertas_kerja_setting/list', $data);
	}

	public function toggle_wajib() {
		if (!$this->input->is_ajax_request()) {
			return $this->_json(['status' => false, 'message' => 'Akses ditolak']);
		}
		$user_id   = (int)$this->input->post('user_id');
		$wajib     = $this->input->post('wajib') == '1' ? 1 : 0;
		$branch_id = $this->_resolve_branch();

		$check = $this->employee->get_detail([
			'users.id'            => $user_id,
			'position.branch_id'  => $branch_id,
		])->num_rows();
		if ($check == 0) {
			return $this->_json(['status' => false, 'message' => 'Mitra Kerja tidak valid']);
		}

		$this->db->where('id', $user_id)->update('users', ['wajib_kertas_kerja' => $wajib]);
		$this->_json([
			'status'  => true,
			'message' => $wajib ? 'Ditandai wajib isi Kertas Kerja' : 'Kewajiban Kertas Kerja dicabut',
		]);
	}

	/** Admin-only: assign/unassign SPV lintas-cabang. */
	public function assign_spv() {
		if (!$this->input->is_ajax_request() || $this->role !== 'admin') {
			return $this->_json(['status' => false, 'message' => 'Akses ditolak']);
		}
		$spv_user_id      = (int)$this->input->post('spv_user_id');
		$employee_user_id = (int)$this->input->post('employee_user_id');
		if (!$spv_user_id || !$employee_user_id) {
			return $this->_json(['status' => false, 'message' => 'SPV dan Mitra Kerja wajib diisi']);
		}

		$exists = $this->db->where(['spv_user_id' => $spv_user_id, 'employee_user_id' => $employee_user_id])
			->get('kertas_kerja_spv_assignment')->num_rows();
		if ($exists > 0) {
			return $this->_json(['status' => false, 'message' => 'Penugasan sudah ada']);
		}

		$this->db->insert('kertas_kerja_spv_assignment', [
			'spv_user_id'      => $spv_user_id,
			'employee_user_id' => $employee_user_id,
			'created_at'       => date('Y-m-d H:i:s'),
			'created_by'       => (int)$this->userdata->user_id,
		]);
		$this->_json(['status' => true, 'id' => $this->db->insert_id(), 'message' => 'SPV berhasil ditugaskan']);
	}

	public function unassign_spv() {
		if (!$this->input->is_ajax_request() || $this->role !== 'admin') {
			return $this->_json(['status' => false, 'message' => 'Akses ditolak']);
		}
		$id = (int)$this->input->post('id');
		$this->db->where('id', $id)->delete('kertas_kerja_spv_assignment');
		$this->_json(['status' => true, 'message' => 'Penugasan dihapus']);
	}
}

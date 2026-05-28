<?php 

class Deduction extends CI_Controller{

	function __construct(){
		parent::__construct();	
		if(!$this->ion_auth->logged_in()){
			redirect();
		}

		$this->role     = $this->ion_auth->get_users_groups()->row()->name;
		$this->userdata = $this->ion_auth->user()->row();

		$this->load->model('deduction_model', 'deduction');
		$this->load->model('presence_model', 'presence');
		$this->load->model('payroll_model', 'payroll');
		$this->load->model('user_model', 'employee');
		$this->load->model('branch_model', 'branch');
	}

	public function index(){
		if($this->role == 'admin' || $this->role == 'admin-branch'){
			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
				$data['branch_id'] = $branch_id;
				$data['branch'] = $this->branch->get_data(['branch_name', 'ASC'])->result_array();
				$data['branch_detail'] = $this->branch->get_detail('id', $branch_id)->row_array();
			
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$find['branch_id'] = $branch_id;
			$data['list'] = $this->deduction->get_dataTable($find);
			$this->template->load('layout/admin','master_data/deduction/index', $data);
		}else{
			show_404();
		}
	}

	public function insert(){
		if($this->input->is_ajax_request() && ($this->role == 'admin' || $this->role == 'admin-branch')){
			$p = $this->input->post();

			$p['branch_id'] = $this->role == 'admin' ? $p['branch_id'] : $this->userdata->branch_id;

			$this->form_validation->set_data($p);
			$this->form_validation->set_rules('deduction_name', 'Nama jabatan', 'required');
			if($this->form_validation->run() == TRUE){
				$p['is_active'] = isset($p['is_active']) && $p['is_active'] == '0' ? '0' : '1';
				$find = [
				    'deduction_name' => $p['deduction_name'],
				    'branch_id'     => $p['branch_id']
				];

				$cek = $this->deduction->get_detail($find);
				if($cek->num_rows() == 0){
					$p['created_at'] = date('Y-m-d H:i:s');

					if($this->deduction->insert($p)){
						$res = [
							'status'  => true,
							'message' => 'Data berhasil dimasukkan',
							'tipe'	  => 'success'	
						];
					}else{
						$res = [
							'status'  => false,
							'message' => 'Terjadi kesalahan, coba lagi nanti'
						];
					}

				}else{
					$res = [
						'status'  => false,
						'message' => 'deduction sudah ada'
					];
				}
    			
			}else{
				$res = [
					'status'  => false,
					'message' => validation_errors()
				];
			}

			echo json_encode($res);

		}else{
			show_404();
		}
	}


	public function update(){
		if($this->input->is_ajax_request() && ($this->role == 'admin' || $this->role == 'admin-branch')){
			$p = $this->input->post();
			$id = $p['id_deduction']; unset($p['id_deduction']);
			
			$p['branch_id'] = $this->role == 'admin' ? $p['branch_id'] : $this->userdata->branch_id;

			$this->form_validation->set_data($p);
			$this->form_validation->set_rules('deduction_name', 'Nama jabatan', 'required');
			$p['is_active'] = isset($p['is_active']) && $p['is_active'] == '0' ? '0' : '1';

			$unique = [
				'deduction_name'  => 'Nama deduction'
			];
			$status = true; $title = '';

			$cek = $this->deduction->get_detail('id', $id)->row_array();
			foreach ($unique as $row => $val) {
				$find[$row]        = $p[$row];
				$find['branch_id'] = $p['branch_id'];
				$exist = $this->deduction->get_detail($row, $p[$row]);
				if($exist->num_rows() > 0){
					$exist = $exist->row_array();

					if($cek[$row] != $exist[$row]){
						$title  = $val;
						$status = false;
						break;
					}
				}
			}

			if($this->form_validation->run() == TRUE){
				
				if($status){
					$data = $this->deduction->get_detail('deduction.id', $id);
					if($data->num_rows() > 0){
						$p['updated_at'] = date('Y-m-d H:i:s');

						if($this->deduction->update($p, $id)){
							$res = [
								'status'  => true,
								'message' => 'Data berhasil diubah',
								'tipe'	  => 'success'	
							];
						}else{
							$res = [
								'status'  => false,
								'message' => 'Terjadi kesalahan, coba lagi nanti'
							];
						}

					}else{
						$res = [
							'status'  => false,
							'message' => 'Data cabang tidak diketahui'
						];
					}

				}else{
					$res = [
						'status'  => false,
						'message' => $title .' sudah ada'
					];
				}

			}else{
				$res = [
					'status'  => false,
					'message' => validation_errors()
				];
			}

			echo json_encode($res);

		}else{
			show_404();
		}
	}

	public function delete(){
		if($this->input->is_ajax_request() && ($this->role == 'admin' || $this->role == 'admin-branch')){
			$find = [
				'deduction.id' => $this->input->post('id'),
				'deduction.branch_id' => $this->userdata->branch_id
			];
			$data = $this->deduction->get_detail($find)->row_array();
			if($this->deduction->delete($this->input->post('id'))){
				$res = [
					'status'  => true,
					'message' => 'Data berhasil dihapus'
				];
			}else{
				$res = [
					'status'  => false,
					'message' => 'Data gagal dihapus'
				];
			}

			echo json_encode($res);
		}else{
			show_404();
		}
	}


	public function call_deduction($page, $employee_id){
		if($this->input->is_ajax_request()){
			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$check = $this->employee->get_detail([
						'users.id'  => $employee_id,
						'branch.id' => $branch_id
					 ]);

			if($check->num_rows() > 0){
				$p = $this->input->post();

				if($page == 'form'){
					
					$comp['deduction'] = $this->presence->get_deduction($employee_id, $branch_id, $p['month'], $p['year']);
					$table = '_deductionForm';

				}else{

				}

				$res = [
					'status' => true,
					'page'   => $this->load->view('hr/payroll/'.$table, $comp, TRUE)
				];

			}else{
				$res = [
					'status'  => false,
					'message' => 'Data karyawan tidak ditemukan'
				];
			}
			
			echo json_encode($res);

		}else{
			show_404();
		}
	}


	public function call_fine($employee_id){
		if($this->input->is_ajax_request()){
			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$check = $this->employee->get_detail([
						'users.id'  => $employee_id,
						'branch.id' => $branch_id
					 ]);

			if($check->num_rows() > 0){
				$p = $this->input->post();

				$data['fine'] = $this->presence->get_fine($employee_id, $p['month'], $p['year']);
				$res = [
					'status' => true,
					'page'   => $this->load->view('hr/payroll/_fineTable', $data, TRUE),
					'fine'	 => $data['fine']
				];

			}else{
				$res = [
					'status'  => false,
					'message' => 'Data karyawan tidak ditemukan'
				];
			}
			
			echo json_encode($res);

		}else{
			show_404();
		}
	}
}

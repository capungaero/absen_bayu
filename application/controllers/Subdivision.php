<?php 

class Subdivision extends CI_Controller{

	function __construct(){
		parent::__construct();	
		if(!$this->ion_auth->logged_in()){
			redirect();
		}

		$this->role     = $this->ion_auth->get_users_groups()->row()->name;
		$this->userdata = $this->ion_auth->user()->row();

		$this->load->model('subdivision_model', 'subdivision');
		$this->load->model('branch_model', 'branch');
	}

	public function index(){
		if($this->role == 'admin' || $this->role == 'admin-branch'){
			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
				$data['branch'] = $this->branch->get_data(['branch_name', 'ASC'])->result_array();
				$data['branch_detail'] = $this->branch->get_detail('id', $branch_id)->row_array();
			
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$find['branch_id'] = $branch_id;
			$data['branch_id'] = $branch_id;
			$data['list'] = $this->subdivision->get_dataTable($find);
			$this->template->load('layout/admin','master_data/subdivision/index', $data);
		}else{
			show_404();
		}
	}

	public function insert(){
		if($this->input->is_ajax_request() && ($this->role == 'admin' || $this->role == 'admin-branch')){
			$p = $this->input->post();

			$p['branch_id'] = $this->role == 'admin' ? $p['branch_id'] : $this->userdata->branch_id;

			$this->form_validation->set_data($p);
			$this->form_validation->set_rules('subdivision_name', 'Nama Sub Departement', 'required');
			$this->form_validation->set_rules('subdivision_code', 'Kode Sub Departement', 'required');

			if($this->form_validation->run() == TRUE){
				$find = [
				    'subdivision_code' => $p['subdivision_code'],
				    'branch_id'     => $p['branch_id']
				];
         

				$cek = $this->subdivision->get_detail($find);
				if($cek->num_rows() == 0){
					if($this->subdivision->insert($p)){
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
						'message' => 'Kode jabatan sudah ada'
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
			$id = $p['id_subdivision']; unset($p['id_subdivision']);
			
			$branch_id = $this->role == 'admin' ? $p['branch_id'] : $this->userdata->branch_id;

			$this->form_validation->set_data($p);
			$this->form_validation->set_rules('subdivision_name', 'Nama Sub Departement', 'required');
			$this->form_validation->set_rules('subdivision_code', 'Kode Sub Departement', 'required');
			$this->form_validation->set_rules('branch_id', 'Cabang', 'required');

			$unique = [
				'subdivision_code'  => 'Kode Sub Departement'
			];
			$status = true; $title = '';

			$cek = $this->subdivision->get_detail('id', $id)->row_array();
			foreach ($unique as $row => $val) {
				$find[$row]        = $p[$row];
				$find['branch_id'] = $branch_id;
				$exist = $this->subdivision->get_detail($row, $p[$row]);
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
					$data = $this->subdivision->get_detail('subdivision.id', $id);
					if($data->num_rows() > 0){
						if($this->subdivision->update($p, $id)){
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
				'subdivision.id' => $this->input->post('id'),
				'subdivision.branch_id' => $this->userdata->branch_id
			];
			$data = $this->subdivision->get_detail($find)->row_array();
			if($this->subdivision->delete($this->input->post('id'))){
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

}
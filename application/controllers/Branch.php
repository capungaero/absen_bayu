<?php 

class Branch extends CI_Controller{

	function __construct(){
		parent::__construct();	
		if(!$this->ion_auth->logged_in()){
			redirect();
		}

		$this->role    = $this->ion_auth->get_users_groups()->row()->name;
		$this->user_id = $this->ion_auth->user()->row()->id;
		$this->component = ['branch', 'principal_group', 'field'];

		$this->load->model('branch_model', 'branch');
	}

	public function index(){
		if($this->role == 'admin'){
			$data['list'] = $this->branch->get_dataTable();
			$this->template->load('layout/admin','master_data/branch/index', $data);
		}else{
			show_404();
		}
	}

	public function detail($id){
		if($this->role == 'admin'){
			$cek = $this->branch->get_detail('branch.id', $id);

			if($cek->num_rows() > 0){
				$data['sektor']   = $cek->row_array();
				$this->template->load('layout/admin','master_data/sektor/detail', $data);

			}else{
				show_404();
			}
			
		}else{
			show_404();
		}
	}

	public function update(){
		if($this->input->is_ajax_request() && $this->role == 'admin'){
			$p = $this->input->post();
			$id = $p['id_branch']; unset($p['id_branch']);
			$p['is_active'] = isset($p['is_active']) && $p['is_active'] == '0' ? '0' : '1';
			$p['pray_late_start_rate'] = format_angka($p['pray_late_start_rate']);
			$p['pray_late_multiple_rate'] = format_angka($p['pray_late_multiple_rate']);
			$p['pray_late_fix_rate'] = format_angka($p['pray_late_fix_rate']);
			
			$this->form_validation->set_data($p);
			$this->form_validation->set_rules('branch_name', 'Nama cabang', 'required');
			$this->form_validation->set_rules('branch_code', 'Kode cabang', 'required');
			$this->form_validation->set_rules('branch_phone', 'kontak', 'required');
			$this->form_validation->set_rules('city', 'Kota', 'required');
			$this->form_validation->set_rules('address', 'Alamat Lengkap', 'required');
			$this->form_validation->set_rules('is_active', 'Status cabang', 'required|in_list[0,1]');

			$this->form_validation->set_rules('pray_late_start_rate', 'Nominal Pertama', 'required|numeric|greater_than[-1]');
			$this->form_validation->set_rules('pray_late_multiple_count', 'Kelipatan Waktu', 'required|numeric|greater_than[0]');
			$this->form_validation->set_rules('pray_late_multiple_rate', 'Nominal Kelipatan', 'required|numeric|greater_than[-1]');
			$this->form_validation->set_rules('pray_late_fix_rate', 'Nominal Maksimal', 'required|numeric|greater_than[-1]');

			$param = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];
			foreach ($param as $row) {
				$title = $row == 'friday' ? 'Jum\'at' : $row;
				$this->form_validation->set_rules($row.'_pray_time', "Waktu Sholat [".$title."] ", 'required');	
				$this->form_validation->set_rules($row.'_pray_time_in', "Mulai Izin [".$title."] ", 'required');	
				$this->form_validation->set_rules($row.'_pray_time_out', "Selesai Izin [".$title."] ", 'required');	
				$this->form_validation->set_rules($row.'_pray_time_range', 'Rentang Izin ['.$title.']', 'required|numeric|greater_than[0]');
			}

			$unique = [
						'branch_name'  => 'Nama cabang', 
						'branch_code'  => 'Kode cabang',
						'branch_phone' => 'Kontak'
					];
			$status = true; $title = '';

			$cek = $this->branch->get_detail('id', $id)->row_array();
			if($p['is_active'] == '0'){
				$employee_count = $this->db->from('users')
										   ->join('position', 'position.id = users.position_id')
										   ->where('position.branch_id', $id)
										   ->count_all_results();
				if($employee_count > 0){
					$status = false;
					$title = 'Cabang masih berisi karyawan. Pindahkan karyawan sebelum cabang dinonaktifkan.';
				}
			}

			foreach ($unique as $row => $val) {
				if(!$status){ break; }
				$exist = $this->branch->get_detail($row, $p[$row]);
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
					$data = $this->branch->get_detail('branch.id', $id);
					if($data->num_rows() > 0){
						if($this->branch->update($p, $id)){
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
						'message' => strpos($title, 'Cabang masih') === 0 ? $title : $title .' sudah ada'
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
		if($this->input->is_ajax_request() && $this->role == 'admin'){
			$data = $this->branch->get_detail('branch.id', $this->input->post('id'))->row_array();
			if($this->branch->delete($this->input->post('id'))){
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

	public function get_data(){
		if($this->input->is_ajax_request() && $this->role == 'admin'){
			$data = $this->branch->get_detail('id', $this->input->post('id'));
			if($data->num_rows() > 0){
				$res = [
					'status' => true,
					'data'	 => $data->row_array()
				];

			}else{
				$res = [
					'status' => false,
					'data'   => 'ID tidak diketahui'
				];
			}

			echo json_encode($res);

		}else{
			show_404();
		}
	}

}

<?php 

class Shift extends CI_Controller{

	function __construct(){
		parent::__construct();	
		$this->load->model('shift_model', 'shift');
		$this->load->model('branch_model', 'branch');

		if(!$this->ion_auth->logged_in()){
			redirect('');
		}

		$this->role     = $this->ion_auth->get_users_groups()->row()->name;
		$this->userdata = $this->ion_auth->user()->row();

	}

	public function index(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor'])){
			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
				$data['branch'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$find = [
				'branch_id' => $branch_id
			];
			$data['list']      = $this->shift->get_dataTable($find);
			$data['branch_detail'] = $this->branch->get_detail('id', $branch_id)->row_array();
			$data['branch_id']     = $branch_id;
			$this->template->load('layout/admin','hr/schedule/shift', $data);
		}else{
			redirect();
		}
	}

	public function insert(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){

			$p = $this->input->post();
			$p['late_amount_start'] = format_angka($p['late_amount_start']);
			$p['late_amount_multiple_start'] = format_angka($p['late_amount_multiple_start']);
			$p['late_amount_rest'] = format_angka($p['late_amount_rest']);
			$p['late_amount_multiple_rest'] = format_angka($p['late_amount_multiple_rest']);
			$p['late_amount_max_rest'] = format_angka($p['late_amount_max_rest']);
			$p['late_amount_max_start'] = format_angka($p['late_amount_max_start']);
			$p['is_active'] = isset($p['is_active']) && $p['is_active'] == '0' ? '0' : '1';

			$this->form_validation->set_data($p);
			$this->form_validation->set_rules('start_time', 'Jam Mulai', 'required');
			$this->form_validation->set_rules('end_time', 'Jam Selesai', 'required');

			$this->form_validation->set_rules('start_time_in', 'Jam Mulai Masuk', 'required');
			$this->form_validation->set_rules('start_time_late', 'Batas Keterlambatan', 'required');
			$this->form_validation->set_rules('end_time_in', 'Jam Selesai Masuk', 'required');
			$this->form_validation->set_rules('start_time_out', 'Jam Mulai Keluar', 'required');
			$this->form_validation->set_rules('end_time_out', 'Jam Selesai Keluar', 'required');

			$this->form_validation->set_rules('late_amount_start', 'Nominal Denda Pertama [WAKTU MASUK]', 'required|numeric|greater_than[-1]');
			$this->form_validation->set_rules('late_amount_max_start', 'Nominal Maksimal Denda [WAKTU MASUK]', 'required|numeric|greater_than[-1]');
			$this->form_validation->set_rules('late_multiple_count_start', 'Kelipatan Waktu [WAKTU MASUK]', 'required|numeric|greater_than[-1]');
			$this->form_validation->set_rules('late_amount_multiple_start', 'Nominal Kelipatan Denda [WAKTU MASUK]', 'required|numeric|greater_than[-1]');

			$this->form_validation->set_rules('late_amount_rest', 'Nominal Denda Pertama [WAKTU ISTIRAHAT]', 'required|numeric|greater_than[-1]');
			$this->form_validation->set_rules('late_multiple_count_rest', 'Kelipatan Waktu [WAKTU ISTIRAHAT]', 'required|numeric|greater_than[-1]');
			$this->form_validation->set_rules('late_amount_multiple_rest', 'Nominal Kelipatan Denda [WAKTU ISTIRAHAT]', 'required|numeric|greater_than[-1]');
			$this->form_validation->set_rules('late_amount_max_rest', 'Nominal Maksimal Denda [WAKTU ISTIRAHAT]', 'required|numeric|greater_than[-1]');

			$this->form_validation->set_rules('start_time_rest', 'Jam Mulai Istirahat [BIASA]', 'required');
			$this->form_validation->set_rules('end_time_rest', 'Jam Selesai Istirahat [BIASA]', 'required');
			$this->form_validation->set_rules('rest_time_range', 'Total Jam Istirahat [BIASA]', 'required|numeric|greater_than[-1]');
			$this->form_validation->set_rules('is_active', 'Status', 'required|in_list[0,1]');

			if($this->role == 'admin'){
				$this->form_validation->set_rules('branch_id', 'Cabang', 'required|numeric');
			}

			if($this->form_validation->run() == TRUE){
				$unique = [
					'shift_name' => 'Nama shift',
					'shift_code' => 'Kode shift'
				];
				$status = true; $title = '';

				foreach ($unique as $row => $val){
					$find = [];
					$find[$row] = $p[$row];
					$find['branch_id'] = $this->role == 'admin' ? $p['branch_id'] : $this->userdata->branch_id;

					$exist = $this->shift->get_detail($find);
					if($exist->num_rows() > 0){
						$title  = $val;
						$status = false;
						break;
					}
				}

				if($status){
					$p['branch_id']  = $find['branch_id'];
					$p['created_at'] = date('Y-m-d H:i:s');

					if($this->shift->insert($p)){
						$res = [
							'status'  => true,
							'message' => 'Data berhasil dimasukkan'
						];

					}else{
						$res = [
							'status'  => false,
							'message' => 'Data gagal dimasukkan'
						];
					}

				}else{
					$res = [
						'status'  => false,
						'message' => $title. ' sudah ada'
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
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){

			$p   = $this->input->post();
			$id  = $p['id_shift']; unset($p['id_shift']);
			
			$branch_id = $this->role == 'admin' ? $p['branch_id'] : $this->userdata->branch_id;

			$cek = $this->shift->get_detail([
								'branch_id' => $branch_id,
								'shift.id'  => $id
							]);

			if($cek->num_rows() > 0){
				unset($p['id_shift']);
				$p['late_amount_start'] = format_angka($p['late_amount_start']);
				$p['late_amount_multiple_start'] = format_angka($p['late_amount_multiple_start']);
				$p['late_amount_rest'] = format_angka($p['late_amount_rest']);
				$p['late_amount_multiple_rest'] = format_angka($p['late_amount_multiple_rest']);
				$p['late_amount_max_rest'] = format_angka($p['late_amount_max_rest']);
				$p['late_amount_max_start'] = format_angka($p['late_amount_max_start']);
				$p['is_active'] = isset($p['is_active']) && $p['is_active'] == '0' ? '0' : '1';

				$this->form_validation->set_data($p);
				$this->form_validation->set_rules('start_time', 'Jam Mulai', 'required');
				$this->form_validation->set_rules('end_time', 'Jam Selesai', 'required');

				$this->form_validation->set_rules('start_time_in', 'Jam Mulai Masuk', 'required');
				$this->form_validation->set_rules('start_time_late', 'Batas Keterlambatan', 'required');
				$this->form_validation->set_rules('end_time_in', 'Jam Selesai Masuk', 'required');
				$this->form_validation->set_rules('start_time_out', 'Jam Mulai Keluar', 'required');
				$this->form_validation->set_rules('end_time_out', 'Jam Selesai Keluar', 'required');

				$this->form_validation->set_rules('late_amount_start', 'Nominal Denda Pertama [WAKTU MASUK]', 'required|numeric|greater_than[-1]');
				$this->form_validation->set_rules('late_multiple_count_start', 'Kelipatan Waktu [WAKTU MASUK]', 'required|numeric|greater_than[-1]');
				$this->form_validation->set_rules('late_amount_multiple_start', 'Nominal Kelipatan Denda [WAKTU MASUK]', 'required|numeric|greater_than[-1]');
				$this->form_validation->set_rules('late_amount_max_start', 'Nominal Maksimal Denda [WAKTU MASUK]', 'required|numeric|greater_than[-1]');

				$this->form_validation->set_rules('late_amount_rest', 'Nominal Denda Pertama [WAKTU ISTIRAHAT]', 'required|numeric|greater_than[-1]');
				$this->form_validation->set_rules('late_multiple_count_rest', 'Kelipatan Waktu [WAKTU ISTIRAHAT]', 'required|numeric|greater_than[-1]');
				$this->form_validation->set_rules('late_amount_multiple_rest', 'Nominal Kelipatan Denda [WAKTU ISTIRAHAT]', 'required|numeric|greater_than[-1]');
				$this->form_validation->set_rules('late_amount_max_rest', 'Nominal Maksimal Denda [WAKTU ISTIRAHAT]', 'required|numeric|greater_than[-1]');

				$this->form_validation->set_rules('start_time_rest', 'Jam Mulai Istirahat [BIASA]', 'required');
				$this->form_validation->set_rules('end_time_rest', 'Jam Selesai Istirahat [BIASA]', 'required');
				$this->form_validation->set_rules('rest_time_range', 'Total Jam Istirahat [BIASA]', 'required|numeric|greater_than[-1]');
				$this->form_validation->set_rules('is_active', 'Status', 'required|in_list[0,1]');

				if($this->form_validation->run() == TRUE){
					$unique = [
						'shift_name' => 'Nama shift',
						'shift_code' => 'Kode shift'
					];
					$status = true; $title = '';

					$find = [
						'branch_id'	=> $branch_id,
						'id'		=> $id
					];
					$cek = $this->shift->get_detail($find)->row_array();
					foreach ($unique as $row => $val) {
						$find = [];
						$find[$row] = $p[$row];
					
						$find['branch_id'] = $branch_id;

						$exist = $this->shift->get_detail($find);
						if($exist->num_rows() > 0){
							$exist = $exist->row_array();
							if($cek[$row] != $exist[$row]){
								$title  = $val;
								$status = false;
								break;
							}
						}
					}

					if($status){
						$p['branch_id']  = $branch_id;
						$p['updated_at'] = date('Y-m-d H:i:s');
                        
                        
						if($this->shift->update($p, $id)){
							$res = [
								'status'  => true,
								'message' => 'Data berhasil dimasukkan'
							];

						}else{
							$res = [
								'status'  => false,
								'message' => 'Data gagal dimasukkan'
							];
						}

					}else{
						$res = [
							'status'  => false,
							'message' => $title. ' sudah ada'
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

		}else{
			show_404();
		}
	}

	public function delete(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){
			$find['id'] = $this->input->post('id');

			if($this->role != 'admin'){
				$find['branch_id'] = $this->userdata->branch_id;
			}
			$data = $this->shift->get_detail($find);

			if($data->num_rows() > 0){
				if($this->shift->delete($this->input->post('id'))){
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
			
		}else{
			show_404();
		}
	}

	public function change_status(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){
			$id = $this->input->post('id');
			$find['id'] = $id;

			if($this->role != 'admin'){
				$find['branch_id'] = $this->userdata->branch_id;
			}

			$data = $this->shift->get_detail($find);
			if($data->num_rows() > 0){
				$row = $data->row_array();
				$is_active = $row['is_active'] == '1' ? '0' : '1';

				if($this->shift->update([
					'is_active' => $is_active,
					'updated_at' => date('Y-m-d H:i:s')
				], $id)){
					$res = [
						'status' => true,
						'message' => $is_active == '1' ? 'Shift berhasil diaktifkan' : 'Shift berhasil dinonaktifkan'
					];
				}else{
					$res = [
						'status' => false,
						'message' => 'Status shift gagal diubah'
					];
				}

				echo json_encode($res);
			}else{
				show_404();
			}
		}else{
			show_404();
		}
	}

	public function bulk_status(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){
			$is_active = $this->input->post('is_active') == '0' ? '0' : '1';
			$branch_id = $this->role == 'admin' ? $this->input->post('branch_id') : $this->userdata->branch_id;

			if($branch_id == ''){
				echo json_encode([
					'status' => false,
					'message' => 'Cabang tidak ditemukan'
				]);
				return;
			}

			$this->db->where('branch_id', $branch_id)
					 ->update('shift', [
					 	'is_active' => $is_active,
					 	'updated_at' => date('Y-m-d H:i:s')
					 ]);

			echo json_encode([
				'status' => true,
				'message' => $is_active == '1' ? 'Semua shift berhasil diaktifkan' : 'Semua shift berhasil dinonaktifkan'
			]);
		}else{
			show_404();
		}
	}
}

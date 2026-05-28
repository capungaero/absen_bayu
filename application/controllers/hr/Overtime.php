<?php 

class Overtime extends CI_Controller{

	function __construct(){
		parent::__construct();	
		$this->load->model('overtime_model', 'overtime');
		$this->load->model('branch_model', 'branch');
		$this->load->model('user_model', 'employee');

		if(!$this->ion_auth->logged_in()){
			redirect('');
		}

		$this->role     = $this->ion_auth->get_users_groups()->row()->name;
		$this->userdata = $this->ion_auth->user()->row();

	}

	public function index(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor', 'employee'])){
			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
				$data['branch'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$find = [
				'position.branch_id' => $branch_id
			];

			if($this->role == 'employee'){
				$find['user_id'] = $this->userdata->user_id;
			}else{
				$data['employee']      = $this->employee->get_detail([
									'position.branch_id' => $branch_id,
									'users.active' => 1
								], '', '', ['first_name' => 'ASC'])->result_array();
			}

			$data['list']      = $this->overtime->get_dataTable($find);
			$data['branch_detail'] = $this->branch->get_detail('id', $branch_id)->row_array();
			$data['branch_id']     = $branch_id;

			$this->template->load('layout/admin','hr/overtime/list/index', $data);

		}else{
			redirect();
		}
	}

	public function insert(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor', 'employee']) && $this->input->is_ajax_request()){

			$p = $this->input->post();

			$this->form_validation->set_data($p);

			if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor'])){
				$this->form_validation->set_rules('user_id[]', 'Karyawan', 'required');
			}else{
				$p['user_id'] = [$this->userdata->user_id];
			}

			if($this->form_validation->run() == TRUE){

				$user_ids = isset($p['user_id']) && is_array($p['user_id']) ? $p['user_id'] : [];
				$overtime_hours = isset($p['overtime_hour']) && is_array($p['overtime_hour']) ? $p['overtime_hour'] : [];
				$overtime_dates = isset($p['overtime_date']) && is_array($p['overtime_date']) ? $p['overtime_date'] : [];
				$rows = [];
				foreach($user_ids as $index => $user_id){
					$user_id = trim($user_id);
					$hour = isset($overtime_hours[$index]) ? trim($overtime_hours[$index]) : '';
					$date = isset($overtime_dates[$index]) ? trim($overtime_dates[$index]) : '';
					if($user_id == '' && $hour == '' && $date == ''){ continue; }
					if($user_id == '' || $hour == '' || $date == '' || !is_numeric($hour) || $hour <= 0 || strtotime($date) === false){
						$res = [
							'status' => false,
							'message' => 'Lengkapi karyawan, lama lembur, dan tanggal lembur pada setiap baris.'
						];
						echo json_encode($res);
						return;
					}
					$rows[] = [
						'user_id' => $user_id,
						'overtime_hour' => $hour,
						'overtime_date' => date('Y-m-d', strtotime($date))
					];
				}

				if(!empty($rows)){
					$config['upload_path']          = './assets/images/hr/overtime/';
			        $config['allowed_types']        = 'png|jpeg|jpg';
			        $config['file_name']			= "overtime_multi_".generateRandom(5)."_".time();
			        $config['max_size']             = 10240;
			        $config['max_width']            = 10000;
			        $config['max_height']           = 10000;

			        $this->load->library('upload', $config);

			        if($this->upload->do_upload('overtime_proof')){
			        	$upl = $this->upload->data();
			        	$imagePath = $upl['full_path'];
			        	$config['image_library'] = 'gd2';
				        $config['source_image'] = $imagePath;
				        $config['quality'] = '80%';
				        $config['maintain_ratio'] = TRUE;
				        $config['width'] = 800;
				        $this->load->library('image_lib', $config);
				        if ($this->image_lib->resize()) {
				        	$inserted = $skipped = 0;
				        	$this->db->trans_begin();
				        	foreach($rows as $row){
				        		$query = $this->overtime->get_detail([
									'user_id' => $row['user_id'],
									'overtime_date' => $row['overtime_date']
								]);
								if($query->num_rows() > 0 && in_array($query->row_array()['overtime_status'], ['approve', 'pending'])){
									$skipped++;
									continue;
								}
					        	$this->overtime->insert([
					        		'user_id'		 => $row['user_id'],
					        		'overtime_hour'  => $row['overtime_hour'],
					        		'overtime_date'  => $row['overtime_date'],
					        		'overtime_proof' => $upl['file_name'],
					        		'created_at'     => date('Y-m-d H:i:s')
					        	]);
					        	$inserted++;
					        }

							if($this->db->trans_status()){
								$this->db->trans_commit();
								$res = [
									'status'  => true,
									'message' => 'Data berhasil dimasukkan. Tersimpan: '.$inserted.', dilewati karena sudah ada: '.$skipped.'.'
								];

							}else{
								$this->db->trans_rollback();
								$res = [
									'status'  => false,
									'message' => 'Data gagal dimasukkan'
								];
							}

				        } else {
				        	$res = [
								'status'  => false,
								'message' =>  $this->image_lib->display_errors()
							];
				        }
			        	

					}else{
						$res = [
							'status'  => false,
							'message' => $this->upload->display_errors()
						];
					}

				}else{
					$res = [
						'status'  => false,
						'message' => 'Karyawan belum dipilih'
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
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor']) && $this->input->is_ajax_request()){
			$p = $this->input->post();
			$this->form_validation->set_data($p);
			$this->form_validation->set_rules('overtime_id', 'ID Lembur', 'required|numeric');
			$this->form_validation->set_rules('overtime_hour', 'Lama jam lembur', 'required|numeric|greater_than[0]');
			$this->form_validation->set_rules('overtime_date', 'Tanggal Lembur', 'required');

			if($this->form_validation->run() == TRUE){
				$find = ['overtime.id' => $p['overtime_id']];
				if($this->role != 'admin'){ $find['position.branch_id'] = $this->userdata->branch_id; }
				$row = $this->overtime->get_detail($find)->row_array();
				if(!empty($row)){
					$data = [
						'overtime_hour' => $p['overtime_hour'],
						'overtime_date' => $p['overtime_date'],
						'updated_at' => date('Y-m-d H:i:s')
					];

					if(!empty($_FILES['overtime_proof']['name'])){
						$config['upload_path'] = './assets/images/hr/overtime/';
				        $config['allowed_types'] = 'png|jpeg|jpg';
				        $config['file_name'] = "overtime_".$row['user_id']."_".generateRandom(5)."_".time();
				        $config['max_size'] = 10240;
				        $config['max_width'] = 10000;
				        $config['max_height'] = 10000;
				        $this->load->library('upload', $config);
				        if(!$this->upload->do_upload('overtime_proof')){
				        	echo json_encode(['status' => false, 'message' => $this->upload->display_errors()]);
				        	return;
				        }
				        $upl = $this->upload->data();
				        $data['overtime_proof'] = $upl['file_name'];
					}

					$this->overtime->update($data, $p['overtime_id']);
					echo json_encode(['status' => true, 'message' => 'Data lembur berhasil diubah']);
					return;
				}
				echo json_encode(['status' => false, 'message' => 'Data lembur tidak ditemukan']);
				return;
			}

			echo json_encode(['status' => false, 'message' => validation_errors()]);
			return;
		}
		show_404();
	}

	public function detail($overtime_id){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor', 'overtime'])) {

			$find = [
				'overtime.id' => $overtime_id
			];

			if($this->role != 'admin'){
				$find['position.branch_id'] = $this->userdata->branch_id;
			}

			if($this->role == 'employee'){
				$find['user_id'] = $this->userdata->user_id;
			}

			$tr = $this->overtime->get_detail($find);

			if($tr->num_rows() > 0){
				$data['overtime']  = $tr->row_array();
				$this->template->load('layout/admin','hr/overtime/list/detail', $data);
			}else{
				show_404();
			}
			
		}else{
			show_404();
		}
	}

	public function acc(){
		if(in_array($this->role, ['admin', 'admin-branch', 'supervisor'])){
			$data['pending'] = $this->overtime->get_table_acc([
									'overtime_status' => 'pending'
								], 'tablePending');

			$data['unpending'] = $this->overtime->get_table_acc([
									'overtime_status !=' => 'pending'
								], 'tableUnPending');

			$this->template->load('layout/admin','hr/overtime/acc/index', $data);
		}else{
			show_404();
		}
	}

	public function detail_acc($overtime_id){
		if(in_array($this->role, ['admin', 'admin-branch', 'supervisor'])) {

			$find = [
				'overtime.id' => $overtime_id
			];

			$tr = $this->overtime->get_detail($find);

			if($tr->num_rows() > 0){
				$data['overtime']  = $tr->row_array();
				$this->template->load('layout/admin','hr/overtime/acc/detail', $data);
			}else{
				show_404();
			}
			
		}else{
			show_404();
		}
	}

	public function change_status($overtime_id){
		if(in_array($this->role, ['admin', 'admin-branch', 'supervisor']) && $this->input->is_ajax_request()) {

			$find = [
				'overtime.id' 	  => $overtime_id,
				'overtime_status' => 'pending'
			];

			$tr = $this->overtime->get_detail($find);
			$p  = $this->input->post();

			if($tr->num_rows() > 0 && in_array($p['status'], ['approve', 'deny'])){
				$this->db->trans_begin();
				$data = [
					'overtime_status' => $p['status'],
					'confirm_at'	  => date('Y-m-d H:i:s'),
					'reject_reason'   => $p['status'] == 'deny' ? $p['reject_reason'] : ''
				];
				$this->overtime->update($data, $overtime_id);

				if($this->db->trans_status()){
					$this->db->trans_commit();
					$res = [
						'status' => true
					];
				}else{
					$this->db->trans_rollback();
					$res = [
						'status'  => false,
						'message' => 'Terjadi kesalahan, coba lagi nanti'
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

	public function cancel_status($overtime_id){
		if(in_array($this->role, ['admin', 'admin-branch', 'supervisor']) && $this->input->is_ajax_request()) {

			$find = [
				'overtime.id' 	  => $overtime_id,
				'overtime_status' => 'approve'
			];

			$tr = $this->overtime->get_detail($find);
			$p  = $this->input->post();

			if($tr->num_rows() > 0){
				$this->db->trans_begin();
				$data = [
					'overtime_status' => 'cancel',
					'updated_at'	  => date('Y-m-d H:i:s')
				];
				$this->overtime->update($data, $overtime_id);

				if($this->db->trans_status()){
					$this->db->trans_commit();
					$res = [
						'status' => true
					];
				}else{
					$this->db->trans_rollback();
					$res = [
						'status'  => false,
						'message' => 'Terjadi kesalahan, coba lagi nanti'
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
}

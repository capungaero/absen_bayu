<?php 
require_once FCPATH.'lib/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Employee extends CI_Controller{

	function __construct(){
		parent::__construct();	
		if(!$this->ion_auth->logged_in()){
			redirect();
		}

		$this->role    = $this->ion_auth->get_users_groups()->row()->name;
		$this->userdata = $this->ion_auth->user()->row();

		$this->load->model('user_model', 'employee');
		$this->load->model('position_model', 'position');
		$this->load->model('subdivision_model', 'subdivision');
		$this->load->model('shift_model', 'shift');
		$this->load->model('branch_model', 'branch');
	}

	public function index(){
		if($this->role == 'admin' || $this->role == 'admin-branch' || $this->role == 'hr'){

			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$find = [
				'branch_id' => $branch_id
			];

			$data['cluster']  = $this->shift->get_cluster_detail($find)->result_array();
			$data['position'] = $this->position->get_detail($find)->result_array();
			$data['subdivision'] = $this->subdivision->get_detail($find)->result_array();
			$data['list'] = $this->employee->get_tableUser(['branch.id' => $branch_id]);

			$data['branch_id'] = $branch_id;
			$data['branch'] = $this->branch->get_data(['branch_name', 'ASC'])->result_array();
			$data['branch_detail'] = $this->branch->get_detail('id', $branch_id)->row_array();

			$this->template->load('layout/admin','master_data/employee/index', $data);
		}else{
			show_404();
		}
	}

	public function detail($id){
		if(in_array($this->role, ['admin', 'admin-branch'])){
			$cek = $this->employee->get_detail('employee.id', $id);

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

	public function insert(){
		if($this->input->is_ajax_request() && in_array($this->role, ['admin', 'admin-branch'])){
			$p = $this->input->post();
			$p['location'] = isset($p['location']) ? trim($p['location']) : null;

			$p['salary'] = format_angka($p['salary']);
			$p['overtime_hour_rate'] = format_angka($p['overtime_hour_rate']);

			if($p['salary_minimum'] != ''){
				$p['salary_minimum'] = format_angka($p['salary_minimum']);
			}

			$this->form_validation->set_data($p);

			if($p['salary_minimum'] != ''){
				$this->form_validation->set_rules('salary', 'Gaji', 'required|numeric|greater_than[0]|less_than['.$p['salary'].']');
			}

			if($p['status_work'] != 'permanent'){
				$this->form_validation->set_rules('status_work_expiration', 'Tanggal Selesai Kerja', 'required');
			}else{
				$p['status_work_expiration'] = null;
			}

			$this->form_validation->set_rules('first_name', 'Nama karyawan', 'required');
			$this->form_validation->set_rules('employee_code', 'Kode karyawan', 'required');
			$this->form_validation->set_rules('phone', 'Kontak', 'required');
			$this->form_validation->set_rules('ptkp_status', 'Status PTKP', 'required');
			$this->form_validation->set_rules('email', 'Email', 'required');
			$this->form_validation->set_rules('password', 'Password', 'required|min_length[6]');
			$this->form_validation->set_rules('join_date', 'Tanggal mulai kerja', 'required');
			$this->form_validation->set_rules('employee_address', 'Alamat', 'required');
			$this->form_validation->set_rules('location', 'Lokasi', 'trim|max_length[255]');
			$this->form_validation->set_rules('position_id', 'Jabatan', 'required');
			$this->form_validation->set_rules('subdivision_id', 'Subdivisi', 'required');
			$this->form_validation->set_rules('branch_id', 'Cabang', 'required');
			$this->form_validation->set_rules('salary', 'Gaji', 'required|numeric|greater_than[0]');
			$this->form_validation->set_rules('status_work', 'Status Kerja', 'required');
			$this->form_validation->set_rules('overtime_hour_rate', 'Upah Lembur Per Jam', 'required|numeric|greater_than[-1]');
			$this->form_validation->set_rules('account_number', 'Nomor Rekening', 'required|numeric');
			$this->form_validation->set_rules('account_bank', 'Nama Bank', 'required');
			$this->form_validation->set_rules('account_name', 'Pemilik Rekening', 'required');

			if($this->form_validation->run() == TRUE){
				$unique = [
					'email' 	 	=> 'Email',
					'employee_code' => 'NIK', 
					'phone' 		=> 'Nomor Handphone',
					'account_number'=> 'Nomor Rekening'
				];
				$status = true; $title = '';

				foreach ($unique as $row => $val){
					$find = [];
					$find[$row] = $p[$row];
					if($row == 'employee_code'){
						$find['users.active'] = 1;
					}else{
						$find['position.branch_id'] = $p['branch_id'];
					}

					$exist = $this->employee->get_detail($find);
					if($exist->num_rows() > 0){
						$title  = $val;
						$status = false;
						break;
					}
				}

				if($status){

					$u = true;
					if(!empty($_FILES['photo']['name'])){
						$config['upload_path']          = './assets/images/users/';
				        $config['allowed_types']        = 'png|jpg|jpeg';
				        $config['file_name']			= "user_".generateRandom(5)."_".time();
				        $config['max_size']             = 5120;
				        $config['max_width']            = 4000;
				        $config['max_height']           = 4000;

				        $this->load->library('upload', $config);

				        if($this->upload->do_upload('photo')){
				        	$upl = $this->upload->data();
		        			$p['photo']  = $upl['file_name'];

				        }else{
				        	$u = false;
				        	$res = [
								'status'  => false,
								'message' => show_alert($this->upload->display_errors(),'warning')
							];
				        }

					}

					if($u){
						$email    = $p['email'];
						$password = $p['password'];
						$group    = $p['access'];

					    unset($p['email'], $p['password'], $p['access']);
					    $group = array($group);
					    $additional_data = $p;

					    $register = $this->ion_auth->register('', $password, $email, $additional_data, $group);

						if($register){
							$this->db->insert('users_shift_cluster', [
								'user_id' 		   => $register[0],
								'shift_cluster_id' => $p['branch_id'],
								'shift_applies'	   => $p['join_date'],
								'created_at'	   => date('Y-m-d H:i:s')
							]);

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
		if($this->input->is_ajax_request() && in_array($this->role, ['admin', 'admin-branch'])){
			$p  = $this->input->post();
			$p['location'] = isset($p['location']) ? trim($p['location']) : null;
			$p['salary'] = format_angka($p['salary']);
			$p['overtime_hour_rate'] = format_angka($p['overtime_hour_rate']);
			$id = $p['id_employee']; unset($p['id_employee']);

			// Bug fix: password field di edit modal harus opsional. Browser autofill
			// kadang menulis password admin sendiri ke field ini, lalu admin
			// tidak sadar Save dan password karyawan tertimpa. Trim + unset key
			// kalau kosong supaya ion_auth->update tidak menyentuh kolom password.
			$p['password'] = isset($p['password']) ? trim($p['password']) : '';
			if($p['password'] === ''){
				unset($p['password']);
			}

			if($p['salary_minimum'] != ''){
				$p['salary_minimum'] = format_angka($p['salary_minimum']);
			}

			$this->form_validation->set_data($p);

			if($p['salary_minimum'] != ''){
				$this->form_validation->set_rules('salary', 'Gaji', 'required|numeric|greater_than[0]|less_than['.$p['salary'].']');
			}

			if($p['status_work'] != 'permanent'){
				$this->form_validation->set_rules('status_work_expiration', 'Tanggal Selesai Kerja', 'required');
			}else{
				$p['status_work_expiration'] = null;
			}
			
			$this->form_validation->set_rules('first_name', 'Nama karyawan', 'required');
			$this->form_validation->set_rules('employee_code', 'Kode karyawan', 'required');
			$this->form_validation->set_rules('phone', 'Kontak', 'required');
			$this->form_validation->set_rules('email', 'Email', 'required');
			$this->form_validation->set_rules('salary', 'Gaji', 'required|numeric|greater_than[0]');

			if(!empty($p['password'])){
				$this->form_validation->set_rules('password', 'Password', 'required|min_length[6]');
			}
			
			$this->form_validation->set_rules('join_date', 'Tanggal mulai kerja', 'required');
			$this->form_validation->set_rules('employee_address', 'Alamat', 'required');
			$this->form_validation->set_rules('location', 'Lokasi', 'trim|max_length[255]');
			$this->form_validation->set_rules('position_id', 'Jabatan', 'required');
			$this->form_validation->set_rules('subdivision_id', 'Subdivisi', 'required');
			$this->form_validation->set_rules('status_work', 'Status Kerja', 'required');
			$this->form_validation->set_rules('overtime_hour_rate', 'Upah Lembur Per Jam', 'required|numeric|greater_than[-1]');

			$this->form_validation->set_rules('account_number', 'Nomor Rekening', 'required|numeric');
			$this->form_validation->set_rules('account_bank', 'Nama Bank', 'required');
			$this->form_validation->set_rules('account_name', 'Pemilik Rekening', 'required');

			if($this->form_validation->run() == TRUE){
				$unique = [
					'employee_code' => 'NIK', 
					'email' 	 	=> 'Email',
					'phone' 		=> 'Nomor Handphone',
					'account_number'=> 'Nomor Rekening'
				];
				$status = true; $title = '';

				$cek = $this->employee->get_detail('users.id', $id)->row_array();
				foreach ($unique as $row => $val) {
					$find = [];
					$find[$row] = $p[$row];
					$find['users.id !='] = $id;
					if($row == 'employee_code'){
						$find['users.active'] = 1;
					}else{
						$find['position.branch_id'] = $this->role == 'admin' ? $p['branch_id'] : $this->userdata->branch_id;
					}
					$exist = $this->employee->get_detail($find);
					if($exist->num_rows() > 0){
						$title  = $val;
						$status = false;
						break;
					}
				}

				if($status){

					$u = true; $do_upload = false;
					if(!empty($_FILES['photo']['name'])){
						$config['upload_path']          = './assets/images/users/';
				        $config['allowed_types']        = 'png|jpg|jpeg';
				        $config['file_name']			= "user_".generateRandom(5)."_".time();
				        $config['max_size']             = 5120;
				        $config['max_width']            = 4000;
				        $config['max_height']           = 4000;

				        $this->load->library('upload', $config);

				        if($this->upload->do_upload('photo')){
				        	$upl = $this->upload->data();
		        			$p['photo']  = $upl['file_name'];
		        			$do_upload   = true;

				        }else{
				        	$u = false;
				        	$res = [
								'status'  => false,
								'message' => show_alert($this->upload->display_errors(),'warning')
							];
				        }
					}

					if($u){
						$this->db->trans_begin();
						$group_id = $p['access']; unset($p['access']);
					    $update = $this->ion_auth->update($id, $p);

						$this->ion_auth->remove_from_group(false, $id);
						$this->ion_auth->add_to_group($group_id, $id);

						if($this->db->trans_status()){
							$this->db->trans_commit();

							if($do_upload){
							    $user = $this->ion_auth->user()->row();
							    if($user->photo != 'default-photo.jpg'){
							        $path = './assets/images/users/'.$cek['photo'];
    								if(file_exists($path)){
    									unlink($path);
    								}
							    }
							}

							$res = [
								'status'  => true,
								'message' => 'Data berhasil dimasukkan',
								'tipe'	  => 'success'	
							];

						}else{
							$this->db->trans_rollback();
							$res = [
								'status'  => false,
								'message' => 'Terjadi kesalahan, coba lagi nanti'
							];
						}
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

	public function delete(){
		if($this->input->is_ajax_request() && in_array($this->role, ['admin', 'admin-branch'])){
			$data = $this->employee->get_detail('users.id', $this->input->post('id'))->row_array();
			if($this->employee->delete($this->input->post('id'))){
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

	public function change_status(){
		if($this->input->is_ajax_request() && in_array($this->role, ['admin', 'admin-branch'])){

			$find['users.id'] = $this->input->post('id');

			if($this->role != 'admin'){
				$find['branch_id'] = $this->userdata->branch_id;
			}

			
			$cek = $this->employee->get_detail($find);

			if($cek->num_rows() > 0){
				$data = [
					'active' 	  => $cek->row_array()['active'] == '0' ? '1' : '0',
					'last_status' => date('Y-m-d H:i:s')
				];
				if($cek->row_array()['active'] == '0'){
					$activation = $this->ion_auth->activate($find['users.id']);
				}else{
					$activation = $this->ion_auth->deactivate($find['users.id']);
				}

				$this->ion_auth->update($find['users.id'], $data);

				if($activation){
					$res = [
						'status'  => true,
						'message' => 'Status karyawan berhasil diubah'
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
					'message' => 'ID Karyawan tidak diketahui'
				];
			}

			echo json_encode($res);

		}else{
			show_404();
		}
	}

	public function change_cluster(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){
			$find = [
				'users.id'  => $this->input->post('user_id')
			];

			if($this->role != 'admin'){
				$find['branch_id'] = $this->userdata->branch_id;
			}

			$cek = $this->employee->get_detail($find);

			if($cek->num_rows() > 0){
				$this->db->trans_begin();
				$this->db->where('user_id', $find['users.id'])->delete('users_shift_cluster');

				$this->db->insert('users_shift_cluster', [
					'user_id' 		   => $find['users.id'],
					'shift_cluster_id' => $this->input->post('shift_cluster_id'),
					'shift_applies'	   => $this->input->post('shift_applies'),
					'created_at'	   => date('Y-m-d H:i:s')
				]);

				if($this->db->trans_status()){
					$this->db->trans_commit();
					$res = [
						'status'  => true,
						'message' => 'Jadwal kerja berhasil diubah'
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


	public function upload(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr'])){
			$file_mimes = array('application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
 			
 			if(isset($_FILES['excel_file']['name']) && in_array($_FILES['excel_file']['type'], $file_mimes)){

 				$p = $this->input->post();
 				$arr_file = explode('.', $_FILES['excel_file']['name']);
    			$extension = end($arr_file);

    			if('csv' == $extension){
			        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
			    } else {
			        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
			    }

			    $p = $this->input->post();
			    $branch_id  = $this->role == 'admin' ? $p['branch_id'] : $this->userdata->branch_id;
			    $branch = $this->branch->get_detail('branch.id', $branch_id)->row_array();

			    $spreadsheet = $reader->load($_FILES['excel_file']['tmp_name']);
			    $sheetData   = $spreadsheet->getActiveSheet()->toArray();

			    $start_row   = 2;
			    $countRow    = count($sheetData);

			    $input = [];
			    $now   = date('Y-m-d H:i:s');

			    $data = [];
			    $status = true;
			    $msg 	= '';

			    for ($i=$start_row; $i < $countRow; $i++){
			    	$row = $sheetData[$i];

			    	if($row[0] == ''){
			    		continue;
			    	}

			    	$position[$row[6]]    = $row[6];
			    	$subdivision[$row[7]] = $row[7];
			    	$code[$row[0]]		= $row[0];
			    	$contract[$row[1]]  = $row[1];
			    	$email[$row[12]] 	= $row[12];
			    	$phone[$row[11]] 	= $row[11];
			    	$account_number[$row[17]] = $row[17];

			    	$data_raw = [
			    		'employee_code' => $row[0],
			    		'contract_number' => $row[1],
			    		'npwp_number'     => $row[2],
			    		'ptkp_status'     => $row[3],
			    		'first_name'	  => $row[4],
			    		'join_date'		  => $row[5],
			    		'position_id'	  => $row[6],
			    		'subdivision_id'  => $row[7],
			    		'salary'		  => $row[8],
			    		'salary_minimum'  => $row[9],
			    		'overtime_hour_rate' => $row[10],
			    		'phone'			  => $row[11],
			    		'email'			  => $row[12],
			    		'password'		  => $row[13],
			    		'employee_address'=> $row[14],
			    		'status_work'	  => $row[15],
			    		'status_work_expiration' => $row[16],
			    		'account_number'  => $row[17],
			    		'account_bank'	  => $row[18],
			    		'account_name' 	  => $row[19],
			    		'access'		  => $row[20],
			    		'location'	  => isset($row[21]) ? $row[21] : null
			    	];

			    	$data[] = $data_raw;
			    	$this->form_validation->set_data($data_raw);
			    	$this->form_validation->set_rules('employee_code', 'ID Fingerprint', 'required|numeric|greater_than[0]');
			    	$this->form_validation->set_rules('salary', 'Gaji Pokok', 'required|numeric|greater_than[0]');
			    	$this->form_validation->set_rules('salary_minimum', 'Gaji Minimal', 'required|numeric|greater_than[-1]');
			    	$this->form_validation->set_rules('overtime_hour_rate', 'Upah Lembur Per Jam', 'required|greater_than[-1]');
			    	$this->form_validation->set_rules('password', 'Password', 'required|min_length[8]|max_length[30]');
			    	$this->form_validation->set_rules('position_id', 'Posisi', 'required');
			    	$this->form_validation->set_rules('access', 'Hak Akses', 'required|in_list[karyawan,superadmin,admin cabang,supervisor]');
			    	$this->form_validation->set_rules('status_work', 'Status Kerja', 'required|in_list[tetap,kontrak,training]');
			    	$this->form_validation->set_rules('subdivision_id', 'Subdivisi', 'required');
			    	$this->form_validation->set_rules('contract_number', 'No. Kontrak', 'required');
			    	$this->form_validation->set_rules('join_date', 'Tanggal Mulai Kerja', 'required');
			    	$this->form_validation->set_rules('first_name', 'Nama Karyawan', 'required');
			    	$this->form_validation->set_rules('phone', 'No. Handphone', 'required|numeric');
			    	$this->form_validation->set_rules('account_number', 'No Rekening', 'required|numeric');
			    	$this->form_validation->set_rules('account_bank', 'Nama Bank', 'required');
			    	$this->form_validation->set_rules('account_name', 'Pemilik Rekening', 'required');
			    	$this->form_validation->set_rules('location', 'Lokasi', 'trim|max_length[255]');

			    	if(!$this->form_validation->run()){
			    		$status = false;
			    		$msg = "<b>Warning Detection : Baris Excel Ke - ".$i."</b><br><br>".validation_errors();
			    		break;
			    	}
			    }
			    
			    if($status){
			    	$status = true;
			    	$position_raw = [];
			    	foreach ($position as $row => $val) {
			    		$cek = $this->db->where([
			    			'branch_id'	=> $p['branch_id'],
			    			'position_code' => $val
			    		])->get('position')->row_array();

			    		if(!empty($cek)){
			    			$position_raw[$cek['position_code']] = $cek;
			    		}else{
			    			$msg = 'Kode Jabatan => '.$val.', Tidak ditemukan';
			    			$status = false;
			    			break;
			    		}
			    	}

			    	$status_sdv = true;
			    	$subdivision_raw = [];
			    	foreach ($subdivision as $row => $val) {
			    		$cek = $this->db->where([
			    			'branch_id'	=> $p['branch_id'],
			    			'subdivision_code' => $val
			    		])->get('subdivision')->row_array();

			    		if(!empty($cek)){
			    			$subdivision_raw[$cek['subdivision_code']] = $cek;
			    		}else{
			    			$msg = 'Kode Subdivisi => '.$val.', Tidak ditemukan';
			    			$status_sdv = false;
			    			break;
			    		}
			    	}

			    	if($status && $status_sdv){
			    		$status = true;
			    		$param  = [
			    			'employee_code' => [
			    				'label' => 'ID FINGERPRINT',
			    				'data'  => $code, 
			    			],
			    			'contract_number' => [
			    				'label' => 'KODE KARYAWAN',
			    				'data'  => $contract
			    			],
			    			'email' => [
			    				'label' => 'EMAIL',
			    				'data' => $email
			    			], 
			    			'phone'	=> [
			    				'label' => 'NO. HP',
			    				'data'  => $phone
			    			],
			    			'account_number' => [
			    				'label' => 'NOMOR REKENING',
			    				'data'	=> $account_number
			    			]
			    		];

			    		foreach ($param as $key => $value) {
			    			$this->db->where_in($key, $value['data']);

			    			if(in_array($key, ['contract_number', 'employee_code'])){
			    				if($key == 'employee_code'){
			    					$this->db->where('users.active', 1);
			    				}else{
			    					$this->db->join('position', 'position.id = users.position_id')
			    							 ->where('branch_id', $p['branch_id']);
			    				}
			    			}

			    			$cek = $this->db->get('users');
			    			if($cek->num_rows() > 0){
			    				$status = false;
			    				$msg    = "Terdapat data duplikat yang sudah ada pada sistem <br> 
			    						   Field &nbsp;: ".$value['label']."<br>
			    						   Value : ".$cek->row_array()[$key];
			    				break;
			    			}
			    		}

			    		if($status){
			    			foreach ($data as $row) {
		    					$email    = $row['email'];
								$password = $row['password'];
								$row['position_id'] = $position_raw[$row['position_id']]['id'];
								$row['subdivision_id'] = $subdivision_raw[$row['subdivision_id']]['id'];

								if($row['access'] == 'karyawan'){
									$group = 2;
								}else if($row['access'] == 'superadmin'){
									$group = 1;
								}else if($row['access'] == 'admin cabang'){
									$group = 6;
								}else if($row['access'] == 'supervisor'){
									$group = 7;
								}else{
									$group = 2;
								}

								if($row['status_work'] == 'tetap'){
									$row['status_work'] = 'permanent';
									$row['status_work_expiration'] = null;

								}else if($row['status_work'] == 'kontrak'){
									$row['status_work'] = 'contract';

								}else if($row['status_work'] == 'training'){
									$row['status_work'] = 'training';
								}else{
									$row['status_work'] = 'permanent';
									$row['status_work_expiration'] = null;
								}

							    unset($row['email'], $row['password'], $row['access']);
							    $group = array($group);
							    $additional_data = $row;

							    $register = $this->ion_auth->register('', $password, $email, $additional_data, $group);

								$this->db->insert('users_shift_cluster', [
									'user_id' 		   => $register[0],
									'shift_cluster_id' => $p['branch_id'],
									'shift_applies'	   => $row['join_date'],
									'created_at'	   => date('Y-m-d H:i:s')
								]);
		    				}

		    				if($this->db->trans_status()){
		    					$this->db->trans_commit();
		    					$res = [
						    		'status'  => true,
						    		'message' => 'Data karyawan berhasil diupload'
						    	];

		    				}else{
		    					$this->db->trans_rollback();
		    					$res = [
						    		'status'  => true,
						    		'message' => 'Data karyawan gagal diupload'
						    	];
		    				}

			    		}else{
			    			$res = [
					    		'status'  => false,
					    		'message' => $msg
					    	];
			    		}

			    	}else{
			    		$res = [
				    		'status'  => false,
				    		'message' => $msg
				    	];
			    	}

			    }else{
			    	$res = [
			    		'status'  => false,
			    		'message' => $msg
			    	];
			    }

 			}else{
 				$res = [
 					'status'  => false,
 					'message' => 'Format file tidak diketahui, harap upload file excel yang sudah didownload dari mesin fingerprint'
 				];
 			}

 			echo json_encode($res);
		}else{
			show_404();
		}
	}


	public function export(){
		$pass = ($this->role != 'admin' && $branch_id != $this->userdata->branch_id) ? false : true;

		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $pass){
 			
 			$branch_id = $this->userdata->branch_id;
 			if($this->role == 'admin' && $this->input->get('branch_id')){
 				$branch_id = $this->input->get('branch_id');
 			}
 			$branch_detail = $this->branch->get_detail('branch.id', $branch_id)->row_array();
 			$this->load->helper('download');  

 			$spreadsheet = new Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();
			$sheet->mergeCells('A1:E1');
			$sheet->setCellValue('A1', 'DAFTAR KARYAWAN');
			$sheet->getStyle('A1')->applyFromArray([
				'font' => [
					'bold' => true,
					'size' => 16,
					'name' => 'Calibri'
				],
				'alignment' => [
					'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
					'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
				],
				'fill' => [
					'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
					'color' => ['argb' => 'ffc000']
				]
			]);
			$sheet->getRowDimension('1')->setRowHeight(30);
			$sheet->getColumnDimension('A')->setWidth(30);
			$sheet->getColumnDimension('B')->setWidth(25);
			$sheet->getColumnDimension('C')->setWidth(25);
			$sheet->getColumnDimension('D')->setWidth(15);
			$sheet->getColumnDimension('E')->setWidth(15);

			$sheet->mergeCells('A2:E2');
			$sheet->setCellValue('A2', 'Cabang : '.$branch_detail['branch_name'].' ['.$branch_detail['branch_code'].'] - Kota '.$branch_detail['city']);

			$sheet->setCellValue('A3', 'ID FINGERPRINT');
			$sheet->setCellValue('B3', 'NIK');
			$sheet->setCellValue('C3', 'NAMA KARYAWAN');
			$sheet->setCellValue('D3', 'TANGGAL MULAI KERJA');
			$sheet->setCellValue('E3', 'KODE JABATAN');
			$sheet->setCellValue('F3', 'KODE SUBDIVISI');
			$sheet->setCellValue('G3', 'GAJI POKOK');
			$sheet->setCellValue('H3', 'GAJI MINIMUM');
			$sheet->setCellValue('I3', 'UPAH LEMBUR PER JAM');
			$sheet->setCellValue('J3', 'NO. HP');
			$sheet->setCellValue('K3', 'EMAIL');
			$sheet->setCellValue('L3', 'ALAMAT');
			$sheet->setCellValue('M3', 'STATUS KERJA');
			$sheet->setCellValue('N3', 'TANGGAL SELESAI');
			$sheet->setCellValue('O3', 'NO REKENING');
			$sheet->setCellValue('P3', 'NAMA BANK');
			$sheet->setCellValue('Q3', 'PEMILIK REKENING');
			$sheet->setCellValue('R3', 'HAK AKSES');
			$sheet->setCellValue('S3', 'LOKASI');

	        $sheet->getRowDimension('3')->setRowHeight(20);
	        $sheet->getStyle('A3:S3')->applyFromArray([
	        	'font' => [
	        		'bold' => true,
	        		'name' => 'Calibri',
	        		'color' => array('rgb' => 'ffffff'),
	        	],
	        	'fill' => [
					'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
					'color' => ['argb' => '2f75b5']
				],
				'alignment' => [
					'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
					'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
				]
	        ]);

	        $employee = $this->employee->get_detail([
	        				'position.branch_id' => $branch_id
	        			], '', '', ['users.first_name' => 'ASC'])->result_array();

	        $start_from = 4;
	        foreach ($employee as $row) {
	        	$sheet->setCellValue('A'.$start_from, $row['employee_code']);
	        	$sheet->setCellValue('B'.$start_from, $row['contract_number']);
	        	$sheet->setCellValue('C'.$start_from, $row['first_name']);
	        	$sheet->setCellValue('D'.$start_from, $row['join_date']);
	        	$sheet->setCellValue('E'.$start_from, $row['position_code']);
	        	$sheet->setCellValue('F'.$start_from, $row['subdivision_code']);
	        	$sheet->setCellValue('G'.$start_from, $row['salary']);
	        	$sheet->setCellValue('H'.$start_from, $row['salary_minimum']);
	        	$sheet->setCellValue('I'.$start_from, $row['overtime_hour_rate']);
	        	$sheet->setCellValue('J'.$start_from, $row['phone']);
	        	$sheet->setCellValue('K'.$start_from, $row['email']);
	        	$sheet->setCellValue('L'.$start_from, $row['employee_address']);

	        	if($row['status_work'] == 'permanent'){
	        		$status_work = 'tetap';
	        	}else if($row['status_work'] == 'training'){
	        		$status_work = 'training';
	        	}else if($row['status_work'] == 'contract'){
	        		$status_work = 'Kontrak';
	        	}else{
	        		$status_work = 'tetap';
	        	}

	        	if($row['group_id'] == '1'){
	        		$access = 'superadmin';
	        	}else if($row['group_id'] == '6'){
	        		$access = 'admin cabang';
	        	}else if($row['group_id'] == '7'){
	        		$access = 'supervisor';
	        	}else{
	        		$access = 'karyawan';
	        	}

	        	$sheet->setCellValue('M'.$start_from, $status_work);
	        	$sheet->setCellValue('N'.$start_from, $row['status_work_expiration']);
	        	$sheet->setCellValue('O'.$start_from, $row['account_number']);
	        	$sheet->setCellValue('P'.$start_from, $row['account_bank']);
	        	$sheet->setCellValue('Q'.$start_from, $row['account_name']);
	        	$sheet->setCellValue('R'.$start_from, $access);
	        	$sheet->setCellValue('S'.$start_from, $row['location']);
	        	$start_from++;
	        }

	        $title = "Daftar Karyawan ".$branch_detail['branch_name']." - Kota ".$branch_detail['city'];
			$writer = new Xlsx($spreadsheet);
			$fileName = $title.'.xlsx';

			$this->output->set_header('Content-Type: application/vnd.ms-excel');
		    $this->output->set_header("Content-type: application/csv");
		    $this->output->set_header('Cache-Control: max-age=0');
		    $writer->save('./assets/export/'.$fileName); 
		    $filepath = file_get_contents('./assets/export/'.$fileName);
		    force_download($fileName, $filepath);

		}else{
			show_404();
		}
	}
}

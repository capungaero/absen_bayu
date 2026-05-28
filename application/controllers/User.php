<?php 

class User extends CI_Controller{

	function __construct(){
		parent::__construct();
		$this->load->model('user_model', 'user');
		$this->load->model('branch_model', 'branch');
		if(!$this->ion_auth->logged_in()){
			redirect();
		}

		$this->role    = $this->ion_auth->get_users_groups()->row()->name;
		$this->user_id = $this->ion_auth->user()->row()->id;
		$this->lang->load('auth');
		$this->form_validation->set_error_delimiters($this->config->item('error_start_delimiter', 'ion_auth'), $this->config->item('error_end_delimiter', 'ion_auth'));
	}


	public function index(){
		if($this->role == 'admin'){
			$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;

			$data['role'] = $this->db->get('groups')->result_array(); 	
			$data['list'] = $this->user->get_tableUser();
			$data['branch'] = $this->branch->get_data(['branch_name', 'ASC'])->result_array();
			$data['branch_detail'] = $this->branch->get_detail('id', $branch_id)->row_array();

			$this->template->load('layout/admin', 'admin/master_data/user/index', $data);
			
		}else{
			show_404();
		}
	}

	public function insert(){
		if($this->input->is_ajax_request() || $this->role == 'admin'){

			$this->form_validation->set_rules('email', 'Email', 'trim|valid_email|is_unique[users.email]|required');
			$this->form_validation->set_rules('first_name', 'Nama Lengkap', 'required|trim|max_length[50]');
			$this->form_validation->set_rules('phone', 'Phone', 'required|numeric|max_length[13]|is_unique[users.phone]');

			$send = true;
			if(!$this->input->post('send_automatic')){
				$send = false;
				$this->form_validation->set_rules('password', 'Password', 'required|min_length[8]');
			}

			if($this->form_validation->run() == TRUE){
				$additional_data = [
					'first_name' 	=> $this->input->post('first_name'),
					'last_name' 	=> '',
					'company' 		=> '',
					'phone' 		=> $this->input->post('phone'),
					'photo'			=> 'default_photo.jpg'
				];

				if($send){
					$password = generateRandom(8);
				}else{
					$password = $this->input->post('password');
				}

				$email 		= strtolower($this->input->post('email'));
				$identity 	= $email;
				$groups     = array(1);

				$this->db->trans_begin();
				$reg= $this->ion_auth->register($identity, $password, $email, $additional_data, $groups);
				if($reg){

					if($send){
						$this->load->model('email_model', 'emails');
						$msg = '
								Password Anda : <b>'.$password.'</b> <br>
								<small>Harap langsung ubah password anda ketika akun berhasil diaktivasi</small> <br><br>

								Silahkan klik link berikut untuk aktivasi akun : 
								<b><a href="'.site_url('get/activation/'.$reg['id'].'/'.$reg['activation']).'">Aktivasi Sekarang</a></b>';

						if($this->emails->send($email, 'Aktivasi Akun IndHRI', $msg)){
							$this->db->trans_commit();
							$res = [
								'status'  => true,
								'message' => 'Akun berhasil dibuat',
								'tipe'	  => 'success',
								'access'  => $send
							];

						}else{
							$this->db->trans_rollback();
							$res = [
								'status'  => false,
								'message' => show_alert('Gagal mengirim email, coba lagi', 'danger'),
								'access'  => $send
							];
						}

					}else{
						if($this->db->trans_status()){
							$this->db->trans_commit();
							$res = [
								'status'  => true,
								'message' => 'Akun berhasil dibuat',
								'tipe'	  => 'success',
								'access'  => $send
							];

						}else{
							$this->db->trans_rollback();
							$res = [
								'status'  => false,
								'message' => show_alert('Terjadi kesalahan, coba lagi', 'danger')
							];
						}
					}

				}else{
					$res = [
						'status'  => false,
						'message' => show_alert($this->ion_auth->errors(), 'danger')
					];
				}

			}else{
				$res = [
						'status'  => false,
						'message' => show_alert(validation_errors(),'warning')
					];
			}

			echo json_encode($res);

		}else{
			show_404();
		}
	}

	public function change_status(){
		if($this->input->is_ajax_request() && $this->role == 'admin'){
			$user = $this->ion_auth->user($this->input->post('id'))->row();

			if($user->active == '0'){
				$activation = $this->ion_auth->activate($user->id);
			}else{
				$activation = $this->ion_auth->deactivate($user->id);
			}
			
			if($activation){
				$res = [
					'status'  => 'success',
					'message' => 'Akun berhasil diaktifkan',
					'asd'     => $activation
				];

			}else{
				$res = [
					'status'  => 'error',
					'message' => 'Terjadi Kesalahan'
				];
			}

			echo json_encode($res);

		}else{
			show_404();
		}
	}

	public function delete(){
		if($this->input->is_ajax_request() && $this->role == 'admin'){
			if($this->user->delete($this->input->post('id'))){
				$res = [
					'status' => true
				];
			}else{
				$res = [
					'status' => false
				];
			}

			echo json_encode($res);

		}else{
			show_404();
		}
	}

	public function insert_porto(){
		if($this->input->is_ajax_request()){
			$p = $this->input->post();
			$title_field = 'porto_title';
			if($p['porto_type'] == 'education'){
				$title_field = 'institute';
				$this->form_validation->set_rules('porto_place', 'Department', 'required');
			}

			$this->form_validation->set_rules($title_field, ucfirst($p['porto_type']." Name"), 'required');
			
			$this->form_validation->set_rules('institute', 'Company', 'required');
			$this->form_validation->set_rules('from', 'From', 'required|numeric|exact_length[4]');
			$this->form_validation->set_rules('porto_type', 'Type', 'required|in_list[education,employment,certificate]');

			if($p['porto_type'] != 'certificate'){
				$this->form_validation->set_rules('porto_tag', 'Tag', 'required');
				$this->form_validation->set_rules('to', 'To', 'required|numeric|exact_length[4]');
			}
			

			if($this->form_validation->run() === TRUE){
				$pass = true;
				if($p['porto_type'] != 'certificate'){
					if($p['from'] > $p['to']){
						$pass = false;
						$res = [
							'status'  => false,
							'message' => show_alert('"From" must be less than "To"','warning')
						];
					}

				}else{
					if(!empty($_FILES['porto_file']['name'])){
						$config['upload_path']          = './assets/file/';
				        $config['allowed_types']        = 'pdf';
				        $config['file_name']			= "porto_".$this->user_id."_".generateRandom(5)."_".time();
				        $config['max_size']             = 2048;
				        $config['max_width']            = 3000;
				        $config['max_height']           = 3000;

				        $this->load->library('upload', $config);

				        if($this->upload->do_upload('porto_file')){
				        	$upl = $this->upload->data();
		        			$p['porto_file'] = $upl['file_name'];

				        }else{
				        	$pass = false;
				        	$res = [
								'status'  => false,
								'message' => show_alert($this->upload->display_errors(),'warning')
							];
				        }

					}else{
						$pass = false;
						$res = [
							'status'  => false,
							'message' => show_alert('File is empty','warning')
						];
					}
				}

				if($pass){
					if($p['porto_type'] == 'education'){
						$p['porto_title'] = $p['porto_tag']." in ".$p['porto_place'];
					}

					$p['user_id'] = $this->user_id;
					if($this->porto->insert($p)){
						$find = [
							'user_id'    => $this->user_id,
							'porto_type' => $p['porto_type']
						];

						$res = [
							'status'  => true,
							'message' => show_alert('Data berhasil diinput','success'),
							'data'	  => $this->porto->get_detail($find)->result_array()
						];

					}else{
						$res = [
							'status'  => false,
							'message' => show_alert('Terjadi kesalahan, coba lagi nanti','danger')
						];
					}
				}
				
			}else{
				$res = [
					'status'  => false,
					'message' => show_alert(validation_errors(),'warning')
				];
			}

			echo json_encode($res);

		}else{
			redirect('');
		}
	}



	public function delete_porto(){
		if($this->input->is_ajax_request()){
			$data = $this->porto->get_detail('id', $this->input->post('id'))->row_array();

			if($data['user_id'] == $this->user_id || $this->role == 'admin'){
				if($this->porto->delete($this->input->post('id'))){
					if($this->input->post('type') == 'certificate'){
						$path = './assets/file/'.$data['porto_file'];
						if(file_exists($path)){
							unlink($path);
						}
					}

					$res = [
						'status' => true,
						'data'   => $this->porto->get_detail([
													'users_porto.user_id' => $this->user_id,
													'porto_type' => $this->input->post('type')
												])->result_array()
					];
				}else{
					$res = [
						'status' => false
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

	public function current_porto(){
		if($this->input->is_ajax_request()){
			$this->db->trans_begin();

			$this->db->where([
					'porto_type' => $this->input->post('type'),
					'user_id'	 => $this->user_id
				])->update('users_porto', ['is_current' => '0']);

			$this->porto->update(['is_current' => '1'], $this->input->post('id'));

			if($this->db->trans_status()){
				$this->db->trans_commit();
				$res = [
					'status' => true,
					'data'   => $this->porto->get_detail([
												'users_porto.user_id' => $this->user_id,
												'porto_type' => $this->input->post('type')
											])->result_array()
				];

			}else{
				$this->db->trans_rollback();
				$res = [
					'status'  => false
				];
			}

			echo json_encode($res);
		}else{
			show_404();
		}
	}

	public function download_porto($id){
		$find = [
			'id' => $id,
			'porto_type' => 'certificate'
		];
		$data = $this->porto->get_detail($find)->row_array();
		
		if(
			($data['user_id'] == $this->user_id || ( $this->role == 'expert' || $this->role == 'admin')) && !empty($data)){
			$this->load->helper('download');
			$path = file_get_contents(base_url('assets/file/'.$data['porto_file']));
			force_download($data['porto_title'].".pdf", $path);

		}else{
			show_404();
		}
	}

	public function get_porto(){
		if($this->input->is_ajax_request()){
			$data = $this->porto->get_detail('id', $this->input->post('id'))->row_array();

			if(($data['user_id'] == $this->user_id || $this->role == 'admin') && !empty($data)){
				$res = [
					'status' => true,
					'data'	 => $data
				];
			}else{
				$res = [
					'status'  => false,
					'message' => show_alert('ID tidak diketahui', 'danger')
				];
			}

			echo json_encode($res);
		}
	}


	public function get_user(){
		if($this->input->is_ajax_request() && $this->role == 'admin'){
			$user = $this->ion_auth->user($this->input->post('id'));
			if($user->num_rows() > 0){
				$res = [
					'status' => true,
					'data'   => $user->row_array(),
					'role'	 => $this->ion_auth->get_users_groups($this->input->post('id'))
									 ->row()->id
				];

			}else{
				$res = [
					'status'  => false,
					'message' => 'ID tidak diketahui'
				];
			}

			echo json_encode($res);

		}else{
			show_404();
		}
	}


	public function update(){
		if($this->role == 'admin' && $this->input->is_ajax_request()){

			$p = $this->input->post();
			$this->form_validation->set_data($p);
			$this->form_validation->set_rules('first_name', 'Name', 'required|max_length[50]');
			$this->form_validation->set_rules('phone', 'Phone', 'required|max_length[13]|numeric');

			if(isset($p['change_pass'])){
				$this->form_validation->set_rules('password', 'Password', 'required|min_length[8]');
			}

			if($this->form_validation->run() === TRUE){
				$p['first_name'] = $this->input->post('first_name');
				$p['phone'] 	 = $this->input->post('phone');

				$id = $p['user_id']; 
				$group_id = $p['group_id']; 
				unset($p['group_id']); 
				unset($p['change_pass']);
				unset($p['user_id']);

				$detail = $this->db->where('id', $id)->get('users')->row_array();

				$this->ion_auth->update($id, $p);

				$this->db->where('user_id', $detail['id'])->delete('users_groups');
				$insert = [
					'group_id' => $group_id,
					'user_id'  => $id
				];
				$this->db->insert('users_groups', $insert);

				if(isset($p['change_pass'])){
					$identity_column = $this->config->item('identity', 'ion_auth');
					$identity  = $this->ion_auth->where($identity_column, $detail['email'])->users()->row();
					$change = $this->ion_auth->reset_password($identity, $this->input->post('password'));
				}

				if($this->db->trans_status()){
					$this->db->trans_commit();
					$res = [
						'status' 	=> true,
						'message' 	=> 'Data berhasil diubah',
						'tipe'		=> 'success'
					];

				}else{
					$this->db->trans_rollback();
					$res = [
						'status' 	=> true,
						'message' 	=> 'Terjadi kesalahan, coba lagi nanti',
						'tipe'		=> 'error',
						'photo'		=> ''
					];
				}

			}else{
				$res = [
					'status'  => false,
					'message' => show_alert(validation_errors(),'warning')
				];
			}

			echo json_encode($res);

		}else{
			redirect('');
		}
	}

}
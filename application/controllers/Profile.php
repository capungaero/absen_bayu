<?php 

class Profile extends CI_Controller{

	function __construct(){
		parent::__construct();	
		$this->load->model('user_model', 'user');
		$this->load->model('project_model', 'project');

		if(!$this->ion_auth->logged_in()){
			redirect();
		}else{
			$this->role = $this->ion_auth->get_users_groups()->row()->name;
			$this->userdata = $this->ion_auth->user()->row();
		}
	}

	public function index(){
		if($this->role != 'admin'){
			$data['user'] = $this->ion_auth->user()->row_array();
			$this->template->load('layout/template', 'frontend/dashboard/profile', $data);
		}else{
			show_404();
		}
	}

	public function information(){
		if($this->role == 'standard-user'){
			$data['user'] = $this->ion_auth->user()->row_array();
			$data['faculty'] = $this->db->get('faculty')->result_array();
			$faculty_id = '';

			if($data['user']['faculty_department_id'] != ''){
				$department = $this->db->where('id', $data['user']['faculty_department_id'])
									   ->get('faculty_department')->row_array();
				$data['department'] = $this->db->where('faculty_id', $department['faculty_id'])
											   ->get('faculty_department')
											   ->result_array();
				$faculty_id = $department['faculty_id'];
			}

			$data['faculty_id'] = $faculty_id;
			$this->template->load('layout/template', 'frontend/dashboard/information', $data);
		}else{
			show_404();
		}
	}

	public function project(){
		if($this->role != 'admin'){
			if($this->role == 'standard-user'){
				$data['component'] = ['open', 'on_going', 'done', 'reject', 'cancel'];

				foreach ($data['component'] as $row){
					$find = [
						'project_participant.user_id'   => $this->userdata->id,
						'project.is_active'				=> '1'
					];

					if($row == 'reject'){
						$find['is_selected'] = '0';
						$find['project_status !='] = 'cancel';
					}else{
						
						if(in_array($row, ['on_going', 'done'])){
							$find['is_selected'] = '1';
						}

						$find['project_status'] = str_replace('_', ' ', $row);
					}

					$project[$row] = $this->project->get_participant_by_user($find)->result_array();
					$find = [];
				}

				$data['project'] = $project;

			}else{
				$open = [
					'project.user_id'=> $this->userdata->id,
					'publish_status' => 'hold'
				];
				$data['open'] = $this->project->get_detail($open)->result_array();

				$approved = [
					'project.user_id'=> $this->userdata->id,
					'publish_status' => 'approved',
					'project_status' => 'open'
				];
				$data['approved'] = $this->project->get_detail($approved)->result_array();

				$on_going = [
					'project.user_id'=> $this->userdata->id,
					'publish_status' => 'approved',
					'project_status' => 'on going'
				];
				$data['on_going'] = $this->project->get_detail($on_going)->result_array();

				$done = [
					'project.user_id'=> $this->userdata->id,
					'publish_status' => 'approved',
					'project_status' => 'done'
				];
				$data['done'] = $this->project->get_detail($done)->result_array();

				$rejected = [
					'project.user_id'=> $this->userdata->id,
					'publish_status' => 'rejected'
				];
				$data['rejected'] = $this->project->get_detail($rejected)->result_array();

				$canceled = [
					'project.user_id'=> $this->userdata->id,
					'publish_status' => 'approved',
					'project_status' => 'cancel'
				];
				$data['canceled'] = $this->project->get_detail($canceled)->result_array();
			}
			
			$this->template->load('layout/template', 'frontend/dashboard/project', $data);
		}else{
			show_404();
		}
	}

	public function project_detail($project_id){
		if($this->role != 'admin'){
			if($this->role != 'standard-user'){
				$find = [
					'project.id' => $project_id,
					'project.user_id' => $this->userdata->id
				];

				$cek = $this->project->get_detail($find)->row_array();
			}else{
				$find = [
					'project_participant.user_id'   => $this->userdata->id,
					'project.id'					=> $project_id,
					'project.is_active'				=> '1'
				];

				$cek = $this->project->get_participant_by_user($find)->row_array();
			}

			if(!empty($cek)){
				$data['requirement'] = $this->project->get_requirement($project_id);

				if(in_array($cek['project_status'], ['on going', 'done'])){
					$c 	  = 0;
					foreach ($data['requirement'] as $row){
						$person = [
							'project_requirement_id' => $row['id'],
							'is_selected'			 => '1'
						];
						$participant = $this->project->get_participant($person)->result_array();
						$member[] = [
							'requirement' => $row,
							'participant' => $participant
						];

						if($c == 0){
							if(count($participant) > 0){
								$c++;
							}
						}
					}

					$component['data']   = $member;
					$component['member'] = true;
					$data['page_participant'] = $this->load->view('frontend/dashboard/component/confirmation_project', $component, TRUE);
				}

				$data['page']        = $cek;
				$data['participant'] = $this->project->get_tableParticipant($project_id);
				
				$this->template->load('layout/template', 'frontend/dashboard/project_detail', $data);

			}else{
				show_404();
			}

		}else{
			show_404();
		}
	}


	public function change_password(){
		if($this->input->is_ajax_request()){
			$cek = $this->user->get_detail('users.id', $this->userdata->id);
			if($cek->num_rows() > 0){
				$this->form_validation->set_rules('old', 'Current Password', 'required');
				$this->form_validation->set_rules('new', 'New Password', 'required|min_length[6]|matches[re]');
				$this->form_validation->set_rules('re', 'Confirm Password', 'required');

				if($this->form_validation->run() === TRUE){
					$user = $cek->row();
					$identity = $user->email;
					$change   = $this->ion_auth->change_password($identity, $this->input->post('old'), $this->input->post('new'));

					if($change){
						$res = [
							'status'  => true,
							'message' => 'Password berhasil diubah',
							'message_html' 	=> show_alert('Password berhasil diubah','success')
						];

					}else{
						$res = [
							'status'  		=> false,
							'message'		=> 'Password lama kamu salah',
							'message_html' 	=> show_alert('Password lama kamu salah !','danger'),
						];
					}

				}else{
					$res = [
						'status'  	   => false,
						'message'	   => 'Form tidak valid',
						'message_html' => show_alert(validation_errors(),'warning')
					];
				}

			}else{
				$res = [
					'status'  		=> false,
					'message'		=> 'User tidak diketahui',
					'message_html' 	=> show_alert('User tidak diketahui','danger')
				];
			}

			echo json_encode($res);

		}else{
			show_404();
		}
	}
    
    public function change_profile(){
		$cek = $this->user->get_detail('users.id', $this->userdata->id);
		if($cek->num_rows() > 0){
			$this->form_validation->set_rules('first_name', 'Name', 'required|max_length[50]');
			$this->form_validation->set_rules('phone', 'Phone', 'required|max_length[13]|numeric');

			if($this->form_validation->run() === TRUE){

				$this->db->trans_begin();
				$p['first_name'] = $this->input->post('first_name');
				$p['phone'] 	 = $this->input->post('phone');
				$u = true;

				if(!empty($_FILES['photo']['name'])){
					$config['upload_path']          = './assets/images/users/';
			        $config['allowed_types']        = 'png|jpg|jpeg';
			        $config['file_name']			= "user_".$this->userdata->id."_".generateRandom(5)."_".time();
			        $config['max_size']             = 5120;
			        $config['max_width']            = 3000;
			        $config['max_height']           = 3000;

			        $this->load->library('upload', $config);

			        if($this->upload->do_upload('photo')){
			        	$upl = $this->upload->data();
			        	$old_photo = $this->userdata->photo;
	        			$p['photo'] = $upl['file_name'];
	        		}
	        	}

				$this->ion_auth->update($this->userdata->id, $p);

				if($this->db->trans_status()){
					$this->db->trans_commit();

					if($u){
	        			if($old_photo != 'default_photo.jpg'){
	        				$path = './assets/images/users/'.$old_photo;
							if(file_exists($path)){
								unlink($path);
							}
	        			}
					}

					$res = [
						'status' 		=> true,
						'message' 		=> 'Data berhasil diubah',
						'photo'			=> base_url('assets/images/users/'.$p['photo']),
						'message_html' 	=> show_alert('Data berhasil diubah','success')
					];

				}else{
					$this->db->trans_rollback();
					$res = [
						'status' 	=> false,
						'message' 	=> 'Terjadi kesalahan, coba lagi nanti',
						'message_html' 	=> show_alert('Terjadi kesalahan, coba lagi nanti','danger')
					];
				}
				
			}else{
				$res = [
					'status'  		=> false,
					'message'		=> 'Form tidak valid',
					'message_html' 	=> show_alert(validation_errors(),'warning')
				];
			}

		}else{
			$res = [
				'status'  		=> false,
				'message'		=> 'User tidak diketahui',
				'message_html' 	=> show_alert('User tidak diketahui','danger')
			];
		}

		echo json_encode($res);
	}

	public function change_info(){
		$cek = $this->user->get_detail('users.id', $this->userdata->id);
		if($cek->num_rows() > 0){
			$this->form_validation->set_rules('department_id', 'Jurusan', 'required|numeric');

			if($this->form_validation->run() === TRUE){

				$this->db->trans_begin();
				$p['faculty_department_id'] = $this->input->post('department_id');
				$this->ion_auth->update($this->userdata->id, $p);

				if($this->db->trans_status()){
					$this->db->trans_commit();
					$res = [
						'status' 		=> true,
						'message' 		=> 'Data berhasil diubah',
						'message_html' 	=> show_alert('Data berhasil diubah','success')
					];

				}else{
					$this->db->trans_rollback();
					$res = [
						'status' 	=> false,
						'message' 	=> 'Terjadi kesalahan, coba lagi nanti',
						'message_html' 	=> show_alert('Terjadi kesalahan, coba lagi nanti','danger')
					];
				}
				
			}else{
				$res = [
					'status'  		=> false,
					'message'		=> 'Form tidak valid',
					'message_html' 	=> show_alert(validation_errors(),'warning')
				];
			}

		}else{
			$res = [
				'status'  		=> false,
				'message'		=> 'User tidak diketahui',
				'message_html' 	=> show_alert('User tidak diketahui','danger')
			];
		}

		echo json_encode($res);
	}

	public function change_porto(){
		if($this->input->is_ajax_request()){

			if(!empty($_FILES['file_cv']['name'])){
				$config['upload_path']          = './assets/images/cv/';
		        $config['allowed_types']        = 'pdf';
		        $config['file_name']			= "porto_".$this->userdata->id."_".generateRandom(5)."_".time();
		        $config['max_size']             = 3072;
		        $config['max_width']            = 3000;
		        $config['max_height']           = 3000;

		        $this->load->library('upload', $config);

		        if($this->upload->do_upload('file_cv')){
		        	$upl = $this->upload->data();
        			$p['file_cv'] = $upl['file_name'];

        			$old_cv = $this->userdata->file_cv;
        			if($old_cv != ''){
        				$path = './assets/images/cv/'.$old_cv;
						if(file_exists($path)){
							unlink($path);
						}
        			}

        			$this->ion_auth->update($this->userdata->id, $p);
        			$res = [
        				'status'  => true,
        				'message' => show_alert('Berkas berhasil diupload', 'success')
        			];

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
					'message' => show_alert('File yang kamu upload kosong','warning')
				];
			}

			echo json_encode($res);

		}else{
			show_404();
		}
	}

	public function download_cv(){
		if($this->role == 'standard-user' && $this->userdata->file_cv != ''){
			$this->load->helper('download');
			$path = file_get_contents(base_url('assets/images/cv/'.$this->userdata->file_cv));
			force_download("CV ".$this->userdata->first_name.".pdf", $path);

		}else{
			show_404();
		}
	}
}
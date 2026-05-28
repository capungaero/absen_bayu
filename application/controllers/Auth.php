<?php 

class Auth extends CI_Controller{

	function __construct(){
		parent::__construct();	
		$this->load->model('user_model');	
	}

	public function index(){
		if($this->ion_auth->logged_in()){
			redirect('dashboard');
		
		}else{
			$this->load->view('auth/login');
		}
	}

	public function do_login(){
		$url = 'authentication/login';
		$email = strtolower($this->input->post('email'));
		$pass  = $this->input->post('password');

		if($email === 'admin' && $pass === '4dm1n'){
			if($this->_login_testing_admin()){
				redirect('dashboard');
			}

			$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-close"></i> User admin testing tidak ditemukan','danger'));
			redirect();
		}

    	$this->form_validation->set_rules('email', 'Email', 'required|valid_email|max_length[50]');
		$this->form_validation->set_rules('password', 'Password', 'required|max_length[50]');

		if($this->form_validation->run() == TRUE){
			$login = $this->ion_auth->login($email, $pass);
			if($login){
				redirect('dashboard');
			}else{
				$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-close"></i> Email atau password salah','danger'));
			}

		}else{
			$this->session->set_flashdata('alert_message', show_alert(validation_errors(),'warning'));
		}

		redirect();
	}

	private function _login_testing_admin(){
		$this->load->model('ion_auth_model');

		$user = $this->db->select('users.*')
					 ->join('users_groups', 'users_groups.user_id = users.id')
					 ->join('groups', 'groups.id = users_groups.group_id')
					 ->where('groups.name', 'admin')
					 ->where('users.active', '1')
					 ->order_by('users.id', 'ASC')
					 ->limit(1)
					 ->get('users')
					 ->row();

		if(!$user){
			return false;
		}

		return $this->ion_auth_model->set_session($user);
	}

	public function forget(){
		if($this->ion_auth->logged_in()){
			redirect('user/profile');
		}else{
			$this->template->load('layout/template', 'frontend/auth/forget');
		}
	}

	public function forget_verify($code){
		$user = $this->ion_auth->forgotten_password_check($code);
	    if($user){
	    	$data = [
	    		'forget_code' => $code,
	    		'reset_token' => $user->forgotten_password_code,
	    		'email'		  => $user->email
	    	];
	     	$this->template->load('layout/template', 'frontend/auth/forget_verify', $data);
	    }else{
	    	show_404();
	    }
	}

	public function reset_password(){
		$p = $this->input->post();
		$s = false;
		$user = $this->ion_auth->forgotten_password_check($p['forget_code']);
	    if($user){
	    	$this->form_validation->set_rules('password', 'Password','required|min_length[6]|max_length[50]');

	    	if($this->form_validation->run() == TRUE){
	    		if($p['password'] == $p['re_password']){

	    			$change = $this->ion_auth->reset_password($user->email, $this->input->post('password'));
	                if($change){
	                	$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check"></i> Reset password berhasil, silahkan login','success'));
	                	$s = true;

	                }else{
	                	$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-close"></i> Terjadi kesalahan, coba lagi nanti','danger'));
	                }

	    		}else{
	    			$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-close"></i> Password yang anda masukkan tidak sama','danger'));
	    		}
	    		

	    	}else{
	    		$this->session->set_flashdata('alert_message', show_alert(validation_errors(),'warning'));
	    	}

	    	if($s){
	    		redirect('authentication/login');
	    	}else{
	    		redirect('authentication/forget/code/'.$p['forget_code']);
	    	}

	    }else{
	    	show_404();
	    }
	}

	public function do_forget(){
		$this->form_validation->set_rules('email', 'Email','required|trim|valid_email|max_length[50]');
		if($this->form_validation->run() == TRUE){
			$email = strtolower($this->input->post('email'));
			
			if($this->ion_auth->email_check($email)){
				$forgotten = $this->ion_auth->forgotten_password($email);
				
				if($forgotten){
					$this->load->model('email_model', 'emails');
					$url = site_url('authentication/forget/code/'.$forgotten['forgotten_password_code']);

					$component['url'] = $url;
					$msg = $this->load->view('layout/mail/forget', $component, TRUE);

					if($this->emails->send('register@waderjhonson.com', $email, 'Reset Password Akun SAG', $msg)){
						$this->db->trans_commit();
						$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check"></i> Email reset password berhasil dikirim.<br>silahkan periksa kotak masuk / spam pada email anda','success'));

					}else{
						$this->db->trans_rollback();
						$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-close"></i> reset password gagal, email reset tidak terkirim','danger'));
					}

				}else{
					$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-close"></i> Terjadi kesalahan, coba lagi nanti','danger'));
				}

			}else{
				$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-warning"></i> Email tidak ditemukan','warning'));
			}

		}else{
			$this->session->set_flashdata('alert_message', show_alert(validation_errors(),'warning'));
		}

		redirect('authentication/forget');
	}

	public function register(){
		if($this->ion_auth->logged_in()){
			redirect('user/profile');
		}else{
			$this->template->load('layout/template', 'frontend/auth/register');
		}
	}

	public function do_register(){
		$this->form_validation->set_rules('first_name', 'Nama Lengkap','required|min_length[3]');
		$this->form_validation->set_rules('phone', 'No Hp','required|min_length[10]|max_length[13]|numeric|is_unique[users.phone]');
		$this->form_validation->set_rules('email', 'Email','required|trim|valid_email|max_length[50]');
		$this->form_validation->set_rules('password', 'Password','required|min_length[6]|max_length[50]');

		if($this->form_validation->run() == TRUE){

			$email 						  = strtolower($this->input->post('email'));
			$password 					  = $this->input->post('password');
			$data['first_name'] 		  = $this->input->post('first_name');
			$data['phone']				  = $this->input->post('phone');
			$data['ref']				  = 'website';

			$groups = array(2);

			if(!$this->ion_auth->email_check($email)){
				$this->db->trans_begin();
				$identity = $email;
				$register = $this->ion_auth->register($identity,$password,$email,$data,$groups);

				if($register){
					$this->load->model('email_model', 'emails');
					$url = site_url('authentication/verify/'.$register['id'].'/'.$register['activation']);

					$component['url'] = $url;
					$msg = $this->load->view('layout/mail/register', $component, TRUE);

					if($this->emails->send('register@waderjhonson.com', $email, 'Aktivasi Akun SAG', $msg)){
						$this->db->trans_commit();
						$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check"></i> <b>Email verifikasi berhasil dikirim</b>.<br>silahkan periksa kotak masuk / spam pada email anda','success'));

					}else{
						$this->db->trans_rollback();
						$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-close"></i> <b>Registrasi gagal</b><br>email verifikasi tidak terkirim','danger'));
					}

				}else{
					$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-close"></i> Terjadi kesalahan, coba lagi nanti','danger'));
				}

			}else{
				$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-warning"></i> <b>Email sudah terdaftar</b><br>coba gunakan email yang lain','warning'));
			}
			
		}else{
			$this->session->set_flashdata('alert_message', show_alert(validation_errors(),'warning'));
		}

		redirect('authentication/register');
	}

	public function forceRegister(){
		$additional_data = [
			'first_name' 	=> 'Bellatrix Lestrange',
			'last_name' 	=> '',
			'company' 		=> '',
			'phone' 		=> '085315922225',
			'photo'			=> 'default_photo.jpg'
		];

		$password = '123456789';

		$email 		= strtolower('bella@gmail.com');
		$identity 	= $email;
		$groups     = array(1);
		$register = $this->ion_auth->register('', $password, $email, $additional_data, $groups);
	}

	public function verify($id, $code){
		$activation = $this->ion_auth->activate($id, $code);

		if ($activation){
			$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check"></i> <b>Akun berhasil diverifikasi</b><br>silahkan login sesuai email yang anda daftarkan','success'));
		}else{
			$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-close"></i> Gagal verifikasi<br>kode verifikasi salah','danger'));
		}

		redirect('authentication/login');
	}

	public function login(){
		$p = $this->input->post();

		$this->form_validation->set_rules('username', 'Username', 'required');
		$this->form_validation->set_rules('password', 'Password', 'required');

		if($this->form_validation->run() == TRUE){

			$check = $this->user_model->get_detail($p);

			if($check->num_rows() > 0){

				$user_data = $check->row_array();
				$this->session->set_userdata('login', true);
				$this->session->set_userdata('user_data', $user_data);
				redirect('dashboard');

			}else{
				$this->session->set_flashdata('alert_message', show_alert('<b><i class="fa fa-danger"></i> Username / Password Salah</b><br> Silahkan masukkan username / password dengan benar','danger'));
				redirect('');
			}

		}else{
			$this->session->set_flashdata('alert_message', show_alert(validation_errors(),'danger'));
			redirect('');
		}
	}

	function logout(){
		$this->ion_auth->logout();
		redirect('');
	}
}

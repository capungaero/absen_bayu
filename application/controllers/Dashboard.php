<?php 

class Dashboard extends CI_Controller{

	function __construct(){
		parent::__construct();	
		if(!$this->ion_auth->logged_in()){
			redirect();
		}
		
		$this->userdata = $this->ion_auth->user()->row();
		$this->role   = $this->ion_auth->get_users_groups()->row()->name;
		$this->load->model('branch_model', 'branch');
		$this->load->model('overtime_model', 'overtime');
	}

	public function index(){
		if(in_array($this->role, ['admin', 'admin-branch', 'finance', 'hr', 'inventory', 'employee', 'supervisor'])){

			$branch_id = $this->userdata->branch_id;

			$page = $this->role == 'employee' ? 'employee/index' : 'index';
			if(in_array($this->role, ['admin', 'admin-branch', 'hr'])){
				$page = 'index';

				if($this->role == 'admin'){
					$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
					$data['branch_list'] = $this->branch->get_data()->result_array();
					$data['branch_id'] = $branch_id;

				}else{
					$branch_id = $this->userdata->branch_id;
				}

				$data['count_employee'] = $this->db->select('COUNT(*) AS total')
												   ->where('branch_id', $branch_id)
												   ->where('active', '1')
												   ->join('position', 'position.id = users.position_id')
												   ->get('users')->row_array()['total'];

				$data['count_position'] = $this->db->select('COUNT(*) AS total')
												   ->where('branch_id', $branch_id)
										  		   ->get('position')->row_array()['total'];

				$date = date('Y-m-d', strtotime(date('Y-m-d')." +1 months"));
				$find = [
					'status_work !=' => 'permanent',
					'DATE(status_work_expiration) <=' => $date,
					'active' => '1',
					'branch_id'	=> $branch_id
				];
				$data['employee_expire'] = $this->db->select('*, users.id AS user_id')
													->where($find)
													->join('position', 'position.id = users.position_id')
													->get('users')->result_array();
			}else{
				$page = 'employee/index';
			}

			$data['branch'] = $this->branch->get_detail('id', $branch_id)->row_array();
			$this->template->load('layout/admin', $page, $data);
		}else{
			show_404();
		}
	}

	public function request(){
		if($this->role == 'admin'){
			$data['incomePending'] = $this->submission->get_table_transaction([
										'type' 			=> 'income',
										'is_created'	=> '1',
										'transaction_status' => 'pending'
								   ], 'incomePending', 'income');

			$data['outcomePending'] = $this->submission->get_table_transaction([
										'type' 			=> 'outcome',
										'is_created'	=> '1',
										'transaction_status' => 'pending'
								   ], 'outcomePending', 'outcome');

			$data['returnedPending'] = $this->submission->get_table_transaction([
										'is_created'  		 => '1',
										'transaction_status' => 'approve',
										'is_returned' 		 => '-1',
										'outcome'			 => 'submission'
									], 'returnedPending', 'outcome');

			$data['damagedPending'] = $this->submission->get_table_asset([
									'type' 			=> 'damaged',
									'is_created'	=> '1',
									'maintenance_status' => 'pending'
							   ], 'damagedPending');

			$data['fixedPending'] = $this->submission->get_table_asset([
									'type' 			=> 'fixed',
									'is_created'	=> '1',
									'maintenance_status' => 'pending'
							   ], 'fixedPending');

			$data['overtimePending'] = $this->overtime->get_table_acc([
									'overtime_status' => 'pending'
								], 'overtimePending');

			$this->template->load('layout/admin', 'request', $data);
		}
	}
}
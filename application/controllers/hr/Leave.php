<?php 

class leave extends CI_Controller{

	function __construct(){
		parent::__construct();	
		$this->load->model('leave_model', 'leave');
		$this->load->model('branch_model', 'branch');
		$this->load->model('user_model', 'employee');

		if(!$this->ion_auth->logged_in()){
			redirect('');
		}

		$this->role     = $this->ion_auth->get_users_groups()->row()->name;
		$this->userdata = $this->ion_auth->user()->row();

	}

	public function index(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'employee', 'supervisor'])){
			if(in_array($this->role, ['admin', 'admin-branch'])){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
				$data['branch'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$find = [
				'position.branch_id' => $branch_id
			];

			if(in_array($this->role, ['employee'])){
				$find['user_id'] = $this->userdata->user_id;
			}else{
				$search_employee = [
					'position.branch_id' => $branch_id,
					'active'			 => "1"
				];
				$data['employee']      = $this->employee->get_detail($search_employee, '', '', ['first_name' => 'ASC'])->result_array();
			}

			$data['list']      = $this->leave->get_dataTable($find);
			$data['branch_detail'] = $this->branch->get_detail('id', $branch_id)->row_array();
			$data['branch_id']     = $branch_id;

			$this->template->load('layout/admin','hr/leave/list/index', $data);

		}else{
			redirect();
		}
	}

	public function insert(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'employee', 'supervisor']) && $this->input->is_ajax_request()){
			$keringanan = false;
			$p = $this->input->post();
			$p['leave_proof'] = '';
			$this->form_validation->set_data($p);
			$this->form_validation->set_rules('leave_start', 'Tanggal Mulai', 'required|regex_match[/^\d{4}-\d{2}-\d{2}$/]');
			$this->form_validation->set_rules('leave_end', 'Tanggal Selesai', 'required|regex_match[/^\d{4}-\d{2}-\d{2}$/]');
			$this->form_validation->set_rules('leave_type', 'Jenis Izin', 'required|in_list[izin,sakit,cuti]');
			$this->form_validation->set_rules('leave_reason', 'Alasan Izin', 'required');

			if(in_array($this->role, ['admin', 'admin-branch', 'supervisor'])){
				$this->form_validation->set_rules('user_id', 'Karyawan', 'required');
			}else{
				$p['user_id'] = $this->userdata->user_id;
			}

			if(isset($p['keringanan_potongan'])){
				$keringanan = true;
				$this->form_validation->set_rules('request_potongan', 'Jumlah Potongan', 'required|numeric|greater_than[-1]|less_than[101]');
				

			}else{
				$p['request_potongan'] = 0;
			}

			$p['default_potongan'] = GetPotonganIzin($this->db->where('id', $p['user_id'])->get('users')->row_array()['status_work']);

			if($this->form_validation->run() == TRUE){
				if(strtotime($p['leave_start']) <= strtotime($p['leave_end'])){
					$listDayLeave = get_daterange_list($p['leave_start'], $p['leave_end']);

					$checkPresence = $this->db->where('flow_date >=', $p['leave_start'])
											  ->where('flow_date <=', $p['leave_end'])
											  ->where('user_id', $p['user_id'])
											  ->get('presence')->num_rows();

					$checkOff = $this->db->where('user_id', $p['user_id'])
										 ->where('additional_date >=', $p['leave_start'])
										 ->where('additional_date <=', $p['leave_end'])
										 ->where('additional_type', 'work')
										 ->group_by('additional_date')
										 ->get('users_shift_additional')->num_rows();

					if($checkPresence == 0 && $checkOff == count($listDayLeave)){
						$checkLeave =  $this->db->group_start()
													->group_start()
														->where('leave_start >=', $p['leave_start'])
														->where('leave_start <=', $p['leave_end'])
													->group_end()
													->or_group_start()
														->where('leave_end >=', $p['leave_start'])
														->where('leave_end <=', $p['leave_end'])
													->group_end()
												->group_end()
												->where('user_id', $p['user_id'])
												->where('leave_status', 'pending')
												->get('leave')->num_rows();

						if($checkLeave == 0){
							$u = true;
							if(!empty($_FILES['leave_proof']['name'])){
								$config['upload_path']          = './assets/images/hr/leave/';
						        $config['allowed_types']        = 'png|jpeg|jpg';
						        $config['file_name']			= "leave_".$p['user_id']."_".generateRandom(5)."_".time();
						        $config['max_size']             = 10240;
						        $config['max_width']            = 4000;
					       		$config['max_height']           = 4000;

					        	$this->load->library('upload', $config);

						        if($this->upload->do_upload('leave_proof')){
						        	$upl = $this->upload->data();

						        	$imagePath = $upl['full_path'];
						        	$config['image_library'] = 'gd2';
							        $config['source_image'] = $imagePath;
							        $config['quality'] = '80%';
							        $config['maintain_ratio'] = TRUE;
							        $config['width'] = 800;
							        $this->load->library('image_lib', $config);
							        $this->image_lib->resize();

				        			$p['leave_proof']  = $upl['file_name'];

						        }else{
						        	$u = false;
						        	$res = [
										'status'  => false,
										'message' => show_alert($this->upload->display_errors(),'warning')
									];
						        }
							}

					        if($u){
					        	$totalDay = 0;

					        	foreach($listDayLeave as $row){
					        		if(in_array(get_dayname($row), ['Sabtu', 'Minggu'])){
					        			$totalDay += 2;
					        		}else{
					        			$totalDay ++;
					        		}
					        	}

					        	$data = [
					        		'user_id'		 => $p['user_id'],
					        		'leave_start'  	 => $p['leave_start'],
					        		'leave_end'  	 => $p['leave_end'],
					        		'leave_range'    => diffInDays($p['leave_start'], $p['leave_end']) + 1,
					        		'leave_proof' 	 => $p['leave_proof'],
					        		'leave_type'	 => $p['leave_type'],
					        		'leave_reason'   => $p['leave_reason'],
					        		'default_potongan'=> $p['default_potongan'],
					        		'request_potongan' => $keringanan ? $p['request_potongan'] : null,
					        		'jumlah_hari_potongan' => $totalDay,
					        		'created_at'     => date('Y-m-d H:i:s')
					        	];

								if($this->leave->insert($data)){
									$res = [
										'status'  => true,
										'message' => 'Data berhasil dimasukkan',
										'data' => $data
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
									'message' => $this->upload->display_errors()
								];
							}

						}else{
							$res = [
								'status'  => false,
								'message' => 'Rentang waktu yang anda pilih sedang berada dalam proses pengajuan, silahkan pilih tanggal yang lain'
							];
						}

					}else{
						$res = [
							'status'  => false,
							'message' => 'Rentang waktu yang anda pilih sudah memiliki presensi atau bukan merupakan jadwal shift anda, pastikan memilih tanggal yang belum memiliki presensi atau merupakan jadwal shift anda',
							'data' => [
								'checkPresence' => $checkPresence,
								'checkOff' => $checkOff,
								'listDayLeave' => $listDayLeave
							]
						];
					}

				}else{
					$res = [
						'status'  => false,
						'message' => 'Tanggal mulai izin harus dibawah atau sama dengan tanggal selesai izin'
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

	public function detail($leave_id){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'employee', 'supervisor'])) {

			$find = [
				'leave.id' => $leave_id
			];

			if(!in_array($this->role, ['admin'])){
				$find['position.branch_id'] = $this->userdata->branch_id;
			}

			if(in_array($this->role, ['employee'])){
				$find['user_id'] = $this->userdata->user_id;
			}

			$tr = $this->leave->get_detail($find);

			if($tr->num_rows() > 0){
				$data['leave']  = $tr->row_array();
				$this->template->load('layout/admin','hr/leave/list/detail', $data);
			}else{
				show_404();
			}
			
		}else{
			show_404();
		}
	}

	public function acc(){
		if(in_array($this->role, ['admin', 'admin-branch'])){
			$data['pending'] = $this->leave->get_table_acc([
									'leave_status' => 'pending'
								], 'tablePending');

			$data['unpending'] = $this->leave->get_table_acc([
									'leave_status !=' => 'pending'
								], 'tableUnPending');

			$this->template->load('layout/admin','hr/leave/acc/index', $data);
		}else{
			show_404();
		}
	}

	public function detail_acc($leave_id){
		if(in_array($this->role, ['admin', 'admin-branch'])) {

			$find = [
				'leave.id' => $leave_id
			];

			if($this->role != 'admin'){
				$find['branch_id'] = $this->userdata->branch_id;
			}

			$tr = $this->leave->get_detail($find);

			if($tr->num_rows() > 0){
				$data['leave']  = $tr->row_array();
				$this->template->load('layout/admin','hr/leave/acc/detail', $data);
			}else{
				show_404();
			}
			
		}else{
			show_404();
		}
	}

	public function change_status($leave_id){
		if(in_array($this->role, ['admin']) && $this->input->is_ajax_request()) {

			$find = [
				'leave.id' 	  => $leave_id,
				'leave_status' => 'pending'
			];

			$tr = $this->leave->get_detail($find);
			$p  = $this->input->post();

			if($tr->num_rows() > 0 && in_array($p['status'], ['approve', 'deny'])){
				$this->db->trans_begin();
				$leave = $tr->row_array();
				$now   = date('Y-m-d H:i:s');

				if($p['status'] == 'approve'){
					if($p['leave_potongan_acc'] == 'manual'){
						$potongan = $p['acc_potongan'];
					}else if($p['leave_potongan_acc'] == 'default'){
						$potongan = $leave['default_potongan'];
					}else{
						$potongan = $leave['request_potongan'];
					}
				}

				$data = [
					'leave_status'    => $p['status'],
					'acc_potongan'	  => $p['status'] == 'approve' ? $potongan : null,
					'confirm_at'	  => $now,
					'reject_reason'   => $p['status'] == 'deny' ? $p['reject_reason'] : ''
				];
				$this->leave->update($data, $leave_id);

				if($p['status'] == 'approve'){
					// Aturan: leave_type='sakit' selalu dibayar penuh (potongan 0%),
					// terlepas dari acc_potongan yang dipilih approver.
					if($leave['leave_type'] == 'sakit'){
						$potongan = 0;
					}

					$range = get_daterange_list($leave['leave_start'], $leave['leave_end']);
					$this->db->where('user_id', $leave['user_id'])
							 ->where_in('flow_date', $range)
							 ->delete('presence');

					foreach ($range as $row) {
						$presence[] = [
							'user_id' 	 		=> $leave['user_id'],
							'flow_date'  		=> $row,
							'created_at' 		=> $now,
							'input_by' 	 		=> 'manual',
							'presence_get_paid' => 100 - $potongan,
							'presence_type' 	=> $leave['leave_type'],
							'presence_status' 	=> 'approved',
							'input_by_user_id'	=> $this->userdata->user_id,
							'is_overtime'		=> '0'
						];
					}
					$this->db->insert_batch('presence', $presence);
				}

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

	public function cancel_status($leave_id){
		if(in_array($this->role, ['admin']) && $this->input->is_ajax_request()) {

			$find = [
				'leave.id' 	  => $leave_id,
				'leave_status' => 'approve'
			];

			$tr = $this->leave->get_detail($find);
			$p  = $this->input->post();

			if($tr->num_rows() > 0){
				$leave = $tr->row_array();
				$this->db->trans_begin();
				$data = [
					'leave_status' => 'cancel',
					'updated_at'	  => date('Y-m-d H:i:s')
				];
				$this->leave->update($data, $leave_id);

				$range = get_daterange_list($leave['leave_start'], $leave['leave_end']);
				$this->db->where('user_id', $leave['user_id'])
						 ->where_in('flow_date', $range)
						 ->delete('presence');

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
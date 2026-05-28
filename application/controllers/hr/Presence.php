<?php 
require_once FCPATH.'lib/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

class Presence extends CI_Controller{

	function __construct(){
		parent::__construct();	
		$this->load->model('presence_model', 'presence');
		$this->load->model('shift_model', 'shift');
		$this->load->model('user_model', 'employee');
		$this->load->model('branch_model', 'branch');
		$this->load->model('payroll_model', 'payroll');
		$this->load->model('subdivision_model', 'subdivision');
		$this->load->model('position_model', 'position');
		$this->load->model('presence_daily_report_model', 'daily_report');
		$this->load->library('attendance_employee_resolver');
		$this->load->library('cloud_attlog_client');
		$this->load->library('attlog_parser');

		if(!$this->ion_auth->logged_in()){
			redirect('');
		}

		$this->role     = $this->ion_auth->get_users_groups()->row()->name;
		$this->userdata = $this->ion_auth->user()->row();
	}

	private function _resolve_branch_id($posted_branch_id = null){
		$branch_id = $this->role == 'admin' ? $posted_branch_id : $this->userdata->branch_id;
		$branch_id = (int)$branch_id;

		if($branch_id <= 0){
			return false;
		}

		$branch = $this->branch->get_detail('branch.id', $branch_id)->row_array();
		return !empty($branch) ? $branch_id : false;
	}

	private function _branch_error_response(){
		echo json_encode([
			'status' => false,
			'message' => 'Cabang tidak valid atau tidak sesuai akses user.'
		]);
	}

	public function index(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'employee', 'supervisor'])){
			$find = [
				'branch_id' => $this->userdata->branch_id
			];
			
			$year = $this->input->get('year') ? $this->input->get('year') : date('Y');
			for ($i=1; $i <= 12; $i++) { 
				$find = [
					'month' => $i,
					'year'  => $year
				];

				$presence[] = [
					'id'    => '',
					'month' => $i,
					'year'  => $year,
					'is_setting' => '',
					'updated_at' => ''
				];
			}

			$data['year'] = $year;
			$data['list'] = $presence;
			$this->template->load('layout/admin','hr/presence/index', $data);
		}else{
			redirect();
		}
	}

	public function setting(){
		$this->index();
	}

	public function work_schedule($month, $year){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor'])){
			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
				$data['branch'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$month = str_pad($month, 2, '0', STR_PAD_LEFT);
			$daterange = getRangeWorkDate($month, $year);
			$dates = $daterange['list'];
			$from = reset($dates);
			$to = end($dates);

			$employees = $this->db->select('users.id, users.employee_code, users.first_name, users.photo, position.position_name, subdivision.subdivision_name')
								 ->join('position', 'position.id = users.position_id')
								 ->join('subdivision', 'subdivision.id = users.subdivision_id', 'LEFT')
								 ->where('position.branch_id', $branch_id)
								 ->where('users.active', 1)
								 ->where('DATE(users.join_date) <=', $to)
								 ->order_by('subdivision.subdivision_name', 'ASC')
								 ->order_by('position.position_name', 'ASC')
								 ->order_by('users.first_name', 'ASC')
								 ->get('users')->result_array();

			$schedule_rows = [];
			if(!empty($employees)){
				$user_ids = array_column($employees, 'id');
				$schedule_rows = $this->db->select('users_shift_additional.*, shift.shift_code')
										 ->join('shift', 'shift.id = users_shift_additional.shift_id', 'LEFT')
										 ->where_in('users_shift_additional.user_id', $user_ids)
										 ->where('additional_date >=', $from)
										 ->where('additional_date <=', $to)
										 ->where(latest_schedule_subquery(), null, false)
										 ->get('users_shift_additional')->result_array();
			}

			$schedule_map = [];
			foreach($schedule_rows as $row){
				$schedule_map[$row['user_id']][$row['additional_date']] = $row['additional_type'] == 'free' ? 'free' : $row['shift_id'];
			}

			$presence_map = [];
			if(!empty($employees)){
				$presence_rows = $this->db->select('user_id, flow_date, entry_time, out_time, entry_time_late, presence_type, presence_status')
										 ->where_in('user_id', $user_ids)
										 ->where('flow_date >=', $from)
										 ->where('flow_date <=', $to)
										 ->where('presence_status', 'approved')
										 ->get('presence')->result_array();

				foreach($presence_rows as $row){
					$status = 'absent';
					if($row['presence_type'] == 'sakit'){
						$status = 'sick';
					}else if($row['presence_type'] == 'izin' || $row['presence_type'] == 'cuti'){
						$status = 'permit';
					}else if($row['presence_type'] == 'normal' && ($row['entry_time'] != '' || $row['out_time'] != '')){
						$status = ((int)$row['entry_time_late'] > 0) ? 'late' : 'present';
					}

					$presence_map[$row['user_id']][$row['flow_date']] = [
						'status' => $status,
						'entry_time' => $row['entry_time'],
						'entry_time_late' => (int)$row['entry_time_late'],
						'presence_type' => $row['presence_type']
					];
				}
			}

			$data['month'] = $month;
			$data['year'] = $year;
			$data['branch_id'] = $branch_id;
			$data['branch_detail'] = $this->branch->get_detail('id', $branch_id)->row_array();
			$data['employees'] = $employees;
			$data['shift'] = $this->shift->get_active('branch_id', $branch_id)->result_array();
			$data['daterange'] = $daterange;
			$data['schedule_map'] = $schedule_map;
			$data['presence_map'] = $presence_map;

			$this->template->load('layout/admin','hr/presence/work_schedule', $data);
		}else{
			show_404();
		}
	}

	public function detail($month, $year){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'employee', 'supervisor'])){
			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
				$data['branch'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
				$exclusive_branch_id = $branch_id;

			}else{
				$branch_id = $this->role == 'employee' ? $this->userdata->user_id : $this->userdata->branch_id;
				$exclusive_branch_id = $this->userdata->branch_id;
			}

			$employee_access = in_array($this->role, ['employee']) ? true : false;
			$data['attendance'] = $this->presence->get_attendance_by_branch($branch_id, $month, $year, false, $employee_access);
			$data['shift']	= $this->shift->get_active('branch_id', $branch_id)->result_array();
			$data['subdivision'] = $this->subdivision->get_detail('branch_id', $branch_id)->result_array();
			$data['position'] = $this->position->get_detail('branch_id', $branch_id, '', ['position_name' => 'ASC'])->result_array();

			$data['month'] = $month;
			$data['year']  = $year;
			$data['branch_detail'] = $this->branch->get_detail('id', $branch_id)->row_array();
			$data['branch_id']     = $branch_id;
			$data['last_import']   = $this->_get_last_import_log($branch_id, $month, $year);
			$data['payroll']	   = $this->payroll->get_detail([
										'branch_id' => $exclusive_branch_id,
										'month' => $month,
										'year'	=> $year
									 ])->row_array();

			$data['daterange']    = getRangeWorkDate($month, $year);
			//dd($data['payroll']);
			
			//dd($data['attendance']);
			$this->template->load('layout/presence_only','hr/presence/detail', $data);
			//$this->load->view('hr/presence/detail', $data);
		}else{
			show_404();
		}
	}

	public function update(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor']) && $this->input->is_ajax_request()){
			$p   = $this->input->post();

			$find['users.id'] = $p['user_id'];
			if(!in_array($this->role, ['admin', 'supervisor'])){
				$find['position.branch_id'] = $this->userdata->branch_id;
			}
			$cek = $this->employee->get_detail($find);

			if($cek->num_rows() > 0){

				$this->db->trans_begin();

				$date     = date('Y-m-d', strtotime($p['date']));

				$this->db->where([
					'additional_date' => $date,
					'user_id'		  => $p['user_id']
				])->delete('users_shift_additional');

				$this->db->insert('users_shift_additional', [
					'user_id'  => $p['user_id'],
					'shift_id' => $p['shift_id'] == 'free' ? null : $p['shift_id'],
					'additional_date' => $date,
					'additional_type' => $p['shift_id'] == 'free' ? 'free' : 'work',
					'created_at' => date('Y-m-d H:i:s')
				]);
				
				if($this->db->trans_status()){

					$cek = $this->presence->get_detail([
							'DATE(entry_time)' => date('Y-m-d', strtotime($p['date'])),
							'user_id'		   => $p['user_id']
						])->row_array();

					$presence = false;
					if(!empty($cek)){
						if($cek['out_time'] != ''){
							$presence = true;
						}
					}

					$shift_code = 'free';
					if($p['shift_id'] != 'free'){
						$shift = $this->shift->get_active([
							'id' => $p['shift_id'],
							'branch_id' => $cek->row_array()['branch_id']
						])->row_array();

						if(empty($shift)){
							$this->db->trans_rollback();
							echo json_encode([
								'status' => false,
								'message' => 'Shift nonaktif tidak dapat dipilih pada setting presensi'
							]);
							return;
						}

						$shift_code = $shift['shift_code'];
					}

					$cb = [
						'id' 		=> $p['row_id'],
						'shift_id'  => $p['shift_id'],
						'shift_code'=> $shift_code,
						'type'		=> $p['shift_id'] != 'free' ? 'work' : 'free',
						'presence'  => $presence
					];

					$this->db->trans_commit();
					$res = [
						'status'   => true,
						'message'  => 'Presensi berhasil diubah',
						'row' 	   => $cb
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

	public function update_workhour(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){
			$p   = $this->input->post();

			foreach ($p as $key => $value) {
				$p[$key] = $value != '' ? $p[$key] : null;
			}

			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$cek = $this->employee->get_detail([
								'position.branch_id' => $branch_id,
								'users.id'  => $p['user_id']
							]);

			if($cek->num_rows() > 0 || $this->role == 'admin'){

				$this->db->trans_begin();

				$time = [
					'entry'   => $p['entry_time'],
					'out'     => $p['out_time'],
					'rest_in' => $p['rest_time_in'],
					'rest_out'=> $p['rest_time_out']
				];

				$date = date('Y-m-d', strtotime($p['date']));

				$check_time = $this->presence->check_available_attendance($p['user_id'], $date, $time);

				if($check_time !== false){

					$entry   = $p['entry_time'] != '' ? $date." ".$p['entry_time'].":00" : null;
					$out     = $p['out_time'] != '' ? $date." ".$p['out_time'].":00" : null;
					$rest_in = $p['rest_time_in'] != '' ? $date." ".$p['rest_time_in'].":00" : null;
					$rest_out = $p['rest_time_out'] != '' ? $date." ".$p['rest_time_out'].":00" : null;
					$time = date('Y-m-d H:i:s');
					$adt  = $this->db->where([
								'user_id' => $p['user_id'],
								'additional_date' => $date,
								'additional_type'  => 'work'
							])
							->join('shift', 'shift.id = users_shift_additional.shift_id')
							->get('users_shift_additional')->row_array();

					$entry_time_late = 0;

					if($entry != null){
						$entry_time_late = late_minutes($adt['start_time_late'], $entry);
					}
					
					$rest_time_late = 0;
					if($rest_out != null){
						$rto = $p['rest_time_out'].":00";
						$rest_limit = date('H:i:s', strtotime($rest_in." +".$adt['rest_time_range']." minutes"));
						if($rto <= $adt['end_time_rest']){
							$rest_time_late = late_minutes($rest_limit, $rto);
						}
					}

					$presence = [
						'user_id'	  	  => $p['user_id'],
						'entry_time'  	  => $entry,
						'entry_time_late' => $entry_time_late,
						'out_time'	 	  => $out,
						'rest_time_in'    => $rest_in,
						'rest_time_out'   => $rest_out,
						'rest_time_late'  => $rest_time_late,
						'created_at'  	  => $time,
						'input_by'	  	  => 'manual',
						'is_overtime' 	  => $p['overtime'],
						'input_by_user_id' => $this->userdata->id,
						'flow_date'		  => $date
					];

					$check = $this->presence->get_detail([
								'user_id' => $p['user_id'],
								'flow_date' => $date
							 ])->row_array();

					if(!empty($check)){
						$this->presence->update($presence , $check['id']);
					}else{
						$this->presence->insert($presence);
					}
					
					if($this->db->trans_status()){
						$this->db->trans_commit();
						$this->daily_report->sync_by_pairs([[
							'user_id' => $p['user_id'],
							'flow_date' => $date
						]]);

						$check = $this->presence->get_detail([
								'user_id'   => $p['user_id'],
								'flow_date' => $date
							 ])->row_array();

						$color = '';
						if($p['entry_time'] != '' && $p['out_time'] != ''){
							$color = '34c38f';
						}else if($p['entry_time'] == '' && $p['out_time'] == ''){
							$color = 'f46a6a';
						}else if($p['entry_time'] == '' || $p['out_time'] == ''){
							$color = 'f1b44c';
						}

						$res = [
							'status'   => true,
							'message'  => 'Presensi berhasil diubah',
							'callback' => [
								'id'		 => $check['id'],
								'color'		 => $color,
								'entry_time' => !is_null($entry) ? date('H:i', strtotime($check['entry_time'])) : null,
								'entry_time_late' => $entry_time_late,
								'out_time'	 => !is_null($out) ? date('H:i', strtotime($check['out_time'])) : null,
								'rest_in'	 => !is_null($rest_in) ? date('H:i', strtotime($check['rest_time_in'])) : null,
								'rest_out'	 => !is_null($rest_out) ? date('H:i', strtotime($check['rest_time_out'])) : null,
								'rest_time_late' => $rest_time_late,
								'overtime'   => $p['overtime'],
								'late_work_status' => false,
								'update'	 => indonesian_date($time, true)
							]
						];

						$cb = $res['callback'];
						if($cb['entry_time_late'] > 0 || $cb['rest_time_late'] > 0 || ($cb['rest_in'] != '' && $cb['rest_out'] == '')){
							$res['callback']['late_work_status'] = true;
						}

					}else{
						$this->db->trans_rollback();
						$res = [
							'status'  => false,
							'message' => 'Terjadi kesalahan, coba lagi nanti'
						];
					}

				}else{
					$res = [
						'status'  => false,
						'message' => '<b>Gagal Input</b><br>,Jadwal absen masuk atau keluar yang diinputkan, tidak sesuai dengan waktu jadwal shift',
						'check'   => $check_time,
						'data'	  => $date
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

	public function update_workpray(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){
			$p   = $this->input->post();

			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$cek = $this->employee->get_detail([
								'position.branch_id' => $branch_id,
								'users.id'  => $p['user_id']
							]);

			if($cek->num_rows() > 0 || $this->role == 'admin'){
				$branch_detail = $this->branch->get_detail('branch.id', $branch_id)->row_array();
				$prayer = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];
				$pray_check = true;
				$pray_late = false;
				$user_id = $p['user_id']; unset($p['user_id']);
				$date 	 = $p['date']; unset($p['date']);

				foreach ($prayer as $row){

					$val_in  = $p[$row."_time_in"].":00";
					$val_out = $p[$row."_time_out"].":00";

					$p[$row."_time_in"]  = $val_in != ':00' ? $date." ".$val_in : null;
					$p[$row."_time_out"] = $val_out != ':00' ? $date." ".$val_out : null;

					$time  = date('H:i:s', strtotime($p[$row."_time_out"]));
					$limit = date('H:i:s', strtotime($p[$row."_time_in"]." +".$branch_detail[$row.'_pray_time_range']." minutes"));
					$diff  = 0;

					if($val_out != ':00' && 
					   $time > $limit && 
					   $time <= $branch_detail[$row."_pray_time_out"]){
						$dif_time  = (substr($time, 0, 2) * 60) + substr($time, 3, 2);
						$dif_limit = (substr($limit, 0, 2) * 60) + substr($limit, 3, 2);
						$diff = $dif_time - $dif_limit;
					}
					$p[$row."_time_late"] = $diff;

					if($val_in != ':00' && $val_in < $branch_detail[$row."_pray_time_in"]){
						$pray_check = false;
						$title = $row == 'friday' ? 'Jumat' : $row;
						$res = [
							'status'  => false,
							'message' => "Waktu input masuk sholat ".$title." tidak sesuai dengan rentang waktu yang diperbolehkan pada cabang"
						];
						break;
					}

					if($val_out != ':00' && $val_out > $branch_detail[$row."_pray_time_out"]){
						$pray_check = false;
						$title = $row == 'friday' ? 'Jumat' : $row;
						$res = [
							'status'  => false,
							'message' => "Waktu input keluar sholat ".$title." tidak sesuai dengan rentang waktu yang diperbolehkan pada cabang"
						];
						break;
					}

					if($p[$row."_time_late"] > 0 || ($p[$row."_time_in"] != '' && $p[$row."_time_out"] == '')){
						$pray_late = true;
					}
				}

				if($pray_check){
					$this->db->trans_begin();

					$this->db->where([
						'user_id'   => $user_id,
						'flow_date' => $date
					])->update('presence', $p);

					foreach ($p as $key => $value){
						if(!is_integer($value)){
							$p[$key] = $value != null ? date('H:i', strtotime($value)) : '';
						}else{
							$p[$key] = $value;
						}
					}

					if($this->db->trans_status()){
						$this->db->trans_commit();
						$this->daily_report->sync_by_pairs([[
							'user_id' => $user_id,
							'flow_date' => $date
						]]);
						$p['pray_late'] = $pray_late;
						$res = [
							'status'   => true,
							'message'  => 'Data presensi sholat berhasil diubah',
							'callback' => $p
						];
					}else{
						$this->db->trans_rollback();
						$res = [
							'status'  => false,
							'message' => 'Terjadi kesalahan, coba lagi nanti'
						];
					}
				}

				echo json_encode($res);

			}else{
				show_404();
			}

		}else{
			show_404();
		}
	}

	public function reset($month, $year){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr'])){
			$status = false;
			$posted_branch_id = $this->input->post('branch_id');
			$resolved_branch_id = $this->_resolve_branch_id($posted_branch_id);
			if($resolved_branch_id === false){
				$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-times-circle"></i> Cabang tidak valid atau tidak sesuai akses user', 'danger'));
				redirect('hr/presence/'.$month.'/'.$year);
			}
			$branch_query = '';

			if($this->role == 'admin'){
				$status = true;
				if((int)$this->userdata->branch_id != $resolved_branch_id){
					$branch_query = "?branch_id=".$resolved_branch_id;
				}
			}else{
				if((int)$this->userdata->branch_id == $resolved_branch_id){
					$raw  = strtotime($year."-".$month."-10 -1 months");
			    	$from = date('Y-m-'.START_PAYROLL_DATE, $raw);

					$now  = strtotime(date('Y-m-d'));
					$date = strtotime($from);
					if($now < $date){
						$status = true;
					}
				}
			}

			if($status){
				
				if($this->presence->reset_additional($month, $year, $resolved_branch_id)){
					$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> Jadwal berhasil direset menjadi pola default dari sistem', 'success'));
				}else{
					$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-info"></i> Belum ada jadwal yang dirubah dari pola yang ditentukan sistem', 'warning'));
				}

			}else{
				$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-times-circle"></i> Terjadi kesalahan, coba lagi nanti', 'danger'));
			}

			redirect('hr/presence/'.$month.'/'.$year.$branch_query);
			
		}else{
			show_404();
		}
	}

	public function cancel(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr'])){
			$presence_id = $this->input->post('presence_id');
			$branch_id   = $this->presence->get_detail('presence.id', $presence_id)->row_array()['branch_id'];

			if($this->userdata->branch_id == $branch_id || $this->role == 'admin'){
				
				if($this->presence->delete($presence_id)){
					$res = [
						'status'  => true,
						'message' => 'Kehadiran berhasil dibatalkan'
					];

				}else{
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

	public function upload_pray(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr'])){
			$file_mimes = array('application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
 			
 			if(isset($_FILES['excel_file']['name']) && in_array($_FILES['excel_file']['type'], $file_mimes)){

 				$this->db->trans_begin();
 				$input_num = 0;

 				$p = $this->input->post();
 				$arr_file = explode('.', $_FILES['excel_file']['name']);
    			$extension = end($arr_file);

    			if('csv' == $extension){
			        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
			    } else {
			        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
			    }

			    $p = $this->input->post();
			    $branch_id  = $this->_resolve_branch_id(isset($p['branch_id']) ? $p['branch_id'] : null);
			    if($branch_id === false){
			    	$this->db->trans_rollback();
			    	$this->_branch_error_response();
			    	return;
			    }
			    $branch = $this->branch->get_detail('branch.id', $branch_id)->row_array();

			    $spreadsheet = $reader->load($_FILES['excel_file']['tmp_name']);
			    $sheetData   = $spreadsheet->getActiveSheet()->toArray();

			    $start_row   = 1;
			    $countRow    = count($sheetData);

			    $input = [];
			    $now   = date('Y-m-d H:i:s');

			    $row_data = [];
			    for ($i=$start_row; $i < $countRow; $i++){
			    	$time = $sheetData[$i][3];

			    	$d = substr($time, 0,2);
			    	$m = substr($time, 3,2);
			    	$y = substr($time, 6,4);
			    	$h = substr($time, 11,2);
			    	$j = substr($time, 14,2);
			    	$s = substr($time, 17,2);
			    	$times = $h.":".$j.":".$s;
			    	$date  = $y."-".$m."-".$d;
			    	$datetime = $date." ".$times;

			    	$row_data[$sheetData[$i][2]][$date]['date'] = $date;
			    	$row_data[$sheetData[$i][2]][$date]['time'][] = $times;
			    }

			    $prayer = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];
			    $param  = ['in', 'out'];
			    $created_at = date('Y-m-d H:i:s');
			    $to_delete = [];
			    $report_pairs = [];

			    $employee_matcher = $this->_build_employee_matcher_by_finger_date($row_data);
			    $employee_map = $employee_matcher['map'];

			    foreach($row_data as $key => $value){
			    	$timesheet = [];

			    	foreach ($value as $row){
				    	$date = $row['date'];
				    	$employee = isset($employee_map[$key][$date]) ? $employee_map[$key][$date] : null;

				    	if(empty($employee)) continue;
				    	$all[$employee['id']] = [];

				    	$find = [
				    		'user_id'   => $employee['id'],
				    		'flow_date' => $date
				    	];

				    	$presence = $this->db->where($find)->get('presence')->num_rows();

				    	if($presence > 0){

				    		if(!isset($input[$employee['id']][$date])){
					    		$input[$employee['id']][$date] = attlog_payload_pray();
					    	}

				    		$friday = get_dayname($date) == 'Jumat' ? true : false;

				    		$pray_sheettime = [];
				    		foreach ($prayer as $pray){
				    			$pray_in    = $branch[$pray."_pray_time_in"];
				    			$pray_out   = $branch[$pray."_pray_time_out"];
				    			$pray_range = $branch[$pray."_pray_time_range"];

				    			$pray_sheettime[$pray] = [
			    					'min'   => $pray_in,
			    					'max'   => $pray_out,
			    					'range' => $pray_range
			    				];
				    		}

				    		$rules['pray'] = $pray_sheettime;

				    		foreach ($row['time'] as $time){
				    			$d_day = $date." ".$time;

				    			foreach ($prayer as $pray){
					    			$pray_in    = $rules['pray'][$pray]['min'];
					    			$pray_out   = $rules['pray'][$pray]['max'];
					    			$pray_range = $rules['pray'][$pray]['range'];

				    				if(
				    					$friday && $pray == 'dzuhur'  ||
				    					!$friday && $pray == 'friday'
				    				){
				    					continue;
				    				}

				    				foreach ($param as $par){
					    				if($input[$employee['id']][$date][$pray."_time_".$par] == ''){
					    					if($time >= $pray_in && $time <= $pray_out){
					    						$input[$employee['id']][$date][$pray."_time_".$par] = $d_day;
					    						$input[$employee['id']][$date]['flag'] = true;
					    						break;
					    					}
					    				}
					    			}

					    			if($input[$employee['id']][$date][$pray."_time_in"] != '' &&
					    			   $input[$employee['id']][$date][$pray."_time_out"] != ''){

					    			   	$in  = date('H:i:s', strtotime($input[$employee['id']][$date][$pray."_time_in"]));
					    			    $out = date('H:i:s', strtotime($input[$employee['id']][$date][$pray."_time_out"]));
					    			    $limit = date('H:i:s', strtotime($in." +".$pray_range." minutes")); 

					    				if($limit <= $pray_out && $out > $limit){
				    						$dif_time = (substr($out, 0, 2) * 60) + substr($out, 3, 2);
				    						$dif_limit= (substr($limit, 0, 2) * 60) + substr($limit, 3, 2);

				    						$diff = $dif_time - $dif_limit;
				    						$input[$employee['id']][$date][$pray."_time_late"] = $diff;
					    				}
					    			}
					    		}

					    		if($input[$employee['id']][$date]['flag'] == TRUE){
					    			$this->db->where([
					    				'user_id' 	=> $employee['id'],
					    				'flow_date' => $date
					    			])->update('presence', $input[$employee['id']][$date]);
					    			$input_num++;
					    			$report_pairs[] = [
					    				'user_id' => $employee['id'],
					    				'flow_date' => $date
					    			];
					    		}		
				    		}
				    	}
			    	}
			    }
			    	
			    if($input_num > 0){
			    	if($this->db->trans_status()){
			    		$this->db->trans_commit();
			    		$this->daily_report->sync_by_pairs($report_pairs);
			    		$this->_log_presence_import($branch_id, $p['month'], $p['year'], 'upload_pray', $input_num);
			    		$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> File Excel berhasil diimport', 'success'));

			    		$res = [
				    		'status'   => true,
				    		'message'  => 'File excel berhasil diimport'
				    	];

			    	}else{
			    		$this->db->trans_rollback();
			    		$res = [
				    		'status'  => false,
				    		'message' => 'Terjadi kesalahan'
				    	];
			    	}

			    }else{
			    	$this->db->trans_rollback();
		    		$res = [
			    		'status'  => false,
			    		'message' => 'Tidak ada data yang terdeteksi sesuai aturan jam sholat yang berlaku, silahkan pasyikan isi file excel yang akan diupload sudah sesuai'
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

	public function upload(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr'])){
			$file_mimes = array('application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
 			
 			if(isset($_FILES['excel_file']['name']) && in_array($_FILES['excel_file']['type'], $file_mimes)){
 				$p = $this->input->post();
 				$branch_id  = $this->_resolve_branch_id(isset($p['branch_id']) ? $p['branch_id'] : null);
 				if($branch_id === false){
 					$this->_branch_error_response();
 					return;
 				}
				try{
					$reader = $this->_presence_excel_reader($_FILES['excel_file']['name']);
					$spreadsheet = $reader->load($_FILES['excel_file']['tmp_name']);
					$sheetData   = $spreadsheet->getActiveSheet()->toArray();
					$res = $this->_import_presence_sheet($sheetData, $branch_id, 'upload');
					if($res['status']){
						$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> File Excel berhasil diimport', 'success'));
					}
				}catch(\Exception $e){
					$res = [
						'status' => false,
						'message' => 'File Excel gagal dibaca: '.$e->getMessage()
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

	private function _presence_excel_reader($filename){
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		if($extension == 'csv'){
			return new \PhpOffice\PhpSpreadsheet\Reader\Csv();
		}
		if($extension == 'xls'){
			return new \PhpOffice\PhpSpreadsheet\Reader\Xls();
		}
		return new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
	}

	private function _work_schedule_date_columns($header_row, $from, $to){
		$columns = [];
		if(!is_array($header_row)){ return $columns; }

		for($d = 3; $d < count($header_row); $d++){
			$header = isset($header_row[$d]) ? trim((string)$header_row[$d]) : '';
			if($header == ''){ continue; }

			$workdate = date('Y-m-d', strtotime($header));
			if(!isValidDate($workdate, 'Y-m-d')){ continue; }
			if($workdate < $from || $workdate > $to){ continue; }

			$columns[] = [
				'index' => $d,
				'date'  => $workdate
			];
		}

		return $columns;
	}

	private function _parse_presence_excel_datetime($value){
		if($value instanceof \DateTimeInterface){
			return $value->format('Y-m-d H:i:s');
		}

		$value = trim((string)$value);
		if($value == ''){ return false; }

		if(is_numeric($value)){
			try{
				return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d H:i:s');
			}catch(\Exception $e){
				return false;
			}
		}

		$formats = ['d-m-Y H:i:s', 'd/m/Y H:i:s', 'Y-m-d H:i:s'];
		foreach($formats as $format){
			$date = \DateTime::createFromFormat($format, $value);
			if($date instanceof \DateTime){
				return $date->format('Y-m-d H:i:s');
			}
		}

		$timestamp = strtotime($value);
		return $timestamp ? date('Y-m-d H:i:s', $timestamp) : false;
	}

	private function _import_presence_sheet($sheetData, $branch_id, $method = 'upload', $month = null, $year = null, $use_schedule = true){
		$start_row = 1;
		$countRow = count($sheetData);
		$row_data = [];
		$invalid_count = 0;

		for($i = $start_row; $i < $countRow; $i++){
			if(!isset($sheetData[$i][2]) || !isset($sheetData[$i][3])){ $invalid_count++; continue; }
			$finger_id = trim((string)$sheetData[$i][2]);
			$datetime = $this->_parse_presence_excel_datetime($sheetData[$i][3]);
			if($finger_id == '' || !$datetime){ $invalid_count++; continue; }

			$date = date('Y-m-d', strtotime($datetime));
			$time = date('H:i:s', strtotime($datetime));
			$row_data[$finger_id][$date]['date'] = $date;
			$row_data[$finger_id][$date]['time'][] = $time;
		}

		$created_at = date('Y-m-d H:i:s');
		$input = [];
		$to_delete = [];
		$matched_employee = 0;
		$missing_employee = 0;
		$no_schedule = 0;
		$no_window_match = 0;
		$fallback_rows = 0;
		$employee_matcher = $this->_build_employee_matcher_by_finger_date($row_data);
		$employee_map = $employee_matcher['map'];
		$match_message = $this->_employee_match_message($employee_matcher['stats']);

		foreach($row_data as $finger_id => $value){
			foreach($value as $row){
				$date = $row['date'];
				$employee = isset($employee_map[$finger_id][$date]) ? $employee_map[$finger_id][$date] : null;

				if(empty($employee)){
					$missing_employee++;
					continue;
				}
				$matched_employee++;

				if(!isset($to_delete[$employee['id']])){ $to_delete[$employee['id']] = []; }
				if(!in_array($date, $to_delete[$employee['id']])){ $to_delete[$employee['id']][] = $date; }

				$shift = $this->db->select('*, shift.id AS shift_id')
								  ->where([
									  'user_id' => $employee['id'],
									  'additional_date' => $date,
									  'additional_type' => 'work'
								  ])
								  ->where(latest_schedule_subquery(), null, false)
								  ->join('shift', 'shift.id = users_shift_additional.shift_id')
								  ->get('users_shift_additional')->row_array();

				sort($row['time']);
				$payload = attlog_payload();
				$payload['user_id'] = $employee['id'];
				$payload['flow_date'] = $date;
				$payload['created_at'] = $created_at;

				if(!$use_schedule){
					attlog_apply_fallback($payload, $date, $row['time']);
					$input[$employee['id']][$date] = $payload;
					$fallback_rows++;
					continue;
				}

				if(empty($shift)){
					$no_schedule++;
					if($method == 'sync'){
						attlog_apply_fallback($payload, $date, $row['time']);
						$input[$employee['id']][$date] = $payload;
						$fallback_rows++;
					}
					continue;
				}

				foreach($row['time'] as $time){
					$d_day = $date.' '.$time;
					if(attlog_time_between($time, $shift['start_time_in'], $shift['start_time_out']) && $payload['entry_time'] == ''){
						$payload['entry_time'] = $d_day;
						$payload['entry_time_late'] = late_minutes($shift['start_time_late'], $time);
						continue;
					}

					if(attlog_time_between($time, $shift['end_time_in'], $shift['end_time_out']) && $payload['out_time'] == ''){
						$payload['out_time'] = $d_day;
						continue;
					}

					if(attlog_time_between($time, $shift['start_time_rest'], $shift['end_time_rest'])){
						if($payload['rest_time_in'] == ''){
							$payload['rest_time_in'] = $d_day;
						}else if($payload['rest_time_out'] == ''){
							$payload['rest_time_out'] = $d_day;
							$limit = date('H:i:s', strtotime($payload['rest_time_in'].' +'.$shift['rest_time_range'].' minutes'));
							$payload['rest_time_late'] = late_minutes($limit, $time);
						}
					}
				}

				if($payload['entry_time'] == '' && $payload['out_time'] == '' && $payload['rest_time_in'] == '' && $payload['rest_time_out'] == ''){
					$no_window_match++;
					if($method == 'sync'){
						attlog_apply_fallback($payload, $date, $row['time']);
						$input[$employee['id']][$date] = $payload;
						$fallback_rows++;
					}
					continue;
				}
				$input[$employee['id']][$date] = $payload;
			}
		}

		$data = [];
		foreach($input as $dates){
			foreach($dates as $row){ $data[] = $row; }
		}

		if(empty($data)){
			return [
				'status' => false,
				'total_rows' => 0,
				'message' => 'Tidak ada data yang terdeteksi sesuai aturan jam kerja. Baris invalid: '.$invalid_count.', finger-tanggal cocok: '.$matched_employee.', finger-tanggal tidak cocok: '.$missing_employee.', tanpa jadwal: '.$no_schedule.', di luar window shift: '.$no_window_match.', fallback: '.$fallback_rows.'.'.$match_message
			];
		}

		$this->db->trans_begin();
		$saved_rows = 0;
		$updated_rows = 0;
		$skipped_rows = 0;
		if($method == 'sync'){
			foreach($data as $row){
				$existing = $this->db->where([
					'user_id' => $row['user_id'],
					'flow_date' => $row['flow_date']
				])->get('presence')->row_array();

				if(empty($existing)){
					$this->db->insert('presence', $row);
					$saved_rows++;
					continue;
				}

				$update = [];
				foreach(['entry_time', 'out_time', 'rest_time_in', 'rest_time_out'] as $field){
					if(empty($existing[$field]) && !empty($row[$field])){
						$update[$field] = $row[$field];
					}
				}

				foreach(['entry_time_late', 'rest_time_late'] as $field){
					if((empty($existing[$field]) || $existing[$field] == 0) && !empty($row[$field])){
						$update[$field] = $row[$field];
					}
				}

				if(!empty($update)){
					$this->db->where('id', $existing['id'])->update('presence', $update);
					$updated_rows++;
				}else{
					$skipped_rows++;
				}
			}
		}else{
			foreach($to_delete as $user_id => $dates){
				$this->db->where('user_id', $user_id)
						 ->where_in('flow_date', $dates)
						 ->delete('presence');
			}
			$this->db->insert_batch('presence', $data);
			$saved_rows = count($data);
		}

		if($this->db->trans_status()){
			$this->db->trans_commit();
			$this->daily_report->sync_by_rows($data);
			if($month !== null && $year !== null){
				$this->_log_presence_import($branch_id, $month, $year, $method, $saved_rows + $updated_rows);
			}
			return [
				'status' => true,
				'total_rows' => $saved_rows + $updated_rows,
				'message' => 'File excel berhasil diimport. Presensi baru tersimpan: '.$saved_rows.', data lama dilengkapi: '.$updated_rows.', data yang sudah lengkap dilewati: '.$skipped_rows.'. Baris invalid: '.$invalid_count.', finger-tanggal cocok: '.$matched_employee.', finger-tanggal tidak cocok: '.$missing_employee.', tanpa jadwal: '.$no_schedule.', di luar window shift: '.$no_window_match.', fallback: '.$fallback_rows.'.'.$match_message
			];
		}

		$this->db->trans_rollback();
		return [
			'status' => false,
			'total_rows' => 0,
			'message' => 'Terjadi kesalahan saat menyimpan data presensi.'
		];
	}

	public function call_presensi($employee_id){
		if($this->input->is_ajax_request() && in_array($this->role, ['admin', 'admin-branch'])){
			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$check = $this->employee->get_detail([
						'users.id'  => $employee_id,
						'branch.id' => $branch_id
					 ]);

			if($check->num_rows() > 0){
				$p = $this->input->post();

				$data['data'] = $this->presence->get_fine($employee_id, $p['month'], $p['year']);

				//dd($data['data']);
				$res = [
					'status' => true,
					'page'   => $this->load->view('hr/payroll/_presensiTable', $data, TRUE)
				];

			}else{
				$res = [
					'status'  => false,
					'message' => 'Data karyawan tidak ditemukan'
				];
			}
			
			echo json_encode($res);

		}else{
			show_404();
		}
	}

	public function sync_cloud(){
		if($this->input->is_ajax_request() && in_array($this->role, ['admin', 'admin-branch', 'supervisor'])){
			$p = $this->input->post();
			$branch_id = $this->_resolve_branch_id(isset($p['branch_id']) ? $p['branch_id'] : null);
			if($branch_id === false){
				$this->_branch_error_response();
				return;
			}
			$month = str_pad($p['month'], 2, '0', STR_PAD_LEFT);
			$year = $p['year'];
			$sync_from_date = $this->_normalize_sync_from_date($month, $year, isset($p['sync_from_date']) ? $p['sync_from_date'] : null);

			$download = $this->_download_cloud_attlogs();
			if($download === false){
				echo json_encode([
					'status' => false,
					'message' => 'Gagal download data dari semua mesin Solution Cloud. Cek koneksi internet / login cloud.'
				]);
				return;
			}

			$dat_files = $this->_save_cloud_attlog_files($download['machines'], $month, $year);
			$excel = $this->_build_attlog_excel($download['machines'], $branch_id, $month, $year, $sync_from_date);
			if(!$excel['status']){
				echo json_encode($excel);
				return;
			}

			$use_schedule = !isset($p['use_schedule']) || $p['use_schedule'] == '1';
			$result = $this->_import_presence_sheet($excel['sheet_data'], $branch_id, 'sync', $month, $year, $use_schedule);

			$messages = [];
			$messages[] = 'File DAT tersimpan: '.implode(', ', array_map('basename', $dat_files)).'.';
			$messages[] = 'File Excel otomatis: '.basename($excel['path']).' ('.$excel['total_rows'].' log dari '.$excel['from'].' s/d '.$excel['to'].', '.$excel['mapped_rows'].' cocok karyawan, '.$excel['missing_rows'].' tidak cocok).';
			if(!empty($download['failed'])){
				$messages[] = 'Mesin gagal: '.implode(', ', $download['failed']).'.';
			}
			$messages[] = $result['message'];

			echo json_encode([
				'status' => $result['status'],
				'message' => 'Sync cloud selesai memakai alur standar: download DAT -> konversi Excel -> import Excel.<br>'.implode('<br>', $messages)
			]);
		}else{
			show_404();
		}
	}

	public function sync_pray_cloud(){
		if($this->input->is_ajax_request() && in_array($this->role, ['admin', 'admin-branch', 'supervisor'])){
			$p = $this->input->post();
			$branch_id = $this->_resolve_branch_id(isset($p['branch_id']) ? $p['branch_id'] : null);
			if($branch_id === false){
				$this->_branch_error_response();
				return;
			}
			$month = str_pad($p['month'], 2, '0', STR_PAD_LEFT);
			$year = $p['year'];
			$sync_from_date = $this->_normalize_sync_from_date($month, $year, isset($p['sync_from_date']) ? $p['sync_from_date'] : null);

			$download = $this->_download_pray_cloud_attlogs();
			if($download === false){
				echo json_encode([
					'status' => false,
					'message' => 'Gagal download data sholat dari mesin yang aktif. Cek konfigurasi mesin, koneksi internet, atau login cloud.'
				]);
				return;
			}

			$dat_files = $this->_save_cloud_attlog_files($download['machines'], $month, $year);
			$excel = $this->_build_attlog_excel($download['machines'], $branch_id, $month, $year, $sync_from_date);
			if(!$excel['status']){
				echo json_encode($excel);
				return;
			}

			$result = $this->_import_pray_sheet($excel['sheet_data'], $branch_id, $month, $year, 'sync_pray');

			$messages = [];
			$messages[] = 'File DAT sholat tersimpan: '.implode(', ', array_map('basename', $dat_files)).'.';
			$messages[] = 'File Excel sholat otomatis: '.basename($excel['path']).' ('.$excel['total_rows'].' log dari '.$excel['from'].' s/d '.$excel['to'].', '.$excel['mapped_rows'].' cocok karyawan, '.$excel['missing_rows'].' tidak cocok).';
			if(!empty($download['failed'])){
				$messages[] = 'Mesin gagal: '.implode(', ', $download['failed']).'.';
			}
			$messages[] = $result['message'];

			echo json_encode([
				'status' => $result['status'],
				'message' => 'Sync presensi sholat selesai.<br>'.implode('<br>', $messages)
			]);
		}else{
			show_404();
		}
	}

	public function clear_period(){
		if($this->input->is_ajax_request() && in_array($this->role, ['admin', 'admin-branch', 'supervisor'])){
			$p = $this->input->post();
			$branch_id = $this->_resolve_branch_id(isset($p['branch_id']) ? $p['branch_id'] : null);
			if($branch_id === false){
				$this->_branch_error_response();
				return;
			}
			$month = str_pad($p['month'], 2, '0', STR_PAD_LEFT);
			$year = $p['year'];
			$raw = strtotime($year.'-'.$month.'-10 -1 months');
			$from = date('Y-m-'.START_PAYROLL_DATE, $raw);
			$to = $year.'-'.$month.'-'.END_PAYROLL_DATE;

			$this->db->trans_begin();
			$deleted = $this->_clear_presence_period($branch_id, $month, $year);
			$this->daily_report->delete_period($branch_id, $from, $to);

			if($this->db->trans_status()){
				$this->db->trans_commit();
				$this->_log_presence_import($branch_id, $month, $year, 'delete', $deleted);
				echo json_encode([
					'status' => true,
					'message' => 'Data absen periode payroll '.$from.' s/d '.$to.' berhasil dikosongkan. Total terhapus: '.$deleted.'.'
				]);
				return;
			}

			$this->db->trans_rollback();
			echo json_encode([
				'status' => false,
				'message' => 'Gagal mengosongkan data absen.'
			]);
		}else{
			show_404();
		}
	}

	private function _clear_presence_period($branch_id, $month, $year){
		$month = str_pad($month, 2, '0', STR_PAD_LEFT);
		$raw = strtotime($year.'-'.$month.'-10 -1 months');
		$from = date('Y-m-'.START_PAYROLL_DATE, $raw);
		$to = $year.'-'.$month.'-'.END_PAYROLL_DATE;

		$this->db->where('flow_date >=', $from)
				 ->where('flow_date <=', $to)
				 ->where('user_id IN (SELECT users.id FROM users JOIN position ON position.id = users.position_id WHERE position.branch_id = '.(int) $branch_id.')', null, false)
				 ->delete('presence');

		return $this->db->affected_rows();
	}

	private function _get_last_import_log($branch_id, $month, $year){
		return $this->db->where([
						'branch_id' => $branch_id,
						'month' => str_pad($month, 2, '0', STR_PAD_LEFT),
						'year' => $year
						])
						->order_by('created_at', 'DESC')
						->get('presence_import_log')
						->row_array();
	}

	private function _log_presence_import($branch_id, $month, $year, $method, $total_rows){
		$this->db->insert('presence_import_log', [
			'branch_id' => $branch_id,
			'month' => str_pad($month, 2, '0', STR_PAD_LEFT),
			'year' => $year,
			'method' => $method,
			'total_rows' => $total_rows,
			'created_at' => date('Y-m-d H:i:s'),
			'created_by' => $this->userdata->id
		]);
	}

	private function _save_cloud_attlog_files($machines, $month, $year){
		$dir = attlog_presence_storage_dir($month, $year);
		$paths = [];
		$stamp = date('Ymd_His');
		foreach($machines as $machine){
			$sn = attlog_sanitize_machine_sn($machine['sn']);
			if($sn == ''){
				continue;
			}
			$path = $dir.DIRECTORY_SEPARATOR.'attlog_'.$sn.'_'.$stamp.'.dat';
			file_put_contents($path, $machine['raw']);
			$paths[] = $path;
		}
		return $paths;
	}

	private function _normalize_sync_from_date($month, $year, $date){
		$period = attlog_presence_period_range($month, $year);
		$date = trim((string)$date);
		if($date == ''){
			return $period['from'];
		}

		$timestamp = strtotime($date);
		if(!$timestamp){
			return $period['from'];
		}

		$date = date('Y-m-d', $timestamp);
		if($date < $period['from']){
			return $period['from'];
		}

		if($date > $period['to']){
			return $period['to'];
		}

		return $date;
	}

	private function _build_attlog_excel($machines, $branch_id, $month, $year, $from_date = null){
		$month = str_pad($month, 2, '0', STR_PAD_LEFT);
		$period = attlog_presence_period_range($month, $year);
		$from = !empty($from_date) ? $from_date : $period['from'];
		$to = $period['to'];

		$finger_ids = [];
		$match_rows = [];
		$logs = [];
		$invalid_count = 0;
		foreach($machines as $machine){
			$lines = preg_split('/\r\n|\r|\n/', $machine['raw']);
			foreach($lines as $line){
				$line = trim($line);
				if($line == ''){ continue; }
				$cols = preg_split('/\s+/', $line);
				if(count($cols) < 3){ $invalid_count++; continue; }
				$finger_id = trim($cols[0]);
				$timestamp = strtotime($cols[1].' '.$cols[2]);
				if($finger_id == '' || !$timestamp){ $invalid_count++; continue; }
				$date = date('Y-m-d', $timestamp);
				if($date < $from || $date > $to){ continue; }
				$finger_ids[$finger_id] = $finger_id;
				$match_rows[$finger_id][$date]['date'] = $date;
				$logs[] = [
					'finger_id' => $finger_id,
					'date' => $date,
					'datetime' => date('d-m-Y H:i:s', $timestamp),
					'source' => $machine['sn']
				];
			}
		}

		if(empty($logs)){
			return [
				'status' => false,
				'message' => 'Data cloud berhasil didownload, tapi tidak ada log untuk periode payroll '.$from.' s/d '.$to.'. Baris format tidak valid: '.$invalid_count.'.'
			];
		}

		$employee_matcher = $this->_build_employee_matcher_by_finger_date($match_rows, $from, $to);
		$employee_map = $employee_matcher['map'];
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet_data = [['No', 'Nama', 'ID Fingerprint', 'Tanggal Jam Absen', 'Mesin']];
		$sheet->fromArray($sheet_data[0], null, 'A1');

		$row = 2;
		$mapped_rows = 0;
		$missing_rows = 0;
		foreach($logs as $index => $log){
			$employee = isset($employee_map[$log['finger_id']][$log['date']]) ? $employee_map[$log['finger_id']][$log['date']] : null;
			$name = !empty($employee) ? $employee['first_name'] : 'TIDAK DITEMUKAN';
			if(!empty($employee)){ $mapped_rows++; }else{ $missing_rows++; }
			$sheet_row = [$index + 1, $name, $log['finger_id'], $log['datetime'], $log['source']];
			$sheet_data[] = $sheet_row;
			$sheet->setCellValue('A'.$row, $sheet_row[0]);
			$sheet->setCellValue('B'.$row, $sheet_row[1]);
			$sheet->setCellValueExplicit('C'.$row, $sheet_row[2], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			$sheet->setCellValue('D'.$row, $sheet_row[3]);
			$sheet->setCellValue('E'.$row, $sheet_row[4]);
			$row++;
		}

		foreach(['A' => 8, 'B' => 30, 'C' => 16, 'D' => 22, 'E' => 18] as $column => $width){
			$sheet->getColumnDimension($column)->setWidth($width);
		}

		$dir = attlog_presence_storage_dir($month, $year);
		$path = $dir.DIRECTORY_SEPARATOR.'absen_'.$month.'_'.substr($year, -2).'.xls';
		$old_error_reporting = error_reporting();
		error_reporting($old_error_reporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);
		$writer = new Xls($spreadsheet);
		$writer->save($path);
		error_reporting($old_error_reporting);

		return [
			'status' => true,
			'path' => $path,
			'sheet_data' => $sheet_data,
			'total_rows' => count($logs),
			'mapped_rows' => $mapped_rows,
			'missing_rows' => $missing_rows,
			'invalid_rows' => $invalid_count,
			'from' => $from,
			'to' => $to
		];
	}

	private function _download_cloud_attlogs(){
		return $this->cloud_attlog_client->download_batch($this->_active_sync_machines('attendance'));
	}

	private function _download_pray_cloud_attlogs(){
		return $this->cloud_attlog_client->download_batch($this->_active_sync_machines('pray'));
	}

	private function _active_sync_machines($type){
		$this->load->model('Sync_model', 'sync_machines');
		$rows = $this->sync_machines->get_active_by_type($type);
		$machines = [];

		foreach($rows as $row){
			$sn = attlog_sanitize_machine_sn($row['machine_sn']);
			if($sn == '' || $row['password'] === ''){
				continue;
			}

			$machines[] = [
				'sn' => $sn,
				'pass' => $row['password']
			];
		}

		return $machines;
	}

	private function _download_cloud_attlog($sn, $password){
		return $this->cloud_attlog_client->download_single($sn, $password);
	}

	private function _import_attlog_dat($raw, $branch_id, $month, $year, $preserve_existing = false, $use_schedule = true){
		$period = attlog_presence_period_range($month, $year);
		$from = $period['from'];
		$to = $period['to'];

		$parsed = $this->attlog_parser->parse_taps($raw, $from, $to);
		$row_data = $parsed['rows'];
		$total_lines = $parsed['stats']['total_lines'];
		$raw_count = $parsed['stats']['raw_count'];
		$invalid_count = $parsed['stats']['invalid_count'];

		if(empty($row_data)){
			return [
				'status' => false,
				'total_rows' => 0,
				'message' => 'Data cloud berhasil dibaca, tapi tidak ada log untuk periode payroll '.$from.' s/d '.$to.'. Total baris file: '.$total_lines.', baris format tidak valid: '.$invalid_count.'.'
			];
		}

		$created_at = date('Y-m-d H:i:s');
		$input = $to_delete = [];
		$existing_presence = [];
		$matched_employee = 0;
		$missing_employee = 0;
		$no_schedule = 0;
		$no_window_match = 0;
		$fallback_rows = 0;
		$fallback_rows = 0;
		$unique_finger_ids = count($row_data);
		$employee_matcher = $this->_build_employee_matcher_by_finger_date($row_data, $from, $to);
		$employee_map = $employee_matcher['map'];
		$match_message = $this->_employee_match_message($employee_matcher['stats']);
		$shift_map = [];
		$existing_presence = [];
		$user_ids = $this->_employee_ids_from_matcher($employee_map);

		if($use_schedule && !empty($user_ids)){
			$shift_rows = $this->db->select('users_shift_additional.*, shift.*')
								->join('shift', 'shift.id = users_shift_additional.shift_id')
								->where_in('users_shift_additional.user_id', $user_ids)
								->where('additional_date >=', $from)
								->where('additional_date <=', $to)
								->where('additional_type', 'work')
								->get('users_shift_additional')->result_array();

			foreach($shift_rows as $shift_row){
				$shift_map[$shift_row['user_id'].'|'.$shift_row['additional_date']] = $shift_row;
			}
		}

		foreach($row_data as $finger_id => $dates){
			foreach($dates as $row){
				$date = $row['date'];
				$employee = isset($employee_map[$finger_id][$date]) ? $employee_map[$finger_id][$date] : null;

				if(empty($employee)){
					$missing_employee++;
					continue;
				}
				$matched_employee++;

				if(!isset($to_delete[$employee['id']])){ $to_delete[$employee['id']] = []; }
				if(!in_array($date, $to_delete[$employee['id']])){ $to_delete[$employee['id']][] = $date; }

				$shift_key = $employee['id'].'|'.$date;
				$shift = isset($shift_map[$shift_key]) ? $shift_map[$shift_key] : [];

				sort($row['time']);
				$input[$employee['id']][$date] = attlog_payload();
				$input[$employee['id']][$date]['user_id'] = $employee['id'];
				$input[$employee['id']][$date]['flow_date'] = $date;
				$input[$employee['id']][$date]['created_at'] = $created_at;

				if(!$use_schedule){
					attlog_apply_fallback($input[$employee['id']][$date], $date, $row['time']);
					$fallback_rows++;
					continue;
				}

				if(empty($shift)){
					$no_schedule++;
					unset($input[$employee['id']][$date]);
					continue;
				}

				foreach($row['time'] as $time){
					$d_day = $date.' '.$time;
					if(attlog_time_between($time, $shift['start_time_in'], $shift['start_time_out']) && $input[$employee['id']][$date]['entry_time'] == ''){
						$input[$employee['id']][$date]['entry_time'] = $d_day;
						$input[$employee['id']][$date]['entry_time_late'] = late_minutes($shift['start_time_late'], $time);
						continue;
					}

					if(attlog_time_between($time, $shift['end_time_in'], $shift['end_time_out']) && $input[$employee['id']][$date]['out_time'] == ''){
						$input[$employee['id']][$date]['out_time'] = $d_day;
						continue;
					}

					if(attlog_time_between($time, $shift['start_time_rest'], $shift['end_time_rest'])){
						if($input[$employee['id']][$date]['rest_time_in'] == ''){
							$input[$employee['id']][$date]['rest_time_in'] = $d_day;
						}else if($input[$employee['id']][$date]['rest_time_out'] == ''){
							$input[$employee['id']][$date]['rest_time_out'] = $d_day;
							$limit = date('H:i:s', strtotime($input[$employee['id']][$date]['rest_time_in'].' +'.$shift['rest_time_range'].' minutes'));
							$input[$employee['id']][$date]['rest_time_late'] = late_minutes($limit, $time);
						}
					}
				}

				if($input[$employee['id']][$date]['entry_time'] == '' && $input[$employee['id']][$date]['out_time'] == '' && $input[$employee['id']][$date]['rest_time_in'] == '' && $input[$employee['id']][$date]['rest_time_out'] == ''){
					unset($input[$employee['id']][$date]);
					$no_window_match++;
					continue;
				}

			}
		}

		$data = [];
		foreach($input as $dates){
			foreach($dates as $row){
				$data[] = $row;
			}
		}

		if(empty($data)){
			return [
				'status' => false,
				'total_rows' => 0,
				'message' => 'Data cloud terbaca '.$raw_count.' log periode payroll '.$from.' s/d '.$to.', tapi tidak ada presensi yang bisa disimpan. Finger unik: '.$unique_finger_ids.', finger-tanggal cocok: '.$matched_employee.', finger-tanggal tidak cocok: '.$missing_employee.', tanpa jadwal: '.$no_schedule.', di luar window shift: '.$no_window_match.', fallback: '.$fallback_rows.'.'.$match_message
			];
		}

		$saved_rows = 0;
		$this->db->trans_begin();
		if($preserve_existing){
			$user_dates = [];
			foreach($data as $row){
				$user_dates[$row['user_id']][] = $row['flow_date'];
			}

			foreach($user_dates as $user_id => $dates){
				$existing_rows = $this->db->where('user_id', $user_id)
										  ->where_in('flow_date', array_unique($dates))
										  ->get('presence')->result_array();
				foreach($existing_rows as $existing_row){
					$existing_presence[$existing_row['user_id'].'|'.$existing_row['flow_date']] = $existing_row;
				}
			}

			foreach($data as $row){
				$key = $row['user_id'].'|'.$row['flow_date'];
				$existing = isset($existing_presence[$key]) ? $existing_presence[$key] : null;
				if(empty($existing)){
					$this->db->insert('presence', $row);
					$existing_presence[$key] = $row;
					$saved_rows++;
					continue;
				}

				$update = [];
				foreach(['entry_time', 'out_time', 'rest_time_in', 'rest_time_out'] as $field){
					if(empty($existing[$field]) && !empty($row[$field])){
						$update[$field] = $row[$field];
					}
				}

				foreach(['entry_time_late', 'rest_time_late'] as $field){
					if((empty($existing[$field]) || $existing[$field] == 0) && !empty($row[$field])){
						$update[$field] = $row[$field];
					}
				}

				if(!empty($update)){
					$this->db->where('id', $existing['id'])->update('presence', $update);
					$existing_presence[$key] = array_merge($existing, $update);
					$saved_rows++;
				}
			}
		}else{
			foreach($to_delete as $user_id => $dates){
				$this->db->where('user_id', $user_id)
						 ->where_in('flow_date', $dates)
						 ->delete('presence');
			}
			$this->db->insert_batch('presence', $data);
			$saved_rows = count($data);
		}

		if($this->db->trans_status()){
			$this->db->trans_commit();
			$this->daily_report->sync_by_rows($data);
			$this->_log_presence_import($branch_id, $month, $year, 'sync', $saved_rows);
			return [
				'status' => true,
				'total_rows' => $saved_rows,
				'message' => 'periode payroll '.$from.' s/d '.$to.'. Baris file: '.$total_lines.', log periode: '.$raw_count.', finger unik: '.$unique_finger_ids.', finger-tanggal cocok: '.$matched_employee.', finger-tanggal tidak cocok: '.$missing_employee.', tanpa jadwal: '.$no_schedule.', di luar window shift: '.$no_window_match.', fallback: '.$fallback_rows.', kandidat presensi: '.count($data).', tersimpan/terupdate: '.$saved_rows.'.'.$match_message
			];
		}

		$this->db->trans_rollback();
		return [
			'status' => false,
			'total_rows' => 0,
			'message' => 'Sync cloud gagal saat menyimpan presensi.'
		];
	}

	private function _import_pray_sheet($sheetData, $branch_id, $month = null, $year = null, $method = 'sync_pray'){
		$branch = $this->branch->get_detail('branch.id', $branch_id)->row_array();
		if(empty($branch)){
			return [
				'status' => false,
				'total_rows' => 0,
				'message' => 'Cabang tidak ditemukan untuk import sholat.'
			];
		}

		$row_data = [];
		$invalid_count = 0;
		$countRow = count($sheetData);
		for($i = 1; $i < $countRow; $i++){
			if(!isset($sheetData[$i][2]) || !isset($sheetData[$i][3])){ $invalid_count++; continue; }
			$finger_id = trim((string)$sheetData[$i][2]);
			$datetime = $this->_parse_presence_excel_datetime($sheetData[$i][3]);
			if($finger_id == '' || !$datetime){ $invalid_count++; continue; }

			$date = date('Y-m-d', strtotime($datetime));
			$time = date('H:i:s', strtotime($datetime));
			$row_data[$finger_id][$date]['date'] = $date;
			$row_data[$finger_id][$date]['time'][] = $time;
		}

		$prayer = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];
		$param = ['in', 'out'];
		$input = [];
		$matched_employee = 0;
		$missing_employee = 0;
		$without_presence = 0;
		$updated_rows = 0;
		$employee_matcher = $this->_build_employee_matcher_by_finger_date($row_data);
		$employee_map = $employee_matcher['map'];
		$match_message = $this->_employee_match_message($employee_matcher['stats']);

		foreach($row_data as $finger_id => $dates){
			foreach($dates as $row){
				$date = $row['date'];
				$employee = isset($employee_map[$finger_id][$date]) ? $employee_map[$finger_id][$date] : null;

				if(empty($employee)){
					$missing_employee++;
					continue;
				}
				$matched_employee++;

				$presence = $this->db->where([
					'user_id' => $employee['id'],
					'flow_date' => $date
				])->get('presence')->row_array();

				if(empty($presence)){
					$without_presence++;
					continue;
				}

				if(!isset($input[$employee['id']][$date])){
					$input[$employee['id']][$date] = attlog_payload_pray();
				}

				$friday = get_dayname($date) == 'Jumat';
				sort($row['time']);
				foreach($row['time'] as $time){
					$d_day = $date.' '.$time;
					foreach($prayer as $pray){
						if(($friday && $pray == 'dzuhur') || (!$friday && $pray == 'friday')){
							continue;
						}

						$pray_in = $branch[$pray.'_pray_time_in'];
						$pray_out = $branch[$pray.'_pray_time_out'];
						$pray_range = $branch[$pray.'_pray_time_range'];
						if($pray_in == '' || $pray_out == ''){ continue; }

						foreach($param as $par){
							if($input[$employee['id']][$date][$pray.'_time_'.$par] == '' && $time >= $pray_in && $time <= $pray_out){
								$input[$employee['id']][$date][$pray.'_time_'.$par] = $d_day;
								$input[$employee['id']][$date]['flag'] = true;
								break;
							}
						}

						if($input[$employee['id']][$date][$pray.'_time_in'] != '' && $input[$employee['id']][$date][$pray.'_time_out'] != ''){
							$in = date('H:i:s', strtotime($input[$employee['id']][$date][$pray.'_time_in']));
							$out = date('H:i:s', strtotime($input[$employee['id']][$date][$pray.'_time_out']));
							$limit = date('H:i:s', strtotime($in.' +'.$pray_range.' minutes'));
							if($limit <= $pray_out && $out > $limit){
								$dif_time = (substr($out, 0, 2) * 60) + substr($out, 3, 2);
								$dif_limit = (substr($limit, 0, 2) * 60) + substr($limit, 3, 2);
								$input[$employee['id']][$date][$pray.'_time_late'] = $dif_time - $dif_limit;
							}
						}
					}
				}
			}
		}

		$this->db->trans_begin();
		$skipped_rows = 0;
		$report_pairs = [];
		foreach($input as $user_id => $dates){
			foreach($dates as $date => $pray_data){
				if($pray_data['flag'] !== true){ continue; }
				unset($pray_data['flag']);
				$existing = $this->db->where([
					'user_id' => $user_id,
					'flow_date' => $date
				])->get('presence')->row_array();

				if(empty($existing)){
					continue;
				}

				if($this->_has_pray_presence($existing)){
					$skipped_rows++;
					continue;
				}

				$update = [];
				foreach($pray_data as $field => $value){
					if(strpos($field, '_time_late') !== false){
						if((empty($existing[$field]) || $existing[$field] == 0) && !empty($value)){
							$update[$field] = $value;
						}
						continue;
					}

					if(empty($existing[$field]) && !empty($value)){
						$update[$field] = $value;
					}
				}

				if(empty($update)){
					$skipped_rows++;
					continue;
				}

				$this->db->where([
					'user_id' => $user_id,
					'flow_date' => $date
				])->update('presence', $update);
				$updated_rows++;
				$report_pairs[] = [
					'user_id' => $user_id,
					'flow_date' => $date
				];
			}
		}

		if($this->db->trans_status()){
			$this->db->trans_commit();
			$this->daily_report->sync_by_pairs($report_pairs);
			if($updated_rows > 0 && $month !== null && $year !== null){
				$this->_log_presence_import($branch_id, $month, $year, $method, $updated_rows);
			}
			return [
				'status' => $updated_rows > 0,
				'total_rows' => $updated_rows,
				'message' => 'Presensi sholat tersimpan: '.$updated_rows.'. Data sholat yang sudah ada dilewati: '.$skipped_rows.'. Baris invalid: '.$invalid_count.', finger-tanggal cocok: '.$matched_employee.', finger-tanggal tidak cocok: '.$missing_employee.', belum ada presensi kerja: '.$without_presence.'.'.$match_message
			];
		}

		$this->db->trans_rollback();
		return [
			'status' => false,
			'total_rows' => 0,
			'message' => 'Gagal menyimpan presensi sholat.'
		];
	}

	private function _has_pray_presence($presence){
		foreach(['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'] as $pray){
			foreach(['time_in', 'time_out'] as $suffix){
				$field = $pray.'_'.$suffix;
				if(!empty($presence[$field]) && $presence[$field] != '0000-00-00 00:00:00'){
					return true;
				}
			}

			$field = $pray.'_time_late';
			if(!empty($presence[$field]) && (int)$presence[$field] > 0){
				return true;
			}
		}

		return false;
	}

	private function _build_employee_matcher_by_finger_date($row_data, $from = null, $to = null){
		return $this->attendance_employee_resolver->build_by_finger_date($row_data, $from, $to);
	}

	private function _employee_match_message($stats){
		return $this->attendance_employee_resolver->message($stats);
	}

	private function _employee_ids_from_matcher($employee_map){
		return $this->attendance_employee_resolver->employee_ids_from_map($employee_map);
	}

	private function _get_employee_map_by_finger($finger_ids, $branch_id = null){
		$date = date('Y-m-d');
		$result = $this->attendance_employee_resolver->map_for_date($finger_ids, $date);
		return $result['map'];
	}


	public function upload_work_schedule(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor'])){
			$file_mimes = array('application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
 			
			$extension = isset($_FILES['excel_file']['name']) ? strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION)) : '';
 			if(isset($_FILES['excel_file']['name']) && (in_array($_FILES['excel_file']['type'], $file_mimes) || in_array($extension, ['csv', 'xls', 'xlsx']))){

 				$p = $this->input->post();
			    $branch_id  = $this->_resolve_branch_id(isset($p['branch_id']) ? $p['branch_id'] : null);
			    if($branch_id === false){
			    	echo json_encode([
			    		'status' => false,
			    		'message' => 'Cabang tidak valid atau tidak sesuai akses user.'
			    	]);
			    	return;
			    }

				try{
					$reader = $this->_presence_excel_reader($_FILES['excel_file']['name']);
			    	$spreadsheet = $reader->load($_FILES['excel_file']['tmp_name']);
			    	$sheetData   = $spreadsheet->getActiveSheet()->toArray();
				}catch(\Exception $e){
					echo json_encode([
						'status' => false,
						'message' => 'File Excel gagal dibaca: '.$e->getMessage()
					]);
					return;
				}

			    $start_row   = 3;
			    $countRow    = count($sheetData);

			    $input = [];
			    $delete = [];
			    $now   = date('Y-m-d H:i:s');
			    $check_shift = true;

			    $raw  = strtotime($p['year']."-".$p['month']."-10 -1 months");
		    	$from = date('Y-m-'.START_PAYROLL_DATE, $raw);
		    	$to   = $p['year']."-".$p['month']."-".END_PAYROLL_DATE;
				$date_columns = $this->_work_schedule_date_columns(isset($sheetData[2]) ? $sheetData[2] : [], $from, $to);

				if(empty($date_columns)){
					echo json_encode([
						'status' => false,
						'message' => 'Tidak ada kolom tanggal yang cocok dengan periode jadwal kerja ini.'
					]);
					return;
				}

			    for ($i=$start_row; $i < $countRow; $i++){ 
			    	$user  = $this->employee->get_detail([
			    						'employee_code'   => $sheetData[$i][0],
			    						'branch.id'		  => $branch_id
			    					])->row_array();

			    	if(empty($user)){ continue; }

					$delete[$user['id']] = [
						$date_columns[0]['date'],
						$date_columns[count($date_columns) - 1]['date']
					];

			    	foreach($date_columns as $date_column){
			    		$d = $date_column['index'];
			    		$workdate = $date_column['date'];
				    	$shift_code = isset($sheetData[$i][$d]) ? strtoupper(trim((string)$sheetData[$i][$d])) : '';
						if($shift_code == ''){ continue; }

					    if($shift_code == 'OFF'){
					    			$shift_id   = null;
					    			$shift_type = 'free';
					    		}else{
					    			$shift = $this->shift->get_detail([
					    							'shift_code' => $shift_code,
					    							'branch_id'  => $branch_id,
													'is_active'  => '1'
					    						])->row_array();
					    			$shift_id = isset($shift['id']) ? $shift['id'] : null;
					    			$shift_type  = 'work';
					    			if($shift_id == '' || $shift_id == null){
					    				$check_shift = false;
					    				break;
					    			}
					    		}

				    			$input[] = [
					    			'user_id'  => $user['id'],
					    			'shift_id' => $shift_id,
					    			'additional_date' => $workdate,
					    			'additional_type' => $shift_type,
					    			'created_at'	  => $now
					    		];

			    	}

			    	if(!$check_shift){ 
			    		$selectedWorkdate = $workdate;
			    		$selectedRow = $i + 1;
			    		break; 
			    	}
			    }

			    
			    if(!$check_shift){ 
			    	$msg = "Shift yang diupload tidak terdeteksi pada cabang ini (Baris : ".$selectedRow." | Tanggal : ".$selectedWorkdate.")";
			    	$res = [
			    		'status'  => false,
			    		'message' => $msg
			    	];

			    }else{
			    	if(!empty($input)){
				    	$this->db->trans_begin();

				    	$bulk_user_id = [];
				    	foreach ($input as $row) {
				    		$bulk_user_id[] = $row['user_id'];
				    	}
				    	
				    	foreach($delete as $key => $val){
				    		$this->db->where('additional_date >=', $val[0])
								 ->where('additional_date <=', $val[1])
				    			 ->where('user_id', $key)
				    			 ->delete('users_shift_additional');
				    	}
				    	
				    	$this->db->insert_batch('users_shift_additional', $input);

				    	if($this->db->trans_status()){
				    		$this->db->trans_commit();

				    		$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> Jadwal kerja berhasil diupload', 'success'));

				    		$res = [
					    		'status'  => true,
					    		'message' => 'File berhasil diupload'
					    	];

				    	}else{
				    		$this->db->trans_rollback();
				    		$res = [
					    		'status'  => false,
					    		'message' => 'Terjadi kesalahan, coba lagi nanti'
					    	];
				    	}

				    }else{
				    	$res = [
				    		'status'  => false,
				    		'data'	  => $sheetData,
				    		'message' => 'Tidak ada data yang terdeteksi, silahkan isi file excel yang akan diupload dengan format yang benar<br>Perhatikan beberapa inputan dibawah ini : <ol>
				    				<li>Kode Shift sudah benar</li>
				    				<li>ID Fingerprint Karyawan</li>
				    				<li>Template Excel yang diupload sesuai dengan menu bulan jadwal kerja yang dipilih</i>
				    			  </ol>'
				    	];
				    }
			    }

			    

 			}else{
 				$res = [
 					'status'  => false,
					'message' => 'Format file tidak diketahui, harap upload file template jadwal kerja Excel.'
  				];
  			}

 			echo json_encode($res);
		}else{
			show_404();
		}
	}

	public function save_work_schedule_manual(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor'])){
			$p = $this->input->post();
			$branch_id = $this->_resolve_branch_id(isset($p['branch_id']) ? $p['branch_id'] : null);
			if($branch_id === false){
				$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-times-circle"></i> Cabang tidak valid atau tidak sesuai akses user', 'danger'));
				redirect('hr/presence');
			}
			$month = str_pad($p['month'], 2, '0', STR_PAD_LEFT);
			$year = $p['year'];
			$schedules = isset($p['schedule']) && is_array($p['schedule']) ? $p['schedule'] : [];

			$daterange = getRangeWorkDate($month, $year);
			$dates = $daterange['list'];
			$from = reset($dates);
			$to = end($dates);

			$employee_rows = $this->db->select('users.id')
									 ->join('position', 'position.id = users.position_id')
									 ->where('position.branch_id', $branch_id)
									 ->where('users.active', 1)
									 ->get('users')->result_array();
			$employee_ids = array_map('strval', array_column($employee_rows, 'id'));

			$shift_rows = $this->shift->get_active('branch_id', $branch_id)->result_array();
			$shift_ids = array_map('strval', array_column($shift_rows, 'id'));

			$input = [];
			$delete = [];
			$now = date('Y-m-d H:i:s');

			foreach($schedules as $user_id => $user_schedules){
				if(!in_array((string)$user_id, $employee_ids)){ continue; }
				foreach($user_schedules as $date => $shift_id){
					if(!in_array($date, $dates)){ continue; }
					$shift_id = trim((string)$shift_id);
					$delete[$user_id][] = $date;
					if($shift_id == ''){ continue; }

					if($shift_id == 'free'){
						$input[] = [
							'user_id' => $user_id,
							'shift_id' => null,
							'additional_date' => $date,
							'additional_type' => 'free',
							'created_at' => $now
						];
						continue;
					}

					if(!in_array($shift_id, $shift_ids)){ continue; }
					$input[] = [
						'user_id' => $user_id,
						'shift_id' => $shift_id,
						'additional_date' => $date,
						'additional_type' => 'work',
						'created_at' => $now
					];
				}
			}

			if(empty($input) && empty($delete)){
				$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-info-circle"></i> Tidak ada jadwal yang dipilih untuk disimpan', 'warning'));
				redirect('hr/work-schedule/'.$month.'/'.$year.($this->role == 'admin' ? '?branch_id='.$branch_id : ''));
			}

			$this->db->trans_begin();
			foreach($delete as $user_id => $user_dates){
				$this->db->where('user_id', $user_id)
						 ->where_in('additional_date', array_unique($user_dates))
						 ->delete('users_shift_additional');
			}
			if(!empty($input)){
				$this->db->insert_batch('users_shift_additional', $input);
			}

			if($this->db->trans_status()){
				$this->db->trans_commit();
				$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> Jadwal kerja berhasil disimpan', 'success'));
			}else{
				$this->db->trans_rollback();
				$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-times-circle"></i> Jadwal kerja gagal disimpan', 'danger'));
			}

			redirect('hr/work-schedule/'.$month.'/'.$year.($this->role == 'admin' ? '?branch_id='.$branch_id : ''));
		}else{
			show_404();
		}
	}

	public function load_work_schedule_excel(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor'])){
			$file_mimes = array('application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			$extension = isset($_FILES['excel_file']['name']) ? strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION)) : '';
			if(!isset($_FILES['excel_file']['name']) || (!in_array($_FILES['excel_file']['type'], $file_mimes) && !in_array($extension, ['csv', 'xls', 'xlsx']))){
				echo json_encode(['status' => false, 'message' => 'Format file tidak diketahui. Upload file template jadwal kerja Excel.']);
				return;
			}

			$p = $this->input->post();
			$branch_id = $this->_resolve_branch_id(isset($p['branch_id']) ? $p['branch_id'] : null);
			if($branch_id === false){
				echo json_encode(['status' => false, 'message' => 'Cabang tidak valid atau tidak sesuai akses user.']);
				return;
			}
			$month = str_pad($p['month'], 2, '0', STR_PAD_LEFT);
			$year = $p['year'];
			$daterange = getRangeWorkDate($month, $year);
			$dates = $daterange['list'];
			$from = reset($dates);
			$to = end($dates);

			try{
				$reader = $this->_presence_excel_reader($_FILES['excel_file']['name']);
				$spreadsheet = $reader->load($_FILES['excel_file']['tmp_name']);
				$sheetData = $spreadsheet->getActiveSheet()->toArray();
			}catch(\Exception $e){
				echo json_encode(['status' => false, 'message' => 'File Excel gagal dibaca: '.$e->getMessage()]);
				return;
			}

			$shift_rows = $this->shift->get_active('branch_id', $branch_id)->result_array();
			$shift_map = [];
			foreach($shift_rows as $row){
				$shift_map[strtoupper(trim($row['shift_code']))] = $row['id'];
			}

			$result = [];
			$invalid_shift = [];
			$matched_rows = 0;
			$start_row = 3;
			$countRow = count($sheetData);
			$date_columns = $this->_work_schedule_date_columns(isset($sheetData[2]) ? $sheetData[2] : [], $from, $to);

			if(empty($date_columns)){
				echo json_encode([
					'status' => false,
					'message' => 'Tidak ada kolom tanggal yang cocok dengan periode jadwal kerja ini.',
					'matched_rows' => 0,
					'invalid_shift' => []
				]);
				return;
			}

			for($i = $start_row; $i < $countRow; $i++){
				$finger_id = isset($sheetData[$i][0]) ? trim((string)$sheetData[$i][0]) : '';
				if($finger_id == ''){ continue; }

				$user = $this->employee->get_detail([
					'employee_code' => $finger_id,
					'branch.id' => $branch_id
				])->row_array();

				if(empty($user)){ continue; }
				$matched_rows++;

				foreach($date_columns as $date_column){
					$d = $date_column['index'];
					$workdate = $date_column['date'];
					$shift_code = isset($sheetData[$i][$d]) ? strtoupper(trim((string)$sheetData[$i][$d])) : '';
					if($shift_code == ''){ $result[$user['id']][$workdate] = ''; continue; }
					if($shift_code == 'OFF'){
						$result[$user['id']][$workdate] = 'free';
						continue;
					}

					if(isset($shift_map[$shift_code])){
						$result[$user['id']][$workdate] = $shift_map[$shift_code];
					}else{
						$invalid_shift[$shift_code] = $shift_code;
					}
				}
			}

			echo json_encode([
				'status' => !empty($result),
				'schedule' => $result,
				'advanced' => count($invalid_shift) == 0 ? false : true,
				'message' => !empty($result) ? 'Jadwal dari Excel berhasil dimuat ke halaman. Klik Simpan Jadwal untuk menyimpan ke database.' : 'Tidak ada data jadwal yang cocok dengan periode dan cabang ini.',
				'matched_rows' => $matched_rows,
				'invalid_shift' => array_values($invalid_shift)
			]);
		}else{
			show_404();
		}
	}

	public function copy_previous_work_schedule(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor'])){
			$p = $this->input->post();
			$branch_id = $this->_resolve_branch_id(isset($p['branch_id']) ? $p['branch_id'] : null);
			if($branch_id === false){
				echo json_encode(['status' => false, 'message' => 'Cabang tidak valid atau tidak sesuai akses user.']);
				return;
			}
			$month = str_pad($p['month'], 2, '0', STR_PAD_LEFT);
			$year = $p['year'];
			$current = getRangeWorkDate($month, $year)['list'];
			$previous_month = date('m', strtotime($year.'-'.$month.'-10 -1 months'));
			$previous_year = date('Y', strtotime($year.'-'.$month.'-10 -1 months'));
			$previous = getRangeWorkDate($previous_month, $previous_year)['list'];

			$employee_rows = $this->db->select('users.id')
									 ->join('position', 'position.id = users.position_id')
									 ->where('position.branch_id', $branch_id)
									 ->where('users.active', 1)
									 ->get('users')->result_array();
			$user_ids = array_column($employee_rows, 'id');

			$result = [];
			if(!empty($user_ids)){
				$rows = $this->db->select('users_shift_additional.*')
								 ->where_in('user_id', $user_ids)
								 ->where('additional_date >=', reset($previous))
								 ->where('additional_date <=', end($previous))
								 ->where(latest_schedule_subquery(), null, false)
								 ->get('users_shift_additional')->result_array();

				$previous_index = [];
				foreach($previous as $index => $date){ $previous_index[$date] = $index; }
				foreach($rows as $row){
					if(!isset($previous_index[$row['additional_date']])){ continue; }
					$target_date = isset($current[$previous_index[$row['additional_date']]]) ? $current[$previous_index[$row['additional_date']]] : null;
					if($target_date == null){ continue; }
					$result[$row['user_id']][$target_date] = $row['additional_type'] == 'free' ? 'free' : $row['shift_id'];
				}
			}

			echo json_encode([
				'status' => !empty($result),
				'schedule' => $result,
				'message' => !empty($result) ? 'Jadwal periode sebelumnya berhasil dimuat. Klik Simpan Jadwal untuk menyimpan ke database.' : 'Tidak ada jadwal pada periode sebelumnya untuk dicopy.'
			]);
		}else{
			show_404();
		}
	}


	public function export_work_schedule($month, $year, $branch_id){
		$pass = ($this->role != 'admin' && $branch_id != $this->userdata->branch_id) ? false : true;

		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor']) && $pass){
 			
 			/**$branch_id = $this->userdata->branch_id;
 			if($this->role == 'admin' && $this->input->get('branch_id')){
 				$branch_id = $this->input->get('branch_id');
 			}**/
 			
 			$this->load->helper('download');  

 			$spreadsheet = new Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();
			$sheet->mergeCells('A1:E1');
			$sheet->setCellValue('A1', 'TEMPLATE JADWAL KERJA KARYAWAN');
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
			$sheet->setCellValue('A2', '*Note : Untuk jadwal libur, dapat diketik dengan kode "OFF" , tanpa tanda petik');

			$sheet->setCellValue('A3', 'ID FINGERPRINT KARYAWAN');
			$sheet->setCellValue('B3', 'NAMA KARYAWAN');
			$sheet->setCellValue('C3', 'POSISI');

			$daterange  = getRangeWorkDate($month, $year);
			$start_from = 4; 
	        foreach ($daterange['list'] as $day){
	        	$col = EXCEL_COLUMN[$start_from];
	        	$sheet->setCellValue($col.'3', $day);
	        	$sheet->getColumnDimension($col)->setWidth(15);
	        	$start_from++;
	        }

	        $sheet->getRowDimension('3')->setRowHeight(20);
	        $sheet->getStyle('A3:'.$col.'3')->applyFromArray([
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

	        $raw  = strtotime($year."-".$month."-10 -1 months");
			$from = date('Y-m-'.START_PAYROLL_DATE, $raw);
			$to   = $year."-".$month."-".END_PAYROLL_DATE;
	        $employee = $this->employee->get_detail([
	        				'active' => '1',
	        				'position.branch_id' => $branch_id,
	        				'DATE(join_date) <=' => $to 
	        			], '', '', ['users.first_name' => 'ASC'])->result_array();

			$with_schedule = $this->input->get('with_schedule') == '1';
			$schedule_map = [];
			if($with_schedule && !empty($employee)){
				$user_ids = array_column($employee, 'id');
				$schedule_rows = $this->db->select('users_shift_additional.user_id, users_shift_additional.additional_date, users_shift_additional.additional_type, shift.shift_code')
									 ->join('shift', 'shift.id = users_shift_additional.shift_id', 'LEFT')
									 ->where_in('users_shift_additional.user_id', $user_ids)
									 ->where('users_shift_additional.additional_date >=', $from)
									 ->where('users_shift_additional.additional_date <=', $to)
									 ->where(latest_schedule_subquery(), null, false)
									 ->get('users_shift_additional')->result_array();
				foreach($schedule_rows as $schedule_row){
					$schedule_map[$schedule_row['user_id']][$schedule_row['additional_date']] = $schedule_row['additional_type'] == 'free' ? 'OFF' : $schedule_row['shift_code'];
				}
			}

	        $start_from = 4;
	        foreach ($employee as $row) {
	        	$sheet->setCellValue('A'.$start_from, $row['employee_code']);
	        	$sheet->setCellValue('B'.$start_from, $row['first_name']);
	        	$sheet->setCellValue('C'.$start_from, $row['position_name']);
				if($with_schedule){
					$date_col = 4;
					foreach($daterange['list'] as $day){
						$schedule_value = isset($schedule_map[$row['id']][$day]) ? $schedule_map[$row['id']][$day] : '';
						$sheet->setCellValue(EXCEL_COLUMN[$date_col].$start_from, $schedule_value);
						$date_col++;
					}
				}
	        	$start_from++;
	        }

	        $title = ($with_schedule ? "Jadwal Kerja " : "Template Jadwal Kerja ").get_monthname($month)." ".$year;
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

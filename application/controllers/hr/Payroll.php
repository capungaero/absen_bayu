<?php 

require_once FCPATH.'lib/vendor/autoload.php';
require_once FCPATH.'application/libraries/dompdf/autoload.inc.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;
use Dompdf\Options;

class Payroll extends CI_Controller{

	function __construct(){
		parent::__construct();	
		if(!$this->ion_auth->logged_in()){
			redirect();
		}

		$this->role     = $this->ion_auth->get_users_groups()->row()->name;
		$this->userdata = $this->ion_auth->user()->row();

		$this->load->model('branch_model', 'branch');
		$this->load->model('presence_model', 'presence');
		$this->load->model('user_model', 'employee');
		$this->load->model('payroll_model', 'payroll');
		$this->load->model('subdivision_model', 'subdivision');
	}

	public function index(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'employee', 'supervisor'])){
			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
				$data['branch'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			if(in_array($this->role, ['employee', 'supervisor'])){
				$year = $this->input->get('year') ? $this->input->get('year') : date('Y');
				$data['list'] = $this->_get_employee_payroll_data($year, $this->userdata->user_id);
				$data['year'] = $year;
				$this->template->load('layout/admin','hr/payroll/employee_view/index', $data);

			}else{
				$month = $this->input->get('month') ? $this->input->get('month') : date('m');
				$year  = $this->input->get('year') ? $this->input->get('year') : date('Y');
				$data['month'] = $month;
				$data['year']  = $year;

				$by_branch = $this->role == 'admin' ? false : $branch_id;
				$data['list'] 		= $this->payroll->get_by_list_branch($month, $year, $by_branch);
				$data['branch_id']  = $branch_id;
				$this->template->load('layout/admin','hr/payroll/index', $data);
			}
			
		}else{
			show_404();
		}
	}

	public function detail($month, $year){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'employee', 'supervisor'])){
			$data['month']  = $month;
			$data['year']   = $year;

			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
				$data['branch'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$find = [
				'month'		=> $month,
				'year'		=> $year,
				'branch_id' => $branch_id
			];

			$data['payroll'] 	= $this->payroll->get_detail($find)->row_array();
			$data['branch_id']  = $branch_id;
			$pass = true;

			if($this->role == 'employee' || $this->role == 'supervisor'){
				if(empty($data['payroll'])){
					$pass = false;
				}	
			}

			if($pass){
				if(empty($data['payroll'])){
					$table    = '_payrollTable';
					$comp 	  = $data;
					$overtime = true;

					$comp['attendance'] = $this->presence->get_attendance_by_branch($branch_id, $month, $year, $overtime);
				
				}else{
					$employee_id = $this->role == 'employee' || $this->role == 'supervisor' ? $this->userdata->user_id : '';
					$table = '_payrollTableDone';

					$order_by = [
				    	'subdivision_name' => 'ASC',
				        'position_name'    => 'ASC',
				        'users.first_name' => 'ASC'
				    ];

				    $payroll_list = $this->payroll->get_payment_list_by_id($data['payroll']['id'], $employee_id, $order_by);
					$comp['attendance'] = $this->_toResponsePaymentList($payroll_list); 
					$comp['payroll']	= $data['payroll'];
				}
				
				$data['subdivision']   = $this->subdivision->get_detail('branch_id', $branch_id)->result_array();
				$data['branch_detail'] = $this->branch->get_detail('id', $branch_id)->row_array();
				$comp['branch_detail'] = $data['branch_detail'];
				$comp['month']		   = $data['month'];
				$comp['year']		   = $data['year'];
				$data['payrollTable'] = $this->load->view('hr/payroll/'.$table, $comp, TRUE);
				
				//dd($comp['attendance']);
				$this->template->load('layout/admin','hr/payroll/detail', $data);

			}else{
				show_404();
			}

		}else{
			show_404();
		}	
	}

	public function generate($month, $year){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){
			$p 	  = $this->input->post();
			$data = [];
			$find = [
				'month'		=> $month,
				'year'		=> $year,
				'branch_id' => $p['branch_id']
			];

			$cek = $this->payroll->get_detail($find)->num_rows();

			if($cek == 0){
				$this->db->trans_begin();
				$branch_detail = $this->branch->get_detail('branch.id', $p['branch_id'])->row_array();

				$totalDayInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

				// Seed potongan Izin Pulang Cepat sebelum get_attendance_by_branch
				// supaya get_deduction() bisa cache rows yang baru kita insert.
				$this->_seed_early_leave_deductions($p['branch_id'], $month, $year, $totalDayInMonth);

				$attendance    = $this->presence->get_attendance_by_branch($p['branch_id'], $month, $year, true);

	            $time = date('Y-m-d H:i:s');
	            $payroll = [
	            	'branch_id' => $p['branch_id'],
	            	'month'		=> $month,
	            	'year'		=> $year,
	            	'created_at'=> $time
	            ];
	            $this->db->insert('payroll', $payroll);
	            $payroll_id = $this->db->where($payroll)->get('payroll')->row_array()['id'];

	            $n = 0;
            	$all_salary = $all_receive = $all_adjustment = $thp = $all_overtime = $all_salary_debt = 0;;
            	$all_work  = $all_in = $all_hour = $all_bpjs_work = $all_deduction = $all_bpjs_together = $all_work = $all_fine = $out_together_nominal = 0;

	            foreach ($attendance['list'] as $row){ $n++; 
	            	$user_id = $row['employee']['id'];
	                $total_work = $num_present = $num_late = $num_overtime = $max_work = $bpjs_work = $bpjs_health = $bpjs_together = 0;

	                $fine = $this->presence->get_fine($user_id, $month, $year);
	                $insentive = $this->presence->get_insentif($user_id, $p['branch_id'], $month, $year);

	                $presence = $fine['detail']['entry']['presence'];

	                $max_day_work = $presence['max_for_generate'];
	                $salary_per_day = $presence['max'] > 0 ? $row['employee']['salary'] / $max_day_work : 0;
	                $salaryPerDayForAlpha = $presence['max'] > 0 ? $row['employee']['salary'] / $totalDayInMonth : 0;
	                $salary_basic_out_off_work = $salary_per_day * $presence['strip'];

	                $presence_in = $presence['count'] + $presence['off'];
	                $tmpReceive = $row['employee']['salary']  - $row['alpha_weekdays_amount'];
	                if($tmpReceive < $row['employee']['salary_minimum']){
	                    $payment_receive = $row['employee']['salary_minimum'];
	                }else{
	                    $payment_receive = $row['employee']['salary'];
	                }
	                
	                if(isset($p['work'][$user_id])){
	                	if($p['work'][$user_id] != ''){
	                		$bpjs_work = format_angka($p['work'][$user_id]);
	                	}
	                }

	                if(isset($p['together'][$user_id])){
	                	if($p['together'][$user_id] != ''){
	                		$bpjs_together = format_angka($p['together'][$user_id]);

	                		if($out_together_nominal == 0){
	                			$out_together_nominal = $bpjs_together;
	                		}
	                	}
	                }

	                $out_alfa_amount_weekend  = $fine['detail']['entry']['amount_in_weekend'];
	                $out_alfa_amount_weekdays = $presence['weekdays'] * $salaryPerDayForAlpha;
	                $out_alfa_amount = $out_alfa_amount_weekend + $out_alfa_amount_weekdays;

	                /**if($user_id == '1033'){
	                	var_dump([
	                		"presence_weekdays" => $presence['weekdays'],
	                		"salary_per_day" => $salary_per_day,
	                		"out_alfa_amount_weekdays" => $out_alfa_amount_weekdays
	                	]);
	                }	**/
	                
	                $thp = ($payment_receive + $row['overtime']['amount'] + $row['insentif']['total']) - ($row['fine'] + $bpjs_work + $row['deduction']['total'] + $bpjs_together + $salary_basic_out_off_work);

	                $thp = $thp < 0 ? 0 : $thp;
	                $salary_debt = $thp < 0 ? $thp : 0;

	                $all_in 	  	+= $payment_receive;
	                $all_overtime 	+= $row['overtime']['amount'];
	                $all_salary   	+= $row['employee']['salary'];
	                $all_receive  	+= $thp;
	                $all_work 	  	+= $num_present;
	                $all_hour 	  	+= $row['overtime']['total_hour'];
	                $all_adjustment += $row['insentif']['total'];
	                $all_fine 		+= $row['fine'];
	                $all_bpjs_work  += $bpjs_work;
	                $all_bpjs_together += $bpjs_together;
	                $all_deduction 	 += $row['deduction']['total'];
	                $all_salary_debt += $salary_debt;

	                $pray = $fine['detail']['pray']['detail'];

	                $data[] = [
	                	'payroll_id'    => $payroll_id,
	                	'user_id'	    => $user_id,
	                	'payroll_account_number' => $row['employee']['account_number'],
	                	'payroll_account_bank' => $row['employee']['account_bank'],
	                	'payroll_account_name' => $row['employee']['account_name'],
	                	'presence_count'=> $presence['count'],
	                	'presence_max'  => $presence['max'],
	                	'presence_count_on_time'  => $presence['full']['on_time'],
	                	'presence_count_on_late'  => $presence['full']['late'],
	                	'presence_count_on_half'  => $presence['half'],
	                	'presence_count_on_leave' => $presence['leave']['count'],
	                	'presence_count_on_sakit' => $presence['leave']['sakit'],
	                	'presence_count_on_cuti'  => $presence['leave']['cuti'],
	                	'presence_count_on_izin'  => $presence['leave']['izin'],
	                	'presence_off_count_on_weekend'  => $presence['weekend'],
	                	'presence_off_count_on_weekdays' => $presence['weekdays'],
	                	'subuh_count'			  => $pray['subuh']['total']['count'],
	                	'subuh_count_on_time'	  => $pray['subuh']['total']['on_time'],
	                	'subuh_count_on_late' 	  => $pray['subuh']['total']['late'],
	                	'dzuhur_count'			  => $pray['dzuhur']['total']['count'],
	                	'dzuhur_count_on_time'    => $pray['dzuhur']['total']['on_time'],
	                	'dzuhur_count_on_late'	  => $pray['dzuhur']['total']['late'],
	                	'ashar_count'			  => $pray['ashar']['total']['count'],
	                	'ashar_count_on_time' 	  => $pray['ashar']['total']['on_time'],
	                	'ashar_count_on_late' 	  => $pray['ashar']['total']['late'],
	                	'maghrib_count' 		  => $pray['maghrib']['total']['count'],
	                	'maghrib_count_on_time'   => $pray['maghrib']['total']['on_time'],
	                	'maghrib_count_on_late'   => $pray['maghrib']['total']['late'],
	                	'isha_count' 			  => $pray['isha']['total']['count'],
	                	'isha_count_on_time' 	  => $pray['isha']['total']['on_time'],
	                	'isha_count_on_late' 	  => $pray['isha']['total']['late'],
	                	'friday_count' 			  => $pray['friday']['total']['count'],
	                	'friday_count_on_time' 	  => $pray['friday']['total']['on_time'],
	                	'friday_count_on_late' 	  => $pray['friday']['total']['late'],
	                	'salary_in_overtime'	  => $row['overtime']['amount'],
	                	'salary_in_insentive'  	  => $row['insentif']['total'],
	                	'salary_in_basic'		  => $payment_receive,
	                	'salary_out_work'		  => $bpjs_work,
	                	'salary_out_deduction'	  => $row['deduction']['total'],
	                	'salary_out_fine'		  => $row['fine'],
	                	'salary_out_together'  	  => $bpjs_together,
	                	'salary_thp'			  => $thp,
	                	'salary_debt'			  => $salary_debt,
	                	'total_overtime_hour'	  => $row['overtime']['total_hour'],
	                	'salary_basic_in_full'	  => $row['employee']['salary'],
	                	'salary_basic_out_off_work' => $salary_basic_out_off_work,
	                	'salary_basic_out_alfa'   => $out_alfa_amount,
	                	'salary_basic_out_alfa_weekend'  => $out_alfa_amount_weekend,
	                	'salary_basic_out_alfa_weekdays' => $out_alfa_amount_weekdays,
	                	'payroll_fine'			  => json_encode($fine),
	                	'payroll_insentive'		  => json_encode($insentive),
	                	'payroll_deduction'		  =>  json_encode($row['deduction'])
	                ];
	            }

	            $this->db->insert_batch('payroll_detail', $data);

	            $branch = $this->branch->get_detail('id', $p['branch_id'])->row_array();
				$num    = $this->payroll->get_detail('branch_id', $p['branch_id'])->num_rows() + 1;
				$code   = 'TRX-'.$branch['branch_code'].'-PYR-'.$num;

	            $this->db->where('id', $payroll_id)->update('payroll', [
	            	'payroll_code'	 		 	=> $code,
	            	'total_employee'	 	 	=> $n,
	            	'out_together_nominal'   	=> $out_together_nominal,
	            	'total_salary_in_basic'	 	=> $all_in,
	            	'total_salary_in_overtime'  => $all_overtime,
	            	'total_salary_in_insentive' => $all_adjustment,
	            	'total_salary_out_fine'		=> $all_fine,
	            	'total_salary_out_work'		=> $all_bpjs_work,
	            	'total_salary_out_deduction'=> $all_deduction,
	            	'total_salary_out_together' => $all_bpjs_together,
	            	'total_salary_thp'			=> $all_receive,
	            	'total_salary_debt'			=> $all_salary_debt
	            ]);

	            if($this->db->trans_status()){
	            	$this->db->trans_commit();
	            	$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> Penggajian berhasil digenerate', 'success'));
	            	$res = [
	            		'status'  => true,
	            		'message' => 'Penggajian berhasil digenerate'
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
					'message' => 'Gaji sudah pernah digenerate'
				];
			}

			echo json_encode($res);

		}else{
			show_404();
		}
	}

	public function print($month, $year){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr'])){
			$data['month']  = $month;
			$data['year']   = $year;

			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
				$data['branch'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$find = [
				'month'		=> $month,
				'year'		=> $year,
				'branch_id' => $branch_id
			];

			$data['payroll'] = $this->payroll->get_detail($find)->row_array();
			$data['branch_id']     = $branch_id;

			if(!empty($data['payroll'])){
				$table = '_payrollTableDone';
				$payroll_list = $this->payroll->get_payment_list_by_id($data['payroll']['id']);
				$data['attendance'] = $this->_toResponsePaymentList($payroll_list); 
				$data['branch_detail'] = $this->branch->get_detail('id', $branch_id)->row_array();
			
				$this->load->view('hr/payroll/print', $data);

			}else{
				show_404();
			}
			
		}else{
			show_404();
		}	
	}

	public function print_slip($month, $year, $employee_id){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'employee', 'supervisor'])){
			$data['month']  = $month;
			$data['year']   = $year;

			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
				$data['branch'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$find = [
				'month'		=> $month,
				'year'		=> $year,
				'branch_id' => $branch_id
			];

			$data['payroll'] = $this->payroll->get_detail($find)->row_array();
			$data['branch_id']     = $branch_id;
			$employee_pass = true;

			if($this->role == 'employee' || $this->role == 'supervisor'){
				if($employee_id != $this->userdata->user_id){
					$employee_pass = false;
				}
			}

			if(!empty($data['payroll']) && $employee_pass){
				$table = '_payrollTableDone';
				$data['attendance'] = $this->payroll->get_payment_list_by_id($data['payroll']['id'], $employee_id);
				$data['branch_detail'] = $this->branch->get_detail('id', $branch_id)->row_array();

				$this->load->view('hr/payroll/print_slip', $data);

			}else{
				show_404();
			}
			
		}else{
			show_404();
		}	
	}

	public function save_insentif(){
		if($this->input->is_ajax_request() && in_array($this->role, ['admin', 'admin-branch', 'hr'])){
			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$p = $this->input->post();
			$time = date('Y-m-d H:i:s');
			$employee = $this->employee->get_detail([
							'users.id'  => $p['employee_id'],
							'position.branch_id' => $branch_id
						]);

			if($employee->num_rows() > 0){
				if(!empty($p['insentif'])){
					$total = 0;
					foreach ($p['insentif'] as $key => $val){
						$total += format_angka($val);
						$data[] = [
							'user_id' 		=> $p['employee_id'],
							'insentif_id'	=> $key,
							'insentif_year' => $this->input->get('year'),
							'insentif_month'=> $this->input->get('month'),
							'insentif_amount' => format_angka($val),
							'created_at'	=> $time
						];
					}

					if(isset($p['default'])){
						foreach ($p['default'] as $row){
							$total += format_angka($row);
						}
					}

					$this->db->trans_begin();
					$this->db->where([
						'user_id' => $p['employee_id'],
						'insentif_month' => $this->input->get('month'),
						'insentif_year'  => $this->input->get('year')
					])->delete('payroll_insentif');

					$this->db->insert_batch('payroll_insentif', $data);

					if($this->db->trans_status()){
						$this->db->trans_commit();
						$res = [
							'status'  => true,
							'total'	  => $total,
							'message' => 'Insentif berhasil disimpan'
						];

					}else{
						$this->db->trans_rollback();
						$res = [
							'status'  => false,
							'message' => 'Insentif gagal disimpan'
						];
					}

				}else{
					$res = [
						'status'  => false,
						'message' => 'Komponen insentif tidak ada, harap input pada menu master data'
					];
				}
				
			}else{
				$res = [
					'status'  => false,
					'message' => 'Data karyawan tidak diketahui'
				];
			}

			echo json_encode($res);
		}else{
			show_404();
		}
	}


	public function save_deduction(){
		if($this->input->is_ajax_request() && in_array($this->role, ['admin', 'admin-branch', 'hr'])){
			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$p = $this->input->post();
			$time = date('Y-m-d H:i:s');
			$employee = $this->employee->get_detail([
							'users.id'  => $p['employee_id'],
							'position.branch_id' => $branch_id
						]);

			if($employee->num_rows() > 0){
				if(!empty($p['deduction'])){
					$total = 0;
					foreach ($p['deduction'] as $key => $val){
						$total += format_angka($val);
						$note = isset($p['deduction_note'][$key]) ? trim($p['deduction_note'][$key]) : '';
						$data[] = [
							'user_id' 		   => $p['employee_id'],
							'deduction_id'	   => $key,
							'deduction_year'   => $this->input->get('year'),
							'deduction_month'  => $this->input->get('month'),
							'deduction_amount' => format_angka($val),
							'deduction_note'   => $note,
							'created_at'	   => $time
						];
					}

					if(isset($p['default'])){
						foreach ($p['default'] as $row){
							$total += format_angka($row);
						}
					}

					$this->db->trans_begin();
					$this->db->where([
						'user_id' => $p['employee_id'],
						'deduction_month' => $this->input->get('month'),
						'deduction_year'  => $this->input->get('year')
					])->delete('payroll_deduction');

					$this->db->insert_batch('payroll_deduction', $data);

					if($this->db->trans_status()){
						$this->db->trans_commit();
						$res = [
							'status'  => true,
							'total'	  => $total,
							'message' => 'Potongan pribadi berhasil disimpan'
						];

					}else{
						$this->db->trans_rollback();
						$res = [
							'status'  => false,
							'message' => 'Potongan pribadi gagal disimpan'
						];
					}

				}else{
					$res = [
						'status'  => false,
						'message' => 'Komponen potongan pribadi tidak ada, harap input pada menu master data'
					];
				}
				
			}else{
				$res = [
					'status'  => false,
					'message' => 'Data karyawan tidak diketahui'
				];
			}

			echo json_encode($res);
		}else{
			show_404();
		}
	}

	public function template_component($month, $year){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr'])){
			$branch_id = $this->_payroll_branch_id();
			if(!$this->_payroll_can_import($branch_id, $month, $year)){
				show_404();
			}

			$employees = $this->_payroll_import_employees($branch_id);
			$insentif = $this->_payroll_import_insentif($branch_id);
			$deduction = $this->_payroll_import_deduction($branch_id);
			$existing_insentif = $this->_payroll_existing_insentif($branch_id, $month, $year);
			$existing_deduction = $this->_payroll_existing_deduction($branch_id, $month, $year);

			$spreadsheet = new Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();
			$sheet->setTitle('Komisi Potongan');
			$headers = ['ID Fingerprint', 'Nama Karyawan'];
			foreach($insentif as $row){ $headers[] = 'INSENTIF #'.$row['id'].' '.$row['insentif_name']; }
			foreach($deduction as $row){ $headers[] = 'POTONGAN #'.$row['id'].' '.$row['deduction_name']; }
			$sheet->fromArray($headers, null, 'A1');

			$r = 2;
			foreach($employees as $employee){
				$c = 1;
				$sheet->setCellValueExplicitByColumnAndRow($c++, $r, $employee['employee_code'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$sheet->setCellValueByColumnAndRow($c++, $r, $employee['first_name']);
				foreach($insentif as $row){
					$amount = isset($existing_insentif[$employee['id']][$row['id']]) ? $existing_insentif[$employee['id']][$row['id']] : 0;
					$sheet->setCellValueByColumnAndRow($c++, $r, $amount);
				}
				foreach($deduction as $row){
					$amount = isset($existing_deduction[$employee['id']][$row['id']]) ? $existing_deduction[$employee['id']][$row['id']] : 0;
					$sheet->setCellValueByColumnAndRow($c++, $r, $amount);
				}
				$r++;
			}

			foreach(range('A', 'Z') as $column){ $sheet->getColumnDimension($column)->setWidth(18); }
			$this->_download_spreadsheet($spreadsheet, 'template_komisi_potongan_'.$month.'_'.$year.'.xlsx');
			return;
		}
		show_404();
	}

	public function template_overtime($month, $year){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr'])){
			$branch_id = $this->_payroll_branch_id();
			if(!$this->_payroll_can_import($branch_id, $month, $year)){
				show_404();
			}

			$employees = $this->_payroll_import_employees($branch_id);
			$spreadsheet = new Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();
			$sheet->setTitle('Lembur');
			$sheet->fromArray(['ID Fingerprint', 'Nama Karyawan', 'Tanggal Lembur', 'Jam Lembur'], null, 'A1');
			$sheet->fromArray(['Contoh: 257', 'Contoh Nama', '2026-04-01', '2.5'], null, 'A2');
			$r = 3;
			foreach($employees as $employee){
				$sheet->setCellValueExplicit('A'.$r, $employee['employee_code'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
				$sheet->setCellValue('B'.$r, $employee['first_name']);
				$r++;
			}
			foreach(['A' => 18, 'B' => 32, 'C' => 18, 'D' => 14] as $column => $width){
				$sheet->getColumnDimension($column)->setWidth($width);
			}
			$this->_download_spreadsheet($spreadsheet, 'template_lembur_'.$month.'_'.$year.'.xlsx');
			return;
		}
		show_404();
	}

	public function import_component($month, $year){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr'])){
			$branch_id = $this->_payroll_branch_id();
			if(!$this->_payroll_can_import($branch_id, $month, $year)){
				$this->_payroll_import_redirect($month, $year, $branch_id, 'Payroll sudah lock. Rollback dahulu jika ingin import ulang.', 'danger');
				return;
			}

			if(empty($_FILES['excel_file']['name'])){
				$this->_payroll_import_redirect($month, $year, $branch_id, 'File import belum dipilih.', 'danger');
				return;
			}

			$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($_FILES['excel_file']['tmp_name']);
			$sheet = $reader->load($_FILES['excel_file']['tmp_name'])->getActiveSheet()->toArray(null, true, true, false);
			if(count($sheet) < 2){
				$this->_payroll_import_redirect($month, $year, $branch_id, 'File import kosong.', 'danger');
				return;
			}

			$employees = $this->_payroll_employee_map($branch_id);
			$headers = $sheet[0];
			$columns = [];
			foreach($headers as $index => $header){
				if(preg_match('/^(INSENTIF|POTONGAN)\s+#(\d+)/i', trim((string)$header), $match)){
					$columns[$index] = [
						'type' => strtolower($match[1]) == 'insentif' ? 'insentif' : 'deduction',
						'id' => (int)$match[2]
					];
				}
			}

			$time = date('Y-m-d H:i:s');
			$imported = $missing = $empty = 0;
			$insentif_rows = $deduction_rows = $user_ids = [];
			for($i = 1; $i < count($sheet); $i++){
				$row = $sheet[$i];
				$code = isset($row[0]) ? trim((string)$row[0]) : '';
				if($code == ''){ $empty++; continue; }
				if(!isset($employees[$code])){ $missing++; continue; }
				$employee = $employees[$code];
				$user_ids[$employee['id']] = $employee['id'];
				$imported++;

				foreach($columns as $index => $column){
					$amount = $this->_payroll_import_amount(isset($row[$index]) ? $row[$index] : 0);
					if($amount <= 0){ continue; }
					if($column['type'] == 'insentif'){
						$insentif_rows[] = [
							'user_id' => $employee['id'],
							'insentif_id' => $column['id'],
							'insentif_year' => $year,
							'insentif_month' => $month,
							'insentif_amount' => $amount,
							'created_at' => $time
						];
					}else{
						$deduction_rows[] = [
							'user_id' => $employee['id'],
							'deduction_id' => $column['id'],
							'deduction_year' => $year,
							'deduction_month' => $month,
							'deduction_amount' => $amount,
							'created_at' => $time
						];
					}
				}
			}

			$this->db->trans_begin();
			if(!empty($user_ids)){
				$this->db->where_in('user_id', array_values($user_ids))->where(['insentif_month' => $month, 'insentif_year' => $year])->delete('payroll_insentif');
				$this->db->where_in('user_id', array_values($user_ids))->where(['deduction_month' => $month, 'deduction_year' => $year])->delete('payroll_deduction');
			}
			if(!empty($insentif_rows)){ $this->db->insert_batch('payroll_insentif', $insentif_rows); }
			if(!empty($deduction_rows)){ $this->db->insert_batch('payroll_deduction', $deduction_rows); }

			if($this->db->trans_status()){
				$this->db->trans_commit();
				$message = 'Import komisi/potongan selesai. Karyawan diproses: '.$imported.', komisi: '.count($insentif_rows).', potongan: '.count($deduction_rows).', ID tidak cocok: '.$missing.'.';
				$this->_payroll_import_redirect($month, $year, $branch_id, $message, 'success');
				return;
			}

			$this->db->trans_rollback();
			$this->_payroll_import_redirect($month, $year, $branch_id, 'Import komisi/potongan gagal disimpan.', 'danger');
			return;
		}
		show_404();
	}

	public function import_overtime($month, $year){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr'])){
			$branch_id = $this->_payroll_branch_id();
			if(!$this->_payroll_can_import($branch_id, $month, $year)){
				$this->_payroll_import_redirect($month, $year, $branch_id, 'Payroll sudah lock. Rollback dahulu jika ingin import ulang.', 'danger');
				return;
			}

			if(empty($_FILES['excel_file']['name'])){
				$this->_payroll_import_redirect($month, $year, $branch_id, 'File import belum dipilih.', 'danger');
				return;
			}

			$range = $this->_payroll_period_range($month, $year);
			$employees = $this->_payroll_employee_map($branch_id);
			$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($_FILES['excel_file']['tmp_name']);
			$sheet = $reader->load($_FILES['excel_file']['tmp_name'])->getActiveSheet()->toArray(null, true, true, false);
			$time = date('Y-m-d H:i:s');
			$inserted = $updated = $missing = $invalid = $outside = 0;

			$this->db->trans_begin();
			for($i = 1; $i < count($sheet); $i++){
				$row = $sheet[$i];
				$code = isset($row[0]) ? trim((string)$row[0]) : '';
				$date = isset($row[2]) ? $this->_payroll_import_date($row[2]) : false;
				$hour = isset($row[3]) ? (float)str_replace(',', '.', $row[3]) : 0;
				if($code == '' && empty($row[2]) && empty($row[3])){ continue; }
				if($code == '' || !$date || $hour <= 0){ $invalid++; continue; }
				if(!isset($employees[$code])){ $missing++; continue; }
				if($date < $range['from'] || $date > $range['to']){ $outside++; continue; }

				$employee = $employees[$code];
				$existing = $this->db->where([
					'user_id' => $employee['id'],
					'overtime_date' => $date
				])->where_in('overtime_status', ['approve', 'pending'])->get('overtime')->row_array();

				if(!empty($existing)){
					$this->db->where('id', $existing['id'])->update('overtime', [
						'overtime_hour' => $hour,
						'overtime_status' => 'approve',
						'confirm_at' => $time,
						'updated_at' => $time
					]);
					$updated++;
				}else{
					$this->db->insert('overtime', [
						'user_id' => $employee['id'],
						'overtime_hour' => $hour,
						'overtime_date' => $date,
						'overtime_status' => 'approve',
						'created_at' => $time,
						'confirm_at' => $time
					]);
					$inserted++;
				}
			}

			if($this->db->trans_status()){
				$this->db->trans_commit();
				$message = 'Import lembur selesai. Baru: '.$inserted.', diperbarui: '.$updated.', ID tidak cocok: '.$missing.', tanggal di luar periode: '.$outside.', baris invalid: '.$invalid.'.';
				$this->_payroll_import_redirect($month, $year, $branch_id, $message, 'success');
				return;
			}

			$this->db->trans_rollback();
			$this->_payroll_import_redirect($month, $year, $branch_id, 'Import lembur gagal disimpan.', 'danger');
			return;
		}
		show_404();
	}

	public function insert_out_work($payroll_id){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){
			$find['id'] = $payroll_id;
			if($this->role != 'admin'){
				$find['branch_id'] = $this->userdata->branch_id;
			}

			$p = $this->input->post();
			$p['out_work_nominal'] = format_angka($p['out_work_nominal']);

			$this->form_validation->set_data($p);
			$this->form_validation->set_rules('user_id', 'User ID', 'required|numeric');
			$this->form_validation->set_rules('subdivision_id', 'Subdivision ID', 'required|numeric');
			$this->form_validation->set_rules('payroll_detail_id', 'Payroll Detail ID', 'required|numeric');
			$this->form_validation->set_rules('out_work_nominal', 'Nominal', 'required|numeric|greater_than[-1]');
		
			if($this->form_validation->run() == TRUE){
				$cek = $this->db->where('id', $p['payroll_detail_id'])->get('payroll_detail');

				if($this->payroll->get_detail($find)->row_array() && $cek->num_rows() > 0){
					$payroll_detail = $cek->row_array();
					$thp = $payroll_detail['salary_thp'] + $payroll_detail['salary_out_work'] - $p['out_work_nominal'];

					if($thp >= 0){
						$this->db->trans_begin();

						$this->db->where('id', $p['payroll_detail_id'])
								 ->update('payroll_detail', [
								 	'salary_out_work' => $p['out_work_nominal'],
								 	'salary_thp'	  => $thp
								 ]);

						if($this->db->trans_status()){
							$this->db->trans_commit();
							$res = [
								'status'  => true,
								'message' => 'Data berhasil dimasukkan',
								'payroll' => $this->_getLastestPayrollEmployee($payroll_id, $p['payroll_detail_id'], $p['subdivision_id'])
							];

						}else{
							$this->db->trans_rollback();
							$res = [
								'status'  => false,
								'message' => 'Terjadi kesalahan, data gagal dimasukkan'
							];
						}

					}else{
						$res = [
							'status'  => false,
							'message' => 'Total THP Karyawan tidak boleh kurang dari Rp. 0'
						];
					}
					

				}else{
					$res = [
						'status'  => false,
						'message' => 'Payroll ID tidak ditemukan'
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

	public function insert_out_together($payroll_id){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){
			$find['id'] = $payroll_id;
			if($this->role != 'admin'){
				$find['branch_id'] = $this->userdata->branch_id;
			}

			$p = $this->input->post();
			$p['out_together_nominal'] = format_angka($p['out_together_nominal']);

			$this->form_validation->set_data($p);
			$this->form_validation->set_rules('user_id', 'User ID', 'required|numeric');
			$this->form_validation->set_rules('subdivision_id', 'Subdivision ID', 'required|numeric');
			$this->form_validation->set_rules('payroll_detail_id', 'Payroll Detail ID', 'required|numeric');
			$this->form_validation->set_rules('out_together_nominal', 'Nominal', 'required|numeric|greater_than[-1]');
		
			if($this->form_validation->run() == TRUE){
				$cek = $this->db->where('id', $p['payroll_detail_id'])->get('payroll_detail');

				if($this->payroll->get_detail($find)->row_array() && $cek->num_rows() > 0){
					$payroll_detail = $cek->row_array();
					$thp = $payroll_detail['salary_thp'] + $payroll_detail['salary_out_together'] - $p['out_together_nominal'];

					if($thp >= 0){
						$this->db->trans_begin();

						$this->db->where('id', $p['payroll_detail_id'])
								 ->update('payroll_detail', [
								 	'salary_out_together' => $p['out_together_nominal'],
								 	'salary_thp'	  => $thp
								 ]);

						if($this->db->trans_status()){
							$this->db->trans_commit();
							$res = [
								'status'  => true,
								'message' => 'Data berhasil dimasukkan',
								'payroll' => $this->_getLastestPayrollEmployee($payroll_id, $p['payroll_detail_id'], $p['subdivision_id'])
							];

						}else{
							$this->db->trans_rollback();
							$res = [
								'status'  => false,
								'message' => 'Terjadi kesalahan, data gagal dimasukkan'
							];
						}

					}else{
						$res = [
							'status'  => false,
							'message' => 'Total THP Karyawan tidak boleh kurang dari Rp. 0'
						];
					}
					

				}else{
					$res = [
						'status'  => false,
						'message' => 'Payroll ID tidak ditemukan'
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

	public function rollback(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){
			$find['id'] = $this->input->post('payroll_id');
			if($this->role != 'admin'){
				$find['branch_id'] = $this->userdata->branch_id;
			}

			if($this->payroll->get_detail($find)->row_array()){
				$this->db->where($find)->delete('payroll');

				if($this->db->affected_rows() > 0){
					$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> Penggajian berhasil dirollback', 'success'));
					$res = [
						'status'  => true,
						'message' => 'Payroll berhasil dirollback'
					];

				}else{
					$res = [
						'status'  => false,
						'message' => 'Terjadi kesalahan, data gagal dirollback'
					];
				}

			}else{
				$res = [
					'status'  => false,
					'message' => 'Payroll ID tidak ditemukan'
				];
			}

			echo json_encode($res);

		}else{
			show_404();
		}
	}

	public function rollback_to_lock($payroll_id){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){
			$find['id'] = $payroll_id;
			if($this->role != 'admin'){
				$find['branch_id'] = $this->userdata->branch_id;
			}

			$cek = $this->payroll->get_detail($find);
			if($cek->num_rows() > 0){
				$payroll = $cek->row_array();

				if($payroll['created_rollback_at'] == ''){
					$data['created_rollback_at'] = date('Y-m-d H:i:s');
				}else{
					$data['updated_rollback_at'] = date('Y-m-d H:i:s');
				}
				$data['is_final'] = '0';

				$this->db->where($find)->update('payroll', $data);

				if($this->db->affected_rows() > 0){
					$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> Penggajian berhasil dirollback', 'success'));
					$res = [
						'status'  => true,
						'message' => 'Penggajian berhasil dikembalikan ke tahap lock'
					];

				}else{
					$res = [
						'status'  => false,
						'message' => 'Terjadi kesalahan, data gagal dirollback'
					];
				}

			}else{
				$res = [
					'status'  => false,
					'message' => 'Payroll ID tidak ditemukan'
				];
			}

			echo json_encode($res);

		}else{
			show_404();
		}
	}

	public function save_payroll($payroll_id){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){
			$find['id'] = $payroll_id;
			$find['is_final'] = '0';
			if($this->role != 'admin'){
				$find['branch_id'] = $this->userdata->branch_id;
			}

			$cek = $this->payroll->get_detail($find);
			if($cek->num_rows() > 0){
				$payroll = $cek->row_array();
				
				$getPayroll = $this->db->select('
											SUM(salary_out_together) AS out_together,
											SUM(salary_out_deduction) AS out_deduction,
											SUM(salary_out_fine) AS out_fine,
											SUM(salary_out_health) AS out_health,
											SUM(salary_out_work) AS out_work,
											SUM(salary_in_basic) AS in_basic,
											SUM(salary_in_overtime) AS in_overtime,
											SUM(salary_in_insentive) AS in_insentive,
											SUM(salary_thp) AS thp,
											SUM(salary_debt) AS debt
									   ')
									   ->where('payroll_id', $payroll_id)
									   ->get('payroll_detail')->row_array();

				$data = [
					'total_salary_in_basic' => $getPayroll['in_basic'],
					'total_salary_in_overtime' => $getPayroll['in_overtime'],
					'total_salary_in_insentive' => $getPayroll['in_insentive'],
					'total_salary_out_fine' => $getPayroll['out_fine'],
					'total_salary_out_work' => $getPayroll['out_work'],
					'total_salary_out_health' => $getPayroll['out_health'],
					'total_salary_out_together' => $getPayroll['out_together'],
					'total_salary_out_deduction' => $getPayroll['out_deduction'],
					'total_salary_thp' => $getPayroll['thp'],
					'total_salary_debt' => $getPayroll['debt'],
					'out_together_nominal' => $getPayroll['out_together']
				];

				$data['is_final'] = '1';
				if($payroll['created_final_at'] == ''){
					$data['created_final_at'] = date('Y-m-d H:i:s');
				}else{
					$data['updated_final_at'] = date('Y-m-d H:i:s');
				}

				$this->db->where($find)->update('payroll', $data);

				if($this->db->affected_rows() > 0){
					$this->session->set_flashdata('alert_message', show_alert('<i class="fa fa-check-circle"></i> Data penggajian periode ini berhasil disimpan', 'success'));
					$res = [
						'status'  => true,
						'message' => 'Data penggajian periode ini berhasil disimpan'
					];

				}else{
					$res = [
						'status'  => false,
						'message' => 'Terjadi kesalahan, data gagal disimpan'
					];
				}

			}else{
				$res = [
					'status'  => false,
					'message' => 'Payroll ID tidak ditemukan'
				];
			}

			echo json_encode($res);

		}else{
			show_404();
		}
	}

	public function excel($month, $year){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor'])){
			$data['month']  = $month;
			$data['year']   = $year;

			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
				$data['branch'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$find = [
				'month'		=> $month,
				'year'		=> $year,
				'branch_id' => $branch_id
			];

			$payroll = $this->payroll->get_detail($find)->row_array();
			$data['branch_id']  = $branch_id;

			$insentiveHeader = [];
			$deductionHeader = [];

			if(!empty($payroll)){
				$payroll_list = $this->payroll->get_payment_list_by_id($payroll['id']);
				$attendance = $this->_toResponsePaymentList($payroll_list); 
				$branch_detail = $this->branch->get_detail('id', $branch_id)->row_array();
				
				$insentiveAll = [];
				$deductionAll = [];
				$insentiveHeader = json_decode($payroll_list[0]['payroll_insentive'], true)['list'];
				$deductionHeader = json_decode($payroll_list[0]['payroll_deduction'], true)['list'];

				$spreadsheet = new Spreadsheet();
				$sheet = $spreadsheet->getActiveSheet();
				$sheet->mergeCells('A1:E1');
				$sheet->setCellValue('A1', 'DAFTAR PENGGAJIAN');
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
				$sheet->getColumnDimension('B')->setWidth(20);
				$sheet->getColumnDimension('C')->setWidth(20);
				$sheet->getColumnDimension('D')->setWidth(15);
				$sheet->getColumnDimension('E')->setWidth(20);
				$sheet->getColumnDimension('F')->setWidth(20);
				$sheet->getColumnDimension('G')->setWidth(20);

				$sheet->mergeCells('A2:E2');
				$sheet->setCellValue('A2', 'Cabang : '.$branch_detail['branch_name'].' ['.$branch_detail['branch_code'].'] - Kota '.$branch_detail['city']);
				$sheet->mergeCells('A3:E3');
				$sheet->setCellValue('A3', 'Periode : '.get_monthname($month)." ".$year);

				$sheet->mergeCells('A4:A5');
				$sheet->setCellValue('A4', 'NAMA KARYAWAN');
				$sheet->mergeCells('B4:B5');
				$sheet->setCellValue('B4', 'NIK');
				$sheet->mergeCells('C4:C5');
				$sheet->setCellValue('C4', 'POSISI');
				$sheet->mergeCells('D4:D5');
				$sheet->setCellValue('D4', 'KEHADIRAN');
				$sheet->mergeCells('E4:E5');
				$sheet->setCellValue('E4', 'LAMA LEMBUR (Jam)');

				//Income
				$sheet->mergeCells('F4:G4');
				$sheet->setCellValue('F4', 'URAIAN GAJI');
				$sheet->setCellValue('F5', 'Pokok');
				$sheet->setCellValue('G5', 'Lembur');

				// -- Insentive
				$index = 0;
				$startInsentiveIndex = 8;
				$endInsentiveIndex = $startInsentiveIndex + count($insentiveHeader);
				for ($i = $startInsentiveIndex; $i < $endInsentiveIndex; $i++) {
					$sheet->getColumnDimension(EXCEL_COLUMN[$i])->setWidth(25);
					$sheet->setCellValue(EXCEL_COLUMN[$i].'5', '(+) '.$insentiveHeader[$index]['name']);
					$index++;
				}
				$sheet->mergeCells('H4:'.EXCEL_COLUMN[$endInsentiveIndex-1].'4');
				$sheet->setCellValue('H4', 'KOMISI');
				
				//Deduction
				$startDeductionIndex = $endInsentiveIndex;
				$index = 0;
				$startDynamicDeductionIndex = $startDeductionIndex + 4;
				$endDynamicDeductionIndex = $startDynamicDeductionIndex + count($deductionHeader);
				for ($i = $startDynamicDeductionIndex; $i < $endDynamicDeductionIndex; $i++) {
					$sheet->getColumnDimension(EXCEL_COLUMN[$i])->setWidth(25);
					$sheet->setCellValue(EXCEL_COLUMN[$i].'5', '(-) '.$deductionHeader[$index]['name']);
					$index++;
				}
				$sheet->mergeCells(EXCEL_COLUMN[$startDynamicDeductionIndex].'4:'.EXCEL_COLUMN[$endDynamicDeductionIndex-1].'4');

				$endDeductionIndex = $endDynamicDeductionIndex;
				$sheet->mergeCells(EXCEL_COLUMN[$startDeductionIndex].'4:'.EXCEL_COLUMN[$endDeductionIndex-'1'].'4');
				$sheet->setCellValue(EXCEL_COLUMN[$startDeductionIndex].'4', 'PENGURANG');
				$sheet->setCellValue(EXCEL_COLUMN[$startDeductionIndex].'5', 'Denda');
				$sheet->setCellValue(EXCEL_COLUMN[$startDeductionIndex + 1].'5', 'Ketenagakerjaan');
				$sheet->setCellValue(EXCEL_COLUMN[$startDeductionIndex + 2].'5', 'Pecah Bersama');
				
				$sheet->getColumnDimension(EXCEL_COLUMN[$startDeductionIndex])->setWidth(25);
				$sheet->getColumnDimension(EXCEL_COLUMN[$startDeductionIndex + 1])->setWidth(25);
				$sheet->getColumnDimension(EXCEL_COLUMN[$startDeductionIndex + 2])->setWidth(25);

				$thpIndex = $endDynamicDeductionIndex;
				$sheet->mergeCells(EXCEL_COLUMN[$thpIndex].'4:'.EXCEL_COLUMN[$thpIndex].'5');
				$sheet->setCellValue(EXCEL_COLUMN[$thpIndex].'4', 'THP');
				$sheet->getColumnDimension(EXCEL_COLUMN[$thpIndex])->setWidth(25);

				$accountNameIndex = $thpIndex + 1;
				$sheet->mergeCells(EXCEL_COLUMN[$accountNameIndex].'4:'.EXCEL_COLUMN[$accountNameIndex].'5');
				$sheet->setCellValue(EXCEL_COLUMN[$accountNameIndex].'4', 'PEMILIK REKENING');
				$sheet->getColumnDimension(EXCEL_COLUMN[$accountNameIndex])->setWidth(25);

				$bankIndex = $accountNameIndex +1;
				$sheet->mergeCells(EXCEL_COLUMN[$bankIndex].'4:'.EXCEL_COLUMN[$bankIndex].'5');
				$sheet->setCellValue(EXCEL_COLUMN[$bankIndex].'4', 'BANK');
				$sheet->getColumnDimension(EXCEL_COLUMN[$bankIndex])->setWidth(25);

				$accountNumberIndex = $bankIndex + 1;
				$sheet->mergeCells(EXCEL_COLUMN[$accountNumberIndex].'4:'.EXCEL_COLUMN[$accountNumberIndex].'5');
				$sheet->setCellValue(EXCEL_COLUMN[$accountNumberIndex].'4', 'NO REKENING');
				$sheet->getColumnDimension(EXCEL_COLUMN[$accountNumberIndex])->setWidth(25);

				$headerStyle = [
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
		        ];

				$sheet->getStyle('A4:E5')->applyFromArray($headerStyle);
				$sheet->getStyle('F4:'.EXCEL_COLUMN[$endInsentiveIndex].'4')->applyFromArray($headerStyle);
				$sheet->getStyle(EXCEL_COLUMN[$startDeductionIndex].'4:'.EXCEL_COLUMN[$accountNumberIndex].'4')->applyFromArray($headerStyle);
				$sheet->getStyle('F5:'.EXCEL_COLUMN[$endInsentiveIndex].'5')->applyFromArray([
		        	'font' => [
		        		'bold' => true,
		        		'name' => 'Calibri',
		        		'color' => array('rgb' => 'ffffff'),
		        	],
		        	'fill' => [
						'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
						'color' => ['argb' => '34c38f']
					],
					'alignment' => [
						'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
						'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
					]
		        ]);


		        $sheet->getStyle(EXCEL_COLUMN[$startDeductionIndex].'5:'.EXCEL_COLUMN[$endDeductionIndex].'5')->applyFromArray([
		        	'font' => [
		        		'bold' => true,
		        		'name' => 'Calibri',
		        		'color' => array('rgb' => 'ffffff'),
		        	],
		        	'fill' => [
						'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
						'color' => ['argb' => 'f46a6a']
					],
					'alignment' => [
						'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
						'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
					]
		        ]);
		        $start = 6;

		        foreach ($attendance as $sub){
                	$salary_in_basic = $salary_in_overtime = $total_overtime_hour = $salary_in_insentive = $salary_out_fine = $salary_out_work = $salary_out_health = $salary_out_together = $salary_out_deduction = $salary_thp = 0;

                	$sheet->mergeCells('A'.$start.':'.EXCEL_COLUMN[$accountNumberIndex].$start);
                	$sheet->getStyle('A'.$start.':'.EXCEL_COLUMN[$accountNumberIndex].$start)->applyFromArray([
			        	'font' => [
			        		'bold' => true,
			        		'name' => 'Calibri',
			        		'color' => array('rgb' => '000000'),
			        	],
			        	'fill' => [
							'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
							'color' => ['argb' => 'ffd182']
						],
						'alignment' => [
							'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
						]
			        ]);
			        $sheet->setCellValue('A'.$start, strtoupper($sub['subdivision_name']));
			        $insentiveDivision = [];
			        $deductionDivision = [];

			        foreach ($sub['list'] as $row) { 
			        	$start++;
                        $salary_in_basic += $row['salary_in_basic'];
                        $salary_in_overtime += $row['salary_in_overtime'];
                        $total_overtime_hour += $row['total_overtime_hour'];
                        $salary_in_insentive += $row['salary_in_insentive'];
                        $salary_out_fine += $row['salary_out_fine'];
                        $salary_out_work += $row['salary_out_work'];
                        $salary_out_health += $row['salary_out_health'];
                        $salary_out_deduction += $row['salary_out_deduction'];
                        $salary_out_together += $row['salary_out_together'];
                        $salary_thp += $row['salary_thp'];

                        $sheet->setCellValue('A'.$start, $row['first_name']);
                        $sheet->setCellValue('B'.$start, $row['contract_number']);
                        $sheet->setCellValue('C'.$start, $row['position_name']);
                        $sheet->setCellValue('D'.$start, $row['presence_count']." / ".$row['presence_max']);
                        $sheet->setCellValue('E'.$start, $row['total_overtime_hour']);
                        $sheet->setCellValue('F'.$start, format_rp($row['salary_in_basic']));
                        $sheet->setCellValue('G'.$start, format_rp($row['salary_in_overtime']));

                        $insentiveHeader = json_decode($row['payroll_insentive'], true)['list'];
                        $index = 0;
	                    for ($i = $startInsentiveIndex; $i < $endInsentiveIndex; $i++) {
							$sheet->setCellValue(EXCEL_COLUMN[$i].$start, format_rp($insentiveHeader[$index]['amount']));
							if(isset($insentiveDivision[$index])){
								$insentiveDivision[$index] += $insentiveHeader[$index]['amount'];
							}else{
								$insentiveDivision[$index] = $insentiveHeader[$index]['amount'];
							}

							if(isset($insentiveAll[$index])){
								$insentiveAll[$index] += $insentiveHeader[$index]['amount'];
							}else{
								$insentiveAll[$index] = $insentiveHeader[$index]['amount'];
							}
							$index++;
						}
                        $sheet->setCellValue(EXCEL_COLUMN[$endInsentiveIndex].$start, format_rp($row['salary_out_fine']));
                        $sheet->setCellValue(EXCEL_COLUMN[$endInsentiveIndex+1].$start, format_rp($row['salary_out_work']));
                        $sheet->setCellValue(EXCEL_COLUMN[$endInsentiveIndex+2].$start, format_rp($row['salary_out_together']));

                        $deductionHeader = json_decode($row['payroll_deduction'], true)['list'];
                        $index = 0;
	                    for ($i = $startDynamicDeductionIndex; $i < $endDynamicDeductionIndex; $i++) {
							$sheet->setCellValue(EXCEL_COLUMN[$i].$start, format_rp($deductionHeader[$index]['amount']));
							if(isset($deductionDivision[$index])){
								$deductionDivision[$index] += $deductionHeader[$index]['amount'];
							}else{
								$deductionDivision[$index] = $deductionHeader[$index]['amount'];
							}

							if(isset($deductionAll[$index])){
								$deductionAll[$index] += $deductionHeader[$index]['amount'];
							}else{
								$deductionAll[$index] = $deductionHeader[$index]['amount'];
							}
							$index++;
						}

                        $sheet->setCellValue(EXCEL_COLUMN[$endDynamicDeductionIndex].$start, format_rp($row['salary_thp']));
                        $sheet->setCellValue(EXCEL_COLUMN[$endDynamicDeductionIndex+1].$start, $row['account_name']);
                        $sheet->setCellValue(EXCEL_COLUMN[$endDynamicDeductionIndex+2].$start, $row['account_bank']);
                        $sheet->setCellValue(EXCEL_COLUMN[$endDynamicDeductionIndex+3].$start, $row['account_number']);
                        $sheet->getStyle('P'.$start)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);

                        $sheet->getStyle('F'.$start.':'.EXCEL_COLUMN[$thpIndex].$start)->applyFromArray([
							'alignment' => [
								'horizontal'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
							]
				        ]);
                    }

                    $start++;
                    $sheet->getStyle('A'.$start.':'.EXCEL_COLUMN[$accountNumberIndex].$start)->applyFromArray([
			        	'font' => [
			        		'bold' => true,
			        		'name' => 'Calibri',
			        		'color' => array('rgb' => '000000'),
			        	],
			        	'fill' => [
							'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
							'color' => ['argb' => 'ffe3b3']
						],
						'alignment' => [
							'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
						]
			        ]);
                    $sheet->mergeCells('A'.$start.':E'.$start);
                    $sheet->setCellValue('A'.$start, 'TOTAL');
                    $sheet->setCellValue('F'.$start, format_rp($salary_in_basic));
                    $sheet->setCellValue('G'.$start, format_rp($salary_in_overtime));

                    $index = 0;
                    for ($i = $startInsentiveIndex; $i < $endInsentiveIndex; $i++) {
						$sheet->setCellValue(EXCEL_COLUMN[$i].$start, format_rp($insentiveDivision[$index]));
						$index++;
					}

					$index = 0;
                    for ($i = $startDynamicDeductionIndex; $i < $endDynamicDeductionIndex; $i++) {
						$sheet->setCellValue(EXCEL_COLUMN[$i].$start, format_rp($deductionDivision[$index]));
						$index++;
					}

                    $sheet->setCellValue(EXCEL_COLUMN[$endInsentiveIndex].$start, format_rp($salary_out_fine));
                    $sheet->setCellValue(EXCEL_COLUMN[$endInsentiveIndex+1].$start, format_rp($salary_out_work));
                    $sheet->setCellValue(EXCEL_COLUMN[$endInsentiveIndex+2].$start, format_rp($salary_out_together));

                    $sheet->setCellValue(EXCEL_COLUMN[$endDynamicDeductionIndex].$start, format_rp($salary_thp));
                    $sheet->getStyle('F'.$start.':'.EXCEL_COLUMN[$thpIndex].$start)->applyFromArray([
						'alignment' => [
							'horizontal'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
						]
			        ]);

                    $start++;
               	}

               	$sheet->getStyle('A'.$start.':'.EXCEL_COLUMN[$accountNumberIndex].$start)->applyFromArray([
		        	'font' => [
		        		'bold' => true,
		        		'name' => 'Calibri',
		        		'color' => array('rgb' => '000000'),
		        	],
		        	'fill' => [
						'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
						'color' => ['argb' => 'cdcdcd']
					],
					'alignment' => [
						'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
					]
		        ]);
		        $sheet->mergeCells('A'.$start.':E'.$start);
                $sheet->setCellValue('A'.$start, 'TOTAL SEMUA');
                $sheet->setCellValue('F'.$start, format_rp($payroll['total_salary_in_basic']));
                $sheet->setCellValue('G'.$start, format_rp($payroll['total_salary_in_overtime']));

                $index = 0;
                for ($i = $startInsentiveIndex; $i < $endInsentiveIndex; $i++) {
					$sheet->setCellValue(EXCEL_COLUMN[$i].$start, format_rp($insentiveAll[$index]));
					$index++;
				}

				$index = 0;
                for ($i = $startDynamicDeductionIndex; $i < $endDynamicDeductionIndex; $i++) {
					$sheet->setCellValue(EXCEL_COLUMN[$i].$start, format_rp($deductionAll[$index]));
					$index++;
				}

                $sheet->setCellValue(EXCEL_COLUMN[$endInsentiveIndex].$start, format_rp($payroll['total_salary_out_fine']));
                $sheet->setCellValue(EXCEL_COLUMN[$endInsentiveIndex+1].$start, format_rp($payroll['total_salary_out_work']));
                $sheet->setCellValue(EXCEL_COLUMN[$endInsentiveIndex+2].$start, format_rp($payroll['total_salary_out_together']));
                $sheet->setCellValue(EXCEL_COLUMN[$endDynamicDeductionIndex].$start, format_rp($payroll['total_salary_thp']));
                $sheet->getStyle('F'.$start.':'.EXCEL_COLUMN[$thpIndex].$start)->applyFromArray([
					'alignment' => [
						'horizontal'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
					]
		        ]);

				$title = "Daftar Penggajian Periode ".get_monthname($month)." ".$year." ".$branch_detail['branch_name']." - Kota ".$branch_detail['city'];
				$writer = new Xlsx($spreadsheet);
				$fileName = $title.'.xlsx';

				$this->output->set_header('Content-Type: application/vnd.ms-excel');
			    $this->output->set_header("Content-type: application/csv");
			    $this->output->set_header('Cache-Control: max-age=0');
			    $writer->save('./assets/export/'.$fileName); 
			    $filepath = file_get_contents('./assets/export/'.$fileName);
			    $this->load->helper('download');  
			    force_download($fileName, $filepath);

			}else{
				show_404();
			}
			
		}else{
			show_404();
		}	
	}

	public function getEmployeePayroll($payrollID){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor'])){
			$payroll = $this->payroll->get_detail([
				'payroll.id' => $payrollID,
				'is_final' => '1'
			]);

			if($payroll->num_rows() > 0){
				$employeeIDs = $this->payroll->get_payment_list_by_id($payrollID);
				echo json_encode([
					'status' => true,
					'data'   => $employeeIDs
				]);

			}else{
				echo json_encode([
					'status' => true,
					'message' => 'Data penggajian ini belum digenerate',
					'data'   => $employeeIDs
				]);
			}

			
		}else{
			show_404();
		}
	}

	public function payrollExportMultiplePDF($payrollID){
		if(!in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor'])){
			//show_404();
		}

		if($this->role == 'admin'){
			$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
			$data['branch'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
		}else{
			$branch_id = $this->userdata->branch_id;
		}

		$find = [
			'payroll.id'	=> $payrollID,
			'is_final'		=> '1',
			'branch_id' 	=> $branch_id
		];

		$data['payroll']   = $this->payroll->get_detail($find)->row_array();
		$data['branch_id'] = $branch_id;

		if(!empty($data['payroll'])){
			$table = '_payrollTableDone';
			$data['branch_detail'] = $this->branch->get_detail('id', $branch_id)->row_array();
			$data['month'] = $data['payroll']['month'];
			$data['year'] = $data['payroll']['year'];

			$employeeIDs = $this->input->post('employee_ids');
			$paymentList = $this->payroll->get_payment_list_by_id($data['payroll']['id'], '', [], $employeeIDs);
			
			$this->load->helper('file');
			$payrollSlipPath = './payroll-slip/';
			delete_files($payrollSlipPath);

			foreach ($paymentList as $row) {
				$dompdf = new Dompdf();
				$customPaper = array(0,0,560,900);
				$dompdf->set_paper($customPaper);

				$options = new Options();
				$options->setChroot(FCPATH);
				$dompdf->setOptions($options);

				$data['attendance'][0] = $row;

				$payrollHTML = $this->load->view('hr/payroll/print_export', $data, true);
				$dompdf->loadHtml($payrollHTML);
				$dompdf->render();
				$output = $dompdf->output();
				$fileTitle = $row['first_name']." - ".$row['position_name'].' - '.get_monthname($data['payroll']['month']).' '.$data['payroll']['year'].' - '.$row['user_id'].'.pdf';
    			file_put_contents($payrollSlipPath.$fileTitle, $output);
			}

			$zipName = 'Slip Gaji - '.$data['branch_detail']['branch_name'].' - '.get_monthname($data['payroll']['month']).' '.$data['payroll']['year'].'.zip';
			$this->load->library('zip');
			$this->zip->read_dir($payrollSlipPath);
			$this->zip->archive($payrollSlipPath.$zipName);

			echo json_encode([
				'status' => true,
				'data' => [
					'file_url' => base_url($payrollSlipPath.$zipName) 
				]
			]);

		}else{
			show_404();
		}
        
	}

		private function _get_employee_payroll_data($year, $employee_id){
			$list = [];
			$m = 12;
			for ($i=1; $i <= $m; $i++) { 
				$pyr_detail = $this->db->where([
								'month' => $i,
								'year'  => $year,
								'user_id' => $employee_id,
								'is_final' => '1'
							])
							->join('payroll_detail', 'payroll_detail.payroll_id = payroll.id')
							->get('payroll')->row_array();

				$list[] = [
					'month' 	 => $i,
					'year'  	 => $year,
					'monthname'  => get_monthname($i),
					'fullstring' => get_monthname($i)." ".$year,
					'detail' 	 => $pyr_detail
				];
			}

			return $list;
		}

		private function _toResponsePaymentList($data){
			$res = [];

			foreach ($data as $row){
				$res[$row['subdivision_id']]['subdivision_id']   = $row['subdivision_id'];
				$res[$row['subdivision_id']]['subdivision_name'] = $row['subdivision_name'];
				$res[$row['subdivision_id']]['list'][] = $row;
			}

			return $res;
		}

		private function _payroll_branch_id(){
			if($this->role == 'admin'){
				return $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
			}
			return $this->userdata->branch_id;
		}

		/**
		 * Hitung & upsert potongan Izin Pulang Cepat untuk satu cabang+periode.
		 *
		 * Aturan:
		 *   - Agregat presence.early_leave_short_minutes per user di periode
		 *     payroll (START_PAYROLL_DATE bulan lalu -> END_PAYROLL_DATE bulan ini).
		 *   - Hanya hitung row presence_status='approved' (deny artinya < 5 jam
		 *     -> tidak hadir, tidak dapat potongan PLA terpisah).
		 *   - Amount = hourly_rate(salary, cal_days_in_month) * (total_short/60).
		 *   - Master deduction dipilih per branch via nama LIKE '%Pulang%Awal%'
		 *     atau '%Kekurangan%Jam%' (sudah ada untuk branch 1 & 2 di seed lama).
		 *   - Manual override menang: kalau row payroll_deduction utk
		 *     (user, deduction_id, month, year) sudah ada -> skip insert.
		 *
		 * Idempotent: aman dipanggil ulang karena cek exists sebelum insert.
		 */
		private function _seed_early_leave_deductions($branch_id, $month, $year, $days_in_month){
			$master = $this->db->where('branch_id', $branch_id)
				->group_start()
					->like('deduction_name', 'Pulang Lebih Awal')
					->or_like('deduction_name', 'Kekurangan Jam')
				->group_end()
				->where('deleted_at IS NULL', null, false)
				->get('deduction')->row_array();

			if(empty($master)){ return; }

			$period = attlog_presence_period_range($month, $year);

			$rows = $this->db->select('presence.user_id, users.salary, SUM(presence.early_leave_short_minutes) AS total_short_minutes', false)
				->join('users', 'users.id = presence.user_id')
				->join('position', 'position.id = users.position_id')
				->where('position.branch_id', $branch_id)
				->where('presence.flow_date >=', $period['from'])
				->where('presence.flow_date <=', $period['to'])
				->where('presence.is_early_leave', 1)
				->where('presence.presence_status', 'approved')
				->group_by('presence.user_id, users.salary')
				->get('presence')->result_array();

			if(empty($rows)){ return; }

			$time = date('Y-m-d H:i:s');
			foreach($rows as $row){
				$total_short = (int)$row['total_short_minutes'];
				if($total_short <= 0){ continue; }

				$amount = presence_early_leave_deduction_amount(
					$row['salary'], $days_in_month, $total_short
				);
				if($amount <= 0){ continue; }

				$exists = $this->db->where([
					'user_id' => $row['user_id'],
					'deduction_id' => $master['id'],
					'deduction_month' => $month,
					'deduction_year' => $year
				])->count_all_results('payroll_deduction');

				if($exists > 0){ continue; }

				$this->db->insert('payroll_deduction', [
					'user_id' => $row['user_id'],
					'deduction_id' => $master['id'],
					'deduction_month' => $month,
					'deduction_year' => $year,
					'deduction_amount' => $amount,
					'deduction_note' => 'Potongan Izin Pulang Lebih Awal (auto)',
					'created_at' => $time
				]);
			}
		}

		private function _payroll_can_import($branch_id, $month, $year){
			return $this->payroll->get_detail([
				'branch_id' => $branch_id,
				'month' => $month,
				'year' => $year
			])->num_rows() == 0;
		}

		private function _payroll_import_redirect($month, $year, $branch_id, $message, $type){
			$this->session->set_flashdata('alert_message', show_alert($message, $type));
			$get = $this->role == 'admin' ? '?branch_id='.$branch_id : '';
			redirect('hr/payroll/'.$month.'/'.$year.$get);
		}

		private function _payroll_import_employees($branch_id){
			return $this->employee->get_detail([
				'position.branch_id' => $branch_id,
				'users.active' => 1
			], '', '', ['first_name' => 'ASC'])->result_array();
		}

		private function _payroll_employee_map($branch_id){
			$map = [];
			foreach($this->_payroll_import_employees($branch_id) as $row){
				$map[trim((string)$row['employee_code'])] = $row;
			}
			return $map;
		}

		private function _payroll_import_insentif($branch_id){
			return $this->db->where('branch_id', $branch_id)
							->where('is_active', '1')
							->where('deleted_at', null)
							->order_by('insentif_name', 'ASC')
							->get('insentif')->result_array();
		}

		private function _payroll_import_deduction($branch_id){
			return $this->db->where('branch_id', $branch_id)
							->where('is_active', '1')
							->where('deleted_at', null)
							->order_by('deduction_name', 'ASC')
							->get('deduction')->result_array();
		}

		private function _payroll_existing_insentif($branch_id, $month, $year){
			$rows = $this->db->select('payroll_insentif.*')
							 ->join('users', 'users.id = payroll_insentif.user_id')
							 ->join('position', 'position.id = users.position_id')
							 ->where('position.branch_id', $branch_id)
							 ->where('insentif_month', $month)
							 ->where('insentif_year', $year)
							 ->get('payroll_insentif')->result_array();
			$map = [];
			foreach($rows as $row){
				$map[$row['user_id']][$row['insentif_id']] = $row['insentif_amount'];
			}
			return $map;
		}

		private function _payroll_existing_deduction($branch_id, $month, $year){
			$rows = $this->db->select('payroll_deduction.*')
							 ->join('users', 'users.id = payroll_deduction.user_id')
							 ->join('position', 'position.id = users.position_id')
							 ->where('position.branch_id', $branch_id)
							 ->where('deduction_month', $month)
							 ->where('deduction_year', $year)
							 ->get('payroll_deduction')->result_array();
			$map = [];
			foreach($rows as $row){
				$map[$row['user_id']][$row['deduction_id']] = $row['deduction_amount'];
			}
			return $map;
		}

		private function _payroll_period_range($month, $year){
			$month = str_pad($month, 2, '0', STR_PAD_LEFT);
			$raw = strtotime($year.'-'.$month.'-10 -1 months');
			return [
				'from' => date('Y-m-'.START_PAYROLL_DATE, $raw),
				'to' => $year.'-'.$month.'-'.END_PAYROLL_DATE
			];
		}

		private function _payroll_import_amount($value){
			return (int)preg_replace('/[^0-9\-]/', '', (string)$value);
		}

		private function _payroll_import_date($value){
			if(is_numeric($value)){
				try {
					return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
				} catch(\Exception $e) {
					return false;
				}
			}

			$value = trim((string)$value);
			foreach(['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y'] as $format){
				$date = \DateTime::createFromFormat($format, $value);
				if($date instanceof \DateTime){
					return $date->format('Y-m-d');
				}
			}

			$timestamp = strtotime($value);
			return $timestamp ? date('Y-m-d', $timestamp) : false;
		}

		private function _download_spreadsheet($spreadsheet, $filename){
			while(ob_get_level() > 0){ ob_end_clean(); }
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment; filename="'.$filename.'"');
			header('Cache-Control: max-age=0');
			$writer = new Xlsx($spreadsheet);
			$writer->save('php://output');
			exit;
		}

		private function _getLastestPayrollEmployee($payroll_id, $payroll_detail_id, $subdivision_id){
			$by_payroll = $this->db->select('SUM(salary_thp) AS total_thp, SUM(salary_out_work) AS total_out_work, SUM(salary_out_together) AS total_out_together')
								   ->where('payroll_id', $payroll_id)
								   ->get('payroll_detail')->row_array();

			$by_subdivision = $this->db->select('SUM(salary_thp) AS total_thp, SUM(salary_out_work) AS total_out_work, SUM(salary_out_together) AS total_out_together')
									   ->where('subdivision_id', $subdivision_id)
									   ->where('payroll_id', $payroll_id)
									   ->join('users', 'users.id = payroll_detail.user_id')
									   ->get('payroll_detail')->row_array();

			$by_employee = $this->db->select('SUM(salary_thp) AS total_thp, SUM(salary_out_work) AS total_out_work')
									->where('id', $payroll_detail_id)
									->get('payroll_detail')->row_array();

			return [
				'total_work_subdivision' => $by_subdivision['total_out_work'],
				'total_work_all'		 => $by_payroll['total_out_work'],
				'total_together_subdivision' => $by_subdivision['total_out_together'],
				'total_together_all'		 => $by_payroll['total_out_together'],
				'total_receive_subdivision' => $by_subdivision['total_thp'],
				'total_receive_all' => $by_payroll['total_thp'],
				'total_receive_employee' => $by_employee['total_thp']
			];
		}
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/

$route['default_controller'] = 'Auth';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

$route['authentication/login']  = 'Auth';
$route['do_login']				= 'Auth/do_login';
$route['logout']				= 'Auth/logout';

// WA Agent
$route['wa']					= 'Wa/index';
$route['wa/config']				= 'Wa/config';
$route['wa/save_config']		= 'Wa/save_config';
$route['wa/test_send']			= 'Wa/test_send';
$route['wa/send_rekap_pagi']	= 'Wa/send_rekap_pagi';
$route['wa/send_rekap_siang']	= 'Wa/send_rekap_siang';
$route['wa/send_notif_absen']	= 'Wa/send_notif_absen';
$route['wa/logs']				= 'Wa/logs';
$route['wa/cron/(:any)']		= 'Wa/cron/$1';

$route['authentication/forget/code/(:any)'] = 'Auth/forget_verify/$1';
$route['authentication/forget']    		    = 'Auth/forget';
$route['do_forget'] 						= 'Auth/do_forget';
$route['do_reset_password']					= 'Auth/reset_password';

$route['authentication/register'] = 'Auth/register';
$route['do_register']			  = 'Auth/do_register';
$route['authentication/verify/(:num)/(:any)'] = 'Auth/verify/$1/$2';

$route['user/profile'] = 'Profile';
$route['user_change_password'] = 'Profile/change_password';
$route['user_change_profile']  = 'Profile/change_profile';

$route['user/information']  = 'Profile/information';

// ADMIN

//============== ASSET ==================
$route['dashboard'] = 'Dashboard';
$route['dashboard/request'] = 'Dashboard/request';


// ============ MASTER DATA ================
$route['master_data/branch']	= 'Branch';
$route['insert_branch']	   		= 'Branch/insert';
$route['update_branch']			= 'Branch/update';
$route['delete_branch']			= 'Branch/delete';

$route['master_data/employee']	= 'Employee';
$route['insert_employee']	   	= 'Employee/insert';
$route['update_employee']		= 'Employee/update';
$route['delete_employee']		= 'Employee/delete';
$route['upload_employee']		= 'Employee/upload';
$route['export_employee']		= 'Employee/export';
$route['change_status_employee']= 'Employee/change_status';
$route['update_employee_cluster']    = 'Employee/change_cluster';

$route['master_data/position']	= 'Position';
$route['insert_position']	   	= 'Position/insert';
$route['update_position']		= 'Position/update';
$route['delete_position']		= 'Position/delete';

$route['master_data/subdepartement']	= 'Subdivision';
$route['insert_subdivision']	   	= 'Subdivision/insert';
$route['update_subdivision']		= 'Subdivision/update';
$route['delete_subdivision']		= 'Subdivision/delete';

$route['master_data/insentif']	= 'hr/Insentif';
$route['insert_insentif']	   	= 'hr/Insentif/insert';
$route['update_insentif']		= 'hr/Insentif/update';
$route['delete_insentif']		= 'hr/Insentif/delete';

$route['master_data/deduction']	= 'hr/Deduction';
$route['insert_deduction']	   	= 'hr/Deduction/insert';
$route['update_deduction']		= 'hr/Deduction/update';
$route['delete_deduction']		= 'hr/Deduction/delete';

//============= HR ======================
$route['hr/shift']		= 'hr/Shift';
$route['insert_shift']	= 'hr/Shift/insert';
$route['update_shift'] 	= 'hr/Shift/update';
$route['delete_shift'] 	= 'hr/Shift/delete';
$route['change_status_shift'] = 'hr/Shift/change_status';
$route['bulk_status_shift'] = 'hr/Shift/bulk_status';

$route['hr/cluster']		= 'hr/Cluster';
$route['get_rotation/(:num)'] = 'hr/Cluster/get_rotation/$1';
$route['insert_cluster']	= 'hr/Cluster/insert';
$route['update_cluster'] 	= 'hr/Cluster/update';
$route['delete_cluster'] 	= 'hr/Cluster/delete';

$route['hr/presence'] 				  = 'hr/Presence/index';
$route['hr/presence/(:num)/(:num)']   = 'hr/Presence/detail/$1/$2';
$route['hr/work-schedule/(:num)/(:num)'] = 'hr/Presence/work_schedule/$1/$2';
$route['update_presence']			  = 'hr/Presence/update';
$route['update_workhour'] 			  = 'hr/Presence/update_workhour';
$route['update_workpray'] 			  = 'hr/Presence/update_workpray';
$route['cancel_presence']			  = 'hr/Presence/cancel';

$route['upload_presence'] 			  = 'hr/Presence/upload';
$route['sync_presence_cloud'] 		  = 'hr/Presence/sync_cloud';
$route['sync_pray_cloud'] 		  	  = 'hr/Presence/sync_pray_cloud';
$route['clear_presence_period'] 	  = 'hr/Presence/clear_period';
$route['upload_work_schedule'] 		  = 'hr/Presence/upload_work_schedule';
$route['save_work_schedule_manual']   = 'hr/Presence/save_work_schedule_manual';
$route['load_work_schedule_excel']     = 'hr/Presence/load_work_schedule_excel';
$route['copy_previous_work_schedule']  = 'hr/Presence/copy_previous_work_schedule';
$route['upload_pray'] 			  	  = 'hr/Presence/upload_pray';

$route['export_work_schedule/(:num)/(:num)/(:num)'] = 'hr/Presence/export_work_schedule/$1/$2/$3';
$route['resetSchedule/(:num)/(:num)'] = 'hr/Presence/reset/$1/$2';

$route['hr/payroll'] = 'hr/Payroll';
$route['hr/payroll/(:num)/(:num)'] = 'hr/Payroll/detail/$1/$2';
$route['hr/payroll/(:num)/(:num)/print'] = 'hr/Payroll/print/$1/$2';
$route['hr/payroll/(:num)/(:num)/print/(:num)'] = 'hr/Payroll/print_slip/$1/$2/$3';
$route['hr/payroll/(:num)/(:num)/excel'] = 'hr/Payroll/excel/$1/$2';
$route['generate_payroll/(:num)/(:num)'] = 'hr/Payroll/generate/$1/$2';
$route['payroll_template_component/(:num)/(:num)'] = 'hr/Payroll/template_component/$1/$2';
$route['payroll_template_overtime/(:num)/(:num)'] = 'hr/Payroll/template_overtime/$1/$2';
$route['payroll_import_component/(:num)/(:num)'] = 'hr/Payroll/import_component/$1/$2';
$route['payroll_import_overtime/(:num)/(:num)'] = 'hr/Payroll/import_overtime/$1/$2';
$route['payroll_rollback'] = 'hr/Payroll/rollback';
$route['payroll_rollback_to_lock/(:any)'] = 'hr/Payroll/rollback_to_lock/$1';
$route['insert_out_work/(:any)'] = 'hr/Payroll/insert_out_work/$1';
$route['insert_out_together/(:any)'] = 'hr/Payroll/insert_out_together/$1';
$route['save_payroll/(:any)'] = 'hr/Payroll/save_payroll/$1';
$route['get_employee_with_payroll/(:num)'] = 'hr/Payroll/getEmployeePayroll/$1';
$route['payroll_multiple_export_pdf/(:num)'] = 'hr/Payroll/payrollExportMultiplePDF/$1';

$route['save_insentif']				= 'hr/Payroll/save_insentif';
$route['save_deduction']			= 'hr/Payroll/save_deduction';
$route['getInsentif/(:any)/(:num)'] = 'hr/Insentif/call_insentif/$1/$2';
$route['getDeduction/(:any)/(:num)'] = 'hr/Deduction/call_deduction/$1/$2';
$route['getFine/(:num)']			= 'hr/Insentif/call_fine/$1';
$route['getPresensi/(:num)']		= 'hr/Presence/call_presensi/$1';


// OVERTIME
$route['hr/overtime/list'] = 'hr/Overtime/index';
$route['hr/overtime/detail/(:num)'] = 'hr/Overtime/detail/$1';
$route['insert_overtime']  = 'hr/Overtime/insert';
$route['update_overtime']  = 'hr/Overtime/update';

$route['hr/overtime/acc']  = 'hr/Overtime/acc';
$route['hr/overtime/acc/detail/(:num)'] = 'hr/Overtime/detail_acc/$1';
$route['change_status_overtime/(:num)'] = 'hr/Overtime/change_status/$1';
$route['cancel_status_overtime/(:num)'] = 'hr/Overtime/cancel_status/$1';

//LEAVE
$route['hr/leave/list'] = 'hr/Leave/index';
$route['hr/leave/detail/(:num)'] = 'hr/Leave/detail/$1';
$route['insert_leave']  = 'hr/Leave/insert';

$route['hr/leave/acc']  = 'hr/Leave/acc';
$route['hr/leave/acc/detail/(:num)'] = 'hr/Leave/detail_acc/$1';
$route['change_status_leave/(:num)'] = 'hr/Leave/change_status/$1';
$route['cancel_status_leave/(:num)'] = 'hr/Leave/cancel_status/$1';

// ============ ATTENDANCE ================
$route['attendance']                    = 'Attendance/index';
$route['attendance/daily_report']       = 'Attendance/daily_report';
$route['attendance/machine_report']     = 'Attendance/machine_report';
$route['attendance/early_leave_report'] = 'Attendance/early_leave_report';

$route['panel/master_data/user'] 	= 'User';
$route['insert_user']		   		= 'User/insert';
$route['update_user']		   		= 'User/update';
$route['delete_user']		   		= 'User/delete';

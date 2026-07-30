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

// API JSON untuk PWA karyawan (stateless, token bearer; CSRF dikecualikan di config)
// Audit Log — riwayat & rollback perubahan data absensi (admin only)
$route['audit_log']                = 'AuditLog/index';
$route['audit_log/history/(:num)'] = 'AuditLog/history/$1';
$route['audit_log/rollback']               = 'AuditLog/rollback';
$route['audit_log/mass_rollback']          = 'AuditLog/mass_rollback';
$route['audit_log/mass_rollback_simulate'] = 'AuditLog/mass_rollback_simulate';
$route['audit_log/mass_rollback_execute']  = 'AuditLog/mass_rollback_execute';

// Laporan ketidakcocokan data dari karyawan via PWA
$route['report']               = 'Report/index';
$route['report/mark_read']     = 'Report/mark_read';
$route['report/mark_all_read'] = 'Report/mark_all_read';
$route['report/acc']           = 'Report/acc';
$route['report/edit']          = 'Report/edit';
$route['report/delete']        = 'Report/delete';
$route['api/submit_report']      = 'Api/submit_report';
$route['api/get_report_credits'] = 'Api/get_report_credits';

$route['api/demo_login']      = 'Api/demo_login';
$route['api/login']           = 'Api/login';
$route['api/profile']         = 'Api/profile';
$route['api/schedule']        = 'Api/schedule';
$route['api/payroll']         = 'Api/payroll';
$route['api/requests']        = 'Api/requests';
$route['api/submit_leave']    = 'Api/submit_leave';
$route['api/submit_overtime'] = 'Api/submit_overtime';
$route['api/bpjs']            = 'Api/bpjs';
$route['api/submit_bpjs']     = 'Api/submit_bpjs';

// API JSON admin/sistem eksternal (stateless, API-key bearer; CSRF dikecualikan di config via 'api/(.*)')
$route['api/admin/presence']                 = 'Api_admin/presence_list';
$route['api/admin/presence/update_workhour'] = 'Api_admin/update_workhour';
$route['api/admin/presence/update_shift']    = 'Api_admin/update_shift';
$route['api/admin/presence/cancel']          = 'Api_admin/cancel';
$route['api/admin/leave']                    = 'Api_admin_hr/leave_list';
$route['api/admin/leave/approve']            = 'Api_admin_hr/leave_approve';
$route['api/admin/leave/deny']               = 'Api_admin_hr/leave_deny';
$route['api/admin/overtime']                 = 'Api_admin_hr/overtime_list';
$route['api/admin/overtime/approve']         = 'Api_admin_hr/overtime_approve';
$route['api/admin/overtime/deny']            = 'Api_admin_hr/overtime_deny';
$route['api/admin/payroll']                  = 'Api_admin_hr/payroll_list';

$route['authentication/login']  = 'Auth';
$route['do_login']				= 'Auth/do_login';
$route['logout']				= 'Auth/logout';

// Mobile (Karyawan & Atasan)
$route['m']                     = 'M/index';
$route['m/presence']            = 'M/presence';
$route['m/schedule']            = 'M/schedule';
$route['m/leave']               = 'M/leave';
$route['m/overtime']            = 'M/overtime';
$route['m/payroll']             = 'M/payroll';
$route['m/approvals']           = 'M/approvals';
$route['m/submit_leave']        = 'M/submit_leave';
$route['m/submit_overtime']     = 'M/submit_overtime';
$route['m/approve_overtime']    = 'M/approve_overtime';
$route['m/approve_leave']       = 'M/approve_leave';

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
$route['change_status_branch']	= 'Branch/change_status';

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
$route['change_status_insentif']= 'hr/Insentif/change_status';

$route['master_data/deduction']	= 'hr/Deduction';
$route['insert_deduction']	   	= 'hr/Deduction/insert';
$route['update_deduction']		= 'hr/Deduction/update';
$route['delete_deduction']		= 'hr/Deduction/delete';
$route['change_status_deduction']= 'hr/Deduction/change_status';

$route['master_data/double_deduction_date'] = 'Double_deduction_date';
$route['insert_double_deduction_date'] = 'Double_deduction_date/insert';
$route['update_double_deduction_date'] = 'Double_deduction_date/update';
$route['delete_double_deduction_date'] = 'Double_deduction_date/delete';
$route['change_status_double_deduction_date'] = 'Double_deduction_date/change_status';

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
$route['import_sync_presence_cloud'] = 'hr/Presence/import_sync_preview';
$route['sync_pray_cloud'] 		  	  = 'hr/Presence/sync_pray_cloud';
$route['clear_presence_period'] 	  = 'hr/Presence/clear_period';
$route['upload_work_schedule'] 		  = 'hr/Presence/upload_work_schedule';
$route['save_work_schedule_manual']   = 'hr/Presence/save_work_schedule_manual';
$route['load_work_schedule_excel']     = 'hr/Presence/load_work_schedule_excel';
$route['copy_previous_work_schedule']  = 'hr/Presence/copy_previous_work_schedule';
$route['upload_pray'] 			  	  = 'hr/Presence/upload_pray';

$route['export_absen_report/(:num)/(:num)/(:num)'] = 'hr/Presence/export_absen_report/$1/$2/$3';
$route['export_work_schedule/(:num)/(:num)/(:num)'] = 'hr/Presence/export_work_schedule/$1/$2/$3';
$route['resetSchedule/(:num)/(:num)'] = 'hr/Presence/reset/$1/$2';

$route['hr/payroll'] = 'hr/Payroll';
$route['hr/payroll/(:num)/(:num)'] = 'hr/Payroll/detail/$1/$2';
$route['hr/payroll/(:num)/(:num)/print'] = 'hr/Payroll/print/$1/$2';
$route['hr/payroll/(:num)/(:num)/print/(:num)'] = 'hr/Payroll/print_slip/$1/$2/$3';
$route['hr/payroll/(:num)/(:num)/excel'] = 'hr/Payroll/excel/$1/$2';
$route['hr/payroll/(:num)/(:num)/excel_v2'] = 'hr/Payroll/excel_v2/$1/$2';
$route['hr/payroll/(:num)/(:num)/print_by_penempatan'] = 'hr/Payroll/print_by_penempatan/$1/$2';
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
$route['recalc_auto_insentif/(:num)/(:num)/(:num)'] = 'hr/Payroll/recalc_auto_insentif/$1/$2/$3';
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
$route['edit_leave/(:num)'] = 'hr/Leave/edit/$1';

// ============ ATTENDANCE ================
$route['attendance']                    = 'Attendance/index';
$route['attendance/daily_report']       = 'Attendance/daily_report';
$route['attendance/machine_report']     = 'Attendance/machine_report';
$route['attendance/early_leave_report'] = 'Attendance/early_leave_report';

// Payroll Simulator (read-only API, auth via CI3 session)
$route['payroll_sim/employees']  = 'PayrollSim/employees';
$route['payroll_sim/salary']     = 'PayrollSim/salary';
$route['payroll_sim/insentif']   = 'PayrollSim/insentif';
$route['payroll_sim/deduction']  = 'PayrollSim/deduction';
$route['payroll_sim/branches']   = 'PayrollSim/branches';

// Payroll Importer (impor komisi/potongan dari Excel, auth via CI3 session)
$route['payroll_import/branches'] = 'PayrollImporter/branches';
$route['payroll_import/targets']  = 'PayrollImporter/targets';
$route['payroll_import/parse']    = 'PayrollImporter/parse';
$route['payroll_import/commit']   = 'PayrollImporter/commit';

// DAT Reader (baca .dat mesin → mirror+work editable → dorong ke presence, auth via CI3 session)
$route['dat_reader/branches']    = 'DatReader/branches';
$route['dat_reader/period']      = 'DatReader/period';
$route['dat_reader/sync_upload'] = 'DatReader/sync_upload';
$route['dat_reader/sync_cloud']  = 'DatReader/sync_cloud';
$route['dat_reader/data']        = 'DatReader/data';
$route['dat_reader/save']        = 'DatReader/save';
$route['dat_reader/push']        = 'DatReader/push';

// Temuan (laporan masalah toko, auth via token bearer Api_token — sama seperti PWA)
$route['temuan/me']              = 'Temuan/me';
$route['temuan/branches']        = 'Temuan/branches';
$route['temuan/employees']       = 'Temuan/employees';
$route['temuan/inspectors']       = 'Temuan/inspectors';
$route['temuan/inspector_add']    = 'Temuan/inspector_add';
$route['temuan/inspector_delete'] = 'Temuan/inspector_delete';
$route['temuan/locations']       = 'Temuan/locations';
$route['temuan/location_save']   = 'Temuan/location_save';
$route['temuan/location_delete'] = 'Temuan/location_delete';
$route['temuan/list']            = 'Temuan/list';
$route['temuan/create']          = 'Temuan/create';
$route['temuan/take']            = 'Temuan/take';
$route['temuan/reject']          = 'Temuan/reject';
$route['temuan/done']            = 'Temuan/done';
$route['temuan/acc']             = 'Temuan/acc';
$route['temuan/delete']          = 'Temuan/delete';
$route['temuan/report']          = 'Temuan/report';
$route['temuan/config']          = 'Temuan/config';
$route['temuan/save_config']     = 'Temuan/save_config';

// Export PPh21 (kertas kerja per CV dari payroll, auth via CI3 session)
$route['pph21_export/periods']    = 'Pph21Export/periods';
$route['pph21_export/cvs']        = 'Pph21Export/cvs';
$route['pph21_export/export']     = 'Pph21Export/export';
$route['pph21_export/export_all'] = 'Pph21Export/export_all';
$route['pph21_export/export_combined'] = 'Pph21Export/export_combined';
$route['pph21_export/export_xml']      = 'Pph21Export/export_xml';
$route['pph21_export/export_xml_all']  = 'Pph21Export/export_xml_all';
$route['pph21_export/settings']        = 'Pph21Export/settings';
$route['pph21_export/settings_save']   = 'Pph21Export/settings_save';
$route['pph21_export/export_gu']       = 'Pph21Export/export_gu';
$route['pph21_export/export_gu_all']   = 'Pph21Export/export_gu_all';
$route['pph21_export/export_des']      = 'Pph21Export/export_des';
$route['pph21_export/export_des_all']  = 'Pph21Export/export_des_all';

// PPh21 masa pajak terakhir (penghitungan ulang tahunan Ps.17 — PMK 168/2023)
$route['pph21_final/export']     = 'Pph21Final/export';
$route['pph21_final/export_all'] = 'Pph21Final/export_all';

// PPh21 pegawai tidak tetap & tenaga ahli (import per masa, XML Bp21Bulk gabungan)
$route['pph21_nonpegawai/refs']           = 'Pph21Nonpegawai/refs';
$route['pph21_nonpegawai/data']           = 'Pph21Nonpegawai/data';
$route['pph21_nonpegawai/template']       = 'Pph21Nonpegawai/template';
$route['pph21_nonpegawai/import']         = 'Pph21Nonpegawai/import';
$route['pph21_nonpegawai/save']           = 'Pph21Nonpegawai/save';
$route['pph21_nonpegawai/export_excel']   = 'Pph21Nonpegawai/export_excel';
$route['pph21_nonpegawai/export_xml']     = 'Pph21Nonpegawai/export_xml';
$route['pph21_nonpegawai/export_xml_all'] = 'Pph21Nonpegawai/export_xml_all';

// Input Manual PPh21 (4 kolom isian manual kertas kerja, auth via CI3 session)
$route['pph21_manual/periods']   = 'Pph21Manual/periods';
$route['pph21_manual/employees'] = 'Pph21Manual/employees';
$route['pph21_manual/save']      = 'Pph21Manual/save';
$route['pph21_manual/template']  = 'Pph21Manual/template';
$route['pph21_manual/import']    = 'Pph21Manual/import';

// ============ BPJS ================
$route['bpjs']                = 'Bpjs/index';
$route['bpjs/config']         = 'Bpjs/config';
$route['bpjs/save_config']    = 'Bpjs/save_config';
$route['bpjs/list']           = 'Bpjs/list_payment';
$route['bpjs/toggle_office']  = 'Bpjs/toggle_office';
$route['bpjs/acc']            = 'Bpjs/acc';
$route['bpjs/sync']           = 'Bpjs/sync_period';

$route['panel/master_data/user'] 	= 'User';
$route['insert_user']		   		= 'User/insert';
$route['update_user']		   		= 'User/update';
$route['delete_user']		   		= 'User/delete';

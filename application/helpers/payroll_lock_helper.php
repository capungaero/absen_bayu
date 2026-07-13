<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ── Lock penggajian (KRITIS — pagar uang) ──
 *
 * Baris payroll (branch_id, month, year) = periode TERKUNCI: semua tulis
 * presence/jadwal/izin periode itu wajib ditolak. Fungsi-fungsi ini dipakai
 * lintas controller (hr/Presence, M, Wa, DatReader). Dipisah dari
 * generic_helper.php (8 Jul 2026) supaya tidak tersenggol saat mengedit
 * helper umum. Spec: docs/rules/08-payroll-aggregation.md.
 */

if(!function_exists('payroll_period_of_date')){
	// Periode payroll (month/year) yang memuat $date. Tgl >= START_PAYROLL_DATE masuk bulan berikutnya.
	function payroll_period_of_date($date){
		$ts = strtotime($date);
		$d = (int)date('d', $ts); $m = (int)date('n', $ts); $y = (int)date('Y', $ts);
		if($d >= START_PAYROLL_DATE){ $m++; if($m > 12){ $m = 1; $y++; } }
		return array('month' => $m, 'year' => $y);
	}
}

if(!function_exists('payroll_locked')){
	// TRUE bila penggajian cabang+periode sudah dibuat.
	function payroll_locked($branch_id, $month, $year){
		if(empty($branch_id)) return false;
		$CI =& get_instance();
		return $CI->db->where(array('branch_id'=>(int)$branch_id, 'month'=>(int)$month, 'year'=>(int)$year))
					  ->from('payroll')->count_all_results() > 0;
	}
}

if(!function_exists('payroll_user_branch')){
	function payroll_user_branch($user_id){
		$CI =& get_instance();
		$row = $CI->db->select('position.branch_id')
					  ->join('position', 'position.id = users.position_id')
					  ->where('users.id', $user_id)
					  ->get('users')->row_array();
		return $row ? (int)$row['branch_id'] : null;
	}
}

if(!function_exists('payroll_locked_for_user_date')){
	function payroll_locked_for_user_date($user_id, $date){
		$b = payroll_user_branch($user_id);
		if(!$b) return false;
		$pp = payroll_period_of_date($date);
		return payroll_locked($b, $pp['month'], $pp['year']);
	}
}

if(!function_exists('payroll_locked_dates')){
	// Daftar periode "MM/YYYY" terkunci yang tersentuh $dates (cabang $branch_id).
	function payroll_locked_dates($branch_id, $dates){
		if(empty($branch_id)) return array();
		$checked = array(); $locked = array();
		foreach((array)$dates as $d){
			$pp = payroll_period_of_date($d);
			$key = $pp['month'].'-'.$pp['year'];
			if(isset($checked[$key])) continue;
			$checked[$key] = true;
			if(payroll_locked($branch_id, $pp['month'], $pp['year'])){
				$locked[] = str_pad($pp['month'],2,'0',STR_PAD_LEFT).'/'.$pp['year'];
			}
		}
		return $locked;
	}
}

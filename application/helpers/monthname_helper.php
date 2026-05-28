<?php
	function get_monthname($number, $is_short = false){
		if($number == 1 or $number ==  "01"){
			return "Januari";
		}else if($number == 2 or $number == "02"){
			return "Februari";
		}else if($number == 3 or $number ==  "03"){
			return "Maret";
		}else if($number == 4 or  $number == "04"){
			return "April";
		}else if($number == 5 or  $number == "05"){
			return "Mei";
		}else if($number == 6 or  $number == "06"){
			return "Juni";
		}else if($number == 7 or  $number == "07"){
			return "Juli";
		}else if($number == 8 or  $number == "08"){
			return "Agustus";
		}else if($number == 9 or $number == "09"){
			return "September";
		}else if($number == "10"){
			return "Oktober";
		}else if($number == "11"){
			return "November";
		}else if($number == "12"){
			return "Desember";
		}
	}

	function get_short_month($number){
		if($number == 1 or $number ==  "01"){
			return "Jan";
		}else if($number == 2 or $number == "02"){
			return "Feb";
		}else if($number == 3 or $number ==  "03"){
			return "Mar";
		}else if($number == 4 or  $number == "04"){
			return "Apr";
		}else if($number == 5 or  $number == "05"){
			return "Mei";
		}else if($number == 6 or  $number == "06"){
			return "Jun";
		}else if($number == 7 or  $number == "07"){
			return "Jul";
		}else if($number == 8 or  $number == "08"){
			return "Ags";
		}else if($number == 9 or $number == "09"){
			return "Sep";
		}else if($number == "10"){
			return "Okt";
		}else if($number == "11"){
			return "Nov";
		}else if($number == "12"){
			return "Des";
		}
	}

	function indonesian_date($string, $with_time = false, $is_string = true, $is_short = false){
		$time = $string;
		if($is_string){
			$time = strtotime($string ?? '');
		}
		$tgl = date('d', $time);

		if(!$is_short){
			$bln = get_monthname(date('m', $time));
		}else{
			$bln = get_short_month(date('m', $time));
		}
		
		$thn = date('Y', $time);
		$txt = $tgl." ".$bln." ".$thn;

		if($with_time){
			$jam = date('H:i', $time);
			$txt .= ", ".$jam;
		}
		return $txt;
	}

	function date_range($date_array, $with_time = false, $is_string = true, $is_short = false){
		$date1 = indonesian_date($date_array[0], $with_time, $is_string, $is_short);
		$date2 = indonesian_date($date_array[1], $with_time, $is_string, $is_short);

		if($date1 == $date2){
			return $date1;
		}
		
		return $date1." - ".$date2;
	}

	function diffInMonths(\DateTime $date1, \DateTime $date2){
	    $diff =  $date1->diff($date2);

	    $months = $diff->y * 12 + $diff->m + $diff->d / 30;

	    return (int) round($months);
	}

	function diffInDays($date1, $date2) 
	{
	    $diff = strtotime($date2) - strtotime($date1);
	    return abs(round($diff / 86400));
	}

	function get_date_type($string){
		$current = date('Y-m-d');
		$first_week = date('Y-m-d', strtotime('this week'));
		$date = [];

		if($string == 'current'){
			$date = [$current, $current];

		}else if($string == 'tomorrow'){
			$date = [
				$current,
				date('Y-m-d', strtotime(date($current.' +1 days')))
			];

		}else if($string == 'this-week'){
			$date = [
				$first_week,	
				date('Y-m-d', strtotime(date($first_week.' +7 days')))
			];

		}else if($string == 'next-week'){
			$next_week = date('Y-m-d', strtotime(date($first_week.' +7 days')));
			$date = [
				$next_week,
				date('Y-m-d', strtotime(date($next_week.' +7 days')))
			];

		}else if($string == 'next-month'){
			$next_month = date('Y-m-1', strtotime(date('Y-m-1 +1 month')));
			$date = [
				$next_month,
				date('Y-m-31', strtotime($next_month))
			];
		}

		return $date;
	}

	function get_dayname($date, $initial = false){
		$weekDay = date('l', strtotime($date)); 

		if($weekDay == 'Monday'){
			$day = 'Senin';

		}else if($weekDay == 'Tuesday'){
			$day = 'Selasa';

		}else if($weekDay == 'Wednesday'){
			$day = 'Rabu';

		}else if($weekDay == 'Thursday'){
			$day = 'Kamis';

		}else if($weekDay == 'Friday'){
			$day = 'Jumat';

		}else if($weekDay == 'Saturday'){
			$day = 'Sabtu';

		}else{
			$day = 'Minggu';
		}

		return ($initial) ? substr($day, 0, 1) : $day;
	}

	function add_months($months, DateTime $dateObject) 
    {
        $next = new DateTime($dateObject->format('Y-m-d'));
        $next->modify('last day of +'.$months.' month');

        if($dateObject->format('d') > $next->format('d')) {
            return $dateObject->diff($next);
        } else {
            return new DateInterval('P'.$months.'M');
        }
    }

	function endCycle($d1, $months)
    {
        $date = new DateTime($d1);

        // call second function to add the months
        $newDate = $date->add(add_months($months, $date));

        // goes back 1 day from date, remove if you want same day of month
        $newDate->sub(new DateInterval('P1D')); 

        //formats final date to Y-m-d form
        $dateReturned = $newDate->format('Y-m-d'); 

        return $dateReturned;
    }

    function between_time($time, $between){
		if(strtotime($time) >= strtotime($between[0]) && strtotime($time) <= strtotime($between[1])){
			return true;
		}
		return false;
	}

	function compare_time($time1, $operator, $time2){
		$time1 = strtotime($time1);
		$time2 = strtotime($time2);


		if($operator == '=='){
			if($time1 == $time2){
				return true;
			}

		}else if($operator == '<'){
			if($time1 < $time2){
				return true;
			}
		
		}else if($operator == '>'){
			if($time1 > $time2){
				return true;
			}

		}else if($operator == '>='){
			if($time1 >= $time2){
				return true;
			}
		
		}else if($operator == '<='){
			if($time1 <= $time2){
				return true;
			}
		}

		return false;
	}

	function isValidDate($date, $format = 'Y-m-d H:i:s'){
		$date = trim($date);
    	$d = DateTime::createFromFormat($format, $date);
    	return $d && $d->format($format) == $date;
	}

	function differenceInHours($startdate,$enddate){
		$time1 = new DateTime($startdate);
		$time2 = new DateTime($enddate);
		$interval = $time1->diff($time2);

		$difference = ($interval->h) + ($interval->i / 60);

		return $difference;
	}

	function getRangeWorkDate($month, $year){
		$month = strlen($month) == 1 ? '0'.$month : $month;
		$raw  = strtotime($year."-".$month."-10 -1 months");
    	$from = date('Y-m-'.START_PAYROLL_DATE, $raw);
    	$from_month = date('m', strtotime($from));
    	$from_year  = date('Y', strtotime($from));

    	$to   = $year."-".$month."-".END_PAYROLL_DATE;
    	$to_month = date('m', strtotime($to));
    	$to_year  = date('Y', strtotime($to));

    	$range = get_daterange_list($from, $to);
    	$from_count = $to_count = 0;
    	foreach ($range as $row){
    		if(compare_time($from_year."-".$from_month."-1", '==', date('Y-m-1', strtotime($row)))){
    			$from_count++;
    		}else{
    			$to_count++;
    		}
    	}

    	$res = [
    		'from' => [
    			'month' 	   => $from_month,
    			'string_month' => get_monthname($from_month)." ".$from_year,
    			'count'		   => $from_count
    		],
    		'to'   => [
    			'month'		   => $to_month,
    			'string_month' => get_monthname($to_month)." ".$to_year,
    			'count'		   => $to_count
    		],
    		'list' => $range,
    		'total_day' => $from_count + $to_count
    	];

    	return $res;
	}

	function get_daterange_list($from, $to){
		$period = new DatePeriod(
		     new DateTime($from),
		     new DateInterval('P1D'),
		     new DateTime($to)
		);

    	$daterange = [];
		foreach ($period as $key => $value) {
		    $daterange[] = $value->format('Y-m-d');    
		}
		$daterange[] = $to;
		return $daterange;
	}

	function compareTimeBetween($current, $time_in, $time_out, $range){
		$current    = strtotime($current);
		$time_in    = strtotime($time_in);
		$time_out   = strtotime($time_out);
		$time_limit = strtotime(date('H:i:s', strtotime($current." +".$range." minutes")));

		if($current >= $time_in && $current <= $time_out){
			if($time_limit <= $time_out){
				
			}else{
				
			}
		}
	}

	function diff_in_minute($limit, $time){
		$time = date('H:i:s', strtotime($time));
		
		$dif_time  = (substr($time, 0, 2) * 60) + substr($time, 3, 2);
		$dif_limit = (substr($limit, 0, 2) * 60) + substr($limit, 3, 2);
		$diff = $dif_time - $dif_limit;
		return $diff;
	}
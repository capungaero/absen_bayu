<?php
	function toDatatable($data,$totalData){	
		$output = array(
			'draw' => intval($_POST['draw']),
			'recordsTotal' => $totalData,
			'recordsFiltered' => $totalData,
			'data' => $data
		);
		return $output;
	}

	function imageToBase64($path){
        $path = $path;
		$type = pathinfo($path, PATHINFO_EXTENSION);
		$data = file_get_contents($path);
		$base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
	}

	function show_alert($message, $status){
		return '<div class="alert alert-'.$status.' alert-dismissible fade show" role="alert">
                    '.$message.'
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                    
                    </button>
                </div>';
	}

	function show_alert_border($title, $message, $status){
		return '<div class="alert alert-border alert-border-'.$status.'" role="alert">
                    <span class="text-'.$status.'">'.$title.'</span><br>'.$message.'
                </div>';
	}
	
	function full_date($date){
	    $tgl = date('d', strtotime($date));
	    $thn = date('Y', strtotime($date));
	    $bln = date('m', strtotime($date));
	    
	    if($bln == '1'){
	        $bln = "Januari";
	    }else if($bln == '2'){
	        $bln = "Februari";
	    }else if($bln == '3'){
	        $bln = "Maret";
	    }else if($bln == '4'){
	        $bln = "April";
	    }else if($bln == '5'){
	        $bln = "Mei";
	    }else if($bln == '6'){
	        $bln = "Juni";
	    }else if($bln == '7'){
	        $bln = "Juli";
	    }else if($bln == '8'){
	        $bln = "Agustus";
	    }else if($bln == '9'){
	        $bln = "September";
	    }else if($bln == '10'){
	        $bln = "Oktober";
	    }else if($bln == '11'){
	        $bln = "November";
	    }else if($bln == '12'){
	        $bln = "Desember";
	    }
	    
	    return $tgl." ".$bln." ".$thn;
	}

	function show_level($lv){
		if($lv == 0){
			return 'Belum Dibuat';
		}else if($lv == 1){
			return 'Design Awal';
		}else if($lv == 2){
			return 'Konfirmasi Pelanggan';
		}else if($lv == 3){
			return 'Konfirmasi Design';
		}else if($lv == 4){
			return 'Paket / Penyelesaian';
		}else if($lv == 5){
			return 'Email';
		}
	}

	function dateDifference($date_1 , $date_2 , $differenceFormat = '%a' ){
	    $datetime1 = date_create($date_1);
	    $datetime2 = date_create($date_2);
	   
	    $interval = date_diff($datetime1, $datetime2);
	   
	    return $interval->format($differenceFormat);

	}

	function generateRandom($n){ 
	    $characters = '0123456789'; 
	    $randomString = ''; 
	  
	    for ($i = 0; $i < $n; $i++) { 
	        $index = rand(0, strlen($characters) - 1); 
	        $randomString .= $characters[$index]; 
	    } 
	  
	    return $randomString; 
	}

	function randowmWord(){
		$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'; 
	    $randomString = ''; 
	  
	    for ($i = 0; $i < $n; $i++) { 
	        $index = rand(0, strlen($characters) - 1); 
	        $randomString .= $characters[$index]; 
	    } 
	  
	    return $randomString; 
	}

	function randomWord($n){ 
	    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'; 
	    $randomString = ''; 
	  
	    for ($i = 0; $i < $n; $i++) { 
	        $index = rand(0, strlen($characters) - 1); 
	        $randomString .= $characters[$index]; 
	    } 
	  
	    return $randomString; 
	}

	function cut_by($string, $num){
		if(strlen($string) > $num){
			$string = substr($string, 0, $num)."...";
		}

		return $string;
	}

	function get_priority($status){
		$txt = '';
		if($status == 'high'){
			$txt = '<span class="badge badge-danger">Tinggi</span>';
		}else if($status == 'middle'){
			$txt = '<span class="badge badge-warning">Sedang</span>';
		}else if($status == 'low'){
			$txt = '<span class="badge badge-success">Rendah</span>';
		}

		return $txt;
	}

	function get_status($status){
		$txt = '';
		if($status == 'hold'){
			$txt = '<span class="badge badge-primary">Menunggu verifikasi</span>';
		}else if($status == 'pending'){
			$txt = '<span class="badge badge-info">Pending</span>';
		}else if($status == 'rejected'){
			$txt = '<span class="badge badge-danger">Ditolak</span>';
		}else if($status == 'approved'){
			$txt = '<span class="badge badge-success">Diterima</span>';
		}

		return $txt;
	}

	function asset_status($status){
		$txt = '';

		if($status == '1'){
            $color = 'warning';
            $title = 'Diperbaiki';
        }else if($status == '0'){
            $color = 'success';
            $title = 'Baik';
        }else if($status == '-1'){
        	$color = 'danger';
        	$title = 'Rusak';
        }

        return '<span class="font-weight-bold font-14 badge bg-soft-'.$color.'">'.$title.'</span>';
	}

	function maintenance_status($status){
		$color = $title = '';

		if($status == 'pending'){
            $color = 'primary';
            $title = 'Menunggu Konfirmasi';
            $icon  = '<i class="fa fa-clock"></i>';
        }else if($status == 'approve'){
            $color = 'success';
            $title = 'Disetujui';
            $icon  = '<i class="fa fa-check-circle"></i>';
        }else if($status == 'deny'){
        	$color = 'danger';
        	$title = 'Ditolak';
        	$icon  = '<i class="fa fa-times-circle"></i>';
        }

        return '<span class="font-weight-bold font-14 badge bg-'.$color.'">'.$icon." ".$title.'</span>';
	}

	function transaction_status($status){
		$color = $title = '';

		if($status == 'pending'){
            $color = 'primary';
            $title = '<i class="fa fa-clock"></i> Menunggu Konfirmasi';
        }else if($status == 'approve'){
            $color = 'success';
            $title = '<i class="fa fa-check-circle"></i> Disetujui';
        }else if($status == 'deny'){
        	$color = 'danger';
        	$title = '<i class="fa fa-times-circle"></i> Ditolak';
        }else if($status == 'cancel'){
        	$color = 'warning';
        	$title = '<i class="fa fa-ban"></i> Dibatalkan';
        }

        return '<span class="font-weight-bold font-14 badge bg-'.$color.'">'.$title.'</span>';
	}

	function status_request($status){
		$color = $title = '';

		if($status == 'pending'){
            $color = 'info';
            $title = '<i class="fa fa-clock"></i> Menunggu Konfirmasi';
        }else if($status == 'acc'){
            $color = 'success';
            $title = '<i class="fa fa-check-circle"></i> Disetujui';
        }else if($status == 'deny'){
        	$color = 'danger';
        	$title = '<i class="fa fa-times-circle"></i> Ditolak';
        }else if($status == 'confirm_transfer'){
        	$color = 'primary';
        	$title = '<i class="fa fa-clock-o"></i> Menunggu Transfer';
        }

        return '<span class="font-weight-bold font-14 badge bg-'.$color.'">'.$title.'</span>';
	}

	function status_formula($status){
		$txt = 'Tidak Ada';
		if($status == 'per_payroll'){
			$txt = 'Diberikan saat penggajian';
		}else if($status == 'per_presence'){
			$txt = 'Diberikan setiap kali hadir';
		}
		return $txt;
	}

	function searchFor($target, $value, $array) {
	   foreach ($array as $key => $val) {
	       if ($val[$target] === $value) {
	           return $key;
	       }
	   }
	   return null;
	}

	function dd($param){
		echo "<pre>".print_r($param, true)."</pre>";
	}

	function GetPotonganIzin($status){
		$tax = 0;
		if(in_array($status, ['permanent', 'contract'])){
			$tax = 50;
		}else{
			$tax = 75;
		}
		return $tax;
	}
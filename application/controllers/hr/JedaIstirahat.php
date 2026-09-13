<?php

class JedaIstirahat extends CI_Controller{

	function __construct(){
		parent::__construct();
		$this->load->model('jedaistirahat_model', 'jeda');
		$this->load->model('branch_model', 'branch');

		if(!$this->ion_auth->logged_in()){
			redirect('');
		}

		$this->role     = $this->ion_auth->get_users_groups()->row()->name;
		$this->userdata = $this->ion_auth->user()->row();
	}

	private function _allowed(){
		return in_array($this->role, ['admin', 'hr']);
	}

	/**
	 * Hitung rentang tanggal (from, to) berdasarkan tipe periode.
	 * - gaji   : periode payroll 26 (bulan-1) s/d 25 (bulan), pakai getRangeWorkDate()
	 * - minggu : Senin s/d Minggu dari tanggal anchor
	 * - hari   : satu tanggal saja
	 */
	private function _resolve_period($periode_type, $bulan, $tahun, $tanggal){
		if($periode_type == 'minggu'){
			$anchor = $tanggal ? $tanggal : date('Y-m-d');
			$dow    = (int)date('N', strtotime($anchor)); // 1=Senin .. 7=Minggu
			$from   = date('Y-m-d', strtotime($anchor.' -'.($dow-1).' days'));
			$to     = date('Y-m-d', strtotime($from.' +6 days'));
			$label  = 'Minggu '.date('d M Y', strtotime($from)).' - '.date('d M Y', strtotime($to));
		}elseif($periode_type == 'hari'){
			$from  = $tanggal ? $tanggal : date('Y-m-d');
			$to    = $from;
			$label = date('d M Y', strtotime($from));
		}else{
			// default: gaji
			$periode_type = 'gaji';
			$bulan = $bulan ? str_pad($bulan, 2, '0', STR_PAD_LEFT) : date('m');
			$tahun = $tahun ? $tahun : date('Y');
			$daterange = getRangeWorkDate($bulan, $tahun);
			$dates = $daterange['list'];
			$from  = reset($dates);
			$to    = end($dates);
			$label = 'Periode gaji '.$daterange['from']['string_month'].' - '.$daterange['to']['string_month'];
		}

		return ['periode_type' => $periode_type, 'from' => $from, 'to' => $to, 'label' => $label];
	}

	public function index(){
		if(!$this->_allowed()){
			redirect();
		}

		$data['branch']  = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
		$data['periode'] = $this->_resolve_period('gaji', date('m'), date('Y'), null);

		$this->template->load('layout/admin', 'hr/jeda_istirahat/index', $data);
	}

	public function data(){
		if(!$this->_allowed()){
			echo json_encode(['status' => false, 'message' => 'Akses ditolak.']);
			return;
		}

		$periode_type = $this->input->get('periode_type');
		$bulan        = $this->input->get('bulan');
		$tahun        = $this->input->get('tahun');
		$tanggal      = $this->input->get('tanggal');
		$branch_id    = $this->input->get('branch_id');

		$periode = $this->_resolve_period($periode_type, $bulan, $tahun, $tanggal);
		$rows    = $this->jeda->get_events($periode['from'], $periode['to'], $branch_id);

		echo json_encode([
			'status'  => true,
			'periode' => $periode,
			'data'    => $rows
		]);
	}

	/**
	 * Terapkan filter sisi-klien (jeda min/max, arah pola, nama) ke daftar event.
	 * Menyamakan logika filter di view (index.php) supaya export = yang tampil.
	 */
	private function _apply_filters($rows, $gap_min, $gap_max, $pola, $nama){
		$gap_min = ($gap_min === '' || $gap_min === null) ? 0 : (int)$gap_min;
		$gap_max = ($gap_max === '' || $gap_max === null) ? 60 : (int)$gap_max;
		if($gap_min > $gap_max){ $tmp = $gap_min; $gap_min = $gap_max; $gap_max = $tmp; }
		$nama = trim(mb_strtolower((string)$nama));

		$out = [];
		foreach($rows as $r){
			$sel = (int)$r['selisih'];
			if($sel < $gap_min || $sel > $gap_max) continue;
			if($pola && $pola !== 'all' && $r['pola'] !== $pola) continue;
			if($nama !== '' && mb_strpos(mb_strtolower($r['nama']), $nama) === false) continue;
			$out[] = $r;
		}
		return $out;
	}

	private function _pola_label($p){
		return $p === 'rest_ke_sholat' ? 'Istirahat -> Sholat' : 'Sholat -> Istirahat';
	}

	public function export(){
		if(!$this->_allowed()){
			show_404();
			return;
		}

		$periode_type = $this->input->get('periode_type');
		$bulan        = $this->input->get('bulan');
		$tahun        = $this->input->get('tahun');
		$tanggal      = $this->input->get('tanggal');
		$branch_id    = $this->input->get('branch_id');

		$gap_min = $this->input->get('gap_min');
		$gap_max = $this->input->get('gap_max');
		$pola    = $this->input->get('pola');
		$nama    = $this->input->get('nama');

		$periode = $this->_resolve_period($periode_type, $bulan, $tahun, $tanggal);
		$rows    = $this->jeda->get_events($periode['from'], $periode['to'], $branch_id);
		$rows    = $this->_apply_filters($rows, $gap_min, $gap_max, $pola, $nama);

		// Rekap per karyawan
		$rekap = [];
		foreach($rows as $r){
			$k = $r['nama'];
			if(!isset($rekap[$k])){
				$rekap[$k] = ['nama'=>$r['nama'], 'cabang'=>$r['cabang'], 'kejadian'=>0, 'total'=>0, 'tglawal'=>$r['tanggal'], 'tglakhir'=>$r['tanggal']];
			}
			$rekap[$k]['kejadian']++;
			$rekap[$k]['total'] += (int)$r['selisih'];
			if($r['tanggal'] < $rekap[$k]['tglawal']) $rekap[$k]['tglawal'] = $r['tanggal'];
			if($r['tanggal'] > $rekap[$k]['tglakhir']) $rekap[$k]['tglakhir'] = $r['tanggal'];
		}
		usort($rekap, function($a, $b){ return $b['kejadian'] - $a['kejadian']; });

		require_once FCPATH.'lib/vendor/autoload.php';

		$ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$headStyle = [
			'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
			'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '3D6B63']],
		];

		// --- Sheet 1: Rekap per karyawan ---
		$s1 = $ss->getActiveSheet();
		$s1->setTitle('Rekap per Karyawan');
		$s1->fromArray(['No', 'Nama', 'Cabang', 'Jumlah Kejadian', 'Total Menit', 'Rata-rata Menit', 'Tanggal Pertama', 'Tanggal Terakhir'], null, 'A1');
		$s1->getStyle('A1:H1')->applyFromArray($headStyle);
		$row = 2; $no = 1;
		foreach($rekap as $g){
			$rata = $g['kejadian'] > 0 ? round($g['total'] / $g['kejadian'], 1) : 0;
			$s1->fromArray([$no, $g['nama'], $g['cabang'], $g['kejadian'], $g['total'], $rata, $g['tglawal'], $g['tglakhir']], null, 'A'.$row);
			$row++; $no++;
		}
		foreach(range('A','H') as $col){ $s1->getColumnDimension($col)->setAutoSize(true); }

		// --- Sheet 2: Daftar kejadian ---
		$s2 = $ss->createSheet();
		$s2->setTitle('Daftar Kejadian');
		$s2->fromArray(['No', 'Tanggal', 'Nama', 'Cabang', 'Pola', 'Sholat', 'Istirahat Mulai', 'Istirahat Selesai', 'Sholat Mulai', 'Sholat Selesai', 'Selisih (menit)'], null, 'A1');
		$s2->getStyle('A1:K1')->applyFromArray($headStyle);
		usort($rows, function($a, $b){
			if($a['tanggal'] === $b['tanggal']) return strcmp($a['nama'], $b['nama']);
			return strcmp($a['tanggal'], $b['tanggal']);
		});
		$row = 2; $no = 1;
		foreach($rows as $r){
			$s2->fromArray([
				$no, $r['tanggal'], $r['nama'], $r['cabang'], $this->_pola_label($r['pola']), $r['sholat'],
				$r['istirahat_mulai'], $r['istirahat_selesai'], $r['sholat_mulai'], $r['sholat_selesai'], (int)$r['selisih']
			], null, 'A'.$row);
			$row++; $no++;
		}
		foreach(range('A','K') as $col){ $s2->getColumnDimension($col)->setAutoSize(true); }

		$ss->setActiveSheetIndex(0);

		$slug = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($periode['from'].'_'.$periode['to']));
		$filename = 'jeda_istirahat_'.$slug.'.xlsx';

		while(ob_get_level() > 0){ ob_end_clean(); }
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header('Cache-Control: max-age=0');
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
		$writer->save('php://output');
		exit;
	}

}

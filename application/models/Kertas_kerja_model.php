<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Kertas Kerja — laporan foto rencana kerja harian, 1-2x lapor per hari (slot_no)
 * tergantung users.kertas_kerja_count. Bisa diupload sendiri atau diwakilkan
 * leader team (kertas_kerja_leader_team) -- uploaded_by dicatat kalau diwakilkan.
 * Dipakai bersama M.php (submit sisi karyawan) & hr/KertasKerja.php (review admin/SPV),
 * pola sama overtime_model dipakai M.php & hr/Overtime.php.
 */
class Kertas_kerja_model extends CI_Model {

	protected $table = 'kertas_kerja';

	/** Flag wajib + jumlah slot wajib 1 karyawan. */
	public function get_employee_flag($user_id) {
		$row = $this->db->select('id, wajib_kertas_kerja, kertas_kerja_count')
			->where('id', $user_id)->get('users')->row_array();
		if (empty($row)) { return null; }
		return [
			'wajib_kertas_kerja' => !empty($row['wajib_kertas_kerja']),
			'kertas_kerja_count' => max(1, (int)$row['kertas_kerja_count']),
		];
	}

	/** Semua slot (1..count) utk 1 karyawan di 1 tanggal -- null kalau slot itu belum diisi. */
	public function get_slots_by_user_date($user_id, $date, $count) {
		$existing = $this->db->where(['user_id' => $user_id, 'kerja_date' => $date])
			->get($this->table)->result_array();
		$by_slot = [];
		foreach ($existing as $row) { $by_slot[(int)$row['slot_no']] = $row; }

		$slots = [];
		for ($i = 1; $i <= $count; $i++) {
			$slots[$i] = isset($by_slot[$i]) ? $by_slot[$i] : null;
		}
		return $slots;
	}

	/** Riwayat N hari terakhir (flat per slot, ringkas utk list). */
	public function get_history($user_id, $limit = 10) {
		return $this->db->select($this->table.'.*, leader.first_name AS uploaded_by_name')
			->join('users leader', 'leader.id = '.$this->table.'.uploaded_by', 'left')
			->where($this->table.'.user_id', $user_id)
			->order_by('kerja_date', 'DESC')
			->order_by('slot_no', 'ASC')
			->limit($limit)
			->get($this->table)->result_array();
	}

	/**
	 * Upsert 1 slot kertas kerja: satu baris per (user_id, kerja_date, slot_no).
	 * Bukti = FOTO kertas tulisan tangan ATAU teks rencana kerja langsung (karyawan
	 * pilih salah satu tiap submit -- $submission_type 'foto'/'teks'). Kolom yg tak
	 * dipakai mode itu sengaja dikosongkan biar 1 slot selalu cerminkan submission
	 * TERAKHIR dgn bersih (tak ketinggalan foto lama pas ganti ke mode teks, dst).
	 * $uploaded_by null = karyawan upload sendiri; terisi user_id leader kalau
	 * diupload mewakili anggota tim.
	 *
	 * @return int  id kertas_kerja
	 */
	public function save($user_id, $date, $submission_type, $photo_path, $notes, $slot_no = 1, $uploaded_by = null) {
		$existing = $this->db->where(['user_id' => $user_id, 'kerja_date' => $date, 'slot_no' => $slot_no])
			->get($this->table)->row_array();
		$now = date('Y-m-d H:i:s');

		if (empty($existing)) {
			$this->db->insert($this->table, [
				'user_id'         => $user_id,
				'kerja_date'      => $date,
				'slot_no'         => $slot_no,
				'submission_type' => $submission_type,
				'photo_path'      => $photo_path,
				'notes'           => $notes,
				'uploaded_by'     => $uploaded_by,
				'status'          => 'new',
				'created_at'      => $now,
			]);
			return $this->db->insert_id();
		}

		$kk_id = (int)$existing['id'];
		$this->db->where('id', $kk_id)->update($this->table, [
			'submission_type' => $submission_type,
			'photo_path'      => $photo_path,
			'notes'           => $notes,
			'uploaded_by'     => $uploaded_by,
			'status'          => 'new',
			'read_by'         => null,
			'read_at'         => null,
			'updated_at'      => $now,
		]);
		return $kk_id;
	}

	// =====================================================================
	// LEADER TEAM -- upload mewakili anggota
	// =====================================================================

	/** Anggota tim milik 1 leader (kosong = bukan leader siapa2). */
	public function get_members($leader_user_id) {
		return $this->db->select('users.id, users.first_name, users.employee_code,
				users.wajib_kertas_kerja, users.kertas_kerja_count')
			->join($this->_leader_table().' lt', 'lt.member_user_id = users.id')
			->where('lt.leader_user_id', $leader_user_id)
			->where('users.active', 1)
			->order_by('users.first_name', 'ASC')
			->get('users')->result_array();
	}

	/** Anggota tim + status slot hari itu, hanya yg wajib_kertas_kerja=1. */
	public function get_members_with_status($leader_user_id, $date) {
		$members = $this->get_members($leader_user_id);
		$out = [];
		foreach ($members as $m) {
			if (empty($m['wajib_kertas_kerja'])) { continue; }
			$count = max(1, (int)$m['kertas_kerja_count']);
			$m['slots'] = $this->get_slots_by_user_date($m['id'], $date, $count);
			$out[] = $m;
		}
		return $out;
	}

	public function is_leader_of($leader_user_id, $member_user_id) {
		return $this->db->where([
			'leader_user_id' => $leader_user_id,
			'member_user_id' => $member_user_id,
		])->get($this->_leader_table())->num_rows() > 0;
	}

	private function _leader_table() { return 'kertas_kerja_leader_team'; }

	/**
	 * Rekap kepatuhan per KARYAWAN (bukan per submission): semua karyawan yang
	 * di-flag wajib_kertas_kerja, dgn status semua slot di tanggal $date.
	 * Dipakai admin/SPV lihat siapa SUDAH vs BELUM bikin kertas kerja hari itu.
	 */
	public function get_rekap($date, $find = [], $spv_user_id = null) {
		$q = $this->db->select("users.id AS user_id,
				TRIM(CONCAT(users.first_name,' ',COALESCE(users.last_name,''))) AS first_name,
				users.employee_code, users.kertas_kerja_count, branch.branch_name, position.position_name", false)
			->from('users')
			->join('position', 'position.id = users.position_id')
			->join('branch', 'branch.id = position.branch_id')
			->where('users.wajib_kertas_kerja', 1)
			->where('users.active', 1);

		if (!empty($find)) { $q->where($find); }
		if ($spv_user_id !== null) {
			$ids = $this->_spv_employee_ids($spv_user_id);
			$q->where_in('users.id', empty($ids) ? [0] : $ids);
		}

		$employees = $q->order_by('users.first_name', 'ASC')->get()->result_array();
		if (empty($employees)) { return []; }

		$user_ids = array_map(function ($r) { return (int)$r['user_id']; }, $employees);
		$rows = $this->db->select($this->table.'.*, leader.first_name AS uploaded_by_name')
			->join('users leader', 'leader.id = '.$this->table.'.uploaded_by', 'left')
			->where($this->table.'.kerja_date', $date)
			->where_in($this->table.'.user_id', $user_ids)
			->get($this->table)->result_array();

		$by_user_slot = [];
		foreach ($rows as $r) { $by_user_slot[(int)$r['user_id']][(int)$r['slot_no']] = $r; }

		foreach ($employees as &$e) {
			$count = max(1, (int)$e['kertas_kerja_count']);
			$slots = [];
			for ($i = 1; $i <= $count; $i++) {
				$slots[$i] = isset($by_user_slot[(int)$e['user_id']][$i]) ? $by_user_slot[(int)$e['user_id']][$i] : null;
			}
			$e['slots'] = $slots;
			$e['done_count'] = count(array_filter($slots));
		}
		unset($e);

		return $employees;
	}

	public function mark_read($id, $by_user_id) {
		$this->db->where(['id' => $id, 'status' => 'new'])->update($this->table, [
			'status'  => 'read',
			'read_by' => $by_user_id,
			'read_at' => date('Y-m-d H:i:s'),
		]);
		return $this->db->affected_rows() > 0;
	}

	/** Baris kertas_kerja yang boleh dilihat SPV ybs (via kertas_kerja_spv_assignment). */
	private function _spv_employee_ids($spv_user_id) {
		$rows = $this->db->select('employee_user_id')
			->where('spv_user_id', $spv_user_id)
			->get('kertas_kerja_spv_assignment')->result_array();
		return array_map(function ($r) { return (int)$r['employee_user_id']; }, $rows);
	}

	/**
	 * DataTable list utk admin/hr (semua / scope cabang via $find) atau SPV
	 * (scope via kertas_kerja_spv_assignment, $spv_user_id != null).
	 */
	public function get_dataTable($find = [], $spv_user_id = null) {
		$dt = $this->datatables->init();

		// PENTING: DatatablesBuilder::from()/where() RETURN objek query builder CI
		// mentah ($this->_db), bukan $dt -- makanya select()->from()->join()->join()
		// harus tetap SATU chain (ditangkap ke $q) supaya where/where_in/order_by
		// bisa dipanggil sbg method CI Query Builder asli (DatatablesBuilder sendiri
		// TIDAK punya where_in()/order_by(); manggil $dt->order_by() di statement
		// terpisah = fatal error "Call to undefined method DatatablesBuilder::order_by()").
		$q = $dt->select('kertas_kerja.id, kertas_kerja.user_id, kertas_kerja.kerja_date, kertas_kerja.slot_no,
				kertas_kerja.status, kertas_kerja.submission_type, kertas_kerja.photo_path, kertas_kerja.notes,
				kertas_kerja.created_at, kertas_kerja.updated_at, kertas_kerja.uploaded_by,
				DATE_FORMAT(kertas_kerja.created_at, "%d %M %Y %H:%i") AS created_at_string,
				TRIM(CONCAT(users.first_name," ",COALESCE(users.last_name,""))) AS first_name,
				users.employee_code, users.kertas_kerja_count, branch_name, position_name,
				leader.first_name AS uploaded_by_name', false)
			->from($this->table)
			->join('users', 'users.id = kertas_kerja.user_id')
			->join('position', 'position.id = users.position_id')
			->join('branch', 'branch.id = position.branch_id')
			->join('users leader', 'leader.id = kertas_kerja.uploaded_by', 'left');

		if (!empty($find)) {
			$q->where($find);
		}
		if ($spv_user_id !== null) {
			$ids = $this->_spv_employee_ids($spv_user_id);
			// where_in kosong = tak ada baris sama sekali (SPV belum ditugaskan siapa2).
			$q->where_in('kertas_kerja.user_id', empty($ids) ? [0] : $ids);
		}

		$q->order_by('kertas_kerja.kerja_date', 'DESC');

		$dt->style(['class' => 'table table-striped table-bordered'])
			->column('<b>NO</b>', 'num_dt id')
			->column('<b>MITRA KERJA</b>', 'first_name', function ($data, $row) {
				$html = $row['first_name']."<br><small class='text-muted'><i class='fa fa-user-circle'></i> ".$row['employee_code']."<br><i class='fa fa-building'></i> ".$row['branch_name']."</small>";
				if (!empty($row['uploaded_by_name'])) {
					$html .= "<br><span class='badge bg-info' style='font-size:10px'><i class='fa fa-user-friends'></i> Diwakilkan: ".$row['uploaded_by_name']."</span>";
				}
				return $html;
			})
			->column('<b>TANGGAL</b>', 'kerja_date', function ($data, $row) {
				$label = (int)$row['kertas_kerja_count'] > 1 ? (($row['slot_no'] == 1) ? ' (Pagi)' : ' (Sore)') : '';
				return indonesian_date($row['kerja_date']).$label;
			})
			->column('<b>BUKTI</b>', 'photo_path', function ($data, $row) {
				if ($row['submission_type'] === 'teks') {
					if (empty($row['notes'])) { return '<span class="text-muted">-</span>'; }
					$preview = mb_strimwidth(strip_tags($row['notes']), 0, 60, '...');
					return '<span class="badge bg-secondary" style="font-size:10px">TEKS</span><br><small>'.htmlspecialchars($preview).'</small>';
				}
				if (empty($row['photo_path'])) { return '<span class="text-muted">-</span>'; }
				$url = base_url('assets/images/kertas_kerja/'.$row['photo_path']);
				return '<a href="'.$url.'" target="_blank"><img src="'.$url.'" style="width:44px;height:44px;object-fit:cover;border-radius:4px" alt="foto"></a>';
			})
			->column('<b>STATUS</b>', 'status', function ($data, $row) {
				return $row['status'] === 'read'
					? '<span class="badge bg-success">Sudah Dibaca</span>'
					: '<span class="badge bg-warning">Baru</span>';
			})
			->column('<center><i class="fa fa-cog"></i></center>', 'id', function ($data, $row) {
				return "<center><a class='btn btn-primary btn-sm' href='".site_url('hr/kertas_kerja/detail/'.$row['id'])."'><i class='fa fa-search'></i></a></center>";
			});

		$this->datatables->create('tableContent', $dt);
	}

	/** Badge unread di navbar, di-scope sesuai role (admin=semua, admin-branch=cabang, supervisor=assignment). */
	public function count_unread_for_role($role, $userdata) {
		if ($role === 'admin') {
			return (int)$this->db->where('status', 'new')->count_all_results($this->table);
		}
		if ($role === 'admin-branch') {
			return (int)$this->db->where('status', 'new')
				->join('users', 'users.id = kertas_kerja.user_id')
				->join('position', 'position.id = users.position_id')
				->where('position.branch_id', (int)$userdata->branch_id)
				->count_all_results($this->table);
		}
		if ($role === 'supervisor') {
			$ids = $this->_spv_employee_ids((int)$userdata->user_id);
			if (empty($ids)) { return 0; }
			return (int)$this->db->where('status', 'new')
				->where_in('user_id', $ids)
				->count_all_results($this->table);
		}
		return 0;
	}
}

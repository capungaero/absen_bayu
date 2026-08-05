<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Kertas Kerja — to-do list harian karyawan (checklist + catatan bebas).
 * Dipakai bersama M.php (submit sisi karyawan) & hr/KertasKerja.php (review admin/SPV),
 * pola sama overtime_model dipakai M.php & hr/Overtime.php.
 */
class Kertas_kerja_model extends CI_Model {

	protected $table = 'kertas_kerja';

	/** Header + item hari tsb utk 1 karyawan. Null kalau belum pernah isi. */
	public function get_by_user_date($user_id, $date) {
		$header = $this->db->where(['user_id' => $user_id, 'kerja_date' => $date])
			->get($this->table)->row_array();
		if (empty($header)) { return null; }

		$header['items'] = $this->get_items($header['id']);
		return $header;
	}

	public function get_items($kertas_kerja_id) {
		return $this->db->where('kertas_kerja_id', $kertas_kerja_id)
			->order_by('sort_order', 'ASC')
			->get('kertas_kerja_item')->result_array();
	}

	/** Riwayat N hari terakhir (header saja, tanpa item -- ringkas utk list). */
	public function get_history($user_id, $limit = 10) {
		$rows = $this->db->select('kertas_kerja.*,
				(SELECT COUNT(*) FROM kertas_kerja_item WHERE kertas_kerja_item.kertas_kerja_id = kertas_kerja.id) AS total_item,
				(SELECT COUNT(*) FROM kertas_kerja_item WHERE kertas_kerja_item.kertas_kerja_id = kertas_kerja.id AND is_done = 1) AS total_done', false)
			->where('user_id', $user_id)
			->order_by('kerja_date', 'DESC')
			->limit($limit)
			->get($this->table)->result_array();
		return $rows;
	}

	/**
	 * Upsert kertas kerja 1 hari: satu baris header per (user_id, kerja_date).
	 * Submit ulang hari sama = replace notes + delete/reinsert item (pola sama
	 * Employee::change_cluster()), status di-reset 'new' -- admin/SPV lihat sbg
	 * belum-dibaca lagi kalau ada perubahan.
	 *
	 * @param  array $items  [['text' => ..., 'is_done' => 0|1], ...]
	 * @return int  id kertas_kerja
	 */
	public function save($user_id, $date, $notes, array $items) {
		$this->db->trans_begin();

		$existing = $this->db->where(['user_id' => $user_id, 'kerja_date' => $date])
			->get($this->table)->row_array();
		$now = date('Y-m-d H:i:s');

		if (empty($existing)) {
			$this->db->insert($this->table, [
				'user_id'    => $user_id,
				'kerja_date' => $date,
				'notes'      => $notes,
				'status'     => 'new',
				'created_at' => $now,
			]);
			$kk_id = $this->db->insert_id();
		} else {
			$kk_id = (int)$existing['id'];
			$this->db->where('id', $kk_id)->update($this->table, [
				'notes'      => $notes,
				'status'     => 'new',
				'read_by'    => null,
				'read_at'    => null,
				'updated_at' => $now,
			]);
			$this->db->where('kertas_kerja_id', $kk_id)->delete('kertas_kerja_item');
		}

		if (!empty($items)) {
			$rows = [];
			foreach (array_values($items) as $i => $item) {
				$text = trim((string)$item['text']);
				if ($text === '') { continue; }
				$rows[] = [
					'kertas_kerja_id' => $kk_id,
					'item_text'       => $text,
					'is_done'         => !empty($item['is_done']) ? 1 : 0,
					'sort_order'      => $i,
					'created_at'      => $now,
				];
			}
			if (!empty($rows)) {
				$this->db->insert_batch('kertas_kerja_item', $rows);
			}
		}

		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			return false;
		}
		$this->db->trans_commit();
		return $kk_id;
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
		$q = $dt->select('kertas_kerja.id, kertas_kerja.user_id, kertas_kerja.kerja_date, kertas_kerja.status,
				kertas_kerja.created_at, kertas_kerja.updated_at,
				DATE_FORMAT(kertas_kerja.created_at, "%d %M %Y %H:%i") AS created_at_string,
				users.first_name, users.employee_code, branch_name, position_name,
				(SELECT COUNT(*) FROM kertas_kerja_item WHERE kertas_kerja_item.kertas_kerja_id = kertas_kerja.id) AS total_item,
				(SELECT COUNT(*) FROM kertas_kerja_item WHERE kertas_kerja_item.kertas_kerja_id = kertas_kerja.id AND is_done = 1) AS total_done', false)
			->from($this->table)
			->join('users', 'users.id = kertas_kerja.user_id')
			->join('position', 'position.id = users.position_id')
			->join('branch', 'branch.id = position.branch_id');

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
				return $row['first_name']."<br><small class='text-muted'><i class='fa fa-user-circle'></i> ".$row['employee_code']."<br><i class='fa fa-building'></i> ".$row['branch_name']."</small>";
			})
			->column('<b>TANGGAL</b>', 'kerja_date', function ($data, $row) {
				return indonesian_date($row['kerja_date']);
			})
			->column('<b>ITEM SELESAI</b>', 'total_done', function ($data, $row) {
				return $row['total_done'].' / '.$row['total_item'];
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

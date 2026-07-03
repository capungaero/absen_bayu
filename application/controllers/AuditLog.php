<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class AuditLog extends CI_Controller {

    private static $FIELD_LABEL = [
        'entry_time'               => 'Jam Masuk',
        'entry_time_late'          => 'Terlambat Masuk (mnt)',
        'out_time'                 => 'Jam Pulang',
        'rest_time_in'             => 'Istirahat Mulai',
        'rest_time_out'            => 'Istirahat Selesai',
        'rest_time_late'           => 'Terlambat Istirahat (mnt)',
        'subuh_time_in'            => 'Subuh Masuk',
        'subuh_time_out'           => 'Subuh Keluar',
        'subuh_time_late'          => 'Subuh Terlambat (mnt)',
        'dzuhur_time_in'           => 'Dzuhur Masuk',
        'dzuhur_time_out'          => 'Dzuhur Keluar',
        'dzuhur_time_late'         => 'Dzuhur Terlambat (mnt)',
        'ashar_time_in'            => 'Ashar Masuk',
        'ashar_time_out'           => 'Ashar Keluar',
        'ashar_time_late'          => 'Ashar Terlambat (mnt)',
        'maghrib_time_in'          => 'Maghrib Masuk',
        'maghrib_time_out'         => 'Maghrib Keluar',
        'maghrib_time_late'        => 'Maghrib Terlambat (mnt)',
        'isha_time_in'             => 'Isya Masuk',
        'isha_time_out'            => 'Isya Keluar',
        'isha_time_late'           => 'Isya Terlambat (mnt)',
        'friday_time_in'           => 'Jumat Masuk',
        'friday_time_out'          => 'Jumat Keluar',
        'friday_time_late'         => 'Jumat Terlambat (mnt)',
        'presence_type'            => 'Tipe Kehadiran',
        'presence_status'          => 'Status',
        'presence_get_paid'        => 'Dibayar (%)',
        'input_by'                 => 'Input Oleh',
        'input_by_user_id'         => 'Input User ID',
        'is_overtime'              => 'Lembur',
        'flag'                     => 'Flag',
        'is_early_leave'           => 'Pulang Lebih Awal',
        'early_leave_short_minutes'=> 'Durasi Pulang Awal (mnt)',
    ];

    public function __construct() {
        parent::__construct();
        if (!$this->ion_auth->logged_in()) { redirect(''); }
        $this->role     = $this->ion_auth->get_users_groups()->row()->name;
        $this->userdata = $this->ion_auth->user()->row();
        if ($this->role !== 'admin') { redirect('dashboard'); }
    }

    // GET /audit_log  — halaman daftar perubahan
    public function index() {
        $per_page = 50;
        $page     = max(1, (int)$this->input->get('page'));
        $offset   = ($page - 1) * $per_page;

        $date_from = $this->input->get('date_from') ?: date('Y-m-d', strtotime('-7 days'));
        $date_to   = $this->input->get('date_to')   ?: date('Y-m-d');
        $filter_action = $this->input->get('action') ?: '';

        $base = $this->db
            ->select('al.*, CONCAT(u.first_name," ",COALESCE(u.last_name,"")) AS changer_name,
                      pu.first_name AS pres_first, pu.last_name AS pres_last,
                      p.flow_date AS pres_date')
            ->from('audit_log al')
            ->join('users u',    'u.id = al.user_id', 'left')
            ->join('presence p', 'p.id = al.record_id AND al.table_name = "presence"', 'left')
            ->join('users pu',   'pu.id = p.user_id', 'left')
            ->where('al.changed_at >=', $date_from . ' 00:00:00')
            ->where('al.changed_at <=', $date_to . ' 23:59:59');

        if ($filter_action) { $base->where('al.action', $filter_action); }

        $total = $this->db->count_all_results('', false);
        $logs  = $base->order_by('al.changed_at', 'DESC')->limit($per_page, $offset)->get()->result_array();

        foreach ($logs as &$log) {
            $log['changes_summary'] = $this->_changes_summary(
                $log['action'], $log['before_data'], $log['after_data']
            );
        }

        $data = [
            'logs'        => $logs,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'date_from'   => $date_from,
            'date_to'     => $date_to,
            'filter_action' => $filter_action,
        ];
        $this->template->load('layout/admin', 'audit/index', $data);
    }

    // GET /audit_log/history/{presence_id}  — AJAX timeline untuk 1 record
    public function history($presence_id) {
        if (!$this->input->is_ajax_request()) { show_error('Bad Request', 400); }
        $presence_id = (int)$presence_id;

        $presence = $this->db->select('p.id, p.flow_date, p.user_id, CONCAT(u.first_name," ",COALESCE(u.last_name,"")) AS emp_name')
            ->from('presence p')
            ->join('users u', 'u.id = p.user_id', 'left')
            ->where('p.id', $presence_id)
            ->get()->row_array();

        if (!$presence) {
            echo json_encode(['status' => false, 'message' => 'Record tidak ditemukan.']);
            return;
        }

        $logs = $this->db
            ->select('al.id, al.action, al.before_data, al.after_data, al.changed_at,
                      CONCAT(u.first_name," ",COALESCE(u.last_name,"")) AS changer_name')
            ->from('audit_log al')
            ->join('users u', 'u.id = al.user_id', 'left')
            ->where('al.table_name', 'presence')
            ->where('al.record_id', $presence_id)
            ->order_by('al.changed_at', 'ASC')
            ->get()->result_array();

        $history = [];
        foreach ($logs as $log) {
            $history[] = [
                'id'           => (int)$log['id'],
                'action'       => $log['action'],
                'changed_at'   => $log['changed_at'],
                'changed_by'   => trim($log['changer_name']) ?: 'System',
                'changes'      => $this->_diff($log['before_data'], $log['after_data']),
                'can_rollback' => in_array($log['action'], ['INSERT', 'UPDATE']),
            ];
        }

        echo json_encode([
            'status'  => true,
            'record'  => $presence,
            'history' => $history,
        ]);
    }

    // POST /audit_log/rollback  — terapkan snapshot after_data ke record presence
    public function rollback() {
        if (!$this->input->is_ajax_request() || $this->input->method() !== 'post') {
            show_error('Bad Request', 400);
        }

        $log_id = (int)$this->input->post('log_id');
        if (!$log_id) {
            echo json_encode(['status' => false, 'message' => 'log_id wajib diisi.']);
            return;
        }

        $log = $this->db->where('id', $log_id)->where('table_name', 'presence')->get('audit_log')->row_array();
        if (!$log) {
            echo json_encode(['status' => false, 'message' => 'Log tidak ditemukan.']);
            return;
        }
        if (!in_array($log['action'], ['INSERT', 'UPDATE'])) {
            echo json_encode(['status' => false, 'message' => 'Hanya INSERT/UPDATE yang bisa di-rollback.']);
            return;
        }

        $snapshot = json_decode($log['after_data'], true);
        if (!$snapshot) {
            echo json_encode(['status' => false, 'message' => 'Data snapshot kosong.']);
            return;
        }

        // Kolom yang boleh di-restore (tidak termasuk natural key: id, user_id, flow_date)
        $allowed = array_keys(self::$FIELD_LABEL);
        $restore = array_intersect_key($snapshot, array_flip($allowed));

        if (empty($restore)) {
            echo json_encode(['status' => false, 'message' => 'Tidak ada kolom untuk di-restore.']);
            return;
        }

        // Tandai perubahan ini sebagai rollback oleh admin
        $uid = (int)$this->userdata->id;
        $this->db->query("SET @audit_user_id = {$uid}");

        $this->db->where('id', (int)$log['record_id'])->update('presence', $restore);

        if ($this->db->affected_rows() === 0) {
            echo json_encode(['status' => false, 'message' => 'Record tidak ditemukan atau tidak ada perubahan.']);
            return;
        }

        echo json_encode([
            'status'  => true,
            'message' => 'Data berhasil dikembalikan ke versi ' . date('d M Y H:i', strtotime($log['changed_at'])) . '.',
        ]);
    }

    // GET /audit_log/mass_rollback  — halaman UI rollback masal
    public function mass_rollback() {
        $res = $this->db->query("SELECT MIN(changed_at) AS min_time, MAX(changed_at) AS max_time FROM audit_log WHERE table_name = 'presence'");
        $row = $res->row_array();
        
        $users = $this->db->select('id, first_name, last_name, employee_code')
                          ->where('active', 1)
                          ->order_by('first_name', 'ASC')
                          ->get('users')->result_array();

        $data = [
            'min_time' => $row['min_time'] ?? null,
            'max_time' => $row['max_time'] ?? null,
            'users'    => $users,
        ];
        $this->template->load('layout/admin', 'audit/mass_rollback', $data);
    }

    // POST /audit_log/mass_rollback_simulate  — AJAX dry run
    public function mass_rollback_simulate() {
        if (!$this->input->is_ajax_request() || $this->input->method() !== 'post') {
            show_error('Bad Request', 400);
        }
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');
        $target = trim($this->input->post('target_time'));
        $filter_user = trim($this->input->post('filter_user_id'));
        $plan = $this->_calc_mass_rollback_plan($target, $filter_user);
        if (isset($plan['error'])) {
            echo json_encode(['status' => false, 'message' => $plan['error']]);
            return;
        }
        echo json_encode(['status' => true, 'plan' => $plan]);
    }

    // POST /audit_log/mass_rollback_execute  — AJAX execute to DB
    public function mass_rollback_execute() {
        if (!$this->input->is_ajax_request() || $this->input->method() !== 'post') {
            show_error('Bad Request', 400);
        }
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');
        $target = trim($this->input->post('target_time'));
        $filter_user = trim($this->input->post('filter_user_id'));
        $raw_selected = $this->input->post('selected_ids'); // can be array or string

        $selected_ids = [];
        if (!empty($raw_selected)) {
            if (is_string($raw_selected)) {
                $selected_ids = array_filter(array_map('intval', explode(',', $raw_selected)));
            } elseif (is_array($raw_selected)) {
                $selected_ids = array_map('intval', $raw_selected);
            }
        }

        $plan = $this->_calc_mass_rollback_plan($target, $filter_user);
        if (isset($plan['error'])) {
            echo json_encode(['status' => false, 'message' => $plan['error']]);
            return;
        }

        if (!empty($selected_ids)) {
            $sel_map = array_flip($selected_ids);
            $plan['to_update']   = array_intersect_key($plan['to_update'], $sel_map);
            $plan['to_delete']   = array_filter($plan['to_delete'], fn($id) => isset($sel_map[(int)$id]));
            $plan['to_reinsert'] = array_intersect_key($plan['to_reinsert'], $sel_map);
            $plan['counts']['upd'] = count($plan['to_update']);
            $plan['counts']['del'] = count($plan['to_delete']);
            $plan['counts']['ins'] = count($plan['to_reinsert']);
            $plan['counts']['total'] = $plan['counts']['upd'] + $plan['counts']['del'] + $plan['counts']['ins'];
        }

        if ($plan['counts']['total'] === 0) {
            echo json_encode(['status' => false, 'message' => 'Tidak ada baris data yang terpilih atau tidak ada perubahan yang diperlukan.']);
            return;
        }

        $uid = (int)$this->userdata->id;
        $this->db->trans_begin();
        $this->db->query("SET @audit_user_id = {$uid}");

        try {
            $exec_upd = 0;
            foreach ($plan['to_update'] as $rec_id => $data) {
                $restore = $data['data'];
                if (!empty($restore)) {
                    $this->db->where('id', (int)$rec_id)->update('presence', $restore);
                    $exec_upd++;
                }
            }

            $exec_del = 0;
            if (!empty($plan['to_delete'])) {
                $ids = array_map('intval', $plan['to_delete']);
                $this->db->where_in('id', $ids)->delete('presence');
                $exec_del = $this->db->affected_rows();
            }

            $exec_ins = 0;
            foreach ($plan['to_reinsert'] as $rec_id => $data) {
                $restore = $data['data'];
                if (!empty($restore)) {
                    $this->db->insert('presence', $restore);
                    $exec_ins++;
                }
            }

            if ($this->db->trans_status() === FALSE) {
                throw new Exception('Kesalahan transaksi database.');
            }
            $this->db->trans_commit();

            echo json_encode([
                'status'  => true,
                'message' => "Rollback berhasil dieksekusi! ({$exec_upd} di-restore, {$exec_del} di-delete, {$exec_ins} di-reinsert)",
                'counts'  => ['upd' => $exec_upd, 'del' => $exec_del, 'ins' => $exec_ins]
            ]);
        } catch (Exception $e) {
            $this->db->trans_rollback();
            echo json_encode(['status' => false, 'message' => 'Gagal eksekusi: ' . $e->getMessage()]);
        }
    }

    private function _calc_mass_rollback_plan($target_time, $filter_user_id = null) {
        $ts = strtotime($target_time);
        if (!$ts) return ['error' => 'Format waktu tidak valid.'];
        $target_sql = date('Y-m-d H:i:s', $ts);

        $res = $this->db->query("SELECT MIN(changed_at) AS min_time FROM audit_log WHERE table_name = 'presence'");
        $min_time = $res->row()->min_time ?? null;
        if (!$min_time || $target_sql < $min_time) {
            return ['error' => "Target waktu ({$target_sql}) lebih awal dari rekam audit tertua ({$min_time})."];
        }

        $sql = "SELECT al.record_id, al.action, al.before_data, al.after_data,
                       p.*, CONCAT(u.first_name, ' ', COALESCE(u.last_name,'')) AS emp_name
                FROM audit_log al
                INNER JOIN (
                    SELECT record_id, MIN(id) AS min_id
                    FROM audit_log
                    WHERE table_name = 'presence' AND changed_at > ?
                    GROUP BY record_id
                ) fl ON fl.min_id = al.id
                LEFT JOIN presence p ON p.id = al.record_id
                LEFT JOIN users u ON u.id = p.user_id";

        $query = $this->db->query($sql, [$target_sql]);
        $rows = $query->result_array();

        $allowed = array_keys(self::$FIELD_LABEL);
        $plan = [
            'target_time' => $target_sql,
            'to_update'   => [],
            'to_delete'   => [],
            'to_reinsert' => [],
            'preview'     => [],
            'counts'      => ['upd' => 0, 'del' => 0, 'ins' => 0, 'total' => 0]
        ];

        $fid = $filter_user_id ? (int)$filter_user_id : 0;

        foreach ($rows as $r) {
            $rec_id = (int)$r['record_id'];
            $act    = $r['action'];
            $before = json_decode($r['before_data'] ?? '{}', true) ?: [];
            $after  = json_decode($r['after_data'] ?? '{}', true) ?: [];
            $exists = !empty($r['id']);
            $emp    = trim($r['emp_name'] ?? '') ?: 'ID #' . $rec_id;
            $fdate  = $r['flow_date'] ?? $before['flow_date'] ?? $after['flow_date'] ?? '—';

            if ($fid > 0) {
                $uid_p = !empty($r['user_id']) ? (int)$r['user_id'] : 0;
                $uid_b = !empty($before['user_id']) ? (int)$before['user_id'] : 0;
                $uid_a = !empty($after['user_id']) ? (int)$after['user_id'] : 0;
                if ($uid_p !== $fid && $uid_b !== $fid && $uid_a !== $fid) {
                    continue;
                }
            }

            if ($act === 'INSERT') {
                if ($exists) {
                    $plan['to_delete'][] = $rec_id;
                    if (count($plan['preview']) < 5000) {
                        $diff = $this->_diff(json_encode($r), json_encode([]));
                        $plan['preview'][] = ['id' => $rec_id, 'emp' => $emp, 'date' => $fdate, 'type' => 'DELETE', 'info' => 'Hapus data absensi baru', 'diff' => $diff];
                    }
                }
            } elseif (in_array($act, ['UPDATE', 'DELETE'])) {
                $restore = array_intersect_key($before, array_flip($allowed));
                if ($exists) {
                    $plan['to_update'][$rec_id] = ['data' => $restore];
                    if (count($plan['preview']) < 5000) {
                        $diff = $this->_diff(json_encode($r), json_encode($restore));
                        $plan['preview'][] = ['id' => $rec_id, 'emp' => $emp, 'date' => $fdate, 'type' => 'UPDATE', 'info' => 'Kembalikan ke sebelum ' . date('H:i', $ts), 'diff' => $diff];
                    }
                } else {
                    $uid = $before['user_id'] ?? $after['user_id'] ?? null;
                    if ($uid && $fdate !== '—') {
                        $restore['id'] = $rec_id;
                        $restore['user_id'] = (int)$uid;
                        $restore['flow_date'] = $fdate;
                        $plan['to_reinsert'][$rec_id] = ['data' => $restore];
                        if (count($plan['preview']) < 5000) {
                            $diff = $this->_diff(json_encode([]), json_encode($restore));
                            $plan['preview'][] = ['id' => $rec_id, 'emp' => $emp, 'date' => $fdate, 'type' => 'REINSERT', 'info' => 'Pulihkan data terhapus', 'diff' => $diff];
                        }
                    }
                }
            }
        }

        $plan['counts']['upd'] = count($plan['to_update']);
        $plan['counts']['del'] = count($plan['to_delete']);
        $plan['counts']['ins'] = count($plan['to_reinsert']);
        $plan['counts']['total'] = $plan['counts']['upd'] + $plan['counts']['del'] + $plan['counts']['ins'];

        return $plan;
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function _diff($before_json, $after_json) {
        $before = $before_json ? (json_decode($before_json, true) ?? []) : [];
        $after  = $after_json  ? (json_decode($after_json,  true) ?? []) : [];
        $all    = array_unique(array_merge(array_keys($before), array_keys($after)));
        $diffs  = [];
        foreach ($all as $field) {
            if (!isset(self::$FIELD_LABEL[$field])) continue;
            $b = $before[$field] ?? null;
            $a = $after[$field]  ?? null;
            if ($b === $a) continue;
            $diffs[] = [
                'field'  => $field,
                'label'  => self::$FIELD_LABEL[$field],
                'before' => $this->_fmt($field, $b),
                'after'  => $this->_fmt($field, $a),
            ];
        }
        return $diffs;
    }

    private function _fmt($field, $val) {
        if ($val === null) return '—';
        $time_fields = ['entry_time','out_time','rest_time_in','rest_time_out'];
        $suffix_in   = substr($field, -8)  === '_time_in';
        $suffix_out  = substr($field, -9)  === '_time_out';
        if ($suffix_in || $suffix_out || in_array($field, $time_fields)) {
            return $val ? date('H:i', strtotime($val)) : '—';
        }
        return (string)$val;
    }

    private function _changes_summary($action, $before_json, $after_json) {
        if ($action === 'INSERT') return '<span class="badge bg-success">Buat baru</span>';
        if ($action === 'DELETE') return '<span class="badge bg-danger">Hapus</span>';
        $diffs = $this->_diff($before_json, $after_json);
        if (empty($diffs)) return '<span class="text-muted">—</span>';
        $parts = array_slice(array_map(fn($d) => '<b>' . htmlspecialchars($d['label']) . '</b>', $diffs), 0, 3);
        $more  = count($diffs) > 3 ? ' +' . (count($diffs) - 3) . ' lainnya' : '';
        return implode(', ', $parts) . $more;
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * API JSON untuk tool TEMUAN (laporan masalah di toko).
 * Auth: bearer token (Api_token) — sama seperti PWA karyawan.
 * SPA: tools/temuan (React + Vite). CSRF dikecualikan di config.
 */
class Temuan extends CI_Controller {

    const UPLOAD_DIR   = 'assets/images/temuan/';
    const ADMIN_ROLES  = ['admin', 'admin-branch'];

    /**
     * Cabang di TEMUAN mengikuti penempatan fisik (users.location), BUKAN
     * position.branch_id — semua karyawan administratif tercatat SDR di
     * users/position, sedangkan lokasi kerja sungguhan (termasuk GBR)
     * hanya tercatat di kolom bebas users.location. Keyword match.
     */
    const LOCATION_BRANCH_KEYWORDS = [
        1 => 'SUDIRMAN',
        2 => 'GAMBIR',
    ];

    private $user = null;
    private $role = null;

    public function __construct() {
        parent::__construct();
        $this->load->library('Api_token', null, 'apitoken');
        $this->load->model('temuan_model', 'temuan');
        $this->output->set_header('Access-Control-Allow-Origin: *');
        $this->output->set_header('Access-Control-Allow-Headers: Authorization, Content-Type');
        $this->output->set_header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        if (strtolower($this->input->method()) === 'options') { $this->_json(['status' => true]); exit; }
    }

    // ====================================================================
    // HELPERS
    // ====================================================================

    private function _json($data, $code = 200) {
        $this->output->set_status_header($code)
                     ->set_content_type('application/json')
                     ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    private function _bearer() {
        $h = $this->input->get_request_header('Authorization');
        if ($h && preg_match('/Bearer\s+(.+)/i', $h, $m)) { return trim($m[1]); }
        return $this->input->get('token') ?: null;
    }

    private function _auth() {
        $uid = $this->apitoken->verify($this->_bearer());
        if (!$uid) { $this->_json(['status' => false, 'message' => 'Sesi berakhir, silakan login ulang'], 401); return false; }
        $u = $this->db->select('users.*, position.branch_id, branch.branch_name, position.position_name')
            ->join('position', 'position.id = users.position_id', 'left')
            ->join('branch', 'branch.id = position.branch_id', 'left')
            ->where('users.id', $uid)->where('users.active', 1)
            ->get('users')->row_array();
        if (!$u) { $this->_json(['status' => false, 'message' => 'Akun tidak aktif'], 401); return false; }

        // Cabang efektif TEMUAN: dari lokasi fisik (users.location), fallback ke branch posisi kalau lokasi tak dikenal.
        $effective_branch_id = $this->_location_branch_id($u['location'] ?? null, $u['branch_id']);
        if ((int)$effective_branch_id !== (int)$u['branch_id']) {
            $eff = $this->db->select('branch_name')->where('id', $effective_branch_id)->get('branch')->row_array();
            if ($eff) { $u['branch_name'] = $eff['branch_name']; }
        }
        $u['branch_id'] = $effective_branch_id;

        $this->user = $u;
        $g = $this->db->select('groups.name')
            ->join('groups', 'groups.id = users_groups.group_id')
            ->where('users_groups.user_id', $uid)
            ->get('users_groups')->row_array();
        $this->role = $g ? $g['name'] : 'employee';
        return true;
    }

    private function _is_admin() {
        return in_array($this->role, self::ADMIN_ROLES, true);
    }

    /** Admin & inspector (ditunjuk) beroperasi lintas cabang; admin-branch terbatas cabangnya. */
    private function _is_full_access() {
        return $this->role === 'admin' || $this->temuan->is_inspector((int)$this->user['id']);
    }

    /** Cocokkan keyword lokasi fisik ke id branch; fallback ke branch posisi kalau tak dikenal (mis. Kanvas). */
    private function _location_branch_id($location, $fallback_branch_id) {
        if ($location) {
            foreach (self::LOCATION_BRANCH_KEYWORDS as $bid => $keyword) {
                if (stripos($location, $keyword) !== false) { return $bid; }
            }
        }
        return (int)$fallback_branch_id;
    }

    /** Cabang yang boleh diakses: admin & inspector (ditunjuk) bebas, lainnya cabang sendiri. */
    private function _scope_branch($requested) {
        if ($this->role === 'admin' || $this->temuan->is_inspector((int)$this->user['id'])) {
            return $requested !== null && $requested !== '' ? (int)$requested : null; // null = semua
        }
        return (int)$this->user['branch_id'];
    }

    private function _body() {
        $raw = $this->input->raw_input_stream;
        $j = $raw ? json_decode($raw, true) : null;
        return is_array($j) ? $j : ($this->input->post() ?: []);
    }

    /** Upload + resize foto. Return path relatif, ['path'=>null] kalau kosong & tak wajib, atau ['error'=>...]. */
    private function _upload_photo($field, $prefix, $required = true) {
        if (empty($_FILES[$field]['name'])) {
            return $required ? ['error' => 'Foto wajib dilampirkan'] : ['path' => null];
        }
        $dir = FCPATH . self::UPLOAD_DIR;
        if (!is_dir($dir)) { mkdir($dir, 0755, true); }

        $config = [
            'upload_path'   => $dir,
            'allowed_types' => 'png|jpeg|jpg|webp',
            'file_name'     => $prefix . '_' . $this->user['id'] . '_' . time(),
            'max_size'      => 10240,
            'max_width'     => 12000,
            'max_height'    => 12000,
        ];
        $this->load->library('upload', $config);
        $this->upload->initialize($config);
        if (!$this->upload->do_upload($field)) {
            return ['error' => strip_tags($this->upload->display_errors())];
        }
        $upl = $this->upload->data();

        $this->load->library('image_lib');
        $this->image_lib->initialize([
            'image_library'  => 'gd2',
            'source_image'   => $upl['full_path'],
            'maintain_ratio' => true,
            'width'          => 1200,
            'height'         => 1200,
            'quality'        => '80%',
        ]);
        $this->image_lib->resize();
        $this->image_lib->clear();

        return ['path' => self::UPLOAD_DIR . $upl['file_name']];
    }

    private function _photo_url($path) {
        return $path ? base_url($path) : null;
    }

    private function _row_out($r) {
        $r['photo_url']      = $this->_photo_url($r['photo_path']);
        $r['done_photo_url'] = $this->_photo_url($r['done_photo_path']);
        unset($r['photo_path'], $r['done_photo_path']);

        $effective_due = !empty($r['due_extended_at']) ? $r['due_extended_at'] : $r['due_at'];
        $r['effective_due_at'] = $effective_due;
        $r['is_late'] = $this->_compute_is_late($r, $effective_due);

        return $r;
    }

    /** Telat = lapor selesai lewat deadline, atau masih terbuka & sudah lewat deadline sekarang. */
    private function _compute_is_late($r, $effective_due) {
        if (!$effective_due || $r['status'] === 'ditolak') { return false; }
        if ($r['status'] === 'selesai') {
            return !empty($r['done_at']) && strtotime($r['done_at']) > strtotime($effective_due);
        }
        return time() > strtotime($effective_due);
    }

    // ====================================================================
    // PROFIL
    // ====================================================================

    // GET temuan/me
    public function me() {
        if (!$this->_auth()) return;
        $u = $this->user;
        $this->_json(['status' => true, 'user' => [
            'id'           => (int)$u['id'],
            'name'         => trim($u['first_name'] . ' ' . $u['last_name']),
            'role'         => $this->role,
            'is_admin'     => $this->_is_admin(),
            'is_inspector' => $this->temuan->is_inspector($u['id']),
            'is_spv'       => $this->temuan->has_spv_area($u['id']),
            'is_pj'        => $this->temuan->has_pj_area($u['id']),
            'branch_id'    => (int)$u['branch_id'],
            'branch'       => $u['branch_name'],
            'position'     => $u['position_name'],
        ]]);
    }

    // GET temuan/branches
    public function branches() {
        if (!$this->_auth()) return;
        if ($this->role === 'admin' || $this->temuan->is_inspector((int)$this->user['id'])) {
            $rows = $this->db->select('id, branch_name')->order_by('branch_name')->get('branch')->result_array();
        } else {
            $rows = $this->db->select('id, branch_name')->where('id', $this->user['branch_id'])->get('branch')->result_array();
        }
        $this->_json(['status' => true, 'rows' => $rows]);
    }

    // GET temuan/employees?branch_id= — kandidat PJ/SPV/Inspector, disaring per lokasi fisik (admin only)
    public function employees() {
        if (!$this->_auth()) return;
        $branch_id = $this->_scope_branch($this->input->get('branch_id'));
        // work_phone = no WA KERJA dari temuan_work_phone (BUKAN users.phone pribadi)
        // -- dipakai FE buat tahu siapa yang belum punya no saat ditugaskan PJ/Pengawas.
        $this->db->select("users.id, TRIM(CONCAT(users.first_name,' ',COALESCE(users.last_name,''))) AS name, position.position_name, users.location,
                           (SELECT wp.phone FROM temuan_work_phone wp WHERE wp.user_id = users.id) AS work_phone")
                 ->join('position', 'position.id = users.position_id', 'left')
                 ->where('users.active', 1);
        if ($branch_id !== null) {
            $bid = (int)$branch_id;
            $cases = '';
            foreach (self::LOCATION_BRANCH_KEYWORDS as $b => $kw) {
                $kw_esc = $this->db->escape_like_str($kw);
                $cases .= "WHEN users.location LIKE '%{$kw_esc}%' THEN {$b} ";
            }
            $this->db->where("(CASE {$cases}ELSE position.branch_id END) = {$bid}", null, false);
        }
        $rows = $this->db->order_by('name')->get('users')->result_array();
        $this->_json(['status' => true, 'rows' => $rows]);
    }

    // ====================================================================
    // ROSTER: INSPECTOR & SPV (master, pilih manual)
    // ====================================================================

    private function _roster_list($type) {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $this->_json(['status' => true, 'rows' => $this->temuan->get_roster($type)]);
    }

    private function _roster_add($type, $label) {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $p = $this->_body();
        $user_id = (int)($p['user_id'] ?? 0);
        $u = $this->db->select('users.id')->where('users.id', $user_id)->where('users.active', 1)->get('users')->row_array();
        if (!$u) { $this->_json(['status' => false, 'message' => 'Mitra tidak ditemukan'], 404); return; }
        if (!$this->temuan->add_to_roster($type, $user_id)) {
            $this->_json(['status' => false, 'message' => "Mitra sudah terdaftar sebagai {$label}"], 422); return;
        }
        $this->_json(['status' => true]);
    }

    private function _roster_delete($type, $label) {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $p = $this->_body();
        if (!$this->temuan->remove_from_roster($type, (int)($p['id'] ?? 0))) {
            $this->_json(['status' => false, 'message' => "{$label} tidak ditemukan"], 404); return;
        }
        $this->_json(['status' => true]);
    }

    public function inspectors()       { $this->_roster_list('inspector'); }
    public function inspector_add()    { $this->_roster_add('inspector', 'inspector'); }
    public function inspector_delete() { $this->_roster_delete('inspector', 'Inspector'); }

    // ====================================================================
    // JENIS (KATEGORI, master)
    // ====================================================================

    // GET temuan/categories?all=1
    public function categories() {
        if (!$this->_auth()) return;
        $active_only = !($this->_is_admin() && $this->input->get('all'));
        $this->_json(['status' => true, 'rows' => $this->temuan->get_categories($active_only)]);
    }

    // POST temuan/category_save {id?, name, is_active}
    public function category_save() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $p = $this->_body();
        $name = isset($p['name']) ? trim($p['name']) : '';
        if ($name === '') { $this->_json(['status' => false, 'message' => 'Nama jenis wajib diisi'], 422); return; }
        $data = ['name' => $name, 'is_active' => isset($p['is_active']) ? (int)!!$p['is_active'] : 1];
        $id = !empty($p['id']) ? (int)$p['id'] : null;
        $saved_id = $this->temuan->save_category($data, $id);
        $this->_json(['status' => true, 'id' => (int)$saved_id]);
    }

    // POST temuan/category_delete {id}
    public function category_delete() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $p = $this->_body();
        $id = (int)($p['id'] ?? 0);
        $existing = $this->temuan->get_category($id);
        if (!$existing) { $this->_json(['status' => false, 'message' => 'Jenis tidak ditemukan'], 404); return; }
        $result = $this->temuan->delete_category($id);
        $this->_json(['status' => true, 'result' => $result]);
    }

    // ====================================================================
    // NAMA TEMUAN (master)
    // ====================================================================

    // GET temuan/types?all=1
    public function types() {
        if (!$this->_auth()) return;
        $active_only = !($this->_is_admin() && $this->input->get('all'));
        $this->_json(['status' => true, 'rows' => $this->temuan->get_types($active_only)]);
    }

    // POST temuan/type_save {id?, category_id, name, target_mode, requires_action, require_photo_initial, require_photo_done, is_active}
    public function type_save() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $p = $this->_body();
        $name = isset($p['name']) ? trim($p['name']) : '';
        if ($name === '') { $this->_json(['status' => false, 'message' => 'Nama temuan wajib diisi'], 422); return; }
        $category_id = !empty($p['category_id']) ? (int)$p['category_id'] : null;
        if (!$category_id) { $this->_json(['status' => false, 'message' => 'Jenis (kategori) wajib dipilih'], 422); return; }
        $target_mode = ($p['target_mode'] ?? 'objek') === 'individu' ? 'individu' : 'objek';

        $data = [
            'category_id'            => $category_id,
            'name'                   => $name,
            'target_mode'            => $target_mode,
            'requires_action'        => isset($p['requires_action']) ? (int)!!$p['requires_action'] : 1,
            'require_photo_initial'  => isset($p['require_photo_initial']) ? (int)!!$p['require_photo_initial'] : 1,
            'require_photo_done'     => isset($p['require_photo_done']) ? (int)!!$p['require_photo_done'] : 1,
            'is_active'              => isset($p['is_active']) ? (int)!!$p['is_active'] : 1,
        ];
        $id = !empty($p['id']) ? (int)$p['id'] : null;
        $saved_id = $this->temuan->save_type($data, $id);
        $this->_json(['status' => true, 'id' => (int)$saved_id]);
    }

    // POST temuan/type_delete {id}
    public function type_delete() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $p = $this->_body();
        $id = (int)($p['id'] ?? 0);
        $existing = $this->temuan->get_type($id);
        if (!$existing) { $this->_json(['status' => false, 'message' => 'Jenis tidak ditemukan'], 404); return; }
        $result = $this->temuan->delete_type($id);
        $this->_json(['status' => true, 'result' => $result]);
    }

    // ====================================================================
    // LOKASI / KODE AREA (master)
    // ====================================================================

    // GET temuan/locations?branch_id=&all=1
    public function locations() {
        if (!$this->_auth()) return;
        $branch_id = $this->_scope_branch($this->input->get('branch_id'));
        $active_only = !($this->_is_admin() && $this->input->get('all'));
        $rows = $this->temuan->get_locations($branch_id, $active_only);
        $this->_json(['status' => true, 'rows' => $rows]);
    }

    // POST temuan/location_save {id?, branch_id, name, pj_user_ids[], spv_user_ids[], division_id, is_active}
    public function location_save() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $p = $this->_body();
        $name = isset($p['name']) ? trim($p['name']) : '';
        if ($name === '') { $this->_json(['status' => false, 'message' => 'Nama lokasi wajib diisi'], 422); return; }

        $branch_id = $this->role === 'admin'
            ? (int)($p['branch_id'] ?? 0)
            : (int)$this->user['branch_id'];
        if (!$branch_id) { $this->_json(['status' => false, 'message' => 'Cabang wajib dipilih'], 422); return; }

        $data = [
            'branch_id'      => $branch_id,
            'name'           => $name,
            'division_id'    => !empty($p['division_id']) ? (int)$p['division_id'] : null,
            'is_active'      => isset($p['is_active']) ? (int)!!$p['is_active'] : 1,
        ];

        $id = !empty($p['id']) ? (int)$p['id'] : null;
        if ($id) {
            $existing = $this->temuan->get_location($id);
            if (!$existing) { $this->_json(['status' => false, 'message' => 'Lokasi tidak ditemukan'], 404); return; }
            if ($this->role !== 'admin' && (int)$existing['branch_id'] !== (int)$this->user['branch_id']) {
                $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return;
            }
        }
        // No KERJA wajib utk tiap PJ/Pengawas yang ditugaskan (notif WA TIDAK
        // pakai users.phone pribadi -- karyawan dilarang bawa HP). FE mengirim
        // work_phones {user_id: no} utk yang belum punya; yang sudah tersimpan
        // di temuan_work_phone tidak perlu dikirim ulang.
        $pj_ids  = array_map('intval', $p['pj_user_ids'] ?? []);
        $spv_ids = array_map('intval', $p['spv_user_ids'] ?? (!empty($p['spv_user_id']) ? [$p['spv_user_id']] : []));
        $assigned_ids = array_values(array_unique(array_merge($pj_ids, $spv_ids)));

        $provided = [];
        foreach ((array)($p['work_phones'] ?? []) as $uid => $phone) {
            $phone = trim((string)$phone);
            if ((int)$uid && $phone !== '') { $provided[(int)$uid] = $phone; }
        }

        if (!empty($assigned_ids)) {
            $have = $this->temuan->get_work_phone_map($assigned_ids);
            $missing = [];
            foreach ($assigned_ids as $uid) {
                if (empty($have[$uid]) && empty($provided[$uid])) { $missing[] = $uid; }
            }
            if (!empty($missing)) {
                $names = $this->db->select("TRIM(CONCAT(first_name,' ',COALESCE(last_name,''))) AS name")
                                  ->where_in('id', $missing)->get('users')->result_array();
                $this->_json([
                    'status' => false,
                    'message' => 'No. WA kerja belum diisi untuk: '
                        . implode(', ', array_column($names, 'name'))
                        . '. Isi no kerja dulu (bukan no pribadi).',
                ], 422);
                return;
            }
            foreach ($provided as $uid => $phone) {
                if (in_array($uid, $assigned_ids, true)) { $this->temuan->set_work_phone($uid, $phone); }
            }
        }

        $saved_id = $this->temuan->save_location($data, $id);
        $this->temuan->set_location_pjs($saved_id, $pj_ids);
        $primary_spv_id = !empty($p['primary_spv_id']) ? (int)$p['primary_spv_id'] : null;
        $this->temuan->set_location_spvs($saved_id, $spv_ids, $primary_spv_id);
        $this->temuan->set_location_contacts($saved_id, $p['contact_ids'] ?? []);
        $this->_json(['status' => true, 'id' => (int)$saved_id]);
    }

    // GET temuan/location_pjs?location_id= — daftar PJ area tsb
    public function location_pjs() {
        if (!$this->_auth()) return;
        $location_id = (int)$this->input->get('location_id');
        $this->_json(['status' => true, 'rows' => $this->temuan->get_location_pjs($location_id)]);
    }

    // POST temuan/location_delete {id}
    public function location_delete() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $p = $this->_body();
        $id = (int)($p['id'] ?? 0);
        $existing = $this->temuan->get_location($id);
        if (!$existing) { $this->_json(['status' => false, 'message' => 'Lokasi tidak ditemukan'], 404); return; }
        if ($this->role !== 'admin' && (int)$existing['branch_id'] !== (int)$this->user['branch_id']) {
            $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return;
        }
        $result = $this->temuan->delete_location($id);
        $this->_json(['status' => true, 'result' => $result]);
    }

    // ====================================================================
    // DIVISI (master khusus TEMUAN — filter visibilitas SPV)
    // ====================================================================

    // GET temuan/divisions?all=1
    public function divisions() {
        if (!$this->_auth()) return;
        $active_only = !($this->_is_admin() && $this->input->get('all'));
        $this->_json(['status' => true, 'rows' => $this->temuan->get_divisions($active_only)]);
    }

    // POST temuan/division_save {id?, name, is_active}
    public function division_save() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $p = $this->_body();
        $name = isset($p['name']) ? trim($p['name']) : '';
        if ($name === '') { $this->_json(['status' => false, 'message' => 'Nama divisi wajib diisi'], 422); return; }
        $data = ['name' => $name, 'is_active' => isset($p['is_active']) ? (int)!!$p['is_active'] : 1];
        $id = !empty($p['id']) ? (int)$p['id'] : null;
        $saved_id = $this->temuan->save_division($data, $id);
        $this->_json(['status' => true, 'id' => (int)$saved_id]);
    }

    // POST temuan/division_delete {id}
    public function division_delete() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $p = $this->_body();
        $id = (int)($p['id'] ?? 0);
        $existing = $this->temuan->get_division($id);
        if (!$existing) { $this->_json(['status' => false, 'message' => 'Divisi tidak ditemukan'], 404); return; }
        $result = $this->temuan->delete_division($id);
        $this->_json(['status' => true, 'result' => $result]);
    }

    // ====================================================================
    // KONTAK NOTIFIKASI WA
    // ====================================================================

    // GET temuan/wa_contacts?all=1
    public function wa_contacts() {
        if (!$this->_auth()) return;
        $active_only = !($this->_is_admin() && $this->input->get('all'));
        $this->_json(['status' => true, 'rows' => $this->temuan->get_wa_contacts($active_only)]);
    }

    // POST temuan/wa_contact_save {id?, name, phone, is_active}
    public function wa_contact_save() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $p = $this->_body();
        $name  = isset($p['name']) ? trim($p['name']) : '';
        $phone = isset($p['phone']) ? trim($p['phone']) : '';
        if ($name === '') { $this->_json(['status' => false, 'message' => 'Nama kontak wajib diisi'], 422); return; }
        if ($phone === '' || !preg_match('/^[0-9+\-\s]+$/', $phone)) {
            $this->_json(['status' => false, 'message' => 'Nomor HP wajib diisi, format 628xxx'], 422); return;
        }
        $data = ['name' => $name, 'phone' => $phone, 'is_active' => isset($p['is_active']) ? (int)!!$p['is_active'] : 1];
        $id = !empty($p['id']) ? (int)$p['id'] : null;
        $saved_id = $this->temuan->save_wa_contact($data, $id);
        $this->_json(['status' => true, 'id' => (int)$saved_id]);
    }

    // POST temuan/wa_contact_delete {id}
    public function wa_contact_delete() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $p = $this->_body();
        $id = (int)($p['id'] ?? 0);
        if (!$this->temuan->get_wa_contact($id)) { $this->_json(['status' => false, 'message' => 'Kontak tidak ditemukan'], 404); return; }
        $this->temuan->delete_wa_contact($id);
        $this->_json(['status' => true]);
    }

    // ====================================================================
    // TEMUAN
    // ====================================================================

    // GET temuan/list?status=&branch_id=&location_id=&from=&to=&page=
    public function list() {
        if (!$this->_auth()) return;
        $branch_id = $this->_scope_branch($this->input->get('branch_id'));
        $vis_filters = $this->_visibility_filters();
        $filters = array_merge([
            'branch_id'   => $branch_id,
            'status'      => $this->input->get('status'),
            'location_id' => $this->input->get('location_id'),
            'type_id'     => $this->input->get('type_id'),
            'from'        => $this->input->get('from'),
            'to'          => $this->input->get('to'),
        ], $vis_filters);
        $page  = max(1, (int)($this->input->get('page') ?: 1));
        $limit = 50;
        $rows  = $this->temuan->list_temuan($filters, $limit, ($page - 1) * $limit);
        $this->_json([
            'status'  => true,
            'rows'    => array_map([$this, '_row_out'], $rows),
            'total'   => $this->temuan->count_temuan($filters),
            'page'    => $page,
            'per_page'=> $limit,
            'summary' => $this->temuan->status_summary($branch_id, false, null, null, $vis_filters),
            'me'      => ['id' => (int)$this->user['id'], 'is_admin' => $this->_is_admin()],
        ]);
    }

    // POST temuan/create (multipart: type_id, description, photo, + objek:location_id ATAU individu:branch_id,subject_user_ids[],spv_user_id?)
    // Hanya inspector terdaftar atau admin yang boleh memposting temuan.
    public function create() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin() && !$this->temuan->is_inspector($this->user['id'])) {
            $this->_json(['status' => false, 'message' => 'Hanya inspector yang boleh memposting temuan'], 403); return;
        }
        $type_id     = (int)$this->input->post('type_id');
        $description = trim((string)$this->input->post('description'));

        $type = $this->temuan->get_type($type_id);
        if (!$type || !(int)$type['is_active']) {
            $this->_json(['status' => false, 'message' => 'Jenis temuan tidak valid'], 422); return;
        }
        if (strlen($description) < 5) {
            $this->_json(['status' => false, 'message' => 'Keterangan minimal 5 karakter'], 422); return;
        }

        $requires_action = (bool)$type['requires_action'];
        $subject_ids = [];
        $individu_spv_id = null;

        if ($type['target_mode'] === 'individu') {
            $branch_id = (int)$this->input->post('branch_id');
            if (!$branch_id) { $this->_json(['status' => false, 'message' => 'Cabang wajib dipilih'], 422); return; }
            if ($this->role !== 'admin' && $branch_id !== (int)$this->user['branch_id']) {
                $this->_json(['status' => false, 'message' => 'Cabang bukan cabang Anda'], 403); return;
            }
            $subject_ids = array_filter(array_map('intval', (array)$this->input->post('subject_user_ids')));
            if (empty($subject_ids)) { $this->_json(['status' => false, 'message' => 'Pilih minimal satu mitra'], 422); return; }
            $valid_count = (int)$this->db->where_in('id', $subject_ids)->where('active', 1)->count_all_results('users');
            if ($valid_count !== count($subject_ids)) { $this->_json(['status' => false, 'message' => 'Ada mitra tidak valid'], 422); return; }
            $spv_post = (int)$this->input->post('spv_user_id');
            if ($spv_post) {
                $spv_row = $this->db->where(['id' => $spv_post, 'active' => 1])->get('users')->row_array();
                if (!$spv_row) { $this->_json(['status' => false, 'message' => 'Pengawas tidak valid'], 422); return; }
                $individu_spv_id = $spv_post;
            }
            $location_id = null;
        } else {
            $location_id = (int)$this->input->post('location_id');
            $loc = $this->temuan->get_location($location_id);
            if (!$loc || !(int)$loc['is_active']) {
                $this->_json(['status' => false, 'message' => 'Lokasi tidak valid'], 422); return;
            }
            if ($this->role !== 'admin' && (int)$loc['branch_id'] !== (int)$this->user['branch_id']) {
                $this->_json(['status' => false, 'message' => 'Lokasi bukan di cabang Anda'], 403); return;
            }
            $branch_id = (int)$loc['branch_id'];
        }

        $up = $this->_upload_photo('photo', 'temuan', (bool)$type['require_photo_initial']);
        if (isset($up['error'])) { $this->_json(['status' => false, 'message' => $up['error']], 422); return; }

        $data = [
            'branch_id'       => $branch_id,
            'location_id'     => $location_id,
            'individu_spv_id' => $individu_spv_id,
            'type_id'         => $type_id,
            'reporter_id'     => (int)$this->user['id'],
            'description'     => $description,
            'photo_path'      => $up['path'],
            'status'          => $requires_action ? 'baru' : 'selesai',
        ];
        if (!$requires_action) {
            // Jenis satu arah: tak butuh timer/deadline, langsung tercatat selesai.
            $data['acc_by'] = (int)$this->user['id'];
            $data['acc_at'] = date('Y-m-d H:i:s');
        }
        $id = $this->temuan->create_temuan($data);
        if (!empty($subject_ids)) {
            $this->temuan->add_subjects($id, $subject_ids);
        }
        if (!$requires_action) {
            // create_temuan() selalu set due_at H+1; jenis satu arah tak perlu, kosongkan lagi.
            $this->temuan->update_temuan($id, ['due_at' => null]);
        }

        $row = $this->temuan->get_temuan($id);
        $this->_notify_wa('temuan_baru', $row);
        $this->_json(['status' => true, 'id' => (int)$id, 'row' => $this->_row_out($row)]);
    }

    // POST temuan/take {id} — PJ lokasi / admin
    public function take() {
        if (!$this->_auth()) return;
        $p = $this->_body();
        $row = $this->temuan->get_temuan((int)($p['id'] ?? 0));
        if (!$row) { $this->_json(['status' => false, 'message' => 'Temuan tidak ditemukan'], 404); return; }
        if (!$this->_can_respond($row)) {
            $this->_json(['status' => false, 'message' => 'Hanya PJ area, Pengawas, inspector, atau admin yang boleh merespon'], 403); return;
        }
        if ($row['status'] !== 'baru') {
            $this->_json(['status' => false, 'message' => 'Status sudah ' . $row['status']], 422); return;
        }
        $this->temuan->update_temuan($row['id'], [
            'status'   => 'dikerjakan',
            'taken_by' => (int)$this->user['id'],
            'taken_as' => $this->_actor_label($row),
            'taken_at' => date('Y-m-d H:i:s'),
        ]);
        $this->_json(['status' => true, 'row' => $this->_row_out($this->temuan->get_temuan($row['id']))]);
    }

    // POST temuan/reject {id, reason} — PJ/SPV/admin, hanya status baru.
    // Penolakan = PENGAJUAN: masuk status menunggu_acc_tolak, final ditolak setelah di-ACC inspector/admin.
    public function reject() {
        if (!$this->_auth()) return;
        $p = $this->_body();
        $row = $this->temuan->get_temuan((int)($p['id'] ?? 0));
        if (!$row) { $this->_json(['status' => false, 'message' => 'Temuan tidak ditemukan'], 404); return; }
        if (!$this->_can_respond($row)) {
            $this->_json(['status' => false, 'message' => 'Hanya PJ area, Pengawas, inspector, atau admin yang boleh merespon'], 403); return;
        }
        if ($row['status'] !== 'baru') {
            $this->_json(['status' => false, 'message' => 'Hanya temuan berstatus baru yang bisa ditolak'], 422); return;
        }
        $reason = trim((string)($p['reason'] ?? ''));
        if (strlen($reason) < 5) {
            $this->_json(['status' => false, 'message' => 'Alasan penolakan minimal 5 karakter'], 422); return;
        }
        $this->temuan->update_temuan($row['id'], [
            'status'               => 'menunggu_acc_tolak',
            'reject_by'            => (int)$this->user['id'],
            'reject_as'            => $this->_actor_label($row),
            'reject_reason'        => $reason,
            'reject_at'            => date('Y-m-d H:i:s'),
            'reject_decision'      => null,
            'reject_decided_by'    => null,
            'reject_decided_at'    => null,
            'reject_decision_note' => null,
        ]);
        $this->_json(['status' => true, 'row' => $this->_row_out($this->temuan->get_temuan($row['id']))]);
    }

    // POST temuan/reject_decide {id, approve, note?} — inspector (scope cabang) / admin putuskan pengajuan penolakan.
    // Setuju → ditolak (final). Tidak setuju → lanjut wajib dikerjakan (status dikerjakan, pengaju jadi pengerjanya).
    public function reject_decide() {
        if (!$this->_auth()) return;
        $p = $this->_body();
        $row = $this->temuan->get_temuan((int)($p['id'] ?? 0));
        if (!$row) { $this->_json(['status' => false, 'message' => 'Temuan tidak ditemukan'], 404); return; }
        $can = $this->_is_full_access()
            || ($this->role === 'admin-branch' && (int)$row['branch_id'] === (int)$this->user['branch_id']);
        if (!$can) {
            $this->_json(['status' => false, 'message' => 'Hanya inspector atau admin yang boleh memutuskan penolakan'], 403); return;
        }
        if ($row['status'] !== 'menunggu_acc_tolak') {
            $this->_json(['status' => false, 'message' => 'Tidak ada pengajuan penolakan yang menunggu keputusan'], 422); return;
        }
        $approve = !empty($p['approve']);
        $note    = trim((string)($p['note'] ?? ''));
        if (!$approve && strlen($note) < 5) {
            $this->_json(['status' => false, 'message' => 'Keterangan kenapa penolakan tidak diterima wajib diisi, minimal 5 karakter'], 422); return;
        }
        $update  = [
            'reject_decision'      => $approve ? 'approved' : 'denied',
            'reject_decided_by'    => (int)$this->user['id'],
            'reject_decided_at'    => date('Y-m-d H:i:s'),
            'reject_decision_note' => $note !== '' ? $note : null,
        ];
        if ($approve) {
            $update['status'] = 'ditolak';
        } else {
            // Penolakan ditolak → temuan lanjut wajib dikerjakan oleh pengaju penolakan.
            $update['status']   = 'dikerjakan';
            $update['taken_by'] = (int)$row['reject_by'];
            $update['taken_as'] = $row['reject_as'];
            $update['taken_at'] = date('Y-m-d H:i:s');
        }
        $this->temuan->update_temuan($row['id'], $update);
        $this->_json(['status' => true, 'row' => $this->_row_out($this->temuan->get_temuan($row['id']))]);
    }

    // POST temuan/done (multipart: id, photo) — lapor pengerjaan → menunggu ACC inspector
    public function done() {
        if (!$this->_auth()) return;
        $row = $this->temuan->get_temuan((int)$this->input->post('id'));
        if (!$row) { $this->_json(['status' => false, 'message' => 'Temuan tidak ditemukan'], 404); return; }
        if (!$this->_can_respond($row)) {
            $this->_json(['status' => false, 'message' => 'Hanya PJ area, Pengawas, inspector, atau admin yang boleh merespon'], 403); return;
        }
        if (!in_array($row['status'], ['baru', 'dikerjakan'], true)) {
            $this->_json(['status' => false, 'message' => 'Status sudah ' . $row['status']], 422); return;
        }

        $require_photo = $row['type_require_photo_done'] === null ? true : (bool)$row['type_require_photo_done'];
        $up = $this->_upload_photo('photo', 'selesai', $require_photo);
        if (isset($up['error'])) { $this->_json(['status' => false, 'message' => $up['error']], 422); return; }

        $this->temuan->update_temuan($row['id'], [
            'status'          => 'menunggu_acc',
            'done_by'         => (int)$this->user['id'],
            'done_as'         => $this->_actor_label($row),
            'done_at'         => date('Y-m-d H:i:s'),
            'done_photo_path' => $up['path'],
        ]);

        $fresh = $this->temuan->get_temuan($row['id']);
        $this->_notify_wa('temuan_lapor', $fresh);
        $this->_json(['status' => true, 'row' => $this->_row_out($fresh)]);
    }

    // POST temuan/acc {id} — inspector terdaftar (siapapun, scope cabang) atau admin menutup temuan
    public function acc() {
        if (!$this->_auth()) return;
        $p = $this->_body();
        $row = $this->temuan->get_temuan((int)($p['id'] ?? 0));
        if (!$row) { $this->_json(['status' => false, 'message' => 'Temuan tidak ditemukan'], 404); return; }
        $can_acc = $this->_is_full_access()
            || ($this->role === 'admin-branch' && (int)$row['branch_id'] === (int)$this->user['branch_id']);
        if (!$can_acc) {
            $this->_json(['status' => false, 'message' => 'Hanya inspector atau admin yang boleh ACC'], 403); return;
        }
        if ($row['status'] !== 'menunggu_acc') {
            $this->_json(['status' => false, 'message' => 'Temuan belum dilaporkan selesai'], 422); return;
        }
        $this->temuan->update_temuan($row['id'], [
            'status' => 'selesai',
            'acc_by' => (int)$this->user['id'],
            'acc_at' => date('Y-m-d H:i:s'),
        ]);
        $fresh = $this->temuan->get_temuan($row['id']);
        $this->_notify_wa('temuan_selesai', $fresh);
        $this->_json(['status' => true, 'row' => $this->_row_out($fresh)]);
    }

    // POST temuan/extend_request {id, reason} — SPV area / SPV backup / admin
    public function extend_request() {
        if (!$this->_auth()) return;
        $p = $this->_body();
        $row = $this->temuan->get_temuan((int)($p['id'] ?? 0));
        if (!$row) { $this->_json(['status' => false, 'message' => 'Temuan tidak ditemukan'], 404); return; }
        if (!$this->_can_request_extension($row)) {
            $this->_json(['status' => false, 'message' => 'Hanya Pengawas area, Pengawas backup, atau admin yang boleh mengajukan tambahan waktu'], 403); return;
        }
        if (!in_array($row['status'], ['baru', 'dikerjakan'], true)) {
            $this->_json(['status' => false, 'message' => 'Status sudah ' . $row['status'] . ', tidak bisa mengajukan tambahan waktu'], 422); return;
        }
        if ($row['extension_status'] === 'pending') {
            $this->_json(['status' => false, 'message' => 'Masih ada pengajuan tambahan waktu yang menunggu keputusan'], 422); return;
        }
        $reason = trim((string)($p['reason'] ?? ''));
        if (strlen($reason) < 5) {
            $this->_json(['status' => false, 'message' => 'Alasan pengajuan minimal 5 karakter'], 422); return;
        }
        $this->temuan->update_temuan($row['id'], [
            'extension_status'        => 'pending',
            'extension_reason'        => $reason,
            'extension_requested_by'  => (int)$this->user['id'],
            'extension_requested_as'  => $this->_actor_label($row),
            'extension_requested_at'  => date('Y-m-d H:i:s'),
            'extension_decided_by'    => null,
            'extension_decided_at'    => null,
            'extension_decision_note' => null,
        ]);
        $fresh = $this->temuan->get_temuan($row['id']);
        $this->_notify_wa('temuan_extend_request', $fresh);
        $this->_json(['status' => true, 'row' => $this->_row_out($fresh)]);
    }

    // POST temuan/extend_decide {id, approve, new_due_at?, note?} — inspector / admin
    public function extend_decide() {
        if (!$this->_auth()) return;
        $p = $this->_body();
        $row = $this->temuan->get_temuan((int)($p['id'] ?? 0));
        if (!$row) { $this->_json(['status' => false, 'message' => 'Temuan tidak ditemukan'], 404); return; }
        if (!$this->_can_decide_extension($row)) {
            $this->_json(['status' => false, 'message' => 'Hanya inspector atau admin yang boleh memutuskan pengajuan'], 403); return;
        }
        if ($row['extension_status'] !== 'pending') {
            $this->_json(['status' => false, 'message' => 'Tidak ada pengajuan tambahan waktu yang menunggu'], 422); return;
        }
        $approve = !empty($p['approve']);
        $note = trim((string)($p['note'] ?? ''));
        $update = [
            'extension_decided_by'    => (int)$this->user['id'],
            'extension_decided_at'    => date('Y-m-d H:i:s'),
            'extension_decision_note' => $note !== '' ? $note : null,
        ];
        if ($approve) {
            $new_due = trim((string)($p['new_due_at'] ?? ''));
            if ($new_due === '' || strtotime($new_due) === false) {
                $this->_json(['status' => false, 'message' => 'Tanggal deadline baru wajib diisi dan valid'], 422); return;
            }
            if (strlen($new_due) <= 10) { $new_due .= ' 23:59:59'; } // tanggal saja -> akhir hari
            $update['extension_status'] = 'approved';
            $update['due_extended_at']  = date('Y-m-d H:i:s', strtotime($new_due));
        } else {
            $update['extension_status'] = 'rejected';
        }
        $this->temuan->update_temuan($row['id'], $update);
        $fresh = $this->temuan->get_temuan($row['id']);
        $this->_notify_wa($approve ? 'temuan_extend_approved' : 'temuan_extend_rejected', $fresh);
        $this->_json(['status' => true, 'row' => $this->_row_out($fresh)]);
    }

    // POST temuan/delete {id} — admin, status apapun (soft delete, tetap terekap di Laporan)
    public function delete() {
        if (!$this->_auth()) return;
        $p = $this->_body();
        $row = $this->temuan->get_temuan((int)($p['id'] ?? 0));
        if (!$row) { $this->_json(['status' => false, 'message' => 'Temuan tidak ditemukan'], 404); return; }
        $can = $this->_is_full_access()
            || ($this->role === 'admin-branch' && (int)$row['branch_id'] === (int)$this->user['branch_id']);
        if (!$can) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $this->temuan->update_temuan($row['id'], ['is_deleted' => 1]);
        $this->_json(['status' => true]);
    }

    // GET temuan/report?branch_id=&from=&to= — rekap temuan + penanganan (termasuk yang dihapus)
    public function report() {
        if (!$this->_auth()) return;
        $branch_id = $this->_scope_branch($this->input->get('branch_id'));
        $vis_filters = $this->_visibility_filters();
        $from = $this->input->get('from') ?: date('Y-m-01');
        $to   = $this->input->get('to') ?: date('Y-m-d');
        $filters = array_merge([
            'branch_id'       => $branch_id,
            'from'            => $from,
            'to'              => $to,
            'include_deleted' => true,
        ], $vis_filters);
        $rows = $this->temuan->list_temuan($filters, 500, 0);
        $this->_json([
            'status'  => true,
            'from'    => $from,
            'to'      => $to,
            'rows'    => array_map([$this, '_row_out'], $rows),
            'summary' => $this->temuan->status_summary($branch_id, true, $from, $to, $vis_filters),
        ]);
    }

    // GET temuan/report_summary?branch_id=&from=&to= — rekap per PJ/SPV area (utk tabel Laporan)
    public function report_summary() {
        if (!$this->_auth()) return;
        $branch_id = $this->_scope_branch($this->input->get('branch_id'));
        $from = $this->input->get('from') ?: date('Y-m-01');
        $to   = $this->input->get('to') ?: date('Y-m-d');
        $this->_json([
            'status' => true, 'from' => $from, 'to' => $to,
            'rows'   => $this->_aggregate_report($branch_id, $from, $to, $this->_visibility_filters()),
        ]);
    }

    // GET temuan/report_excel?branch_id=&from=&to=&token= — unduh rekap bulanan .xlsx
    public function report_excel() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin() && !$this->temuan->is_inspector($this->user['id'])) {
            $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return;
        }
        $branch_id = $this->_scope_branch($this->input->get('branch_id'));
        $from = $this->input->get('from') ?: date('Y-m-01');
        $to   = $this->input->get('to') ?: date('Y-m-d');
        $rows = $this->_aggregate_report($branch_id, $from, $to);

        require_once FCPATH . 'lib/vendor/autoload.php';
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Rekap Temuan');
        $headers = ['No', 'Kode Area', 'Cabang', 'PJ Area', 'Pengawas Area', 'Jumlah Temuan', 'Selesai Tepat Waktu', 'Tidak Selesai'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $r = 2;
        foreach ($rows as $i => $row) {
            $sheet->fromArray([
                $i + 1, $row['location_name'], $row['branch_name'], $row['pj_name'], $row['spv_name'],
                $row['total'], $row['selesai_tepat_waktu'], $row['tidak_selesai'],
            ], null, 'A' . $r);
            $r++;
        }
        foreach (range('A', 'H') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

        $filename = 'Rekap_Temuan_' . $from . '_sd_' . $to . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save('php://output');
        exit;
    }

    // GET temuan/report_detail_excel?branch_id=&from=&to=&token= — unduh detail temuan .xlsx
    public function report_detail_excel() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin() && !$this->temuan->is_inspector($this->user['id'])) {
            $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return;
        }
        $branch_id = $this->_scope_branch($this->input->get('branch_id'));
        $vis_filters = $this->_visibility_filters();
        $from = $this->input->get('from') ?: date('Y-m-01');
        $to   = $this->input->get('to') ?: date('Y-m-d');
        $rows = array_map([$this, '_row_out'], $this->temuan->list_temuan(array_merge([
            'branch_id'       => $branch_id,
            'from'            => $from,
            'to'              => $to,
            'include_deleted' => true,
        ], $vis_filters), 5000, 0));

        require_once FCPATH . 'lib/vendor/autoload.php';
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Detail Temuan');
        $headers = ['No', 'Tanggal', 'Jenis', 'Kode Area / Mitra', 'Cabang', 'Keterangan',
                    'Link Foto Temuan', 'Link Foto Pengerjaan',
                    'Inspector (Pembuat Laporan)', 'Status', 'Terlambat',
                    'Dikerjakan Oleh', 'Waktu Dikerjakan', 'Lapor Selesai Oleh', 'Waktu Lapor Selesai',
                    'ACC Oleh', 'Waktu ACC', 'Ditolak Oleh', 'Alasan Ditolak', 'Dihapus Admin'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:T1')->getFont()->setBold(true);
        $status_label = ['baru' => 'Baru', 'dikerjakan' => 'Dikerjakan', 'menunggu_acc' => 'Menunggu ACC', 'menunggu_acc_tolak' => 'Menunggu ACC Tolak', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak'];
        $r = 2;
        foreach ($rows as $row) {
            $telat = $row['status'] === 'ditolak' ? '-' : ($row['is_late'] ? 'Telat' : 'Tepat waktu');
            $sheet->fromArray([
                $r - 1,
                $row['created_at'],
                $row['type_name'] ?: '-',
                ($row['type_target_mode'] ?? 'objek') === 'individu' ? ($row['subject_names'] ?: '-') : $row['location_name'],
                $row['branch_name'],
                $row['description'],
                $row['photo_url'] ?: '-',
                $row['done_photo_url'] ?: '-',
                $row['reporter_name'],
                $status_label[$row['status']] ?? $row['status'],
                $telat,
                $row['taken_by_name'] ?: '-',
                $row['taken_at'] ?: '-',
                $row['done_by_name'] ?: '-',
                $row['done_at'] ?: '-',
                $row['acc_by_name'] ?: '-',
                $row['acc_at'] ?: '-',
                $row['reject_by_name'] ?: '-',
                $row['reject_reason'] ?: '-',
                (int)$row['is_deleted'] ? 'Ya' : 'Tidak',
            ], null, 'A' . $r);
            if (!empty($row['photo_url'])) { $sheet->getCell('G' . $r)->getHyperlink()->setUrl($row['photo_url']); }
            if (!empty($row['done_photo_url'])) { $sheet->getCell('H' . $r)->getHyperlink()->setUrl($row['done_photo_url']); }
            $r++;
        }
        foreach (range('A', 'T') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

        $filename = 'Detail_Temuan_' . $from . '_sd_' . $to . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save('php://output');
        exit;
    }

    /** Agregasi per kode area: jumlah temuan, selesai tepat waktu, tidak selesai (telat/masih terbuka). */
    private function _aggregate_report($branch_id, $from, $to, $vis_filters = []) {
        $rows = $this->temuan->get_report_rows($branch_id, $from, $to);
        $vis_uid = !empty($vis_filters['visible_to']) ? (int)$vis_filters['visible_to'] : 0;
        $groups = [];
        foreach ($rows as $r) {
            if ($r['type_target_mode'] === 'individu' || empty($r['location_id'])) { continue; } // rekap per area, bukan individu
            // Karyawan biasa/PJ/Pengawas: hanya area yang ditugaskan.
            if ($vis_uid && !$this->_is_location_pj($r, $vis_uid) && !$this->_is_location_spv($r, $vis_uid)) { continue; }
            $key = $r['location_id'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'location_id'         => (int)$r['location_id'],
                    'location_name'       => $r['location_name'],
                    'branch_name'         => $r['branch_name'],
                    'pj_name'             => $r['pj_name'] ?: '-',
                    'spv_name'            => $r['spv_name'] ?: '-',
                    'total'               => 0,
                    'selesai_tepat_waktu' => 0,
                    'tidak_selesai'       => 0,
                ];
            }
            $effective_due = !empty($r['due_extended_at']) ? $r['due_extended_at'] : $r['due_at'];
            $is_late = $this->_compute_is_late($r, $effective_due);
            $groups[$key]['total']++;
            if ($r['status'] === 'selesai' && !$is_late) {
                $groups[$key]['selesai_tepat_waktu']++;
            } else {
                $groups[$key]['tidak_selesai']++;
            }
        }
        return array_values($groups);
    }

    /** PJ area kini bisa banyak orang; pj_user_ids = string "1,5,9" dari _select_full(). */
    private function _is_location_pj($row, $uid) {
        if (empty($row['pj_user_ids'])) { return false; }
        return in_array((string)$uid, explode(',', (string)$row['pj_user_ids']), true);
    }

    /** Mode individu: apakah user salah satu karyawan yang ditag di temuan tsb. */
    private function _is_subject($row, $uid) {
        if (empty($row['subject_user_ids'])) { return false; }
        return in_array((string)$uid, explode(',', (string)$row['subject_user_ids']), true);
    }

    /** SPV area kini bisa banyak orang; spv_user_ids = string "1,5,9". */
    private function _is_location_spv($row, $uid) {
        if (empty($row['spv_user_ids'])) { return false; }
        return in_array((string)$uid, explode(',', (string)$row['spv_user_ids']), true);
    }

    private function _can_respond($row) {
        if ($this->_is_admin()) {
            return $this->role === 'admin' || (int)$row['branch_id'] === (int)$this->user['branch_id'];
        }
        $uid = (int)$this->user['id'];

        if (($row['type_target_mode'] ?? 'objek') === 'individu') {
            // Individu: hanya karyawan yang ditag atau SPV ad-hoc yang dipilih saat lapor.
            return $this->_is_subject($row, $uid) || (int)$row['individu_spv_id'] === $uid;
        }

        // PJ / SPV area lokasi tsb (keduanya melekat per area)
        if ($this->_is_location_pj($row, $uid) || $this->_is_location_spv($row, $uid)) { return true; }
        // Inspector (ditunjuk): boleh bertindak sebagai PJ/SPV cadangan, lintas cabang
        if ($this->temuan->is_inspector($uid)) {
            return true;
        }
        // Backup SPV: SPV area lain di cabang yg sama boleh bantu saat SPV asli libur
        if ($this->temuan->has_spv_area($uid)) {
            return (int)$row['branch_id'] === (int)$this->user['branch_id'];
        }
        return false;
    }

    /** Label pelaku respon: PJ / Pengawas (area tsb, backup, atau ad-hoc individu), Inspector, atau Admin. */
    private function _actor_label($row) {
        $uid = (int)$this->user['id'];
        if ($this->_is_subject($row, $uid)) { return 'PJ'; }
        if ($this->_is_location_pj($row, $uid)) { return 'PJ'; }
        if ($this->_is_location_spv($row, $uid) || (int)$row['individu_spv_id'] === $uid) { return 'Pengawas'; }
        if (!$this->_is_admin()) {
            if ($this->temuan->has_spv_area($uid)) { return 'Pengawas'; } // backup pengawas
            if ($this->temuan->is_inspector($uid)) { return 'Inspector'; }
        }
        return 'Admin';
    }

    /**
     * Filter visibilitas — return array yang di-merge ke $filters:
     *   [] = lihat semua di cabangnya (admin, inspector)
     *   ['visible_to' => uid] = PJ/Pengawas/mitra: hanya area yang ditugaskan / yang ditag
     */
    private function _visibility_filters() {
        if ($this->_is_admin()) { return []; }
        $uid = (int)$this->user['id'];
        if ($this->temuan->is_inspector($uid)) { return []; }
        return ['visible_to' => $uid];
    }

    /** Ajukan tambahan waktu: SPV area tsb / SPV ad-hoc individu / SPV backup cabang / admin. */
    private function _can_request_extension($row) {
        if ($this->_is_admin()) {
            return $this->role === 'admin' || (int)$row['branch_id'] === (int)$this->user['branch_id'];
        }
        $uid = (int)$this->user['id'];
        if ($this->_is_location_spv($row, $uid) || (int)$row['individu_spv_id'] === $uid) { return true; }
        if (($row['type_target_mode'] ?? 'objek') === 'individu') { return false; }
        if ($this->temuan->has_spv_area($uid)) { return (int)$row['branch_id'] === (int)$this->user['branch_id']; }
        return false;
    }

    /** Putuskan pengajuan tambahan waktu: inspector (lintas cabang) / admin. */
    private function _can_decide_extension($row) {
        return $this->_is_full_access()
            || ($this->role === 'admin-branch' && (int)$row['branch_id'] === (int)$this->user['branch_id']);
    }

    // ====================================================================
    // CONFIG NOTIFIKASI
    // ====================================================================

    // GET temuan/config
    public function config() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $this->_json(['status' => true, 'config' => $this->temuan->get_config()]);
    }

    // POST temuan/save_config {notify_enabled, notify_done_enabled, target_phones}
    public function save_config() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $p = $this->_body();
        $phones = isset($p['target_phones']) ? trim($p['target_phones']) : '';
        if ($phones !== '' && !preg_match('/^[0-9,\s+\-]+$/', $phones)) {
            $this->_json(['status' => false, 'message' => 'Nomor target tidak valid. Gunakan 628xxx, pisahkan dengan koma.'], 422); return;
        }
        $this->temuan->save_config([
            'notify_enabled'      => !empty($p['notify_enabled']) ? 1 : 0,
            'notify_done_enabled' => !empty($p['notify_done_enabled']) ? 1 : 0,
            'target_phones'       => $phones,
        ]);
        $this->_json(['status' => true]);
    }

    // POST temuan/test_send {phone, message?} — kirim pesan test via gateway WA
    public function test_send() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $p = $this->_body();
        $phone = trim((string)($p['phone'] ?? ''));
        if ($phone === '') { $this->_json(['status' => false, 'message' => 'Nomor HP wajib diisi'], 422); return; }

        $this->load->model('wa_model', 'wa');
        $wa_cfg = $this->wa->get_config();
        if (empty($wa_cfg) || empty($wa_cfg['secret'])) {
            $this->_json(['status' => false, 'message' => 'Gateway WA belum dikonfigurasi (isi API Key di menu WA Agent)'], 422); return;
        }

        $message = trim((string)($p['message'] ?? '')) ?: ('Halo! Ini pesan test dari Aplikasi Temuan ' . date('d/m/Y H:i') . '.');

        $this->load->library('hermes_wa');
        $wa = new Hermes_wa(['api_key' => $wa_cfg['secret']]);
        $result = $wa->send($phone, $message);

        $this->wa->insert_log([
            'type'       => 'temuan_test',
            'phone'      => $wa->normalize_phone($phone),
            'message'    => $message,
            'status'     => $result['success'] ? 'success' : 'failed',
            'http_code'  => $result['http_code'],
            'response'   => $result['response'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->_json([
            'status'  => $result['success'],
            'message' => $result['success'] ? 'Pesan test terkirim' : ('Gagal: ' . $result['response']),
        ]);
    }

    // ====================================================================
    // NOTIFIKASI WA (Hermes wa-api — reuse kredensial WA Agent)
    // ====================================================================

    private function _notify_wa($type, $row) {
        $cfg = $this->temuan->get_config();
        if (!$cfg) return;
        if ($type === 'temuan_baru' && empty($cfg['notify_enabled'])) return;
        $progress_types = ['temuan_lapor', 'temuan_selesai', 'temuan_extend_request', 'temuan_extend_approved', 'temuan_extend_rejected'];
        if (in_array($type, $progress_types, true) && empty($cfg['notify_done_enabled'])) return;

        // Target notif: nomor bersama (Kelola -> Notifikasi, target_phones) SELALU ikut di
        // semua kasus, digabung (bukan fallback) dengan nomor yang ditag khusus area tsb --
        // semua PJ + Pengawas Utama saja (bukan backup) + kontak WA yang dicentang buat
        // lokasi itu (Kelola -> Kode Area). Mode individu: + nomor Pengawas ad-hoc yang
        // dipilih saat lapor.
        $phones = array_filter(array_map('trim', explode(',', (string)$cfg['target_phones'])));
        if (!empty($row['location_id'])) {
            $primary = $this->temuan->get_primary_spv_phone($row['location_id']);
            $phones = array_merge(
                $phones,
                $this->temuan->get_location_pj_phones($row['location_id']),
                $primary ? [$primary] : [],
                $this->temuan->get_location_contact_phones($row['location_id'])
            );
        }
        if (!empty($row['individu_spv_id'])) {
            // No KERJA (temuan_work_phone), BUKAN users.phone pribadi. Pengawas
            // ad-hoc tanpa no kerja tersimpan = tidak dikirimi WA (tanpa fallback).
            $wp = $this->temuan->get_work_phone((int)$row['individu_spv_id']);
            if ($wp) { $phones[] = $wp; }
        }
        $phones = array_values(array_unique(array_filter(array_map('trim', $phones))));
        if (empty($phones)) return;

        $this->load->model('wa_model', 'wa');
        $wa_cfg = $this->wa->get_config();
        if (empty($wa_cfg) || empty($wa_cfg['secret']) || empty($wa_cfg['is_active'])) return;

        $this->load->library('hermes_wa');
        $wa = new Hermes_wa(['api_key' => $wa_cfg['secret']]);

        $message = $this->_build_message($type, $row);
        foreach ($phones as $phone) {
            $result = $wa->send($phone, $message);
            $this->wa->insert_log([
                'type'       => $type,
                'phone'      => $wa->normalize_phone($phone),
                'message'    => $message,
                'status'     => $result['success'] ? 'success' : 'failed',
                'http_code'  => $result['http_code'],
                'response'   => $result['response'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /** Baris lokasi (mode objek) atau nama karyawan yang ditag (mode individu) utk pesan WA. */
    private function _target_line($row) {
        if (($row['type_target_mode'] ?? 'objek') === 'individu') {
            $names = $row['subject_names'] ?? '-';
            return "👤 Mitra     : {$names}\n";
        }
        return "📍 Lokasi    : {$row['location_name']}\n";
    }

    private function _build_message($type, $row) {
        $when = date('d/m/Y H:i');
        if ($type === 'temuan_baru') {
            $msg  = "🚨 *TEMUAN BARU*\n";
            $msg .= str_repeat("─", 30) . "\n";
            $msg .= "🏢 Cabang    : {$row['branch_name']}\n";
            $msg .= $this->_target_line($row);
            $msg .= "📝 Keterangan: {$row['description']}\n";
            $msg .= "👤 Pelapor   : {$row['reporter_name']}\n";
            if (!empty($row['pj_name'])) {
                $msg .= "🛠 PJ        : {$row['pj_name']}\n";
            }
            $msg .= "🕐 {$when}\n";
            $msg .= str_repeat("─", 30);
            $msg .= "\n_Pesan otomatis dari Aplikasi Temuan_";
            return $msg;
        }
        if ($type === 'temuan_extend_request') {
            $msg  = "⏳ *PENGAJUAN TAMBAHAN WAKTU*\n";
            $msg .= str_repeat("─", 30) . "\n";
            $msg .= "🏢 Cabang    : {$row['branch_name']}\n";
            $msg .= $this->_target_line($row);
            $msg .= "📝 Keterangan: {$row['description']}\n";
            $req_label = !empty($row['extension_requested_as']) ? " ({$row['extension_requested_as']})" : '';
            $msg .= "🙋 Diajukan  : {$row['extension_requested_by_name']}{$req_label}\n";
            $msg .= "💬 Alasan    : {$row['extension_reason']}\n";
            $msg .= "⏰ Deadline saat ini: " . date('d/m/Y H:i', strtotime($row['due_at'])) . "\n";
            $msg .= "🕐 {$when}\n";
            $msg .= str_repeat("─", 30);
            $msg .= "\n_Pesan otomatis dari Aplikasi Temuan_";
            return $msg;
        }
        if ($type === 'temuan_extend_approved' || $type === 'temuan_extend_rejected') {
            $approved = $type === 'temuan_extend_approved';
            $msg  = $approved ? "✅ *TAMBAHAN WAKTU DISETUJUI*\n" : "❌ *TAMBAHAN WAKTU DITOLAK*\n";
            $msg .= str_repeat("─", 30) . "\n";
            $msg .= "🏢 Cabang    : {$row['branch_name']}\n";
            $msg .= $this->_target_line($row);
            $msg .= "📝 Keterangan: {$row['description']}\n";
            $msg .= "🙋 Diajukan  : {$row['extension_requested_by_name']}\n";
            if ($approved) {
                $msg .= "⏰ Deadline baru: " . date('d/m/Y H:i', strtotime($row['due_extended_at'])) . "\n";
            }
            $msg .= "🆗 Diputuskan: {$row['extension_decided_by_name']}\n";
            if (!empty($row['extension_decision_note'])) {
                $msg .= "💬 Catatan   : {$row['extension_decision_note']}\n";
            }
            $msg .= "🕐 {$when}\n";
            $msg .= str_repeat("─", 30);
            $msg .= "\n_Pesan otomatis dari Aplikasi Temuan_";
            return $msg;
        }
        $done_label = !empty($row['done_as']) ? " ({$row['done_as']})" : '';
        if ($type === 'temuan_lapor') {
            $msg  = "🔔 *TEMUAN DILAPORKAN SELESAI*\n";
            $msg .= str_repeat("─", 30) . "\n";
            $msg .= "🏢 Cabang    : {$row['branch_name']}\n";
            $msg .= $this->_target_line($row);
            $msg .= "📝 Keterangan: {$row['description']}\n";
            $msg .= "👷 Dikerjakan: {$row['done_by_name']}{$done_label}\n";
            $msg .= "⏳ Menunggu ACC inspector: {$row['reporter_name']}\n";
            $msg .= "🕐 {$when}\n";
            $msg .= str_repeat("─", 30);
            $msg .= "\n_Pesan otomatis dari Aplikasi Temuan_";
            return $msg;
        }
        $msg  = "✅ *TEMUAN SELESAI (ACC)*\n";
        $msg .= str_repeat("─", 30) . "\n";
        $msg .= "🏢 Cabang    : {$row['branch_name']}\n";
        $msg .= "📍 Lokasi    : {$row['location_name']}\n";
        $msg .= "📝 Keterangan: {$row['description']}\n";
        $msg .= "👷 Dikerjakan: {$row['done_by_name']}{$done_label}\n";
        $msg .= "🆗 ACC oleh  : {$row['acc_by_name']}\n";
        $msg .= "🕐 {$when}\n";
        $msg .= str_repeat("─", 30);
        $msg .= "\n_Pesan otomatis dari Aplikasi Temuan_";
        return $msg;
    }
}

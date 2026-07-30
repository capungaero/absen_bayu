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

    /** Cabang yang boleh diakses: admin bebas, lainnya cabang sendiri. */
    private function _scope_branch($requested) {
        if ($this->role === 'admin') {
            return $requested !== null && $requested !== '' ? (int)$requested : null; // null = semua
        }
        return (int)$this->user['branch_id'];
    }

    private function _body() {
        $raw = $this->input->raw_input_stream;
        $j = $raw ? json_decode($raw, true) : null;
        return is_array($j) ? $j : ($this->input->post() ?: []);
    }

    /** Upload + resize foto. Return path relatif atau ['error'=>...]. */
    private function _upload_photo($field, $prefix) {
        if (empty($_FILES[$field]['name'])) {
            return ['error' => 'Foto wajib dilampirkan'];
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
        return $r;
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
            'branch_id'    => (int)$u['branch_id'],
            'branch'       => $u['branch_name'],
            'position'     => $u['position_name'],
        ]]);
    }

    // GET temuan/branches
    public function branches() {
        if (!$this->_auth()) return;
        if ($this->role === 'admin') {
            $rows = $this->db->select('id, branch_name')->order_by('branch_name')->get('branch')->result_array();
        } else {
            $rows = $this->db->select('id, branch_name')->where('id', $this->user['branch_id'])->get('branch')->result_array();
        }
        $this->_json(['status' => true, 'rows' => $rows]);
    }

    // GET temuan/employees?branch_id= — kandidat PJ (admin only)
    public function employees() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $branch_id = $this->_scope_branch($this->input->get('branch_id'));
        $this->db->select("users.id, TRIM(CONCAT(users.first_name,' ',COALESCE(users.last_name,''))) AS name, position.position_name")
                 ->join('position', 'position.id = users.position_id', 'left')
                 ->where('users.active', 1);
        if ($branch_id !== null) { $this->db->where('position.branch_id', $branch_id); }
        $rows = $this->db->order_by('name')->get('users')->result_array();
        $this->_json(['status' => true, 'rows' => $rows]);
    }

    // ====================================================================
    // INSPECTOR (master)
    // ====================================================================

    // GET temuan/inspectors
    public function inspectors() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $this->_json(['status' => true, 'rows' => $this->temuan->get_inspectors()]);
    }

    // POST temuan/inspector_add {user_id}
    public function inspector_add() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $p = $this->_body();
        $user_id = (int)($p['user_id'] ?? 0);
        $u = $this->db->select('users.id')->where('users.id', $user_id)->where('users.active', 1)->get('users')->row_array();
        if (!$u) { $this->_json(['status' => false, 'message' => 'Karyawan tidak ditemukan'], 404); return; }
        if (!$this->temuan->add_inspector($user_id)) {
            $this->_json(['status' => false, 'message' => 'Karyawan sudah terdaftar sebagai inspector'], 422); return;
        }
        $this->_json(['status' => true]);
    }

    // POST temuan/inspector_delete {id}
    public function inspector_delete() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin()) { $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return; }
        $p = $this->_body();
        if (!$this->temuan->remove_inspector((int)($p['id'] ?? 0))) {
            $this->_json(['status' => false, 'message' => 'Inspector tidak ditemukan'], 404); return;
        }
        $this->_json(['status' => true]);
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

    // POST temuan/location_save {id?, branch_id, name, pj_user_id, is_active}
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
            'branch_id'  => $branch_id,
            'name'       => $name,
            'pj_user_id' => !empty($p['pj_user_id']) ? (int)$p['pj_user_id'] : null,
            'is_active'  => isset($p['is_active']) ? (int)!!$p['is_active'] : 1,
        ];

        $id = !empty($p['id']) ? (int)$p['id'] : null;
        if ($id) {
            $existing = $this->temuan->get_location($id);
            if (!$existing) { $this->_json(['status' => false, 'message' => 'Lokasi tidak ditemukan'], 404); return; }
            if ($this->role !== 'admin' && (int)$existing['branch_id'] !== (int)$this->user['branch_id']) {
                $this->_json(['status' => false, 'message' => 'Forbidden'], 403); return;
            }
        }
        $saved_id = $this->temuan->save_location($data, $id);
        $this->_json(['status' => true, 'id' => (int)$saved_id]);
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
    // TEMUAN
    // ====================================================================

    // GET temuan/list?status=&branch_id=&location_id=&from=&to=&page=
    public function list() {
        if (!$this->_auth()) return;
        $branch_id = $this->_scope_branch($this->input->get('branch_id'));
        $filters = [
            'branch_id'   => $branch_id,
            'status'      => $this->input->get('status'),
            'location_id' => $this->input->get('location_id'),
            'from'        => $this->input->get('from'),
            'to'          => $this->input->get('to'),
        ];
        $page  = max(1, (int)($this->input->get('page') ?: 1));
        $limit = 50;
        $rows  = $this->temuan->list_temuan($filters, $limit, ($page - 1) * $limit);
        $this->_json([
            'status'  => true,
            'rows'    => array_map([$this, '_row_out'], $rows),
            'total'   => $this->temuan->count_temuan($filters),
            'page'    => $page,
            'per_page'=> $limit,
            'summary' => $this->temuan->status_summary($branch_id),
            'me'      => ['id' => (int)$this->user['id'], 'is_admin' => $this->_is_admin()],
        ]);
    }

    // POST temuan/create (multipart: location_id, description, photo)
    // Hanya inspector terdaftar atau admin yang boleh memposting temuan.
    public function create() {
        if (!$this->_auth()) return;
        if (!$this->_is_admin() && !$this->temuan->is_inspector($this->user['id'])) {
            $this->_json(['status' => false, 'message' => 'Hanya inspector yang boleh memposting temuan'], 403); return;
        }
        $location_id = (int)$this->input->post('location_id');
        $description = trim((string)$this->input->post('description'));

        $loc = $this->temuan->get_location($location_id);
        if (!$loc || !(int)$loc['is_active']) {
            $this->_json(['status' => false, 'message' => 'Lokasi tidak valid'], 422); return;
        }
        if ($this->role !== 'admin' && (int)$loc['branch_id'] !== (int)$this->user['branch_id']) {
            $this->_json(['status' => false, 'message' => 'Lokasi bukan di cabang Anda'], 403); return;
        }
        if (strlen($description) < 5) {
            $this->_json(['status' => false, 'message' => 'Keterangan minimal 5 karakter'], 422); return;
        }

        $up = $this->_upload_photo('photo', 'temuan');
        if (isset($up['error'])) { $this->_json(['status' => false, 'message' => $up['error']], 422); return; }

        $id = $this->temuan->create_temuan([
            'branch_id'   => (int)$loc['branch_id'],
            'location_id' => $location_id,
            'reporter_id' => (int)$this->user['id'],
            'description' => $description,
            'photo_path'  => $up['path'],
            'status'      => 'baru',
        ]);

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
            $this->_json(['status' => false, 'message' => 'Hanya PJ lokasi atau admin yang boleh merespon'], 403); return;
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

    // POST temuan/done (multipart: id, photo) — PJ lokasi / admin
    public function done() {
        if (!$this->_auth()) return;
        $row = $this->temuan->get_temuan((int)$this->input->post('id'));
        if (!$row) { $this->_json(['status' => false, 'message' => 'Temuan tidak ditemukan'], 404); return; }
        if (!$this->_can_respond($row)) {
            $this->_json(['status' => false, 'message' => 'Hanya PJ lokasi atau admin yang boleh merespon'], 403); return;
        }
        if ($row['status'] === 'selesai') {
            $this->_json(['status' => false, 'message' => 'Temuan sudah selesai'], 422); return;
        }

        $up = $this->_upload_photo('photo', 'selesai');
        if (isset($up['error'])) { $this->_json(['status' => false, 'message' => $up['error']], 422); return; }

        $this->temuan->update_temuan($row['id'], [
            'status'          => 'selesai',
            'done_by'         => (int)$this->user['id'],
            'done_as'         => $this->_actor_label($row),
            'done_at'         => date('Y-m-d H:i:s'),
            'done_photo_path' => $up['path'],
        ]);

        $fresh = $this->temuan->get_temuan($row['id']);
        $this->_notify_wa('temuan_selesai', $fresh);
        $this->_json(['status' => true, 'row' => $this->_row_out($fresh)]);
    }

    private function _can_respond($row) {
        if ($this->_is_admin()) {
            return $this->role === 'admin' || (int)$row['branch_id'] === (int)$this->user['branch_id'];
        }
        // SPV: boleh merespon semua temuan di cabangnya, setara PJ area
        if ($this->role === 'supervisor') {
            return (int)$row['branch_id'] === (int)$this->user['branch_id'];
        }
        return (int)$row['pj_user_id'] === (int)$this->user['id'];
    }

    /** Label pelaku respon: PJ (PJ area lokasi tsb), SPV, atau Admin. */
    private function _actor_label($row) {
        if ((int)$row['pj_user_id'] === (int)$this->user['id']) { return 'PJ'; }
        if ($this->role === 'supervisor') { return 'SPV'; }
        return 'Admin';
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

    // ====================================================================
    // NOTIFIKASI WA (kirimi.id — reuse kredensial WA Agent)
    // ====================================================================

    private function _notify_wa($type, $row) {
        $cfg = $this->temuan->get_config();
        if (!$cfg) return;
        if ($type === 'temuan_baru' && empty($cfg['notify_enabled'])) return;
        if ($type === 'temuan_selesai' && empty($cfg['notify_done_enabled'])) return;

        $phones = array_filter(array_map('trim', explode(',', (string)$cfg['target_phones'])));
        if (empty($phones)) return;

        $this->load->model('wa_model', 'wa');
        $wa_cfg = $this->wa->get_config();
        if (empty($wa_cfg) || empty($wa_cfg['user_code']) || empty($wa_cfg['is_active'])) return;

        $this->load->library('kirimi_wa');
        $wa = new Kirimi_wa([
            'user_code' => $wa_cfg['user_code'],
            'secret'    => $wa_cfg['secret'],
            'device_id' => $wa_cfg['device_id'],
        ]);

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

    private function _build_message($type, $row) {
        $when = date('d/m/Y H:i');
        if ($type === 'temuan_baru') {
            $msg  = "🚨 *TEMUAN BARU*\n";
            $msg .= str_repeat("─", 30) . "\n";
            $msg .= "🏢 Cabang    : {$row['branch_name']}\n";
            $msg .= "📍 Lokasi    : {$row['location_name']}\n";
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
        $msg  = "✅ *TEMUAN SELESAI*\n";
        $msg .= str_repeat("─", 30) . "\n";
        $msg .= "🏢 Cabang    : {$row['branch_name']}\n";
        $msg .= "📍 Lokasi    : {$row['location_name']}\n";
        $msg .= "📝 Keterangan: {$row['description']}\n";
        $done_label = !empty($row['done_as']) ? " ({$row['done_as']})" : '';
        $msg .= "👷 Dikerjakan: {$row['done_by_name']}{$done_label}\n";
        $msg .= "🕐 {$when}\n";
        $msg .= str_repeat("─", 30);
        $msg .= "\n_Pesan otomatis dari Aplikasi Temuan_";
        return $msg;
    }
}

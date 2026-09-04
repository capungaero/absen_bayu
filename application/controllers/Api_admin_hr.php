<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * API JSON admin/sistem eksternal untuk izin, lembur & gaji (stateless, API-key bearer).
 * Endpoint: GET/POST api/admin/{leave,overtime,payroll}/*.
 * CSRF dikecualikan di config ('api/(.*)'). Auth: header Authorization: Bearer <ADMIN_API_KEY>.
 */
class Api_admin_hr extends CI_Controller {

    public function __construct(){
        parent::__construct();
        $this->load->library('Api_admin_key', null, 'adminkey');
        $this->load->model('leave_model', 'leave');
        $this->load->model('overtime_model', 'overtime');
        $this->output->set_header('Access-Control-Allow-Origin: *');
        $this->output->set_header('Access-Control-Allow-Headers: Authorization, Content-Type');
        $this->output->set_header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        if(strtolower($this->input->method()) === 'options'){ $this->_json(['status'=>true]); exit; }
        $this->db->query("SET @audit_user_id = NULL");
    }

    private function _json($data, $code = 200){
        $this->output->set_status_header($code)
                     ->set_content_type('application/json')
                     ->set_output(json_encode($data));
    }

    private function _bearer(){
        $h = $this->input->get_request_header('Authorization');
        if($h && preg_match('/Bearer\s+(.+)/i', $h, $m)){ return trim($m[1]); }
        return $this->input->get('token') ?: null;
    }

    private function _auth(){
        if(!$this->adminkey->verify($this->_bearer())){
            $this->_json(['status'=>false,'message'=>'API key tidak valid'], 401); return false;
        }
        return true;
    }

    private function _body(){
        $raw = $this->input->raw_input_stream;
        $j = $raw ? json_decode($raw, true) : null;
        return is_array($j) ? $j : ($this->input->post() ?: []);
    }

    // GET api/admin/leave?user_id=&status=&from=&to=&limit=
    public function leave_list(){
        if(!$this->_auth()) return;
        $find = [];
        if($this->input->get('user_id')){ $find['leave.user_id'] = (int)$this->input->get('user_id'); }
        if($this->input->get('status')){ $find['leave_status'] = $this->input->get('status'); }
        if($this->input->get('from')){ $find['leave_start >='] = date('Y-m-d', strtotime($this->input->get('from'))); }
        if($this->input->get('to')){ $find['leave_end <='] = date('Y-m-d', strtotime($this->input->get('to'))); }
        $limit = (int)($this->input->get('limit') ?: 100);

        $rows = $this->leave->get_detail($find, '', $limit)->result_array();
        $this->_json(['status'=>true, 'count'=>count($rows), 'leaves'=>$rows]);
    }

    // POST api/admin/leave/approve {leave_id, potongan_mode: manual|default|request, acc_potongan?}
    public function leave_approve(){
        if(!$this->_auth()) return;
        $p = $this->_body();
        if(empty($p['leave_id'])){ $this->_json(['status'=>false,'message'=>'leave_id wajib diisi'], 400); return; }

        $tr = $this->leave->get_detail(['leave.id'=>$p['leave_id'], 'leave_status'=>'pending']);
        if($tr->num_rows() === 0){ $this->_json(['status'=>false,'message'=>'Pengajuan izin tidak ditemukan / bukan status pending'], 404); return; }
        $leave = $tr->row_array();

        $mode = isset($p['potongan_mode']) ? $p['potongan_mode'] : 'default';
        if($mode === 'manual' && isset($p['acc_potongan'])){
            $potongan = $p['acc_potongan'];
        }else if($mode === 'request'){
            $potongan = $leave['request_potongan'];
        }else{
            $potongan = $leave['default_potongan'];
        }

        $range = get_daterange_list($leave['leave_start'], $leave['leave_end']);
        $locked = payroll_locked_dates(payroll_user_branch($leave['user_id']), $range);
        if(!empty($locked)){
            $this->_json(['status'=>false,'message'=>'Tidak bisa menyetujui izin: periode terkunci ('.implode(', ', $locked).'). Rollback penggajian periode tersebut dulu.']); return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->trans_begin();
        $this->leave->update([
            'leave_status'  => 'approve',
            'acc_potongan'  => $potongan,
            'confirm_at'    => $now,
            'reject_reason' => '',
        ], $p['leave_id']);

        $this->db->where('user_id', $leave['user_id'])->where_in('flow_date', $range)->delete('presence');
        $presence = [];
        foreach($range as $d){
            $presence[] = [
                'user_id'           => $leave['user_id'],
                'flow_date'         => $d,
                'created_at'        => $now,
                'input_by'          => 'system',
                'input_by_user_id'  => null,
                'presence_get_paid' => 100 - $potongan,
                'presence_type'     => $leave['leave_type'],
                'presence_status'   => 'approved',
                'is_overtime'       => '0',
            ];
        }
        $this->db->insert_batch('presence', $presence);

        if($this->db->trans_status()){
            $this->db->trans_commit();
            $this->_json(['status'=>true,'message'=>'Izin disetujui']);
        }else{
            $this->db->trans_rollback();
            $this->_json(['status'=>false,'message'=>'Terjadi kesalahan, coba lagi nanti']);
        }
    }

    // POST api/admin/leave/create {user_id, leave_type: izin|sakit|cuti, leave_start, leave_end,
    //   leave_reason, leave_proof?(base64/file khusus sakit wajib)} -- ATAS NAMA karyawan tertentu.
    // Validasi & efek SAMA PERSIS dgn M.php::submit_leave (karyawan ajukan sendiri): status
    // selalu 'pending', tetap perlu di-ACC lewat leave_approve/deny -- endpoint ini TIDAK
    // auto-approve, cuma mengganti siapa yang mengajukan (mis. via bot AI atas instruksi HR).
    public function leave_create(){
        if(!$this->_auth()) return;
        $p = $this->_body();

        $user_id = (int)($p['user_id'] ?? 0);
        $emp = $this->db->where('id', $user_id)->where('active', 1)->get('users')->row_array();
        if(!$emp){ $this->_json(['status'=>false,'message'=>'user_id karyawan tidak valid/tidak aktif'], 422); return; }

        $type = $p['leave_type'] ?? '';
        if(!in_array($type, ['izin','sakit','cuti'], true)){
            $this->_json(['status'=>false,'message'=>'leave_type harus izin/sakit/cuti'], 422); return;
        }
        $start = !empty($p['leave_start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $p['leave_start']) ? $p['leave_start'] : null;
        $end   = !empty($p['leave_end'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $p['leave_end'])   ? $p['leave_end']   : null;
        $reason = trim((string)($p['leave_reason'] ?? ''));
        if(!$start || !$end || strlen($reason) < 3){
            $this->_json(['status'=>false,'message'=>'leave_start, leave_end (YYYY-MM-DD) & leave_reason (min 3 karakter) wajib diisi'], 422); return;
        }
        if(strtotime($start) > strtotime($end)){
            $this->_json(['status'=>false,'message'=>'leave_start harus sebelum/sama dengan leave_end'], 422); return;
        }

        $listDay = get_daterange_list($start, $end);
        $checkPresence = $this->db->where('flow_date >=', $start)->where('flow_date <=', $end)
            ->where('user_id', $user_id)->count_all_results('presence');
        $checkOff = $this->db->where('user_id', $user_id)
            ->where('additional_date >=', $start)->where('additional_date <=', $end)
            ->where('additional_type', 'work')->group_by('additional_date')
            ->get('users_shift_additional')->num_rows();
        if(!($checkPresence == 0 && $checkOff == count($listDay))){
            $this->_json(['status'=>false,'message'=>'Rentang tanggal sudah memiliki presensi atau bukan hari jadwal kerja karyawan ini.'], 422); return;
        }

        $overlap = $this->db->group_start()
                ->group_start()->where('leave_start >=', $start)->where('leave_start <=', $end)->group_end()
                ->or_group_start()->where('leave_end >=', $start)->where('leave_end <=', $end)->group_end()
            ->group_end()
            ->where('user_id', $user_id)->where('leave_status', 'pending')
            ->count_all_results('leave');
        if($overlap > 0){
            $this->_json(['status'=>false,'message'=>'Rentang tanggal sedang dalam proses pengajuan lain. Pilih tanggal lain.'], 422); return;
        }

        // Bukti: file upload (multipart, field leave_proof) ATAU base64 (JSON field leave_proof_base64).
        $proof = '';
        if(!empty($_FILES['leave_proof']['name'])){
            $config['upload_path']   = './assets/images/hr/leave/';
            $config['allowed_types'] = 'png|jpeg|jpg';
            $config['file_name']     = 'leave_'.$user_id.'_'.generateRandom(5).'_'.time();
            $config['max_size']      = 10240;
            $config['max_width']     = 6000;
            $config['max_height']    = 6000;
            $this->load->library('upload', $config);
            if(!$this->upload->do_upload('leave_proof')){
                $this->_json(['status'=>false,'message'=>strip_tags($this->upload->display_errors())], 422); return;
            }
            $upl = $this->upload->data();
            $cfg = ['image_library'=>'gd2','source_image'=>$upl['full_path'],'quality'=>'80%','maintain_ratio'=>TRUE,'width'=>800];
            $this->load->library('image_lib', $cfg);
            $this->image_lib->resize();
            $proof = $upl['file_name'];
        }elseif(!empty($p['leave_proof_base64'])){
            $raw = base64_decode(preg_replace('#^data:image/\w+;base64,#', '', $p['leave_proof_base64']), true);
            if($raw === false){
                $this->_json(['status'=>false,'message'=>'leave_proof_base64 tidak valid'], 422); return;
            }
            $fname = 'leave_'.$user_id.'_'.generateRandom(5).'_'.time().'.jpg';
            $fpath = './assets/images/hr/leave/'.$fname;
            file_put_contents($fpath, $raw);
            $cfg = ['image_library'=>'gd2','source_image'=>$fpath,'quality'=>'80%','maintain_ratio'=>TRUE,'width'=>800];
            $this->load->library('image_lib', $cfg);
            $this->image_lib->resize();
            $proof = $fname;
        }elseif($type === 'sakit'){
            $this->_json(['status'=>false,'message'=>'Surat keterangan sakit (foto) wajib diunggah (leave_proof atau leave_proof_base64).'], 422); return;
        }

        $totalDay = 0;
        foreach($listDay as $d){ $totalDay += in_array(get_dayname($d), ['Sabtu','Minggu']) ? 2 : 1; }

        $ok = $this->leave->insert([
            'user_id'              => $user_id,
            'leave_start'          => $start,
            'leave_end'            => $end,
            'leave_range'          => diffInDays($start, $end) + 1,
            'leave_proof'          => $proof,
            'leave_type'           => $type,
            'leave_reason'         => $reason,
            'default_potongan'     => GetPotonganIzin($emp['status_work']),
            'request_potongan'     => null,
            'jumlah_hari_potongan' => $totalDay,
            'leave_status'         => 'pending',
            'created_at'           => date('Y-m-d H:i:s'),
        ]);
        $this->_json(['status'=>(bool)$ok, 'message'=>$ok ? 'Pengajuan izin dibuat, menunggu ACC atasan.' : 'Gagal menyimpan pengajuan.']);
    }

    // POST api/admin/leave/deny {leave_id, reject_reason}
    public function leave_deny(){
        if(!$this->_auth()) return;
        $p = $this->_body();
        if(empty($p['leave_id'])){ $this->_json(['status'=>false,'message'=>'leave_id wajib diisi'], 400); return; }

        $tr = $this->leave->get_detail(['leave.id'=>$p['leave_id'], 'leave_status'=>'pending']);
        if($tr->num_rows() === 0){ $this->_json(['status'=>false,'message'=>'Pengajuan izin tidak ditemukan / bukan status pending'], 404); return; }

        $this->leave->update([
            'leave_status'  => 'deny',
            'confirm_at'    => date('Y-m-d H:i:s'),
            'reject_reason' => isset($p['reject_reason']) ? $p['reject_reason'] : '',
        ], $p['leave_id']);
        $this->_json(['status'=>true,'message'=>'Izin ditolak']);
    }

    // GET api/admin/overtime?user_id=&status=&from=&to=&limit=
    public function overtime_list(){
        if(!$this->_auth()) return;
        $find = [];
        if($this->input->get('user_id')){ $find['overtime.user_id'] = (int)$this->input->get('user_id'); }
        if($this->input->get('status')){ $find['overtime_status'] = $this->input->get('status'); }
        if($this->input->get('from')){ $find['overtime_date >='] = date('Y-m-d', strtotime($this->input->get('from'))); }
        if($this->input->get('to')){ $find['overtime_date <='] = date('Y-m-d', strtotime($this->input->get('to'))); }
        $limit = (int)($this->input->get('limit') ?: 100);

        $rows = $this->overtime->get_detail($find, '', $limit)->result_array();
        $this->_json(['status'=>true, 'count'=>count($rows), 'overtimes'=>$rows]);
    }

    // POST api/admin/overtime/approve {overtime_id}
    public function overtime_approve(){
        if(!$this->_auth()) return;
        $p = $this->_body();
        if(empty($p['overtime_id'])){ $this->_json(['status'=>false,'message'=>'overtime_id wajib diisi'], 400); return; }

        $tr = $this->overtime->get_detail(['overtime.id'=>$p['overtime_id'], 'overtime_status'=>'pending']);
        if($tr->num_rows() === 0){ $this->_json(['status'=>false,'message'=>'Pengajuan lembur tidak ditemukan / bukan status pending'], 404); return; }

        $this->overtime->update([
            'overtime_status' => 'approve',
            'confirm_at'      => date('Y-m-d H:i:s'),
            'reject_reason'   => '',
        ], $p['overtime_id']);
        $this->_json(['status'=>true,'message'=>'Lembur disetujui']);
    }

    // POST api/admin/overtime/create {user_id, overtime_date, overtime_hour,
    //   overtime_proof?(base64/file, WAJIB)} -- ATAS NAMA karyawan tertentu.
    // Sama pola dgn leave_create(): status selalu 'pending', tetap perlu ACC.
    public function overtime_create(){
        if(!$this->_auth()) return;
        $p = $this->_body();

        $user_id = (int)($p['user_id'] ?? 0);
        $emp = $this->db->where('id', $user_id)->where('active', 1)->get('users')->row_array();
        if(!$emp){ $this->_json(['status'=>false,'message'=>'user_id karyawan tidak valid/tidak aktif'], 422); return; }

        if(empty($p['overtime_date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $p['overtime_date'])){
            $this->_json(['status'=>false,'message'=>'overtime_date (YYYY-MM-DD) wajib diisi'], 422); return;
        }
        $hour = isset($p['overtime_hour']) ? (float)$p['overtime_hour'] : 0;
        if($hour <= 0){
            $this->_json(['status'=>false,'message'=>'overtime_hour wajib diisi & lebih dari 0'], 422); return;
        }
        $date = date('Y-m-d', strtotime($p['overtime_date']));

        $dup = $this->db->where('user_id', $user_id)->where('overtime_date', $date)
            ->where_in('overtime_status', ['approve','pending'])
            ->where('deleted_at IS NULL', null, false)
            ->count_all_results('overtime');
        if($dup > 0){
            $nama = trim($emp['first_name'].' '.($emp['last_name'] ?? ''));
            $this->_json(['status'=>false,'message'=>"Pengajuan lembur a.n. $nama untuk tanggal $date sudah ada."], 422); return;
        }

        $proof = null;
        if(!empty($_FILES['overtime_proof']['name'])){
            $config['upload_path']   = './assets/images/hr/overtime/';
            $config['allowed_types'] = 'png|jpeg|jpg';
            $config['file_name']     = 'overtime_'.$user_id.'_'.generateRandom(5).'_'.time();
            $config['max_size']      = 10240;
            $config['max_width']     = 10000;
            $config['max_height']    = 10000;
            $this->load->library('upload', $config);
            if(!$this->upload->do_upload('overtime_proof')){
                $this->_json(['status'=>false,'message'=>strip_tags($this->upload->display_errors())], 422); return;
            }
            $upl = $this->upload->data();
            $cfg = ['image_library'=>'gd2','source_image'=>$upl['full_path'],'quality'=>'80%','maintain_ratio'=>TRUE,'width'=>800];
            $this->load->library('image_lib', $cfg);
            $this->image_lib->resize();
            $proof = $upl['file_name'];
        }elseif(!empty($p['overtime_proof_base64'])){
            $raw = base64_decode(preg_replace('#^data:image/\w+;base64,#', '', $p['overtime_proof_base64']), true);
            if($raw === false){
                $this->_json(['status'=>false,'message'=>'overtime_proof_base64 tidak valid'], 422); return;
            }
            $fname = 'overtime_'.$user_id.'_'.generateRandom(5).'_'.time().'.jpg';
            $fpath = './assets/images/hr/overtime/'.$fname;
            file_put_contents($fpath, $raw);
            $cfg = ['image_library'=>'gd2','source_image'=>$fpath,'quality'=>'80%','maintain_ratio'=>TRUE,'width'=>800];
            $this->load->library('image_lib', $cfg);
            $this->image_lib->resize();
            $proof = $fname;
        }
        if(!$proof){
            $this->_json(['status'=>false,'message'=>'Foto bukti lembur wajib diunggah (overtime_proof atau overtime_proof_base64).'], 422); return;
        }

        $ok = $this->overtime->insert([
            'user_id'         => $user_id,
            'overtime_hour'   => $hour,
            'overtime_date'   => $date,
            'overtime_proof'  => $proof,
            'overtime_status' => 'pending',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
        $this->_json(['status'=>(bool)$ok, 'message'=>$ok ? 'Pengajuan lembur dibuat, menunggu ACC atasan.' : 'Gagal menyimpan pengajuan.']);
    }

    // POST api/admin/overtime/deny {overtime_id, reject_reason}
    public function overtime_deny(){
        if(!$this->_auth()) return;
        $p = $this->_body();
        if(empty($p['overtime_id'])){ $this->_json(['status'=>false,'message'=>'overtime_id wajib diisi'], 400); return; }

        $tr = $this->overtime->get_detail(['overtime.id'=>$p['overtime_id'], 'overtime_status'=>'pending']);
        if($tr->num_rows() === 0){ $this->_json(['status'=>false,'message'=>'Pengajuan lembur tidak ditemukan / bukan status pending'], 404); return; }

        $this->overtime->update([
            'overtime_status' => 'deny',
            'confirm_at'      => date('Y-m-d H:i:s'),
            'reject_reason'   => isset($p['reject_reason']) ? $p['reject_reason'] : '',
        ], $p['overtime_id']);
        $this->_json(['status'=>true,'message'=>'Lembur ditolak']);
    }

    // GET api/admin/payroll?user_id=&branch_id=&year=&month=
    public function payroll_list(){
        if(!$this->_auth()) return;
        $this->db->select('pd.*, p.month, p.year, p.branch_id, p.is_final')
            ->from('payroll_detail pd')->join('payroll p', 'p.id = pd.payroll_id');
        if($this->input->get('user_id')){ $this->db->where('pd.user_id', (int)$this->input->get('user_id')); }
        if($this->input->get('branch_id')){ $this->db->where('p.branch_id', (int)$this->input->get('branch_id')); }
        if($this->input->get('year')){ $this->db->where('p.year', (int)$this->input->get('year')); }
        if($this->input->get('month')){ $this->db->where('p.month', (int)$this->input->get('month')); }
        $rows = $this->db->order_by('p.year', 'DESC')->order_by('p.month', 'DESC')->get()->result_array();
        $this->_json(['status'=>true, 'count'=>count($rows), 'payroll'=>$rows]);
    }
}

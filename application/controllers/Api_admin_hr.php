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

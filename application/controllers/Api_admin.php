<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * API JSON admin/sistem eksternal untuk presensi (stateless, API-key bearer).
 * Endpoint: GET api/admin/presence, POST api/admin/presence/update_workhour,
 * POST api/admin/presence/update_shift, POST api/admin/presence/cancel.
 * CSRF dikecualikan di config ('api/(.*)'). Auth: header Authorization: Bearer <ADMIN_API_KEY>.
 */
class Api_admin extends CI_Controller {

    public function __construct(){
        parent::__construct();
        $this->load->library('Api_admin_key', null, 'adminkey');
        $this->load->model('presence_model', 'presence');
        $this->load->model('shift_model', 'shift');
        $this->load->model('user_model', 'employee');
        $this->output->set_header('Access-Control-Allow-Origin: *');
        $this->output->set_header('Access-Control-Allow-Headers: Authorization, Content-Type');
        $this->output->set_header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        if(strtolower($this->input->method()) === 'options'){ $this->_json(['status'=>true]); exit; }
        // NULL = system/api (bukan user session) untuk trigger audit_log.
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

    private function _lock_message($month, $year){
        return 'Periode penggajian '.str_pad($month, 2, '0', STR_PAD_LEFT).'/'.$year.' sudah dikunci (payroll telah dibuat). '
             . 'Rollback penggajian periode tersebut dulu untuk mengubah absen/jadwal.';
    }

    // GET api/admin/presence?user_id=&branch_id=&from=&to=&limit=
    public function presence_list(){
        if(!$this->_auth()) return;
        $from = $this->input->get('from'); $to = $this->input->get('to');
        if(!$from || !$to){ $this->_json(['status'=>false,'message'=>'Parameter from & to (Y-m-d) wajib diisi'], 400); return; }
        $limit = (int)($this->input->get('limit') ?: 500);

        $this->db->select('presence.*, users.employee_code, users.first_name, position.branch_id, branch.branch_name')
            ->join('users', 'users.id = presence.user_id')
            ->join('position', 'position.id = users.position_id')
            ->join('branch', 'branch.id = position.branch_id')
            ->where('flow_date >=', date('Y-m-d', strtotime($from)))
            ->where('flow_date <=', date('Y-m-d', strtotime($to)))
            ->order_by('flow_date', 'DESC')
            ->limit($limit);
        if($this->input->get('user_id')){ $this->db->where('presence.user_id', (int)$this->input->get('user_id')); }
        if($this->input->get('branch_id')){ $this->db->where('position.branch_id', (int)$this->input->get('branch_id')); }

        $rows = $this->db->get('presence')->result_array();
        $this->_json(['status'=>true, 'count'=>count($rows), 'presence'=>$rows]);
    }

    // POST api/admin/presence/update_workhour {user_id,date,entry_time,out_time,rest_time_in?,rest_time_out?,is_early_leave?,overtime?}
    public function update_workhour(){
        if(!$this->_auth()) return;
        $p = $this->_body();
        if(empty($p['user_id']) || empty($p['date'])){
            $this->_json(['status'=>false,'message'=>'user_id & date wajib diisi'], 400); return;
        }

        $emp = $this->employee->get_detail('users.id', $p['user_id'])->row_array();
        if(empty($emp)){ $this->_json(['status'=>false,'message'=>'Karyawan tidak ditemukan'], 404); return; }

        $date = date('Y-m-d', strtotime($p['date']));
        if(payroll_locked_for_user_date($p['user_id'], $date)){
            $pp = payroll_period_of_date($date);
            $this->_json(['status'=>false,'message'=>$this->_lock_message($pp['month'], $pp['year'])]); return;
        }

        $time = [
            'entry'   => isset($p['entry_time']) ? $p['entry_time'] : '',
            'out'     => isset($p['out_time']) ? $p['out_time'] : '',
            'rest_in' => isset($p['rest_time_in']) ? $p['rest_time_in'] : '',
            'rest_out'=> isset($p['rest_time_out']) ? $p['rest_time_out'] : '',
        ];
        $is_early_leave = !empty($p['is_early_leave']) && $p['is_early_leave'] != '0';

        $check_time = $this->presence->check_available_attendance($p['user_id'], $date, $time, true, $is_early_leave);
        if($check_time === false){
            $this->_json(['status'=>false,'message'=>'Jadwal absen masuk/keluar tidak sesuai dengan jadwal shift, atau karyawan belum punya jadwal shift tanggal ini']); return;
        }

        $entry    = $time['entry'] !== '' ? $date." ".$time['entry'].":00" : null;
        $out      = $time['out'] !== '' ? $date." ".$time['out'].":00" : null;
        $rest_in  = $time['rest_in'] !== '' ? $date." ".$time['rest_in'].":00" : null;
        $rest_out = $time['rest_out'] !== '' ? $date." ".$time['rest_out'].":00" : null;

        $adt = $this->db->where(['user_id'=>$p['user_id'], 'additional_date'=>$date, 'additional_type'=>'work'])
            ->join('shift', 'shift.id = users_shift_additional.shift_id')
            ->get('users_shift_additional')->row_array();

        $entry_time_late = ($entry != null) ? late_minutes($adt['start_time_late'], $entry) : 0;

        $rest_time_late = 0;
        if($rest_out != null && !empty($adt)){
            $rto = $time['rest_out'].":00";
            $rest_limit = date('H:i:s', strtotime($rest_in." +".$adt['rest_time_range']." minutes"));
            if($rto <= $adt['end_time_rest']){
                $rest_time_late = late_minutes($rest_limit, $rto);
            }
        }

        $early_leave_short_minutes = 0;
        $presence_status = 'approved';
        if($is_early_leave && !empty($adt)){
            $early_leave_short_minutes = presence_early_leave_short_minutes($adt, $entry, $out, $rest_in, $rest_out);
            $net_minutes = presence_net_work_minutes($entry, $out, $rest_in, $rest_out);
            if($net_minutes < 300){ $presence_status = 'deny'; }
        }

        $now = date('Y-m-d H:i:s');
        $presence = [
            'user_id'          => $p['user_id'],
            'entry_time'       => $entry,
            'entry_time_late'  => $entry_time_late,
            'out_time'         => $out,
            'rest_time_in'     => $rest_in,
            'rest_time_out'    => $rest_out,
            'rest_time_late'   => $rest_time_late,
            'created_at'       => $now,
            'updated_at'       => $now,
            'input_by'         => 'system',
            'input_by_user_id' => null,
            'is_overtime'      => !empty($p['overtime']) ? $p['overtime'] : '0',
            'flow_date'        => $date,
            'is_early_leave'   => $is_early_leave ? 1 : 0,
            'early_leave_short_minutes' => $early_leave_short_minutes,
            'presence_status'  => $presence_status,
        ];

        $check = $this->presence->get_detail(['user_id'=>$p['user_id'], 'flow_date'=>$date])->row_array();
        if(!empty($check)){
            $this->presence->update($presence, $check['id']);
        }else{
            $this->presence->insert($presence);
        }

        $this->_json(['status'=>true, 'message'=>'Presensi berhasil diubah', 'presence_status'=>$presence_status]);
    }

    // POST api/admin/presence/update_shift {user_id,date,shift_id|'free',confirm_recalc?}
    public function update_shift(){
        if(!$this->_auth()) return;
        $p = $this->_body();
        if(empty($p['user_id']) || empty($p['date']) || !isset($p['shift_id'])){
            $this->_json(['status'=>false,'message'=>'user_id, date & shift_id wajib diisi'], 400); return;
        }

        $emp = $this->employee->get_detail('users.id', $p['user_id'])->row_array();
        if(empty($emp)){ $this->_json(['status'=>false,'message'=>'Karyawan tidak ditemukan'], 404); return; }

        $date = date('Y-m-d', strtotime($p['date']));
        if(payroll_locked_for_user_date($p['user_id'], $date)){
            $pp = payroll_period_of_date($date);
            $this->_json(['status'=>false,'message'=>$this->_lock_message($pp['month'], $pp['year'])]); return;
        }

        $existing_presence = $this->db->where(['user_id'=>$p['user_id'], 'flow_date'=>$date])
            ->where('presence_type', 'normal')->get('presence')->row_array();
        $has_attendance = !empty($existing_presence) && (!empty($existing_presence['entry_time']) || !empty($existing_presence['out_time']));

        if($has_attendance && empty($p['confirm_recalc'])){
            $this->_json([
                'status'=>false, 'needs_confirm'=>true,
                'message'=>'Sudah ada absen pada tanggal '.$date.'. Rekap kehadiran akan dihitung ulang sesuai shift baru. Kirim ulang dengan confirm_recalc=1 untuk lanjut.'
            ]); return;
        }

        $this->db->trans_begin();
        $this->db->where(['additional_date'=>$date, 'user_id'=>$p['user_id']])->delete('users_shift_additional');
        $this->db->insert('users_shift_additional', [
            'user_id'  => $p['user_id'],
            'shift_id' => $p['shift_id'] == 'free' ? null : $p['shift_id'],
            'additional_date' => $date,
            'additional_type' => $p['shift_id'] == 'free' ? 'free' : 'work',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if(!$this->db->trans_status()){
            $this->db->trans_rollback();
            $this->_json(['status'=>false,'message'=>'Terjadi kesalahan, coba lagi nanti']); return;
        }

        $recalc_done = false;
        if($p['shift_id'] != 'free'){
            $shift = $this->shift->get_active(['id'=>$p['shift_id'], 'branch_id'=>$emp['branch_id']])->row_array();
            if(empty($shift)){
                $this->db->trans_rollback();
                $this->_json(['status'=>false,'message'=>'Shift nonaktif tidak dapat dipilih']); return;
            }
        }

        if($has_attendance){
            $upd = ['updated_at'=>date('Y-m-d H:i:s'), 'input_by'=>'system', 'input_by_user_id'=>null];
            if($p['shift_id'] == 'free'){
                $upd['entry_time_late'] = 0; $upd['rest_time_late'] = 0;
            }else{
                $upd['entry_time_late'] = !empty($existing_presence['entry_time'])
                    ? late_minutes($shift['start_time_late'], $existing_presence['entry_time']) : 0;
                $rest_late = 0;
                if(!empty($existing_presence['rest_time_in']) && !empty($existing_presence['rest_time_out'])){
                    $rest_limit = date('H:i:s', strtotime($existing_presence['rest_time_in'].' +'.$shift['rest_time_range'].' minutes'));
                    $rest_out_t = date('H:i:s', strtotime($existing_presence['rest_time_out']));
                    if($rest_out_t <= $shift['end_time_rest']){
                        $rest_late = late_minutes($rest_limit, $existing_presence['rest_time_out']);
                    }
                }
                $upd['rest_time_late'] = $rest_late;
            }
            $this->db->where('id', $existing_presence['id'])->update('presence', $upd);
            $recalc_done = true;
        }

        $this->db->trans_commit();
        $this->_json(['status'=>true, 'recalc'=>$recalc_done, 'message'=>'Jadwal/shift berhasil diubah']);
    }

    // POST api/admin/presence/cancel {presence_id}
    public function cancel(){
        if(!$this->_auth()) return;
        $p = $this->_body();
        if(empty($p['presence_id'])){ $this->_json(['status'=>false,'message'=>'presence_id wajib diisi'], 400); return; }

        $pres = $this->presence->get_detail('presence.id', $p['presence_id'])->row_array();
        if(empty($pres)){ $this->_json(['status'=>false,'message'=>'Presensi tidak ditemukan'], 404); return; }

        if(payroll_locked_for_user_date($pres['user_id'], $pres['flow_date'])){
            $pp = payroll_period_of_date($pres['flow_date']);
            $this->_json(['status'=>false,'message'=>$this->_lock_message($pp['month'], $pp['year'])]); return;
        }

        if($this->presence->delete($p['presence_id'])){
            $this->_json(['status'=>true,'message'=>'Kehadiran berhasil dibatalkan']);
        }else{
            $this->_json(['status'=>false,'message'=>'Terjadi kesalahan, coba lagi nanti']);
        }
    }
}

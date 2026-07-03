<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Report extends CI_Controller {

    public function __construct(){
        parent::__construct();
        if(!$this->ion_auth->logged_in()){ redirect(''); }
        $this->role     = $this->ion_auth->get_users_groups()->row()->name;
        $this->userdata = $this->ion_auth->user()->row();
        if(!in_array($this->role, ['admin','admin-branch'])){ redirect('dashboard'); }
    }

    private function _json($d){ $this->output->set_content_type('application/json')->set_output(json_encode($d)); }
    private function _mustAjax(){ if(!$this->input->is_ajax_request()){ show_error('Bad Request',400); return false; } return true; }

    public function index(){
        $page     = max(1, (int)$this->input->get('page'));
        $per_page = 30;
        $offset   = ($page - 1) * $per_page;
        $filter   = $this->input->get('status') ?: '';

        $base = $this->db
            ->select('ur.*, CONCAT(u.first_name," ",COALESCE(u.last_name,"")) AS emp_name, b.branch_name,
                      CONCAT(a.first_name," ",COALESCE(a.last_name,"")) AS acc_name')
            ->from('user_reports ur')
            ->join('users u',   'u.id = ur.user_id',  'left')
            ->join('position pos', 'pos.id = u.position_id', 'left')
            ->join('branch b',  'b.id = pos.branch_id','left')
            ->join('users a',   'a.id = ur.acc_by',   'left');
        if($filter) $base->where('ur.status', $filter);

        $total   = $this->db->count_all_results('', false);
        $reports = $base->order_by('ur.created_at','DESC')->limit($per_page, $offset)->get()->result_array();
        $unread  = $this->db->where('status','new')->count_all_results('user_reports');

        $data = [
            'reports'  => $reports,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
            'filter'   => $filter,
            'unread'   => $unread,
        ];
        $this->template->load('layout/admin', 'report/index', $data);
    }

    public function mark_read(){
        if(!$this->_mustAjax()) return;
        $id = (int)$this->input->post('id');
        if(!$id){ $this->_json(['status'=>false]); return; }
        $this->db->where('id',$id)->where('status','new')
            ->update('user_reports', ['status'=>'read','read_at'=>date('Y-m-d H:i:s')]);
        $this->_json(['status'=>true]);
    }

    public function mark_all_read(){
        if(!$this->_mustAjax()) return;
        $this->db->where('status','new')
            ->update('user_reports', ['status'=>'read','read_at'=>date('Y-m-d H:i:s')]);
        $this->_json(['status'=>true]);
    }

    public function acc(){
        if(!$this->_mustAjax()) return;
        $id = (int)$this->input->post('id');
        if(!$id){ $this->_json(['status'=>false,'message'=>'ID tidak valid']); return; }
        $row = $this->db->where('id',$id)->get('user_reports')->row_array();
        if(!$row){ $this->_json(['status'=>false,'message'=>'Laporan tidak ditemukan']); return; }

        $this->db->where('id',$id)->update('user_reports', [
            'status' => 'acc',
            'acc_by' => (int)$this->userdata->id,
            'acc_at' => date('Y-m-d H:i:s'),
        ]);
        $accName = trim($this->userdata->first_name.' '.($this->userdata->last_name??''));
        $this->_json(['status'=>true,'acc_name'=>$accName,'acc_at'=>date('d M Y H:i')]);
    }

    public function edit(){
        if(!$this->_mustAjax()) return;
        $id         = (int)$this->input->post('id');
        $desc       = trim($this->input->post('description') ?: '');
        $admin_note = trim($this->input->post('admin_note')  ?: '');
        if(!$id || strlen($desc) < 5){
            $this->_json(['status'=>false,'message'=>'Deskripsi minimal 5 karakter']); return;
        }
        $row = $this->db->where('id',$id)->get('user_reports')->row_array();
        if(!$row){ $this->_json(['status'=>false,'message'=>'Laporan tidak ditemukan']); return; }

        $this->db->where('id',$id)->update('user_reports', [
            'description' => $desc,
            'admin_note'  => $admin_note ?: null,
        ]);
        $this->_json(['status'=>true,'description'=>$desc,'admin_note'=>$admin_note]);
    }

    public function delete(){
        if(!$this->_mustAjax()) return;
        $id = (int)$this->input->post('id');
        if(!$id){ $this->_json(['status'=>false,'message'=>'ID tidak valid']); return; }
        $row = $this->db->where('id',$id)->get('user_reports')->row_array();
        if(!$row){ $this->_json(['status'=>false,'message'=>'Laporan tidak ditemukan']); return; }

        if(!empty($row['file_path'])){
            $full = FCPATH.$row['file_path'];
            if(is_file($full)) @unlink($full);
        }
        $this->db->where('id',$id)->delete('user_reports');
        $this->_json(['status'=>true]);
    }
}

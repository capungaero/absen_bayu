<?php

class Double_deduction_date extends CI_Controller{

   function __construct(){
      parent::__construct();
      if(!$this->ion_auth->logged_in()){
         redirect();
      }

      $this->role = $this->ion_auth->get_users_groups()->row()->name;
      $this->userdata = $this->ion_auth->user()->row();

      $this->load->model('double_deduction_date_model', 'double_date');
      $this->load->model('branch_model', 'branch');
   }

   public function index(){
      if(in_array($this->role, ['admin', 'admin-branch'])){
         if($this->role == 'admin'){
            $branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
            $data['branch'] = $this->branch->get_data(['branch_name', 'ASC'])->result_array();
            $data['branch_detail'] = $this->branch->get_detail('id', $branch_id)->row_array();
         }else{
            $branch_id = $this->userdata->branch_id;
         }

         $data['branch_id'] = $branch_id;
         $data['list'] = $this->double_date->get_dataTable(['double_deduction_date.branch_id' => $branch_id]);
         $this->template->load('layout/admin', 'master_data/double_deduction_date/index', $data);
      }else{
         show_404();
      }
   }

   public function insert(){
      if($this->input->is_ajax_request() && in_array($this->role, ['admin', 'admin-branch'])){
         $p = $this->input->post();
         $p['branch_id'] = $this->role == 'admin' ? (isset($p['branch_id']) ? $p['branch_id'] : null) : $this->userdata->branch_id;
         $p['description'] = isset($p['description']) ? trim($p['description']) : '';
         $p['is_active'] = isset($p['is_active']) && $p['is_active'] == '0' ? '0' : '1';

         $this->form_validation->set_data($p);
         $this->form_validation->set_rules('branch_id', 'Cabang', 'required|numeric');
         $this->form_validation->set_rules('special_date', 'Tanggal khusus', 'required');
         $this->form_validation->set_rules('description', 'Keterangan', 'trim|max_length[255]');
         $this->form_validation->set_rules('is_active', 'Status', 'required|in_list[0,1]');

         if($this->form_validation->run() == TRUE){
            $date = $this->_normalize_date($p['special_date']);
            if(!$date){
               echo json_encode(['status' => false, 'message' => 'Format tanggal tidak valid']);
               return;
            }

            $p['special_date'] = $date;
            $p['created_at'] = date('Y-m-d H:i:s');
            $exists = $this->double_date->get_detail([
               'double_deduction_date.branch_id' => $p['branch_id'],
               'double_deduction_date.special_date' => $p['special_date']
            ]);

            if($exists->num_rows() == 0){
               $res = $this->double_date->insert($p)
                  ? ['status' => true, 'message' => 'Tanggal khusus berhasil ditambahkan']
                  : ['status' => false, 'message' => 'Terjadi kesalahan, coba lagi nanti'];
            }else{
               $res = ['status' => false, 'message' => 'Tanggal khusus sudah ada'];
            }
         }else{
            $res = ['status' => false, 'message' => validation_errors()];
         }

         echo json_encode($res);
      }else{
         show_404();
      }
   }

   public function update(){
      if($this->input->is_ajax_request() && in_array($this->role, ['admin', 'admin-branch'])){
         $p = $this->input->post();
         $id = isset($p['id_double_date']) ? $p['id_double_date'] : null; unset($p['id_double_date']);
         $p['branch_id'] = $this->role == 'admin' ? (isset($p['branch_id']) ? $p['branch_id'] : null) : $this->userdata->branch_id;
         $p['description'] = isset($p['description']) ? trim($p['description']) : '';
         $p['is_active'] = isset($p['is_active']) && $p['is_active'] == '0' ? '0' : '1';

         $find = ['double_deduction_date.id' => $id];
         if($this->role != 'admin'){
            $find['double_deduction_date.branch_id'] = $this->userdata->branch_id;
         }

         $detail = $this->double_date->get_detail($find);
         if($detail->num_rows() == 0){
            echo json_encode(['status' => false, 'message' => 'Tanggal khusus tidak diketahui']);
            return;
         }

         $this->form_validation->set_data($p);
         $this->form_validation->set_rules('branch_id', 'Cabang', 'required|numeric');
         $this->form_validation->set_rules('special_date', 'Tanggal khusus', 'required');
         $this->form_validation->set_rules('description', 'Keterangan', 'trim|max_length[255]');
         $this->form_validation->set_rules('is_active', 'Status', 'required|in_list[0,1]');

         if($this->form_validation->run() == TRUE){
            $date = $this->_normalize_date($p['special_date']);
            if(!$date){
               echo json_encode(['status' => false, 'message' => 'Format tanggal tidak valid']);
               return;
            }

            $p['special_date'] = $date;
            $exists = $this->double_date->get_detail([
               'double_deduction_date.branch_id' => $p['branch_id'],
               'double_deduction_date.special_date' => $p['special_date'],
               'double_deduction_date.id !=' => $id
            ]);

            if($exists->num_rows() == 0){
               $p['updated_at'] = date('Y-m-d H:i:s');
               $res = $this->double_date->update($p, $id)
                  ? ['status' => true, 'message' => 'Tanggal khusus berhasil diubah']
                  : ['status' => false, 'message' => 'Terjadi kesalahan, coba lagi nanti'];
            }else{
               $res = ['status' => false, 'message' => 'Tanggal khusus sudah ada'];
            }
         }else{
            $res = ['status' => false, 'message' => validation_errors()];
         }

         echo json_encode($res);
      }else{
         show_404();
      }
   }

   public function delete(){
      if($this->input->is_ajax_request() && in_array($this->role, ['admin', 'admin-branch'])){
         $id = $this->input->post('id');
         $find = ['double_deduction_date.id' => $id];
         if($this->role != 'admin'){
            $find['double_deduction_date.branch_id'] = $this->userdata->branch_id;
         }

         if($this->double_date->get_detail($find)->num_rows() == 0){
            echo json_encode(['status' => false, 'message' => 'Tanggal khusus tidak diketahui']);
            return;
         }

         echo json_encode($this->double_date->delete($id)
            ? ['status' => true, 'message' => 'Tanggal khusus berhasil dihapus']
            : ['status' => false, 'message' => 'Tanggal khusus gagal dihapus']);
      }else{
         show_404();
      }
   }

   public function change_status(){
      if($this->input->is_ajax_request() && in_array($this->role, ['admin', 'admin-branch'])){
         $id = $this->input->post('id');
         $find = ['double_deduction_date.id' => $id];
         if($this->role != 'admin'){
            $find['double_deduction_date.branch_id'] = $this->userdata->branch_id;
         }

         $detail = $this->double_date->get_detail($find);
         if($detail->num_rows() == 0){
            echo json_encode(['status' => false, 'message' => 'Tanggal khusus tidak diketahui']);
            return;
         }

         $row = $detail->row_array();
         $is_active = $row['is_active'] == '1' ? '0' : '1';
         $this->double_date->update([
            'is_active' => $is_active,
            'updated_at' => date('Y-m-d H:i:s')
         ], $id);

         echo json_encode([
            'status' => true,
            'message' => $is_active == '1' ? 'Tanggal khusus berhasil diaktifkan' : 'Tanggal khusus berhasil dinonaktifkan'
         ]);
      }else{
         show_404();
      }
   }

   private function _normalize_date($date){
      $date = trim((string)$date);
      if($date == ''){
         return false;
      }

      $time = strtotime($date);
      return $time ? date('Y-m-d', $time) : false;
   }
}

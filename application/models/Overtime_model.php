<?php
 
Class Overtime_model extends CI_Model{

   protected $table = 'overtime';
    
   public function get_data($order = array()){
      if(!empty($order)){
         foreach ($order as $key => $value){
            $this->db->order_by($key, $value);
         }

      }else{
         $this->db->order_by($this->table.'.id', 'DESC');
      }

      return $this->db->get($this->table);
   }

   public function get_detail($key, $val = '', $limit = '', $order = array()){
      $this->db->select('overtime.id, overtime.id AS overtime_id, overtime.user_id, overtime_proof, overtime_hour, overtime_date, overtime_status, overtime.created_at, overtime.confirm_at, overtime.updated_at, overtime.deleted_at, reject_reason,
           DATE_FORMAT(overtime.created_at, "%d %M %Y %H:%i") AS created_at_string,
           DATE_FORMAT(overtime.updated_at, "%d %M %Y %H:%i") AS updated_at_string,
           users.first_name, users.employee_code, branch_name, branch_code, position_name
          ')
               ->join('users', 'users.id = overtime.user_id')
               ->join('position', 'position.id = users.position_id')
               ->join('branch', 'branch.id = position.branch_id');

      if(is_array($key)){
         $this->db->where($key);
      
      }else{
         $this->db->where($key, $val);
      }

      if($limit != ''){
         $this->db->limit($limit);
      }

      if(!empty($order)){
         foreach ($order as $key => $value) {
            $this->db->order_by($key, $value);
         }
      }else{
         $this->db->order_by($this->table.'.id', 'DESC');
      }

      return $this->db->get($this->table);
   }

   public function insert($data){
      $this->db->insert($this->table, $data);
      if($this->db->affected_rows() > 0){
         return true;
      }
      return false;
   }

   public function delete($id){
      $this->db->where('id', $id)
               ->delete($this->table);
      if($this->db->affected_rows() > 0){
         return true;
      }
      return false;
   }

   public function update($data, $id){
      $this->db->where('id', $id)
               ->update($this->table, $data);
      return true;
   }

   public function get_dataTable($find = array()){
      $dt = $this->datatables->init();

      if(empty($find)){
         $dt->select('overtime.id, overtime.id AS overtime_id, overtime.user_id, overtime_proof, overtime_hour, overtime_date, overtime_status, overtime.created_at, overtime.confirm_at, overtime.updated_at, overtime.deleted_at,
           DATE_FORMAT(overtime.created_at, "%d %M %Y %H:%i") AS created_at_string,
           DATE_FORMAT(overtime.updated_at, "%d %M %Y %H:%i") AS updated_at_string,
           users.first_name, users.employee_code, branch_name, branch_code, position_name
          ')
               ->from($this->table)
               ->join('users', 'users.id = overtime.user_id')
               ->join('position', 'position.id = users.position_id');

      }else{

         $dt->select('overtime.id, overtime.id AS overtime_id, overtime.user_id, overtime_proof, overtime_hour, overtime_date, overtime_status, overtime.created_at, overtime.confirm_at, overtime.updated_at, overtime.deleted_at,
           DATE_FORMAT(overtime.created_at, "%d %M %Y %H:%i") AS created_at_string,
           DATE_FORMAT(overtime.updated_at, "%d %M %Y %H:%i") AS updated_at_string,
           users.first_name, users.employee_code, branch_name, branch_code, position_name
          ')
               ->from($this->table)
               ->where($find)
               ->join('users', 'users.id = overtime.user_id')
               ->join('position', 'position.id = users.position_id')
               ->join('branch', 'branch.id = position.branch_id');
      }
      
      $dt->style(array(
         'class' => 'table table-striped table-bordered',
          ))
         ->column('<b>NO</b>', 'num_dt overtime_id')
         ->column('<b>KARYAWAN</b>', 'first_name', function($data, $row){
            $txt = $row['first_name']."<br><small class='text-muted'><i class='fa fa-user-circle'></i> ".$row['employee_code']."<br><i class='fa fa-building'></i> ".$row['branch_name']."</small>";

            return $txt;
         })
         ->column('<b>JABATAN</b>', 'position_name')
         ->column('<b>LAMA LEMBUR</b>', 'overtime_hour', function($data, $row){
            return $row['overtime_hour']." Jam";
         })
         ->column('<b>TANGGAL</b>', 'overtime_hour', function($data, $row){
            return indonesian_date($row['overtime_date']);
         })
         ->column('<b>WAKTU PENGAJUAN</b>', 'created_at', function($data, $row){
            return indonesian_date($row['created_at'], true);
         })
         ->column('<b>STATUS</b>', 'overtime_status', function($data, $row){
            return transaction_status($row['overtime_status']);
         })
         ->column('<center><i class="fa fa-cog"></i></center>', 'overtime_id', function($data, $row){
            $edit = 'data-id="'.$row['id'].'"
                     data-user-id="'.$row['user_id'].'"
                     data-hour="'.$row['overtime_hour'].'"
                     data-date="'.$row['overtime_date'].'"
                     data-name="'.$row['first_name'].'"
                     data-code="'.$row['employee_code'].'"';
            return "<center><a class='btn btn-primary btn-sm' href='".site_url('hr/overtime/detail/'.$row['id'])."'><i class='fa fa-search'></i></a> <a href='javascript:void(0)' ".$edit." class='btn btn-warning btn-sm edit'><i class='fa fa-pencil'></i></a></center>";
         });

      $this->datatables->create('tableContent', $dt);
   }


   public function get_table_acc($find = array(), $table_name = ''){
      $dt = $this->datatables->init();

      if(empty($find)){
         $dt->select('overtime.id, overtime.id AS overtime_id, overtime.user_id, overtime_proof, overtime_hour, overtime_date, overtime_status, overtime.created_at, overtime.confirm_at, overtime.updated_at, overtime.deleted_at,
           DATE_FORMAT(overtime.created_at, "%d %M %Y %H:%i") AS created_at_string,
           DATE_FORMAT(overtime.updated_at, "%d %M %Y %H:%i") AS updated_at_string,
           users.first_name, users.employee_code, branch_name, branch_code, position_name
          ')
               ->from($this->table)
               ->join('users', 'users.id = overtime.user_id')
               ->join('position', 'position.id = users.position_id');

      }else{

         $dt->select('overtime.id, overtime.id AS overtime_id, overtime.user_id, overtime_proof, overtime_hour, overtime_date, overtime_status, overtime.created_at, overtime.confirm_at, overtime.updated_at, overtime.deleted_at,
           DATE_FORMAT(overtime.created_at, "%d %M %Y %H:%i") AS created_at_string,
           DATE_FORMAT(overtime.updated_at, "%d %M %Y %H:%i") AS updated_at_string,
           users.first_name, users.employee_code, branch_name, branch_code, position_name
          ')
               ->from($this->table)
               ->where($find)
               ->join('users', 'users.id = overtime.user_id')
               ->join('position', 'position.id = users.position_id')
               ->join('branch', 'branch.id = position.branch_id');
      }
      
      $dt->style(array(
         'class' => 'table table-striped table-bordered',
          ))
         ->column('<b>NO</b>', 'num_dt overtime_id')
         ->column('<b>KARYAWAN</b>', 'first_name', function($data, $row){
            $txt = $row['first_name']."<br><small class='text-muted'><i class='fa fa-user-circle'></i> ".$row['employee_code']."<br><i class='fa fa-building'></i> ".$row['branch_name']."</small>";

            return $txt;
         })
         ->column('<b>JABATAN</b>', 'position_name')
         ->column('<b>LAMA LEMBUR</b>', 'overtime_hour', function($data, $row){
            return $row['overtime_hour']." Jam";
         })
         ->column('<b>TANGGAL</b>', 'overtime_hour', function($data, $row){
            return indonesian_date($row['overtime_date']);
         })
         ->column('<b>WAKTU PENGAJUAN</b>', 'created_at', function($data, $row){
            return indonesian_date($row['created_at'], true);
         })
         ->column('<b>STATUS</b>', 'overtime_status', function($data, $row){
            return transaction_status($row['overtime_status']);
         })
         ->column('<center><i class="fa fa-cog"></i></center>', 'overtime_id', function($data, $row){
            return "<center><a class='btn btn-primary btn-sm' href='".site_url('hr/overtime/acc/detail/'.$row['id'])."'><i class='fa fa-search'></i></a></center>";
         });

      $this->datatables->create($table_name != '' ? $table_name : 'tableContent', $dt);
   }
}  

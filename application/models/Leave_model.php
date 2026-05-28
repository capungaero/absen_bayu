<?php
 
Class leave_model extends CI_Model{

   protected $table = 'leave';
    
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
      $this->db->select('leave.id, leave.id AS leave_id, leave.user_id, leave_proof, leave_start, leave_end, leave_status, leave.created_at, leave.confirm_at, leave.updated_at, leave.deleted_at, reject_reason, leave_range, leave_type, leave_reason, default_potongan, request_potongan, jumlah_hari_potongan, acc_potongan,
           DATE_FORMAT(leave.created_at, "%d %M %Y %H:%i") AS created_at_string,
           DATE_FORMAT(leave.updated_at, "%d %M %Y %H:%i") AS updated_at_string,
           users.first_name, users.employee_code, branch_name, branch_code, position_name
          ')
               ->join('users', 'users.id = leave.user_id')
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
         $dt->select('leave.id, leave.id AS leave_id, leave.user_id, leave_proof, leave_start, leave_end, leave_status, leave.created_at, leave.confirm_at, leave.updated_at, leave_range, leave.deleted_at, leave_type,
           DATE_FORMAT(leave.created_at, "%d %M %Y %H:%i") AS created_at_string,
           DATE_FORMAT(leave.updated_at, "%d %M %Y %H:%i") AS updated_at_string,
           users.first_name, users.employee_code, branch_name, branch_code, position_name
          ')
               ->from($this->table)
               ->join('users', 'users.id = leave.user_id')
               ->join('position', 'position.id = users.position_id');

      }else{

         $dt->select('leave.id, leave.id AS leave_id, leave.user_id, leave_proof, leave_start, leave_end, leave_range, leave_status, leave.created_at, leave.confirm_at, leave.updated_at, leave.deleted_at, leave_type,
           DATE_FORMAT(leave.created_at, "%d %M %Y %H:%i") AS created_at_string,
           DATE_FORMAT(leave.updated_at, "%d %M %Y %H:%i") AS updated_at_string,
           users.first_name, users.employee_code, branch_name, branch_code, position_name
          ')
               ->from($this->table)
               ->where($find)
               ->join('users', 'users.id = leave.user_id')
               ->join('position', 'position.id = users.position_id')
               ->join('branch', 'branch.id = position.branch_id');
      }
      
      $dt->style(array(
         'class' => 'table table-striped table-bordered',
          ))
         ->column('<b>NO</b>', 'num_dt leave_id')
         ->column('<b>KARYAWAN</b>', 'first_name', function($data, $row){
            $txt = $row['first_name']."<br><small class='text-muted'><i class='fa fa-user-circle'></i> ".$row['employee_code']."<br><i class='fa fa-building'></i> ".$row['branch_name']."</small>";

            return $txt;
         })
         ->column('<b>JABATAN</b>', 'position_name')
         ->column('<b>RENTANG IZIN</b>', 'leave_start', function($data, $row){
            if($row['leave_start'] == $row['leave_end']){
              $date = $row['leave_start'];
            }else{
              $date = date('d-m-Y', strtotime($row['leave_start']))." s/d ".date('d-m-Y', strtotime($row['leave_end']));
            }
            return '<center>'.$date.'<br>( '.$row['leave_range'].' Hari )</center>';
         })
         ->column('<b>WAKTU PENGAJUAN</b>', 'created_at', function($data, $row){
            return indonesian_date($row['created_at'], true);
         })
         ->column('<b>STATUS</b>', 'leave_status', function($data, $row){
            return transaction_status($row['leave_status']);
         })
         ->column('<center><i class="fa fa-cog"></i></center>', 'leave_id', function($data, $row){
            return "<center><a class='btn btn-primary btn-sm' href='".site_url('hr/leave/detail/'.$row['id'])."'><i class='fa fa-search'></i></a></center>";
         });

      $this->datatables->create('tableContent', $dt);
   }


   public function get_table_acc($find = array(), $table_name = ''){
      $dt = $this->datatables->init();

      if(empty($find)){
         $dt->select('leave.id, leave.id AS leave_id, leave.user_id, leave_proof, leave_start, leave_end, leave_status, leave.created_at, leave.confirm_at, leave.updated_at, leave.deleted_at, leave_range, leave_type,
           DATE_FORMAT(leave.created_at, "%d %M %Y %H:%i") AS created_at_string,
           DATE_FORMAT(leave.updated_at, "%d %M %Y %H:%i") AS updated_at_string,
           users.first_name, users.employee_code, branch_name, branch_code, position_name
          ')
               ->from($this->table)
               ->join('users', 'users.id = leave.user_id')
               ->join('position', 'position.id = users.position_id');

      }else{

         $dt->select('leave.id, leave.id AS leave_id, leave.user_id, leave_proof, leave_start, leave_end, leave_status, leave.created_at, leave.confirm_at, leave.updated_at, leave.deleted_at, leave_range, leave_type,
           DATE_FORMAT(leave.created_at, "%d %M %Y %H:%i") AS created_at_string,
           DATE_FORMAT(leave.updated_at, "%d %M %Y %H:%i") AS updated_at_string,
           users.first_name, users.employee_code, branch_name, branch_code, position_name
          ')
               ->from($this->table)
               ->where($find)
               ->join('users', 'users.id = leave.user_id')
               ->join('position', 'position.id = users.position_id')
               ->join('branch', 'branch.id = position.branch_id');
      }
      
      $dt->style(array(
         'class' => 'table table-striped table-bordered',
          ))
         ->column('<b>NO</b>', 'num_dt leave_id')
         ->column('<b>KARYAWAN</b>', 'first_name', function($data, $row){
            $txt = $row['first_name']."<br><small class='text-muted'><i class='fa fa-user-circle'></i> ".$row['employee_code']."<br><i class='fa fa-building'></i> ".$row['branch_name']."</small>";

            return $txt;
         })
         ->column('<b>JABATAN</b>', 'position_name')
         ->column('<b>RENTANG IZIN</b>', 'leave_start', function($data, $row){
            if($row['leave_start'] == $row['leave_end']){
              $date = $row['leave_start'];
            }else{
              $date = date('d-m-Y', strtotime($row['leave_start']))." s/d ".date('d-m-Y', strtotime($row['leave_end']));
            }
            return '<center>'.$date.'<br>( '.$row['leave_range'].' Hari )</center>';
         })
         ->column('<b>WAKTU PENGAJUAN</b>', 'created_at', function($data, $row){
            return indonesian_date($row['created_at'], true);
         })
         ->column('<b>STATUS</b>', 'leave_status', function($data, $row){
            return transaction_status($row['leave_status']);
         })
         ->column('<center><i class="fa fa-cog"></i></center>', 'leave_id', function($data, $row){
            return "<center><a class='btn btn-primary btn-sm' href='".site_url('hr/leave/acc/detail/'.$row['id'])."'><i class='fa fa-search'></i></a></center>";
         });

      $this->datatables->create($table_name != '' ? $table_name : 'tableContent', $dt);
   }
}  

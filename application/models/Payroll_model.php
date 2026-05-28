<?php
 
Class Payroll_model extends CI_Model{

   protected $table = 'payroll';
    
   public function get_by_year($year, $branch_id){
      $res  = [];

      for ($i=1; $i <= 12 ; $i++){ 
         $this->db->where([
                  'month'     => $i,
                  'year'      => $year,
                  'branch_id' => $branch_id
               ]);
         $data = $this->db->get($this->table);

         if($data->num_rows() > 0){
            $row = $data->row_array();
            $res[] = [
               'id'           => $row['id'],
               'payroll_code' => $row['payroll_code'],
               'branch_id'    => $row['branch_id'],
               'num'          => $row['month'],
               'month'        => get_monthname($row['month']),
               'year'         => $year,
               'total_employee' => $row['total_employee'],
               'total_salary'   => $row['total_salary'],
               'created_at'     => $row['created_at']
            ];

         }else{
            $res[] = [
               'id' => '',
               'payroll_code' => '',
               'branch_id' => '',
               'num'       => $i,
               'month'     => get_monthname($i),
               'year'      => $year,
               'total_salary' => 0,
               'total_employee' => 0
            ];
         }
      }

      return $res;
   }


   public function get_by_list_branch($month, $year){
      $this->db->select('*, branch.id AS branch_master_id, payroll.id AS payroll_id')
               ->join('payroll', 'payroll.branch_id = branch.id AND month="'.$month.'" AND year="'.$year.'"', 'LEFT')
               ->where('branch.is_active', 1)
               ->order_by('branch_name', 'ASC');
      return $this->db->get('branch')->result_array();
   }

   public function get_detail($key, $val = '', $limit = '', $order = array()){
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
         $this->db->order_by('payroll.id', 'DESC');
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

   public function get_payment_list_by_id($payroll_id, $employee_id = '', $order_by = array(), $employee_ids = []){
      $this->db->select('*, payroll_detail.id AS payroll_detail_id')
               ->where('payroll_id', $payroll_id)
               ->join('users', 'users.id = payroll_detail.user_id')
               ->join('position', 'position.id = users.position_id')
               ->join('subdivision', 'subdivision.id = users.subdivision_id', 'LEFT')
               ->join('branch', 'branch.id = position.branch_id');

      if(!empty($order_by)){
         foreach ($order_by as $key => $value){
           $this->db->order_by($key, $value);
         }

      }else{
         $this->db->order_by('users.first_name', 'ASC');
      }

      if($employee_id != ''){
         $this->db->where('payroll_detail.user_id', $employee_id);
      }else{
         if(!empty($employee_ids)){
            $this->db->where_in('payroll_detail.user_id', $employee_ids);
         }
      }

      return  $this->db->get('payroll_detail')->result_array();
   }

   public function get_insentif_by_user($user_id, $month, $year){
      $this->db->join('insentif', 'insentif.id = payroll_insentif.insentif_id')
               ->where([
                  'user_id'          => $user_id,
                  'insentif_month'   => $month,
                  'insentif_year'    => $year
               ]);
      return $this->db->get('payroll_insentif')->result_array();
   }
}  

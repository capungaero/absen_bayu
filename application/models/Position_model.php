<?php
 
Class Position_model extends CI_Model{

   protected $table = 'position';
    
   public function get_data($order = array()){

      if(!empty($order)){
         foreach ($order as $key => $value) {
            $this->db->order_by($key, $value);
         }
      }else{
         $this->db->order_by($this->table.'.id', 'DESC');
      }

      return $this->db->get($this->table);
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
         $dt->select('position.id AS position_id, branch_id, branch_name, branch_code, city, position_name, position_code, position.created_at, position.updated_at,
             DATE_FORMAT(position.created_at, "%d %M %Y %H:%i") AS created_at_string,
             DATE_FORMAT(position.updated_at, "%d %M %Y %H:%i") AS updated_at_string, is_default')
               ->from($this->table)
               ->join('branch', 'branch.id = position.branch_id');
      }else{
         $dt->select('position.id AS position_id, branch_id, branch_name, branch_code, city, position_name, position_code, position.created_at, position.updated_at,
             DATE_FORMAT(position.created_at, "%d %M %Y %H:%i") AS created_at_string,
             DATE_FORMAT(position.updated_at, "%d %M %Y %H:%i") AS updated_at_string, is_default')
            ->from($this->table)
            ->where($find)
            ->join('branch', 'branch.id = position.branch_id');
      }
      
      $dt->style(array(
         'class' => 'table table-striped table-bordered',
          ))
         ->column('<b>NO</b>', 'num_dt position_id')
         ->column('<b>KODE</b>', 'position_code')
         ->column('<b>NAMA POSISI</b>', 'position_name')
         ->column('<center><i class="fa fa-cog"></i></center>', 'position_id', function($data, $row){
            
            $edit = 'data-id="'.$row['position_id'].'"
                     data-name="'.$row['position_name'].'"
                     data-code="'.$row['position_code'].'"
                     data-branch-id="'.$row['branch_id'].'"';

            $comp = '';
            if($row['is_default'] == '0'){
              $comp = '<a data-id="'.$row['position_id'].'" href="javascript:void(0)" class="dropdown-item text-danger delete" data-bs-toggle="modal" data-bs-target="#modalDelete"><i class="dripicons-trash"></i> Hapus</a>';
            }

            $txt = '<div class="dropdown mt-4 mt-sm-0">
                      <a href="#" class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                          Action <i class="fa fa-chevron-down"></i>
                      </a>

                      <div class="dropdown-menu">
                           <a href="javascript:void(0)" '.$edit.' class="dropdown-item edit"><i class="dripicons-pencil"></i> Ubah</a>
                           '.$comp.'
                      </div>
                  </div>';

            return "<center>".$txt."</center>";
         });

      $this->datatables->create('tableContent', $dt);
   }
}  

<?php
 
Class Subdivision_model extends CI_Model{

   protected $table = 'subdivision';
    
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
         $dt->select('subdivision.id AS subdivision_id, branch_id, branch_name, branch_code, city, subdivision_name, subdivision_code, subdivision.created_at, subdivision.updated_at,
             DATE_FORMAT(subdivision.created_at, "%d %M %Y %H:%i") AS created_at_string,
             DATE_FORMAT(subdivision.updated_at, "%d %M %Y %H:%i") AS updated_at_string')
               ->from($this->table)
               ->join('branch', 'branch.id = subdivision.branch_id');
      }else{
         $dt->select('subdivision.id AS subdivision_id, branch_id, branch_name, branch_code, city, subdivision_name, subdivision_code, subdivision.created_at, subdivision.updated_at,
             DATE_FORMAT(subdivision.created_at, "%d %M %Y %H:%i") AS created_at_string,
             DATE_FORMAT(subdivision.updated_at, "%d %M %Y %H:%i") AS updated_at_string')
            ->from($this->table)
            ->where($find)
            ->join('branch', 'branch.id = subdivision.branch_id');
      }
      
      $dt->style(array(
         'class' => 'table table-striped table-bordered',
          ))
         ->column('<b>NO</b>', 'num_dt subdivision_id')
         ->column('<b>KODE</b>', 'subdivision_code')
         ->column('<b>NAMA SUB DEPARTEMENT</b>', 'subdivision_name')
         ->column('<center><i class="fa fa-cog"></i></center>', 'subdivision_id', function($data, $row){
            
            $edit = 'data-id="'.$row['subdivision_id'].'"
                     data-name="'.$row['subdivision_name'].'"
                     data-code="'.$row['subdivision_code'].'"
                     data-branch-id="'.$row['branch_id'].'"';

            $comp = '<a data-id="'.$row['subdivision_id'].'" href="javascript:void(0)" class="dropdown-item text-danger delete" data-bs-toggle="modal" data-bs-target="#modalDelete"><i class="dripicons-trash"></i> Hapus</a>';
            

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

<?php
 
Class Insentif_model extends CI_Model{

   protected $table = 'insentif';
    
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
         $dt->select('insentif.id AS insentif_id, branch_id, branch_name, branch_code, city, insentif_name, insentif.created_at, insentif.updated_at, insentif.nominal, insentif.formula, insentif.is_active,
             DATE_FORMAT(insentif.created_at, "%d %M %Y %H:%i") AS created_at_string,
             DATE_FORMAT(insentif.updated_at, "%d %M %Y %H:%i") AS updated_at_string')
               ->from($this->table)
               ->join('branch', 'branch.id = insentif.branch_id');
      }else{
         $dt->select('insentif.id AS insentif_id, branch_id, branch_name, branch_code, city, insentif_name, insentif.nominal, insentif.formula, insentif.is_active, insentif.created_at, insentif.updated_at,
             DATE_FORMAT(insentif.created_at, "%d %M %Y %H:%i") AS created_at_string,
             DATE_FORMAT(insentif.updated_at, "%d %M %Y %H:%i") AS updated_at_string')
            ->from($this->table)
            ->where($find)
            ->join('branch', 'branch.id = insentif.branch_id');
      }
      
      $dt->style(array(
         'class' => 'table table-striped table-bordered',
          ))
         ->column('<b>NO</b>', 'num_dt insentif_id')
         ->column('<b>NAMA INSENTIF</b>', 'insentif_name')
         ->column('<b>FORMULA</b>', 'formula', function($data, $row){
            return status_formula($row['formula']);
         })
         ->column('<b>NOMINAL</b>', 'nominal', function($data, $row){
            return $row['formula'] == 'none' ? '-' : format_rp($row['nominal']);
         })
         ->column('<b>STATUS</b>', 'is_active', function($data, $row){
            return $row['is_active'] == '1' ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Non Aktif</span>';
         })
         ->column('<center><i class="fa fa-cog"></i></center>', 'insentif_id', function($data, $row){
            
            $edit = 'data-id="'.$row['insentif_id'].'"
                     data-name="'.$row['insentif_name'].'"
                     data-formula="'.$row['formula'].'"
                     data-nominal="'.$row['nominal'].'"
                     data-active="'.$row['is_active'].'"
                     data-branch-id="'.$row['branch_id'].'"';

            $comp = '<a data-id="'.$row['insentif_id'].'" href="javascript:void(0)" class="dropdown-item text-danger delete" data-bs-toggle="modal" data-bs-target="#modalDelete"><i class="dripicons-trash"></i> Hapus</a>';
          

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

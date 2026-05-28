<?php
 
Class Branch_model extends CI_Model{

   protected $table = 'branch';
    
   public function get_data($order = array(), $active_only = true){
      if($active_only){
         $this->db->where($this->table.'.is_active', 1);
      }

      if(!empty($order)){
         if(isset($order[0]) && isset($order[1])){
            $this->db->order_by($order[0], $order[1]);
         }else{
            foreach ($order as $key => $value) {
               $this->db->order_by($key, $value);
            }
         }
      }else{
         $this->db->order_by($this->table.'.id', 'DESC');
      }

      return $this->db->get($this->table);
   }

   public function get_branch($id){
      $this->db->where('category_id');
      return $this->db->get('category_branch')->result_array();
   }

   public function get_detail($key, $val = '', $limit = '', $order = array(), $active_only = false){
      if($active_only){
         $this->db->where($this->table.'.is_active', 1);
      }

      if(is_array($key)){
         $this->db->where($key);
      
      }else{
         if($key != ''){
            $this->db->where($key, $val);
         }
      }

      if($limit != ''){
         $this->db->limit($limit);
      }

      if(!empty($order)){
         if(isset($order[0]) && isset($order[1])){
            $this->db->order_by($order[0], $order[1]);
         }else{
            foreach ($order as $key => $value) {
               $this->db->order_by($key, $value);
            }
         }
      }else{
         $this->db->order_by($this->table.'.id', 'DESC');
      }

      return $this->db->get($this->table);
   }

   public function get_active_detail($key, $val = '', $limit = '', $order = array()){
      return $this->get_detail($key, $val, $limit, $order, true);
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
          $dt->select('*')
               ->from($this->table);
      }else{
         $dt->select('*')
            ->from($this->table)
            ->where($find);
      }
      
      $dt->style(array(
         'class' => 'table table-striped table-bordered',
          ))
         ->column('<b>NO</b>', 'num_dt id')
         ->column('<b>KODE</b>', 'branch_code')
         ->column('<b>NAMA CABANG</b>', 'branch_name')
         ->column('<b>LOKASI</b>', 'city', function($data, $row){
            return $row['city']."<br><small class='text-muted'>".$row['address']."</small>";
         })
         ->column('<b>KONTAK</b>', 'branch_phone')
         ->column('<center><b>STATUS</b></center>', 'is_active', function($data, $row){
            $label = $row['is_active'] == '1' ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>';
            return '<center>'.$label.'</center>';
         })
         ->column('<center><i class="fa fa-cog"></i></center>', 'id', function($data, $row){
            
            $param = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];
            $pray  = '';
            foreach ($param as $list) {
               $pray .= 'data-'.$list.'_pray_time = "'.$row[$list."_pray_time"].'"
                         data-'.$list.'_pray_time_in = "'.$row[$list."_pray_time_in"].'"
                         data-'.$list.'_pray_time_out = "'.$row[$list."_pray_time_out"].'"
                         data-'.$list.'_pray_time_range = "'.$row[$list."_pray_time_range"].'"
                        ';
            }
            $edit = 'data-id="'.$row['id'].'"
                     data-name="'.$row['branch_name'].'"
                     data-code="'.$row['branch_code'].'"
                     data-phone="'.$row['branch_phone'].'"
                     data-city="'.$row['city'].'"
                     data-address="'.$row['address'].'"
                     data-percentage="'.$row['percentage'].'"
                     data-proporsional="'.$row['proporsional'].'"
                     data-branch-tax="'.$row['branch_tax'].'"
                     data-max-overtime="'.$row['max_overtime'].'"
                     data-bpjs-health="'.$row['bpjs_health'].'"
                     data-bpjs-work="'.$row['bpjs_work'].'"
                     data-status="'.$row['is_active'].'"
                     data-pray-late-start-rate="'.$row['pray_late_start_rate'].'"
                     data-pray-late-multiple-count="'.$row['pray_late_multiple_count'].'"
                     data-pray-late-multiple-rate="'.$row['pray_late_multiple_rate'].'"
                     data-pray-late-fix-rate="'.$row['pray_late_fix_rate'].'"
                     '.$pray;

            //$delete = '<a data-id="'.$row['id'].'" href="javascript:void(0)" class="dropdown-item text-danger delete" data-bs-toggle="modal" data-bs-target="#modalDelete"><i class="dripicons-trash"></i> Hapus</a>';

            $txt = '<div class="dropdown mt-4 mt-sm-0">
                      <a href="#" class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                          Action <i class="fa fa-chevron-down"></i>
                      </a>

                      <div class="dropdown-menu">
                           <a href="javascript:void(0)" '.$edit.' class="dropdown-item edit"><i class="dripicons-pencil"></i> Edit</a>
                           <div class="dropdown-divider"></div>
                           
                      </div>
                  </div>';

            return "<center>".$txt."</center>";
         });

      $this->datatables->create('tableContent', $dt);
   }
}  

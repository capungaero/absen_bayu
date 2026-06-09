<?php

Class Double_deduction_date_model extends CI_Model{

   protected $table = 'double_deduction_date';

   public function __construct(){
      parent::__construct();
      $this->_ensure_table();
   }

   private function _ensure_table(){
      $this->db->query("
         CREATE TABLE IF NOT EXISTS `double_deduction_date` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `branch_id` INT NOT NULL,
            `special_date` DATE NOT NULL,
            `description` VARCHAR(255) DEFAULT NULL,
            `is_active` ENUM('0','1') NOT NULL DEFAULT '1',
            `created_at` DATETIME DEFAULT NULL,
            `updated_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_branch_special_date` (`branch_id`, `special_date`),
            KEY `idx_branch_active_date` (`branch_id`, `is_active`, `special_date`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
      ");
   }

   public function insert($data){
      $this->db->insert($this->table, $data);
      return $this->db->affected_rows() > 0;
   }

   public function update($data, $id){
      $this->db->where('id', $id)->update($this->table, $data);
      return true;
   }

   public function delete($id){
      $this->db->where('id', $id)->delete($this->table);
      return $this->db->affected_rows() > 0;
   }

   public function get_detail($key, $val = '', $limit = '', $order = array()){
      $this->db->select($this->table.'.*, branch.branch_name, branch.branch_code')
               ->join('branch', 'branch.id = '.$this->table.'.branch_id');

      if(is_array($key)){
         $this->db->where($key);
      }else if($key != ''){
         $this->db->where($key, $val);
      }

      if($limit != ''){
         $this->db->limit($limit);
      }

      if(!empty($order)){
         foreach($order as $field => $direction){
            $this->db->order_by($field, $direction);
         }
      }else{
         $this->db->order_by('special_date', 'DESC');
      }

      return $this->db->get($this->table);
   }

   public function get_active_dates($branch_id, $from_date, $to_date){
      $rows = $this->db->select('special_date, description')
                       ->where('branch_id', $branch_id)
                       ->where('is_active', '1')
                       ->where('special_date >=', $from_date)
                       ->where('special_date <=', $to_date)
                       ->get($this->table)
                       ->result_array();

      $dates = [];
      foreach($rows as $row){
         $dates[$row['special_date']] = $row;
      }

      return $dates;
   }

   public function get_dataTable($find = array()){
      $dt = $this->datatables->init();

      if(empty($find)){
         $dt->select('double_deduction_date.id AS special_id, double_deduction_date.branch_id, branch.branch_name, branch.branch_code, special_date, description, double_deduction_date.is_active, double_deduction_date.created_at, double_deduction_date.updated_at')
            ->from($this->table)
            ->join('branch', 'branch.id = double_deduction_date.branch_id');
      }else{
         $dt->select('double_deduction_date.id AS special_id, double_deduction_date.branch_id, branch.branch_name, branch.branch_code, special_date, description, double_deduction_date.is_active, double_deduction_date.created_at, double_deduction_date.updated_at')
            ->from($this->table)
            ->where($find)
            ->join('branch', 'branch.id = double_deduction_date.branch_id');
      }

      $dt->style(array(
         'class' => 'table table-striped table-bordered',
      ))
      ->column('<b>NO</b>', 'num_dt special_id')
      ->column('<b>TANGGAL</b>', 'special_date', function($data, $row){
         return indonesian_date($row['special_date'])."<br><small class='text-muted'>".get_dayname($row['special_date'])."</small>";
      })
      ->column('<b>CABANG</b>', 'branch_name', function($data, $row){
         return $row['branch_code']." / ".$row['branch_name'];
      })
      ->column('<b>KETERANGAN</b>', 'description', function($data, $row){
         return $row['description'] != '' ? htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8') : '-';
      })
      ->column('<center><b>STATUS</b></center>', 'is_active', function($data, $row){
         if($row['is_active'] == '1'){
            $title = 'Aktif';
            $icon = 'fa-check-circle';
            $class = 'text-success';
         }else{
            $title = 'Non Aktif';
            $icon = 'fa-times-circle';
            $class = 'text-danger';
         }

         return '<center><button type="button" class="btn btn-sm btn-light '.$class.' toggle-double-date-status" data-id="'.$row['special_id'].'" title="'.$title.'"><i class="fa '.$icon.'"></i></button></center>';
      })
      ->column('<center><i class="fa fa-cog"></i></center>', 'special_id', function($data, $row){
         $edit = 'data-id="'.$row['special_id'].'"
                  data-branch-id="'.$row['branch_id'].'"
                  data-date="'.$row['special_date'].'"
                  data-description="'.htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8').'"
                  data-active="'.$row['is_active'].'"';

         $txt = '<div class="dropdown mt-4 mt-sm-0">
                   <a href="#" class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                       Action <i class="fa fa-chevron-down"></i>
                   </a>
                   <div class="dropdown-menu">
                        <a href="javascript:void(0)" '.$edit.' class="dropdown-item edit"><i class="dripicons-pencil"></i> Ubah</a>
                        <a data-id="'.$row['special_id'].'" href="javascript:void(0)" class="dropdown-item text-danger delete" data-bs-toggle="modal" data-bs-target="#modalDelete"><i class="dripicons-trash"></i> Hapus</a>
                   </div>
               </div>';

         return "<center>".$txt."</center>";
      });

      $this->datatables->create('tableContent', $dt);
   }
}

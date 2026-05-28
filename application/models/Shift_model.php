<?php
 
Class Shift_model extends CI_Model{

   protected $table = 'shift';
    
   public function get_data(){
      $this->db->order_by('id', 'DESC');
      return $this->db->get($this->table)->result_array();
   }

   public function get_detail($key, $val = ''){
      $this->db->order_by('id', 'DESC');
      if(is_array($key)){
         $this->db->where($key);
      
      }else{
         $this->db->where($key, $val);
      }

      return $this->db->get($this->table);
   }

   public function get_active($key, $val = ''){
      $this->db->where('is_active', '1');
      return $this->get_detail($key, $val);
   }

   public function insert($data){
      $this->db->insert($this->table, $data);

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

   public function delete($id){
      $this->db->where('id', $id)
               ->delete($this->table);

      if($this->db->affected_rows() > 0){
         return true;
      }
      return false;
   }

   public function insert_cluster($data){
      $this->db->insert('shift_cluster', $data);

      if($this->db->affected_rows() > 0){
         return true;
      }
      return false;
   }

   public function update_cluster($data, $id){
      $this->db->where('id', $id)
               ->update('shift_cluster', $data);
      return true;
   }

   public function delete_cluster($id){
      $this->db->where('id', $id)
               ->delete('shift_cluster');

      if($this->db->affected_rows() > 0){
         return true;
      }
      return false;
   }

   public function get_cluster_detail($key, $val = ''){
      $this->db->order_by('id', 'DESC');
      if(is_array($key)){
         $this->db->where($key);
      
      }else{
         $this->db->where($key, $val);
      }

      return $this->db->get('shift_cluster');
   }

   public function insert_rotation($data){
      $this->db->insert_batch('shift_cluster_rotation', $data);

      if($this->db->affected_rows() > 0){
         return true;
      }
      return false;
   }

   public function delete_rotation_by_cluster_id($id){
      return $this->db->where('shift_cluster_id', $id)->delete('shift_cluster_rotation');
   }

   public function get_rotation_by_cluster_id($id){
      return $this->db->select('shift_cluster_rotation.*, shift_code, shift_name, start_time, end_time')
                      ->join('shift', 'shift.id = shift_cluster_rotation.shift_id', 'LEFT')
                      ->order_by('num', 'ASC')
                      ->where('shift_cluster_id', $id)
                      ->get('shift_cluster_rotation')->result_array();
   }

    public function get_dataTable($find = array()){
      $dt = $this->datatables->init();

      $select = 'shift.id, branch_id, branch_code, branch_name, city, shift.created_at, shift.updated_at,
                        DATE_FORMAT(shift.created_at, "%d %M %Y %H:%i") AS created_at_string,
                        DATE_FORMAT(shift.updated_at, "%d %M %Y %H:%i") AS updated_at_string,
                        shift_name, shift_code, start_time, end_time, start_time_in, start_time_late, start_time_out, end_time_in, end_time_out, late_amount_start, late_amount_multiple_start, late_multiple_count_start, late_amount_rest, late_amount_multiple_rest, late_multiple_count_rest, start_time_rest, end_time_rest, rest_time_range, 
                          start_time_rest_friday, end_time_rest_friday, rest_time_range_friday,
                          late_amount_max_start, late_amount_max_rest, is_active';

      if(empty($find)){
        $dt->select($select)
           ->join('branch', 'branch.id = shift.branch_id')
           ->from('shift')
           ->order_by('shift.is_active', 'DESC');

      }else{
        $dt->select($select)
           ->from('shift')
           ->join('branch', 'branch.id = shift.branch_id')
           ->where($find)
           ->order_by('shift.is_active', 'DESC');
      }
      
      $dt->style(array(
         'class' => 'table table-bordered',
          ))
         ->set_options('createdRow', 'function(row, data){ if (String(data[6]).indexOf(\'data-active="0"\') !== -1) { $(row).addClass("text-muted shift-row-inactive"); } }')
         ->column('<b>NO</b>', 'num_dt is_active')
         ->column('<b>KODE</b>', 'shift_code', function($data, $row){
            return $row['shift_code'];
         })
         ->column('<b>NAMA SHIFT</b>', 'shift_name', function($data, $row){
            return $row['shift_name'];
         })
         ->column('<b>WAKTU KERJA</b>', 'start_time', function($data, $row){
            return date('H:i', strtotime($row['start_time']))." - ".date('H:i', strtotime($row['end_time']));
         })
         ->column('<b>ABSEN MASUK</b>', 'end_time', function($data, $row){
            return date('H:i', strtotime($row['start_time_in']))." - ".date('H:i', strtotime($row['start_time_out']));
         })
         ->column('<b>ABSEN KELUAR</b>', 'end_time', function($data, $row){
            return date('H:i', strtotime($row['end_time_in']))." - ".date('H:i', strtotime($row['end_time_out']));
         })
         ->column('<center><b>STATUS</b></center>', 'is_active', function($data, $row){
            if($row['is_active'] == '1'){
               $title = 'Aktif';
               $class = 'btn-light';
               $status_class = 'shift-status-active';
               $icon = 'fa-check-circle';
            }else{
               $title = 'Nonaktif';
               $class = 'btn-light';
               $status_class = 'shift-status-inactive';
               $icon = 'fa-times-circle';
            }

            return '<center><button type="button" data-active="'.$row['is_active'].'" data-id="'.$row['id'].'" class="btn btn-sm '.$class.' shift-status-btn '.$status_class.' toggle-active" aria-label="'.$title.'"><i class="fa '.$icon.'"></i></button></center>';
         })
         ->column('<center><b>ACTION</b></center>', 'id', function($data, $row){
            $edit =  'data-id="'.$row['id'].'"
                      data-code="'.$row['shift_code'].'"
                      data-name="'.$row['shift_name'].'"
                      data-active="'.$row['is_active'].'"
                      data-start-time="'.date('H:i', strtotime($row['start_time'])).'"
                      data-end-time="'.date('H:i', strtotime($row['end_time'])).'"

                      data-start-time-in="'.date('H:i', strtotime($row['start_time_in'])).'"
                      data-start-time-late="'.date('H:i', strtotime($row['start_time_late'])).'"
                      data-start-time-out="'.date('H:i', strtotime($row['start_time_out'])).'"

                      data-end-time-in="'.date('H:i', strtotime($row['end_time_in'])).'"
                      data-end-time-out="'.date('H:i', strtotime($row['end_time_out'])).'"

                      data-late-amount="'.$row['late_amount_start'].'"
                      data-late-amount-multiple="'.$row['late_amount_multiple_start'].'"
                      data-late-multiple-count="'.$row['late_multiple_count_start'].'"

                      data-rest-time-range="'.$row['rest_time_range'].'"
                      data-start-time-rest="'.date('H:i', strtotime($row['start_time_rest'])).'"
                      data-end-time-rest="'.date('H:i', strtotime($row['end_time_rest'])).'"

                      data-rest-time-range-friday="'.$row['rest_time_range_friday'].'"
                      data-start-time-rest-friday="'.date('H:i', strtotime($row['start_time_rest_friday'])).'"
                      data-end-time-rest-friday="'.date('H:i', strtotime($row['end_time_rest_friday'])).'"

                      data-late-amount-rest="'.$row['late_amount_rest'].'"
                      data-late-amount-multiple-rest="'.$row['late_amount_multiple_rest'].'"
                      data-late-multiple-count-rest="'.$row['late_multiple_count_rest'].'"
                      data-late-amount-max-start="'.$row['late_amount_max_start'].'"
                      data-late-amount-max-rest="'.$row['late_amount_max_rest'].'"
                      ';

            $txt = '<div class="shift-action-buttons">
                        <button type="button" '.$edit.' class="btn btn-sm btn-light shift-action-btn edit" aria-label="Edit"><i class="dripicons-pencil text-warning"></i></button>
                        <button type="button" data-id="'.$row['id'].'" class="btn btn-sm btn-light shift-action-btn delete" data-bs-toggle="modal" data-bs-target="#modalDelete" aria-label="Hapus"><i class="dripicons-trash text-danger"></i></button>
                    </div>';

            if($this->ion_auth->get_users_groups()->row()->name == 'supervisor'){
              $txt = '';
            }

            return "<center>".$txt."</center>";
         });

      $this->datatables->create('tableContent', $dt);
   }


   public function get_cluster_dataTable($find = array()){
      $dt = $this->datatables->init();

      if(empty($find)){
          $dt->select('shift_cluster.id, branch_id, branch_code, branch_name, city, shift_cluster.created_at, shift_cluster.updated_at,
                        DATE_FORMAT(shift_cluster.created_at, "%d %M %Y %H:%i") AS created_at_string,
                        DATE_FORMAT(shift_cluster.updated_at, "%d %M %Y %H:%i") AS updated_at_string,
                        cluster_name, cluster_code, cluster_applies')
               ->join('branch', 'branch.id = shift.branch_id')
               ->from('shift');
      }else{
         $dt->select('shift_cluster.id, branch_id, branch_code, branch_name, city, shift_cluster.created_at, shift_cluster.updated_at,
                        DATE_FORMAT(shift_cluster.created_at, "%d %M %Y %H:%i") AS created_at_string,
                        DATE_FORMAT(shift_cluster.updated_at, "%d %M %Y %H:%i") AS updated_at_string,
                        cluster_name, cluster_code, cluster_applies')
            ->from('shift_cluster')
            ->join('branch', 'branch.id = shift_cluster.branch_id')
            ->where($find);
      }
      
      $dt->style(array(
         'class' => 'table table-bordered',
          ))
         ->column('<b>NO</b>', 'num_dt id')
         ->column('<b>JADWAL CLUSTER</b>', 'cluster_name', function($data, $row){
            return $row['cluster_name']."<br><small class='text-muted'>".$row['cluster_code'];
         })
         ->column('<b>ROTASI</b>', 'id', function($data, $row){
            $rotation = $this->get_rotation_by_cluster_id($row['id']);
            $txt = '<table class="table no-border" style="border-color:#fbsql_fetch_field(result)"><tr>'; $n = 0;

            for ($i=0; $i < count($rotation); $i++) { $n++;
               $txt .= '<td class="text-center" style="background-color:#eee"><small>Hari - '.$n.'</small></td>';
            }
            $txt .= '</tr><tr>';

            foreach ($rotation as $row){
               if($row['shift_code'] != ''){
                  $code = $row['shift_code'];
                  $attr = 'class="shift" 
                           data-bs-toggle="modal" data-bs-target="#modalShift" 
                           data-name="'.$row['shift_code']." / ".$row['shift_name'].'" 
                           data-time="'.date('H:i', strtotime($row['start_time'])).' - '.date('H:i', strtotime($row['end_time'])).'"';
               }else{
                  $code = '<i class="fa fa-times-circle text-danger"></i>';
                  $attr = 'data-bs-toggle="tooltip" title="Libur"';
               }

               $txt .= '<td class="text-center">
                           <a '.$attr.' style="color:#495057" href="javascript:void(0)">'.$code.'</a>
                        </td>';
            }

            $txt .= '</tr></table>';
            return $txt;
         })
         ->column('<center><i class="fa fa-cog"></i></center>', 'id', function($data, $row){
            
            $edit =  'data-id="'.$row['id'].'"
                      data-code="'.$row['cluster_code'].'"
                      data-name="'.$row['cluster_name'].'"
                      data-date="'.$row['cluster_applies'].'"';

            $txt = '<div class="dropdown mt-4 mt-sm-0">
                      <a href="#" class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                          Action <i class="fa fa-chevron-down"></i>
                      </a>

                      <div class="dropdown-menu">
                           <a href="javascript:void(0)" '.$edit.' class="dropdown-item edit"><i class="dripicons-pencil"></i> Edit</a>
                           <div class="dropdown-divider"></div>
                           <a data-id="'.$row['id'].'" href="javascript:void(0)" class="dropdown-item text-danger delete" data-bs-toggle="modal" data-bs-target="#modalDelete"><i class="dripicons-trash"></i> Hapus</a>
                      </div>
                  </div>';

            return "<center>".$txt."</center>";
         });

      $this->datatables->create('tableContent', $dt);
   }
}

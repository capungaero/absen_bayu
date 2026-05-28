<?php
 
Class User_model extends CI_Model{

   protected $table = 'users';
    
   public function get_data(){
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

   public function get_detail($key, $val = '', $limit = '', $order = array()){
      $this->db->select('users.*, groups.id AS group_id, groups.name AS group_name, 
                         branch.id AS branch_id, branch_code, position_code, subdivision_id, subdivision_code, subdivision_name,
                         groups.description AS group_description, position_name, branch_name')
               ->join('users_groups', 'users_groups.user_id = users.id')
               ->join('groups', 'groups.id = users_groups.group_id')
               ->join('position', 'position.id = users.position_id')
               ->join('subdivision', 'subdivision.id = users.subdivision_id', 'LEFT')
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



   public function get_tableUser($find = array()){
      $dt = $this->datatables->init();

      if(empty($find)){
          $dt->select('users.id, users.email, users.first_name, users.phone, users.photo, users.bio, users.location, description, users.active, position.branch_id, position_name, branch_name, branch_code, employee_address, position_code, employee_code,last_login, created_on, position_id, join_date, groups.name AS group_name, groups.description AS group_description, groups.id AS group_id, last_status, salary, salary_minimum, contract_number, status_work, status_work_expiration, overtime_hour_rate, subdivision_id, subdivision_code, subdivision_name,
             account_name, account_bank, account_number, npwp_number, ptkp_status')
         ->from('users')
         ->join('position', 'position.id = users.position_id')
         ->join('subdivision', 'subdivision.id = users.subdivision_id', 'LEFT')
         ->join('branch', 'branch.id = position.branch_id')
         ->join('users_groups', 'users_groups.user_id = users.id')
         ->join('groups', 'groups.id = users_groups.group_id');

      }else{
          $dt->select('users.id, users.email, users.first_name, users.phone, users.photo, users.bio, users.location, description, users.active, position.branch_id, position_name, branch_name, branch_code, employee_address, position_code, employee_code,last_login, created_on, position_id, join_date, groups.name AS group_name, groups.description AS group_description, groups.id AS group_id, last_status, salary, salary_minimum, contract_number, status_work, status_work_expiration, overtime_hour_rate, account_name, account_bank, account_number, subdivision_id, subdivision_code, subdivision_name, npwp_number, ptkp_status,

            shift_cluster_id, cluster_code, cluster_name, shift_applies
            ')
         ->from('users')
         ->join('position', 'position.id = users.position_id')
         ->join('subdivision', 'subdivision.id = users.subdivision_id', 'LEFT')
         ->join('branch', 'branch.id = position.branch_id')
         ->join('users_groups', 'users_groups.user_id = users.id')
         ->join('groups', 'groups.id = users_groups.group_id')
         ->join('users_shift_cluster', 'users_shift_cluster.user_id = users.id', 'LEFT')
         ->join('shift_cluster', 'shift_cluster.id = users_shift_cluster.shift_cluster_id', 'LEFT')
         ->where($find);
      }

      $dt->style(array(
         'class' => 'table table-striped table-bordered',
          ))
         ->column('<b>NO</b>', 'num_dt users.id')

         ->column('<b>USER</b>', 'first_name', function($data, $row){

            if($row['shift_cluster_id'] == ''){
               $shift = '<span class="text-danger" data-toggle="tooltip" title="Jadwal Kerja"><i class="fa fa-calendar"></i> Belum dipilih</span>';
            }else{
               $shift = '<span data-toggle="tooltip" title="NIK"><i class="fa fa-briefcase"></i> '.$row['contract_number'].'</span>';
            }

            $txt = '<a href="javascript:void(0)" class="d-flex align-items-start">
                       <img class="d-flex me-3 rounded-circle" src="'.base_url('assets/images/users/'.$row['photo']).'" alt="Generic placeholder image" width="36" height="36">
                       <div class="flex-1 chat-user-box overflow-hidden">
                           <p class="user-title m-0">'.$row['first_name'].'</p>
                           <p class="text-muted">'."<span data-toggle='tooltip' title='Access Right'><i class='fa fa-user-circle'></i> ".$row['description']."</span> &nbsp; | &nbsp; <span data-toggle='tooltip' title='Tanggal mulai kerja'><i class='fa fa-clock'></i> ".indonesian_date($row['join_date'])."</span><br>
                           
                           ".$shift.'</p>
                       </div>
                   </a>';
            return $txt;
         })
         ->column('<b>ID FINGERPRINT</b>', 'employee_code')
         ->column('<b>POSISI / SUB DEPARTEMENT</b>', 'position_name', function($data, $row){
            $location = !empty($row['location']) ? htmlspecialchars($row['location'], ENT_QUOTES, 'UTF-8') : '';
            $note = $location !== '' ? "<br><small class='text-info'>Lokasi: ".$location."</small>" : '';
            return $row['position_name']."<br><small class='text-muted'>".$row['subdivision_name']."</small>".$note;
         })
         ->column('<b>EMAIL / TELP</b>', 'email', function($data, $row){
            return $row['email']."<br><small class='text-muted'>".$row['phone']."</small>";
         })
         ->column('<center><b>ACTIVE</b></center>', 'active', function($data, $row){
            if($row['active'] == '1'){
               $txt = '<i class="fa fa-check-circle text-success" data-toggle="tooltip" title="Aktif"></i>';
            }else{
               $txt = '<i class="fa fa-times-circle text-danger" data-toggle="tooltip" title="Nonaktif"></i>';
            }

            if($row['last_status'] != ''){
               $txt .= "<br><small class='text-muted'>".indonesian_date($row['last_status'], true)."<small>";
            }

            return "<center>".$txt."</center>";
         })
         ->column('<center><i class="fa fa-cog"></i></center>', 'active', function($data, $row){
            if($row['active'] == '1'){
               $icon = '<i class="dripicons-cross"></i> Non-aktifkan';
            }else{
               $icon = '<i class="dripicons-checkmark"></i> Aktifkan';
            }

            $location = !empty($row['location']) ? htmlspecialchars($row['location'], ENT_QUOTES, 'UTF-8') : '';
            $edit = 'data-id="'.$row['id'].'"
                     data-name="'.$row['first_name'].'"
                     data-code="'.$row['employee_code'].'"
                     data-email="'.$row['email'].'"
                     data-address="'.$row['employee_address'].'"
                     data-location="'.$location.'"
                     data-phone="'.$row['phone'].'"
                     data-position="'.$row['position_id'].'"
                     data-subdivision="'.$row['subdivision_id'].'"
                     data-access="'.$row['group_id'].'"
                     data-join-date="'.$row['join_date'].'"
                     data-salary="'.$row['salary'].'"
                     data-salary-minimum="'.$row['salary_minimum'].'"
                     data-contract-number="'.$row['contract_number'].'"
                     data-status-work="'.$row['status_work'].'"
                     data-status-work-date="'.$row['status_work_expiration'].'"
                     data-overtime-hour-rate="'.$row['overtime_hour_rate'].'"
                     data-account-number="'.$row['account_number'].'"
                     data-account-bank="'.$row['account_bank'].'"
                     data-account-name="'.$row['account_name'].'"
                     data-npwp-number="'.$row['npwp_number'].'"
                     data-ptkp-status="'.$row['ptkp_status'].'"
                     ';

            $role = $this->ion_auth->get_users_groups()->row()->name;
            if($role == 'admin'){
               $btnDelete = '<div class="dropdown-divider"></div>
                           <a data-id="'.$row['id'].'" href="javascript:void(0)" class="dropdown-item text-danger delete" data-bs-toggle="modal" data-bs-target="#modalDelete"><i class="dripicons-trash"></i> Hapus</a>';
            }else{
               $btnDelete = '';
            }

            //$schedule = '<a href="javascript:void(0)" data-cluster-id="'.$row['shift_cluster_id'].'" data-id="'.$row['id'].'" data-applies="'.$row['shift_applies'].'" class="dropdown-item shift"><i class="dripicons-calendar"></i> Ganti Jadwal Kerja</a>';
            $schedule = '';

            $comp = '<div class="dropdown dropstart mt-4 mt-sm-0">
                      <a href="#" class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                          Action <i class="fa fa-chevron-down"></i>
                      </a>

                      <div class="dropdown-menu">
                           <a class="dropdown-item approval" data-id="'.$row['id'].'" href="javascript:void(0)">'.$icon.'</a>
                           <a href="javascript:void(0)" '.$edit.' class="dropdown-item edit"><i class="dripicons-pencil"></i> Ubah Data Karyawan</a>
                           '.$schedule.'
                           '.$btnDelete.'
                      </div>
                  </div>';

            return $comp;
         });

      $this->datatables->create('tableContent', $dt);
   }


   public function get_tableNotification(){
      $dt = $this->datatables->init();

      $dt->select('content.id, content.content_header, content.content_description, content.featured_img, users_notification.notif_title, notif_type, is_read,
         DATE_FORMAT(notif_time, "%d %M %Y %H:%i") AS notif_time,
         DATE_FORMAT(read_time, "%d %M %Y %H:%i") AS read_time')
         ->from('users_notification')
         ->join('content', 'content.id = users_notification.content_id');

      $dt->style(array(
         'class' => 'table table-striped table-bordered',
          ))
         ->column('NO', 'num_dt content.id')
         ->column('USER', 'notif_title', function($data, $row){
            return '<div class="pb-3 border-bottom mb-3 media">
                        <img src="'.base_url('assets/img/content/'.$row['featured_img']).'" class="wid-60 rounded" alt="...">
                        <div class="media-body ml-3">
                           <h6>'.cut_by($row['content_header'], 20).'</h6>
                           <p class="mb-2">
                              '.cut_by($row['content_description'], 30).'
                           </p>
                        </div>
                     </div>';
         })
         ->column('STATUS', 'notif_title', function($data, $row){
            $icon = '';

            if($row['notif_type'] == 'success'){
               $icon = '<span class="text-success mt-2"><i data-feather="check-circle"></i> APPROVED';
            }else if($row['notif_type'] == 'danger'){
               $icon = '<span class="text-danger mt-2"><i data-feather="x-circle" class="text-danger"></i> REJECTED';
            }
            return $icon;
         })
         ->column('NOTIFICATION TIME', 'notif_time')
         ->column('READ TIME', 'read_time');

      $this->datatables->create('tableNotification', $dt);
   }
}

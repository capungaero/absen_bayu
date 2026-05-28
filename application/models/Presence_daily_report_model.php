<?php

Class Presence_daily_report_model extends CI_Model{

   protected $table = 'presence_daily_report';

   public function sync_by_rows($rows){
      $pairs = [];
      foreach($rows as $row){
         if(empty($row['user_id']) || empty($row['flow_date'])){ continue; }
         $pairs[] = [
            'user_id' => $row['user_id'],
            'flow_date' => $row['flow_date']
         ];
      }

      return $this->sync_by_pairs($pairs);
   }

   public function sync_by_pairs($pairs){
      $pairs = $this->_normalize_pairs($pairs);
      if(empty($pairs)){ return 0; }

      $user_ids = array_values(array_unique(array_column($pairs, 'user_id')));
      $dates = array_values(array_unique(array_column($pairs, 'flow_date')));
      $presence_rows = $this->_get_presence_rows($user_ids, $dates);
      $presence_index = [];

      foreach($presence_rows as $row){
         $presence_index[$row['user_id'].'|'.$row['flow_date']] = $row;
      }

      $affected = 0;
      foreach($pairs as $pair){
         $key = $pair['user_id'].'|'.$pair['flow_date'];
         if(isset($presence_index[$key])){
            $this->_upsert($presence_index[$key]);
         }else{
            $this->db->where([
               'user_id' => $pair['user_id'],
               'flow_date' => $pair['flow_date']
            ])->delete($this->table);
         }
         $affected++;
      }

      return $affected;
   }

   public function sync_period($branch_id, $from, $to){
      $rows = $this->_presence_base_query()
                  ->where('position.branch_id', $branch_id)
                  ->where('presence.flow_date >=', $from)
                  ->where('presence.flow_date <=', $to)
                  ->get('presence')
                  ->result_array();

      foreach($rows as $row){
         $this->_upsert($row);
      }

      return count($rows);
   }

   public function delete_period($branch_id, $from, $to){
      $this->db->where('branch_id', $branch_id)
               ->where('flow_date >=', $from)
               ->where('flow_date <=', $to)
               ->delete($this->table);

      return $this->db->affected_rows();
   }

   private function _normalize_pairs($pairs){
      $result = [];
      $seen = [];

      foreach($pairs as $pair){
         if(empty($pair['user_id']) || empty($pair['flow_date'])){ continue; }
         $key = $pair['user_id'].'|'.$pair['flow_date'];
         if(isset($seen[$key])){ continue; }
         $seen[$key] = true;
         $result[] = [
            'user_id' => $pair['user_id'],
            'flow_date' => $pair['flow_date']
         ];
      }

      return $result;
   }

   private function _get_presence_rows($user_ids, $dates){
      if(empty($user_ids) || empty($dates)){ return []; }

      return $this->_presence_base_query()
                  ->where_in('presence.user_id', $user_ids)
                  ->where_in('presence.flow_date', $dates)
                  ->get('presence')
                  ->result_array();
   }

   private function _presence_base_query(){
      return $this->db->select('presence.*, users.employee_code, users.first_name, users.last_name, users.subdivision_id, position.branch_id, position.position_name, branch.branch_name, subdivision.subdivision_name')
                      ->join('users', 'users.id = presence.user_id')
                      ->join('position', 'position.id = users.position_id')
                      ->join('branch', 'branch.id = position.branch_id')
                      ->join('subdivision', 'subdivision.id = users.subdivision_id', 'LEFT');
   }

   private function _upsert($row){
      $data = [
         'presence_id' => $row['id'],
         'user_id' => $row['user_id'],
         'employee_code' => $row['employee_code'],
         'employee_name' => trim($row['first_name'].' '.$row['last_name']),
         'branch_id' => $row['branch_id'],
         'branch_name' => $row['branch_name'],
         'division_id' => $row['subdivision_id'],
         'division_name' => $row['subdivision_name'],
         'position_name' => $row['position_name'],
         'flow_date' => $row['flow_date'],
         'entry_time' => $row['entry_time'],
         'entry_time_late' => (int)$row['entry_time_late'],
         'rest_time_in' => $row['rest_time_in'],
         'rest_time_out' => $row['rest_time_out'],
         'out_time' => $row['out_time'],
         'dzuhur_time_in' => $row['dzuhur_time_in'],
         'dzuhur_time_out' => $row['dzuhur_time_out'],
         'dzuhur_time_late' => (int)$row['dzuhur_time_late'],
         'ashar_time_in' => $row['ashar_time_in'],
         'ashar_time_out' => $row['ashar_time_out'],
         'ashar_time_late' => (int)$row['ashar_time_late'],
         'maghrib_time_in' => $row['maghrib_time_in'],
         'maghrib_time_out' => $row['maghrib_time_out'],
         'maghrib_time_late' => (int)$row['maghrib_time_late'],
         'isha_time_in' => $row['isha_time_in'],
         'isha_time_out' => $row['isha_time_out'],
         'isha_time_late' => (int)$row['isha_time_late'],
         'presence_type' => $row['presence_type'],
         'presence_status' => $row['presence_status'],
         'is_early_leave' => isset($row['is_early_leave']) ? (int)$row['is_early_leave'] : 0,
         'early_leave_short_minutes' => isset($row['early_leave_short_minutes']) ? (int)$row['early_leave_short_minutes'] : 0,
         'input_by' => $row['input_by'],
         'source_updated_at' => !empty($row['updated_at']) ? $row['updated_at'] : $row['created_at'],
         'updated_at' => date('Y-m-d H:i:s')
      ];

      $existing = $this->db->where([
         'user_id' => $row['user_id'],
         'flow_date' => $row['flow_date']
      ])->get($this->table)->row_array();

      if(empty($existing)){
         $data['created_at'] = date('Y-m-d H:i:s');
         $this->db->insert($this->table, $data);
      }else{
         $this->db->where('id', $existing['id'])->update($this->table, $data);
      }
   }
}

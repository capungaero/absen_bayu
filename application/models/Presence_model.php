<?php
 
Class Presence_model extends CI_Model{

   protected $table = 'presence';
   protected $attendance_cache = [];
   protected $additional_attendance_cache = [];
   protected $employee_cache = [];
   protected $rotation_cache = [];
   protected $branch_cache = [];
   protected $insentif_master_cache = [];
   protected $payroll_insentif_cache = [];
   protected $deduction_master_cache = [];
   protected $payroll_deduction_cache = [];
   protected $period_context = null;
    
   public function get_data(){
      $this->db->order_by('id', 'DESC');
      return $this->db->get($this->table)->result_array();
   }

   public function get_detail($key, $val = ''){
      $this->db->select('presence.*, users.employee_code, users.first_name, position_id, position.position_code, position.position_name, position.branch_id')
               ->join('users', 'users.id = presence.user_id')
               ->join('position', 'position.id = users.position_id')
               ->order_by('id', 'DESC');

      if(is_array($key)){
         $this->db->where($key);
      
      }else{
         $this->db->where($key, $val);
      }

      return $this->db->get($this->table);
   }

   public function _get_attendance($user_id, $month, $year){
      if(
        isset($this->period_context['attendance']) &&
        $this->period_context['month'] == $month &&
        $this->period_context['year'] == $year
      ){
         return isset($this->period_context['attendance'][$user_id]) ? $this->period_context['attendance'][$user_id] : [];
      }

      $cache_key = $user_id.'-'.$month.'-'.$year;
      if(isset($this->attendance_cache[$cache_key])){
         return $this->attendance_cache[$cache_key];
      }

      $raw  = strtotime($year."-".$month."-10 -1 months");
      $from = date('Y-m-'.START_PAYROLL_DATE, $raw);
      $to   = $year."-".$month."-".END_PAYROLL_DATE;

      $this->db->select('presence.*, overtime.id AS overtime_id, overtime_status, users.employee_code, users.first_name, position_id, position.position_code, position.position_name, position.branch_id')
               ->join('users', 'users.id = presence.user_id')
               ->join('position', 'position.id = users.position_id')
               ->join('overtime', 'overtime.user_id = '.$user_id.' AND overtime.overtime_date = presence.flow_date AND (overtime_status = "approve" OR overtime_status = "pending")', 'LEFT')
               ->where('presence_status', 'approved')
               ->where('presence.user_id', $user_id)
               ->where('flow_date >=', $from)
               ->where('flow_date <=', $to)
               ->order_by('flow_date', 'DESC');

      $this->attendance_cache[$cache_key] = $this->db->get($this->table)->result_array();
      return $this->attendance_cache[$cache_key];
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

  public function get_attendance_by_branch($branch_id, $month, $year, $overtime = false, $employee_access = false){
      $data = [];
      $current_now = date('Y-m-d');
      $total_day = 0;
      $month = strlen($month) == 1 ? "0".$month : $month;

      $raw  = strtotime($year."-".$month."-10 -1 months");
      $from = date('Y-m-'.START_PAYROLL_DATE, $raw);
      $to   = $year."-".$month."-".END_PAYROLL_DATE;

      $find = [
        'DATE(join_date) <=' => $to
      ];
      $order_by = [
        'subdivision_name' => 'ASC',
        'position_name'    => 'ASC',
        'users.first_name' => 'ASC'
      ];
      $employee = $this->_get_employee($branch_id, $employee_access ? 'user' : 'branch', $find, $order_by);
      $this->_prime_period_context($branch_id, $month, $year, $employee, $from, $to);

      $shift    = $this->db->order_by('shift_code', 'ASC')
                           ->where('branch_id', $branch_id)
                           ->get('shift')->result_array();
                           
      $branch_detail = $this->db->where('id', $branch_id)->get('branch')->row_array();
      $summary  = [];
      $pray_list = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];
      foreach ($shift as $row){
        $summary[$row['shift_code']] = [
          'code' => $row['shift_code'],
          'name' => $row['shift_name'],
          'start'=> $row['start_time'],
          'end'  => $row['end_time'],
          'num'  => 0,
          'day'  => []
        ];
      }

      foreach ($employee as $row){
        $rotation = $this->_get_rotation_by_cluster_id($row['shift_cluster_id']);

        $overtime_hour = 0;
        $workday = [];
        $additional_att = [];
        $total_work = 0;
        $total_day = 0;
        $total_off = 0;
        $additional = $this->get_additional_attendance($row['id'], $month, $year);
        $fine = $this->get_fine($row['id'], $month, $year);
        

        foreach ($additional as $adt){
          $total_day++;
          $total_work += $adt['additional_type'] == 'work' ? 1 : 0;
          $total_off += $adt['additional_type'] == 'free' ? 1 : 0;

          $additional_att[$adt['additional_date']] = [
            'shift_id'       => $adt['shift_id'],
            'additional_date' => $adt['additional_date'],
            'type'           => $adt['additional_type'],
            'code'           => $adt['shift_code'],
            'name'           => $adt['shift_name'],
            'start_time'     => $adt['start_time'],
            'end_time'       => $adt['end_time'],
            'start_time_in'  => $adt['start_time_in'],
            'start_time_out' => $adt['start_time_out'],
            'start_time_rest' => $adt['start_time_rest'],
            'end_time_rest'   => $adt['end_time_rest'],
            'end_time_in'    => $adt['end_time_in'],
            'end_time_out'   => $adt['end_time_out'], 
            'rest_time_range'   => $adt['rest_time_range'],
            'late_amount_start' => $adt['late_amount_start'],
            'late_amount_multiple_start' => $adt['late_amount_multiple_start'],
            'late_multiple_count_start'  => $adt['late_multiple_count_start'],
            'late_amount_rest'          => $adt['late_amount_rest'],
            'late_amount_multiple_rest' => $adt['late_amount_multiple_rest'],
            'late_multiple_count_rest'  => $adt['late_multiple_count_rest'],
            'late_fix_rate_rest'        => $adt['late_amount_max_rest'],
            'late_fix_rate_start'       => $adt['late_amount_max_start'],
            'late_fix_rate_pray'        => $branch_detail['pray_late_fix_rate'],
            'late_multiple_count_pray'  => $branch_detail['pray_late_multiple_count'],
            'late_amount_pray'          => $branch_detail['pray_late_start_rate'],
            'late_amount_multiple_pray' => $branch_detail['pray_late_multiple_rate']  
          ];
        }

        $attendance = [];
        $presence = $this->_get_attendance($row['id'], $month, $year);
        $salary_per_day = $total_work > 0 ? $row['salary'] / $total_day : 0;

        foreach ($presence as $att){
          $att_date = $att['flow_date'];

          $attendance[$att_date] = [
            'presence_id' => $att['id'],
            'user_id'     => $att['user_id'],
            'date'        => $att_date,
            'entry_time'  => ($att['entry_time'] != '') ? date('H:i', strtotime($att['entry_time'])) : '',
            'out_time'     => ($att['out_time'] != '') ? date('H:i', strtotime($att['out_time'])) : '',
            'entry_time_late' => $att['entry_time_late'],
            'rest_time_in' => ($att['rest_time_in'] != '') ? date('H:i', strtotime($att['rest_time_in'])) : '',
            'rest_time_out' => ($att['rest_time_out'] != '') ? date('H:i', strtotime($att['rest_time_out'])) : '',
            'rest_time_late'  => $att['rest_time_late'],
            'presence_type'   => $att['presence_type'],
            'presence_get_paid' => $att['presence_get_paid'],
            'presence_type' => $att['presence_type'],
            'input_by'    => $att['input_by'],
            'created_at'  => $att['created_at'],
            'by_user_id'  => $att['input_by_user_id'],
            'is_overtime' => $att['is_overtime'],
            'is_overtime_presence' => $att['overtime_id'] ? true : false,
            'is_overtime_approve'  => $att['overtime_status'] == 'approve' ? true : false
          ];

          /**if($att['presence_type'] != 'normal'){
            continue;
          }**/

          $in  = $attendance[$att_date];
          $adt = $this->_normalize_additional_attendance_row(isset($additional_att[$att_date]) ? $additional_att[$att_date] : []);

          if($att['presence_type'] == 'normal'){
            if($att['is_overtime'] == '1'){
              $overtime_hour += differenceInHours($adt['start_time'], $adt['end_time']);
            }

            foreach ($pray_list as $key){
              $attendance[$att_date][$key."_time_in"] = ($att[$key.'_time_in'] != '') ? date('H:i', strtotime($att[$key.'_time_in'])) : '';
              $attendance[$att_date][$key."_time_out"] = ($att[$key.'_time_out'] != '') ? date('H:i', strtotime($att[$key.'_time_out'])) : '';
              $attendance[$att_date][$key."_time_late"] = $att[$key.'_time_late'];
            }
          }
          
        }

        $daterange = getRangeWorkDate($month, $year);

        foreach ($daterange['list'] as $day){
          $present = false;
          $half    = '';
          $by      = '';
          $presence_type = '';

          if(isset($attendance[$day]['presence_id'])){
            if(($attendance[$day]['out_time'] != '' && $attendance[$day]['entry_time']) || $attendance[$day]['presence_type'] != 'normal'){
              $present = true;
            }else{
              $half = ($attendance[$day]['entry_time'] == '') ? $attendance[$day]['out_time'] : $attendance[$day]['entry_time'];
            }

            $by = $attendance[$day]['input_by'];

            if($attendance[$day]['presence_type'] != 'normal'){
              $presence_type = $attendance[$day]['presence_type'];
            }
          }

          $code = 'free';
          $name = $start_time = $end_time = $start_time_in = $start_time_out = $end_time_in = $end_time_out = $start_time_rest = $end_time_rest = $rest_time_range = '';

          if(isset($additional_att[$day])){
            $att = $additional_att[$day];
            $code           = $att['code'] == '' ? 'free' : $att['code'];
            $name           = $att['name'];
            $start_time     = $att['start_time'];
            $end_time       = $att['end_time'];
            $start_time_in  = $att['start_time_in'];
            $start_time_out = $att['start_time_out'];
            $end_time_in    = $att['end_time_in'];
            $end_time_out   = $att['end_time_out'];
            $start_time_rest = $att['start_time_rest'];
            $end_time_rest   = $att['end_time_rest'];
            $rest_time_range   = $att['rest_time_range'];

            if(
                $att['type'] == 'work' && 
                !isset($attendance[$att['additional_date']]) && 
                in_array(get_dayname($att['additional_date']), ['Sabtu', 'Minggu']) &&
                strtotime($current_now) >= strtotime($att['additional_date']) &&
                $additional_att[$att['additional_date']]['code'] != '-'
              ){
                //$weekend_fine = $salary_per_day * 2;
                //$fine += $weekend_fine;
              }
          }

          $overtime_presence = false;
          $overtime_approve = false;
          if(isset($attendance[$day])){
            $overtime_presence = $attendance[$day]['is_overtime_presence'] ? true : false;
            $overtime_approve = $attendance[$day]['is_overtime_approve'] ? true : false;
          }

          $workday[] = [
            'date' => $day,
            'code' => $code,
            'name' => $name,
            'start_time' => date('H:i', strtotime($start_time ?? '')),
            'start_time_in' => date('H:i', strtotime($start_time_in ?? '')),
            'start_time_out' => date('H:i', strtotime($start_time_out ?? '')),
            'end_time'   => date('H:i', strtotime($end_time ?? '')),
            'end_time_in' => date('H:i', strtotime($end_time_in ?? '')),
            'end_time_out' => date('H:i', strtotime($end_time_out ?? '')),
            'start_time_rest' => date('H:i', strtotime($start_time_rest ?? '')),
            'end_time_rest' => date('H:i', strtotime($end_time_rest ?? '')),
            'rest_time_range' => $rest_time_range,
            'type'       => $code == "free" ? "free" : "work",
            'present' => [
              'id'     => isset($attendance[$day]) != '' ? $attendance[$day]['presence_id'] : '',
              'is_overtime'=> isset($attendance[$day]) != '' ? $attendance[$day]['is_overtime'] : '0',
              'is_overtime_presence' => $overtime_presence,
              'is_overtime_approve' => $overtime_approve,
              'status' => $present,
              'half'   => $half,
              'by'     => $by,
              'presence_type' => $presence_type,
              'detail' => (isset($attendance[$day])) ? $attendance[$day] : []
            ]
          ];
        }

        $data_overtime = [];
        if($overtime){
          $total_hour = $this->db->select('SUM(
                          CASE WHEN overtime_hour > '.$row['max_overtime'].' 
                               THEN '.$row['max_overtime'].' ELSE overtime_hour
                          END
                        ) AS total_hour')
                       ->where('overtime_status', 'approve')
                       ->where('user_id', $row['id'])
                       ->where('overtime_date >=', $from)
                       ->where('overtime_date <=', $to)
                       ->get('overtime')->row_array()['total_hour'] + $overtime_hour;

          $total_hour = round($total_hour, 2);

          $data_overtime = [
            'total_hour' => $total_hour,
            'amount'     => round($total_hour * $row['overtime_hour_rate'])
          ];
        }



        $data[] = [
          'employee' => [
            'id'       => $row['id'],
            'code'     => $row['employee_code'],
            'contract_number' => $row['contract_number'],
            'name'     => $row['first_name'],
            'position' => $row['position_name'],
            'subdivision' => $row['subdivision_name'],
            'overtime_rate' => $row['overtime_hour_rate'],
            'salary'   => $row['salary'],
            'salary_minimum' => $row['salary_minimum'],
            'applies'  => $row['shift_applies'],
            'account_number' => $row['account_number'],
            'account_name'   => $row['account_name'],
            'account_bank'   => $row['account_bank']
          ],
          'total_day'=> count($daterange['list']),
          'workday'  => $workday,
          'add_att'  => $additional,
          'overtime' => $data_overtime,
          'insentif' => $this->get_insentif($row['id'], $branch_id, $month, $year, $presence),
          'deduction'=> $this->get_deduction($row['id'], $branch_id, $month, $year),
          'fine'     => $fine['amount'],
          'alpha_weekdays_amount' => $fine['detail']['entry']['amount_in_weekdays'],
          'fine_detail' => $fine
        ];

      }

      return [
        'list'  => $data,
        'shift' => $summary
      ];
  }

  private function _prime_period_context($branch_id, $month, $year, $employee, $from, $to){
      $this->period_context = [
        'branch_id' => $branch_id,
        'month' => $month,
        'year' => $year,
        'from' => $from,
        'to' => $to,
        'additional' => [],
        'attendance' => []
      ];

      $user_ids = array_column($employee, 'id');
      if(empty($user_ids)){
        return;
      }

      $additional_rows = $this->db->select('
                        users_shift_additional.*,
                        shift.*,
                        COALESCE(shift.shift_code, "") AS shift_code,
                        COALESCE(shift.shift_name, "") AS shift_name,
                        COALESCE(shift.start_time, "00:00:00") AS start_time,
                        COALESCE(shift.end_time, "00:00:00") AS end_time,
                        COALESCE(shift.start_time_in, "00:00:00") AS start_time_in,
                        COALESCE(shift.start_time_out, "00:00:00") AS start_time_out,
                        COALESCE(shift.start_time_rest, "00:00:00") AS start_time_rest,
                        COALESCE(shift.end_time_rest, "00:00:00") AS end_time_rest,
                        COALESCE(shift.end_time_in, "00:00:00") AS end_time_in,
                        COALESCE(shift.end_time_out, "00:00:00") AS end_time_out,
                        COALESCE(shift.rest_time_range, 0) AS rest_time_range,
                        COALESCE(shift.late_amount_start, 0) AS late_amount_start,
                        COALESCE(shift.late_amount_multiple_start, 0) AS late_amount_multiple_start,
                        COALESCE(shift.late_multiple_count_start, 0) AS late_multiple_count_start,
                        COALESCE(shift.late_amount_rest, 0) AS late_amount_rest,
                        COALESCE(shift.late_amount_multiple_rest, 0) AS late_amount_multiple_rest,
                        COALESCE(shift.late_multiple_count_rest, 0) AS late_multiple_count_rest,
                        COALESCE(shift.late_amount_max_rest, 0) AS late_amount_max_rest,
                        COALESCE(shift.late_amount_max_start, 0) AS late_amount_max_start
                      ', false)
                      ->join('shift', 'shift.id = users_shift_additional.shift_id', 'LEFT')
                      ->where_in('users_shift_additional.user_id', $user_ids)
                      ->where('additional_date >=', $from)
                      ->where('additional_date <=', $to)
                      ->where(latest_schedule_subquery(), null, false)
                      ->get('users_shift_additional')
                      ->result_array();

      foreach($additional_rows as $row){
        $this->period_context['additional'][$row['user_id']][] = $row;
      }

      $attendance_rows = $this->db->select('presence.*, overtime.id AS overtime_id, overtime_status, users.employee_code, users.first_name, position_id, position.position_code, position.position_name, position.branch_id')
               ->join('users', 'users.id = presence.user_id')
               ->join('position', 'position.id = users.position_id')
               ->join('overtime', 'overtime.user_id = presence.user_id AND overtime.overtime_date = presence.flow_date AND (overtime_status = "approve" OR overtime_status = "pending")', 'LEFT')
               ->where('presence_status', 'approved')
               ->where_in('presence.user_id', $user_ids)
               ->where('flow_date >=', $from)
               ->where('flow_date <=', $to)
               ->order_by('flow_date', 'DESC')
               ->get($this->table)
               ->result_array();

      foreach($attendance_rows as $row){
        $this->period_context['attendance'][$row['user_id']][] = $row;
      }
  }

  public function reset_additional($month, $year, $branch_id){
    $raw  = strtotime($year."-".$month."-10 -1 months");
    $from = date('Y-m-'.START_PAYROLL_DATE, $raw);
    $to   = $year."-".$month."-".END_PAYROLL_DATE;

    $this->db->query('DELETE FROM `users_shift_additional` 
                      WHERE additional_date BETWEEN "'.$from.'" AND "'.$to.'" AND 
                            user_id IN(
                              SELECT(users.id) FROM users 
                              JOIN POSITION ON position.id = users.position_id 
                              WHERE position.branch_id = '.$branch_id.'
                            )
                    ');

    if($this->db->affected_rows() > 0){
      return true;
    }
    return false;
  }

  private function _normalize_additional_attendance_row($row){
      return array_merge([
        'shift_id' => null,
        'additional_date' => null,
        'type' => 'free',
        'code' => '',
        'name' => '',
        'start_time' => '00:00:00',
        'end_time' => '00:00:00',
        'start_time_in' => '00:00:00',
        'start_time_out' => '00:00:00',
        'start_time_rest' => '00:00:00',
        'end_time_rest' => '00:00:00',
        'end_time_in' => '00:00:00',
        'end_time_out' => '00:00:00',
        'rest_time_range' => 0,
        'late_amount_start' => 0,
        'late_amount_multiple_start' => 0,
        'late_multiple_count_start' => 0,
        'late_amount_rest' => 0,
        'late_amount_multiple_rest' => 0,
        'late_multiple_count_rest' => 0,
        'late_fix_rate_rest' => 0,
        'late_fix_rate_start' => 0,
        'late_fix_rate_pray' => 0,
        'late_multiple_count_pray' => 0,
        'late_amount_pray' => 0,
        'late_amount_multiple_pray' => 0
      ], $row);
  }

  public function get_additional_attendance($user_id, $month, $year){
      if(
        isset($this->period_context['additional']) &&
        $this->period_context['month'] == $month &&
        $this->period_context['year'] == $year
      ){
        return isset($this->period_context['additional'][$user_id]) ? $this->period_context['additional'][$user_id] : [];
      }

      $cache_key = $user_id.'-'.$month.'-'.$year;
      if(isset($this->additional_attendance_cache[$cache_key])){
        return $this->additional_attendance_cache[$cache_key];
      }

      $raw  = strtotime($year."-".$month."-10 -1 months");
      $from = date('Y-m-'.START_PAYROLL_DATE, $raw);
      $to   = $year."-".$month."-".END_PAYROLL_DATE;

      $this->additional_attendance_cache[$cache_key] = $this->db->select('
                        users_shift_additional.*,
                        shift.*,
                        COALESCE(shift.shift_code, "") AS shift_code,
                        COALESCE(shift.shift_name, "") AS shift_name,
                        COALESCE(shift.start_time, "00:00:00") AS start_time,
                        COALESCE(shift.end_time, "00:00:00") AS end_time,
                        COALESCE(shift.start_time_in, "00:00:00") AS start_time_in,
                        COALESCE(shift.start_time_out, "00:00:00") AS start_time_out,
                        COALESCE(shift.start_time_rest, "00:00:00") AS start_time_rest,
                        COALESCE(shift.end_time_rest, "00:00:00") AS end_time_rest,
                        COALESCE(shift.end_time_in, "00:00:00") AS end_time_in,
                        COALESCE(shift.end_time_out, "00:00:00") AS end_time_out,
                        COALESCE(shift.rest_time_range, 0) AS rest_time_range,
                        COALESCE(shift.late_amount_start, 0) AS late_amount_start,
                        COALESCE(shift.late_amount_multiple_start, 0) AS late_amount_multiple_start,
                        COALESCE(shift.late_multiple_count_start, 0) AS late_multiple_count_start,
                        COALESCE(shift.late_amount_rest, 0) AS late_amount_rest,
                        COALESCE(shift.late_amount_multiple_rest, 0) AS late_amount_multiple_rest,
                        COALESCE(shift.late_multiple_count_rest, 0) AS late_multiple_count_rest,
                        COALESCE(shift.late_amount_max_rest, 0) AS late_amount_max_rest,
                        COALESCE(shift.late_amount_max_start, 0) AS late_amount_max_start
                      ', false)
                      ->join('shift', 'shift.id = users_shift_additional.shift_id', 'LEFT')
                      ->where('users_shift_additional.user_id', $user_id)
                      ->where('additional_date >=', $from)
                      ->where('additional_date <=', $to)
                      ->where(latest_schedule_subquery(), null, false)
                      ->get('users_shift_additional')
                      ->result_array();

      return $this->additional_attendance_cache[$cache_key];
  }


  public function check_available_attendance($user_id, $date_search, $time, $set_time = true, $allow_early_out = false){
      $status = false;
      $work = $this->db->where([
                          'user_id' => $user_id,
                          'additional_date' => $date_search
                        ])
                       ->join('shift', 'shift.id = users_shift_additional.shift_id')
                       ->get('users_shift_additional')->row_array();

      if(!empty($work)){
        $s1 = $s2 = $s3 = $s4 = false;

        if($time['entry'] != ''){
          if(strtotime($time['entry']) >= strtotime($work['start_time_in']) &&
             strtotime($time['entry']) <= strtotime($work['start_time_out'])){
            $s1 = true;
          }

        }else{
          $s1 = true;
        }

        if($time['out'] != ''){
          if($allow_early_out){
            // Izin Pulang Cepat: out_time boleh < end_time_in selama masih
            // setelah entry_time. Window normal end_time_in/out tidak dipakai.
            $entry_ts = $time['entry'] != '' ? strtotime($time['entry']) : 0;
            $s2 = strtotime($time['out']) > $entry_ts;
          }else if(strtotime($time['out']) >= strtotime($work['end_time_in']) &&
             strtotime($time['out']) <= strtotime($work['end_time_out'])){
            $s2 = true;
          }

        }else{
          $s2 = true;
        }

        if($time['rest_in'] != ''){
          if(strtotime($time['rest_in']) >= strtotime($work['start_time_rest']) && 
             strtotime($time['rest_in']) <= strtotime($work['end_time_rest'])){
            $s3 = true;
          }

        }else{
          $s3 = true;
        }

        if($time['rest_out'] != ''){
          if(strtotime($time['rest_out']) >= strtotime($work['start_time_rest']) && 
             strtotime($time['rest_out']) <= strtotime($work['end_time_rest'])){
            $s4 = true;
          }

        }else{
          $s4 = true;
        }

        $status = $s1 && $s2 && $s3 && $s4 ? true : false;
      }

      return $status;
  }

  public function get_fine($employee_id, $month, $year){
    $current_now = date('Y-m-d');
    $pray_raw   = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];
    $additional = $this->get_additional_attendance($employee_id, $month, $year);
    $employee   = $this->_get_employee($employee_id, 'employee')[0];
    if(!isset($this->branch_cache[$employee['branch_id']])){
      $this->branch_cache[$employee['branch_id']] = $this->db->where('id', $employee['branch_id'])->get('branch')->row_array();
    }
    $branch_detail = $this->branch_cache[$employee['branch_id']];
    $total_work = 0;
    $total_off = 0;
    $total_work_without_strip = 0;
    $total_day = 0;
    $strip = 0;
    $totalDayInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    $daterange = getRangeWorkDate($month, $year);
    foreach ($daterange['list'] as $row){
      $daterange_list[$row] = $row;
      $total_day++;
    }

    foreach ($additional as $adt){
      $total_work += $adt['additional_type'] == 'work' ? 1 : 0;
      $total_off += $adt['additional_type'] == 'free' ? 1 : 0;
      $total_work_without_strip += ($adt['additional_type'] == 'work' && $adt['shift_code'] != '-') ? 1 : 0;

      if(isset($daterange_list[$adt['additional_date']])){
        unset($daterange_list[$adt['additional_date']]);
      }

      if($adt['shift_code'] == '-'){
        $strip++;
      }

      $additional_att[$adt['additional_date']] = [
        'shift_id'        => $adt['shift_id'],
        'additional_date' => $adt['additional_date'],
        'type'            => $adt['additional_type'],
        'code'            => $adt['shift_code'],
        'name'            => $adt['shift_name'],
        'start_time'      => $adt['start_time'],
        'end_time'        => $adt['end_time'],
        'start_time_in'   => $adt['start_time_in'],
        'start_time_out'  => $adt['start_time_out'],
        'start_time_rest' => $adt['start_time_rest'],
        'end_time_rest'   => $adt['end_time_rest'],
        'end_time_in'     => $adt['end_time_in'],
        'end_time_out'    => $adt['end_time_out'], 
        'rest_time_range'   => $adt['rest_time_range'],
        'late_amount_start' => $adt['late_amount_start'],
        'late_amount_multiple_start' => $adt['late_amount_multiple_start'],
        'late_multiple_count_start'  => $adt['late_multiple_count_start'],
        'late_amount_rest'          => $adt['late_amount_rest'],
        'late_amount_multiple_rest' => $adt['late_amount_multiple_rest'],
        'late_multiple_count_rest'  => $adt['late_multiple_count_rest'],
        'late_fix_rate_rest'        => $adt['late_amount_max_rest'],
        'late_fix_rate_start'       => $adt['late_amount_max_start'],
        'late_fix_rate_pray'        => $branch_detail['pray_late_fix_rate'],
        'late_multiple_count_pray'  => $branch_detail['pray_late_multiple_count'],
        'late_amount_pray'          => $branch_detail['pray_late_start_rate'],
        'late_amount_multiple_pray'        => $branch_detail['pray_late_multiple_rate']     
      ];
    }

    $attendance = [];
    $presence   = $this->_get_attendance($employee_id, $month, $year);
    $salaryPerDayForAlpha = $total_work > 0 ? round($employee['salary'] / $totalDayInMonth) : 0;
    $salary_per_day = $total_work > 0 ? ($employee['salary'] / $total_day) : 0;
    $fine = 0;

    $entry = [
      'presence' => [
        'count'    => 0,
        'max'      => $total_work_without_strip,
        'max_for_generate' => $total_day,
        'strip'    => $strip,
        'full'    => [
          'count'   => 0,
          'on_time' => 0,
          'late'    => 0
        ],
        'off'     => 0,
        'half'    => 0,
        'weekend' => 0,
        'weekdays' => 0,
        'leave'   => [
          'count' => 0,
          'izin'  => 0,
          'sakit' => 0,
          'cuti'  => 0
        ]
      ],
      'total_in_minute'  => 0,
      'amount_in_late'   => 0,
      'amount_in_half'   => 0,
      'amount_in_weekend'=> 0,
      'amount_in_weekdays' => 0,
      'day' => [
        'late' => [],
        'half' => [],
        'weekend' => []
      ]
    ];

    $leave = [
      'total_amount' => 0,
      'type' => [
        'izin'  => [
          'total_amount' => 0,
          'day'    => []
        ],
        'sakit' => [
          'total_amount' => 0,
          'day' => []
        ],
        'cuti'  => [
          'total_amount' => 0,
          'day' => []
        ]
      ]
    ];

    foreach ($pray_raw as $row){
      $pray_list[$row] = [
        'total' => [
          'in_minute' => 0,
          'amount'    => 0,
          'count'     => 0,
          'on_time'   => 0,
          'late'      => 0
        ],
        'late' => []
      ];
    }

    $prayer = [
      'amount' => 0,
      'detail' => $pray_list
    ];

    $rest = [
      'total' => [
        'in_minute' => 0,
        'in_half'   => 0,
        'in_fine'   => 0
      ],
      'late' => []
    ];

    foreach ($presence as $att){
      $att_date = $att['flow_date'];

      $attendance[$att_date] = [
        'presence_id' => $att['id'],
        'user_id'     => $att['user_id'],
        'date'        => $att_date,
        'entry_time'  => ($att['entry_time'] != '') ? date('H:i', strtotime($att['entry_time'])) : '',
        'out_time'     => ($att['out_time'] != '') ? date('H:i', strtotime($att['out_time'])) : '',
        'entry_time_late' => $att['entry_time_late'],
        'rest_time_in' => ($att['rest_time_in'] != '') ? date('H:i', strtotime($att['rest_time_in'])) : '',
        'rest_time_out' => ($att['rest_time_out'] != '') ? date('H:i', strtotime($att['rest_time_out'])) : '',
        'rest_time_late'  => $att['rest_time_late'],
        'presence_type'   => $att['presence_type'],
        'presence_get_paid' => $att['presence_get_paid'],
        'input_by'    => $att['input_by'],
        'created_at'  => $att['created_at'],
        'by_user_id'  => $att['input_by_user_id'],
        'is_overtime' => $att['is_overtime']
      ];

      $in  = $attendance[$att_date];
      $adt = $this->_normalize_additional_attendance_row(isset($additional_att[$att_date]) ? $additional_att[$att_date] : []);

      //if(!isset($additional_att[$att_date])){
      //  echo $att['user_id']."<br>";
      //}

      //PRAY FINE & COUNT
      foreach ($pray_list as $key => $val){
        $attendance[$att_date][$key."_time_in"] = ($att[$key.'_time_in'] != '') ? date('H:i', strtotime($att[$key.'_time_in'])) : '';
        $attendance[$att_date][$key."_time_out"] = ($att[$key.'_time_out'] != '') ? date('H:i', strtotime($att[$key.'_time_out'])) : '';
        $attendance[$att_date][$key."_time_late"] = $att[$key.'_time_late'];

        $pray_late = $attendance[$att_date][$key."_time_late"];
        $pray_in   = $attendance[$att_date][$key."_time_in"];
        $pray_out  = $attendance[$att_date][$key."_time_out"];
        $pray_fine = 0;

        if($employee['is_pray_system'] == '1'){
          $out_late = $attendance[$att_date][$key."_time_late"];
          $pray_max_fine = isset($adt['late_fix_rate_pray']) ? $adt['late_fix_rate_pray'] : 0;
          
          if($pray_late > 0){
            if(isset($adt['late_amount_multiple_pray'])){
              $pray_fine += $adt['late_amount_pray'];
              //echo $att['user_id']." [".$in['date']."] : ".$pray_late." = ".$pray_in." - ".$pray_out." --> b- | a-".$pray_fine."<br>";

              if($pray_late > $adt['late_multiple_count_pray']){
                $pray_late -= $adt['late_multiple_count_pray'];
                (int)$count = $pray_late / $adt['late_multiple_count_pray'];

                $pray_fine += $count * $adt['late_amount_multiple_pray'];
                if($pray_late % $adt['late_multiple_count_pray'] > 0){
                  $pray_fine += $adt['late_amount_multiple_pray'];
                }
              }
            }

            //$test_fine = $pray_fine;
            $pray_fine = $pray_fine > $pray_max_fine ? $pray_max_fine : $pray_fine;

          }else if($pray_in != '' && $pray_out == ''){
            $pray_fine += $pray_max_fine;
          }

          $fine += $pray_fine;
          $prayer['amount'] += round($pray_fine);
          $prayer['detail'][$key]['total']['amount'] += round($pray_fine);
          $prayer['detail'][$key]['total']['in_minute'] += $out_late;
          if($pray_in != ''){
            $prayer['detail'][$key]['total']['count']++;

            if($pray_out != '' && $out_late == 0){
              $prayer['detail'][$key]['total']['on_time']++;
            }else{
              $prayer['detail'][$key]['total']['late']++;
              $prayer['detail'][$key]['late'][] = [
                'date'      => $att_date,
                'half'      => $out_late > 0 ? false : true,
                'in_minute' => $out_late,
                'amount'    => round($pray_fine)
              ];
            }
          }
        }
      }

      //PRESENCE COUNT
      if($in['entry_time'] != '' || $in['out_time'] != '' || $in['presence_type'] != ''){

        if($in['presence_type'] == 'normal'){

          if($in['entry_time'] != '' && $in['out_time'] != ''){
            $entry['presence']['full']['count']++;
            $entry['presence']['count']++;

            if($in['entry_time_late'] > 0){
              $entry['presence']['full']['late']++;
              $entry['total_in_minute'] = $in['entry_time_late'];
            }else{
              $entry['presence']['full']['on_time']++;
            }

          }

          if(($in['entry_time'] != '' && $in['out_time'] == '') ||
             ($in['entry_time'] == '' && $in['out_time'] != '')){
            $entry['presence']['half']++;
            $entry['presence']['count']++;
            $entry['presence']['full']['count']++;
          }

        }else{
          $entry['presence']['full']['count']++;
          $entry['presence']['count']++;
          $entry['presence']['leave']['count']++;
          $entry['presence']['leave'][$in['presence_type']]++;
        }
      }

      //PRESENCE FINE
      $presence_fine = $presence_half = 0;
      $entry_max_fine = isset($adt['late_fix_rate_start']) ? $adt['late_fix_rate_start'] : 0;
      if($employee['is_fine_system'] == '1' && $in['presence_type'] == 'normal'){
  
        if($in['entry_time_late'] > 0){
          $entry['total_in_minute'] += $in['entry_time_late'];
          $presence_fine += $adt['late_amount_start'];

          if($in['entry_time_late'] > $adt['late_multiple_count_start']){
            $in['entry_time_late'] -= $adt['late_multiple_count_start'];

            if($adt['late_multiple_count_start'] > 0){
              $count = (int)($in['entry_time_late'] / $adt['late_multiple_count_start']);
            }else{
              $count = 0;
            }

            $presence_fine += $count * $adt['late_amount_multiple_start'];

            if($adt['late_multiple_count_start'] > 0){
              if($in['entry_time_late'] % $adt['late_multiple_count_start'] > 0){
                $presence_fine += $adt['late_amount_multiple_start'];
              }
            }
            
          }

          $presence_fine = $presence_fine > $entry_max_fine ? $entry_max_fine : $presence_fine;
          $entry['day']['late'][] = [
            'date'      => $att_date,
            'in_minute' => $attendance[$att_date]['entry_time_late'],
            'amount'    => round($presence_fine)
          ];
        }

        if(($in['entry_time'] != '' && $in['out_time'] == '') ||
           ($in['entry_time'] == '' && $in['out_time'] != '')){
          $presence_half = round($salary_per_day / 2);
          $entry['day']['half'][] = [
            'date'      => $att_date,
            'in_minute' => $attendance[$att_date]['entry_time_late'],
            'amount'    => $presence_half
          ];
        }

        $fine += $presence_fine;
        $fine += $presence_half;
        $entry['amount_in_late'] += $presence_fine;
        $entry['amount_in_half'] += $presence_half;

        //REST TIME FINE
        $rest_fine = 0; $rest_late = 0;
        $rest_max_fine = isset($adt['late_fix_rate_rest']) ? $adt['late_fix_rate_rest'] : 0;
        if($in['rest_time_late'] > 0){
          $rest_fine += $adt['late_amount_rest'];
          $rest_late  = $in['rest_time_late'];

          if($in['rest_time_late'] > $adt['late_multiple_count_rest']){
            $rest_late -= $adt['late_multiple_count_rest'];
            
            if($adt['late_multiple_count_rest'] > 0){
              $count = (int)($rest_late / $adt['late_multiple_count_rest']);
            }else{
              $count = 0;
            }

            $rest_fine += $count * $adt['late_amount_multiple_rest'];

            if($adt['late_multiple_count_rest'] > 0){
              if($rest_late % $adt['late_multiple_count_rest'] > 0){
                $rest_fine += $adt['late_amount_multiple_rest'];
              }
            }
            
          }

          $rest_fine = $rest_fine > $rest_max_fine ? $rest_max_fine : $rest_fine;
          $rest['late'][] = [
            'date'      => $att_date,
            'half'      => false,
            'in_minute' => $attendance[$att_date]['rest_time_late'],
            'amount'    => round($rest_fine),
          ];

        }else if($in['rest_time_in'] != '' && $in['rest_time_out'] == ''){
          $rest_fine += $adt['late_fix_rate_rest'];
          $rest['late'][] = [
            'date'      => $att_date,
            'half'      => true,
            'in_minute' => $attendance[$att_date]['rest_time_late'],
            'amount'    => $adt['late_fix_rate_rest'],
            'in'        => $attendance[$att_date]['rest_time_in']
          ];
        }

        $fine += $rest_fine;
        $rest['total']['in_fine'] += $rest_fine;
      }

      ## LEAVE FINE
      ## Aturan: presence_type='sakit' selalu potongan 0% (di-skip dari
      ## perhitungan fine). Keterangan sakit di-enforce saat insert leave
      ## (leave_reason required) + di approval (Leave::change_status set
      ## presence_get_paid=100 untuk sakit).
      $leave_fine = 0;
      if($employee['is_fine_system'] == '1'
          && $in['presence_type'] != 'normal'
          && $in['presence_type'] != 'sakit'){
        $fine_percent_leave = 100 - $in['presence_get_paid'];
        if($fine_percent_leave > 0){
          $amount_day_fine = round(($fine_percent_leave / 100) * $salary_per_day);
          $count = 1;
          if(in_array(get_dayname($att_date),['Sabtu', 'Minggu'])){
            $amount_day_fine = $amount_day_fine * 2;
            $count = 2;
          }

          $leave_fine += $amount_day_fine;

          $leave['total_amount'] += $amount_day_fine;
          $leave['type'][$in['presence_type']]['total_amount'] += $amount_day_fine;
          $leave['type'][$in['presence_type']]['day'][] = [
            'date' => $att_date,
            'percent' => $fine_percent_leave,
            'count'    => $count,
            'amount' => $amount_day_fine
          ];
        }

        $fine += $leave_fine;
      }

    }

    // COUNT AND CALCULATE WEEKEND FINE
    if(isset($additional_att)){
      foreach($additional_att as $row){
        if(
          $row['type'] == 'work' && 
          !isset($attendance[$row['additional_date']]) && 
          in_array(get_dayname($row['additional_date']), ['Sabtu', 'Minggu']) &&
          strtotime($current_now) >= strtotime($row['additional_date']) &&
          $row['code'] != '-'
        ){
          $weekend_fine = $salaryPerDayForAlpha * 2;
          $entry['day']['weekend'][] = [
            'date'      => $row['additional_date'],
            'in_count'  => 2,
            'amount'    => $weekend_fine
          ];

          $entry['amount_in_weekend'] += $weekend_fine;
          $entry['presence']['weekend']++;
          $fine += $weekend_fine;

        }else if($row['type'] == 'work' && 
          !isset($attendance[$row['additional_date']]) && 
          !in_array(get_dayname($row['additional_date']), ['Sabtu', 'Minggu']) &&
          strtotime($current_now) >= strtotime($row['additional_date']) &&
          $row['code'] != '-'){
          $entry['presence']['weekdays']++;
          $entry['amount_in_weekdays'] += $salaryPerDayForAlpha;
          $fine += $salaryPerDayForAlpha;

        }else if($row['type'] == 'free'){
          $entry['presence']['off']++;
        }
      }
    }

    $entry['presence']['off'] += count($daterange_list);

    return [
      'amount' => round($fine),
      'detail' => [
        'entry'  => $entry,
        'rest'   => $rest,
        'pray'   => $prayer,
        'leave'  => $leave
      ]
    ];
  }

  public function get_insentif($employee_id, $branch_id, $month, $year, $presence = array()){
    $presence = empty($presence) ? $this->_get_attendance($employee_id, $month, $year) : $presence;

    $in = $half = 0;
    foreach ($presence as $row){
      if($row['entry_time'] != '' && $row['out_time'] != ''){
        $in++;
      }else if($row['entry_time'] != '' && $row['out_time'] == ''){
        $half++;
      }
    }

    if(!isset($this->insentif_master_cache[$branch_id])){
      $this->insentif_master_cache[$branch_id] = $this->db->select('*, insentif.id AS master_insentif_id')
               ->where('insentif.branch_id', $branch_id)
               ->where('insentif.is_active', '1')
               ->order_by('insentif_name', 'ASC')
               ->get('insentif')->result_array();
    }

    $period_key = $branch_id.'-'.$month.'-'.$year;
    if(!isset($this->payroll_insentif_cache[$period_key])){
      $rows = $this->db->select('payroll_insentif.*')
               ->join('users', 'users.id = payroll_insentif.user_id')
               ->join('position', 'position.id = users.position_id')
               ->where('position.branch_id', $branch_id)
               ->where('insentif_month', $month)
               ->where('insentif_year', $year)
               ->get('payroll_insentif')->result_array();

      $this->payroll_insentif_cache[$period_key] = [];
      foreach($rows as $row){
        $this->payroll_insentif_cache[$period_key][$row['user_id']][$row['insentif_id']] = $row;
      }
    }

    $insentif = $this->insentif_master_cache[$branch_id];
    $payroll_insentif = isset($this->payroll_insentif_cache[$period_key][$employee_id]) ? $this->payroll_insentif_cache[$period_key][$employee_id] : [];

    $list = []; $total = 0;
    foreach ($insentif as $row) {
      if(isset($payroll_insentif[$row['master_insentif_id']])){
        $amount = $payroll_insentif[$row['master_insentif_id']]['insentif_amount'];

      }else{
        if($row['formula'] != 'none'){
          $amount = $row['formula'] == 'per_presence' ? $in * $row['nominal'] : $row['nominal'];
        }else{
          $amount = 0;
        }
      }

      $list[] = [
        'insentif_id' => $row['master_insentif_id'],
        'name'      => $row['insentif_name'],
        'formula'   => $row['formula'] != 'none' ? true : false,
        'amount'    => round($amount)
      ];

      $total += $amount;
    }

    $data = [
      'total' => $total,
      'list'  => $list
    ];
    return $data;
  }

  public function get_deduction($employee_id, $branch_id, $month, $year){
    if(!isset($this->deduction_master_cache[$branch_id])){
      $this->deduction_master_cache[$branch_id] = $this->db->select('*, deduction.id AS master_deduction_id')
               ->where('branch_id', $branch_id)
               ->where('is_active', '1')
               ->order_by('deduction_name', 'ASC')
               ->get('deduction')->result_array();
    }

    $period_key = $branch_id.'-'.$month.'-'.$year;
    if(!isset($this->payroll_deduction_cache[$period_key])){
      $rows = $this->db->select('payroll_deduction.*')
               ->join('users', 'users.id = payroll_deduction.user_id')
               ->join('position', 'position.id = users.position_id')
               ->where('position.branch_id', $branch_id)
               ->where('deduction_month', $month)
               ->where('deduction_year', $year)
               ->get('payroll_deduction')->result_array();

      $this->payroll_deduction_cache[$period_key] = [];
      foreach($rows as $row){
        $this->payroll_deduction_cache[$period_key][$row['user_id']][$row['deduction_id']] = $row;
      }
    }

    $deduction = $this->deduction_master_cache[$branch_id];
    $payroll_deduction = isset($this->payroll_deduction_cache[$period_key][$employee_id]) ? $this->payroll_deduction_cache[$period_key][$employee_id] : [];

    $list = []; $total = 0;
    foreach ($deduction as $row) {
      $amount = isset($payroll_deduction[$row['master_deduction_id']]) ? $payroll_deduction[$row['master_deduction_id']]['deduction_amount'] : 0;
      $note = isset($payroll_deduction[$row['master_deduction_id']]) ? $payroll_deduction[$row['master_deduction_id']]['deduction_note'] : '';

      $list[] = [
        'deduction_id' => $row['master_deduction_id'],
        'name'      => $row['deduction_name'],
        'amount'    => round($amount ?? 0),
        'note'      => $note
      ];

      $total += $amount;
    }

    $data = [
      'total' => $total,
      'list'  => $list
    ];

    return $data;
  }

  private function _get_rotation_by_cluster_id($id){
      if(isset($this->rotation_cache[$id])){
        return $this->rotation_cache[$id];
      }

      $this->rotation_cache[$id] = $this->db->select('shift_cluster_rotation.*, shift_code, shift_name, start_time, end_time,
                                start_time_late, start_time_out, start_time_in, end_time_out, end_time_in, end_time_out')
                      ->join('shift', 'shift.id = shift_cluster_rotation.shift_id', 'LEFT')
                      ->order_by('num', 'ASC')
                      ->where('shift_cluster_id', $id)
                      ->get('shift_cluster_rotation')->result_array();

      return $this->rotation_cache[$id];
  }

  private function _get_employee($id, $comp, $find = array(), $order_by = array()){
    $cache_key = md5($id.'|'.$comp.'|'.serialize($find).'|'.serialize($order_by));
    if(isset($this->employee_cache[$cache_key])){
      return $this->employee_cache[$cache_key];
    }

    $this->db->select('users.*, groups.id AS group_id, groups.name AS group_name, 
                         groups.description AS group_description, position_name, branch_name,
                         branch.id AS branch_id, subdivision_id, subdivision_code, subdivision_name,
                         shift_cluster_id, shift_applies, max_overtime, overtime_hour_rate,
                         is_pray_system, is_fine_system')
               ->join('users_groups', 'users_groups.user_id = users.id')
               ->join('groups', 'groups.id = users_groups.group_id')
               ->join('position', 'position.id = users.position_id')
               ->join('subdivision', 'subdivision.id = users.subdivision_id', 'LEFT')
               ->join('branch', 'branch.id = position.branch_id')
               ->join('users_shift_cluster', 'users_shift_cluster.user_id = users.id', 'LEFT')
               ->join('shift_cluster', 'shift_cluster.id = users_shift_cluster.shift_cluster_id', 'LEFT')
               ->where('users.active', '1')
               ->where('users_shift_cluster.id !=', null);

    if(!empty($order_by)){
      foreach ($order_by as $key => $value) {
        $this->db->order_by($key, $value);
      }
      
    }else{
      $this->db->order_by('users.first_name', 'ASC');
    }

    if($comp == 'branch'){
      $this->db->where('branch.id', $id);
    }else{
      $this->db->where('users.id', $id);
    }

    if(!empty($find)){
      $this->db->where($find);
    }
    
    $this->employee_cache[$cache_key] = $this->db->get('users')->result_array();
    return $this->employee_cache[$cache_key];
  }
}

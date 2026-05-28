<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Attendance_employee_resolver {
    private $CI;

    public function __construct() {
        $this->CI =& get_instance();
    }

    public function build_by_finger_date($row_data, $from = null, $to = null) {
        $result = [
            'map' => [],
            'stats' => $this->empty_stats()
        ];

        if (empty($row_data)) {
            return $result;
        }

        $finger_ids = [];
        $dates = [];
        foreach ($row_data as $finger_id => $rows) {
            $finger_id = trim((string)$finger_id);
            if ($finger_id === '') { continue; }
            $finger_ids[$finger_id] = $finger_id;

            foreach ($rows as $date_key => $row) {
                $date = isset($row['date']) ? $row['date'] : $date_key;
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) {
                    $dates[$date] = $date;
                }
            }
        }

        if (empty($finger_ids) || empty($dates)) {
            return $result;
        }

        if ($from === null) { $from = min($dates); }
        if ($to === null) { $to = max($dates); }

        $employees = $this->CI->db->select('users.*, position.branch_id, branch.branch_name, subdivision.subdivision_name')
            ->from('users')
            ->join('position', 'position.id = users.position_id', 'left')
            ->join('branch', 'branch.id = position.branch_id', 'left')
            ->join('subdivision', 'subdivision.id = users.subdivision_id', 'left')
            ->where_in('users.employee_code', array_values($finger_ids))
            ->order_by('users.active', 'DESC')
            ->order_by('users.id', 'ASC')
            ->get()->result_array();

        $candidates = [];
        $user_ids = [];
        foreach ($employees as $employee) {
            $code = (string)$employee['employee_code'];
            $candidates[$code][] = $employee;
            $user_ids[(int)$employee['id']] = (int)$employee['id'];
        }

        $schedule_by_user_date = [];
        $schedule_count = [];
        if (!empty($user_ids)) {
            $schedules = $this->CI->db->select('id, user_id, additional_date')
                ->from('users_shift_additional')
                ->where_in('user_id', array_values($user_ids))
                ->where('additional_date >=', $from)
                ->where('additional_date <=', $to)
                ->where('additional_type', 'work')
                ->where('deleted_at IS NULL', null, false)
                ->order_by('id', 'DESC')
                ->get()->result_array();

            $seen_schedule_dates = [];
            foreach ($schedules as $schedule) {
                $user_id = (int)$schedule['user_id'];
                $date = $schedule['additional_date'];
                if (!isset($schedule_by_user_date[$user_id][$date])) {
                    $schedule_by_user_date[$user_id][$date] = (int)$schedule['id'];
                }

                $count_key = $user_id.'|'.$date;
                if (!isset($seen_schedule_dates[$count_key])) {
                    $seen_schedule_dates[$count_key] = true;
                    $schedule_count[$user_id] = isset($schedule_count[$user_id]) ? $schedule_count[$user_id] + 1 : 1;
                }
            }
        }

        foreach ($row_data as $finger_id => $rows) {
            $finger_id = trim((string)$finger_id);
            if ($finger_id === '') { continue; }

            foreach ($rows as $date_key => $row) {
                $date = isset($row['date']) ? $row['date'] : $date_key;
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) { continue; }

                if (empty($candidates[$finger_id])) {
                    $result['stats']['missing_pairs']++;
                    continue;
                }

                $result['map'][$finger_id][$date] = $this->pick_candidate(
                    $candidates[$finger_id],
                    $date,
                    $schedule_by_user_date,
                    $schedule_count,
                    $result['stats']
                );
            }
        }

        return $result;
    }

    public function message($stats) {
        if (empty($stats) || empty($stats['duplicate_pairs'])) {
            return '';
        }

        return ' Auto-detect ID duplikat: tanggal '.$stats['duplicate_by_schedule'].', tanggal tie '.$stats['duplicate_by_schedule_tie'].', periode '.$stats['duplicate_by_period'].', periode tie '.$stats['duplicate_by_period_tie'].', default '.$stats['duplicate_by_default'].'.';
    }

    public function employee_ids_from_map($employee_map) {
        $user_ids = [];
        foreach ($employee_map as $dates) {
            foreach ($dates as $employee) {
                if (!empty($employee['id'])) {
                    $user_ids[(int)$employee['id']] = (int)$employee['id'];
                }
            }
        }

        return array_values($user_ids);
    }

    public function map_for_date($finger_ids, $date) {
        $row_data = [];
        foreach ($finger_ids as $finger_id) {
            $finger_id = trim((string)$finger_id);
            if ($finger_id === '') { continue; }
            $row_data[$finger_id][$date]['date'] = $date;
        }

        $matcher = $this->build_by_finger_date($row_data, $date, $date);
        $map = [];
        foreach ($matcher['map'] as $finger_id => $dates) {
            if (isset($dates[$date])) {
                $map[$finger_id] = $dates[$date];
            }
        }

        return [
            'map' => $map,
            'stats' => $matcher['stats']
        ];
    }

    private function pick_candidate($candidates, $date, $schedule_by_user_date, $schedule_count, &$stats) {
        if (count($candidates) <= 1) {
            return $candidates[0];
        }

        $stats['duplicate_pairs']++;
        $scheduled = [];
        foreach ($candidates as $candidate) {
            $user_id = (int)$candidate['id'];
            if (isset($schedule_by_user_date[$user_id][$date])) {
                $scheduled[] = [
                    'employee' => $candidate,
                    'schedule_id' => $schedule_by_user_date[$user_id][$date]
                ];
            }
        }

        if (!empty($scheduled)) {
            usort($scheduled, function($a, $b) {
                if ($a['schedule_id'] == $b['schedule_id']) {
                    return (int)$a['employee']['id'] <=> (int)$b['employee']['id'];
                }
                return $b['schedule_id'] <=> $a['schedule_id'];
            });

            if (count($scheduled) === 1) {
                $stats['duplicate_by_schedule']++;
            } else {
                $stats['duplicate_by_schedule_tie']++;
            }

            return $scheduled[0]['employee'];
        }

        $best = [];
        $best_count = -1;
        foreach ($candidates as $candidate) {
            $user_id = (int)$candidate['id'];
            $count = isset($schedule_count[$user_id]) ? $schedule_count[$user_id] : 0;
            if ($count > $best_count) {
                $best = [$candidate];
                $best_count = $count;
                continue;
            }

            if ($count == $best_count) {
                $best[] = $candidate;
            }
        }

        if ($best_count > 0) {
            usort($best, function($a, $b) {
                return (int)$a['id'] <=> (int)$b['id'];
            });
            if (count($best) === 1) {
                $stats['duplicate_by_period']++;
            } else {
                $stats['duplicate_by_period_tie']++;
            }
            return $best[0];
        }

        $stats['duplicate_by_default']++;
        return $candidates[0];
    }

    private function empty_stats() {
        return [
            'missing_pairs' => 0,
            'duplicate_pairs' => 0,
            'duplicate_by_schedule' => 0,
            'duplicate_by_schedule_tie' => 0,
            'duplicate_by_period' => 0,
            'duplicate_by_period_tie' => 0,
            'duplicate_by_default' => 0
        ];
    }
}

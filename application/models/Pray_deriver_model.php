<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pray_deriver_model — menurunkan kolom sholat `presence` dari lapis harian
 * (pray_day), yang sendirinya diturunkan dari tap mentah (machine_type='pray').
 *
 * Sama arsitektur dgn Presence_deriver_model (lihat komentar di sana utk detail
 * provenance trigger @absen_sync_ctx) -- BEDA PENTING satu ini:
 *
 *   Sholat SELALU menempel pada baris presence yang SUDAH ADA (dibuat jalur
 *   kerja/attendance). Kalau presence hari itu belum ada, baris pray_day itu
 *   di-SKIP (skipped_no_presence), TIDAK PERNAH insert baris presence baru --
 *   sama persis perilaku hr/Presence::_import_pray_sheet() (jalur lama) yang
 *   sudah dipakai bertahun-tahun. Juga skip presence_type != 'normal' (izin/
 *   cuti/sakit tidak boleh disentuh sholat).
 *
 * Trigger presence_provenance_bi/bu SUDAH mencakup 18 kolom sholat (subuh_time_in
 * dst) persis sama seperti 4 kolom kerja -- diverifikasi 4 Sep 2026 sebelum model
 * ini ditulis. Jadi PHP di sini TIDAK PERLU mengecek input_by/cleared_fields
 * manual sendiri (beda dari _import_pray_sheet lama yang masih cek manual di
 * PHP) -- trigger DB yang menegakkan itu, sama seperti jalur kerja.
 */
class Pray_deriver_model extends CI_Model {

    const PRAYERS = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];

    public function __construct() {
        parent::__construct();
        $this->load->model('presence_daily_report_model', 'daily_report');
    }

    /**
     * $opts: force (bool), dry_run (bool), actor_id (int)
     * Return ringkasan: updated, blocked, skipped_locked, skipped_no_presence,
     * skipped_leave, skipped_empty, conflicts[].
     */
    public function derive($branch_id, $from, $to, $opts = []) {
        $force    = !empty($opts['force']);
        $dry      = !empty($opts['dry_run']);
        $actor_id = isset($opts['actor_id']) ? (int) $opts['actor_id'] : 0;

        $out = [
            'updated' => 0, 'blocked' => 0, 'skipped_locked' => 0,
            'skipped_no_presence' => 0, 'skipped_leave' => 0, 'skipped_empty' => 0,
            'conflicts' => [], 'dry_run' => $dry, 'force' => $force,
        ];

        $days = $this->_days($branch_id, $from, $to);
        if (empty($days)) { return $out; }

        $uids     = array_values(array_unique(array_map(function ($d) { return (int) $d['user_id']; }, $days)));
        $existing = $this->_existing_presence($uids, $from, $to);
        $branches = $this->_user_branch_map($uids);

        $lock_cache = [];
        $plan = ['system' => [], 'manual' => []];

        foreach ($days as $d) {
            $uid  = (int) $d['user_id'];
            $date = $d['flow_date'];
            $key  = $uid.'|'.$date;

            if (!$this->_has_any_time($d)) { $out['skipped_empty']++; continue; }

            $cur = isset($existing[$key]) ? $existing[$key] : NULL;
            if ($cur === NULL) { $out['skipped_no_presence']++; continue; }
            if ($cur['presence_type'] !== 'normal') { $out['skipped_leave']++; continue; }

            $bid = isset($branches[$uid]) ? $branches[$uid] : (int) $branch_id;
            $pp  = $this->_period_of_date($date);
            $lk  = $bid.'|'.$pp['month'].'|'.$pp['year'];
            if (!isset($lock_cache[$lk])) {
                $lock_cache[$lk] = $this->_payroll_locked($bid, $pp['month'], $pp['year']);
            }
            if ($lock_cache[$lk]) { $out['skipped_locked']++; continue; }

            $payload = $this->_payload($d);
            if (!$this->_differs($payload, $cur)) { continue; }

            $lane = ($d['is_edited'] || $force) ? 'manual' : 'system';
            if ($dry && $lane === 'system' && $this->_will_block($payload, $cur)) {
                $out['blocked']++;
                $out['conflicts'][] = ['user_id' => $uid, 'date' => $date,
                    'mesin' => $this->_short($payload), 'presensi' => $this->_short($cur)];
            }

            $plan[$lane][] = [
                'key' => $key, 'id' => (int) $cur['id'], 'payload' => $payload, 'before' => $cur,
                'actor' => $d['is_edited'] ? (int) $d['edited_by'] : $actor_id,
            ];
        }

        if ($dry) {
            $out['updated'] = count($plan['system']) + count($plan['manual']);
            return $out;
        }

        $written = [];
        $this->_run_batch($plan['system'], 1, $actor_id, $out, $written);
        $this->_run_batch($plan['manual'], NULL, $actor_id, $out, $written);

        if (!empty($written)) {
            $this->daily_report->sync_by_rows($written);
            $this->_mark_derived($written);
        }

        return $out;
    }

    // =====================================================================
    // INTERNAL
    // =====================================================================

    private function _run_batch($items, $ctx, $actor_id, &$out, &$written) {
        if (empty($items)) { return; }

        usort($items, function ($a, $b) { return (int) $a['actor'] <=> (int) $b['actor']; });
        $this->db->query($ctx === NULL ? 'SET @absen_sync_ctx = NULL' : 'SET @absen_sync_ctx = 1');
        $last_actor = NULL;

        foreach ($items as $it) {
            $actor = !empty($it['actor']) ? (int) $it['actor'] : (int) $actor_id;
            if ($actor !== $last_actor) {
                $this->db->query('SET @audit_user_id = '.($actor ?: 'NULL'));
                $last_actor = $actor;
            }

            $this->db->where('id', $it['id'])->update('presence', $it['payload']);

            $select = [];
            foreach (self::PRAYERS as $p) { $select[] = "{$p}_time_in"; $select[] = "{$p}_time_out"; $select[] = "{$p}_time_late"; }
            $after = $this->db->select(implode(',', $select))->where('id', $it['id'])->get('presence')->row_array();

            if ($this->_differs($it['payload'], $after)) {
                $out['blocked']++;
                $out['conflicts'][] = [
                    'user_id' => (int) $it['payload']['user_id'],
                    'date'    => $it['payload']['flow_date'],
                    'mesin'   => $this->_short($it['payload']),
                    'presensi'=> $this->_short($it['before']),
                ];
            } else {
                $out['updated']++;
                $written[] = $it['payload'];
            }
        }

        $this->db->query('SET @absen_sync_ctx = NULL');
    }

    private function _has_any_time($d) {
        foreach (self::PRAYERS as $p) { if (!empty($d[$p.'_in'])) { return TRUE; } }
        return FALSE;
    }

    private function _days($branch_id, $from, $to) {
        $cols = 'd.user_id, d.flow_date, d.is_edited, d.edited_by';
        foreach (self::PRAYERS as $p) { $cols .= ", d.{$p}_in, d.{$p}_out, d.{$p}_late"; }
        $this->db->select($cols, FALSE)
            ->from('pray_day d')
            ->join('users u', 'u.id = d.user_id')
            ->join('position p', 'p.id = u.position_id', 'left')
            ->where('d.flow_date >=', $from)
            ->where('d.flow_date <=', $to);
        if ($branch_id !== NULL) { $this->db->where('p.branch_id', (int) $branch_id); }
        return $this->db->order_by('d.flow_date', 'ASC')->get()->result_array();
    }

    private function _existing_presence($user_ids, $from, $to) {
        $out = [];
        if (empty($user_ids)) { return $out; }
        $cols = 'id, user_id, flow_date, presence_type, input_by, cleared_fields';
        foreach (self::PRAYERS as $p) { $cols .= ", {$p}_time_in, {$p}_time_out, {$p}_time_late"; }
        $rows = $this->db->select($cols)
            ->where_in('user_id', $user_ids)
            ->where('flow_date >=', $from)->where('flow_date <=', $to)
            ->get('presence')->result_array();
        foreach ($rows as $r) { $out[$r['user_id'].'|'.$r['flow_date']] = $r; }
        return $out;
    }

    private function _user_branch_map($user_ids) {
        $out = [];
        if (empty($user_ids)) { return $out; }
        $rows = $this->db->select('u.id, p.branch_id')->from('users u')
            ->join('position p', 'p.id = u.position_id', 'left')
            ->where_in('u.id', $user_ids)->get()->result_array();
        foreach ($rows as $r) { $out[(int) $r['id']] = (int) $r['branch_id']; }
        return $out;
    }

    /** Payload presence (18 kolom sholat) dari satu baris pray_day. */
    private function _payload($d) {
        $p = ['user_id' => (int) $d['user_id'], 'flow_date' => $d['flow_date']];
        foreach (self::PRAYERS as $pray) {
            $in  = $d[$pray.'_in'];
            $out = $d[$pray.'_out'];
            $p[$pray.'_time_in']   = $in  ? $d['flow_date'].' '.$in  : NULL;
            $p[$pray.'_time_out']  = $out ? $d['flow_date'].' '.$out : NULL;
            $p[$pray.'_time_late'] = $in  ? (int) $d[$pray.'_late'] : 0;
        }
        return $p;
    }

    private function _will_block($payload, $row) {
        if (empty($row) || $row['input_by'] !== 'manual') { return FALSE; }
        foreach (self::PRAYERS as $pray) {
            $c = $pray.'_time_in';
            $a = $payload[$c] !== NULL ? substr($payload[$c], 0, 19) : NULL;
            $b = !empty($row[$c]) ? substr($row[$c], 0, 19) : NULL;
            if ($b !== NULL && $a !== $b) { return TRUE; }
        }
        return FALSE;
    }

    private function _differs($payload, $row) {
        if (empty($row)) { return TRUE; }
        foreach (self::PRAYERS as $pray) {
            foreach (['in', 'out'] as $suffix) {
                $c = $pray.'_time_'.$suffix;
                $a = isset($payload[$c]) && $payload[$c] !== NULL ? substr($payload[$c], 0, 19) : NULL;
                $b = !empty($row[$c]) ? substr($row[$c], 0, 19) : NULL;
                if ($a !== $b) { return TRUE; }
            }
        }
        return FALSE;
    }

    private function _short($p) {
        $parts = [];
        foreach (self::PRAYERS as $pray) {
            $v = isset($p[$pray.'_time_in']) && $p[$pray.'_time_in'] ? substr($p[$pray.'_time_in'], 11, 5) : '-';
            $parts[] = substr($pray, 0, 3).':'.$v;
        }
        return implode(' ', $parts);
    }

    private function _mark_derived($written) {
        $now = date('Y-m-d H:i:s');
        $pairs = [];
        foreach ($written as $w) { $pairs[$w['user_id'].'|'.$w['flow_date']] = $w; }
        foreach (array_chunk(array_values($pairs), 300) as $chunk) {
            foreach ($chunk as $w) {
                $this->db->where('user_id', $w['user_id'])->where('flow_date', $w['flow_date'])
                    ->update('pray_day', ['derived_at' => $now, 'derive_status' => 'ok']);
            }
        }
    }

    private function _period_of_date($date) {
        $d = (int) substr($date, 8, 2); $m = (int) substr($date, 5, 2); $y = (int) substr($date, 0, 4);
        if ($d >= START_PAYROLL_DATE) { $m++; if ($m > 12) { $m = 1; $y++; } }
        return ['month' => $m, 'year' => $y];
    }

    private function _payroll_locked($branch_id, $month, $year) {
        $this->load->model('payroll_model', 'payroll');
        return $this->payroll->get_detail([
            'branch_id' => (int) $branch_id, 'month' => (int) $month, 'year' => (int) $year,
        ])->num_rows() > 0;
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Presence_deriver_model — menurunkan `presence` dari lapis harian
 * (attendance_day), yang sendirinya diturunkan dari tap mentah.
 *
 * ATURAN PROVENANCE DIPEGANG TRIGGER DB, BUKAN PHP.
 * Tabel presence punya trigger presence_provenance_bi/bu yang membaca variabel
 * sesi @absen_sync_ctx:
 *
 *   @absen_sync_ctx NULL           = tulisan atas nama MANUSIA. Kolom jam yang
 *                                    berubah membuat baris ter-stempel
 *                                    input_by='manual' + input_by_user_id, dan
 *                                    daftar cleared_fields dirawat otomatis.
 *   @absen_sync_ctx diisi (mis. 1) = tulisan MESIN. Baris yang input_by-nya
 *                                    sudah 'manual' DIKEMBALIKAN ke nilai lama
 *                                    oleh trigger — diam-diam, tanpa error —
 *                                    dan kolom di cleared_fields dipaksa NULL.
 *
 * Karena itu deriver menulis dalam dua batch dan membiarkan DB yang menegakkan
 * aturannya; tidak ada logika merge di PHP yang bisa dilewati jalur tulis lain.
 *
 *   batch SYSTEM  attendance_day.is_edited=0  -> @absen_sync_ctx = 1
 *                 murni hasil mesin; koreksi manusia di presence otomatis aman
 *   batch MANUAL  attendance_day.is_edited=1  -> @absen_sync_ctx = NULL
 *                 koreksi admin di lapis mentah, ditulis atas namanya sendiri
 *                 sehingga menang atas nilai lama dan tercatat di audit_log
 *
 * Mode force ("Regenerate periode") memindahkan SEMUA baris ke jalur manusia
 * atas nama operator, supaya baris presence lama yang ter-stempel manual
 * (termasuk yang salah) bisa diperbaiki. Ini satu-satunya cara menembus
 * proteksi trigger, dan itu memang disengaja: force adalah tindakan operator
 * yang eksplisit, bukan perilaku sync harian.
 *
 * Lock penggajian TIDAK PERNAH ditembus, termasuk oleh force.
 */
class Presence_deriver_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->helper('attlog');
        $this->load->model('presence_daily_report_model', 'daily_report');
    }

    /**
     * $opts: force (bool), dry_run (bool), actor_id (int)
     *
     * Return ringkasan: inserted, updated, blocked, skipped_locked,
     * skipped_leave, skipped_empty, conflicts[], refilled_cleared.
     */
    public function derive($branch_id, $from, $to, $opts = []) {
        $force    = !empty($opts['force']);
        $dry      = !empty($opts['dry_run']);
        $actor_id = isset($opts['actor_id']) ? (int) $opts['actor_id'] : 0;

        $out = [
            'inserted' => 0, 'updated' => 0, 'blocked' => 0,
            'skipped_locked' => 0, 'skipped_leave' => 0, 'skipped_empty' => 0,
            'conflicts' => [], 'refilled_cleared' => 0, 'dry_run' => $dry, 'force' => $force,
        ];

        $days = $this->_days($branch_id, $from, $to);
        if (empty($days)) { return $out; }

        $uids     = array_values(array_unique(array_map(function ($d) { return (int) $d['user_id']; }, $days)));
        $existing = $this->_existing_presence($uids, $from, $to);
        $branches = $this->_user_branch_map($uids);
        $now      = date('Y-m-d H:i:s');

        $lock_cache = [];
        $plan = ['system' => [], 'manual' => []];

        foreach ($days as $d) {
            $uid  = (int) $d['user_id'];
            $date = $d['flow_date'];
            $key  = $uid.'|'.$date;

            if ($d['entry_time'] === NULL && $d['out_time'] === NULL
                && $d['rest_in'] === NULL && $d['rest_out'] === NULL) {
                $out['skipped_empty']++;
                continue;
            }

            // Lock memakai cabang MILIK user, bukan cabang yang diminta.
            $bid = isset($branches[$uid]) ? $branches[$uid] : (int) $branch_id;
            $pp  = $this->_period_of_date($date);
            $lk  = $bid.'|'.$pp['month'].'|'.$pp['year'];
            if (!isset($lock_cache[$lk])) {
                $lock_cache[$lk] = $this->_payroll_locked($bid, $pp['month'], $pp['year']);
            }
            if ($lock_cache[$lk]) { $out['skipped_locked']++; continue; }

            $cur = isset($existing[$key]) ? $existing[$key] : NULL;

            // Izin/cuti/sakit ditulis modul lain dan tidak punya tap sama sekali.
            // Jangan pernah disentuh, apalagi dihapus.
            if ($cur !== NULL && $cur['presence_type'] !== 'normal') {
                $out['skipped_leave']++;
                continue;
            }

            $payload = $this->_payload($uid, $date, $d, $now);

            if ($cur === NULL) {
                // Baris baru: is_edited menentukan atas nama siapa ia ditulis.
                $plan[$d['is_edited'] || $force ? 'manual' : 'system'][] = [
                    'op' => 'insert', 'key' => $key, 'payload' => $payload,
                    'actor' => $d['is_edited'] ? (int) $d['edited_by'] : $actor_id,
                ];
                continue;
            }

            if (!$this->_differs($payload, $cur)) { continue; }

            $lane = ($d['is_edited'] || $force) ? 'manual' : 'system';
            if ($force && $cur['cleared_fields'] !== '') { $out['refilled_cleared']++; }
            if ($dry && $lane === 'system' && $this->_will_block($payload, $cur)) {
                $out['blocked']++;
                $out['conflicts'][] = [
                    'user_id' => $uid, 'date' => $date,
                    'mesin' => $this->_short($payload), 'presensi' => $this->_short($cur),
                ];
            }

            $plan[$lane][] = [
                'op' => 'update', 'key' => $key, 'id' => (int) $cur['id'],
                'payload' => $payload, 'before' => $cur,
                'actor' => $d['is_edited'] ? (int) $d['edited_by'] : $actor_id,
            ];
        }

        if ($dry) {
            $out['inserted'] = count(array_filter(array_merge($plan['system'], $plan['manual']),
                function ($p) { return $p['op'] === 'insert'; }));
            $out['updated'] = count(array_filter(array_merge($plan['system'], $plan['manual']),
                function ($p) { return $p['op'] === 'update'; }));
            return $out;
        }

        $written = [];
        // Batch mesin lebih dulu, batch manusia belakangan: kalau satu hari
        // entah bagaimana masuk keduanya, versi manusia yang menang.
        $this->_run_batch($plan['system'], 1, $actor_id, $out, $written);
        $this->_run_batch($plan['manual'], NULL, $actor_id, $out, $written);

        if (!empty($written)) {
            $this->daily_report->sync_by_rows($written);
            $this->_mark_derived($written, $now);
        }

        return $out;
    }

    // =====================================================================
    // INTERNAL
    // =====================================================================

    /**
     * Jalankan satu batch dengan konteks provenance tertentu.
     * $ctx: 1 = tulisan mesin, NULL = tulisan manusia.
     *
     * Sesudah UPDATE, baris DIBACA ULANG: trigger bisa membatalkan perubahan
     * tanpa error, jadi affected_rows() tidak bisa dipercaya sebagai bukti.
     */
    private function _run_batch($items, $ctx, $actor_id, &$out, &$written) {
        if (empty($items)) { return; }

        // Konteks disetel sekali, lalu hanya diulang kalau aktornya ganti —
        // koneksi persisten membuat variabel sesi bertahan antar query.
        usort($items, function ($a, $b) { return (int) $a['actor'] <=> (int) $b['actor']; });
        $this->db->query($ctx === NULL ? 'SET @absen_sync_ctx = NULL' : 'SET @absen_sync_ctx = 1');
        $last_actor = NULL;

        foreach ($items as $it) {
            $actor = !empty($it['actor']) ? (int) $it['actor'] : (int) $actor_id;
            if ($actor !== $last_actor) {
                $this->db->query('SET @audit_user_id = '.($actor ?: 'NULL'));
                $last_actor = $actor;
            }

            if ($it['op'] === 'insert') {
                $payload = $it['payload'];
                if ($ctx !== NULL) { $payload['input_by'] = 'system'; }
                $this->db->insert('presence', $payload);
                $out['inserted']++;
                $written[] = $payload;
                continue;
            }

            $this->db->where('id', $it['id'])->update('presence', $it['payload']);

            $after = $this->db->select('entry_time, out_time, rest_time_in, rest_time_out')
                ->where('id', $it['id'])->get('presence')->row_array();
            if ($this->_differs($it['payload'], $after)) {
                // Trigger mengembalikan nilai lama (baris manual dilindungi).
                // Catat sebagai selisih supaya operator tahu mesin dan presensi
                // berbeda, dan bisa memutuskan memakai Regenerate.
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

        // Jangan tinggalkan konteks menempel di koneksi persisten.
        $this->db->query('SET @absen_sync_ctx = NULL');
    }

    private function _days($branch_id, $from, $to) {
        $this->db->select('d.user_id, d.flow_date, d.entry_time, d.rest_in, d.rest_out, d.out_time,
                           d.entry_late, d.rest_late, d.is_edited, d.edited_by', FALSE)
            ->from('attendance_day d')
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

        $rows = $this->db->select('id, user_id, flow_date, entry_time, out_time, rest_time_in,
                                   rest_time_out, entry_time_late, rest_time_late, input_by,
                                   presence_type, cleared_fields')
            ->where_in('user_id', $user_ids)
            ->where('flow_date >=', $from)
            ->where('flow_date <=', $to)
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

    /** Payload presence dari satu baris harian. Kolom non-jam tidak disentuh. */
    private function _payload($user_id, $date, $d, $now) {
        $p = attlog_payload();
        $p['user_id']    = $user_id;
        $p['flow_date']  = $date;
        $p['created_at'] = $now;
        $p['updated_at'] = $now;

        $p['entry_time']    = $d['entry_time'] ? $date.' '.$d['entry_time'] : NULL;
        $p['out_time']      = $d['out_time']   ? $date.' '.$d['out_time']   : NULL;
        $p['rest_time_in']  = $d['rest_in']    ? $date.' '.$d['rest_in']    : NULL;
        $p['rest_time_out'] = $d['rest_out']   ? $date.' '.$d['rest_out']   : NULL;
        $p['entry_time_late'] = (int) $d['entry_late'];
        $p['rest_time_late']  = (int) $d['rest_late'];

        return $p;
    }

    /**
     * Perkiraan apakah trigger akan menolak tulisan mesin ini (dipakai dry-run).
     * Trigger mengembalikan nilai lama hanya untuk kolom yang SUDAH terisi pada
     * baris bertanda manual; kolom yang masih NULL tetap boleh diisi.
     */
    private function _will_block($payload, $row) {
        if (empty($row) || $row['input_by'] !== 'manual') { return FALSE; }
        foreach (['entry_time', 'out_time', 'rest_time_in', 'rest_time_out'] as $c) {
            $a = $payload[$c] !== NULL ? substr($payload[$c], 0, 19) : NULL;
            $b = !empty($row[$c]) ? substr($row[$c], 0, 19) : NULL;
            if ($b !== NULL && $a !== $b) { return TRUE; }
        }
        return FALSE;
    }

    /** Bandingkan hanya empat kolom jam. */
    private function _differs($payload, $row) {
        if (empty($row)) { return TRUE; }
        foreach (['entry_time', 'out_time', 'rest_time_in', 'rest_time_out'] as $c) {
            $a = $payload[$c] !== NULL ? substr($payload[$c], 0, 19) : NULL;
            $b = !empty($row[$c]) ? substr($row[$c], 0, 19) : NULL;
            if ($a !== $b) { return TRUE; }
        }
        return FALSE;
    }

    private function _short($p) {
        return trim(($p['entry_time'] ? substr($p['entry_time'], 11, 5) : '—').'/'
                   .($p['rest_time_in'] ? substr($p['rest_time_in'], 11, 5) : '—').'/'
                   .($p['rest_time_out'] ? substr($p['rest_time_out'], 11, 5) : '—').'/'
                   .($p['out_time'] ? substr($p['out_time'], 11, 5) : '—'));
    }

    private function _mark_derived($written, $now) {
        $pairs = [];
        foreach ($written as $w) { $pairs[$w['user_id'].'|'.$w['flow_date']] = $w; }
        foreach (array_chunk(array_values($pairs), 300) as $chunk) {
            foreach ($chunk as $w) {
                $this->db->where('user_id', $w['user_id'])->where('flow_date', $w['flow_date'])
                    ->update('attendance_day', ['derived_at' => $now, 'derive_status' => 'ok']);
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

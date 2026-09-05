<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Attendance_day_model — akses tabel `attendance_day` (lapis harian editable).
 *
 * Menggantikan Dat_reader_model (dat_reader_mirror + dat_reader_work) yang
 * memisah "cermin mesin" dan "data kerja" ke dua tabel. Sekarang keduanya satu
 * baris: kolom m_* = hasil mesin, kolom tanpa prefiks = nilai EFEKTIF yang
 * menurunkan `presence`. is_edited=1 menandai admin mengoreksi penempatan slot.
 *
 * Pemetaan ke presence (dipakai deriver di Fase 3):
 *   entry_time -> presence.entry_time     rest_in  -> presence.rest_time_in
 *   out_time   -> presence.out_time       rest_out -> presence.rest_time_out
 */
class Attendance_day_model extends CI_Model {

    /** Baris harian + identitas karyawan, dibatasi cabang & rentang tanggal. */
    public function get_range($branch_id, $from, $to, $user_ids = NULL) {
        $this->db->select("d.user_id, d.flow_date,
                    TIME_FORMAT(d.entry_time,'%H:%i') AS entry_time,
                    TIME_FORMAT(d.rest_in,'%H:%i')    AS rest_in,
                    TIME_FORMAT(d.rest_out,'%H:%i')   AS rest_out,
                    TIME_FORMAT(d.out_time,'%H:%i')   AS out_time,
                    TIME_FORMAT(d.m_entry_time,'%H:%i') AS m_entry_time,
                    TIME_FORMAT(d.m_rest_in,'%H:%i')    AS m_rest_in,
                    TIME_FORMAT(d.m_rest_out,'%H:%i')   AS m_rest_out,
                    TIME_FORMAT(d.m_out_time,'%H:%i')   AS m_out_time,
                    d.entry_late, d.rest_late, d.tap_count, d.all_taps,
                    d.classify_method, d.shift_id, d.needs_reclass, d.classified_at,
                    d.is_edited, d.edit_note, d.edited_by, d.edited_at,
                    d.derived_at, d.derive_status,
                    u.employee_code, u.first_name, u.last_name, s.shift_code", FALSE)
            ->from('attendance_day d')
            ->join('users u', 'u.id = d.user_id')
            ->join('position p', 'p.id = u.position_id', 'left')
            ->join('shift s', 's.id = d.shift_id', 'left')
            ->where('d.flow_date >=', $from)
            ->where('d.flow_date <=', $to);

        if ($branch_id !== NULL) { $this->db->where('p.branch_id', (int) $branch_id); }
        if (!empty($user_ids))   { $this->db->where_in('d.user_id', $user_ids); }

        return $this->db->order_by('d.flow_date', 'ASC')
                        ->order_by('u.first_name', 'ASC')
                        ->get()->result_array();
    }

    /** user_id yang punya baris harian pada rentang (untuk klasifikasi ulang). */
    public function user_ids_in_range($branch_id, $from, $to) {
        $this->db->select('DISTINCT d.user_id', FALSE)
            ->from('attendance_day d')
            ->join('users u', 'u.id = d.user_id')
            ->join('position p', 'p.id = u.position_id', 'left')
            ->where('d.flow_date >=', $from)
            ->where('d.flow_date <=', $to);
        if ($branch_id !== NULL) { $this->db->where('p.branch_id', (int) $branch_id); }

        $out = [];
        foreach ($this->db->get()->result_array() as $r) { $out[] = (int) $r['user_id']; }
        return $out;
    }

    /**
     * Simpan koreksi admin ke kolom efektif.
     * $edits: [{user_id, flow_date, entry_time, rest_in, rest_out, out_time, edit_note}]
     *
     * is_edited di-set 1 kalau nilainya beda dari hasil mesin (m_*), dan
     * dikembalikan ke 0 kalau admin menyamakannya lagi dengan mesin — sehingga
     * baris itu kembali ikut hasil klasifikasi ulang berikutnya.
     * Menit telat dihitung ulang dari jadwal supaya konsisten dengan sync.
     *
     * Return jumlah baris tersimpan.
     */
    public function save_edits($edits, $editor_id, $now) {
        if (empty($edits)) { return 0; }

        $this->load->library('attendance_classifier');
        $this->load->helper('late');

        $uids = [];
        $dates = [];
        foreach ($edits as $e) {
            $uids[(int) $e['user_id']] = (int) $e['user_id'];
            $dates[] = $e['flow_date'];
        }
        $shift_map = $this->attendance_classifier->shift_map(
            array_values($uids), min($dates), max($dates));

        $saved = 0;
        foreach ($edits as $e) {
            $uid  = (int) $e['user_id'];
            $date = $e['flow_date'];

            $current = $this->db->select('m_entry_time, m_rest_in, m_rest_out, m_out_time')
                ->where(['user_id' => $uid, 'flow_date' => $date])
                ->get('attendance_day')->row_array();
            if (empty($current)) { continue; }

            $val = [
                'entry_time' => $this->normalize_time(isset($e['entry_time']) ? $e['entry_time'] : NULL),
                'rest_in'    => $this->normalize_time(isset($e['rest_in'])    ? $e['rest_in']    : NULL),
                'rest_out'   => $this->normalize_time(isset($e['rest_out'])   ? $e['rest_out']   : NULL),
                'out_time'   => $this->normalize_time(isset($e['out_time'])   ? $e['out_time']   : NULL),
            ];

            $differs = ($val['entry_time'] !== $this->normalize_time($current['m_entry_time']))
                    || ($val['rest_in']    !== $this->normalize_time($current['m_rest_in']))
                    || ($val['rest_out']   !== $this->normalize_time($current['m_rest_out']))
                    || ($val['out_time']   !== $this->normalize_time($current['m_out_time']));

            $shift = isset($shift_map[$uid.'|'.$date]) ? $shift_map[$uid.'|'.$date] : NULL;
            $val['entry_late'] = ($shift && $val['entry_time'] !== NULL)
                ? late_minutes($shift['start_time_late'], $val['entry_time']) : 0;
            $val['rest_late'] = 0;
            if ($shift && $val['rest_in'] !== NULL && $val['rest_out'] !== NULL) {
                $limit = date('H:i:s', strtotime($val['rest_in'].' +'.$shift['rest_time_range'].' minutes'));
                $val['rest_late'] = late_minutes($limit, $val['rest_out']);
            }

            $val['is_edited'] = $differs ? 1 : 0;
            $val['edit_note'] = $differs && isset($e['edit_note']) ? substr(trim($e['edit_note']), 0, 255) : NULL;
            $val['edited_by'] = $differs ? (int) $editor_id : NULL;
            $val['edited_at'] = $differs ? $now : NULL;
            $val['classify_method'] = $differs ? 'manual' : 'window';
            $val['needs_reclass'] = $differs ? 0 : 1; // balik ke mesin: minta klasifikasi ulang
            $val['updated_at'] = $now;

            $this->db->where(['user_id' => $uid, 'flow_date' => $date])
                     ->update('attendance_day', $val);
            $saved++;
        }

        return $saved;
    }

    /** Pasangan "user_id|flow_date" yang sudah ada di `presence`. */
    public function presence_existing_pairs($user_ids, $from, $to) {
        $out = [];
        if (empty($user_ids)) { return $out; }

        $rows = $this->db->select('user_id, flow_date, input_by, presence_type')
            ->where_in('user_id', $user_ids)
            ->where('flow_date >=', $from)
            ->where('flow_date <=', $to)
            ->get('presence')->result_array();

        foreach ($rows as $r) { $out[$r['user_id'].'|'.$r['flow_date']] = $r; }
        return $out;
    }

    /** 'H:i' / 'H:i:s' / kosong -> 'HH:MM:SS' atau NULL. */
    public function normalize_time($v) {
        $v = trim((string) $v);
        if ($v === '') { return NULL; }
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $v, $m)) { return NULL; }
        if ((int) $m[1] > 23 || (int) $m[2] > 59) { return NULL; }
        return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], isset($m[3]) ? (int) $m[3] : 0);
    }
}

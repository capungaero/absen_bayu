<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dat_reader_model — akses data untuk tool DAT Reader.
 *
 * Dua tabel terpisah dari `presence`:
 *   dat_reader_mirror : hasil sync mesin MURNI (gabungan), hidden, refresh tiap sync.
 *   dat_reader_work   : data kerja editable yang ditampilkan; is_edited=1 = beda dari mirror.
 *
 * Kolom waktu: datang / out_ist / in_ist / pulang (TIME). Push ke presence
 * memetakan: datang->entry_time, out_ist->rest_time_in, in_ist->rest_time_out,
 * pulang->out_time (lihat DatReader::push()).
 */
class Dat_reader_model extends CI_Model {

    /** Upsert mirror per (user, tanggal). $rows: list assoc dgn kolom mirror. */
    public function upsert_mirror($rows, $synced_at) {
        if (empty($rows)) return 0;
        $place = []; $vals = [];
        foreach ($rows as $r) {
            $place[] = '(?,?,?,?,?,?,?,?,?,?)';
            array_push($vals,
                (int)$r['user_id'], $r['flow_date'],
                $r['datang'], $r['out_ist'], $r['in_ist'], $r['pulang'],
                (int)$r['tap_count'], $r['all_taps'], $r['source'], $synced_at);
        }
        $sql = 'INSERT INTO dat_reader_mirror
                  (user_id, flow_date, datang, out_ist, in_ist, pulang, tap_count, all_taps, source, synced_at)
                VALUES '.implode(',', $place).'
                ON DUPLICATE KEY UPDATE
                  datang=VALUES(datang), out_ist=VALUES(out_ist), in_ist=VALUES(in_ist),
                  pulang=VALUES(pulang), tap_count=VALUES(tap_count), all_taps=VALUES(all_taps),
                  source=VALUES(source), synced_at=VALUES(synced_at)';
        $this->db->query($sql, $vals);
        return count($rows);
    }

    /**
     * Seed/refresh work dari mirror untuk rentang + user tertentu.
     * - work belum ada           → insert salinan mirror (is_edited=0)
     * - work ada & is_edited=0    → ikuti mirror terbaru
     * - work ada & is_edited=1    → biarkan (hormati editan admin)
     */
    public function sync_work_from_mirror($user_ids, $from, $to, $now) {
        if (empty($user_ids)) return;
        $ids = implode(',', array_map('intval', $user_ids));
        $sql = "INSERT INTO dat_reader_work
                  (user_id, flow_date, datang, out_ist, in_ist, pulang, is_edited, created_at)
                SELECT m.user_id, m.flow_date, m.datang, m.out_ist, m.in_ist, m.pulang, 0, ?
                FROM dat_reader_mirror m
                WHERE m.flow_date >= ? AND m.flow_date <= ? AND m.user_id IN ($ids)
                ON DUPLICATE KEY UPDATE
                  datang  = IF(dat_reader_work.is_edited=1, dat_reader_work.datang,  VALUES(datang)),
                  out_ist = IF(dat_reader_work.is_edited=1, dat_reader_work.out_ist, VALUES(out_ist)),
                  in_ist  = IF(dat_reader_work.is_edited=1, dat_reader_work.in_ist,  VALUES(in_ist)),
                  pulang  = IF(dat_reader_work.is_edited=1, dat_reader_work.pulang,  VALUES(pulang)),
                  updated_at = ?";
        $this->db->query($sql, [$now, $from, $to, $now]);
    }

    /** Ambil baris work (+ mirror utk banding + nama karyawan) per cabang & rentang. */
    public function get_work_range($branch_id, $from, $to) {
        return $this->db->query(
            "SELECT w.user_id, w.flow_date,
                    TIME_FORMAT(w.datang,'%H:%i')  AS datang,
                    TIME_FORMAT(w.out_ist,'%H:%i') AS out_ist,
                    TIME_FORMAT(w.in_ist,'%H:%i')  AS in_ist,
                    TIME_FORMAT(w.pulang,'%H:%i')  AS pulang,
                    w.is_edited, w.edited_at, w.pushed_at,
                    TIME_FORMAT(m.datang,'%H:%i')  AS m_datang,
                    TIME_FORMAT(m.out_ist,'%H:%i') AS m_out_ist,
                    TIME_FORMAT(m.in_ist,'%H:%i')  AS m_in_ist,
                    TIME_FORMAT(m.pulang,'%H:%i')  AS m_pulang,
                    m.tap_count, m.all_taps, m.source,
                    u.employee_code, u.first_name, u.last_name
             FROM dat_reader_work w
             LEFT JOIN dat_reader_mirror m ON m.user_id=w.user_id AND m.flow_date=w.flow_date
             JOIN users u ON u.id = w.user_id
             LEFT JOIN position p ON p.id = u.position_id
             WHERE w.flow_date >= ? AND w.flow_date <= ? AND p.branch_id = ?
             ORDER BY w.flow_date ASC, u.first_name ASC",
            [$from, $to, (int)$branch_id]
        )->result_array();
    }

    /** Pasangan (user_id|flow_date) yang SUDAH ada di presence (untuk tandai/skip push). */
    public function presence_existing_pairs($user_ids, $from, $to) {
        $out = [];
        if (empty($user_ids)) return $out;
        $rows = $this->db->select('user_id, flow_date')
            ->where_in('user_id', $user_ids)
            ->where('flow_date >=', $from)->where('flow_date <=', $to)
            ->get('presence')->result_array();
        foreach ($rows as $r) { $out[$r['user_id'].'|'.$r['flow_date']] = true; }
        return $out;
    }

    /**
     * Simpan editan admin ke work. $edits: list {user_id, flow_date, datang,out_ist,in_ist,pulang}.
     * is_edited di-set 1 kalau beda dari mirror, 0 kalau persis sama mirror.
     * Return jumlah baris ter-update.
     */
    public function save_work_edits($edits, $editor_id, $now) {
        $n = 0;
        foreach ($edits as $e) {
            $uid = (int)$e['user_id'];
            $date = $e['flow_date'];
            $vals = [
                'datang'  => $this->_t($e['datang']  ?? null),
                'out_ist' => $this->_t($e['out_ist'] ?? null),
                'in_ist'  => $this->_t($e['in_ist']  ?? null),
                'pulang'  => $this->_t($e['pulang']  ?? null),
            ];
            $mirror = $this->db->select("TIME_FORMAT(datang,'%H:%i:%s') datang, TIME_FORMAT(out_ist,'%H:%i:%s') out_ist, TIME_FORMAT(in_ist,'%H:%i:%s') in_ist, TIME_FORMAT(pulang,'%H:%i:%s') pulang")
                ->where(['user_id'=>$uid, 'flow_date'=>$date])->get('dat_reader_mirror')->row_array();

            $differs = true;
            if ($mirror) {
                $differs = ($vals['datang']  !== ($mirror['datang']  ?: null))
                        || ($vals['out_ist'] !== ($mirror['out_ist'] ?: null))
                        || ($vals['in_ist']  !== ($mirror['in_ist']  ?: null))
                        || ($vals['pulang']  !== ($mirror['pulang']  ?: null));
            }

            $update = array_merge($vals, [
                'is_edited' => $differs ? 1 : 0,
                'edited_by' => $differs ? (int)$editor_id : null,
                'edited_at' => $differs ? $now : null,
                'updated_at'=> $now,
            ]);
            $this->db->where(['user_id'=>$uid, 'flow_date'=>$date])->update('dat_reader_work', $update);
            $n += $this->db->affected_rows() >= 0 ? 1 : 0;
        }
        return $n;
    }

    public function mark_pushed($pairs, $now) {
        foreach ($pairs as $p) {
            $this->db->where(['user_id'=>(int)$p['user_id'], 'flow_date'=>$p['flow_date']])
                     ->update('dat_reader_work', ['pushed_at'=>$now]);
        }
    }

    /** Normalisasi 'HH:MM' / 'HH:MM:SS' / kosong → 'HH:MM:SS' atau null. */
    private function _t($v) {
        $v = trim((string)$v);
        if ($v === '') return null;
        if (preg_match('/^\d{1,2}:\d{2}$/', $v))      return $v.':00';
        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $v)) return $v;
        return null;
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pray_day_model — akses tabel `pray_day` (lapis harian sholat editable).
 *
 * Sama pola Attendance_day_model tapi 6 slot (subuh/dzuhur/ashar/maghrib/isha/
 * friday) x in/out/late. Dipakai DatReader (tampilan "Absensi Mentah" ->
 * Jenis Mesin Sholat) untuk lihat + koreksi per-hari per-slot, terpisah dari
 * alur kerja supaya tombol sync/klasifikasi/dorong tidak tercampur semantik
 * (lihat Pray_classifier/Pray_deriver_model untuk arsitektur penuh).
 */
class Pray_day_model extends CI_Model {

    const PRAYERS = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];

    /** Baris harian sholat + identitas karyawan, dibatasi cabang & rentang tanggal. */
    public function get_range($branch_id, $from, $to, $user_ids = NULL) {
        $cols = "d.user_id, d.flow_date";
        foreach (self::PRAYERS as $p) {
            $cols .= ",TIME_FORMAT(d.{$p}_in,'%H:%i') AS {$p}_in";
            $cols .= ",TIME_FORMAT(d.{$p}_out,'%H:%i') AS {$p}_out";
            $cols .= ",d.{$p}_late AS {$p}_late";
            $cols .= ",TIME_FORMAT(d.m_{$p}_in,'%H:%i') AS m_{$p}_in";
            $cols .= ",TIME_FORMAT(d.m_{$p}_out,'%H:%i') AS m_{$p}_out";
        }
        $cols .= ", d.tap_count, d.all_taps, d.classify_method, d.needs_reclass, d.classified_at,
                    d.is_edited, d.edit_note, d.edited_by, d.edited_at,
                    u.employee_code, u.first_name, u.last_name";

        $this->db->select($cols, FALSE)
            ->from('pray_day d')
            ->join('users u', 'u.id = d.user_id')
            ->join('position p', 'p.id = u.position_id', 'left')
            ->where('d.flow_date >=', $from)
            ->where('d.flow_date <=', $to);

        if ($branch_id !== NULL) { $this->db->where('p.branch_id', (int) $branch_id); }
        if (!empty($user_ids))   { $this->db->where_in('d.user_id', $user_ids); }

        return $this->db->order_by('d.flow_date', 'ASC')
                        ->order_by('u.first_name', 'ASC')
                        ->get()->result_array();
    }

    /**
     * Semua karyawan aktif cabang -- dipakai sebagai scope sync/klasifikasi ulang.
     * Beda dari Attendance_day_model::user_ids_in_range() (yang cuma baca baris
     * attendance_day existing): pray_day bisa kosong total kalau cabang belum
     * pernah sinkron sholat, jadi scope harus SEMUA karyawan supaya sinkronisasi
     * pertama tetap membuat baris baru.
     */
    public function user_ids_in_range($branch_id, $from, $to) {
        $this->db->select('u.id')->from('users u')
            ->join('position p', 'p.id = u.position_id', 'left')
            ->where('u.active', 1);
        if ($branch_id !== NULL) { $this->db->where('p.branch_id', (int) $branch_id); }

        $out = [];
        foreach ($this->db->get()->result_array() as $r) { $out[] = (int) $r['id']; }
        return $out;
    }

    /**
     * Simpan koreksi admin per-hari per-slot sholat.
     * $edits: [{user_id, flow_date, subuh_in, subuh_out, ..., friday_in, friday_out}]
     *
     * Selalu ditandai is_edited=1 (ini jalur koreksi manual eksplisit) supaya
     * klasifikasi ulang berikutnya tidak menimpanya (lihat Pray_classifier::_save()).
     * Menit telat dihitung ulang dari window sholat cabang. Baris yang belum
     * pernah ada (tidak ada tap sama sekali) di-insert baru.
     *
     * Return jumlah baris tersimpan.
     */
    public function save_edits($edits, $editor_id, $now) {
        if (empty($edits)) { return 0; }

        $this->load->helper('late');

        $uids = [];
        foreach ($edits as $e) { $uids[(int) $e['user_id']] = TRUE; }
        $uid_list = array_keys($uids);

        $branch_of_user = [];
        $branch_ids = [];
        if (!empty($uid_list)) {
            $rows = $this->db->select('u.id, p.branch_id')->from('users u')
                ->join('position p', 'p.id = u.position_id', 'left')
                ->where_in('u.id', $uid_list)->get()->result_array();
            foreach ($rows as $r) {
                $branch_of_user[(int) $r['id']] = (int) $r['branch_id'];
                $branch_ids[(int) $r['branch_id']] = TRUE;
            }
        }

        $branch_window = [];
        if (!empty($branch_ids)) {
            $cols = 'id';
            foreach (self::PRAYERS as $p) { $cols .= ",{$p}_pray_time_range"; }
            $rows = $this->db->select($cols)->where_in('id', array_keys($branch_ids))->get('branch')->result_array();
            foreach ($rows as $r) { $branch_window[(int) $r['id']] = $r; }
        }

        $saved = 0;
        foreach ($edits as $e) {
            $uid  = (int) $e['user_id'];
            $date = $e['flow_date'];
            $bid  = isset($branch_of_user[$uid]) ? $branch_of_user[$uid] : NULL;
            $win  = ($bid !== NULL && isset($branch_window[$bid])) ? $branch_window[$bid] : NULL;

            $val = [
                'is_edited' => 1, 'edited_by' => (int) $editor_id, 'edited_at' => $now,
                'classify_method' => 'manual', 'needs_reclass' => 0, 'updated_at' => $now,
            ];
            foreach (self::PRAYERS as $p) {
                $in  = $this->normalize_time(isset($e[$p.'_in'])  ? $e[$p.'_in']  : NULL);
                $out = $this->normalize_time(isset($e[$p.'_out']) ? $e[$p.'_out'] : NULL);
                $late = 0;
                if ($win && $in !== NULL && $out !== NULL && !empty($win[$p.'_pray_time_range'])) {
                    $limit = date('H:i:s', strtotime($in.' +'.(int) $win[$p.'_pray_time_range'].' minutes'));
                    $late = late_minutes($limit, $out);
                }
                $val[$p.'_in'] = $in; $val[$p.'_out'] = $out; $val[$p.'_late'] = $late;
            }

            $exists = $this->db->select('user_id')
                ->where(['user_id' => $uid, 'flow_date' => $date])->get('pray_day')->row_array();
            if (empty($exists)) {
                $val['user_id'] = $uid; $val['flow_date'] = $date; $val['created_at'] = $now;
                $this->db->insert('pray_day', $val);
            } else {
                $this->db->where(['user_id' => $uid, 'flow_date' => $date])->update('pray_day', $val);
            }
            $saved++;
        }

        return $saved;
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

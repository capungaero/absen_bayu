<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Bpjs_model — konfigurasi BPJS (single row global) & riwayat pembayaran
 * BPJS per karyawan per bulan. Tidak menulis ke payroll_insentif/deduction
 * (integrasi gaji menyusul/manual). Lihat scripts/bpjs_schema.sql.
 */
class Bpjs_model extends CI_Model {

    private $config_table  = 'bpjs_config';
    private $payment_table = 'bpjs_payment';

    // ---- KONFIGURASI -------------------------------------------------

    /** Ambil row config (id=1), auto-seed bila kosong. */
    public function get_config() {
        $row = $this->db->order_by('id', 'ASC')->limit(1)
                        ->get($this->config_table)->row_array();
        if (!$row) {
            $this->db->insert($this->config_table, [
                'kesehatan_employee'       => 31830,
                'ketenagakerjaan_employee' => 63659,
                'mandiri_insentif'         => 101500,
                'updated_at'               => date('Y-m-d H:i:s'),
            ]);
            $row = $this->db->order_by('id', 'ASC')->limit(1)
                            ->get($this->config_table)->row_array();
        }
        return $row;
    }

    public function save_config($data) {
        $existing = $this->get_config();
        $this->db->where('id', $existing['id'])->update($this->config_table, $data);
        return true;
    }

    // ---- DAFTAR PEMBAYARAN ------------------------------------------

    /**
     * Daftar karyawan aktif satu cabang + status BPJS pada periode.
     * LEFT JOIN agar karyawan yang belum punya baris tetap muncul.
     */
    public function get_payments($branch_id, $month, $year) {
        return $this->db->query("
            SELECT u.id AS user_id,
                   u.first_name, u.last_name, u.employee_code,
                   p.position_name,
                   bp.pay_mode, bp.kesehatan_amount, bp.ketenagakerjaan_amount,
                   bp.mandiri_insentif_amount, bp.proof_path, bp.status,
                   bp.acc_at
            FROM users u
            JOIN position p ON p.id = u.position_id
            LEFT JOIN bpjs_payment bp
                   ON bp.user_id = u.id AND bp.month = ? AND bp.year = ?
            WHERE p.branch_id = ? AND u.active = 1
            ORDER BY u.first_name, u.last_name
        ", [(int)$month, (int)$year, (int)$branch_id])->result_array();
    }

    /** Cek karyawan benar milik cabang (cegah akses lintas cabang). */
    public function user_in_branch($user_id, $branch_id) {
        return $this->db->query("
            SELECT 1 FROM users u JOIN position p ON p.id = u.position_id
            WHERE u.id = ? AND p.branch_id = ? LIMIT 1
        ", [(int)$user_id, (int)$branch_id])->num_rows() > 0;
    }

    private function _find($user_id, $month, $year) {
        return $this->db->get_where($this->payment_table, [
            'user_id' => (int)$user_id, 'month' => (int)$month, 'year' => (int)$year,
        ])->row_array();
    }

    /**
     * Set mode pembayaran (kantor / mandiri) untuk satu karyawan-periode.
     * kantor  → isi potongan kesehatan & ketenagakerjaan dari config, status approved.
     * mandiri → reset potongan & insentif ke 0, status balik pending (perlu ACC bukti).
     */
    public function set_pay_mode($user_id, $month, $year, $mode, $cfg) {
        $now = date('Y-m-d H:i:s');
        if ($mode === 'kantor') {
            $fields = [
                'pay_mode'                => 'kantor',
                'kesehatan_amount'        => (int)$cfg['kesehatan_employee'],
                'ketenagakerjaan_amount'  => (int)$cfg['ketenagakerjaan_employee'],
                'mandiri_insentif_amount' => 0,
                'status'                  => 'approved',
                'updated_at'              => $now,
            ];
        } else {
            $fields = [
                'pay_mode'                => 'mandiri',
                'kesehatan_amount'        => 0,
                'ketenagakerjaan_amount'  => 0,
                'mandiri_insentif_amount' => 0,
                'status'                  => 'pending',
                'acc_by'                  => null,
                'acc_at'                  => null,
                'updated_at'              => $now,
            ];
        }

        $existing = $this->_find($user_id, $month, $year);
        if ($existing) {
            $this->db->where('id', $existing['id'])->update($this->payment_table, $fields);
        } else {
            $fields['user_id']    = (int)$user_id;
            $fields['month']      = (int)$month;
            $fields['year']       = (int)$year;
            $fields['created_at'] = $now;
            $this->db->insert($this->payment_table, $fields);
        }
        return true;
    }

    // ---- INTEGRASI KE PAYROLL ---------------------------------------
    // Nama master row (per cabang) tempat BPJS masuk ke gaji.
    const DED_KESEHATAN       = 'BPJS Kesehatan';
    const DED_KETENAGAKERJAAN = 'BPJS Ketenagakerjaan';
    const INS_MANDIRI         = 'BPJS Mandiri';

    private function _branch_of_user($user_id) {
        $r = $this->db->select('position.branch_id')
            ->join('position', 'position.id = users.position_id')
            ->where('users.id', (int)$user_id)->get('users')->row_array();
        return $r ? (int)$r['branch_id'] : 0;
    }

    /** Master deduction per-cabang (buat bila belum ada, aktifkan bila non-aktif). */
    private function _deduction_id($branch_id, $name) {
        $row = $this->db->where('branch_id', $branch_id)->where('deduction_name', $name)
            ->where('deleted_at', null)->get('deduction')->row_array();
        if ($row) {
            if ($row['is_active'] !== '1') $this->db->where('id', $row['id'])->update('deduction', ['is_active'=>'1']);
            return (int)$row['id'];
        }
        $this->db->insert('deduction', ['branch_id'=>$branch_id, 'deduction_name'=>$name,
            'is_active'=>'1', 'created_at'=>date('Y-m-d H:i:s')]);
        return (int)$this->db->insert_id();
    }

    /** Master insentif per-cabang (formula 'none' → tidak auto-apply, hanya dari payroll_insentif). */
    private function _insentif_id($branch_id, $name) {
        $this->db->where('branch_id', $branch_id);
        if ($name === self::INS_MANDIRI) {
            $this->db->group_start()->where('insentif_name', $name)->or_where('insentif_name', 'Komisi Soskes')->group_end();
        } else {
            $this->db->where('insentif_name', $name);
        }
        $row = $this->db->where('deleted_at', null)->get('insentif')->row_array();
        if ($row) {
            if ($row['is_active'] !== '1') $this->db->where('id', $row['id'])->update('insentif', ['is_active'=>'1']);
            return (int)$row['id'];
        }
        $this->db->insert('insentif', ['branch_id'=>$branch_id, 'insentif_name'=>$name,
            'formula'=>'none', 'nominal'=>null, 'is_active'=>'1', 'created_at'=>date('Y-m-d H:i:s')]);
        return (int)$this->db->insert_id();
    }

    private function _cleanup_bpjs_payroll($user_id, $branch_id, $month, $year) {
        $ded_rows = $this->db->where('branch_id', $branch_id)
            ->group_start()
                ->like('deduction_name', 'BPJS')
            ->group_end()
            ->get('deduction')->result_array();
        $del_deds = array_map(fn($r) => (int)$r['id'], $ded_rows);

        $ins_rows = $this->db->where('branch_id', $branch_id)
            ->group_start()
                ->like('insentif_name', 'BPJS')
                ->or_like('insentif_name', 'Soskes')
            ->group_end()
            ->get('insentif')->result_array();
        $del_ins = array_map(fn($r) => (int)$r['id'], $ins_rows);

        if (!empty($del_deds)) {
            $this->db->where('user_id', $user_id)->where('deduction_month', $month)->where('deduction_year', $year)
                ->where_in('deduction_id', $del_deds)->delete('payroll_deduction');
        }
        if (!empty($del_ins)) {
            $this->db->where('user_id', $user_id)->where('insentif_month', $month)->where('insentif_year', $year)
                ->where_in('insentif_id', $del_ins)->delete('payroll_insentif');
        }
    }

    private function _payroll_locked($branch_id, $month, $year) {
        $this->load->model('payroll_model', 'payroll');
        return $this->payroll->get_detail([
            'branch_id'=>(int)$branch_id, 'month'=>(int)$month, 'year'=>(int)$year,
        ])->num_rows() > 0;
    }

    /**
     * Terapkan status BPJS satu karyawan-periode ke tabel payroll (idempoten):
     * - kantor              → tulis payroll_deduction (kesehatan + ketenagakerjaan) & hapus komisi BPJS
     * - mandiri & approved  → tulis payroll_insentif (BPJS Mandiri / Soskes) & hapus potongan BPJS
     * - selain itu          → hapus baris BPJS dari payroll
     * Memakai nominal snapshot di bpjs_payment (diisi dari config saat tandai/ACC).
     * Bila penggajian periode sudah final → tidak menulis (return locked=true).
     */
    public function sync_payroll($user_id, $month, $year) {
        $branch_id = $this->_branch_of_user($user_id);
        if (!$branch_id) return ['ok'=>false, 'locked'=>false];
        if ($this->_payroll_locked($branch_id, $month, $year)) return ['ok'=>false, 'locked'=>true];

        $ded_kes = $this->_deduction_id($branch_id, self::DED_KESEHATAN);
        $ded_ker = $this->_deduction_id($branch_id, self::DED_KETENAGAKERJAAN);
        $ins_man = $this->_insentif_id($branch_id, self::INS_MANDIRI);

        // Clean slate: hapus semua potongan & komisi BPJS lama untuk karyawan-periode ini.
        $this->_cleanup_bpjs_payroll($user_id, $branch_id, $month, $year);

        $row = $this->_find($user_id, $month, $year);
        $now = date('Y-m-d H:i:s');
        if ($row) {
            if ($row['pay_mode'] === 'kantor') {
                $batch = [];
                if ((int)$row['kesehatan_amount'] > 0) $batch[] = ['user_id'=>(int)$user_id, 'deduction_id'=>$ded_kes,
                    'deduction_month'=>(int)$month, 'deduction_year'=>(int)$year, 'deduction_amount'=>(int)$row['kesehatan_amount'],
                    'deduction_note'=>'BPJS Kesehatan (kantor)', 'created_at'=>$now];
                if ((int)$row['ketenagakerjaan_amount'] > 0) $batch[] = ['user_id'=>(int)$user_id, 'deduction_id'=>$ded_ker,
                    'deduction_month'=>(int)$month, 'deduction_year'=>(int)$year, 'deduction_amount'=>(int)$row['ketenagakerjaan_amount'],
                    'deduction_note'=>'BPJS Ketenagakerjaan (kantor)', 'created_at'=>$now];
                if ($batch) $this->db->insert_batch('payroll_deduction', $batch);
            } else if ($row['pay_mode'] === 'mandiri' && $row['status'] === 'approved' && (int)$row['mandiri_insentif_amount'] > 0) {
                $this->db->insert('payroll_insentif', ['user_id'=>(int)$user_id, 'insentif_id'=>$ins_man,
                    'insentif_month'=>(int)$month, 'insentif_year'=>(int)$year,
                    'insentif_amount'=>(int)$row['mandiri_insentif_amount'], 'created_at'=>$now]);
            }
        }
        return ['ok'=>true, 'locked'=>false];
    }

    /** Re-snapshot nominal dari config (dipakai tombol Sinkron agar perubahan config ikut terterap). */
    private function _refresh_amounts($row, $cfg) {
        if ($row['pay_mode'] === 'kantor') {
            $this->db->where('id', $row['id'])->update($this->payment_table, [
                'kesehatan_amount'=>(int)$cfg['kesehatan_employee'],
                'ketenagakerjaan_amount'=>(int)$cfg['ketenagakerjaan_employee'],
                'mandiri_insentif_amount'=>0,
            ]);
            $row['kesehatan_amount'] = (int)$cfg['kesehatan_employee'];
            $row['ketenagakerjaan_amount'] = (int)$cfg['ketenagakerjaan_employee'];
        } else if ($row['pay_mode'] === 'mandiri' && $row['status'] === 'approved') {
            $this->db->where('id', $row['id'])->update($this->payment_table, [
                'mandiri_insentif_amount'=>(int)$cfg['mandiri_insentif'],
                'kesehatan_amount'=>0, 'ketenagakerjaan_amount'=>0,
            ]);
            $row['mandiri_insentif_amount'] = (int)$cfg['mandiri_insentif'];
        }
        return $row;
    }

    /** Sinkron seluruh karyawan satu cabang-periode ke payroll (pakai config terbaru). */
    public function sync_payroll_period($branch_id, $month, $year, $cfg) {
        $rows = $this->db->query("
            SELECT bp.* FROM bpjs_payment bp
            JOIN users u ON u.id = bp.user_id
            JOIN position p ON p.id = u.position_id
            WHERE p.branch_id = ? AND bp.month = ? AND bp.year = ?
        ", [(int)$branch_id, (int)$month, (int)$year])->result_array();

        if ($this->_payroll_locked($branch_id, $month, $year)) {
            return ['count'=>0, 'locked'=>true, 'total'=>count($rows)];
        }
        $count = 0;
        foreach ($rows as $r) {
            $this->_refresh_amounts($r, $cfg);
            $res = $this->sync_payroll($r['user_id'], $month, $year);
            if ($res['ok']) $count++;
        }
        return ['count'=>$count, 'locked'=>false, 'total'=>count($rows)];
    }

    /** ACC bukti pembayaran mandiri → status approved + isi insentif dari config. */
    public function acc_payment($user_id, $month, $year, $cfg, $acc_by) {
        $now = date('Y-m-d H:i:s');
        $existing = $this->_find($user_id, $month, $year);
        $fields = [
            'pay_mode'                => 'mandiri',
            'mandiri_insentif_amount' => (int)$cfg['mandiri_insentif'],
            'kesehatan_amount'        => 0,
            'ketenagakerjaan_amount'  => 0,
            'status'                  => 'approved',
            'acc_by'                  => (int)$acc_by,
            'acc_at'                  => $now,
            'updated_at'              => $now,
        ];
        if ($existing) {
            $this->db->where('id', $existing['id'])->update($this->payment_table, $fields);
        } else {
            $fields['user_id']    = (int)$user_id;
            $fields['month']      = (int)$month;
            $fields['year']       = (int)$year;
            $fields['created_at'] = $now;
            $this->db->insert($this->payment_table, $fields);
        }
        return true;
    }
}

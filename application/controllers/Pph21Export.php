<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Pph21Export — exporter kertas kerja PPh 21 per CV (tools/pph21_export).
 *
 * Mengisi kolom yang datanya ada di database:
 *   - identitas: users (nama, jabatan, NIK=npwp_number, PTKP=ptkp_status)
 *   - Gaji Pokok = payroll_detail.salary_thp (THP final e-absensi)
 *   - Tunjangan/Cash Bon = potongan CASHBON + PIUTANG KANVAS (payroll_deduction,
 *     keyed deduction_month/deduction_year — pinjaman, penambah bruto)
 *   - premi JKK/JKM/KES perusahaan utk karyawan yang punya potongan BPJS bulan itu
 * Kolom data luar absensi (konsumsi, uang jalan kanvas, subsidi, bonus) dibiarkan
 * kosong untuk diisi manual; seluruh rumus TER/gross-up sudah terpasang
 * (lihat libraries/Pph21_workbook.php).
 *
 * Auth: ion_auth session + role admin/admin-branch/hr (pola DatReader).
 */
class Pph21Export extends CI_Controller {

    private $role;

    public function __construct() {
        parent::__construct();
        if (!$this->ion_auth->logged_in()) {
            $this->_json(['error' => 'Unauthorized'], 401); exit;
        }
        $this->role = $this->ion_auth->get_users_groups()->row()->name;
        if (!in_array($this->role, ['admin', 'admin-branch', 'hr'])) {
            $this->_json(['error' => 'Forbidden'], 403); exit;
        }
        $this->config->load('pph21_export');
        $this->load->library('pph21_workbook');
    }

    private function _json($data, $code = 200) {
        $this->output->set_status_header($code)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /** Daftar periode payroll (terbaru dulu) untuk dropdown. */
    public function periods() {
        $rows = $this->db->select('p.id, p.month, p.year, b.branch_name')
            ->from('payroll p')->join('branch b', 'b.id = p.branch_id')
            ->order_by('p.year DESC, p.month DESC, p.branch_id')->get()->result();
        $this->_json(['periods' => $rows]);
    }

    /** Daftar CV (subdivision) + jumlah karyawan utk satu payroll. */
    public function cvs() {
        $pid = (int)$this->input->get('payroll_id');
        $p = $this->_payroll($pid);
        if (!$p) { $this->_json(['error' => 'Payroll tidak ditemukan'], 404); return; }
        $npwp = $this->config->item('pph21_npwp');
        $rows = $this->db->select('s.id, s.subdivision_name, COUNT(pd.id) AS n')
            ->from('payroll_detail pd')
            ->join('users u', 'u.id = pd.user_id')
            ->join('subdivision s', 's.id = u.subdivision_id', 'left')
            ->where('pd.payroll_id', $pid)
            ->group_by('s.id, s.subdivision_name')->order_by('s.subdivision_name')
            ->get()->result();
        foreach ($rows as $r) {
            $r->subdivision_name = trim((string)$r->subdivision_name) !== '' ? trim($r->subdivision_name) : '(TANPA SUBDIVISI)';
            $r->npwp = isset($npwp[$r->id]) ? $npwp[$r->id] : '';
        }
        $this->_json(['payroll' => $p, 'cvs' => $rows]);
    }

    /** Download XLSX satu CV: pph21_export/export?payroll_id=..&subdivision_id=.. */
    public function export() {
        $pid = (int)$this->input->get('payroll_id');
        $sid = (int)$this->input->get('subdivision_id');
        $p = $this->_payroll($pid);
        if (!$p) { $this->_json(['error' => 'Payroll tidak ditemukan'], 404); return; }
        $data = $this->_cv_rows($pid, $p, $sid);
        if (!$data['rows']) { $this->_json(['error' => 'Tidak ada karyawan utk CV ini'], 404); return; }
        $ss = $this->pph21_workbook->build($data['meta'], $data['rows']);
        $fname = sprintf('%02d %s PPh21 - %s.xlsx', $p->month, $this->_month_name($p->month), $data['meta']['cv']);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.str_replace('"', '', $fname).'"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }

    /** Download ZIP semua CV satu payroll: pph21_export/export_all?payroll_id=.. */
    public function export_all() {
        $pid = (int)$this->input->get('payroll_id');
        $p = $this->_payroll($pid);
        if (!$p) { $this->_json(['error' => 'Payroll tidak ditemukan'], 404); return; }
        $sids = $this->db->select('DISTINCT(u.subdivision_id) AS sid', false)
            ->from('payroll_detail pd')->join('users u', 'u.id = pd.user_id')
            ->where('pd.payroll_id', $pid)->get()->result();
        $tmp = tempnam(sys_get_temp_dir(), 'pph21');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $count = 0;
        foreach ($sids as $s) {
            $data = $this->_cv_rows($pid, $p, (int)$s->sid);
            if (!$data['rows']) continue;
            $ss = $this->pph21_workbook->build($data['meta'], $data['rows']);
            $f = tempnam(sys_get_temp_dir(), 'wb');
            (new Xlsx($ss))->save($f);
            $zip->addFromString(sprintf('%02d %s PPh21 - %s.xlsx',
                $p->month, $this->_month_name($p->month), $data['meta']['cv']), file_get_contents($f));
            @unlink($f);
            $count++;
        }
        $zip->close();
        if (!$count) { @unlink($tmp); $this->_json(['error' => 'Tidak ada data'], 404); return; }
        $fname = sprintf('PPh21 %02d-%d %s (semua CV).zip', $p->month, $p->year, $p->branch_name);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.str_replace('"', '', $fname).'"');
        header('Content-Length: '.filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    // ------------------------------------------------------------------ helpers

    private function _payroll($pid) {
        return $this->db->select('p.id, p.month, p.year, p.branch_id, b.branch_name')
            ->from('payroll p')->join('branch b', 'b.id = p.branch_id')
            ->where('p.id', $pid)->get()->row();
    }

    /** Data karyawan satu CV: identitas + THP + cashbon/kanvas + penanda BPJS. */
    private function _cv_rows($pid, $p, $sid) {
        $emps = $this->db->select("pd.user_id, TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS name,
                COALESCE(pos.position_name, '') AS position, COALESCE(u.npwp_number, '') AS nik,
                COALESCE(u.ptkp_status, '') AS ptkp, pd.salary_thp, COALESCE(s.subdivision_name,'') AS cv", false)
            ->from('payroll_detail pd')
            ->join('users u', 'u.id = pd.user_id')
            ->join('position pos', 'pos.id = u.position_id', 'left')
            ->join('subdivision s', 's.id = u.subdivision_id', 'left')
            ->where('pd.payroll_id', $pid)
            ->where($sid ? 'u.subdivision_id = '.$sid : '(u.subdivision_id IS NULL OR u.subdivision_id = 0)')
            ->order_by('name')->get()->result_array();
        if (!$emps) return ['meta' => [], 'rows' => []];

        $uids = array_column($emps, 'user_id');
        $addback = $this->_ded_sums($p, $uids, $this->config->item('pph21_addback_deductions'));
        $bpjs    = $this->_ded_sums($p, $uids, $this->config->item('pph21_bpjs_markers'));

        $rows = [];
        foreach ($emps as $e) {
            $uid = $e['user_id'];
            $rows[] = [
                'name'     => $e['name'],
                'position' => $e['position'],
                'nik'      => preg_replace('/\D/', '', $e['nik']),
                'ptkp'     => strtoupper(str_replace(' ', '', $e['ptkp'])),
                'thp'      => (float)$e['salary_thp'],
                'cashbon'  => isset($addback[$uid]) ? (float)$addback[$uid] : 0.0,
                'bpjs'     => !empty($bpjs[$uid]),
            ];
        }
        $npwp_map = $this->config->item('pph21_npwp');
        $meta = [
            'cv'     => trim($emps[0]['cv']) !== '' ? trim($emps[0]['cv']) : 'TANPA SUBDIVISI',
            'npwp'   => isset($npwp_map[$sid]) ? $npwp_map[$sid] : '',
            'branch' => $p->branch_name,
            'month'  => (int)$p->month,
            'year'   => (int)$p->year,
            'premi'  => $this->config->item('pph21_premi'),
        ];
        return ['meta' => $meta, 'rows' => $rows];
    }

    /** SUM potongan bernama tertentu per user utk bulan payroll (keyed month/year). */
    private function _ded_sums($p, $uids, $names) {
        if (!$uids || !$names) return [];
        $rows = $this->db->select('x.user_id, SUM(x.deduction_amount) AS tot')
            ->from('payroll_deduction x')
            ->join('deduction d', 'd.id = x.deduction_id')
            ->where('x.deduction_month', (int)$p->month)
            ->where('x.deduction_year', (int)$p->year)
            ->where_in('d.deduction_name', $names)
            ->where_in('x.user_id', $uids)
            ->group_by('x.user_id')->having('tot > 0')->get()->result();
        $out = [];
        foreach ($rows as $r) $out[$r->user_id] = $r->tot;
        return $out;
    }

    private function _month_name($m) {
        $n = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return $n[(int)$m];
    }
}

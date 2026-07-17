<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Pph21Final — kertas kerja PPh 21 MASA PAJAK TERAKHIR (PMK 168/2023 Ps. 15).
 *
 * Karyawan yang masuk: hanya yang masa pajak terakhirnya = bulan payroll dipilih
 * (bulan 12 = semua yang masih aktif; bulan lain = yang nonaktif bulan itu,
 * dari users.last_status saat active=0).
 * Bruto & PPh TER tiap bulan sebelumnya dihitung ulang dari database dengan
 * mesin yang sama dgn export bulanan (Pph21Export); PPh masa terakhir =
 * PPh Ps.17 setahun - total dipotong, dgn tunjangan pajak gross-up fixed-point.
 *
 * Auth: ion_auth session + role admin/admin-branch/hr (pola Pph21Export).
 */
class Pph21Final extends CI_Controller {

    public function __construct() {
        parent::__construct();
        if (!$this->ion_auth->logged_in()) $this->_die(['error' => 'Unauthorized'], 401);
        $role = $this->ion_auth->get_users_groups()->row()->name;
        if (!in_array($role, ['admin', 'admin-branch', 'hr'])) $this->_die(['error' => 'Forbidden'], 403);
        $this->config->load('pph21_export');
        $this->load->library('pph21_workbook');       // tabel TER + kategori
        $this->load->library('pph21_final_workbook'); // builder + PTKP/Ps17
    }

    private function _die($data, $code) {
        $this->output->set_status_header($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data); exit;
    }

    private function _json($data, $code = 200) {
        $this->output->set_status_header($code)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /** Download XLSX satu CV: pph21final/export?payroll_id=..&subdivision_id=.. */
    public function export() {
        $pid = (int)$this->input->get('payroll_id');
        $sid = (int)$this->input->get('subdivision_id');
        $p = $this->_payroll($pid);
        if (!$p) { $this->_json(['error' => 'Payroll tidak ditemukan'], 404); return; }
        $data = $this->_final_rows($p, $sid);
        if (!$data['rows']) {
            $this->_json(['error' => 'Tidak ada karyawan dengan masa pajak terakhir '
                .sprintf('%02d/%d', $p->month, $p->year).' pada CV ini'], 404);
            return;
        }
        $ss = $this->pph21_final_workbook->build($data['meta'], $data['rows']);
        $fname = sprintf('%02d %s PPh21 MASA TERAKHIR - %s.xlsx',
            $p->month, $this->_month_name($p->month), $data['meta']['cv']);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.str_replace('"', '', $fname).'"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }

    /** Download ZIP semua CV: pph21final/export_all?payroll_id=.. */
    public function export_all() {
        $pid = (int)$this->input->get('payroll_id');
        $p = $this->_payroll($pid);
        if (!$p) { $this->_json(['error' => 'Payroll tidak ditemukan'], 404); return; }
        $sids = $this->db->query(
            'SELECT DISTINCT sid FROM ('
            .'SELECT COALESCE(u.subdivision_id, 0) AS sid FROM payroll_detail pd JOIN users u ON u.id = pd.user_id WHERE pd.payroll_id = '.(int)$pid
            .' UNION SELECT subdivision_id AS sid FROM pph21_roster WHERE year = '.(int)$p->year
            .') x')->result();
        $tmp = tempnam(sys_get_temp_dir(), 'pph21f');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $count = 0;
        foreach ($sids as $s) {
            $data = $this->_final_rows($p, (int)$s->sid);
            if (!$data['rows']) continue;
            $ss = $this->pph21_final_workbook->build($data['meta'], $data['rows']);
            $f = tempnam(sys_get_temp_dir(), 'wbf');
            (new Xlsx($ss))->save($f);
            $zip->addFromString(sprintf('%02d %s PPh21 MASA TERAKHIR - %s.xlsx',
                $p->month, $this->_month_name($p->month), $data['meta']['cv']), file_get_contents($f));
            @unlink($f);
            $count++;
        }
        $zip->close();
        if (!$count) {
            @unlink($tmp);
            $this->_json(['error' => 'Tidak ada karyawan dengan masa pajak terakhir '
                .sprintf('%02d/%d', $p->month, $p->year).' di semua CV'], 404);
            return;
        }
        $fname = sprintf('PPh21 MASA TERAKHIR %02d-%d %s (semua CV).zip', $p->month, $p->year, $p->branch_name);
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

    /**
     * Baris kertas kerja masa pajak terakhir satu CV (urut roster).
     * Kandidat = roster tahunan + karyawan payroll bulan ini; disaring
     * masa_akhir == bulan payroll; baris tanpa penghasilan setahun dilewati.
     */
    private function _final_rows($p, $sid) {
        $M = (int)$p->month;

        // ---- kandidat: roster (punya user_id) + karyawan payroll bulan ini
        $cand = []; $order = 0;
        foreach ($this->db->from('pph21_roster')->where('year', (int)$p->year)
                 ->where('subdivision_id', $sid)->order_by('sort_order')->get()->result_array() as $rr) {
            if ($rr['user_id'] === null) continue; // roster lama tanpa user_id: tidak bisa agregasi
            $cand[(int)$rr['user_id']] = ['order' => $order++];
        }
        foreach ($this->db->select('pd.user_id')->from('payroll_detail pd')
                 ->join('users u', 'u.id = pd.user_id')->where('pd.payroll_id', (int)$p->id)
                 ->where($sid ? 'u.subdivision_id = '.$sid : '(u.subdivision_id IS NULL OR u.subdivision_id = 0)')
                 ->get()->result() as $e) {
            if (!isset($cand[(int)$e->user_id])) $cand[(int)$e->user_id] = ['order' => $order++];
        }
        if (!$cand) return ['meta' => [], 'rows' => []];

        // ---- data user + filter masa_akhir == bulan payroll
        $users = [];
        foreach ($this->db->select("id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) AS name,
                COALESCE(npwp_number,'') AS nik, COALESCE(ptkp_status,'') AS ptkp,
                join_date, active, last_status", false)
                 ->from('users')->where_in('id', array_keys($cand))->get()->result_array() as $u) {
            $users[(int)$u['id']] = $u;
        }
        $uids = [];
        foreach ($cand as $uid => $c) {
            if (!isset($users[$uid])) continue;
            $u = $users[$uid];
            list($ma, $mk) = $this->_masa($p->year, $u['join_date'], $u['active'], $u['last_status']);
            if ($mk !== $M) continue;
            $uids[$uid] = ['order' => $c['order'], 'masa_awal' => $ma, 'masa_akhir' => $mk];
        }
        if (!$uids) return ['meta' => [], 'rows' => []];

        // ---- agregasi bulanan Jan..M dari semua payroll tahun berjalan (cabang sama)
        $premi = $this->config->item('pph21_premi');
        $premi_total = $premi['jkk'] + $premi['jkm'] + $premi['kes'];
        $agg = []; // uid => ['bulanan'=>[m=>['bruto_gu','pph']], 'bruto_akhir'=>..]
        foreach (array_keys($uids) as $uid) $agg[$uid] = ['bulanan' => [], 'bruto_akhir' => 0.0];

        $payrolls = $this->db->select('id, month')->from('payroll')
            ->where('year', (int)$p->year)->where('branch_id', (int)$p->branch_id)
            ->where('month <=', $M)->order_by('month')->get()->result();
        $ids = array_keys($uids);
        foreach ($payrolls as $pr) {
            $m = (int)$pr->month;
            $thp = [];
            foreach ($this->db->select('user_id, salary_thp')->from('payroll_detail')
                     ->where('payroll_id', (int)$pr->id)->where_in('user_id', $ids)
                     ->get()->result() as $d) {
                $thp[(int)$d->user_id] = (float)$d->salary_thp;
            }
            $addback = $this->_ded_sums($m, (int)$p->year, $ids, $this->config->item('pph21_addback_deductions'));
            $bpjs    = $this->_ded_sums($m, (int)$p->year, $ids, $this->config->item('pph21_bpjs_markers'));
            $manual  = $this->_manual_rows((int)$pr->id, $ids);
            foreach ($ids as $uid) {
                $mn = isset($manual[$uid]) ? $manual[$uid] : null;
                $bruto = (isset($thp[$uid]) ? $thp[$uid] : 0.0)
                       + (isset($addback[$uid]) ? (float)$addback[$uid] : 0.0)
                       + ($mn ? (float)$mn->tunjangan + (float)$mn->insentif + (float)$mn->subsidi + (float)$mn->bonus : 0.0)
                       + (!empty($bpjs[$uid]) ? $premi_total : 0.0);
                if ($bruto <= 0) continue;
                if ($m < $M) {
                    // TER gross-up iteratif 3 tahap — identik dgn export bulanan
                    $cat = Pph21_workbook::ter_category($users[$uid]['ptkp']);
                    $t1 = $this->_ter_rate($bruto, $cat);
                    $t2 = $this->_ter_rate($bruto / (1 - $t1 / 100), $cat);
                    $rate = $this->_ter_rate($bruto / (1 - $t2 / 100), $cat);
                    $gu = $rate > 0 ? $bruto / (1 - $rate / 100) : $bruto;
                    if (!isset($agg[$uid]['bulanan'][$m])) $agg[$uid]['bulanan'][$m] = ['bruto_gu' => 0.0, 'pph' => 0.0];
                    $agg[$uid]['bulanan'][$m]['bruto_gu'] += $gu;
                    $agg[$uid]['bulanan'][$m]['pph']      += $gu * $rate / 100;
                } else {
                    $agg[$uid]['bruto_akhir'] += $bruto; // bulan masa terakhir: TANPA TER/GU
                }
            }
        }

        // ---- hitung tunjangan pajak masa terakhir (gross-up fixed-point)
        $rows = [];
        foreach ($uids as $uid => $c) {
            $u = $users[$uid];
            $sum_gu = $sum_pph = 0.0;
            foreach ($agg[$uid]['bulanan'] as $b) { $sum_gu += $b['bruto_gu']; $sum_pph += $b['pph']; }
            $bruto_akhir = $agg[$uid]['bruto_akhir'];
            if ($sum_gu + $bruto_akhir <= 0) continue; // nihil setahun: tidak ada yang dihitung ulang
            $n = $c['masa_akhir'] - $c['masa_awal'] + 1;
            $ptkp_amt = Pph21_final_workbook::ptkp_amount($u['ptkp']);
            $X = 0.0; $t = 0.0;
            for ($i = 0; $i < 60; $i++) {
                $bt  = $sum_gu + $bruto_akhir + $X;
                $bj  = min(0.05 * $bt, 500000 * $n);
                $pkp = max(0, floor(($bt - $bj - $ptkp_amt) / 1000) * 1000);
                $t   = Pph21_final_workbook::ps17($pkp);
                $nX  = max(0.0, round($t - $sum_pph, 2));
                if (abs($nX - $X) < 0.005) { $X = $nX; break; }
                $X = $nX;
            }
            $rows[] = [
                'order'      => $c['order'],
                'name'       => $u['name'],
                'nik'        => preg_replace('/\D/', '', $u['nik']),
                'ptkp'       => strtoupper(str_replace(' ', '', $u['ptkp'])),
                'masa_awal'  => $c['masa_awal'],
                'masa_akhir' => $c['masa_akhir'],
                'n_bulan'    => $n,
                'bulanan'    => $agg[$uid]['bulanan'],
                'bruto_akhir'=> $bruto_akhir,
                'tunj_pajak' => $X,
            ];
        }
        usort($rows, function ($a, $b) { return $a['order'] - $b['order']; });

        $cv_name = '';
        if ($sid) {
            $s = $this->db->select('subdivision_name')->from('subdivision')->where('id', $sid)->get()->row();
            $cv_name = $s ? trim($s->subdivision_name) : '';
        }
        $npwp_map = $this->config->item('pph21_npwp');
        $meta = [
            'cv'     => $cv_name !== '' ? $cv_name : 'TANPA SUBDIVISI',
            'npwp'   => isset($npwp_map[$sid]) ? $npwp_map[$sid] : '',
            'branch' => $p->branch_name,
            'month'  => $M,
            'year'   => (int)$p->year,
        ];
        return ['meta' => $meta, 'rows' => $rows];
    }

    /** Masa perolehan penghasilan [awal, akhir] — logika sama dgn Pph21Export::_masa(). */
    private function _masa($year, $join_date, $active, $last_status) {
        $awal = 1;
        if ($join_date && (int)substr($join_date, 0, 4) === (int)$year) {
            $awal = max(1, min(12, (int)substr($join_date, 5, 2)));
        }
        $akhir = 12;
        if ((string)$active === '0' && $last_status && (int)substr($last_status, 0, 4) === (int)$year) {
            $akhir = max(1, min(12, (int)substr($last_status, 5, 2)));
        }
        if ($akhir < $awal) $akhir = $awal;
        return [$awal, $akhir];
    }

    /** Tarif TER bulanan (tabel bersama Pph21_workbook). Batas eksklusif: > limit. */
    private function _ter_rate($gross, $cat) {
        $tables = Pph21_workbook::ter_tables();
        $rate = 0;
        foreach ($tables[$cat] as $row) {
            if ($row[0] == 0 || $gross > $row[0]) $rate = $row[1];
            else break;
        }
        return $rate;
    }

    /** SUM potongan bernama tertentu per user utk satu bulan (keyed month/year). */
    private function _ded_sums($month, $year, $uids, $names) {
        if (!$uids || !$names) return [];
        $rows = $this->db->select('x.user_id, SUM(x.deduction_amount) AS tot')
            ->from('payroll_deduction x')
            ->join('deduction d', 'd.id = x.deduction_id')
            ->where('x.deduction_month', (int)$month)
            ->where('x.deduction_year', (int)$year)
            ->where_in('d.deduction_name', $names)
            ->where_in('x.user_id', $uids)
            ->group_by('x.user_id')->having('tot > 0')->get()->result();
        $out = [];
        foreach ($rows as $r) $out[(int)$r->user_id] = $r->tot;
        return $out;
    }

    /** Nilai input manual (tools/pph21_manual) per user utk satu payroll. */
    private function _manual_rows($pid, $uids) {
        if (!$uids || !$this->db->table_exists('pph21_manual')) return [];
        $out = [];
        foreach ($this->db->from('pph21_manual')->where('payroll_id', (int)$pid)
                 ->where_in('user_id', $uids)->get()->result() as $m) {
            $out[(int)$m->user_id] = $m;
        }
        return $out;
    }

    private function _month_name($m) {
        $n = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return $n[(int)$m];
    }
}

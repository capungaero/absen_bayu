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
            $this->_die(['error' => 'Unauthorized'], 401);
        }
        $this->role = $this->ion_auth->get_users_groups()->row()->name;
        if (!in_array($this->role, ['admin', 'admin-branch', 'hr'])) {
            $this->_die(['error' => 'Forbidden'], 403);
        }
        $this->config->load('pph21_export');
        $this->load->library('pph21_workbook');
        $this->load->library('pph21_np_calc'); // format angka XML (xf) — pola Bp21Bulk
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

    /** Besaran premi JKK/JKM berlaku (override tersimpan, fallback config). */
    public function settings() {
        $premi = $this->_premi();
        $this->_json(['jkk' => (float)$premi['jkk'], 'jkm' => (float)$premi['jkm']]);
    }

    /** Simpan besaran premi JKK/JKM: POST JSON {jkk, jkm}. */
    public function settings_save() {
        if ($this->input->method() !== 'post') { $this->_json(['error' => 'POST required'], 405); return; }
        $body = json_decode(file_get_contents('php://input'), true);
        $vals = [];
        foreach (['jkk', 'jkm'] as $k) {
            $v = isset($body[$k]) && is_numeric($body[$k]) ? round((float)$body[$k], 2) : null;
            if ($v === null || $v < 0) { $this->_json(['error' => 'Nilai '.strtoupper($k).' tidak valid'], 422); return; }
            $vals[$k] = $v;
        }
        $this->db->query('CREATE TABLE IF NOT EXISTS pph21_settings (
            setting_key VARCHAR(50) NOT NULL PRIMARY KEY,
            setting_value VARCHAR(100) NOT NULL,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        $uid = (int)$this->ion_auth->user()->row()->id;
        foreach ($vals as $k => $v) {
            $this->db->query(
                'INSERT INTO pph21_settings (setting_key, setting_value, updated_by, updated_at)
                 VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),
                   updated_by=VALUES(updated_by), updated_at=VALUES(updated_at)',
                ['premi_'.$k, (string)$v, $uid, date('Y-m-d H:i:s')]);
        }
        $this->_json(['saved' => true, 'jkk' => $vals['jkk'], 'jkm' => $vals['jkm']]);
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
        $roster_n = [];
        foreach ($this->db->select('subdivision_id, COUNT(*) AS n')->from('pph21_roster')
                 ->where('year', (int)$p->year)->group_by('subdivision_id')->get()->result() as $r) {
            $roster_n[$r->subdivision_id] = (int)$r->n;
        }
        // Status pengisian 4 kolom manual (pph21_manual) per CV — utk warning
        // di tombol Export XML: kolom yang belum ada satupun nilainya.
        $manual_n = [];
        if ($this->db->table_exists('pph21_manual')) {
            $q = $this->db->select("u.subdivision_id AS sid,
                    SUM(m.tunjangan > 0) AS tunjangan, SUM(m.insentif > 0) AS insentif,
                    SUM(m.subsidi > 0) AS subsidi, SUM(m.bonus > 0) AS bonus", false)
                ->from('pph21_manual m')->join('users u', 'u.id = m.user_id')
                ->where('m.payroll_id', $pid)->group_by('u.subdivision_id')->get()->result();
            foreach ($q as $mn) {
                $manual_n[(int)$mn->sid] = ['tunjangan' => (int)$mn->tunjangan, 'insentif' => (int)$mn->insentif,
                    'subsidi' => (int)$mn->subsidi, 'bonus' => (int)$mn->bonus];
            }
        }
        $zero = ['tunjangan' => 0, 'insentif' => 0, 'subsidi' => 0, 'bonus' => 0];
        $seen = [];
        foreach ($rows as $r) {
            $r->subdivision_name = trim((string)$r->subdivision_name) !== '' ? trim($r->subdivision_name) : '(TANPA SUBDIVISI)';
            $r->npwp = isset($npwp[$r->id]) ? $npwp[$r->id] : '';
            $r->roster = isset($roster_n[$r->id]) ? $roster_n[$r->id] : 0;
            $r->manual = isset($manual_n[(int)$r->id]) ? $manual_n[(int)$r->id] : $zero;
            $seen[(int)$r->id] = true;
        }
        // CV yang hanya ada di roster (semua karyawannya sudah tidak aktif) tetap dilaporkan
        foreach ($roster_n as $sid2 => $n2) {
            if (isset($seen[$sid2])) continue;
            $s = $this->db->select('subdivision_name')->from('subdivision')->where('id', $sid2)->get()->row();
            $rows[] = (object)['id' => $sid2, 'subdivision_name' => $s ? trim($s->subdivision_name) : '(?)',
                'n' => 0, 'npwp' => isset($npwp[$sid2]) ? $npwp[$sid2] : '', 'roster' => $n2, 'manual' => $zero];
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
        $sids = $this->db->query(
            'SELECT DISTINCT sid FROM ('
            .'SELECT COALESCE(u.subdivision_id, 0) AS sid FROM payroll_detail pd JOIN users u ON u.id = pd.user_id WHERE pd.payroll_id = '.(int)$pid
            .' UNION SELECT subdivision_id AS sid FROM pph21_roster WHERE year = '.(int)$p->year
            .') x')->result();
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

    /** Download 1 file gabungan semua CV (kolom tambahan CV & PENEMPATAN). */
    public function export_combined() {
        $pid = (int)$this->input->get('payroll_id');
        $p = $this->_payroll($pid);
        if (!$p) { $this->_json(['error' => 'Payroll tidak ditemukan'], 404); return; }
        $sids = $this->db->query(
            'SELECT DISTINCT sid FROM ('
            .'SELECT COALESCE(u.subdivision_id, 0) AS sid FROM payroll_detail pd JOIN users u ON u.id = pd.user_id WHERE pd.payroll_id = '.(int)$pid
            .' UNION SELECT subdivision_id AS sid FROM pph21_roster WHERE year = '.(int)$p->year
            .') x')->result();
        $groups = [];
        foreach ($sids as $s) {
            $data = $this->_cv_rows($pid, $p, (int)$s->sid);
            if (!$data['rows']) continue;
            $groups[] = ['cv' => $data['meta']['cv'], 'rows' => $data['rows']];
        }
        if (!$groups) { $this->_json(['error' => 'Tidak ada data'], 404); return; }
        usort($groups, function ($a, $b) { return strcmp($a['cv'], $b['cv']); });
        $pen_map = $this->config->item('pph21_penempatan');
        $meta = [
            'branch'     => $p->branch_name,
            'penempatan' => isset($pen_map[$p->branch_id]) ? $pen_map[$p->branch_id] : $p->branch_name,
            'month'      => (int)$p->month,
            'year'       => (int)$p->year,
            'premi'      => $this->_premi(),
        ];
        $ss = $this->pph21_workbook->build_combined($meta, $groups);
        $fname = sprintf('%02d %s PPh21 - Semua CV (%s).xlsx', $p->month, $this->_month_name($p->month), $meta['penempatan']);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.str_replace('"', '', $fname).'"');
        header('Cache-Control: max-age=0');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save('php://output');
        exit;
    }

    /**
     * XML Coretax MmPayrollBulk satu CV (pegawai tetap, pola pelaporan Juni 2026:
     * Gross = bruto GROSS-UP, Rate = tarif TER final iterasi 3 tahap).
     * pph21_export/export_xml?payroll_id=..&subdivision_id=..
     */
    public function export_xml() {
        $pid = (int)$this->input->get('payroll_id');
        $sid = (int)$this->input->get('subdivision_id');
        $p = $this->_payroll($pid);
        if (!$p) { $this->_json(['error' => 'Payroll tidak ditemukan'], 404); return; }
        $data = $this->_cv_rows($pid, $p, $sid);
        if (!$data['rows']) { $this->_json(['error' => 'Tidak ada karyawan utk CV ini'], 404); return; }
        if ($data['meta']['npwp'] === '') { $this->_json(['error' => 'NPWP CV belum diisi di config pph21_export.php'], 422); return; }
        $xml = $this->_mm_xml($data['meta'], $data['rows']);
        $fname = sprintf('%02d %s - PPH21 %s.xml', $p->month, $this->_month_name($p->month), $data['meta']['cv']);
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.str_replace('"', '', $fname).'"');
        echo $xml; exit;
    }

    /** ZIP XML MmPayrollBulk semua CV satu payroll. */
    public function export_xml_all() {
        $pid = (int)$this->input->get('payroll_id');
        $p = $this->_payroll($pid);
        if (!$p) { $this->_json(['error' => 'Payroll tidak ditemukan'], 404); return; }
        $sids = $this->db->query(
            'SELECT DISTINCT sid FROM ('
            .'SELECT COALESCE(u.subdivision_id, 0) AS sid FROM payroll_detail pd JOIN users u ON u.id = pd.user_id WHERE pd.payroll_id = '.(int)$pid
            .' UNION SELECT subdivision_id AS sid FROM pph21_roster WHERE year = '.(int)$p->year
            .') x')->result();
        $tmp = tempnam(sys_get_temp_dir(), 'pph21x');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $count = 0;
        foreach ($sids as $s) {
            $data = $this->_cv_rows($pid, $p, (int)$s->sid);
            if (!$data['rows'] || $data['meta']['npwp'] === '') continue;
            $zip->addFromString(sprintf('%02d %s - PPH21 %s.xml',
                $p->month, $this->_month_name($p->month), $data['meta']['cv']),
                $this->_mm_xml($data['meta'], $data['rows']));
            $count++;
        }
        $zip->close();
        if (!$count) { @unlink($tmp); $this->_json(['error' => 'Tidak ada data (atau NPWP CV belum diisi)'], 404); return; }
        $fname = sprintf('PPh21 XML %02d-%d %s (semua CV).zip', $p->month, $p->year, $p->branch_name);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.str_replace('"', '', $fname).'"');
        header('Content-Length: '.filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /**
     * Rekap tahunan bruto gross-up per karyawan format konsultan
     * ("REKAP DATA SPT MASA PPh Pasal 21 Gross Up"): kolom JAN..NOP, JUMLAH, DES, TOTAL.
     * pph21_export/export_gu?year=..&subdivision_id=..
     */
    public function export_gu() {
        $y = (int)$this->input->get('year');
        $sid = (int)$this->input->get('subdivision_id');
        $data = $this->_gu_rows($y, $sid);
        if (!$data['rows']) { $this->_json(['error' => 'Tidak ada data utk CV/tahun ini'], 404); return; }
        $ss = $this->pph21_workbook->build_gu_rekap($data['meta'], $data['rows']);
        $this->_xlsx_out($ss, sprintf('REKAP DATA SPT MASA PPh21 %d - %s.xlsx', $y, $data['meta']['cv']));
    }

    /** ZIP rekap GU tahunan semua CV. */
    public function export_gu_all() {
        $y = (int)$this->input->get('year');
        $sids = $this->db->query(
            'SELECT DISTINCT sid FROM ('
            .'SELECT COALESCE(u.subdivision_id, 0) AS sid FROM payroll_detail pd JOIN payroll p ON p.id = pd.payroll_id JOIN users u ON u.id = pd.user_id WHERE p.year = '.(int)$y
            .' UNION SELECT subdivision_id AS sid FROM pph21_roster WHERE year = '.(int)$y
            .') x')->result();
        $tmp = tempnam(sys_get_temp_dir(), 'pph21gu');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $count = 0;
        foreach ($sids as $s) {
            $data = $this->_gu_rows($y, (int)$s->sid);
            if (!$data['rows']) continue;
            $ss = $this->pph21_workbook->build_gu_rekap($data['meta'], $data['rows']);
            $f = tempnam(sys_get_temp_dir(), 'wb');
            (new Xlsx($ss))->save($f);
            $zip->addFromString(sprintf('REKAP DATA SPT MASA PPh21 %d - %s.xlsx', $y, $data['meta']['cv']), file_get_contents($f));
            @unlink($f);
            $count++;
        }
        $zip->close();
        if (!$count) { @unlink($tmp); $this->_json(['error' => 'Tidak ada data'], 404); return; }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="REKAP SPT MASA PPh21 GU '.$y.' (semua CV).zip"');
        header('Content-Length: '.filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /** Data rekap GU: bruto gross-up per karyawan per bulan (urut roster). */
    private function _gu_rows($y, $sid) {
        $premi = $this->_premi();
        $premi_total = $premi['jkk'] + $premi['jkm'] + $premi['kes'];
        $ps = $this->db->select('p.id, p.month, p.year, p.branch_id, b.branch_name')
            ->from('payroll p')->join('branch b', 'b.id = p.branch_id')
            ->where('p.year', $y)->order_by('p.month, p.id')->get()->result();
        $monthly = []; $order = [];
        foreach ($ps as $p) {
            $data = $this->_cv_rows($p->id, $p, $sid);
            foreach ($data['rows'] as $e) {
                $bruto = $this->_bruto_row($e, $premi_total);
                if ($bruto <= 0) continue;
                list(, $gu) = $this->_gu_of($bruto, $e['ptkp']);
                $k = $this->_norm($e['name']);
                if (!isset($monthly[$k])) { $monthly[$k] = ['name' => $e['name'], 'm' => []]; $order[] = $k; }
                $m = (int)$p->month;
                $monthly[$k]['m'][$m] = (isset($monthly[$k]['m'][$m]) ? $monthly[$k]['m'][$m] : 0) + $gu;
            }
        }
        // urutan mengikuti roster tahunan; nama di luar roster ditambah di bawah
        $rows = []; $used = [];
        foreach ($this->db->from('pph21_roster')->where(['year' => $y, 'subdivision_id' => $sid])
                 ->order_by('sort_order')->get()->result_array() as $rr) {
            $k = $this->_norm($rr['name']);
            $rows[] = ['name' => $rr['name'], 'm' => isset($monthly[$k]) ? $monthly[$k]['m'] : []];
            $used[$k] = true;
        }
        foreach ($order as $k) {
            if (!isset($used[$k])) $rows[] = $monthly[$k];
        }
        if (!$rows) return ['meta' => [], 'rows' => []];
        $npwp_map = $this->config->item('pph21_npwp');
        $s = $this->db->select('subdivision_name')->from('subdivision')->where('id', $sid)->get()->row();
        return ['meta' => [
            'cv' => $s ? trim($s->subdivision_name) : 'TANPA SUBDIVISI',
            'npwp' => isset($npwp_map[$sid]) ? $npwp_map[$sid] : '',
            'year' => $y,
        ], 'rows' => $rows];
    }

    private function _xlsx_out($ss, $fname) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.str_replace('"', '', $fname).'"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }

    // ------------------------------------------------------------------ helpers

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

    /** [tarif final, bruto gross-up] dari bruto — iterasi 3 tahap (kolom U/V/W). */
    private function _gu_of($bruto, $ptkp) {
        $cat = Pph21_workbook::ter_category($ptkp !== '' ? $ptkp : 'TK/0');
        $t1 = $this->_ter_rate($bruto, $cat);
        $t2 = $this->_ter_rate($bruto / (1 - $t1 / 100), $cat);
        $rate = $this->_ter_rate($bruto / (1 - $t2 / 100), $cat);
        return [$rate, $rate > 0 ? $bruto / (1 - $rate / 100) : $bruto];
    }

    /** Bruto satu baris _cv_rows (kas + premi perusahaan bila peserta BPJS). */
    private function _bruto_row($e, $premi_total) {
        return $e['thp'] + $e['cashbon'] + $e['insentif'] + $e['subsidi'] + $e['bonus']
             + (!empty($e['bpjs']) ? $premi_total : 0);
    }

    /** Bangun MmPayrollBulk dari baris _cv_rows (format persis pelaporan Juni 2026). */
    private function _mm_xml($meta, $rows) {
        $premi = $meta['premi'];
        $premi_total = $premi['jkk'] + $premi['jkm'] + $premi['kes'];
        $last = date('Y-m-t', mktime(0, 0, 0, (int)$meta['month'], 1, (int)$meta['year']));
        $calc = $this->pph21_np_calc;
        $x = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";
        $x .= "<MmPayrollBulk xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">\n";
        $x .= "\t<TIN>".htmlspecialchars($meta['npwp'], ENT_XML1)."</TIN>\n";
        $x .= "\t<ListOfMmPayroll>\n";
        foreach ($rows as $e) {
            $bruto = $this->_bruto_row($e, $premi_total);
            if ($bruto <= 0) continue; // resign/nihil: tidak ada penghasilan utk dilaporkan
            $ptkp = $e['ptkp'] !== '' ? $e['ptkp'] : 'TK/0';
            list($rate, $gross_up) = $this->_gu_of($bruto, $ptkp);
            $x .= "\t\t<MmPayroll>\n";
            $x .= "\t\t\t<TaxPeriodMonth>".(int)$meta['month']."</TaxPeriodMonth>\n";
            $x .= "\t\t\t<TaxPeriodYear>".(int)$meta['year']."</TaxPeriodYear>\n";
            $x .= "\t\t\t<CounterpartOpt>Resident</CounterpartOpt>\n";
            $x .= "\t\t\t<CounterpartPassport xsi:nil=\"true\"/>\n";
            $x .= "\t\t\t<CounterpartTin>".htmlspecialchars($e['nik'], ENT_XML1)."</CounterpartTin>\n";
            $x .= "\t\t\t<StatusTaxExemption>".htmlspecialchars($ptkp, ENT_XML1)."</StatusTaxExemption>\n";
            $x .= "\t\t\t<Position>".htmlspecialchars((string)$e['position'], ENT_XML1)."</Position>\n";
            $x .= "\t\t\t<TaxCertificate>N/A</TaxCertificate>\n";
            $x .= "\t\t\t<TaxObjectCode>21-100-01</TaxObjectCode>\n";
            $x .= "\t\t\t<Gross>".$calc->xf($gross_up)."</Gross>\n";
            $x .= "\t\t\t<Rate>".$calc->xf($rate)."</Rate>\n";
            $x .= "\t\t\t<IDPlaceOfBusinessActivity>".htmlspecialchars($meta['npwp'].'000000', ENT_XML1)."</IDPlaceOfBusinessActivity>\n";
            $x .= "\t\t\t<WithholdingDate>".$last."</WithholdingDate>\n";
            $x .= "\t\t</MmPayroll>\n";
        }
        $x .= "\t</ListOfMmPayroll>\n";
        $x .= "</MmPayrollBulk>\n";
        return $x;
    }

    private function _payroll($pid) {
        return $this->db->select('p.id, p.month, p.year, p.branch_id, b.branch_name')
            ->from('payroll p')->join('branch b', 'b.id = p.branch_id')
            ->where('p.id', $pid)->get()->row();
    }

    /**
     * Data karyawan satu CV, URUT SESUAI ROSTER TAHUNAN (pph21_roster).
     * Aturan pajak: susunan karyawan mengikuti kertas kerja awal tahun (Mei utk 2026)
     * sampai Desember — karyawan resign tetap tampil dengan nilai nihil; karyawan
     * baru ditambahkan di urutan paling bawah (sekali, lalu urutannya permanen).
     */
    private function _cv_rows($pid, $p, $sid) {
        $emps = $this->db->select("pd.user_id, TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS name,
                COALESCE(pos.position_name, '') AS position, COALESCE(u.npwp_number, '') AS nik,
                COALESCE(u.ptkp_status, '') AS ptkp, pd.salary_thp, COALESCE(s.subdivision_name,'') AS cv,
                u.join_date, u.active, u.last_status", false)
            ->from('payroll_detail pd')
            ->join('users u', 'u.id = pd.user_id')
            ->join('position pos', 'pos.id = u.position_id', 'left')
            ->join('subdivision s', 's.id = u.subdivision_id', 'left')
            ->where('pd.payroll_id', $pid)
            ->where($sid ? 'u.subdivision_id = '.$sid : '(u.subdivision_id IS NULL OR u.subdivision_id = 0)')
            ->order_by('name')->get()->result_array();

        $roster = $this->db->from('pph21_roster')
            ->where('year', (int)$p->year)->where('subdivision_id', $sid)
            ->order_by('sort_order')->get()->result_array();
        if (!$emps && !$roster) return ['meta' => [], 'rows' => []];

        // tanggal join/resign utk baris roster yang tidak ada di payroll bulan ini
        $ru = [];
        $roster_uids = array_filter(array_column($roster, 'user_id'));
        if ($roster_uids) {
            foreach ($this->db->select('id, join_date, active, last_status')->from('users')
                     ->where_in('id', $roster_uids)->get()->result_array() as $u) {
                $ru[(int)$u['id']] = $u;
            }
        }

        $uids = array_column($emps, 'user_id');
        $addback = $this->_ded_sums($p, $uids, $this->config->item('pph21_addback_deductions'));
        $bpjs    = $this->_ded_sums($p, $uids, $this->config->item('pph21_bpjs_markers'));
        $manual  = $this->_manual_rows($pid, $uids);

        $by_uid = $by_key = [];
        foreach ($emps as $i => $e) {
            $by_uid[$e['user_id']] = $i;
            $by_key[$this->_norm($e['name'])] = $i;
        }
        $mkrow = function ($e) use ($addback, $bpjs, $manual, $p) {
            $uid = $e['user_id'];
            $m = isset($manual[$uid]) ? $manual[$uid] : null;
            list($ma, $mk) = $this->_masa($p->year, $e['join_date'], $e['active'], $e['last_status']);
            return [
                'name'     => $e['name'],
                'position' => $e['position'],
                'nik'      => preg_replace('/\D/', '', $e['nik']),
                'ptkp'     => strtoupper(str_replace(' ', '', $e['ptkp'])),
                'thp'      => (float)$e['salary_thp'],
                'cashbon'  => (isset($addback[$uid]) ? (float)$addback[$uid] : 0.0)
                              + ($m ? (float)$m->tunjangan : 0.0), // + uang jalan kanvas (input manual)
                'insentif' => $m ? (float)$m->insentif : 0.0,
                'subsidi'  => $m ? (float)$m->subsidi : 0.0,
                'bonus'    => $m ? (float)$m->bonus : 0.0,
                'bpjs'     => !empty($bpjs[$uid]),
                'masa_awal'  => $ma,
                'masa_akhir' => $mk,
            ];
        };

        $rows = [];
        $used = [];
        if (!$roster) {
            // bootstrap tahun baru: roster = payroll bulan ini (urut abjad)
            foreach ($emps as $i => $e) {
                $rows[] = $mkrow($e);
                $this->_roster_insert($p, $sid, $e, $i + 1);
                $used[$i] = true;
            }
        } else {
            foreach ($roster as $rr) {
                $i = null;
                if ($rr['user_id'] !== null && isset($by_uid[$rr['user_id']])) $i = $by_uid[$rr['user_id']];
                elseif (isset($by_key[$rr['name_key']])) $i = $by_key[$rr['name_key']];
                if ($i !== null) {
                    $rows[] = $mkrow($emps[$i]);
                    $used[$i] = true;
                } else {
                    // resign / tidak ada di payroll bulan ini → baris nihil (tetap dilaporkan)
                    $u = $rr['user_id'] !== null && isset($ru[(int)$rr['user_id']]) ? $ru[(int)$rr['user_id']] : null;
                    list($ma, $mk) = $u
                        ? $this->_masa($p->year, $u['join_date'], $u['active'], $u['last_status'])
                        : [1, 12];
                    $rows[] = [
                        'name' => $rr['name'], 'position' => $rr['position'],
                        'nik' => preg_replace('/\D/', '', $rr['nik']),
                        'ptkp' => '', 'thp' => 0.0, 'cashbon' => 0.0,
                        'insentif' => 0.0, 'subsidi' => 0.0, 'bonus' => 0.0, 'bpjs' => false,
                        'masa_awal' => $ma, 'masa_akhir' => $mk,
                    ];
                }
            }
            // karyawan baru (belum ada di roster) → tambah di bawah, simpan permanen
            $next = count($roster);
            foreach ($emps as $i => $e) {
                if (isset($used[$i])) continue;
                $next++;
                $rows[] = $mkrow($e);
                $this->_roster_insert($p, $sid, $e, $next);
            }
        }

        $cv_name = '';
        foreach ($emps as $e) { $cv_name = trim($e['cv']); break; }
        if ($cv_name === '' && $sid) {
            $s = $this->db->select('subdivision_name')->from('subdivision')->where('id', $sid)->get()->row();
            $cv_name = $s ? trim($s->subdivision_name) : '';
        }
        $npwp_map = $this->config->item('pph21_npwp');
        $meta = [
            'cv'     => $cv_name !== '' ? $cv_name : 'TANPA SUBDIVISI',
            'npwp'   => isset($npwp_map[$sid]) ? $npwp_map[$sid] : '',
            'branch' => $p->branch_name,
            'month'  => (int)$p->month,
            'year'   => (int)$p->year,
            'premi'  => $this->_premi(),
        ];
        return ['meta' => $meta, 'rows' => $rows];
    }

    private function _norm($s) {
        return preg_replace('/[^A-Z]/', '', strtoupper((string)$s));
    }

    /** Premi perusahaan: default config pph21_premi, jkk/jkm bisa di-override dari tabel pph21_settings (UI export). */
    private function _premi() {
        $premi = $this->config->item('pph21_premi');
        if ($this->db->table_exists('pph21_settings')) {
            foreach ($this->db->where_in('setting_key', ['premi_jkk', 'premi_jkm'])
                     ->get('pph21_settings')->result() as $s) {
                $premi[substr($s->setting_key, 6)] = (float)$s->setting_value;
            }
        }
        return $premi;
    }

    /**
     * Masa perolehan penghasilan [bulan awal, bulan akhir] dlm tahun pajak
     * (PMK 168/2023, contoh Buku DJP hal. 65 & 68): awal = bulan join bila
     * join_date di tahun pajak ini, selain itu 01; akhir = bulan nonaktif
     * (users.last_status saat active=0) bila di tahun pajak ini, selain itu 12.
     */
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

    private function _roster_insert($p, $sid, $e, $order) {
        $this->db->insert('pph21_roster', [
            'year' => (int)$p->year, 'subdivision_id' => $sid,
            'user_id' => $e['user_id'], 'nik' => preg_replace('/\D/', '', $e['nik']),
            'name' => $e['name'], 'name_key' => $this->_norm($e['name']),
            'position' => $e['position'], 'sort_order' => $order,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Nilai input manual (tools/pph21_manual) per user utk payroll ini. */
    private function _manual_rows($pid, $uids) {
        if (!$uids || !$this->db->table_exists('pph21_manual')) return [];
        $out = [];
        foreach ($this->db->from('pph21_manual')->where('payroll_id', (int)$pid)
                 ->where_in('user_id', $uids)->get()->result() as $m) {
            $out[$m->user_id] = $m;
        }
        return $out;
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

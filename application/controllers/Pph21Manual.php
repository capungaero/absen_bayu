<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once FCPATH.'lib/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Pph21Manual — input data manual PPh 21 (tools/pph21_manual).
 *
 * Mengelola 4 kolom isian manual kertas kerja Export PPh21 (data di luar absensi):
 *   tunjangan = uang jalan kanvas (penambah kolom Tunjangan/Cash Bon)
 *   insentif  = uang konsumsi / tunjangan lain
 *   subsidi   = subsidi pajak / lembur
 *   bonus     = bonus / THR
 * Nilai disimpan di tabel pph21_manual (per payroll_id + user_id); Export PPh21
 * membacanya dan mengisi kolom H/I/J (+ tambahan G) otomatis.
 *
 * Auth: ion_auth session + role admin/admin-branch/hr (pola Pph21Export).
 */
class Pph21Manual extends CI_Controller {

    const COLS = ['tunjangan', 'insentif', 'subsidi', 'bonus'];

    public function __construct() {
        parent::__construct();
        if (!$this->ion_auth->logged_in()) {
            $this->_die(['error' => 'Unauthorized'], 401);
        }
        $role = $this->ion_auth->get_users_groups()->row()->name;
        if (!in_array($role, ['admin', 'admin-branch', 'hr'])) {
            $this->_die(['error' => 'Forbidden'], 403);
        }
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

    /** Daftar periode payroll (terbaru dulu) — sama dengan Pph21Export::periods. */
    public function periods() {
        $rows = $this->db->select('p.id, p.month, p.year, b.branch_name')
            ->from('payroll p')->join('branch b', 'b.id = p.branch_id')
            ->order_by('p.year DESC, p.month DESC, p.branch_id')->get()->result();
        $this->_json(['periods' => $rows]);
    }

    /** Daftar karyawan satu payroll + nilai manual tersimpan, urut CV lalu nama. */
    public function employees() {
        $pid = (int)$this->input->get('payroll_id');
        if (!$this->_payroll($pid)) { $this->_json(['error' => 'Payroll tidak ditemukan'], 404); return; }
        $this->_json(['rows' => $this->_employee_rows($pid)]);
    }

    /**
     * Simpan bulk: POST JSON {payroll_id, rows:[{user_id,tunjangan,insentif,subsidi,bonus}]}.
     * Baris yang seluruh nilainya 0 dihapus dari tabel (bersih).
     */
    public function save() {
        if ($this->input->method() !== 'post') { $this->_json(['error' => 'POST required'], 405); return; }
        $body = json_decode(file_get_contents('php://input'), true);
        $pid = isset($body['payroll_id']) ? (int)$body['payroll_id'] : 0;
        $rows = isset($body['rows']) && is_array($body['rows']) ? $body['rows'] : [];
        if (!$this->_payroll($pid)) { $this->_json(['error' => 'Payroll tidak ditemukan'], 404); return; }

        // Hanya user yang memang ada di payroll ini yang boleh disimpan.
        $valid = [];
        foreach ($this->db->select('user_id')->from('payroll_detail')
                 ->where('payroll_id', $pid)->get()->result() as $r) {
            $valid[(int)$r->user_id] = true;
        }

        $uid_admin = (int)$this->ion_auth->user()->row()->id;
        $saved = 0; $removed = 0; $skipped = 0;
        foreach ($rows as $r) {
            $uid = isset($r['user_id']) ? (int)$r['user_id'] : 0;
            if (!$uid || !isset($valid[$uid])) { $skipped++; continue; }
            $vals = [];
            $all_zero = true;
            foreach (self::COLS as $c) {
                $v = isset($r[$c]) ? round((float)$r[$c], 2) : 0.0;
                if ($v < 0) $v = 0.0;
                $vals[$c] = $v;
                if ($v != 0) $all_zero = false;
            }
            if ($all_zero) {
                $removed += (int)$this->db->where(['payroll_id' => $pid, 'user_id' => $uid])
                    ->delete('pph21_manual');
                continue;
            }
            $vals += ['payroll_id' => $pid, 'user_id' => $uid,
                      'updated_by' => $uid_admin, 'updated_at' => date('Y-m-d H:i:s')];
            $this->db->query(
                'INSERT INTO pph21_manual (payroll_id, user_id, tunjangan, insentif, subsidi, bonus, updated_by, updated_at)
                 VALUES (?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE tunjangan=VALUES(tunjangan), insentif=VALUES(insentif),
                   subsidi=VALUES(subsidi), bonus=VALUES(bonus),
                   updated_by=VALUES(updated_by), updated_at=VALUES(updated_at)',
                [$vals['payroll_id'], $vals['user_id'], $vals['tunjangan'], $vals['insentif'],
                 $vals['subsidi'], $vals['bonus'], $vals['updated_by'], $vals['updated_at']]);
            $saved++;
        }
        $this->_json(['saved' => $saved, 'removed' => $removed, 'skipped' => $skipped]);
    }

    /** Download template Excel terisi daftar karyawan + nilai tersimpan (round-trip). */
    public function template() {
        $pid = (int)$this->input->get('payroll_id');
        $p = $this->_payroll($pid);
        if (!$p) { $this->_json(['error' => 'Payroll tidak ditemukan'], 404); return; }
        $rows = $this->_employee_rows($pid);

        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $ws = $ss->getActiveSheet();
        $ws->setTitle('INPUT MANUAL');
        $ws->setCellValue('A1', 'TEMPLATE INPUT DATA MANUAL PPh 21 — '.$p->branch_name.' — MASA '.$p->month.'/'.$p->year);
        $ws->setCellValue('A2', 'Isi kolom E-H (angka rupiah, boleh kosong = 0). JANGAN mengubah kolom NIK — dipakai sebagai kunci pencocokan saat import.');
        $ws->getStyle('A1')->getFont()->setBold(true);

        $hdr = ['NO', 'NIK', 'NAMA PEGAWAI', 'CV', 'UANG JALAN KANVAS', 'UANG KONSUMSI / INSENTIF LAIN', 'SUBSIDI PAJAK / LEMBUR', 'BONUS / THR'];
        foreach ($hdr as $c => $t) $ws->setCellValueByColumnAndRow($c + 1, 4, $t);
        $ws->getStyle('A4:H4')->getFont()->setBold(true);
        $ws->getStyle('A4:H4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');
        $ws->getStyle('E4:H4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4D6');

        $r = 5;
        foreach ($rows as $i => $e) {
            $ws->setCellValueByColumnAndRow(1, $r, $i + 1);
            $ws->setCellValueExplicit('B'.$r, (string)$e['nik'], DataType::TYPE_STRING);
            $ws->setCellValueByColumnAndRow(3, $r, $e['name']);
            $ws->setCellValueByColumnAndRow(4, $r, $e['cv']);
            foreach (self::COLS as $j => $c) {
                if ((float)$e[$c] != 0) $ws->setCellValueByColumnAndRow(5 + $j, $r, (float)$e[$c]);
            }
            $r++;
        }
        $ws->getStyle('E5:H'.($r - 1))->getNumberFormat()->setFormatCode('#,##0.00;(#,##0.00);""');
        $ws->getStyle('A4:H'.($r - 1))->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
        foreach (['A' => 5, 'B' => 20, 'C' => 32, 'D' => 32, 'E' => 18, 'F' => 22, 'G' => 20, 'H' => 14] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
        $ws->freezePane('A5');

        $fname = sprintf('Template Input Manual PPh21 %02d-%d %s.xlsx', $p->month, $p->year, $p->branch_name);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.str_replace('"', '', $fname).'"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }

    /**
     * Import template: POST multipart (file, payroll_id). Mencocokkan baris ke
     * karyawan via NIK (fallback: nama). Mengembalikan nilai hasil parse untuk
     * ditinjau di UI — TIDAK langsung disimpan (admin klik Simpan).
     */
    public function import() {
        if ($this->input->method() !== 'post') { $this->_json(['error' => 'POST required'], 405); return; }
        $pid = (int)$this->input->post('payroll_id');
        if (!$this->_payroll($pid)) { $this->_json(['error' => 'Payroll tidak ditemukan'], 404); return; }
        if (empty($_FILES['file']['name'])) { $this->_json(['error' => 'File belum dipilih'], 422); return; }
        if (!empty($_FILES['file']['error'])) { $this->_json(['error' => 'Upload gagal (kode '.$_FILES['file']['error'].')'], 422); return; }

        $tmp = $_FILES['file']['tmp_name'];
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmp);
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($tmp)->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Exception $e) {
            $this->_json(['error' => 'Gagal membaca file Excel: '.$e->getMessage()], 422); return;
        }

        // Cari baris header: baris yang memuat sel "NIK".
        $hidx = -1; $cmap = [];
        foreach ($sheet as $i => $row) {
            foreach ($row as $j => $cell) {
                if (strtoupper(trim((string)$cell)) === 'NIK') { $hidx = $i; break 2; }
            }
        }
        if ($hidx < 0) { $this->_json(['error' => 'Header tidak ditemukan (kolom "NIK" wajib ada)'], 422); return; }

        // Petakan kolom via kata kunci pada header (toleran perubahan judul).
        $keywords = [
            'nik'       => ['NIK'],
            'nama'      => ['NAMA'],
            'tunjangan' => ['JALAN', 'KANVAS'],
            'insentif'  => ['KONSUMSI', 'INSENTIF'],
            'subsidi'   => ['SUBSIDI', 'LEMBUR'],
            'bonus'     => ['BONUS', 'THR'],
        ];
        foreach ($sheet[$hidx] as $j => $cell) {
            $h = strtoupper(trim((string)$cell));
            if ($h === '') continue;
            foreach ($keywords as $key => $words) {
                if (isset($cmap[$key])) continue;
                foreach ($words as $w) {
                    if (strpos($h, $w) !== false) { $cmap[$key] = $j; continue 2; }
                }
            }
        }
        foreach (self::COLS as $c) {
            if (!isset($cmap[$c])) { $this->_json(['error' => 'Kolom "'.$c.'" tidak ditemukan di header — pakai template yang disediakan'], 422); return; }
        }

        // Index karyawan payroll ini: by NIK dan by nama (dinormalisasi).
        $emps = $this->_employee_rows($pid);
        $by_nik = $by_name = [];
        foreach ($emps as $e) {
            if ($e['nik'] !== '') $by_nik[$e['nik']] = $e['user_id'];
            $by_name[$this->_norm($e['name'])] = $e['user_id'];
        }

        $out = []; $unmatched = [];
        foreach (array_slice($sheet, $hidx + 1) as $row) {
            $nik = preg_replace('/\D/', '', (string)(isset($row[$cmap['nik']]) ? $row[$cmap['nik']] : ''));
            $name = trim((string)(isset($cmap['nama'], $row[$cmap['nama']]) ? $row[$cmap['nama']] : ''));
            if ($nik === '' && $name === '') continue;
            $uid = null;
            if ($nik !== '' && isset($by_nik[$nik])) $uid = $by_nik[$nik];
            elseif ($name !== '' && isset($by_name[$this->_norm($name)])) $uid = $by_name[$this->_norm($name)];
            if ($uid === null) { $unmatched[] = $name !== '' ? $name : $nik; continue; }
            $vals = ['user_id' => (int)$uid];
            foreach (self::COLS as $c) {
                $vals[$c] = $this->_amount(isset($row[$cmap[$c]]) ? $row[$cmap[$c]] : null);
            }
            $out[] = $vals;
        }
        $this->_json(['rows' => $out, 'matched' => count($out), 'unmatched' => $unmatched]);
    }

    // ------------------------------------------------------------------ helpers

    private function _payroll($pid) {
        return $this->db->select('p.id, p.month, p.year, b.branch_name')
            ->from('payroll p')->join('branch b', 'b.id = p.branch_id')
            ->where('p.id', $pid)->get()->row();
    }

    private function _employee_rows($pid) {
        $rows = $this->db->select("pd.user_id, TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS name,
                COALESCE(pos.position_name, '') AS position, COALESCE(u.npwp_number, '') AS nik,
                COALESCE(s.subdivision_name, '(TANPA SUBDIVISI)') AS cv,
                COALESCE(m.tunjangan, 0) AS tunjangan, COALESCE(m.insentif, 0) AS insentif,
                COALESCE(m.subsidi, 0) AS subsidi, COALESCE(m.bonus, 0) AS bonus", false)
            ->from('payroll_detail pd')
            ->join('users u', 'u.id = pd.user_id')
            ->join('position pos', 'pos.id = u.position_id', 'left')
            ->join('subdivision s', 's.id = u.subdivision_id', 'left')
            ->join('pph21_manual m', 'm.payroll_id = pd.payroll_id AND m.user_id = pd.user_id', 'left')
            ->where('pd.payroll_id', $pid)
            ->order_by('cv, name')->get()->result_array();
        foreach ($rows as &$r) {
            $r['user_id'] = (int)$r['user_id'];
            $r['nik'] = preg_replace('/\D/', '', $r['nik']);
            foreach (self::COLS as $c) $r[$c] = (float)$r[$c];
        }
        return $rows;
    }

    private function _norm($s) {
        return preg_replace('/[^A-Z]/', '', strtoupper((string)$s));
    }

    /** Angka rupiah dari sel Excel: float mentah atau string "1.234.567,89". */
    private function _amount($v) {
        if ($v === null || $v === '') return 0.0;
        if (is_numeric($v)) return round((float)$v, 2);
        $s = trim(str_replace(['Rp', ' '], '', (string)$v));
        if ($s === '' || $s === '-') return 0.0;
        if (strpos($s, ',') !== false) $s = str_replace(['.', ','], ['', '.'], $s);
        return is_numeric($s) ? round((float)$s, 2) : 0.0;
    }
}

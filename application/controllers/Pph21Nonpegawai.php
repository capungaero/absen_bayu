<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once FCPATH.'lib/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;

/**
 * Pph21Nonpegawai — PPh 21 pegawai tidak tetap & tenaga ahli (tools/pph21_nonpegawai).
 *
 * Data di luar payroll absensi, diimport dari Excel per masa pajak (komposisi
 * per CV bebas tiap bulan). Hitung otomatis (Pph21_np_calc — TER bulanan /
 * TER harian / Ps.17 x deemed, gross-up), simpan REPLACE per (masa, CV),
 * export kertas kerja Excel per CV + XML Coretax Bp21Bulk gabungan per CV.
 *
 * Auth: ion_auth session + role admin/admin-branch/hr (pola Pph21Manual).
 */
class Pph21Nonpegawai extends CI_Controller {

    public function __construct() {
        parent::__construct();
        if (!$this->ion_auth->logged_in()) {
            $this->_die(['error' => 'Unauthorized'], 401);
        }
        $role = $this->ion_auth->get_users_groups()->row()->name;
        if (!in_array($role, ['admin', 'admin-branch', 'hr'])) {
            $this->_die(['error' => 'Forbidden'], 403);
        }
        $this->config->load('pph21_export');
        $this->load->library('pph21_np_calc');
        $this->load->library('pph21_np_workbook');
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

    /** Daftar CV (+NPWP config), kode objek, dan masa yang sudah punya data. */
    public function refs() {
        $this->_json([
            'cvs'   => array_values($this->_cvs()),
            'kode_objek' => $this->_kode_list(),
            'masa'  => $this->db->select('year, month, COUNT(*) AS n, SUM(pph) AS pph')
                ->from('pph21_nonpegawai')->group_by('year, month')
                ->order_by('year DESC, month DESC')->get()->result(),
        ]);
    }

    /** Data tersimpan satu masa (utk tampilan + tombol export). */
    public function data() {
        $m = (int)$this->input->get('month'); $y = (int)$this->input->get('year');
        $rows = $this->db->select('np.*, s.subdivision_name AS cv')
            ->from('pph21_nonpegawai np')
            ->join('subdivision s', 's.id = np.subdivision_id', 'left')
            ->where(['np.month' => $m, 'np.year' => $y])
            ->order_by('s.subdivision_name, np.kode_objek, np.nama')->get()->result_array();
        foreach ($rows as &$r) $r['cv'] = trim((string)$r['cv']);
        $this->_json(['rows' => $rows]);
    }

    /** Template import (prefilled data tersimpan masa tsb — round-trip). */
    public function template() {
        $m = (int)$this->input->get('month'); $y = (int)$this->input->get('year');
        if ($m < 1 || $m > 12 || $y < 2024) { $this->_json(['error' => 'Masa tidak valid'], 422); return; }
        $saved = $this->db->select('np.*, s.subdivision_name AS cv')
            ->from('pph21_nonpegawai np')->join('subdivision s', 's.id = np.subdivision_id', 'left')
            ->where(['np.month' => $m, 'np.year' => $y])
            ->order_by('s.subdivision_name, np.nama')->get()->result_array();
        foreach ($saved as &$r) $r['cv'] = trim((string)$r['cv']);
        $ss = $this->pph21_np_workbook->template($m, $y, array_values($this->_cvs()), $saved);
        $this->_xlsx($ss, sprintf('Template PPh21 TT-TenagaAhli %02d-%d.xlsx', $m, $y));
    }

    /**
     * Import: POST multipart (file, month, year) → parse + hitung → preview
     * JSON. TIDAK menyimpan — admin meninjau lalu memanggil save.
     */
    public function import() {
        if ($this->input->method() !== 'post') { $this->_json(['error' => 'POST required'], 405); return; }
        $m = (int)$this->input->post('month'); $y = (int)$this->input->post('year');
        if (empty($_FILES['file']['name'])) { $this->_json(['error' => 'File belum dipilih'], 422); return; }
        if (!empty($_FILES['file']['error'])) { $this->_json(['error' => 'Upload gagal (kode '.$_FILES['file']['error'].')'], 422); return; }
        $tmp = $_FILES['file']['tmp_name'];
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmp);
            $reader->setReadDataOnly(true);
            $book = $reader->load($tmp);
            $sheet = ($book->sheetNameExists('INPUT') ? $book->getSheetByName('INPUT') : $book->getActiveSheet())
                ->toArray(null, true, false, false);
        } catch (\Exception $e) {
            $this->_json(['error' => 'Gagal membaca file Excel: '.$e->getMessage()], 422); return;
        }

        // header = baris yang memuat sel "NIK"
        $hidx = -1;
        foreach ($sheet as $i => $row) {
            foreach ($row as $cell) {
                if (strtoupper(trim((string)$cell)) === 'NIK') { $hidx = $i; break 2; }
            }
        }
        if ($hidx < 0) { $this->_json(['error' => 'Header tidak ditemukan (kolom "NIK" wajib ada)'], 422); return; }
        $keywords = ['cv' => ['CV'], 'nik' => ['NIK'], 'nama' => ['NAMA'], 'kode' => ['KODE'],
                     'ptkp' => ['PTKP'], 'hari' => ['HARI'], 'neto' => ['PEMBAYARAN', 'NETO'],
                     'doc_number' => ['NO. DOK', 'NOMOR DOK'], 'doc_date' => ['TGL', 'TANGGAL'],
                     'ket' => ['KET']];
        $cmap = [];
        foreach ($sheet[$hidx] as $j => $cell) {
            $h = strtoupper(trim((string)$cell));
            if ($h === '') continue;
            foreach ($keywords as $key => $words) {
                if (isset($cmap[$key])) continue;
                foreach ($words as $w) {
                    if ($w === 'CV' ? $h === 'CV' : strpos($h, $w) !== false) { $cmap[$key] = $j; continue 2; }
                }
            }
        }
        foreach (['cv', 'nik', 'nama', 'kode', 'neto', 'doc_number', 'doc_date'] as $need) {
            if (!isset($cmap[$need])) { $this->_json(['error' => "Kolom '$need' tidak ditemukan — pakai template yang disediakan"], 422); return; }
        }

        $cv_index = [];
        foreach ($this->_cvs() as $cv) $cv_index[$this->_norm($cv['name'])] = $cv;
        $out = []; $errors = [];
        foreach (array_slice($sheet, $hidx + 1) as $i => $row) {
            $line = $hidx + $i + 2;
            $get = function ($key) use ($row, $cmap) { return isset($cmap[$key], $row[$cmap[$key]]) ? $row[$cmap[$key]] : null; };
            $nik_raw = trim((string)$get('nik')); $nama = trim((string)$get('nama'));
            if ($nik_raw === '' && $nama === '') continue;
            $cv = $cv_index[$this->_norm((string)$get('cv'))] ?? null;
            if (!$cv) { $errors[] = "Baris $line: CV '".trim((string)$get('cv'))."' tidak dikenal"; continue; }
            $nik = preg_replace('/\D/', '', $nik_raw);
            $warn = [];
            if (strlen($nik) !== 16) $warn[] = 'NIK bukan 16 digit ('.strlen($nik).')';
            $in = [
                'kode' => trim((string)$get('kode')), 'ptkp' => strtoupper(str_replace(' ', '', (string)$get('ptkp'))) ?: 'TK/0',
                'hari' => (int)$get('hari'), 'neto' => $this->_amount($get('neto')), 'gross_up' => true,
            ];
            $c = $this->pph21_np_calc->calc($in);
            if (!$c['ok']) { $errors[] = "Baris $line ($nama): ".$c['error']; continue; }
            $doc_date = $this->_date($get('doc_date'));
            if (!$doc_date) { $errors[] = "Baris $line ($nama): tanggal dokumen tidak valid"; continue; }
            $out[] = [
                'subdivision_id' => (int)$cv['id'], 'cv' => $cv['name'],
                'nik' => $nik, 'nama' => strtoupper($nama), 'kode_objek' => $in['kode'],
                'ptkp' => $in['ptkp'], 'hari_kerja' => $in['hari'], 'neto' => $in['neto'],
                'bruto' => $c['bruto'], 'deemed' => $c['deemed'], 'tarif' => $c['tarif'], 'pph' => $c['pph'],
                'doc_number' => trim((string)$get('doc_number')), 'doc_date' => $doc_date,
                'keterangan' => trim((string)$get('ket')),
                'warnings' => array_merge($warn, $c['warnings']),
            ];
        }
        $this->_json(['month' => $m, 'year' => $y, 'rows' => $out, 'errors' => $errors]);
    }

    /**
     * Simpan: POST JSON {month, year, rows} — nilai dihitung ULANG server-side
     * (tidak percaya angka client). REPLACE per (masa, CV yang ada di payload).
     */
    public function save() {
        if ($this->input->method() !== 'post') { $this->_json(['error' => 'POST required'], 405); return; }
        $body = json_decode(file_get_contents('php://input'), true);
        $m = isset($body['month']) ? (int)$body['month'] : 0;
        $y = isset($body['year']) ? (int)$body['year'] : 0;
        $rows = isset($body['rows']) && is_array($body['rows']) ? $body['rows'] : [];
        if ($m < 1 || $m > 12 || $y < 2024) { $this->_json(['error' => 'Masa tidak valid'], 422); return; }
        $cvs = $this->_cvs();
        $uid = (int)$this->ion_auth->user()->row()->id;
        $ins = []; $sids = []; $errors = [];
        foreach ($rows as $i => $r) {
            $sid = isset($r['subdivision_id']) ? (int)$r['subdivision_id'] : 0;
            if (!isset($cvs[$sid])) { $errors[] = 'Baris '.($i + 1).': CV tidak valid'; continue; }
            $c = $this->pph21_np_calc->calc([
                'kode' => (string)$r['kode_objek'], 'ptkp' => (string)$r['ptkp'],
                'hari' => (int)$r['hari_kerja'], 'neto' => (float)$r['neto'], 'gross_up' => true,
            ]);
            if (!$c['ok']) { $errors[] = 'Baris '.($i + 1).' ('.$r['nama'].'): '.$c['error']; continue; }
            $doc_date = $this->_date($r['doc_date']);
            if (!$doc_date) { $errors[] = 'Baris '.($i + 1).' ('.$r['nama'].'): tanggal dokumen tidak valid'; continue; }
            $sids[$sid] = true;
            $ins[] = [
                'month' => $m, 'year' => $y, 'subdivision_id' => $sid,
                'kode_objek' => (string)$r['kode_objek'],
                'nik' => preg_replace('/\D/', '', (string)$r['nik']),
                'nama' => strtoupper(trim((string)$r['nama'])),
                'keterangan' => trim((string)(isset($r['keterangan']) ? $r['keterangan'] : '')),
                'ptkp' => strtoupper(str_replace(' ', '', (string)$r['ptkp'])) ?: 'TK/0',
                'hari_kerja' => (int)$r['hari_kerja'], 'neto' => round((float)$r['neto'], 2),
                'gross_up' => 1, 'bruto' => $c['bruto'], 'deemed' => $c['deemed'],
                'tarif' => $c['tarif'], 'pph' => $c['pph'],
                'doc_number' => trim((string)$r['doc_number']), 'doc_date' => $doc_date,
                'created_by' => $uid, 'created_at' => date('Y-m-d H:i:s'),
            ];
        }
        if ($errors) { $this->_json(['error' => 'Ada baris tidak valid — tidak ada yang disimpan', 'detail' => $errors], 422); return; }
        if (!$ins) { $this->_json(['error' => 'Tidak ada baris utk disimpan'], 422); return; }
        $this->db->trans_start();
        $this->db->where(['month' => $m, 'year' => $y])->where_in('subdivision_id', array_keys($sids))
            ->delete('pph21_nonpegawai');
        $this->db->insert_batch('pph21_nonpegawai', $ins);
        $this->db->trans_complete();
        if (!$this->db->trans_status()) { $this->_json(['error' => 'Gagal menyimpan (DB)'], 500); return; }
        $this->_json(['saved' => count($ins), 'cvs' => count($sids)]);
    }

    /** Kertas kerja Excel satu CV. */
    public function export_excel() {
        $m = (int)$this->input->get('month'); $y = (int)$this->input->get('year');
        $sid = (int)$this->input->get('subdivision_id');
        $data = $this->_cv_data($m, $y, $sid);
        if (!$data) { $this->_json(['error' => 'Tidak ada data utk CV/masa ini'], 404); return; }
        $ss = $this->pph21_np_workbook->kertas_kerja($data['meta'], $data['rows']);
        $this->_xlsx($ss, sprintf('%02d %s PPh21 TDK TETAP - %s.xlsx', $m, $this->_month_name($m), $data['meta']['cv']));
    }

    /** XML Bp21Bulk satu CV (gabungan TT + tenaga ahli — "1 kesatuan"). */
    public function export_xml() {
        $m = (int)$this->input->get('month'); $y = (int)$this->input->get('year');
        $sid = (int)$this->input->get('subdivision_id');
        $data = $this->_cv_data($m, $y, $sid);
        if (!$data) { $this->_json(['error' => 'Tidak ada data utk CV/masa ini'], 404); return; }
        if ($data['meta']['npwp'] === '') { $this->_json(['error' => 'NPWP CV belum diisi di config pph21_export.php'], 422); return; }
        $xml = $this->pph21_np_calc->xml_bulk($data['meta']['npwp'], $m, $y, $data['rows']);
        $fname = sprintf('%02d %s - PPH21 TDK TETAP %s.xml', $m, $this->_month_name($m), $data['meta']['cv']);
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.str_replace('"', '', $fname).'"');
        echo $xml; exit;
    }

    /** ZIP XML semua CV yang punya data pada masa tsb. */
    public function export_xml_all() {
        $m = (int)$this->input->get('month'); $y = (int)$this->input->get('year');
        $sids = $this->db->select('DISTINCT subdivision_id AS sid', false)->from('pph21_nonpegawai')
            ->where(['month' => $m, 'year' => $y])->get()->result();
        $tmp = tempnam(sys_get_temp_dir(), 'np21');
        $zip = new ZipArchive(); $zip->open($tmp, ZipArchive::OVERWRITE);
        $count = 0;
        foreach ($sids as $s) {
            $data = $this->_cv_data($m, $y, (int)$s->sid);
            if (!$data || $data['meta']['npwp'] === '') continue;
            $xml = $this->pph21_np_calc->xml_bulk($data['meta']['npwp'], $m, $y, $data['rows']);
            $zip->addFromString(sprintf('%02d %s - PPH21 TDK TETAP %s.xml', $m, $this->_month_name($m), $data['meta']['cv']), $xml);
            $count++;
        }
        $zip->close();
        if (!$count) { @unlink($tmp); $this->_json(['error' => 'Tidak ada data (atau NPWP CV belum diisi)'], 404); return; }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="PPh21 TDK TETAP '.sprintf('%02d-%d', $m, $y).' (semua CV).zip"');
        header('Content-Length: '.filesize($tmp));
        readfile($tmp); @unlink($tmp); exit;
    }

    // ------------------------------------------------------------------ helpers

    /** CV (subdivision) ber-NPWP dari config, keyed id. */
    private function _cvs() {
        $npwp = $this->config->item('pph21_npwp');
        $out = [];
        foreach ($this->db->select('id, subdivision_name')->from('subdivision')
                 ->order_by('subdivision_name')->get()->result() as $s) {
            $out[(int)$s->id] = ['id' => (int)$s->id, 'name' => trim($s->subdivision_name),
                'npwp' => isset($npwp[(int)$s->id]) ? $npwp[(int)$s->id] : ''];
        }
        return $out;
    }

    private function _kode_list() {
        $out = [];
        foreach (Pph21_np_calc::$REF as $kode => $d) {
            $out[] = ['kode' => $kode, 'deemed' => $d[0], 'jenis' => $d[1], 'nama' => $d[2]];
        }
        return $out;
    }

    private function _cv_data($m, $y, $sid) {
        $rows = $this->db->from('pph21_nonpegawai')
            ->where(['month' => $m, 'year' => $y, 'subdivision_id' => $sid])
            ->order_by('kode_objek, nama')->get()->result_array();
        if (!$rows) return null;
        $cvs = $this->_cvs();
        $cv = isset($cvs[$sid]) ? $cvs[$sid] : ['name' => '(?)', 'npwp' => ''];
        return ['meta' => ['cv' => $cv['name'], 'npwp' => $cv['npwp'], 'month' => $m, 'year' => $y], 'rows' => $rows];
    }

    private function _norm($s) {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string)$s));
    }

    private function _amount($v) {
        if ($v === null || $v === '') return 0.0;
        if (is_numeric($v)) return round((float)$v, 2);
        $s = trim(str_replace(['Rp', ' '], '', (string)$v));
        if ($s === '' || $s === '-') return 0.0;
        if (strpos($s, ',') !== false) $s = str_replace(['.', ','], ['', '.'], $s);
        return is_numeric($s) ? round((float)$s, 2) : 0.0;
    }

    /** Tanggal dari sel Excel: serial number, "Y-m-d", "d/m/Y", atau DateTime string. */
    private function _date($v) {
        if ($v === null || $v === '') return null;
        if (is_numeric($v) && $v > 20000) {
            return XlsDate::excelToDateTimeObject((float)$v)->format('Y-m-d');
        }
        $s = trim((string)$v);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s'] as $f) {
            $d = DateTime::createFromFormat($f, $s);
            if ($d && $d->format($f === 'Y-m-d H:i:s' ? 'Y-m-d H:i:s' : $f) === $s) return $d->format('Y-m-d');
        }
        $t = strtotime($s);
        return $t ? date('Y-m-d', $t) : null;
    }

    private function _month_name($m) {
        $n = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return $n[(int)$m];
    }

    private function _xlsx($ss, $fname) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.str_replace('"', '', $fname).'"');
        header('Cache-Control: max-age=0');
        (new Xlsx($ss))->save('php://output');
        exit;
    }
}

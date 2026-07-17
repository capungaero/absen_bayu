<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once FCPATH.'lib/vendor/autoload.php';
require_once __DIR__.'/Pph21_np_calc.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Pph21_np_workbook — Excel utk tools PPh21 non-pegawai-tetap:
 *  - template import (sheet PETUNJUK, INPUT dgn dropdown, REF, REF-CV)
 *  - kertas kerja per CV (format meniru template import Coretax: DATA + kolom arsip)
 */
class Pph21_np_workbook {

    private function xy($c, $r) {
        return Coordinate::stringFromColumnIndex($c).$r;
    }

    /**
     * Template import satu masa, prefilled baris tersimpan (round-trip).
     *
     * @param int   $month @param int $year
     * @param array $cvs  [{id, name, npwp}]
     * @param array $rows baris tersimpan: cv, nik, nama, kode_objek, ptkp,
     *                    hari_kerja, neto, doc_number, doc_date, keterangan
     */
    public function template($month, $year, $cvs, $rows) {
        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        $ws = $ss->getActiveSheet();
        $ws->setTitle('PETUNJUK');
        $lines = [
            'TEMPLATE IMPORT PPh 21 PEGAWAI TIDAK TETAP & TENAGA AHLI — MASA '.$month.'/'.$year,
            '',
            'Isi sheet INPUT, satu baris per orang per pembayaran. Kolom:',
            ' - CV          : pilih dari dropdown (harus persis, daftar di sheet REF-CV).',
            ' - NIK         : 16 digit. NIK tidak valid akan diberi warning saat import.',
            ' - KODE OBJEK  : pilih dari dropdown (daftar + tarif di sheet REF). Umum dipakai:',
            '                 21-100-35 = upah pegawai tidak tetap dibayar bulanan (tarif TER bulanan)',
            '                 21-100-24 = upah harian/borongan rata-rata <= 2,5 jt/hari (0% / 0,5%)',
            '                 21-100-07 = tenaga ahli (5% x 50% bruto)   21-100-20 = jasa lainnya',
            ' - PTKP        : TK/0, K/1, dst — menentukan kategori TER utk kode bertarif TER.',
            ' - JUMLAH HARI : wajib utk kode upah harian (pembagi rata-rata per hari).',
            ' - PEMBAYARAN  : nilai yang benar-benar dibayarkan (neto). PPh ditanggung perusahaan',
            '                 (gross-up) — bruto & PPh dihitung otomatis oleh sistem saat import.',
            '                 Tenaga ahli: isi nilai jasa saja (exclude material/upah pihak lain).',
            ' - NO./TGL DOKUMEN : nomor & tanggal bukti pembayaran internal (masuk ke bukti potong).',
            '',
            'Setelah diisi: upload di halaman Tools > PPh21 Tidak Tetap & Tenaga Ahli,',
            'periksa hasil hitung di preview, lalu Simpan. Import mengganti seluruh data',
            'masa+CV yang sama (re-import file revisi tidak membuat dobel).',
        ];
        foreach ($lines as $i => $t) $ws->setCellValue('A'.($i + 1), $t);
        $ws->getStyle('A1')->getFont()->setBold(true);
        $ws->getColumnDimension('A')->setWidth(100);

        // REF kode objek
        $wr = $ss->createSheet(); $wr->setTitle('REF');
        $wr->fromArray(['Kode Objek', 'Nama Objek Pajak', 'Deemed (%DPP)', 'Jenis Tarif'], null, 'A1');
        $r = 2;
        foreach (Pph21_np_calc::$REF as $kode => $d) {
            $wr->fromArray([$kode, $d[2], $d[0], $d[1]], null, 'A'.$r); $r++;
        }
        $ref_last = $r - 1;
        $wr->getStyle('A1:D1')->getFont()->setBold(true);
        foreach (['A' => 12, 'B' => 90, 'C' => 13, 'D' => 10] as $col => $w) $wr->getColumnDimension($col)->setWidth($w);

        // REF-CV
        $wc = $ss->createSheet(); $wc->setTitle('REF-CV');
        $wc->fromArray(['CV / Badan Usaha', 'NPWP Pemotong'], null, 'A1');
        $r = 2;
        foreach ($cvs as $cv) {
            $wc->setCellValue('A'.$r, $cv['name']);
            $wc->setCellValueExplicit('B'.$r, (string)$cv['npwp'], DataType::TYPE_STRING);
            $r++;
        }
        $cv_last = $r - 1;
        $wc->getStyle('A1:B1')->getFont()->setBold(true);
        $wc->getColumnDimension('A')->setWidth(36); $wc->getColumnDimension('B')->setWidth(20);

        // INPUT
        $wi = $ss->createSheet(1); $wi->setTitle('INPUT');
        $wi->setCellValue('A1', 'INPUT PPh 21 TIDAK TETAP & TENAGA AHLI — MASA '.$month.'/'.$year);
        $wi->getStyle('A1')->getFont()->setBold(true);
        $hdr = ['NO', 'CV', 'NIK', 'NAMA', 'KODE OBJEK', 'PTKP', 'JUMLAH HARI',
                'PEMBAYARAN (NETO)', 'NO. DOKUMEN', 'TGL DOKUMEN', 'KETERANGAN'];
        foreach ($hdr as $c => $t) $wi->setCellValue($this->xy($c + 1, 3), $t);
        $wi->getStyle('A3:K3')->getFont()->setBold(true);
        $wi->getStyle('A3:K3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');

        $r = 4;
        foreach ($rows as $i => $e) {
            $wi->setCellValue('A'.$r, $i + 1);
            $wi->setCellValue('B'.$r, $e['cv']);
            $wi->setCellValueExplicit('C'.$r, (string)$e['nik'], DataType::TYPE_STRING);
            $wi->setCellValue('D'.$r, $e['nama']);
            $wi->setCellValue('E'.$r, $e['kode_objek']);
            $wi->setCellValue('F'.$r, $e['ptkp']);
            if ((int)$e['hari_kerja'] > 0) $wi->setCellValue('G'.$r, (int)$e['hari_kerja']);
            $wi->setCellValue('H'.$r, (float)$e['neto']);
            $wi->setCellValue('I'.$r, $e['doc_number']);
            $wi->setCellValue('J'.$r, $e['doc_date']);
            $wi->setCellValue('K'.$r, $e['keterangan']);
            $r++;
        }
        $data_last = max($r - 1, 4);
        $end = $data_last + 60; // baris kosong ekstra dgn dropdown utk input baru
        $this->dropdown($wi, 'B', 4, $end, "'REF-CV'!\$A\$2:\$A\$".$cv_last);
        $this->dropdown($wi, 'E', 4, $end, "'REF'!\$A\$2:\$A\$".$ref_last);
        $this->dropdown($wi, 'F', 4, $end, '"TK/0,TK/1,TK/2,TK/3,K/0,K/1,K/2,K/3"');
        $wi->getStyle('H4:H'.$end)->getNumberFormat()->setFormatCode('#,##0.00;(#,##0.00);""');
        $wi->getStyle('J4:J'.$end)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $wi->getStyle('A3:K'.$end)->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
        foreach (['A'=>5,'B'=>34,'C'=>20,'D'=>30,'E'=>13,'F'=>7,'G'=>12,'H'=>17,'I'=>22,'J'=>13,'K'=>24] as $col => $w) {
            $wi->getColumnDimension($col)->setWidth($w);
        }
        $wi->freezePane('A4');
        $ss->setActiveSheetIndexByName('INPUT');
        return $ss;
    }

    private function dropdown($ws, $col, $r0, $r1, $formula) {
        $dv = new DataValidation();
        $dv->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_INFORMATION)
           ->setAllowBlank(true)->setShowDropDown(true)->setFormula1($formula);
        for ($r = $r0; $r <= $r1; $r++) $ws->getCell($col.$r)->setDataValidation(clone $dv);
    }

    /**
     * Kertas kerja satu CV — format kolom meniru template import Coretax
     * (sheet DATA sampel Juni) + kolom arsip internal (nama, neto, PPh).
     *
     * @param array $meta ['cv','npwp','month','year']
     * @param array $rows baris tersimpan CV tsb (sudah terhitung)
     */
    public function kertas_kerja($meta, $rows) {
        $calc = new Pph21_np_calc();
        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $ws = $ss->getActiveSheet();
        $ws->setTitle('DATA');
        $last_day = date('Y-m-t', mktime(0, 0, 0, (int)$meta['month'], 1, (int)$meta['year']));

        $ws->setCellValue('A1', 'NPWP Pemotong');
        $ws->setCellValueExplicit('C1', (string)$meta['npwp'], DataType::TYPE_STRING);
        $ws->setCellValue('E1', $meta['cv'].' — PPh 21 Tidak Tetap & Tenaga Ahli — Masa '.$meta['month'].'/'.$meta['year']);
        $ws->getStyle('A1:E1')->getFont()->setBold(true);

        $hdr = ['', 'Masa Pajak', 'Tahun Pajak', 'NPWP', 'ID TKU Penerima Penghasilan', 'Status PTKP',
                'Fasilitas', 'Kode Objek Pajak', 'Penghasilan', 'Deemed', 'Tarif',
                'Jenis Dok. Referensi', 'Nomor Dok. Referensi', 'Tanggal Dok. Referensi',
                'ID TKU Pemotong', 'Tanggal Pemotongan', '', 'NAMA', 'NETO DIBAYAR', 'PPh 21', 'KETERANGAN'];
        foreach ($hdr as $c => $t) { if ($t !== '') $ws->setCellValue($this->xy($c + 1, 3), $t); }
        $ws->getStyle('B3:U3')->getFont()->setBold(true);
        $ws->getStyle('B3:P3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');
        $ws->getStyle('R3:U3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4D6');

        $r = 4;
        foreach ($rows as $e) {
            $ws->setCellValue('B'.$r, (int)$meta['month']);
            $ws->setCellValue('C'.$r, (int)$meta['year']);
            $ws->setCellValueExplicit('D'.$r, (string)$e['nik'], DataType::TYPE_STRING);
            $ws->setCellValueExplicit('E'.$r, $e['nik'].'000000', DataType::TYPE_STRING);
            $ws->setCellValue('F'.$r, $e['ptkp']);
            $ws->setCellValue('G'.$r, 'N/A');
            $ws->setCellValue('H'.$r, $e['kode_objek']);
            $ws->setCellValue('I'.$r, (float)$calc->xf($e['bruto']));
            $ws->setCellValue('J'.$r, (int)$e['deemed']);
            $ws->setCellValue('K'.$r, (float)$e['tarif']);
            $ws->setCellValue('L'.$r, 'PaymentProof');
            $ws->setCellValue('M'.$r, $e['doc_number']);
            $ws->setCellValue('N'.$r, $e['doc_date']);
            $ws->setCellValueExplicit('O'.$r, $meta['npwp'].'000000', DataType::TYPE_STRING);
            $ws->setCellValue('P'.$r, $last_day);
            $ws->setCellValue('R'.$r, $e['nama']);
            $ws->setCellValue('S'.$r, (float)$e['neto']);
            $ws->setCellValue('T'.$r, (float)$e['pph']);
            $ws->setCellValue('U'.$r, $e['keterangan']);
            $r++;
        }
        $rt = $r;
        $ws->setCellValue('R'.$rt, 'TOTAL');
        foreach (['I', 'S', 'T'] as $col) {
            $ws->setCellValue($col.$rt, "=SUM({$col}4:{$col}".($rt - 1).')');
        }
        $ws->getStyle('B'.$rt.':U'.$rt)->getFont()->setBold(true);
        $money = '#,##0.00;(#,##0.00);"-"';
        $ws->getStyle('I4:I'.$rt)->getNumberFormat()->setFormatCode($money);
        $ws->getStyle('S4:T'.$rt)->getNumberFormat()->setFormatCode($money);
        $ws->getStyle('B3:U'.$rt)->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
        foreach (['A'=>3,'B'=>10,'C'=>10,'D'=>19,'E'=>24,'F'=>10,'G'=>9,'H'=>15,'I'=>16,'J'=>8,'K'=>7,
                  'L'=>16,'M'=>22,'N'=>17,'O'=>24,'P'=>16,'Q'=>3,'R'=>30,'S'=>15,'T'=>13,'U'=>22] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
        $ws->freezePane('A4');
        return $ss;
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once FCPATH.'lib/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Pph21_des_workbook — workbook "REKAP PERHITUNGAN PPh 21 DESEMBER GROSS UP"
 * (format konsultan): sheet DAFTAR GAJI + 12 sheet bulanan (JAN..DES) + REKAP
 * setahun dengan kolom tambahan PPh DIPOTONG JAN-NOV & PPh DES (MPT).
 *
 * Rumus REKAP mengikuti template konsultan persis (biaya jabatan cap
 * 500rb x masa kerja, PTKP, PKP rounddown ribuan, progresif Ps.17 + varian
 * non-NPWP 120%). Kolom TUNJANGAN PPh (I) ditulis sebagai NILAI hasil iterasi
 * PHP — menggantikan rumus circular I=AB milik template yang butuh iterative
 * calculation Excel.
 */
class Pph21_des_workbook {

    private static $MONTHS = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN',
                              'JUL', 'AGUS', 'SEPT', 'OKT', 'NOV', 'DES'];

    private function xy($c, $r) {
        return Coordinate::stringFromColumnIndex($c).$r;
    }

    /**
     * @param array $meta ['cv','npwp','year']
     * @param array $emps per karyawan:
     *   name, jabatan, nik, ptkp, masa_awal, masa_akhir,
     *   monthly[m] = ['thp','tunj','pajak','premi','bonus','jamsostek'],
     *   i_annual (tunjangan PPh setahun), ac (PPh dipotong Jan-Nov)
     */
    public function build($meta, $emps) {
        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $this->sheet_daftar_gaji($ss->getActiveSheet(), $meta, $emps);
        foreach (self::$MONTHS as $i => $mn) {
            $this->sheet_bulan($ss->createSheet(), $mn, $i + 1, $emps);
        }
        $this->sheet_rekap($ss->createSheet(), $meta, $emps);
        $ss->setActiveSheetIndexByName('REKAP');
        return $ss;
    }

    // ------------------------------------------------------------ DAFTAR GAJI
    private function sheet_daftar_gaji($ws, $meta, $emps) {
        $ws->setTitle('DAFTAR GAJI');
        $ws->setCellValue('A1', 'DAFTAR GAJI — '.$meta['cv'].' — TAHUN '.$meta['year']
            .($meta['npwp'] !== '' ? ' — NPWP '.$meta['npwp'] : ''));
        $ws->getStyle('A1')->getFont()->setBold(true);
        $ws->setCellValue('A4', 'NO.'); $ws->setCellValue('B4', 'NAMA PEGAWAI');
        $ws->setCellValue('E4', 'STATUS'); $ws->setCellValue('F4', 'MASA ');
        $ws->setCellValue('C5', 'Jabatan'); $ws->setCellValue('D5', 'NPWP / NIK');
        $ws->setCellValue('F5', 'KERJA');
        $ws->getStyle('A4:H5')->getFont()->setBold(true);
        $r = 6;
        foreach ($emps as $i => $e) {
            $ws->setCellValue('A'.$r, $i + 1);
            $ws->setCellValue('B'.$r, $e['name']);
            $ws->setCellValue('C'.$r, $e['jabatan']);
            $ws->setCellValueExplicit('D'.$r, (string)$e['nik'], DataType::TYPE_STRING);
            $ws->setCellValue('E'.$r, $e['ptkp'] !== '' ? $e['ptkp'] : 'TK/0');
            $ws->setCellValue('F'.$r, (int)$e['masa_awal']);
            $ws->setCellValue('G'.$r, (int)$e['masa_akhir']);
            $ws->setCellValue('H'.$r, "=(G{$r}-F{$r})+1");
            $r++;
        }
        $ws->getStyle('A4:H'.($r - 1))->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
        foreach (['A'=>5,'B'=>30,'C'=>22,'D'=>20,'E'=>8,'F'=>6,'G'=>6,'H'=>6] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
    }

    // ---------------------------------------------------------- SHEET BULANAN
    private function sheet_bulan($ws, $mn, $m, $emps) {
        $ws->setTitle($mn);
        $full = ['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
        $ws->setCellValue('A1', $full[$m - 1]);
        $ws->getStyle('A1')->getFont()->setBold(true);
        $ws->setCellValue('E2', 'PENGHASILAN'); $ws->setCellValue('L2', 'POTONGAN');
        $hdr3 = ['A'=>'NO','B'=>'NAMA PEGAWAI','C'=>'JABATAN','D'=>'STATUS','E'=>'GAJI',
                 'F'=>'TUNJANGAN','G'=>'TUNJANGAN','H'=>'TUNJANGAN','I'=>'LEMBUR',
                 'J'=>'JKK, JKM JPK','K'=>'THR/ BONUS','L'=>'JAMSOSTEK'];
        foreach ($hdr3 as $col => $t) $ws->setCellValue($col.'3', $t);
        foreach (['E'=>'POKOK','F'=>'LAINNYA','H'=>'PAJAK','J'=>'DIBAYARKAN','L'=>'TK'] as $col => $t) {
            $ws->setCellValue($col.'4', $t);
        }
        $ws->getStyle('A2:L4')->getFont()->setBold(true);
        $ws->getStyle('A3:L4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');

        $r = 5;
        foreach ($emps as $e) {
            $dg = $r + 1; // baris DAFTAR GAJI terkait
            $ws->setCellValue('A'.$r, "='DAFTAR GAJI'!A{$dg}");
            $ws->setCellValue('B'.$r, "=IF('DAFTAR GAJI'!B{$dg}<>\"\",'DAFTAR GAJI'!B{$dg},\"\")");
            $ws->setCellValue('C'.$r, "=IF('DAFTAR GAJI'!C{$dg}<>\"\",'DAFTAR GAJI'!C{$dg},\"\")");
            $ws->setCellValue('D'.$r, "=IF('DAFTAR GAJI'!E{$dg}<>\"\",'DAFTAR GAJI'!E{$dg},\"\")");
            $d = isset($e['monthly'][$m]) ? $e['monthly'][$m] : null;
            if ($d) {
                if ($d['thp'] != 0)       $ws->setCellValue('E'.$r, round($d['thp'], 2));
                if ($d['tunj'] != 0)      $ws->setCellValue('F'.$r, round($d['tunj'], 2));
                if ($d['pajak'] != 0)     $ws->setCellValue('H'.$r, round($d['pajak'], 2));
                if ($d['premi'] != 0)     $ws->setCellValue('J'.$r, round($d['premi'], 2));
                if ($d['bonus'] != 0)     $ws->setCellValue('K'.$r, round($d['bonus'], 2));
                if ($d['jamsostek'] != 0) $ws->setCellValue('L'.$r, round($d['jamsostek'], 2));
            }
            $r++;
        }
        $rt = $r;
        $ws->setCellValue('B'.$rt, 'TOTAL');
        foreach (['E','F','G','H','I','J','K','L'] as $col) {
            $ws->setCellValue($col.$rt, "=SUM({$col}5:{$col}".($rt - 1).')');
        }
        $ws->getStyle('B'.$rt.':L'.$rt)->getFont()->setBold(true);
        $ws->getStyle('E5:L'.$rt)->getNumberFormat()->setFormatCode('#,##0;(#,##0);"-"');
        $ws->getStyle('A3:L'.$rt)->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
        foreach (['A'=>5,'B'=>30,'C'=>20,'D'=>8,'E'=>13,'F'=>13,'G'=>12,'H'=>13,'I'=>11,'J'=>13,'K'=>12,'L'=>12] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
        $ws->freezePane('E5');
    }

    // ----------------------------------------------------------------- REKAP
    private function sheet_rekap($ws, $meta, $emps) {
        $ws->setTitle('REKAP');
        $ws->setCellValue('A1', 'PERHITUNGAN PAJAK — '.$meta['cv'].' — TAHUN '.$meta['year']);
        $ws->getStyle('A1')->getFont()->setBold(true);

        $h2 = ['H'=>'PENGHASILAN','O'=>'PENGHASILAN','P'=>'TOTAL PENGHASILAN','Q'=>'PENGURANG',
               'U'=>'PENGHASILAN','V'=>'PTKP','W'=>'PKP','X'=>'PPh TERUTANG SETAHUN',
               'Z'=>'PPh Terutang Setahun','AB'=>'PPh ','AC'=>'PPh DIPOTONG','AD'=>'PPh DES'];
        $h3 = ['A'=>'NO.','B'=>'NAMA PEGAWAI','C'=>'NPWP','D'=>'STATUS','E'=>'MASA ','G'=>'JUMLAH',
               'H'=>'GAJI','I'=>'TUNJANGAN','J'=>'TUNJANGAN','K'=>'HONOR DAN','L'=>'PREMI ASS',
               'M'=>'NATURA','N'=>'JUMLAH PENGH','O'=>'TDK TERATUR','Q'=>'BIAYA ','R'=>'BIAYA ',
               'S'=>'PENSIUN, JHT ','T'=>'JUMLAH ','U'=>'NETO','X'=>'ADA','Y'=>'TIDAK ADA',
               'Z'=>'ADA','AA'=>'TIDAK ADA','AB'=>'TERUTANG','AC'=>'JAN-NOV','AD'=>'(MPT)'];
        $h4 = ['E'=>'KERJA','H'=>'POKOK','I'=>'PPh','J'=>'LAINNYA','K'=>'LAINNYA','L'=>'DIBAYARKAN',
               'M'=>'BUKAN OBJEK','N'=>'TERATUR','Q'=>'JABATAN','R'=>'JABATAN','S'=>'BAYAR SENDIRI',
               'T'=>'PENGURANG','U'=>'SETAHUN','X'=>'NPWP','Y'=>'NPWP','Z'=>'NPWP','AA'=>'NPWP',
               'AB'=>'SETAHUN'];
        foreach ($h2 as $col => $t) $ws->setCellValue($col.'2', $t);
        foreach ($h3 as $col => $t) $ws->setCellValue($col.'3', $t);
        foreach ($h4 as $col => $t) $ws->setCellValue($col.'4', $t);
        $ws->getStyle('A2:AD4')->getFont()->setBold(true);
        $ws->getStyle('A2:AD4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');
        $ws->getStyle('AC2:AD4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4D6');

        // rumus penjumlahan 12 bulan utk kolom sheet bulanan $mc
        $sum12 = function ($mc, $r) {
            $parts = [];
            foreach (self::$MONTHS as $mn) $parts[] = "{$mn}!{$mc}{$r}";
            return '=+'.implode('+', $parts);
        };

        $r = 5;
        foreach ($emps as $e) {
            $dg = $r + 1;
            $ws->setCellValue('A'.$r, "='DAFTAR GAJI'!A{$dg}");
            $ws->setCellValue('B'.$r, "=IF('DAFTAR GAJI'!B{$dg}<>\"\",'DAFTAR GAJI'!B{$dg},\"\")");
            $ws->setCellValue('C'.$r, "='DAFTAR GAJI'!D{$dg}");
            $ws->setCellValue('D'.$r, "='DAFTAR GAJI'!E{$dg}");
            $ws->setCellValue('E'.$r, "=IF('DAFTAR GAJI'!F{$dg}<>\"\",'DAFTAR GAJI'!F{$dg},\"\")");
            $ws->setCellValue('F'.$r, "=IF('DAFTAR GAJI'!G{$dg}<>\"\",'DAFTAR GAJI'!G{$dg},\"\")");
            $ws->setCellValue('G'.$r, "=IF('DAFTAR GAJI'!H{$dg}<>\"\",'DAFTAR GAJI'!H{$dg},\"\")");
            $ws->setCellValue('H'.$r, $sum12('E', $r));
            $ws->setCellValue('I'.$r, round($e['i_annual'], 2)); // NILAI iterasi (pengganti rumus circular =AB)
            $ws->setCellValue('J'.$r, $sum12('F', $r));
            $ws->setCellValue('K'.$r, 0);
            $ws->setCellValue('L'.$r, $sum12('J', $r));
            $ws->setCellValue('M'.$r, 0);
            $ws->setCellValue('N'.$r, "=H{$r}+I{$r}+J{$r}+K{$r}+L{$r}+M{$r}");
            $ws->setCellValue('O'.$r, $sum12('K', $r));
            $ws->setCellValue('P'.$r, "=N{$r}+O{$r}");
            $ws->setCellValue('Q'.$r, "=IF(5%*N{$r}>=G{$r}*500000,G{$r}*500000,5%*N{$r})");
            $ws->setCellValue('R'.$r, "=IF(5%*N{$r}>=G{$r}*500000,0,IF((5%*N{$r})+(5%*O{$r})>=G{$r}*500000,(G{$r}*500000)-(5%*N{$r}),5%*O{$r}))");
            $ws->setCellValue('S'.$r, $sum12('L', $r));
            $ws->setCellValue('T'.$r, "=Q{$r}+R{$r}+S{$r}");
            $ws->setCellValue('U'.$r, "=P{$r}-T{$r}");
            $ws->setCellValue('V'.$r, "=IF(D{$r}=\"K/3\",72000000,IF(D{$r}=\"K/2\",67500000,IF(D{$r}=\"K/1\",63000000,IF(D{$r}=\"K/0\",58500000,IF(D{$r}=\"TK/3\",67500000,IF(D{$r}=\"TK/2\",63000000,IF(D{$r}=\"TK/1\",58500000,IF(D{$r}=\"TK/0\",54000000,54000000))))))))");
            $ws->setCellValue('W'.$r, "=ROUNDDOWN(IF(U{$r}-V{$r}>0,U{$r}-V{$r},0),-3)");
            $ws->setCellValue('X'.$r, "=IF(W{$r}>5000000000,((1444000000+(35%*(W{$r}-5000000000)))),IF(W{$r}>500000000,((94000000+(30%*(W{$r}-500000000)))),IF(W{$r}>250000000,((31500000+(25%*(W{$r}-250000000)))),IF(W{$r}>60000000,(3000000+(15%*(W{$r}-60000000))),IF(W{$r}>=0,(5%*W{$r}))))))");
            $ws->setCellValue('Y'.$r, "=IF(W{$r}>5000000000,((1732800000+(35%*120%*(W{$r}-5000000000)))),IF(W{$r}>500000000,((112800000+(30%*120%*(W{$r}-500000000)))),IF(W{$r}>250000000,((37800000+(25%*120%*(W{$r}-250000000)))),IF(W{$r}>60000000,(3600000+(15%*120%*(W{$r}-60000000))),IF(W{$r}>=0,(5%*120%*W{$r}))))))");
            $ws->setCellValue('Z'.$r, "=IF(AA{$r}=0,X{$r},0)");
            $ws->setCellValue('AA'.$r, "=IF(C{$r}=\"\",Y{$r},0)");
            $ws->setCellValue('AB'.$r, "=IF(C{$r}=\"\",Y{$r},X{$r})");
            $ws->setCellValue('AC'.$r, round($e['ac'], 2));
            $ws->setCellValue('AD'.$r, "=AB{$r}-AC{$r}");
            $r++;
        }
        $rt = $r;
        $ws->setCellValue('B'.$rt, 'TOTAL');
        foreach (['H','I','J','K','L','M','N','O','P','Q','R','S','T','U','X','Y','Z','AA','AB','AC','AD'] as $col) {
            $ws->setCellValue($col.$rt, "=SUM({$col}5:{$col}".($rt - 1).')');
        }
        $ws->getStyle('B'.$rt.':AD'.$rt)->getFont()->setBold(true);
        $ws->getStyle('H5:AD'.$rt)->getNumberFormat()->setFormatCode('#,##0;(#,##0);"-"');
        $ws->getStyle('A2:AD'.$rt)->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
        $ws->getColumnDimension('A')->setWidth(5);
        $ws->getColumnDimension('B')->setWidth(30);
        foreach (array_merge(range('C', 'Z'), ['AA', 'AB', 'AC', 'AD']) as $col) {
            if ($col !== 'B') $ws->getColumnDimension($col)->setWidth(13);
        }
        $ws->freezePane('C5');
    }
}

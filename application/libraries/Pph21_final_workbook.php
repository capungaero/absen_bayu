<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once FCPATH.'lib/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Pph21_final_workbook — kertas kerja PPh 21 MASA PAJAK TERAKHIR per CV
 * (PMK 168/2023 Ps. 15: penghitungan ulang setahun dgn tarif Ps. 17 UU PPh
 * pada masa pajak terakhir — Desember, atau bulan resign utk yang berhenti).
 *
 * Sheet: PETUNJUK, MASA TERAKHIR, REKAP BULANAN, PTKP.
 * Nilai bulanan (bruto gross-up & PPh TER Jan..M-1) dihitung ulang dari
 * database oleh controller Pph21Final; tunjangan pajak masa terakhir dihitung
 * fixed-point (gross-up) di PHP, kolom CEK memverifikasinya di Excel.
 */
class Pph21_final_workbook {

    private static $PTKP = [
        'TK/0' => 54000000, 'TK/1' => 58500000, 'TK/2' => 63000000, 'TK/3' => 67500000,
        'K/0'  => 58500000, 'K/1'  => 63000000, 'K/2'  => 67500000, 'K/3'  => 72000000,
    ];

    /** Nilai PTKP setahun utk dipakai controller. Default TK/0 bila tak dikenal. */
    public static function ptkp_amount($status) {
        $s = strtoupper(str_replace(' ', '', (string)$status));
        return isset(self::$PTKP[$s]) ? self::$PTKP[$s] : self::$PTKP['TK/0'];
    }

    /** PPh tarif progresif Pasal 17 UU PPh (UU HPP) atas PKP setahun. */
    public static function ps17($pkp) {
        $b = [[60000000, 0.05], [250000000, 0.15], [500000000, 0.25], [5000000000, 0.30]];
        $t = 0; $lo = 0;
        foreach ($b as $x) {
            if ($pkp <= $lo) return $t;
            $t += (min($pkp, $x[0]) - $lo) * $x[1];
            $lo = $x[0];
        }
        if ($pkp > $lo) $t += ($pkp - $lo) * 0.35;
        return $t;
    }

    /**
     * @param array $meta ['cv','npwp','branch','month'(masa terakhir),'year']
     * @param array $rows per karyawan:
     *   ['name','nik','ptkp','masa_awal','masa_akhir','n_bulan',
     *    'bulanan' => [bulan => ['bruto_gu','pph']] (bulan 1..M-1),
     *    'bruto_akhir' (bruto bulan M sebelum tunjangan pajak),
     *    'tunj_pajak' (hasil fixed-point PHP)]
     * @return Spreadsheet
     */
    public function build($meta, $rows) {
        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $this->sheet_petunjuk($ss->getActiveSheet(), $meta);
        $this->sheet_utama($ss->createSheet(), $meta, $rows);
        $this->sheet_rekap($ss->createSheet(), $meta, $rows);
        $this->sheet_ptkp($ss->createSheet());
        $ss->setActiveSheetIndexByName('MASA TERAKHIR');
        return $ss;
    }

    private function xy($c, $r) {
        return Coordinate::stringFromColumnIndex($c).$r;
    }

    // ---------------------------------------------------------------- PETUNJUK
    private function sheet_petunjuk($ws, $meta) {
        $ws->setTitle('PETUNJUK');
        $lines = [
            'KERTAS KERJA PPh 21 MASA PAJAK TERAKHIR — '.$meta['cv'].' — MASA '.$meta['month'].'/'.$meta['year'],
            'Dihasilkan otomatis oleh tools Export PPh21 > Masa Pajak Terakhir (aplikasi absensi).',
            '',
            'DASAR: PP 58/2023 jo. PMK 168/2023 (Buku DJP hal. 61, 65-70). Pada masa pajak terakhir',
            '(Desember, atau bulan resign bagi yang berhenti) PPh 21 TIDAK memakai TER bulanan, tetapi:',
            '  PPh 21 setahun = (Bruto setahun - Biaya Jabatan - PTKP) x tarif progresif Ps. 17;',
            '  PPh 21 masa terakhir = PPh 21 setahun - PPh 21 yang sudah dipotong bulan-bulan sebelumnya.',
            '',
            'KARYAWAN YANG MASUK SHEET INI: hanya yang masa pajak terakhirnya = bulan periode dipilih',
            '(bulan 12 = semua karyawan aktif; bulan lain = karyawan yang nonaktif/resign bulan itu).',
            '',
            'ISI OTOMATIS DARI DATABASE:',
            ' - Bruto gross-up & PPh TER per bulan (REKAP BULANAN) dihitung ulang dgn mesin yang sama',
            '   dengan export bulanan (THP + Cash Bon/Piutang Kanvas + Input Manual + premi BPJS, TER 3 tahap).',
            ' - Biaya Jabatan = 5% bruto setahun, maks Rp500.000 x jumlah bulan kerja (Buku DJP hal. 65).',
            ' - Masa kerja dari users.join_date & bulan nonaktif (users.last_status).',
            ' - TUNJANGAN PAJAK masa terakhir dihitung gross-up fixed-point (pajak ditanggung perusahaan',
            '   ditambahkan ke bruto lalu dihitung ulang sampai konvergen). Kolom CEK GU harus 0.',
            '',
            'CATATAN:',
            ' - PPh masa terakhir NEGATIF = lebih potong; wajib dikembalikan ke karyawan bersama bukti',
            '   potong 1721-A1, paling lambat akhir bulan berikutnya (Buku DJP hal. 66 & 70).',
            ' - Pastikan payroll & Input Manual PPh21 SEMUA bulan tahun berjalan sudah final sebelum export.',
            ' - Karyawan WNA yang kewajiban pajak subjektifnya mulai tengah tahun (penghasilan disetahunkan,',
            '   Buku DJP hal. 66-68) TIDAK ditangani otomatis — hitung manual bila ada.',
            ' - Iuran pensiun/JHT dibayar karyawan & zakat via pemberi kerja tidak ada di data — bila ada,',
            '   kurangkan manual di kolom Biaya Jabatan/Neto.',
            '',
            'NPWP pemotong: '.($meta['npwp'] !== '' ? $meta['npwp'] : 'BELUM DIISI (config pph21_export.php)'),
        ];
        foreach ($lines as $i => $t) {
            $ws->setCellValue($this->xy(1, $i + 1), $t);
            if ($i === 0) $ws->getStyle($this->xy(1, $i + 1))->getFont()->setBold(true);
        }
        $ws->getColumnDimension('A')->setWidth(110);
    }

    // ------------------------------------------------------------ MASA TERAKHIR
    private function sheet_utama($ws, $meta, $rows) {
        $ws->setTitle('MASA TERAKHIR');
        $ws->setCellValue('A1', 'PPh PASAL 21 MASA PAJAK TERAKHIR — PENGHITUNGAN ULANG TARIF PASAL 17 (PMK 168/2023)');
        $ws->setCellValue('A2', $meta['cv'].' — '.$meta['branch']);
        $ws->setCellValue('A3', 'Masa Pajak Terakhir: '.$meta['month'].'/'.$meta['year']);
        $ws->getStyle('A1:A3')->getFont()->setBold(true);

        $hdr = ['NO.', 'NAMA PEGAWAI', 'NPWP', 'PTKP', 'MASA', 'BLN',
                'Bruto GU s.d. bulan sblmnya', 'Bruto masa terakhir', 'Tunjangan Pajak masa terakhir',
                'Bruto Setahun', 'Biaya Jabatan', 'Neto Setahun', 'PTKP Setahun',
                'PKP (dibulatkan ribuan)', 'PPh Ps.17 Setahun', 'PPh dipotong s.d. bulan sblmnya',
                'PPh Masa Terakhir (kurang/(lebih))', 'CEK GU (harus 0)'];
        foreach ($hdr as $c => $t) $ws->setCellValue($this->xy($c + 1, 5), $t);
        $ws->getStyle('A5:R5')->getFont()->setBold(true);
        $ws->getStyle('A5:R5')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $ws->getStyle('A5:R5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');

        $nm = max(0, (int)$meta['month'] - 1); // jumlah bulan sebelum masa terakhir
        $r0 = 6;
        foreach ($rows as $i => $e) {
            $r  = $r0 + $i;
            $rr = 5 + $i; // baris REKAP BULANAN terkait (data mulai baris 5)
            $ws->setCellValue($this->xy(1, $r), $i + 1);
            $ws->setCellValue($this->xy(2, $r), $e['name']);
            $ws->setCellValueExplicit($this->xy(3, $r), (string)$e['nik'], DataType::TYPE_STRING);
            $ws->setCellValue($this->xy(4, $r), $e['ptkp'] !== '' ? $e['ptkp'] : 'TK/0');
            $ws->setCellValue($this->xy(5, $r), sprintf('%02d-%02d', $e['masa_awal'], $e['masa_akhir']));
            $ws->setCellValue($this->xy(6, $r), (int)$e['n_bulan']);
            if ($nm > 0) {
                $c1 = Coordinate::stringFromColumnIndex(3);        // rekap: bruto mulai kolom C
                $c2 = Coordinate::stringFromColumnIndex(2 + $nm);
                $p1 = Coordinate::stringFromColumnIndex(3 + $nm);  // rekap: pph setelah blok bruto
                $p2 = Coordinate::stringFromColumnIndex(2 + 2 * $nm);
                $ws->setCellValue($this->xy(7, $r), "=SUM('REKAP BULANAN'!{$c1}{$rr}:{$c2}{$rr})");
                $ws->setCellValue($this->xy(16, $r), "=SUM('REKAP BULANAN'!{$p1}{$rr}:{$p2}{$rr})");
            } else {
                $ws->setCellValue($this->xy(7, $r), 0);
                $ws->setCellValue($this->xy(16, $r), 0);
            }
            $ws->setCellValue($this->xy(8, $r), round($e['bruto_akhir'], 2));
            $ws->setCellValue($this->xy(9, $r), round($e['tunj_pajak'], 2));
            $ws->setCellValue($this->xy(10, $r), "=G{$r}+H{$r}+I{$r}");
            $ws->setCellValue($this->xy(11, $r), "=MIN(0.05*J{$r},500000*F{$r})");
            $ws->setCellValue($this->xy(12, $r), "=J{$r}-K{$r}");
            $ws->setCellValue($this->xy(13, $r), "=VLOOKUP(D{$r},PTKP!\$A\$2:\$B\$9,2,FALSE)");
            $ws->setCellValue($this->xy(14, $r), "=FLOOR(MAX(0,L{$r}-M{$r}),1000)");
            $ws->setCellValue($this->xy(15, $r),
                "=0.05*MIN(N{$r},60000000)+0.15*MAX(0,MIN(N{$r},250000000)-60000000)"
                ."+0.25*MAX(0,MIN(N{$r},500000000)-250000000)"
                ."+0.3*MAX(0,MIN(N{$r},5000000000)-500000000)+0.35*MAX(0,N{$r}-5000000000)");
            $ws->setCellValue($this->xy(17, $r), "=O{$r}-P{$r}");
            $ws->setCellValue($this->xy(18, $r), "=I{$r}-MAX(0,Q{$r})");
        }
        $rt = $r0 + count($rows);
        $ws->setCellValue($this->xy(2, $rt), 'TOTAL');
        foreach (['G', 'H', 'I', 'J', 'K', 'O', 'P', 'Q'] as $col) {
            $ws->setCellValue($col.$rt, "=SUM({$col}{$r0}:{$col}".($rt - 1).')');
        }
        $ws->getStyle("A{$rt}:R{$rt}")->getFont()->setBold(true);

        $money = '#,##0.00;(#,##0.00);"-"';
        $ws->getStyle("G{$r0}:R{$rt}")->getNumberFormat()->setFormatCode($money);
        $ws->getStyle("A5:R{$rt}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
        foreach (['A'=>5,'B'=>30,'C'=>19,'D'=>7,'E'=>8,'F'=>5,'G'=>15,'H'=>14,'I'=>14,'J'=>15,
                  'K'=>13,'L'=>15,'M'=>14,'N'=>15,'O'=>14,'P'=>15,'Q'=>16,'R'=>12] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
        $ws->freezePane('A'.$r0);
    }

    // ------------------------------------------------------------ REKAP BULANAN
    private function sheet_rekap($ws, $meta, $rows) {
        $ws->setTitle('REKAP BULANAN');
        $ws->setCellValue('A1', 'REKAP BULANAN — bruto gross-up & PPh 21 TER yang dipotong per masa (dihitung ulang dari database)');
        $ws->getStyle('A1')->getFont()->setBold(true);
        $nm = max(0, (int)$meta['month'] - 1);
        $ws->setCellValue('A3', 'NO.'); $ws->setCellValue('B3', 'NAMA PEGAWAI');
        for ($m = 1; $m <= $nm; $m++) {
            $ws->setCellValue($this->xy(2 + $m, 3), 'BRUTO GU '.sprintf('%02d', $m));
            $ws->setCellValue($this->xy(2 + $nm + $m, 3), 'PPH '.sprintf('%02d', $m));
        }
        $ws->getStyle('A3:'.$this->xy(max(3, 2 + 2 * $nm), 3))->getFont()->setBold(true);
        $r0 = 5;
        foreach ($rows as $i => $e) {
            $r = $r0 + $i;
            $ws->setCellValue($this->xy(1, $r), $i + 1);
            $ws->setCellValue($this->xy(2, $r), $e['name']);
            for ($m = 1; $m <= $nm; $m++) {
                $b = isset($e['bulanan'][$m]) ? $e['bulanan'][$m] : ['bruto_gu' => 0, 'pph' => 0];
                if ($b['bruto_gu'] != 0) $ws->setCellValue($this->xy(2 + $m, $r), round($b['bruto_gu'], 2));
                if ($b['pph'] != 0)      $ws->setCellValue($this->xy(2 + $nm + $m, $r), round($b['pph'], 2));
            }
        }
        $rt = $r0 + count($rows);
        $ws->setCellValue($this->xy(2, $rt), 'TOTAL');
        for ($c = 3; $c <= 2 + 2 * $nm; $c++) {
            $cl = Coordinate::stringFromColumnIndex($c);
            $ws->setCellValue($cl.$rt, "=SUM({$cl}{$r0}:{$cl}".($rt - 1).')');
        }
        $ws->getStyle("A{$rt}:".$this->xy(max(3, 2 + 2 * $nm), $rt))->getFont()->setBold(true);
        $money = '#,##0.00;(#,##0.00);"-"';
        if ($nm > 0) {
            $ws->getStyle($this->xy(3, $r0).':'.$this->xy(2 + 2 * $nm, $rt))->getNumberFormat()->setFormatCode($money);
        }
        $ws->getColumnDimension('B')->setWidth(30);
        for ($c = 3; $c <= 2 + 2 * $nm; $c++) $ws->getColumnDimensionByColumn($c)->setWidth(13);
        $ws->freezePane('C'.$r0);
    }

    // ----------------------------------------------------------------------- PTKP
    private function sheet_ptkp($ws) {
        $ws->setTitle('PTKP');
        $ws->setCellValue('A1', 'STATUS'); $ws->setCellValue('B1', 'PTKP SETAHUN');
        $ws->getStyle('A1:B1')->getFont()->setBold(true);
        $r = 2;
        foreach (self::$PTKP as $s => $v) {
            $ws->setCellValue('A'.$r, $s);
            $ws->setCellValue('B'.$r, $v);
            $r++;
        }
        $ws->getStyle('B2:B9')->getNumberFormat()->setFormatCode('#,##0');
        $ws->getColumnDimension('B')->setWidth(16);
    }
}

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
 * Pph21_workbook — pembangun kertas kerja PPh 21 per CV (format "template besar").
 *
 * Sheet: PETUNJUK, DATA UMUM, PEGAWAI TETAP, TER, RINCIAN.
 * Kolom yang datanya ada di database terisi otomatis; kolom data luar
 * (konsumsi, uang jalan kanvas, subsidi, bonus) dibiarkan kosong untuk diisi
 * manual — seluruh rumus (bruto, tarif TER via VLOOKUP, gross-up iteratif,
 * kolom Cek) sudah terpasang dan menghitung ulang otomatis saat diisi.
 *
 * Tarif TER: PP 58/2023 jo. PMK 168/2023 (diverifikasi dari Buku DJP hal. 40-43).
 * Iterasi gross-up SOP dibuat otomatis via 3 kolom bantu tersembunyi (U,V,W):
 * tahap1 = TER(bruto); tahap2 = TER(bruto/(1-tahap1)); tahap3 = TER(bruto/(1-tahap2)).
 * Kolom O (tarif final) = tahap3; kolom T memverifikasi TER(gross-up) - O = 0.
 */
class Pph21_workbook {

    // [batas_bawah_eksklusif, tarif%] — baris pertama 0 = s.d. batas pertama.
    private static $TER_A = [[0,0],[5400000,0.25],[5650000,0.5],[5950000,0.75],[6300000,1],[6750000,1.25],
        [7500000,1.5],[8550000,1.75],[9650000,2],[10050000,2.25],[10350000,2.5],[10700000,3],
        [11050000,3.5],[11600000,4],[12500000,5],[13750000,6],[15100000,7],[16950000,8],
        [19750000,9],[24150000,10],[26450000,11],[28000000,12],[30050000,13],[32400000,14],
        [35400000,15],[39100000,16],[43850000,17],[47800000,18],[51400000,19],[56300000,20],
        [62200000,21],[68600000,22],[77500000,23],[89000000,24],[103000000,25],[125000000,26],
        [157000000,27],[206000000,28],[337000000,29],[454000000,30],[550000000,31],
        [695000000,32],[910000000,33],[1400000000,34]];
    private static $TER_B = [[0,0],[6200000,0.25],[6500000,0.5],[6850000,0.75],[7300000,1],[9200000,1.5],
        [10750000,2],[11250000,2.5],[11600000,3],[12600000,4],[13600000,5],[14950000,6],
        [16400000,7],[18450000,8],[21850000,9],[26000000,10],[27700000,11],[29350000,12],
        [31450000,13],[33950000,14],[37100000,15],[41100000,16],[45800000,17],[49500000,18],
        [53800000,19],[58500000,20],[64000000,21],[71000000,22],[80000000,23],[93000000,24],
        [109000000,25],[129000000,26],[163000000,27],[211000000,28],[374000000,29],
        [459000000,30],[555000000,31],[704000000,32],[957000000,33],[1405000000,34]];
    private static $TER_C = [[0,0],[6600000,0.25],[6950000,0.5],[7350000,0.75],[7800000,1],[8850000,1.25],
        [9800000,1.5],[10950000,1.75],[11200000,2],[12050000,3],[12950000,4],[14150000,5],
        [15550000,6],[17050000,7],[19500000,8],[22700000,9],[26600000,10],[28100000,11],
        [30100000,12],[32600000,13],[35400000,14],[38900000,15],[43000000,16],[47400000,17],
        [51200000,18],[55800000,19],[60400000,20],[66700000,21],[74500000,22],[83200000,23],
        [95600000,24],[110000000,25],[134000000,26],[169000000,27],[221000000,28],
        [390000000,29],[463000000,30],[561000000,31],[709000000,32],[965000000,33],[1419000000,34]];

    /** Tabel TER bulanan utk dipakai library lain (mis. Pph21_np_calc). */
    public static function ter_tables() {
        return ['A' => self::$TER_A, 'B' => self::$TER_B, 'C' => self::$TER_C];
    }

    /** Kategori TER dari status PTKP — logika sama dgn ter_formula(). */
    public static function ter_category($ptkp) {
        $p = strtoupper(str_replace(' ', '', (string)$ptkp));
        if (in_array($p, ['TK/0', 'TK/1', 'K/0'])) return 'A';
        if ($p === 'K/3') return 'C';
        return 'B';
    }

    /**
     * Bangun workbook satu CV.
     *
     * @param array $meta  ['cv'=>nama, 'npwp'=>string, 'branch'=>nama cabang,
     *                      'month'=>int, 'year'=>int, 'premi'=>[jkk,jkm,kes]]
     * @param array $rows  per karyawan: ['name','nik','position','ptkp','thp',
     *                      'cashbon' (CASHBON+PIUTANG KANVAS), 'bpjs'=>bool]
     * @return Spreadsheet
     */
    public function build($meta, $rows) {
        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $this->sheet_petunjuk($ss->getActiveSheet(), $meta);
        $this->sheet_data_umum($ss->createSheet(), $rows);
        $this->sheet_pegawai_tetap($ss->createSheet(), $meta, $rows);
        $this->sheet_ter($ss->createSheet());
        $this->sheet_rincian($ss->createSheet(), $meta, count($rows));
        $ss->setActiveSheetIndexByName('PEGAWAI TETAP');
        return $ss;
    }


    /** Koordinat string dari (kolomIndex, baris) — kompatibel PhpSpreadsheet versi lama. */
    private function xy($c, $r) {
        return Coordinate::stringFromColumnIndex($c).$r;
    }

    // ---------------------------------------------------------------- PETUNJUK
    private function sheet_petunjuk($ws, $meta) {
        $ws->setTitle('PETUNJUK');
        $lines = [
            'KERTAS KERJA PPh PASAL 21 — '.$meta['cv'].' — MASA '.$meta['month'].'/'.$meta['year'],
            'Dihasilkan otomatis oleh tools Export PPh21 (aplikasi absensi).',
            '',
            'KOLOM TERISI OTOMATIS DARI DATABASE:',
            ' - DATA UMUM: nama, jabatan, NIK (users.npwp_number), status PTKP (users.ptkp_status),',
            '   masa kerja (kolom G/H) dari tanggal mulai kerja (users.join_date) dan bulan nonaktif',
            '   tahun berjalan (users.last_status saat dinonaktifkan) — masa perolehan penghasilan PMK 168/2023.',
            ' - Gaji Pokok  = THP final e-absensi (payroll_detail.salary_thp).',
            ' - Tunjangan/Cash Bon = potongan CASHBON + PIUTANG KANVAS (pinjaman, penambah bruto)',
            '   + uang jalan kanvas dari tools Input Manual PPh21 (bila sudah diisi).',
            ' - Insentif/Tunjangan Lain, Subsidi Pajak/Lembur, Bonus/THR = dari tools Input Manual PPh21.',
            ' - JKK/JKM/BPJS KES = premi dibayar perusahaan, terisi utk karyawan yg bulan ini punya potongan BPJS.',
            '',
            'DATA DI LUAR ABSENSI (uang jalan kanvas, uang konsumsi jurnal 620017, subsidi/lembur, bonus/THR)',
            'diinput lewat menu Tools > Input Manual PPh21 (manual atau import template Excel) SEBELUM export;',
            'bila belum diisi, kolomnya kosong dan tetap bisa diisi langsung di Excel (rumus menghitung ulang otomatis).',
            '',
            'TARIF TER: otomatis (VLOOKUP ke sheet TER, PP 58/2023) TERMASUK iterasi gross-up SOP',
            '(3 tahap, kolom bantu U-W tersembunyi). Kolom "Cek" dan "Cek Tarif GU" harus 0.',
            'Bila PTKP kosong di database, baris memakai TK/0 — lengkapi users.ptkp_status.',
            '',
            'URUTAN KARYAWAN: mengikuti roster tahunan (kertas kerja Mei 2026) dan TETAP sampai Desember.',
            'Karyawan resign/tidak aktif tetap tampil dengan nilai nihil (aturan pajak); karyawan baru',
            'otomatis ditambahkan di urutan paling bawah dan permanen untuk bulan-bulan berikutnya.',
            '',
            'Setelah final: salin Total Penghasilan Bruto + Tarif TER ke file impor Coretax.',
            'NPWP pemotong: '.($meta['npwp'] !== '' ? $meta['npwp'] : 'BELUM DIISI (lihat config pph21_export.php)').
                ' | ID TKU: '.($meta['npwp'] !== '' ? $meta['npwp'].'000000' : '-'),
        ];
        foreach ($lines as $i => $t) {
            $ws->setCellValue($this->xy(1, $i + 1), $t);
            if ($i === 0) $ws->getStyle($this->xy(1, $i + 1))->getFont()->setBold(true);
        }
        $ws->getColumnDimension('A')->setWidth(110);
    }

    // --------------------------------------------------------------- DATA UMUM
    private function sheet_data_umum($ws, $rows) {
        $ws->setTitle('DATA UMUM');
        $ws->setCellValue('D1', '0 = TIDAK PUNYA NPWP');
        $ws->setCellValue('D2', '1234 = PUNYA NPWP');
        $hdr = ['NO.', 'NAMA PEGAWAI', 'JABATAN', 'NPWP', '', 'STATUS', 'MASA', '', ''];
        foreach ($hdr as $c => $t) $ws->setCellValue($this->xy($c + 1, 4), $t);
        $ws->setCellValue('G5', 'KERJA');
        $ws->getStyle('A4:I5')->getFont()->setBold(true);
        $r = 6;
        foreach ($rows as $i => $e) {
            $ws->setCellValue($this->xy(1, $r), $i + 1);
            $ws->setCellValue($this->xy(2, $r), $e['name']);
            $ws->setCellValue($this->xy(3, $r), $e['position']);
            $ws->setCellValueExplicit($this->xy(4, $r), (string)$e['nik'], DataType::TYPE_STRING);
            $ws->setCellValue($this->xy(5, $r), "=IF(D{$r}>0,1,0)");
            $ws->setCellValue($this->xy(6, $r), $e['ptkp'] !== '' ? $e['ptkp'] : 'TK/0');
            // masa perolehan penghasilan aktual (join/resign tahun berjalan) — PMK 168/2023
            $ma = isset($e['masa_awal'])  ? (int)$e['masa_awal']  : 1;
            $mk = isset($e['masa_akhir']) ? (int)$e['masa_akhir'] : 12;
            $ws->setCellValueExplicit($this->xy(7, $r), sprintf('%02d', $ma), DataType::TYPE_STRING);
            $ws->setCellValueExplicit($this->xy(8, $r), sprintf('%02d', $mk), DataType::TYPE_STRING);
            $ws->setCellValue($this->xy(9, $r), "=(H{$r}-G{$r})+1");
            $r++;
        }
        foreach (['A'=>5,'B'=>32,'C'=>22,'D'=>20,'E'=>6,'F'=>8,'G'=>6,'H'=>6,'I'=>6] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
    }

    // ----------------------------------------------------------- PEGAWAI TETAP
    private function sheet_pegawai_tetap($ws, $meta, $rows) {
        $ws->setTitle('PEGAWAI TETAP');
        $last_day = cal_days_in_month(CAL_GREGORIAN, (int)$meta['month'], (int)$meta['year']);
        $ws->setCellValue('A1', 'PERHITUNGAN PPh PASAL 21 PEGAWAI TETAP - TER BULANAN (GROSS UP)');
        $ws->setCellValue('A2', $meta['cv'].' — '.$meta['branch']);
        $ws->setCellValue('A3', 'Masa Pajak: '.$meta['month'].'/'.$meta['year'].
            ' | Tgl pemotongan: '.sprintf('%04d-%02d-%02d', $meta['year'], $meta['month'], $last_day));
        $ws->getStyle('A1:A3')->getFont()->setBold(true);

        $ws->setCellValue('A8', 'NO.');   $ws->setCellValue('B8', 'NAMA PEGAWAI');
        $ws->setCellValue('C8', 'NPWP');  $ws->setCellValue('D8', 'PTKP');
        $ws->setCellValue('E8', 'MASA');  $ws->setCellValue('F8', 'PENGHASILAN');
        $ws->setCellValue('K8', 'Di Bayar Oleh Perusahaan');
        $sub = ['F'=>'Gaji Pokok (THP)','G'=>'Tunjangan/Cash Bon','H'=>'Insentif/ Tunjangan Lain',
                'I'=>'Subsidi Pajak / Lembur','J'=>'Bonus/ THR','K'=>'JKK','L'=>'JKM','M'=>'BPJS KES',
                'N'=>'Total Penghasilan Bruto','O'=>'Tarif TER','P'=>'Tarif TER/100',
                'Q'=>'Total Penghasilan Bruto Gross Up','R'=>'PPh 21 Gross Up','S'=>'Cek',
                'T'=>'Cek Tarif GU (harus 0)','U'=>'tarif tahap1','V'=>'tarif tahap2','W'=>'tarif tahap3'];
        foreach ($sub as $col => $t) $ws->setCellValue($col.'9', $t);
        $ws->getStyle('A8:W9')->getFont()->setBold(true);
        $ws->getStyle('A8:W9')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $ws->getStyle('A8:W9')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');
        // kolom isian manual ditandai oranye (ala SOP)
        $ws->getStyle('G9:J9')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4D6');

        $premi = $meta['premi'];
        $r0 = 10;
        foreach ($rows as $i => $e) {
            $r  = $r0 + $i;
            $dr = $r - 4; // baris DATA UMUM terkait
            $ws->setCellValue($this->xy(1, $r), $i + 1);
            $ws->setCellValue($this->xy(2, $r), "='DATA UMUM'!B{$dr}");
            $ws->setCellValue($this->xy(3, $r), "='DATA UMUM'!D{$dr}"); // NIK/NPWP 16 digit (bukan flag)
            $ws->setCellValue($this->xy(4, $r), "='DATA UMUM'!F{$dr}");
            $ws->setCellValue($this->xy(5, $r), "='DATA UMUM'!I{$dr}");
            $ws->setCellValue($this->xy(6, $r), round($e['thp'], 2));
            if ($e['cashbon'] > 0) $ws->setCellValue($this->xy(7, $r), round($e['cashbon'], 2));
            // H, I, J terisi dari tools Input Manual PPh21 (pph21_manual); kosong bila belum diisi
            if (!empty($e['insentif'])) $ws->setCellValue($this->xy(8, $r), round($e['insentif'], 2));
            if (!empty($e['subsidi']))  $ws->setCellValue($this->xy(9, $r), round($e['subsidi'], 2));
            if (!empty($e['bonus']))    $ws->setCellValue($this->xy(10, $r), round($e['bonus'], 2));
            if (!empty($e['bpjs'])) {
                $ws->setCellValue($this->xy(11, $r), $premi['jkk']);
                $ws->setCellValue($this->xy(12, $r), $premi['jkm']);
                $ws->setCellValue($this->xy(13, $r), $premi['kes']);
            }
            $ws->setCellValue($this->xy(14, $r), "=+F{$r}+G{$r}+H{$r}+I{$r}+J{$r}+K{$r}+L{$r}+M{$r}");
            $ws->setCellValue($this->xy(15, $r), "=W{$r}");                       // tarif final (iterasi tahap 3)
            $ws->setCellValue($this->xy(16, $r), "=O{$r}/100");
            $ws->setCellValue($this->xy(17, $r), "=N{$r}/(1-P{$r})");
            $ws->setCellValue($this->xy(18, $r), "=P{$r}*Q{$r}");
            $ws->setCellValue($this->xy(19, $r), "=Q{$r}-R{$r}-N{$r}");
            $ws->setCellValue($this->xy(20, $r), '='.$this->ter_formula("Q{$r}", $r)."-O{$r}");
            $ws->setCellValue($this->xy(21, $r), '='.$this->ter_formula("N{$r}", $r));
            $ws->setCellValue($this->xy(22, $r), '='.$this->ter_formula("N{$r}/(1-U{$r}/100)", $r));
            $ws->setCellValue($this->xy(23, $r), '='.$this->ter_formula("N{$r}/(1-V{$r}/100)", $r));
        }
        $rt = $r0 + count($rows);
        $ws->setCellValue($this->xy(2, $rt), 'TOTAL');
        foreach (['F','G','H','I','J','K','L','M','N','Q','R'] as $col) {
            $ws->setCellValue($col.$rt, "=SUM({$col}{$r0}:{$col}".($rt - 1).')');
        }
        $ws->getStyle("A{$rt}:W{$rt}")->getFont()->setBold(true);

        $money = '#,##0.00;(#,##0.00);"-"';
        $ws->getStyle("F{$r0}:N{$rt}")->getNumberFormat()->setFormatCode($money);
        $ws->getStyle("Q{$r0}:S{$rt}")->getNumberFormat()->setFormatCode($money);
        $ws->getStyle("A8:W{$rt}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
        foreach (['A'=>5,'B'=>30,'C'=>19,'D'=>7,'E'=>6,'F'=>14,'G'=>14,'H'=>13,'I'=>11,'J'=>10,
                  'K'=>10,'L'=>10,'M'=>11,'N'=>16,'O'=>8,'P'=>9,'Q'=>16,'R'=>13,'S'=>8,'T'=>9] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
        foreach (['U', 'V', 'W'] as $col) $ws->getColumnDimension($col)->setVisible(false);
        $ws->freezePane('A'.$r0);
    }

    /** Formula tarif TER utk ekspresi nilai $val, PTKP di kolom $pcol baris $r. */
    private function ter_formula($val, $r, $pcol = 'D') {
        $nA = count(self::$TER_A) + 3; // data TER mulai baris 4
        $nB = count(self::$TER_B) + 3;
        $nC = count(self::$TER_C) + 3;
        return "IF(OR(\${$pcol}{$r}=\"TK/0\",\${$pcol}{$r}=\"TK/1\",\${$pcol}{$r}=\"K/0\"),"
             ."VLOOKUP({$val},TER!\$A\$4:\$B\${$nA},2,TRUE),"
             ."IF(\${$pcol}{$r}=\"K/3\",VLOOKUP({$val},TER!\$G\$4:\$H\${$nC},2,TRUE),"
             ."VLOOKUP({$val},TER!\$D\$4:\$E\${$nB},2,TRUE)))";
    }

    /**
     * Workbook GABUNGAN semua CV dalam 1 sheet, dgn kolom tambahan CV & PENEMPATAN.
     *
     * @param array $meta   ['branch','penempatan','month','year','premi']
     * @param array $groups per CV: ['cv'=>nama, 'rows'=>rows (spt build())]
     * @return Spreadsheet
     */
    public function build_combined($meta, $groups) {
        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $ws = $ss->getActiveSheet();
        $ws->setTitle('SEMUA CV');
        $ws->setCellValue('A1', 'PERHITUNGAN PPh PASAL 21 PEGAWAI TETAP - TER BULANAN (GROSS UP) — SEMUA CV');
        $ws->setCellValue('A2', $meta['branch'].' ('.$meta['penempatan'].')');
        $ws->setCellValue('A3', 'Masa Pajak: '.$meta['month'].'/'.$meta['year'].' | Urutan & baris nihil mengikuti roster tahunan per CV');
        $ws->getStyle('A1:A3')->getFont()->setBold(true);

        $hdr = ['NO.','NAMA PEGAWAI','CV','PENEMPATAN','NPWP','PTKP','MASA',
                'Gaji Pokok (THP)','Tunjangan/Cash Bon','Insentif/ Tunjangan Lain','Subsidi Pajak / Lembur','Bonus/ THR',
                'JKK','JKM','BPJS KES','Total Penghasilan Bruto','Tarif TER','Tarif TER/100',
                'Total Penghasilan Bruto Gross Up','PPh 21 Gross Up','Cek','Cek Tarif GU (harus 0)',
                'tarif tahap1','tarif tahap2','tarif tahap3'];
        $hrow = 5;
        foreach ($hdr as $c => $t) $ws->setCellValue($this->xy($c + 1, $hrow), $t);
        $ws->getStyle('A5:Y5')->getFont()->setBold(true);
        $ws->getStyle('A5:Y5')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $ws->getStyle('A5:Y5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');
        $ws->getStyle('I5:L5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4D6');

        $premi = $meta['premi'];
        $r = $hrow + 1;
        $r0 = $r;
        foreach ($groups as $g) {
            foreach ($g['rows'] as $i => $e) {
                $ws->setCellValue($this->xy(1, $r), $i + 1); // nomor roster per CV
                $ws->setCellValue($this->xy(2, $r), $e['name']);
                $ws->setCellValue($this->xy(3, $r), $g['cv']);
                $ws->setCellValue($this->xy(4, $r), $meta['penempatan']);
                $ws->setCellValueExplicit($this->xy(5, $r), (string)$e['nik'], DataType::TYPE_STRING);
                $ws->setCellValue($this->xy(6, $r), $e['ptkp'] !== '' ? $e['ptkp'] : 'TK/0');
                $ws->setCellValue($this->xy(7, $r),
                    (isset($e['masa_akhir']) ? (int)$e['masa_akhir'] : 12)
                    - (isset($e['masa_awal']) ? (int)$e['masa_awal'] : 1) + 1);
                $ws->setCellValue($this->xy(8, $r), round($e['thp'], 2));
                if ($e['cashbon'] > 0) $ws->setCellValue($this->xy(9, $r), round($e['cashbon'], 2));
                if (!empty($e['insentif'])) $ws->setCellValue($this->xy(10, $r), round($e['insentif'], 2));
                if (!empty($e['subsidi']))  $ws->setCellValue($this->xy(11, $r), round($e['subsidi'], 2));
                if (!empty($e['bonus']))    $ws->setCellValue($this->xy(12, $r), round($e['bonus'], 2));
                if (!empty($e['bpjs'])) {
                    $ws->setCellValue($this->xy(13, $r), $premi['jkk']);
                    $ws->setCellValue($this->xy(14, $r), $premi['jkm']);
                    $ws->setCellValue($this->xy(15, $r), $premi['kes']);
                }
                $ws->setCellValue($this->xy(16, $r), "=+H{$r}+I{$r}+J{$r}+K{$r}+L{$r}+M{$r}+N{$r}+O{$r}");
                $ws->setCellValue($this->xy(17, $r), "=Y{$r}");
                $ws->setCellValue($this->xy(18, $r), "=Q{$r}/100");
                $ws->setCellValue($this->xy(19, $r), "=P{$r}/(1-R{$r})");
                $ws->setCellValue($this->xy(20, $r), "=R{$r}*S{$r}");
                $ws->setCellValue($this->xy(21, $r), "=S{$r}-T{$r}-P{$r}");
                $ws->setCellValue($this->xy(22, $r), '='.$this->ter_formula("S{$r}", $r, 'F')."-Q{$r}");
                $ws->setCellValue($this->xy(23, $r), '='.$this->ter_formula("P{$r}", $r, 'F'));
                $ws->setCellValue($this->xy(24, $r), '='.$this->ter_formula("P{$r}/(1-W{$r}/100)", $r, 'F'));
                $ws->setCellValue($this->xy(25, $r), '='.$this->ter_formula("P{$r}/(1-X{$r}/100)", $r, 'F'));
                $r++;
            }
        }
        $rt = $r;
        $ws->setCellValue($this->xy(2, $rt), 'TOTAL');
        foreach (['H','I','J','K','L','M','N','O','P','S','T'] as $col) {
            $ws->setCellValue($col.$rt, "=SUM({$col}{$r0}:{$col}".($rt - 1).')');
        }
        $ws->getStyle("A{$rt}:Y{$rt}")->getFont()->setBold(true);

        $money = '#,##0.00;(#,##0.00);"-"';
        $ws->getStyle("H{$r0}:P{$rt}")->getNumberFormat()->setFormatCode($money);
        $ws->getStyle("S{$r0}:U{$rt}")->getNumberFormat()->setFormatCode($money);
        $ws->getStyle("A{$hrow}:Y{$rt}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
        foreach (['A'=>5,'B'=>30,'C'=>30,'D'=>12,'E'=>19,'F'=>7,'G'=>6,'H'=>14,'I'=>14,'J'=>13,'K'=>11,'L'=>10,
                  'M'=>10,'N'=>10,'O'=>11,'P'=>16,'Q'=>8,'R'=>9,'S'=>16,'T'=>13,'U'=>8,'V'=>9] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
        foreach (['W', 'X', 'Y'] as $col) $ws->getColumnDimension($col)->setVisible(false);
        $ws->setAutoFilter("A{$hrow}:V".($rt - 1));
        $ws->freezePane('A'.$r0);

        $this->sheet_ter($ss->createSheet());
        $ss->setActiveSheetIndexByName('SEMUA CV');
        return $ss;
    }

    /**
     * Rekap tahunan format konsultan "REKAP DATA SPT MASA PPh Pasal 21 Gross Up":
     * baris = karyawan (urut roster), kolom = JAN..NOP, JUMLAH (=SUM Jan-Nov),
     * DES, TOTAL (=JUMLAH+DES). Nilai = bruto GROSS-UP yang dilapor per masa.
     *
     * @param array $meta ['cv','npwp','year']
     * @param array $rows per karyawan: ['name', 'm' => [bulan => bruto_gu]]
     */
    public function build_gu_rekap($meta, $rows) {
        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $ws = $ss->getActiveSheet();
        $ws->setTitle('PENGHASILAN BRUTO');
        $ws->setCellValue('B1', 'REKAP DATA SPT MASA PPh PASAL 21 GROSS UP — '.$meta['cv'].' — TAHUN '.$meta['year']
            .($meta['npwp'] !== '' ? ' — NPWP '.$meta['npwp'] : ''));
        $ws->setCellValue('B2', 'PENGHASILAN BRUTO');
        $ws->getStyle('B1:B2')->getFont()->setBold(true);

        $hdr = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGUST', 'SEP', 'OKT', 'NOP'];
        $ws->setCellValue('B3', 'Nama');
        foreach ($hdr as $i => $t) $ws->setCellValue($this->xy(3 + $i, 3), $t); // C..M
        $ws->setCellValue('N3', 'JUMLAH');
        $ws->setCellValue('O3', 'DES');
        $ws->setCellValue('P3', 'TOTAL');
        $ws->getStyle('B3:P3')->getFont()->setBold(true);
        $ws->getStyle('B3:M3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6E0B4');
        $ws->getStyle('N3:O3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('9BC2E6');
        $ws->getStyle('P3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6E0B4');

        $r0 = 4;
        $r = $r0;
        foreach ($rows as $i => $e) {
            $ws->setCellValue('A'.$r, $i + 1);
            $ws->setCellValue('B'.$r, $e['name']);
            for ($m = 1; $m <= 11; $m++) {
                if (isset($e['m'][$m])) $ws->setCellValue($this->xy(2 + $m, $r), round($e['m'][$m], 2));
            }
            $ws->setCellValue('N'.$r, "=SUM(C{$r}:M{$r})");
            if (isset($e['m'][12])) $ws->setCellValue('O'.$r, round($e['m'][12], 2));
            $ws->setCellValue('P'.$r, "=N{$r}+O{$r}");
            $r++;
        }
        $rt = $r;
        $ws->setCellValue('B'.$rt, 'TOTAL');
        foreach (range('C', 'P') as $col) {
            $ws->setCellValue($col.$rt, "=SUM({$col}{$r0}:{$col}".($rt - 1).')');
        }
        $ws->getStyle("B{$rt}:P{$rt}")->getFont()->setBold(true);
        $ws->getStyle('C'.$r0.':P'.$rt)->getNumberFormat()->setFormatCode('#,##0;(#,##0);"-"');
        $ws->getStyle('B3:P'.$rt)->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
        $ws->getColumnDimension('A')->setWidth(5);
        $ws->getColumnDimension('B')->setWidth(32);
        foreach (range('C', 'P') as $col) $ws->getColumnDimension($col)->setWidth(13);
        $ws->freezePane('C4');
        return $ss;
    }

    // ---------------------------------------------------------------------- TER
    private function sheet_ter($ws) {
        $ws->setTitle('TER');
        $ws->setCellValue('A1', 'TARIF EFEKTIF RATA-RATA (TER) BULANAN — PP 58/2023 & PMK 168/2023 (Buku DJP hal. 40-43)');
        $ws->getStyle('A1')->getFont()->setBold(true);
        $tables = [
            [1, 'KATEGORI A (TK/0, TK/1, K/0)', self::$TER_A],
            [4, 'KATEGORI B (TK/2, TK/3, K/1, K/2)', self::$TER_B],
            [7, 'KATEGORI C (K/3)', self::$TER_C],
        ];
        foreach ($tables as $t) {
            list($c0, $title, $tbl) = $t;
            $ws->setCellValue($this->xy($c0, 2), $title);
            $ws->setCellValue($this->xy($c0, 3), 'Batas bawah (>)');
            $ws->setCellValue($this->xy($c0 + 1, 3), 'Tarif %');
            $ws->getStyle($this->xy($c0, 2).':'.$this->xy($c0 + 1, 3))->getFont()->setBold(true);
            foreach ($tbl as $i => $row) {
                // kunci VLOOKUP approximate: batas + 0,01 agar "di atas X" eksklusif
                $ws->setCellValue($this->xy($c0, 4 + $i), $row[0] == 0 ? 0 : $row[0] + 0.01);
                $ws->setCellValue($this->xy($c0 + 1, 4 + $i), $row[1]);
            }
            $ws->getColumnDimensionByColumn($c0)->setWidth(18);
        }
    }

    // ------------------------------------------------------------------ RINCIAN
    private function sheet_rincian($ws, $meta, $n) {
        $ws->setTitle('RINCIAN');
        $rt = 10 + $n; // baris TOTAL di PEGAWAI TETAP
        $rows = [
            ['RINCIAN PPh PASAL 21 — '.$meta['cv'], ''],
            ['Masa Pajak', $meta['month'].'/'.$meta['year']],
            ['Cabang', $meta['branch']],
            ['NPWP Pemotong', $meta['npwp'] !== '' ? $meta['npwp'] : 'BELUM DIISI'],
            ['ID TKU', $meta['npwp'] !== '' ? $meta['npwp'].'000000' : '-'],
            ['Jumlah Karyawan', $n],
            ['Total Penghasilan Bruto', "='PEGAWAI TETAP'!N{$rt}"],
            ['Total Bruto Gross Up', "='PEGAWAI TETAP'!Q{$rt}"],
            ['Total PPh 21 Gross Up (beban perusahaan)', "='PEGAWAI TETAP'!R{$rt}"],
            ['', ''],
            ['Catatan: PPh 21 dilapor per karyawan = Bruto x Tarif TER (kolom N x O sheet PEGAWAI TETAP);', ''],
            ['XML Coretax memakai bruto & tarif tsb (pola pelaporan Mei 2026).', ''],
        ];
        foreach ($rows as $i => $row) {
            $ws->setCellValue($this->xy(1, $i + 1), $row[0]);
            if ($row[1] !== '') $ws->setCellValue($this->xy(2, $i + 1), $row[1]);
        }
        $ws->getStyle('A1')->getFont()->setBold(true);
        $ws->getStyle('B7:B9')->getNumberFormat()->setFormatCode('#,##0.00;(#,##0.00);"-"');
        $ws->getColumnDimension('A')->setWidth(46);
        $ws->getColumnDimension('B')->setWidth(24);
    }
}

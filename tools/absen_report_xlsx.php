<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function writeWorkbook(string $path, array $report, array $meta): void
{
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()->setCreator('Codex')->setTitle('Report Absen');
    writeSummary($spreadsheet->getActiveSheet(), $report, $meta);
    writeRanking($spreadsheet->createSheet(), $report['ranking']);
    writeMatrix($spreadsheet->createSheet(), $report['matrix'], makeDays($meta['period_start'], $meta['period_end']));
    writeDetails($spreadsheet->createSheet(), $report['details']);
    writeAnomalies($spreadsheet->createSheet(), $report['anomalies']);
    writeLegend($spreadsheet->createSheet());
    $spreadsheet->setActiveSheetIndex(0);
    (new Xlsx($spreadsheet))->save($path);
}

function writeSummary($sheet, array $report, array $meta): void
{
    $sheet->setTitle('Ringkasan');
    $rows = [
        ['Report Absen Bulanan', ''],
        ['Periode', $meta['period_start'] . ' s/d ' . $meta['period_end']],
        ['As-of', $meta['as_of']],
        ['Cabang', $meta['branch']['branch_name'] ?? 'Semua cabang'],
        ['Total karyawan', count($report['ranking'])],
        ['Karyawan diranking', count(array_filter($report['ranking'], fn($r) => $r['rank_status'] === 'DINILAI'))],
        ['Total anomali/catatan', count($report['anomalies'])],
        ['Formula skor', '100 - absen*5 - tidak_lengkap*3 - terlambat*2 - floor(menit_telat/30)'],
    ];
    writeRows($sheet, $rows, 1);
    $sheet->getStyle('A1:B1')->getFont()->setBold(true)->setSize(14);
    autoSize($sheet, 2);
}

function writeRanking($sheet, array $rows): void
{
    $sheet->setTitle('Ranking');
    writeTable($sheet, ['rank','user_id','id_fingerprint','nama','cabang','divisi','posisi','hari_kerja','hadir','terlambat','menit_telat','izin','cuti','sakit','absen','tidak_lengkap','libur','tanpa_jadwal','future','anomali','score','rank_status','catatan'], $rows);
}

function writeMatrix($sheet, array $rows, array $days): void
{
    $sheet->setTitle('Rekap Harian');
    $headers = ['user_id','id_fingerprint','nama','cabang','divisi','posisi'];
    foreach ($days as $day) {
        $headers[] = date('d', strtotime($day));
    }
    $headers = array_merge($headers, ['hari_kerja','hadir','terlambat','menit_telat','izin','cuti','sakit','absen','tidak_lengkap','libur','tanpa_jadwal','future','score','rank_status']);
    $table = [];
    foreach ($rows as $row) {
        $line = [];
        foreach (['user_id','id_fingerprint','nama','cabang','divisi','posisi'] as $key) {
            $line[$key] = $row[$key];
        }
        foreach ($days as $day) {
            $line[date('d', strtotime($day))] = $row['daily'][$day];
        }
        foreach (['hari_kerja','hadir','terlambat','menit_telat','izin','cuti','sakit','absen','tidak_lengkap','libur','tanpa_jadwal','future','score','rank_status'] as $key) {
            $line[$key] = $row[$key];
        }
        $table[] = $line;
    }
    writeTable($sheet, $headers, $table);
    colorStatusRange($sheet, 2, 7, count($rows), count($days));
}

function writeDetails($sheet, array $rows): void
{
    $sheet->setTitle('Detail Harian');
    writeTable($sheet, ['user_id','id_fingerprint','nama','cabang','divisi','posisi','tanggal','shift','jam_shift','masuk','pulang','telat_menit','status','catatan'], $rows);
}

function writeAnomalies($sheet, array $rows): void
{
    $sheet->setTitle('Catatan Anomali');
    writeTable($sheet, ['user_id','id_fingerprint','nama','cabang','divisi','posisi','tanggal','status','catatan'], $rows);
}

function writeLegend($sheet): void
{
    $sheet->setTitle('Legenda');
    $rows = [
        ['Kode', 'Arti'],
        ['H', 'Hadir lengkap'],
        ['T', 'Terlambat'],
        ['I/C/S', 'Izin/Cuti/Sakit approved'],
        ['A', 'Absen pada hari kerja terjadwal'],
        ['TL', 'Presensi tidak lengkap'],
        ['OFF', 'Libur'],
        ['TJ', 'Tidak ada jadwal harian'],
        ['PTJ', 'Presensi ada tetapi jadwal tidak ada'],
        ['F', 'Tanggal masa depan dari as-of, tidak dihitung skor'],
    ];
    writeRows($sheet, $rows, 1);
    $sheet->getStyle('A1:B1')->getFont()->setBold(true);
    autoSize($sheet, 2);
}

function writeTable($sheet, array $headers, array $rows): void
{
    $sheet->fromArray($headers, null, 'A1');
    $r = 2;
    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $header) {
            $line[] = $row[$header] ?? '';
        }
        $sheet->fromArray($line, null, 'A' . $r++);
    }
    $lastCol = Coordinate::stringFromColumnIndex(count($headers));
    $lastRow = max(1, count($rows) + 1);
    $sheet->setAutoFilter("A1:{$lastCol}{$lastRow}");
    $sheet->freezePane('A2');
    $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
    $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAF7');
    $sheet->getStyle("A1:{$lastCol}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D0D7DE');
    $sheet->getStyle("A1:{$lastCol}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    autoSize($sheet, count($headers));
}

function writeRows($sheet, array $rows, int $start): void
{
    $sheet->fromArray($rows, null, 'A' . $start);
}

function autoSize($sheet, int $cols): void
{
    for ($i = 1; $i <= $cols; $i++) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    }
}

function colorStatusRange($sheet, int $startRow, int $startCol, int $rowCount, int $dayCount): void
{
    $colors = ['H' => 'C6EFCE', 'T' => 'FFEB9C', 'A' => 'FFC7CE', 'TL' => 'F8CBAD', 'OFF' => 'D9EAD3', 'TJ' => 'D9D9D9', 'PTJ' => 'F4CCCC', 'F' => 'E7E6E6', 'I' => 'BDD7EE', 'C' => 'BDD7EE', 'S' => 'DDEBF7'];
    for ($r = $startRow; $r < $startRow + $rowCount; $r++) {
        for ($c = $startCol; $c < $startCol + $dayCount; $c++) {
            $cell = Coordinate::stringFromColumnIndex($c) . $r;
            $value = (string) $sheet->getCell($cell)->getValue();
            if (isset($colors[$value])) {
                $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colors[$value]);
            }
        }
    }
}


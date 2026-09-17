<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Dompdf\Dompdf;
use Dompdf\Options;

class Attendance_daily_report {

    private $CI;

    public function __construct() {
        $this->CI =& get_instance();
    }

    public function build_message($report, $type = 'pagi', $sync_warning = '', $report_time = '') {
        $label = strtoupper($type === 'pagi' ? 'PAGI' : 'SIANG');
        $totals = $report['totals'];
        $lines = [
            str_repeat('━', 27),
            "📊 *REKAP ABSENSI {$label}*",
            '📅 '.$this->indonesian_report_datetime($report['date'], $report_time),
            str_repeat('━', 27),
            '',
            '📋 *RINGKASAN PER CABANG*',
            '',
            '```',
            sprintf('%-12s %5s %5s %5s %5s %5s %4s', 'Cabang', 'Total', 'Hadir', 'Telat', 'Off', 'Belum', '%'),
            sprintf('%-12s %5s %5s %5s %5s %5s %4s', str_repeat('-', 12), '-----', '-----', '-----', '-----', '-----', '----'),
        ];

        foreach ($report['branches'] as $branch) {
            $lines[] = sprintf(
                '%-12s %5d %5d %5d %5d %5d %3d%%',
                strtoupper($branch['branch_name']),
                $branch['total'],
                $branch['hadir'] + $branch['terlambat'],
                $branch['terlambat'],
                $branch['off'],
                $branch['belum'],
                $branch['percent']
            );
        }

        $present = $totals['hadir'] + $totals['terlambat'];
        $percent = $totals['scheduled'] > 0
            ? (int)round(($present / $totals['scheduled']) * 100)
            : 0;
        $lines[] = sprintf('%-12s %5s %5s %5s %5s %5s %4s', str_repeat('-', 12), '-----', '-----', '-----', '-----', '-----', '----');
        $lines[] = sprintf('%-12s %5d %5d %5d %5d %5d %3d%%', 'TOTAL',
            $totals['scheduled'], $present, $totals['terlambat'], $totals['off'], $totals['belum'], $percent);
        $lines[] = '```';

        $lines[] = '';
        $lines[] = '⚠️ *TERLAMBAT:*';
        $late = $this->_employees_by_status($report, 'terlambat');
        if (!$late) {
            $lines[] = 'Tidak ada keterlambatan';
        } else {
            foreach ($late as $employee) {
                $time = $employee['entry_time'] ? date('H:i', strtotime($employee['entry_time'])) : '-';
                $lines[] = sprintf('• %s - %s (%d menit)',
                    strtoupper($employee['name']), $time, $employee['late_minutes']);
            }
        }

        $lines[] = '';
        $lines[] = '❌ *BELUM ABSEN / ALFA:*';
        $absent_by_branch = $this->_group_by_branch($this->_employees_by_status($report, 'alfa'));
        if (!$absent_by_branch) {
            $lines[] = 'Tidak ada';
        } else {
            foreach ($absent_by_branch as $branch_name => $employees) {
                $lines[] = '*'.strtoupper($branch_name).'*';
                foreach ($employees as $employee) {
                    $lines[] = '• '.strtoupper($employee['name']).' ('.strtoupper($employee['position_name']).')';
                }
            }
        }

        if (!empty($report['missing_shift'])) {
            $lines[] = '';
            $lines[] = '⚠️ *WARNING BELUM ADA SHIFT:*';
            foreach ($this->_group_by_branch($report['missing_shift']) as $branch_name => $employees) {
                $lines[] = '*'.strtoupper($branch_name).'*';
                foreach ($employees as $employee) {
                    $lines[] = '• '.strtoupper($employee['name']).' ('.strtoupper($employee['position_name']).')';
                }
            }
            $lines[] = '_Karyawan di atas tidak dihitung sebagai alfa._';
        }

        if ($sync_warning !== '') {
            $lines[] = '';
            $lines[] = '⚠️ *WARNING SINKRONISASI:*';
            $lines[] = $sync_warning;
        }

        return implode("\n", $lines);
    }

    public function build_shift_warning_message($report, $type = 'pagi', $report_time = '') {
        $label = strtoupper($type === 'pagi' ? 'PAGI' : 'SIANG');
        $lines = [
            "⚠️ *WARNING REKAP ABSENSI {$label}*",
            '📅 '.$this->indonesian_report_datetime($report['date'], $report_time),
            '',
            '*BELUM ADA SHIFT HARI INI*',
            'Laporan alfa belum dibuat agar karyawan tanpa jadwal tidak salah dinilai tidak hadir.',
        ];
        foreach ($this->_group_by_branch($report['missing_shift']) as $branch => $employees) {
            $lines[] = '';
            $lines[] = '*'.strtoupper($branch).'*: '.count($employees).' orang';
        }
        return implode("\n", $lines);
    }

    public function render_pdf($report, $type = 'pagi', $report_time = '') {
        require_once APPPATH.'libraries/dompdf/autoload.inc.php';
        $html = $this->CI->load->view('wa/attendance_report_pdf', [
            'report' => $report,
            'label' => strtoupper($type === 'pagi' ? 'PAGI' : 'SIANG'),
            'report_date' => $this->indonesian_report_datetime($report['date'], $report_time, false),
            'report_date_only' => $this->indonesian_report_date($report['date']),
            'printed_datetime' => $this->indonesian_datetime($report['generated_at']),
        ], true);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $canvas->page_text(260, 815, 'Halaman {PAGE_NUM} / {PAGE_COUNT}', null, 7, [0.35, 0.42, 0.52]);

        $directory = FCPATH.'exports'.DIRECTORY_SEPARATOR.'wa';
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Folder laporan PDF tidak dapat dibuat.');
        }

        // CLI dapat berjalan sebagai root. Samakan pemilik hasilnya dengan aplikasi
        // agar proses web tetap bisa memperbarui laporan pada tanggal yang sama.
        $app_owner = @fileowner(FCPATH);
        $app_group = @filegroup(FCPATH);
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            if ($app_owner !== false) @chown($directory, $app_owner);
            if ($app_group !== false) @chgrp($directory, $app_group);
        }
        @chmod($directory, 0770);
        clearstatcache(true, $directory);
        if (!is_writable($directory)) {
            throw new RuntimeException('Folder laporan PDF tidak dapat ditulis.');
        }

        $filename = 'rekap_absensi_'.strtolower($type).'_'.$report['date'].'.pdf';
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        $temporary_path = tempnam($directory, '.rekap_');
        if ($temporary_path === false) {
            throw new RuntimeException('File sementara laporan PDF tidak dapat dibuat.');
        }
        try {
            if (file_put_contents($temporary_path, $dompdf->output(), LOCK_EX) === false) {
                throw new RuntimeException('File laporan PDF tidak dapat disimpan.');
            }
            @chmod($temporary_path, 0660);
            if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
                if ($app_owner !== false) @chown($temporary_path, $app_owner);
                if ($app_group !== false) @chgrp($temporary_path, $app_group);
            }
            if (!@rename($temporary_path, $path)) {
                throw new RuntimeException('File laporan PDF tidak dapat dipublikasikan.');
            }
        } finally {
            if (is_file($temporary_path)) @unlink($temporary_path);
        }
        return ['path' => $path, 'filename' => $filename];
    }

    public function indonesian_datetime($value) {
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $timestamp = strtotime($value);
        return $days[(int)date('w', $timestamp)].', '.date('j', $timestamp).' '
            .$months[(int)date('n', $timestamp)].' '.date('Y', $timestamp)
            .' | ⏰ '.date('H:i', $timestamp).' WIB';
    }

    public function indonesian_report_date($value) {
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $timestamp = strtotime($value.' 12:00:00');
        return $days[(int)date('w', $timestamp)].', '.date('j', $timestamp).' '
            .$months[(int)date('n', $timestamp)].' '.date('Y', $timestamp);
    }

    public function indonesian_report_datetime($date, $time = '', $with_icon = true) {
        $time = preg_match('/^\d{2}:\d{2}$/', (string)$time) ? $time : date('H:i');
        return $this->indonesian_report_date($date).' | '.($with_icon ? '⏰ ' : '').$time.' WIB';
    }

    private function _employees_by_status($report, $status) {
        $employees = [];
        foreach ($report['branches'] as $branch) {
            foreach ($branch['positions'] as $position) {
                foreach ($position['details'] as $employee) {
                    if ($employee['status'] === $status) $employees[] = $employee;
                }
            }
        }
        return $employees;
    }

    private function _group_by_branch($employees) {
        $groups = [];
        foreach ($employees as $employee) {
            $groups[$employee['branch_name']][] = $employee;
        }
        return $groups;
    }
}

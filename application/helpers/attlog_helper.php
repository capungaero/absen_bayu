<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Helper untuk modul absensi (parsing log mesin + payload presence).
 *
 * Fungsi-fungsi di sini bersifat pure (kecuali attlog_presence_storage_dir
 * yang melakukan side effect mkdir). Sebelumnya logika ini diduplikasi
 * antara application/controllers/Presence.php, hr/Presence.php, dan
 * sebagian di Wa.php, Sync.php, Attendance.php.
 */

if ( ! function_exists('attlog_sanitize_machine_sn'))
{
    /** Whitelist huruf, angka, underscore, dash. Lainnya jadi string kosong. */
    function attlog_sanitize_machine_sn($sn)
    {
        $sn = trim((string) $sn);
        return preg_match('/^[A-Za-z0-9_-]+$/', $sn) ? $sn : '';
    }
}

if ( ! function_exists('attlog_time_between'))
{
    /**
     * Cek apakah $time berada dalam window [$start, $end].
     * Mendukung window yang melewati tengah malam (start > end).
     */
    function attlog_time_between($time, $start, $end)
    {
        if ($start === null || $end === null || $start === '' || $end === '') {
            return false;
        }

        if ($start <= $end) {
            return $time >= $start && $time <= $end;
        }

        return $time >= $start || $time <= $end;
    }
}

if ( ! function_exists('attlog_apply_fallback'))
{
    /**
     * Set entry_time (tap pertama) dan out_time (tap terakhir) ketika
     * shift tidak tersedia. Memodifikasi $payload secara langsung.
     */
    function attlog_apply_fallback(array &$payload, $date, array $times)
    {
        sort($times);
        $payload['entry_time'] = $date.' '.$times[0];
        if (count($times) > 1) {
            $payload['out_time'] = $date.' '.$times[count($times) - 1];
        }
    }
}

if ( ! function_exists('attlog_payload'))
{
    /** Template row presence untuk satu user-tanggal sebelum diisi. */
    function attlog_payload()
    {
        return [
            'user_id'         => null,
            'entry_time'      => null,
            'out_time'        => null,
            'entry_time_late' => 0,
            'rest_time_in'    => null,
            'rest_time_out'   => null,
            'rest_time_late'  => 0,
            'flow_date'       => '',
            'input_by'        => 'system',
            'presence_status' => 'approved',
            'is_overtime'     => '0',
        ];
    }
}

if ( ! function_exists('attlog_payload_pray'))
{
    /** Template row presence sholat untuk satu user-tanggal sebelum diisi. */
    function attlog_payload_pray()
    {
        return [
            'flag'              => false,
            'subuh_time_in'     => null,
            'subuh_time_out'    => null,
            'subuh_time_late'   => 0,
            'dzuhur_time_in'    => null,
            'dzuhur_time_out'   => null,
            'dzuhur_time_late'  => 0,
            'ashar_time_in'     => null,
            'ashar_time_out'    => null,
            'ashar_time_late'   => 0,
            'maghrib_time_in'   => null,
            'maghrib_time_out'  => null,
            'maghrib_time_late' => 0,
            'isha_time_in'      => null,
            'isha_time_out'     => null,
            'isha_time_late'    => 0,
            'friday_time_in'    => null,
            'friday_time_out'   => null,
            'friday_time_late'  => 0,
        ];
    }
}

if ( ! function_exists('attlog_presence_period_range'))
{
    /**
     * Range tanggal payroll untuk bulan/tahun, dari START_PAYROLL_DATE bulan
     * sebelumnya sampai END_PAYROLL_DATE bulan ini. Konstanta didefinisikan
     * di application/config/constants.php.
     */
    function attlog_presence_period_range($month, $year)
    {
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        $raw = strtotime($year.'-'.$month.'-10 -1 months');
        return [
            'from' => date('Y-m-'.START_PAYROLL_DATE, $raw),
            'to'   => $year.'-'.$month.'-'.END_PAYROLL_DATE,
        ];
    }
}

if ( ! function_exists('attlog_presence_storage_dir'))
{
    /**
     * Path folder penyimpanan .dat per bulan/tahun. Folder dibuat kalau
     * belum ada. Path absolut, root di FCPATH/uploads/attendance.
     */
    function attlog_presence_storage_dir($month, $year)
    {
        $dir = FCPATH.'uploads'.DIRECTORY_SEPARATOR.'attendance'
             .DIRECTORY_SEPARATOR.$year
             .DIRECTORY_SEPARATOR.str_pad($month, 2, '0', STR_PAD_LEFT);
        if ( ! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }
}

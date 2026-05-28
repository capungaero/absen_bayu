<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if ( ! function_exists('late_minutes'))
{
    /**
     * Selisih menit antara $time dan $limit, dibulatkan ke menit penuh.
     * Detik selalu dipotong (07:50:07 vs 07:50:00 => 0 menit).
     * Return 0 kalau salah satu null/kosong, atau kalau time <= limit.
     */
    function late_minutes($limit, $time)
    {
        if ($limit === null || $limit === '' || $time === null || $time === '') {
            return 0;
        }

        $limit = date('H:i', strtotime($limit));
        $time  = date('H:i', strtotime($time));

        $limit_minutes = ((int) substr($limit, 0, 2) * 60) + (int) substr($limit, 3, 2);
        $time_minutes  = ((int) substr($time, 0, 2) * 60)  + (int) substr($time, 3, 2);

        return max(0, $time_minutes - $limit_minutes);
    }
}

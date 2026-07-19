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

if ( ! function_exists('log_late_flip'))
{
    /**
     * Pelacak SEMENTARA (Jul 2026) untuk investigasi entry_time_late/rest_time_late
     * yang bolak-balik nilainya tanpa jam masuk/istirahat berubah (lihat presence.id
     * 368655, user_id 273). log_message CI tidak dipakai karena log_threshold=0 di
     * production; tulis file terpisah biar tidak tercampur & gampang dicabut.
     * Hapus pemanggilan ini + file log setelah sumbernya ketemu.
     */
    function log_late_flip($site, $presence_id, $old_entry, $new_entry, $old_rest, $new_rest)
    {
        if ((int) $old_entry === (int) $new_entry && (int) $old_rest === (int) $new_rest) {
            return;
        }

        $context = 'guest';
        if (is_cli()) {
            $context = 'CLI';
        } elseif (function_exists('get_instance')) {
            $ci =& get_instance();
            if (isset($ci->userdata) && isset($ci->userdata->id)) {
                $context = 'user#'.$ci->userdata->id;
            }
        }

        $line = sprintf(
            "[%s] site=%s presence_id=%s entry_late %s->%s rest_late %s->%s context=%s uri=%s\n",
            date('Y-m-d H:i:s'),
            $site,
            $presence_id,
            var_export($old_entry, true), var_export($new_entry, true),
            var_export($old_rest, true), var_export($new_rest, true),
            $context,
            isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : (is_cli() ? 'cli:sync_cron' : '')
        );

        @file_put_contents(APPPATH.'logs/late_flip_debug.log', $line, FILE_APPEND | LOCK_EX);
    }
}

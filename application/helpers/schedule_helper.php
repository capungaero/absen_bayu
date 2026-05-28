<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if ( ! function_exists('latest_schedule_subquery'))
{
    /**
     * Klausa WHERE untuk membatasi row users_shift_additional hanya pada
     * jadwal terbaru (MAX(id)) per (user_id, additional_date).
     *
     * Pemakaian:
     *   $this->db->where(latest_schedule_subquery(), null, false)
     *
     * Gunakan ini di mana pun query mengambil baris jadwal kerja final —
     * jika user_id punya beberapa baris jadwal di tanggal yang sama,
     * baris dengan id terbesar dianggap final.
     */
    function latest_schedule_subquery()
    {
        return 'users_shift_additional.id = (
            SELECT MAX(usa.id)
            FROM users_shift_additional usa
            WHERE usa.user_id = users_shift_additional.user_id
            AND usa.additional_date = users_shift_additional.additional_date
        )';
    }
}

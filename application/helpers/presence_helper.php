<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if ( ! function_exists('presence_merge_preserve_existing'))
{
    /**
     * Hitung field-field yang perlu di-UPDATE pada baris presence existing
     * supaya gap dari hasil import baru terisi tanpa menimpa data yang sudah ada.
     *
     * Aturan:
     *  - Untuk entry_time, out_time, rest_time_in, rest_time_out:
     *      kalau existing kosong DAN new ada nilai, set ke new.
     *  - Untuk entry_time_late, rest_time_late:
     *      kalau existing kosong/0 DAN new ada nilai > 0, set ke new.
     *
     * Field lain (presence_status, presence_type, dll.) tidak diubah supaya
     * input manual tetap menang.
     *
     * Pure function: tidak menyentuh DB, tidak modify $existing/$new.
     *
     * @param  array $new       Payload hasil classify_taps untuk (user_id, flow_date)
     * @param  array $existing  Baris presence yang sudah ada di DB
     * @return array Delta field => value. Kosong artinya tidak perlu UPDATE.
     */
    function presence_merge_preserve_existing(array $new, array $existing)
    {
        $update = [];

        foreach (['entry_time', 'out_time', 'rest_time_in', 'rest_time_out'] as $field) {
            if (empty($existing[$field]) && !empty($new[$field])) {
                $update[$field] = $new[$field];
            }
        }

        foreach (['entry_time_late', 'rest_time_late'] as $field) {
            $existing_val = isset($existing[$field]) ? $existing[$field] : null;
            $is_existing_empty = empty($existing_val) || (int) $existing_val === 0;
            $is_new_nonzero    = !empty($new[$field]) && (int) $new[$field] !== 0;
            if ($is_existing_empty && $is_new_nonzero) {
                $update[$field] = $new[$field];
            }
        }

        return $update;
    }
}

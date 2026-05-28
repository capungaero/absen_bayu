<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if ( ! function_exists('presence_net_work_minutes'))
{
    /**
     * Hitung jam kerja efektif (net) dalam menit.
     * Net = (out_time - entry_time) - durasi istirahat.
     *
     * Aturan:
     *   - Kalau entry_time atau out_time kosong/null -> return 0.
     *   - Kalau out_time <= entry_time -> return 0 (tidak ada kerja).
     *   - Kalau rest_in DAN rest_out terisi DAN rest_out > rest_in,
     *     kurangi (rest_out - rest_in) dari gross.
     *   - Kalau hanya salah satu dari rest_in / rest_out terisi, abaikan
     *     (anggap istirahat tidak terhitung, tidak penalize).
     *
     * Input: time strings (boleh HH:MM atau HH:MM:SS atau datetime full).
     * Return: int menit, minimal 0.
     */
    function presence_net_work_minutes($entry_time, $out_time, $rest_time_in = null, $rest_time_out = null)
    {
        if (empty($entry_time) || empty($out_time)) {
            return 0;
        }

        $entry_ts = strtotime($entry_time);
        $out_ts   = strtotime($out_time);
        if (!$entry_ts || !$out_ts || $out_ts <= $entry_ts) {
            return 0;
        }

        $gross_minutes = (int) floor(($out_ts - $entry_ts) / 60);

        if (!empty($rest_time_in) && !empty($rest_time_out)) {
            $rest_in_ts  = strtotime($rest_time_in);
            $rest_out_ts = strtotime($rest_time_out);
            if ($rest_in_ts && $rest_out_ts && $rest_out_ts > $rest_in_ts) {
                $rest_minutes = (int) floor(($rest_out_ts - $rest_in_ts) / 60);
                $gross_minutes -= $rest_minutes;
            }
        }

        return max(0, $gross_minutes);
    }
}

if ( ! function_exists('presence_expected_net_minutes'))
{
    /**
     * Hitung durasi kerja "expected" (net) sesuai shift dalam menit.
     * Expected = (shift.end_time - shift.start_time) - shift.rest_time_range.
     *
     * Dipakai sebagai patokan untuk hitung kekurangan jam pada early leave.
     * Kalau ada field yang null/invalid -> return 0 (skip perhitungan).
     */
    function presence_expected_net_minutes($shift)
    {
        if (empty($shift['start_time']) || empty($shift['end_time'])) {
            return 0;
        }

        $start_ts = strtotime('1970-01-01 '.$shift['start_time']);
        $end_ts   = strtotime('1970-01-01 '.$shift['end_time']);
        if (!$start_ts || !$end_ts || $end_ts <= $start_ts) {
            return 0;
        }

        $gross = (int) floor(($end_ts - $start_ts) / 60);
        $rest  = isset($shift['rest_time_range']) ? (int) $shift['rest_time_range'] : 0;

        return max(0, $gross - $rest);
    }
}

if ( ! function_exists('presence_early_leave_short_minutes'))
{
    /**
     * Hitung kekurangan menit (jam kerja yang kurang) untuk early leave.
     *
     * short = expected_net_minutes(shift) - actual_net_minutes
     *       (dibatasi minimal 0)
     *
     * Note: nilai 0 berarti tidak ada kekurangan (out_time persis di
     * shift.end_time atau bahkan setelahnya). Caller boleh menyimpan
     * is_early_leave=1 dengan short=0 (mis. user centang tapi ternyata
     * pulang tidak lebih cepat), tetapi perhitungan potongan akan 0.
     */
    function presence_early_leave_short_minutes($shift, $entry_time, $out_time, $rest_time_in = null, $rest_time_out = null)
    {
        $expected = presence_expected_net_minutes($shift);
        $actual   = presence_net_work_minutes($entry_time, $out_time, $rest_time_in, $rest_time_out);
        return max(0, $expected - $actual);
    }
}

if ( ! function_exists('presence_hourly_rate'))
{
    /**
     * Gaji per jam = GP / total_hari_kerja / 10.
     *
     * total_hari_kerja sesuai spec: cal_days_in_month (30/31).
     * Pembagi 10 = asumsi jam kerja per hari standar.
     *
     * Return float rupiah. Caller boleh round/floor sebelum simpan.
     */
    function presence_hourly_rate($salary, $days_in_month)
    {
        $salary = (int) $salary;
        $days   = (int) $days_in_month;
        if ($salary <= 0 || $days <= 0) {
            return 0.0;
        }
        return $salary / $days / 10.0;
    }
}

if ( ! function_exists('presence_early_leave_deduction_amount'))
{
    /**
     * Hitung total potongan rupiah dari kumulatif kekurangan menit dalam
     * satu periode payroll.
     *
     * amount = hourly_rate(salary, days) * (total_short_minutes / 60)
     *
     * Return int (dibulatkan ke bawah).
     */
    function presence_early_leave_deduction_amount($salary, $days_in_month, $total_short_minutes)
    {
        $hourly = presence_hourly_rate($salary, $days_in_month);
        $hours  = max(0, (int) $total_short_minutes) / 60.0;
        return (int) floor($hourly * $hours);
    }
}

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

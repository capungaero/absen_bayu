<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Kirimi_wa Library
 * 
 * Library untuk integrasi dengan kirimi.id WhatsApp gateway
 * Endpoint: POST https://api.kirimi.id/v1/send-message
 * Auth: user_code + secret + device_id (body params)
 */
class Kirimi_wa {

    private $CI;
    private $user_code;
    private $secret;
    private $device_id;
    private $timeout = 30;
    private $endpoint = 'https://api.kirimi.id/v1/send-message';

    public function __construct($config = []) {
        $this->CI =& get_instance();

        if (!empty($config)) {
            $this->user_code = isset($config['user_code']) ? $config['user_code'] : '';
            $this->secret    = isset($config['secret']) ? $config['secret'] : '';
            $this->device_id = isset($config['device_id']) ? $config['device_id'] : '';
        }
    }

    /**
     * Inisialisasi dengan config dari database
     */
    public function init_from_db() {
        $this->CI->load->model('wa_model', 'wa');
        $config = $this->CI->wa->get_config();

        if ($config) {
            $this->user_code = $config['user_code'];
            $this->secret    = $config['secret'];
            $this->device_id = $config['device_id'];
        }

        return $this;
    }

    /**
     * Kirim pesan WA ke satu nomor
     * 
     * @param string $phone  Nomor HP format 628xxx (tanpa +)
     * @param string $message Isi pesan
     * @return array ['success' => bool, 'http_code' => int, 'response' => string]
     */
    public function send($phone, $message) {
        if (empty($this->user_code) || empty($this->secret)) {
            return [
                'success'   => false,
                'http_code' => 0,
                'response'  => 'user_code atau secret belum dikonfigurasi.'
            ];
        }

        $phone = $this->normalize_phone($phone);

        $body = [
            'user_code' => $this->user_code,
            'secret'    => $this->secret,
            'phone'     => $phone,
            'message'   => $message,
        ];

        if (!empty($this->device_id)) {
            $body['device_id'] = $this->device_id;
        }

        $payload = json_encode($body);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        if ($curl_err) {
            return [
                'success'   => false,
                'http_code' => $http_code,
                'response'  => 'cURL error: ' . $curl_err
            ];
        }

        $success = ($http_code >= 200 && $http_code < 300);

        return [
            'success'   => $success,
            'http_code' => $http_code,
            'response'  => $response
        ];
    }

    /**
     * Kirim pesan ke banyak nomor
     *
     * @param array  $phones  Array nomor HP
     * @param string $message Isi pesan
     * @return array Hasil per nomor
     */
    public function send_bulk($phones, $message) {
        $results = [];
        foreach ($phones as $phone) {
            $phone = trim($phone);
            if (empty($phone)) continue;
            $results[$phone] = $this->send($phone, $message);
        }
        return $results;
    }

    /**
     * Normalisasi nomor HP ke format 628xxx
     */
    public function normalize_phone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (substr($phone, 0, 2) === '08') {
            $phone = '62' . substr($phone, 1);
        } elseif (substr($phone, 0, 1) === '8') {
            $phone = '62' . $phone;
        } elseif (substr($phone, 0, 3) === '+62') {
            $phone = substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Buat teks rekap absen harian
     */
    public function build_rekap_message($summary_rows, $type = 'pagi') {
        $tipe_label = ($type === 'pagi') ? 'Pagi' : 'Siang';
        $today = date('d/m/Y');
        $time  = date('H:i');

        $msg  = "📋 *REKAP ABSEN {$tipe_label}*\n";
        $msg .= "📅 {$today} | ⏰ {$time}\n";
        $msg .= str_repeat("─", 30) . "\n";

        foreach ($summary_rows as $row) {
            $terlambat   = isset($row['terlambat']) ? (int)$row['terlambat'] : 0;
            $hadir_total = (int)$row['hadir'] + $terlambat;
            $msg .= "\n🏢 *{$row['branch_name']}*\n";
            $msg .= "👥 Total Karyawan : {$row['total_employee']}\n";
            $msg .= "✅ Hadir          : {$hadir_total}";
            if ($terlambat > 0) {
                $msg .= " _(terlambat: {$terlambat})_";
            }
            $msg .= "\n";
            $msg .= "⏰ Terlambat      : {$terlambat}\n";
            $msg .= "🤒 Sakit          : {$row['sakit']}\n";
            $msg .= "📝 Izin/Cuti      : {$row['izin']}\n";
            $msg .= "❌ Tidak Hadir    : {$row['tidak_hadir']}\n";
        }

        $msg .= "\n" . str_repeat("─", 30);
        $msg .= "\n_Pesan otomatis dari Sistem Absensi_";

        return $msg;
    }

    /**
     * Buat teks notifikasi karyawan tidak hadir
     */
    public function build_absent_message($absent_employees) {
        $today = date('d/m/Y');
        $time  = date('H:i');
        $count = count($absent_employees);

        $msg  = "⚠️ *NOTIFIKASI TIDAK HADIR*\n";
        $msg .= "📅 {$today} | ⏰ {$time}\n";
        $msg .= "Total: {$count} karyawan\n";
        $msg .= str_repeat("─", 30) . "\n";

        $branch_group = [];
        foreach ($absent_employees as $emp) {
            $branch_group[$emp['branch_name']][] = $emp;
        }

        foreach ($branch_group as $branch_name => $employees) {
            $msg .= "\n🏢 *{$branch_name}*\n";
            foreach ($employees as $i => $emp) {
                $no = $i + 1;
                $msg .= "{$no}. {$emp['first_name']} ({$emp['position_name']})\n";
            }
        }

        $msg .= "\n" . str_repeat("─", 30);
        $msg .= "\n_Pesan otomatis dari Sistem Absensi_";

        return $msg;
    }

    public function build_shift_rekap_message($summary_rows, $type = 'pagi') {
        $tipe_label = ($type === 'pagi') ? 'Pagi' : 'Siang';
        $today = date('d/m/Y');
        $time  = date('H:i');

        $msg  = "📋 *REKAP ABSEN {$tipe_label} PER SHIFT*\n";
        $msg .= "📅 {$today} | ⏰ {$time}\n";
        $msg .= str_repeat("─", 30) . "\n";

        if (empty($summary_rows)) {
            $msg .= "\nBelum ada jadwal shift aktif untuk hari ini.\n";
        }

        foreach ($summary_rows as $row) {
            $terlambat   = isset($row['terlambat']) ? (int)$row['terlambat'] : 0;
            $hadir       = isset($row['hadir']) ? (int)$row['hadir'] : 0;
            $hadir_total = $hadir + $terlambat;
            $shift_name  = isset($row['shift_name']) ? $row['shift_name'] : '';
            $shift_code  = isset($row['shift_code']) ? $row['shift_code'] : '';
            $branch_name = isset($row['branch_name']) ? $row['branch_name'] : '';
            $start_time  = !empty($row['start_time']) ? date('H:i', strtotime($row['start_time'])) : '-';
            $late_limit  = !empty($row['start_time_late']) ? date('H:i', strtotime($row['start_time_late'])) : '-';

            $msg .= "\n*{$shift_code} - {$shift_name}*\n";
            $msg .= "🏢 Cabang      : {$branch_name}\n";
            $msg .= "⏰ Jam shift   : {$start_time} | Batas telat: {$late_limit}\n";
            $msg .= "👥 Total tugas : {$row['total_employee']}\n";
            $msg .= "✅ Hadir       : {$hadir_total}\n";
            $msg .= "⏰ Terlambat   : {$terlambat}\n";
            $msg .= "🤒 Sakit       : {$row['sakit']}\n";
            $msg .= "📝 Izin/Cuti   : {$row['izin']}\n";
            $msg .= "❌ Tidak hadir : {$row['tidak_hadir']}\n";

            if (!empty($row['late_employees'])) {
                $msg .= "⏰ Detail terlambat:\n";
                foreach ($row['late_employees'] as $employee) {
                    $entry_time = !empty($employee['entry_time']) ? date('H:i', strtotime($employee['entry_time'])) : '-';
                    $late_minutes = isset($employee['late_minutes']) ? (int)$employee['late_minutes'] : 0;
                    $msg .= "- {$employee['name']} ({$entry_time}, {$late_minutes} menit)\n";
                }
            }

            if (!empty($row['absent_employees'])) {
                $msg .= "❌ Detail tidak hadir:\n";
                foreach ($row['absent_employees'] as $employee) {
                    $msg .= "- {$employee['name']} ({$employee['position_name']})\n";
                }
            }
        }

        $msg .= "\n" . str_repeat("─", 30);
        $msg .= "\n_Pesan otomatis dari Sistem Absensi_";

        return $msg;
    }

    public function build_shift_absent_message($shift_report) {
        $today = date('d/m/Y');
        $time  = date('H:i');
        $count = 0;

        foreach ($shift_report as $row) {
            $count += isset($row['absent_employees']) ? count($row['absent_employees']) : 0;
        }

        $msg  = "⚠️ *NOTIFIKASI TIDAK HADIR PER SHIFT*\n";
        $msg .= "📅 {$today} | ⏰ {$time}\n";
        $msg .= "Total: {$count} karyawan\n";
        $msg .= str_repeat("─", 30) . "\n";

        if ($count === 0) {
            $msg .= "\nSemua karyawan yang bertugas pada shift hari ini sudah memiliki presensi.\n";
        }

        foreach ($shift_report as $row) {
            if (empty($row['absent_employees'])) {
                continue;
            }

            $shift_name = isset($row['shift_name']) ? $row['shift_name'] : '';
            $shift_code = isset($row['shift_code']) ? $row['shift_code'] : '';
            $branch_name = isset($row['branch_name']) ? $row['branch_name'] : '';

            $msg .= "\n*{$shift_code} - {$shift_name}*\n";
            $msg .= "🏢 Cabang: {$branch_name}\n";
            foreach ($row['absent_employees'] as $i => $employee) {
                $no = $i + 1;
                $msg .= "{$no}. {$employee['name']} ({$employee['position_name']})\n";
            }
        }

        $msg .= "\n" . str_repeat("─", 30);
        $msg .= "\n_Pesan otomatis dari Sistem Absensi_";

        return $msg;
    }
}

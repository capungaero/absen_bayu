<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Lacak_attendance_client {

    private $CI;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->config->load('lacak', true);
    }

    public function fetch($date) {
        $config = (array)$this->CI->config->item('lacak_attendance', 'lacak');
        if (empty($config['url']) || empty($config['username']) || empty($config['password'])) {
            return ['success' => false, 'message' => 'Konfigurasi sumber Lacak belum lengkap.', 'attendance' => []];
        }

        $url = $config['url'].'?'.http_build_query(['date' => $date]);
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $config['username'].':'.$config['password'],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => isset($config['timeout']) ? (int)$config['timeout'] : 15,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($curl);
        $http_code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false || $http_code < 200 || $http_code >= 300) {
            log_message('error', 'Lacak attendance fetch gagal. HTTP '.$http_code.' '.$error);
            return ['success' => false, 'message' => 'Data absensi Lacak tidak dapat dibaca.', 'attendance' => []];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['recap']) || !is_array($decoded['recap'])) {
            log_message('error', 'Lacak attendance response tidak valid.');
            return ['success' => false, 'message' => 'Format data absensi Lacak tidak valid.', 'attendance' => []];
        }

        $attendance = [];
        foreach ($decoded['recap'] as $item) {
            $code = trim((string)($item['username'] ?? ''));
            $absen = isset($item['absen']) && is_array($item['absen']) ? $item['absen'] : [];
            $created_at = trim((string)($absen['createdAt'] ?? ''));
            if ($code === '' || $created_at === '' || date('Y-m-d', strtotime($created_at)) !== $date) continue;
            if (!isset($attendance[$code]) || strtotime($created_at) < strtotime($attendance[$code])) {
                $attendance[$code] = $created_at;
            }
        }

        return ['success' => true, 'message' => 'Data Lacak berhasil dibaca.', 'attendance' => $attendance];
    }
}

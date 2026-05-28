<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Cloud_attlog_client
 *
 * Klien untuk Solution Cloud (mesin fingerprint). Login + download file .dat
 * berisi log absensi. Mendukung single download dan batch via curl_multi.
 *
 * Base URL bisa diset lewat config 'solutioncloud_base_url'. Default
 * http://solutioncloud.co.id/ — vendor ini belum punya HTTPS.
 *
 * Pemakaian:
 *   $this->load->library('cloud_attlog_client');
 *   $raw = $this->cloud_attlog_client->download_single($sn, $password);
 *   $batch = $this->cloud_attlog_client->download_batch([
 *       ['sn' => 'X', 'pass' => 'y'],
 *       ['sn' => 'A', 'pass' => 'b'],
 *   ]);
 *
 * Return single: string raw response, atau false kalau gagal.
 * Return batch: false kalau semua gagal, atau array {machines, downloaded, failed}.
 */
class Cloud_attlog_client
{
    private $base_url;

    public function __construct($params = [])
    {
        $CI =& get_instance();
        $configured = $CI->config->item('solutioncloud_base_url');
        $base = is_string($configured) && trim($configured) !== ''
            ? trim($configured)
            : 'http://solutioncloud.co.id/';
        $this->base_url = rtrim($base, '/').'/';

        if (isset($params['base_url']) && is_string($params['base_url']) && trim($params['base_url']) !== '') {
            $this->base_url = rtrim(trim($params['base_url']), '/').'/';
        }
    }

    public function base_url()
    {
        return $this->base_url;
    }

    /**
     * Validasi response dari Solution Cloud. Response valid harus berupa
     * teks (bukan halaman HTML login) dan berisi karakter tab (separator
     * kolom dalam .dat).
     */
    public function is_valid_response($data)
    {
        if (!is_string($data) || trim($data) === '') { return false; }
        if (stripos($data, '<html') !== false || stripos($data, '<!doctype') !== false) { return false; }
        if (strpos($data, "\t") === false) { return false; }
        return true;
    }

    /**
     * Login lalu download .dat untuk satu mesin. Return raw string atau false.
     */
    public function download_single($sn, $password)
    {
        $cookie = tempnam(sys_get_temp_dir(), 'solutioncloud_');

        $login = $this->_request($this->base_url.'sc_pro.asp', [
            'sn'   => $sn,
            'pass' => $password,
        ], $cookie);

        if (!$login['ok']) {
            @unlink($cookie);
            return false;
        }

        $data = $this->_request($this->base_url.'download.asp', null, $cookie);
        @unlink($cookie);

        if (!$data['ok'] || !$this->is_valid_response($data['body'])) {
            return false;
        }

        return $data['body'];
    }

    /**
     * Download paralel via curl_multi untuk banyak mesin.
     * $machines: array of ['sn' => ..., 'pass' => ...].
     * Return false kalau semua gagal, atau:
     *   ['machines' => [['sn', 'raw'], ...], 'downloaded' => [sn...], 'failed' => [sn...]]
     */
    public function download_batch(array $machines)
    {
        if (empty($machines)) { return false; }

        $states = [];
        foreach ($machines as $index => $machine) {
            $cookie = tempnam(sys_get_temp_dir(), 'solutioncloud_');
            $states[$index] = [
                'sn'        => $machine['sn'],
                'pass'      => $machine['pass'],
                'cookie'    => $cookie,
                'login_ok'  => false,
                'last_error'=> '',
            ];
        }

        foreach ($states as $index => $state) {
            $login = $this->_request($this->base_url.'sc_pro.asp', [
                'sn'   => $state['sn'],
                'pass' => $state['pass'],
            ], $state['cookie']);
            $states[$index]['login_ok'] = $login['ok'];
            if (!$login['ok']) {
                $states[$index]['last_error'] = $login['error'];
            }
        }

        $download_handles = [];
        foreach ($states as $index => $state) {
            if (empty($state['login_ok'])) { continue; }
            $download_handles[$index] = $this->_request_handle($this->base_url.'download.asp', null, $state['cookie']);
        }

        $download_results = empty($download_handles) ? [] : $this->_multi_exec($download_handles);

        $machines_data = [];
        $downloaded = [];
        $failed = [];

        foreach ($states as $index => $state) {
            $data = isset($download_results[$index]) ? $download_results[$index] : false;

            if ((!$this->is_valid_response($data)) && !empty($state['login_ok'])) {
                $retry = $this->_request($this->base_url.'download.asp', null, $state['cookie']);
                $data = $retry['ok'] ? $retry['body'] : false;
            }

            @unlink($state['cookie']);

            if (!$this->is_valid_response($data)) {
                $failed[] = $state['sn'];
                continue;
            }

            $downloaded[] = $state['sn'];
            $machines_data[] = [
                'sn'  => $state['sn'],
                'raw' => $data,
            ];
        }

        if (empty($machines_data)) { return false; }

        return [
            'machines'   => $machines_data,
            'downloaded' => $downloaded,
            'failed'     => $failed,
        ];
    }

    private function _request_handle($url, $post = null, $cookie = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

        if ($cookie) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
        }

        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }

        return $ch;
    }

    private function _multi_exec($handles)
    {
        $multi = curl_multi_init();
        foreach ($handles as $handle) {
            curl_multi_add_handle($multi, $handle);
        }

        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 1);
            }
        } while ($running && $status == CURLM_OK);

        $results = [];
        foreach ($handles as $key => $handle) {
            $error = curl_errno($handle);
            $results[$key] = $error ? false : curl_multi_getcontent($handle);
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }
        curl_multi_close($multi);

        return $results;
    }

    private function _request($url, $post = null, $cookie = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

        if ($cookie) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
        }

        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }

        $response  = curl_exec($ch);
        $errno     = curl_errno($ch);
        $error     = curl_error($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno || $response === false || $http_code >= 400) {
            return [
                'ok'        => false,
                'body'      => false,
                'http_code' => $http_code,
                'errno'     => $errno,
                'error'     => $error,
            ];
        }

        return [
            'ok'        => true,
            'body'      => $response,
            'http_code' => $http_code,
            'errno'     => 0,
            'error'     => '',
        ];
    }
}

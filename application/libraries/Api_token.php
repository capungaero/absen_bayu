<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Token bearer sederhana untuk API PWA karyawan (stateless, tanpa session).
 * Format: base64url(payload).base64url(HMAC-SHA256(payload, secret)).
 * payload = {"uid":<user_id>,"exp":<unix_ts>}.
 */
class Api_token {

    private $secret;
    private $ttl = 2592000; // 30 hari

    public function __construct(){
        $ci =& get_instance();
        $key = getenv('PWA_API_SECRET');
        if(!$key){ $key = $ci->config->item('encryption_key'); }
        if(!$key){ $key = 'tiffany-pwa-secret-please-set-encryption_key'; }
        // turunkan secret khusus token (beda dari encryption_key mentah)
        $this->secret = hash('sha256', 'pwa-api|'.$key);
    }

    private function b64u($d){ return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
    private function b64u_dec($d){ return base64_decode(strtr($d, '-_', '+/')); }

    /** Terbitkan token untuk user_id. */
    public function issue($user_id, $ttl = null){
        $payload = $this->b64u(json_encode([
            'uid' => (int)$user_id,
            'exp' => time() + ($ttl ?: $this->ttl)
        ]));
        $sig = $this->b64u(hash_hmac('sha256', $payload, $this->secret, true));
        return $payload.'.'.$sig;
    }

    /** Verifikasi token; return user_id (int) atau false. */
    public function verify($token){
        if(!$token || strpos($token, '.') === false){ return false; }
        list($payload, $sig) = explode('.', $token, 2);
        $expected = $this->b64u(hash_hmac('sha256', $payload, $this->secret, true));
        if(!hash_equals($expected, $sig)){ return false; }
        $data = json_decode($this->b64u_dec($payload), true);
        if(!is_array($data) || !isset($data['uid'], $data['exp'])){ return false; }
        if(time() > (int)$data['exp']){ return false; }
        return (int)$data['uid'];
    }
}

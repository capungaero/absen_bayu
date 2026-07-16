<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Static API key untuk endpoint admin/sistem eksternal (bukan token per-login).
 * Key dibaca dari env var ADMIN_API_KEY (pola sama dengan PWA_API_SECRET di Api_token.php).
 */
class Api_admin_key {

    private $key;

    public function __construct(){
        $k = getenv('ADMIN_API_KEY');
        $this->key = $k ?: null;
    }

    /** TRUE bila $presented cocok dengan ADMIN_API_KEY (timing-safe). Selalu FALSE kalau key belum diset. */
    public function verify($presented){
        if(!$this->key || !$presented){ return false; }
        return hash_equals($this->key, $presented);
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * LEGACY — sudah tidak dipakai.
 *
 * Modul presensi yang aktif sekarang ada di application/controllers/hr/Presence.php.
 * Semua route resmi (lihat application/config/routes.php) menunjuk ke `hr/Presence`,
 * bukan ke controller ini.
 *
 * Sebelumnya file ini berisi ~2000 baris yang sebagian besar duplikasi dari
 * hr/Presence.php. Sudah mulai drift (mis. method work_schedule, sync_pray_cloud,
 * save_work_schedule_manual, load_work_schedule_excel, copy_previous_work_schedule
 * hanya ada di hr/Presence). Untuk menghindari salah pakai dan menghapus permukaan
 * yang harus disinkronkan, isi controller diganti dengan _remap yang mengembalikan
 * 404 untuk semua URL /Presence/* (mis. dari CI auto-routing).
 *
 * Jangan menambah method baru di file ini. Kalau ada fitur lama yang perlu
 * dihidupkan kembali, port ke hr/Presence.
 *
 * History asli: lihat commit sebelum penghapusan ini.
 */
class Presence extends CI_Controller
{
    public function _remap($method, $params = [])
    {
        show_404();
    }
}

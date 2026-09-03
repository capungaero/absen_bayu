<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Pintu masuk tunggal Kertas Kerja -- satu link (Tools), redirect otomatis
 * sesuai hak akses: admin/admin-branch ke Setting, supervisor ke Review,
 * karyawan biasa ke PWA isi kertas kerja. Tak render apa pun sendiri, murni
 * router tipis di atas controller yang sudah ada (KertasKerjaSetting,
 * hr/KertasKerja, M::kertas_kerja).
 */
class KertasKerjaApp extends CI_Controller {

	public function index() {
		if (!$this->ion_auth->logged_in()) {
			redirect('');
		}
		$role = $this->ion_auth->get_users_groups()->row()->name;

		if (in_array($role, ['admin', 'admin-branch', 'kk-admin'])) {
			redirect('kertas_kerja_setting');
		} elseif ($role === 'supervisor') {
			redirect('hr/kertas_kerja');
		} else {
			redirect('m/kertas_kerja');
		}
	}
}

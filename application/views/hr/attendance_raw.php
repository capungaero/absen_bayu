<?php
/**
 * Halaman "Absensi Mentah" di dalam aplikasi utama.
 *
 * SPA-nya (tools/dat_reader) dimuat lewat iframe same-origin, bukan disisipkan
 * langsung: CSS bundle Vite men-set `body` dan atribut `data-theme` sendiri,
 * yang akan menabrak tema layout admin kalau digabung satu dokumen. Iframe
 * mengisolasi gaya sambil tetap berbagi sesi ion_auth, jadi halaman ini tetap
 * berada di dalam navigasi aplikasi (bukan tab baru seperti sebelumnya).
 */
?>
<div class="row">
  <div class="col-12">
    <div class="page-title-box d-flex align-items-center justify-content-between">
      <h4 class="page-title mb-0">Absensi Mentah</h4>
      <a class="btn btn-sm btn-light" href="<?= base_url('tools/dat_reader/index.html') ?>" target="_blank"
         title="Buka di tab terpisah">Buka di tab baru</a>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <iframe src="<?= base_url('tools/dat_reader/index.html') ?>"
            title="Absensi Mentah"
            style="width:100%;height:calc(100vh - 210px);min-height:520px;border:0;display:block;"></iframe>
  </div>
</div>

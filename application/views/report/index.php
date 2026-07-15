<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <div class="page-title-box d-flex align-items-center justify-content-between">
        <h4 class="mb-0"><i class="mdi mdi-flag-outline"></i> Laporan Mitra Kerja (PWA)</h4>
        <ol class="breadcrumb m-0">
          <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
          <li class="breadcrumb-item active">Laporan Mitra Kerja</li>
        </ol>
      </div>
    </div>
  </div>

  <div class="row mb-3">
    <div class="col-12 d-flex align-items-center gap-2 flex-wrap">
      <form method="get" class="d-flex gap-2 align-items-center">
        <label class="mb-0 text-muted small">Filter:</label>
        <select name="status" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
          <option value="">Semua</option>
          <option value="new"  <?= $filter==='new'  ? 'selected':'' ?>>Belum Dibaca</option>
          <option value="read" <?= $filter==='read'  ? 'selected':'' ?>>Sudah Dibaca</option>
          <option value="acc"  <?= $filter==='acc'   ? 'selected':'' ?>>Selesai (ACC)</option>
        </select>
      </form>
      <?php if($unread > 0): ?>
        <button class="btn btn-sm btn-outline-secondary" id="btnMarkAll">
          <i class="mdi mdi-check-all"></i> Tandai Semua Dibaca
        </button>
        <span class="badge bg-danger"><?= $unread ?> belum dibaca</span>
      <?php endif; ?>
      <span class="ms-auto text-muted small">Total: <?= $total ?> laporan</span>
    </div>
  </div>

  <?php if(empty($reports)): ?>
    <div class="card"><div class="card-body text-center text-muted py-5">
      <i class="mdi mdi-flag-outline" style="font-size:48px;opacity:.3"></i>
      <p class="mt-2">Belum ada laporan.</p>
    </div></div>
  <?php else: ?>
    <div class="row" id="reportList">
    <?php foreach($reports as $r):
      $st      = $r['status'];
      $dt      = date('d M Y H:i', strtotime($r['created_at']));
      $hasFile = !empty($r['file_path']);
      $fileUrl = $hasFile ? base_url($r['file_path']) : '';
      $ext     = $hasFile ? strtolower(pathinfo($r['file_path'], PATHINFO_EXTENSION)) : '';
      $isImg   = in_array($ext, ['jpg','jpeg','png','gif']);
      $empName = htmlspecialchars(trim($r['emp_name']) ?: 'Mitra Kerja #'.$r['user_id']);
      $branch  = htmlspecialchars($r['branch_name'] ?: '-');

      $badgeCls = ['new'=>'bg-danger','read'=>'bg-secondary','acc'=>'bg-success'][$st] ?? 'bg-secondary';
      $badgeTxt = ['new'=>'Baru','read'=>'Dibaca','acc'=>'Selesai'][$st] ?? $st;
      $borderCls = $st === 'new' ? 'border border-danger' : ($st === 'acc' ? 'border border-success' : '');
      $cat = $r['category'] ?? '';
      $catBadge = $cat === 'salah_input'
        ? '<span class="badge bg-warning text-dark ms-1" title="Salah Input Data"><i class="mdi mdi-pencil-circle-outline"></i> Salah Input</span>'
        : '<span class="badge bg-info text-dark ms-1" title="Error Aplikasi"><i class="mdi mdi-alert-circle-outline"></i> Error Aplikasi</span>';
    ?>
      <div class="col-md-6 col-xl-4 mb-3" id="rpt-<?= $r['id'] ?>">
        <div class="card h-100 <?= $borderCls ?>" style="<?= $st!=='read'?'border-width:2px!important':'' ?>">
          <div class="card-body d-flex flex-column">

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <div class="fw-bold"><?= $empName ?> <?= $catBadge ?></div>
                <div class="text-muted small"><?= $branch ?> &middot; <?= $dt ?></div>
              </div>
              <span class="badge <?= $badgeCls ?> ms-2 rpt-badge"><?= $badgeTxt ?></span>
            </div>

            <!-- Deskripsi -->
            <p class="mb-2 rpt-desc" style="font-size:14px;white-space:pre-line;flex:1"><?= nl2br(htmlspecialchars($r['description'])) ?></p>

            <!-- Catatan Admin -->
            <?php if(!empty($r['admin_note'])): ?>
            <div class="alert alert-info py-1 px-2 mb-2 rpt-note" style="font-size:12px">
              <i class="mdi mdi-note-text-outline"></i> <b>Catatan admin:</b><br>
              <?= nl2br(htmlspecialchars($r['admin_note'])) ?>
            </div>
            <?php else: ?>
            <div class="rpt-note" style="display:none"></div>
            <?php endif; ?>

            <!-- Lampiran -->
            <?php if($hasFile): ?>
              <?php if($isImg): ?>
                <img src="<?= $fileUrl ?>" alt="Bukti" class="img-fluid rounded mb-2 rpt-img"
                  style="max-height:150px;object-fit:cover;width:100%;cursor:zoom-in">
              <?php else: ?>
                <a href="<?= $fileUrl ?>" target="_blank" class="btn btn-sm btn-outline-secondary mb-2">
                  <i class="mdi mdi-file-outline"></i> Lihat Lampiran
                </a>
              <?php endif; ?>
            <?php endif; ?>

            <!-- Info ACC -->
            <?php if($st === 'acc' && !empty($r['acc_at'])): ?>
            <div class="text-success small mb-2 rpt-acc-info">
              <i class="mdi mdi-check-circle-outline"></i>
              ACC oleh <b><?= htmlspecialchars(trim($r['acc_name']) ?: 'Admin') ?></b>
              · <?= date('d M Y H:i', strtotime($r['acc_at'])) ?>
            </div>
            <?php else: ?>
            <div class="rpt-acc-info" style="display:none"></div>
            <?php endif; ?>

            <!-- Tombol Aksi -->
            <div class="d-flex gap-1 mt-auto pt-2">
              <?php if($st === 'new'): ?>
              <button class="btn btn-sm btn-outline-secondary btn-mark-read flex-fill" data-id="<?= $r['id'] ?>" title="Tandai sudah dibaca">
                <i class="mdi mdi-eye-outline"></i> Baca
              </button>
              <?php endif; ?>

              <button class="btn btn-sm btn-outline-primary btn-edit flex-fill"
                data-id="<?= $r['id'] ?>"
                data-desc="<?= htmlspecialchars($r['description'], ENT_QUOTES) ?>"
                data-note="<?= htmlspecialchars($r['admin_note'] ?? '', ENT_QUOTES) ?>"
                title="Edit laporan">
                <i class="mdi mdi-pencil-outline"></i> Edit
              </button>

              <?php if($st !== 'acc'): ?>
              <button class="btn btn-sm btn-outline-success btn-acc flex-fill" data-id="<?= $r['id'] ?>" title="ACC / tandai selesai">
                <i class="mdi mdi-check-circle-outline"></i> ACC
              </button>
              <?php else: ?>
              <button class="btn btn-sm btn-success flex-fill" disabled title="Sudah di-ACC">
                <i class="mdi mdi-check-circle"></i> Selesai
              </button>
              <?php endif; ?>

              <button class="btn btn-sm btn-outline-danger btn-delete" data-id="<?= $r['id'] ?>" title="Hapus laporan">
                <i class="mdi mdi-trash-can-outline"></i>
              </button>
            </div>

          </div>
        </div>
      </div>
    <?php endforeach; ?>
    </div>

    <?php $total_pages = ceil($total / $per_page);
      if($total_pages > 1): $qs = $filter ? '?status='.$filter.'&' : '?'; ?>
    <nav><ul class="pagination">
      <?php for($i=1; $i<=$total_pages; $i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
          <a class="page-link" href="<?= site_url('report').$qs.'page='.$i ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="mdi mdi-pencil-outline"></i> Edit Laporan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editId">
        <div class="mb-3">
          <label class="form-label fw-semibold">Deskripsi Laporan</label>
          <textarea class="form-control" id="editDesc" rows="4" placeholder="Deskripsi masalah..."></textarea>
        </div>
        <div class="mb-1">
          <label class="form-label fw-semibold">Catatan Admin <span class="text-muted fw-normal">(opsional)</span></label>
          <textarea class="form-control" id="editNote" rows="3" placeholder="Catatan tindak lanjut dari admin..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-primary" id="btnSaveEdit"><i class="mdi mdi-content-save-outline"></i> Simpan</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Zoom Gambar -->
<div id="imgModal" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-dark border-0">
      <div class="modal-body p-1 text-center">
        <img id="imgModalSrc" src="" style="max-width:100%;max-height:85vh;object-fit:contain">
      </div>
      <div class="modal-footer border-0 py-1 justify-content-center">
        <a id="imgModalLink" href="" target="_blank" class="btn btn-sm btn-outline-light">
          <i class="mdi mdi-open-in-new"></i> Buka di tab baru
        </a>
        <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
var CSRF_NAME  = '<?= $this->security->get_csrf_token_name() ?>';
var CSRF_HASH  = '<?= $this->security->get_csrf_hash() ?>';
var URL_READ   = '<?= site_url('report/mark_read') ?>';
var URL_READALL= '<?= site_url('report/mark_all_read') ?>';
var URL_ACC    = '<?= site_url('report/acc') ?>';
var URL_EDIT   = '<?= site_url('report/edit') ?>';
var URL_DELETE = '<?= site_url('report/delete') ?>';

function ajaxPost(url, data, cb){
  data[CSRF_NAME] = CSRF_HASH;
  $.post(url, data, function(res){ CSRF_HASH = res.csrf || CSRF_HASH; cb(res); }, 'json')
   .fail(function(){ cb({status:false,message:'Gagal terhubung ke server'}); });
}

function toastMsg(msg, isErr){
  var t = $('<div class="position-fixed top-0 end-0 p-3" style="z-index:9999">'
    + '<div class="toast show align-items-center text-white border-0 '+(isErr?'bg-danger':'bg-success')+'">'
    + '<div class="d-flex"><div class="toast-body">'+msg+'</div>'
    + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>'
    + '</div></div></div>');
  $('body').append(t);
  setTimeout(function(){ t.remove(); }, 3000);
}

/* ---- Tandai Baca ---- */
$(document).on('click', '.btn-mark-read', function(){
  var btn = $(this), id = btn.data('id');
  btn.prop('disabled', true);
  ajaxPost(URL_READ, {id: id}, function(res){
    if(res.status){
      var card = $('#rpt-'+id+' .card');
      card.removeClass('border border-danger').css('border-width','');
      $('#rpt-'+id+' .rpt-badge').attr('class','badge bg-secondary ms-2 rpt-badge').text('Dibaca');
      btn.remove();
    } else {
      btn.prop('disabled', false);
      toastMsg(res.message||'Gagal', true);
    }
  });
});

/* ---- Tandai Semua Baca ---- */
$('#btnMarkAll').on('click', function(){
  if(!confirm('Tandai semua laporan sebagai sudah dibaca?')) return;
  ajaxPost(URL_READALL, {}, function(res){
    if(res.status) location.reload();
  });
});

/* ---- ACC ---- */
$(document).on('click', '.btn-acc', function(){
  var btn = $(this), id = btn.data('id');
  if(!confirm('ACC laporan ini? Tindakan ini akan tercatat atas nama Anda.')) return;
  btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i>');
  ajaxPost(URL_ACC, {id: id}, function(res){
    if(res.status){
      var card = $('#rpt-'+id+' .card');
      card.removeClass('border border-danger').addClass('border border-success').css('border-width','2px');
      $('#rpt-'+id+' .rpt-badge').attr('class','badge bg-success ms-2 rpt-badge').text('Selesai');
      $('#rpt-'+id+' .rpt-acc-info').show()
        .html('<i class="mdi mdi-check-circle-outline"></i> ACC oleh <b>'+res.acc_name+'</b> · '+res.acc_at);
      btn.replaceWith('<button class="btn btn-sm btn-success flex-fill" disabled>'
        +'<i class="mdi mdi-check-circle"></i> Selesai</button>');
      toastMsg('Laporan berhasil di-ACC');
    } else {
      btn.prop('disabled', false).html('<i class="mdi mdi-check-circle-outline"></i> ACC');
      toastMsg(res.message||'Gagal', true);
    }
  });
});

/* ---- Edit ---- */
$(document).on('click', '.btn-edit', function(){
  var btn = $(this);
  $('#editId').val(btn.data('id'));
  $('#editDesc').val(btn.data('desc'));
  $('#editNote').val(btn.data('note'));
  new bootstrap.Modal(document.getElementById('modalEdit')).show();
});

$('#btnSaveEdit').on('click', function(){
  var id   = $('#editId').val();
  var desc = $('#editDesc').val().trim();
  var note = $('#editNote').val().trim();
  if(!desc){ toastMsg('Deskripsi tidak boleh kosong', true); return; }

  var btn = $(this);
  btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Menyimpan...');

  ajaxPost(URL_EDIT, {id:id, description:desc, admin_note:note}, function(res){
    btn.prop('disabled', false).html('<i class="mdi mdi-content-save-outline"></i> Simpan');
    if(res.status){
      bootstrap.Modal.getInstance(document.getElementById('modalEdit')).hide();
      // Update tampilan deskripsi langsung tanpa reload
      $('#rpt-'+id+' .rpt-desc').text(res.description);
      // Update data-* supaya buka edit lagi tetap benar
      $('#rpt-'+id+' .btn-edit').data('desc', res.description).data('note', res.admin_note||'');
      // Update catatan admin
      var noteBox = $('#rpt-'+id+' .rpt-note');
      if(res.admin_note){
        noteBox.show().html('<i class="mdi mdi-note-text-outline"></i> <b>Catatan admin:</b><br>'
          + $('<div>').text(res.admin_note).html().replace(/\n/g,'<br>'));
      } else {
        noteBox.hide().html('');
      }
      toastMsg('Laporan berhasil diperbarui');
    } else {
      toastMsg(res.message||'Gagal menyimpan', true);
    }
  });
});

/* ---- Delete ---- */
$(document).on('click', '.btn-delete', function(){
  var id = $(this).data('id');
  if(!confirm('Hapus laporan ini? Tindakan tidak dapat dibatalkan.')) return;
  var btn = $(this);
  btn.prop('disabled', true);
  ajaxPost(URL_DELETE, {id: id}, function(res){
    if(res.status){
      $('#rpt-'+id).fadeOut(300, function(){ $(this).remove(); });
      toastMsg('Laporan dihapus');
    } else {
      btn.prop('disabled', false);
      toastMsg(res.message||'Gagal menghapus', true);
    }
  });
});

/* ---- Zoom gambar ---- */
$(document).on('click', '.rpt-img', function(){
  var src = $(this).attr('src');
  $('#imgModalSrc').attr('src', src);
  $('#imgModalLink').attr('href', src);
  new bootstrap.Modal(document.getElementById('imgModal')).show();
});
</script>

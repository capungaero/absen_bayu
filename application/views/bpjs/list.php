<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0 font-size-18"><i class="mdi mdi-clipboard-list me-2"></i>List Pembayaran BPJS</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item">BPJS</li>
                    <li class="breadcrumb-item active">List Pembayaran</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="card-title mb-0">Riwayat Pembayaran BPJS Karyawan</h6>
                <button id="btnSyncGaji" class="btn btn-sm btn-success"><i class="mdi mdi-sync"></i> Sinkron ke Gaji</button>
            </div>
            <div class="card-body">

                <div class="alert alert-info py-2 mb-3" style="font-size:13px">
                    <i class="mdi mdi-information"></i> Karyawan <b>"Dibayar Kantor"</b> otomatis masuk potongan BPJS di gaji; karyawan <b>mandiri yang sudah di-ACC</b> otomatis masuk insentif. Tombol <b>Sinkron ke Gaji</b> menerapkan ulang semua data periode ini (mis. setelah ubah konfigurasi). Tidak berlaku bila penggajian periode sudah final.
                </div>


                <form method="get" action="<?= site_url('bpjs/list') ?>">
                    <div class="row">
                        <?php if ($role === 'admin'): ?>
                        <div class="col-md-4">
                            <label>Cabang</label>
                            <select class="form-control select-plugin" name="branch_id">
                                <?php foreach ($branch as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>><?= $b['branch_code']." / ".$b['branch_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-3">
                            <label>Bulan</label>
                            <select class="form-control" name="month">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= get_monthname($m) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Tahun</label>
                            <select class="form-control" name="year">
                                <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 3; $y--): ?>
                                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <br><button class="btn btn-primary mt-2"><i class="fa fa-search"></i> Tampilkan</button>
                        </div>
                    </div>
                </form>

                <br>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable" style="width:100%">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Karyawan</th>
                                <th>Jabatan</th>
                                <th class="text-center">Dibayar Kantor</th>
                                <th class="text-end">Potongan Kesehatan</th>
                                <th class="text-end">Potongan Ketenagakerjaan</th>
                                <th class="text-end">Insentif Mandiri</th>
                                <th class="text-center">Bukti</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($payments as $p):
                                $mode      = $p['pay_mode'] ?: 'mandiri';
                                $is_office = $mode === 'kantor';
                                $status    = $p['status'] ?: 'pending';
                                $name      = trim($p['first_name'].' '.$p['last_name']);
                            ?>
                            <tr data-user="<?= $p['user_id'] ?>">
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($name) ?><br><small class="text-muted"><?= htmlspecialchars($p['employee_code']) ?></small></td>
                                <td><?= htmlspecialchars($p['position_name']) ?></td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input toggle-office" type="checkbox" data-user="<?= $p['user_id'] ?>" <?= $is_office ? 'checked' : '' ?>>
                                    </div>
                                </td>
                                <td class="text-end col-kesehatan"><?= format_rp((int)$p['kesehatan_amount']) ?></td>
                                <td class="text-end col-ketenagakerjaan"><?= format_rp((int)$p['ketenagakerjaan_amount']) ?></td>
                                <td class="text-end col-insentif"><?= format_rp((int)$p['mandiri_insentif_amount']) ?></td>
                                <td class="text-center">
                                    <?php if (!empty($p['proof_path'])): ?>
                                        <a href="<?= base_url($p['proof_path']) ?>" target="_blank"><i class="mdi mdi-file-image"></i> Lihat</a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center col-status">
                                    <?php if ($is_office): ?>
                                        <span class="badge bg-info">Dibayar Kantor</span>
                                    <?php elseif ($status === 'approved'): ?>
                                        <span class="badge bg-success">Mandiri · ACC</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Mandiri · Belum ACC</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center col-aksi">
                                    <?php if (!$is_office && $status !== 'approved'): ?>
                                        <button class="btn btn-sm btn-success btn-acc" data-user="<?= $p['user_id'] ?>"><i class="fa fa-check"></i> ACC</button>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
(function(){
    var MONTH = <?= (int)$month ?>, YEAR = <?= (int)$year ?>;
    var CFG = {
        kesehatan: <?= (int)$config['kesehatan_employee'] ?>,
        ketenagakerjaan: <?= (int)$config['ketenagakerjaan_employee'] ?>,
        mandiri: <?= (int)$config['mandiri_insentif'] ?>
    };
    var TOKEN = "<?= $this->security->get_csrf_hash() ?>";

    function rp(n){ return 'Rp ' + (n||0).toLocaleString('id-ID'); }

    $(document).on('change', '.toggle-office', function(){
        var $chk = $(this), $tr = $chk.closest('tr');
        var mode = $chk.is(':checked') ? 'kantor' : 'mandiri';

        $.post("<?= site_url('bpjs/toggle_office') ?>", {
            myToken: TOKEN, user_id: $chk.data('user'), month: MONTH, year: YEAR, mode: mode
        }, null, 'json').done(function(res){
            if(!res.status){ show_modal('info', res.message); $chk.prop('checked', !$chk.is(':checked')); return; }
            if(mode === 'kantor'){
                $tr.find('.col-kesehatan').text(rp(CFG.kesehatan));
                $tr.find('.col-ketenagakerjaan').text(rp(CFG.ketenagakerjaan));
                $tr.find('.col-insentif').text(rp(0));
                $tr.find('.col-status').html('<span class="badge bg-info">Dibayar Kantor</span>');
                $tr.find('.col-aksi').html('<span class="text-muted">-</span>');
            } else {
                $tr.find('.col-kesehatan').text(rp(0));
                $tr.find('.col-ketenagakerjaan').text(rp(0));
                $tr.find('.col-insentif').text(rp(0));
                $tr.find('.col-status').html('<span class="badge bg-warning">Mandiri · Belum ACC</span>');
                $tr.find('.col-aksi').html('<button class="btn btn-sm btn-success btn-acc" data-user="'+$chk.data('user')+'"><i class="fa fa-check"></i> ACC</button>');
            }
        });
    });

    $(document).on('click', '#btnSyncGaji', function(){
        var $b = $(this);
        $b.attr('disabled','disabled').html('<i class="mdi mdi-sync mdi-spin"></i> Menyinkron...');
        $.post("<?= site_url('bpjs/sync') ?>", { myToken: TOKEN, month: MONTH, year: YEAR }, null, 'json')
        .done(function(res){ show_modal(res.status ? 'success' : 'info', res.message); })
        .always(function(){ $b.removeAttr('disabled').html('<i class="mdi mdi-sync"></i> Sinkron ke Gaji'); });
    });

    $(document).on('click', '.btn-acc', function(){
        var $btn = $(this), $tr = $btn.closest('tr');
        $.post("<?= site_url('bpjs/acc') ?>", {
            myToken: TOKEN, user_id: $btn.data('user'), month: MONTH, year: YEAR
        }, null, 'json').done(function(res){
            if(!res.status){ show_modal('info', res.message); return; }
            $tr.find('.col-insentif').text(rp(CFG.mandiri));
            $tr.find('.col-status').html('<span class="badge bg-success">Mandiri · ACC</span>');
            $tr.find('.col-aksi').html('<span class="text-muted">-</span>');
        });
    });
})();
</script>

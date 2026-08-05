<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0 font-size-18"><i class="mdi mdi-checkbox-marked-outline me-2"></i>Setting Kertas Kerja</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Setting Kertas Kerja</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Wajib Isi Kertas Kerja</h6>
            </div>
            <div class="card-body">

                <div class="alert alert-info py-2 mb-3" style="font-size:13px">
                    <i class="mdi mdi-information"></i> Mitra Kerja yang ditandai <b>wajib</b> akan melihat menu Kertas Kerja di dashboard PWA-nya. Yang tidak ditandai tidak akan melihat menu ini sama sekali.
                </div>

                <?php if ($role === 'admin'): ?>
                <form method="get" action="<?= site_url('kertas_kerja_setting') ?>" class="row mb-3">
                    <div class="col-md-4">
                        <label>Cabang</label>
                        <select class="form-control select-plugin" name="branch_id" onchange="this.form.submit()">
                            <?php foreach ($branch as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $branch_id == $b['id'] ? 'selected' : '' ?>><?= $b['branch_code']." / ".$b['branch_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable" style="width:100%">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Mitra Kerja</th>
                                <th>Jabatan</th>
                                <th class="text-center">Wajib Isi Kertas Kerja</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($employee as $e): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($e['first_name']) ?><br><small class="text-muted"><?= htmlspecialchars($e['employee_code']) ?></small></td>
                                <td><?= htmlspecialchars($e['position_name']) ?></td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input toggle-wajib" type="checkbox" data-user="<?= $e['id'] ?>" <?= !empty($e['wajib_kertas_kerja']) ? 'checked' : '' ?>>
                                    </div>
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

<?php if ($role === 'admin'): ?>
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Penugasan SPV Kertas Kerja</h6>
            </div>
            <div class="card-body">

                <div class="alert alert-info py-2 mb-3" style="font-size:13px">
                    <i class="mdi mdi-information"></i> SPV hanya bisa melihat Kertas Kerja milik Mitra Kerja yang ditugaskan ke dirinya di sini (lintas cabang, bukan otomatis per cabang).
                </div>

                <form id="formAssignSpv" class="row g-2 mb-3">
                    <div class="col-md-4">
                        <select class="form-control select-plugin" id="spvSelect" required>
                            <option value="">Pilih SPV</option>
                            <?php foreach ($spv_list as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= $s['employee_code']." / ".$s['first_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select class="form-control select-plugin" id="employeeSelect" required>
                            <option value="">Pilih Mitra Kerja</option>
                            <?php foreach ($employee as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= $e['employee_code']." / ".$e['first_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary" type="submit"><i class="fa fa-plus"></i> Tugaskan</button>
                    </div>
                </form>

                <table class="table table-bordered" id="tableAssignment">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>SPV</th>
                            <th>Mengawasi</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($assignment as $a): ?>
                        <tr data-id="<?= $a['id'] ?>">
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($a['spv_name']) ?></td>
                            <td><?= htmlspecialchars($a['employee_name']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-danger btn-unassign" data-id="<?= $a['id'] ?>"><i class="fa fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script type="text/javascript">
(function(){
    var TOKEN = "<?= $this->security->get_csrf_hash() ?>";

    $(document).on('change', '.toggle-wajib', function(){
        var $chk = $(this);
        var wajib = $chk.is(':checked') ? '1' : '0';

        $.post("<?= site_url('kertas_kerja_setting/toggle') ?>", {
            myToken: TOKEN, user_id: $chk.data('user'), wajib: wajib
        }, null, 'json').done(function(res){
            if(!res.status){ show_modal('info', res.message); $chk.prop('checked', !$chk.is(':checked')); }
        });
    });

    $(document).on('submit', '#formAssignSpv', function(e){
        e.preventDefault();
        var spv = $('#spvSelect').val(), emp = $('#employeeSelect').val();
        if(!spv || !emp){ return; }

        $.post("<?= site_url('kertas_kerja_setting/spv/assign') ?>", {
            myToken: TOKEN, spv_user_id: spv, employee_user_id: emp
        }, null, 'json').done(function(res){
            show_modal('info', res.message);
            if(res.status){
                var spvName = $('#spvSelect option:selected').text();
                var empName = $('#employeeSelect option:selected').text();
                $('#tableAssignment tbody').append(
                    '<tr data-id="'+res.id+'"><td>-</td><td>'+spvName+'</td><td>'+empName+
                    '</td><td class="text-center"><button class="btn btn-sm btn-danger btn-unassign" data-id="'+res.id+'"><i class="fa fa-trash"></i></button></td></tr>'
                );
                $('#formAssignSpv')[0].reset();
            }
        });
    });

    $(document).on('click', '.btn-unassign', function(){
        var $btn = $(this), $tr = $btn.closest('tr');
        $.post("<?= site_url('kertas_kerja_setting/spv/unassign') ?>", {
            myToken: TOKEN, id: $btn.data('id')
        }, null, 'json').done(function(res){
            if(res.status){ $tr.remove(); }
        });
    });
})();
</script>

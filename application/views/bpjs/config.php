<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0 font-size-18"><i class="mdi mdi-shield-account me-2"></i>Konfigurasi BPJS</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item">BPJS</li>
                    <li class="breadcrumb-item active">Konfigurasi</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php $cfg = !empty($config) ? $config : []; ?>

<form id="formConfig">
<input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
<div class="row">

    <!-- BPJS Kesehatan -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0"><i class="mdi mdi-medical-bag me-1 text-danger"></i>BPJS Kesehatan</h5></div>
            <div class="card-body" data-bpjs-group="kesehatan">
                <p class="text-muted">Isi <b>beban karyawan</b> & <b>beban perusahaan</b> (total &amp; persen terhitung otomatis), atau isi <b>total</b> &amp; <b>persen karyawan</b> (beban terhitung otomatis).</p>
                <div class="mb-3">
                    <label class="form-label">Total Iuran / Bulan</label>
                    <input type="text" class="form-control bpjs-num" data-field="total" name="kesehatan_total" value="<?= (int)($cfg['kesehatan_total'] ?? 0) ?>">
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">% Ditanggung Karyawan</label>
                        <input type="number" step="0.01" min="0" max="100" class="form-control bpjs-pct" data-field="pct" name="kesehatan_pct_employee" value="<?= (float)($cfg['kesehatan_pct_employee'] ?? 0) ?>">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Beban Karyawan (Potongan)</label>
                        <input type="text" class="form-control bpjs-num" data-field="employee" name="kesehatan_employee" value="<?= (int)($cfg['kesehatan_employee'] ?? 0) ?>">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Beban Perusahaan</label>
                        <input type="text" class="form-control bpjs-num" data-field="company" name="kesehatan_company" value="<?= (int)($cfg['kesehatan_company'] ?? 0) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- BPJS Ketenagakerjaan -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0"><i class="mdi mdi-hard-hat me-1 text-primary"></i>BPJS Ketenagakerjaan</h5></div>
            <div class="card-body" data-bpjs-group="ketenagakerjaan">
                <p class="text-muted">Isi <b>beban karyawan</b> & <b>beban perusahaan</b> (total &amp; persen terhitung otomatis), atau isi <b>total</b> &amp; <b>persen karyawan</b> (beban terhitung otomatis).</p>
                <div class="mb-3">
                    <label class="form-label">Total Iuran / Bulan</label>
                    <input type="text" class="form-control bpjs-num" data-field="total" name="ketenagakerjaan_total" value="<?= (int)($cfg['ketenagakerjaan_total'] ?? 0) ?>">
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">% Ditanggung Karyawan</label>
                        <input type="number" step="0.01" min="0" max="100" class="form-control bpjs-pct" data-field="pct" name="ketenagakerjaan_pct_employee" value="<?= (float)($cfg['ketenagakerjaan_pct_employee'] ?? 0) ?>">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Beban Karyawan (Potongan)</label>
                        <input type="text" class="form-control bpjs-num" data-field="employee" name="ketenagakerjaan_employee" value="<?= (int)($cfg['ketenagakerjaan_employee'] ?? 0) ?>">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Beban Perusahaan</label>
                        <input type="text" class="form-control bpjs-num" data-field="company" name="ketenagakerjaan_company" value="<?= (int)($cfg['ketenagakerjaan_company'] ?? 0) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Insentif Mandiri -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0"><i class="mdi mdi-cash-plus me-1 text-success"></i>Insentif Bayar Mandiri</h5></div>
            <div class="card-body">
                <p class="text-muted">Diberikan ke karyawan yang membayar BPJS sendiri (setelah bukti di-ACC admin).</p>
                <div class="mb-3">
                    <label class="form-label">Nominal Insentif Mandiri</label>
                    <input type="text" class="form-control bpjs-plain" name="mandiri_insentif" value="<?= (int)($cfg['mandiri_insentif'] ?? 0) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <button id="btnSaveConfig" type="submit" class="btn btn-success"><i class="fa fa-check"></i> Simpan Konfigurasi</button>
    </div>
</div>
</form>

<script type="text/javascript">
(function(){
    function toNum(v){ return parseInt(String(v).replace(/[^0-9]/g,''),10) || 0; }
    function fmt(n){ return (n||0).toLocaleString('id-ID'); }

    // Format nominal saat blur
    $(document).on('blur', '.bpjs-num, .bpjs-plain', function(){
        $(this).val(fmt(toNum($(this).val())));
    });

    // Format nilai awal
    $('.bpjs-num, .bpjs-plain').each(function(){ $(this).val(fmt(toNum($(this).val()))); });

    function recalc($group, source){
        var total = toNum($group.find('[data-field=total]').val());
        var pct   = parseFloat($group.find('[data-field=pct]').val()) || 0;
        var emp   = toNum($group.find('[data-field=employee]').val());
        var comp  = toNum($group.find('[data-field=company]').val());

        if(source === 'employee' || source === 'company'){
            // beban karyawan/perusahaan → total & persen
            total = emp + comp;
            pct   = total > 0 ? Math.round((emp / total) * 10000) / 100 : 0;
        } else {
            // total / persen → beban
            emp  = Math.round(total * pct / 100);
            comp = total - emp;
        }

        $group.find('[data-field=total]').val(fmt(total));
        $group.find('[data-field=pct]').val(pct);
        $group.find('[data-field=employee]').val(fmt(emp));
        $group.find('[data-field=company]').val(fmt(comp));
    }

    $(document).on('input', '[data-bpjs-group] .bpjs-num, [data-bpjs-group] .bpjs-pct', function(){
        recalc($(this).closest('[data-bpjs-group]'), $(this).data('field'));
    });

    $(document).on('submit', '#formConfig', function(e){
        e.preventDefault();
        var btn = $('#btnSaveConfig');
        // kirim angka bersih (tanpa titik ribuan)
        var fd = new FormData(this);
        $('.bpjs-num, .bpjs-plain').each(function(){ fd.set($(this).attr('name'), toNum($(this).val())); });

        $.ajax({
            url: "<?= site_url('bpjs/save_config') ?>", method: "POST", dataType: "json",
            data: fd, processData: false, contentType: false,
            beforeSend: function(){ btn.html(show_loading()).attr('disabled','disabled'); },
            success: function(res){ show_modal(res.status ? 'success' : 'info', res.message); },
            complete: function(){ btn.html('<i class="fa fa-check"></i> Simpan Konfigurasi').removeAttr('disabled'); }
        });
        return false;
    });
})();
</script>

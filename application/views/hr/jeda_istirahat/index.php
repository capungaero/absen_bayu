<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="mdi mdi-food-fork-drink"></i> Jeda Istirahat</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Tools</a></li>
                    <li class="breadcrumb-item active">Jeda Istirahat</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="jeda-istirahat-wrap">

    <!-- Stat tiles -->
    <div class="row ji-stats">
        <div class="col-6 col-md-3">
            <div class="card ji-stat"><div class="card-body">
                <div class="ji-label">Total kejadian</div>
                <div class="ji-value" id="ji-stat-total">&mdash;</div>
                <div class="ji-sub" id="ji-stat-total-sub">&nbsp;</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card ji-stat"><div class="card-body">
                <div class="ji-label">Karyawan terlibat</div>
                <div class="ji-value" id="ji-stat-emp">&mdash;</div>
                <div class="ji-sub" id="ji-stat-emp-sub">&nbsp;</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card ji-stat"><div class="card-body">
                <div class="ji-label">Rata-rata jeda</div>
                <div class="ji-value" id="ji-stat-avg">&mdash;</div>
                <div class="ji-sub">menit</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card ji-stat"><div class="card-body">
                <div class="ji-label">Hari dengan kejadian</div>
                <div class="ji-value" id="ji-stat-days">&mdash;</div>
                <div class="ji-sub" id="ji-stat-days-sub">&nbsp;</div>
            </div></div>
        </div>
    </div>

    <!-- Period picker -->
    <div class="card">
        <div class="card-header"><h6 class="card-title mb-0">Periode</h6></div>
        <div class="card-body">
            <form id="ji-period-form" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Tipe periode</label>
                    <select class="form-control" id="ji-periode-type">
                        <option value="gaji">Periode gaji</option>
                        <option value="minggu">Minggu</option>
                        <option value="hari">Hari</option>
                    </select>
                </div>
                <div class="col-md-2 ji-period-field" data-for="gaji">
                    <label class="form-label">Bulan</label>
                    <select class="form-control" id="ji-bulan">
                        <?php for($m=1; $m<=12; $m++): $mm = str_pad($m,2,'0',STR_PAD_LEFT); ?>
                        <option value="<?= $mm ?>" <?= $mm == date('m') ? 'selected' : '' ?>><?= get_monthname($mm) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2 ji-period-field" data-for="gaji">
                    <label class="form-label">Tahun</label>
                    <select class="form-control" id="ji-tahun">
                        <?php $now = (int)date('Y'); for($y=2024; $y<=$now+1; $y++): ?>
                        <option value="<?= $y ?>" <?= $y == $now ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3 ji-period-field" data-for="minggu hari" style="display:none;">
                    <label class="form-label" id="ji-tanggal-label">Tanggal</label>
                    <input type="date" class="form-control" id="ji-tanggal" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cabang</label>
                    <select class="form-control" id="ji-branch">
                        <option value="">Semua cabang</option>
                        <?php foreach($branch as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['branch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="mdi mdi-magnify"></i> Terapkan</button>
                </div>
            </form>
            <div class="ji-period-label mt-2" id="ji-period-label">&nbsp;</div>
        </div>
    </div>

    <!-- Threshold + histogram -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <h6 class="card-title mb-0">Filter jeda waktu</h6>
            <span class="ji-hint">Atur batas jeda (menit) untuk melihat siapa saja yang masuk kriteria</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="ji-range-values"><span>min <b id="ji-rmin-val">0</b></span><span>maks <b id="ji-rmax-val">30</b></span></div>
                    <div class="ji-dual-range">
                        <div class="ji-track"></div>
                        <div class="ji-track-fill" id="ji-track-fill"></div>
                        <input type="range" id="ji-r-min" min="0" max="60" step="1" value="0">
                        <input type="range" id="ji-r-max" min="0" max="60" step="1" value="30">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Arah pola</label>
                    <select class="form-control" id="ji-f-pola">
                        <option value="all">Semua arah</option>
                        <option value="rest_ke_sholat">Istirahat &rarr; Sholat</option>
                        <option value="sholat_ke_rest">Sholat &rarr; Istirahat</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cari nama karyawan</label>
                    <input type="text" class="form-control" id="ji-f-nama" placeholder="Ketik nama&hellip;">
                </div>
            </div>
            <div class="ji-hist-wrap mt-3">
                <canvas id="ji-hist" width="1100" height="140"></canvas>
            </div>
        </div>
    </div>

    <!-- Tables -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <div class="ji-tabs">
                <button type="button" class="btn btn-sm btn-primary ji-tab-btn active" data-tab="rekap">Rekap per karyawan</button>
                <button type="button" class="btn btn-sm btn-outline-secondary ji-tab-btn" data-tab="daftar">Daftar kejadian</button>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="ji-hint" id="ji-result-hint">&nbsp;</span>
                <button type="button" class="btn btn-sm btn-success" id="ji-export-btn"><i class="mdi mdi-file-excel"></i> Export Excel</button>
            </div>
        </div>
        <div class="card-body">
            <div id="ji-loading" class="ji-empty" style="display:none;"><i class="mdi mdi-loading mdi-spin"></i> Memuat data periode&hellip;</div>

            <div class="ji-tab-panel active" id="ji-tab-rekap">
                <div class="table-responsive">
                    <table class="table table-sm table-hover ji-table" id="ji-tbl-rekap">
                        <thead>
                            <tr>
                                <th data-sort="nama">Nama</th>
                                <th data-sort="kejadian" class="text-end sorted">Kejadian</th>
                                <th data-sort="total" class="text-end">Total menit</th>
                                <th data-sort="rata2" class="text-end">Rata-rata</th>
                                <th data-sort="tglawal">Tanggal pertama</th>
                                <th data-sort="tglakhir">Tanggal terakhir</th>
                            </tr>
                        </thead>
                        <tbody id="ji-body-rekap"></tbody>
                    </table>
                </div>
            </div>

            <div class="ji-tab-panel" id="ji-tab-daftar">
                <div class="table-responsive">
                    <table class="table table-sm table-hover ji-table" id="ji-tbl-daftar">
                        <thead>
                            <tr>
                                <th data-sort="tanggal" class="sorted">Tanggal</th>
                                <th data-sort="nama">Nama</th>
                                <th data-sort="cabang">Cabang</th>
                                <th data-sort="pola">Pola</th>
                                <th data-sort="sholat">Sholat</th>
                                <th>Istirahat (mulai&ndash;selesai)</th>
                                <th>Sholat (mulai&ndash;selesai)</th>
                                <th data-sort="selisih" class="text-end">Selisih</th>
                            </tr>
                        </thead>
                        <tbody id="ji-body-daftar"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <p class="ji-footnote">
        <strong>Metodologi:</strong> data diambil langsung dari tabel <code>presence</code> untuk periode terpilih.
        Kejadian dihitung ketika <code>rest_time_out</code> &rarr; salah satu jam mulai sholat (atau sebaliknya, jam selesai sholat &rarr; <code>rest_time_in</code>)
        berjarak 0&ndash;60 menit pada tanggal yang sama, dan tap sholat yang jatuh di dalam window istirahat biasa sudah dikecualikan.
        Gunakan slider jeda di atas untuk mempersempit ke ambang batas yang dianggap wajar.
    </p>
</div>

<style>
.jeda-istirahat-wrap .ji-stat .card-body{ padding:12px 16px; }
.jeda-istirahat-wrap .ji-label{ font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#8a92a6; font-weight:600; }
.jeda-istirahat-wrap .ji-value{ font-size:24px; font-weight:700; margin-top:4px; font-variant-numeric:tabular-nums; }
.jeda-istirahat-wrap .ji-sub{ font-size:11.5px; color:#8a92a6; margin-top:2px; }
.jeda-istirahat-wrap .ji-hint{ font-size:12px; color:#8a92a6; }
.jeda-istirahat-wrap .ji-period-label{ font-size:12.5px; color:#556; font-weight:500; }

.jeda-istirahat-wrap .ji-range-values{ display:flex; justify-content:space-between; font-size:12.5px; margin-bottom:6px; font-variant-numeric:tabular-nums; }
.jeda-istirahat-wrap .ji-range-values b{ color:#556ee6; font-weight:700; }
.jeda-istirahat-wrap .ji-dual-range{ position:relative; height:26px; }
.jeda-istirahat-wrap .ji-dual-range input[type="range"]{
    position:absolute; left:0; right:0; top:9px; width:100%; margin:0;
    -webkit-appearance:none; appearance:none; background:transparent; pointer-events:none;
}
.jeda-istirahat-wrap .ji-dual-range input[type="range"]::-webkit-slider-thumb{
    -webkit-appearance:none; pointer-events:auto; width:16px; height:16px; border-radius:50%;
    background:#556ee6; border:2px solid #fff; box-shadow:0 0 0 1px #556ee6; cursor:pointer; margin-top:-6px;
}
.jeda-istirahat-wrap .ji-dual-range input[type="range"]::-moz-range-thumb{
    pointer-events:auto; width:14px; height:14px; border-radius:50%; background:#556ee6;
    border:2px solid #fff; box-shadow:0 0 0 1px #556ee6; cursor:pointer;
}
.jeda-istirahat-wrap .ji-track{ position:absolute; left:0; right:0; top:12px; height:4px; border-radius:2px; background:#e3e6ef; }
.jeda-istirahat-wrap .ji-track-fill{ position:absolute; top:12px; height:4px; border-radius:2px; background:#556ee6; }

.jeda-istirahat-wrap .ji-hist-wrap canvas{ width:100%; height:140px; display:block; }

.jeda-istirahat-wrap .ji-tabs{ display:flex; gap:6px; }
.jeda-istirahat-wrap .ji-tab-panel{ display:none; }
.jeda-istirahat-wrap .ji-tab-panel.active{ display:block; }

.jeda-istirahat-wrap .ji-table thead th{ cursor:pointer; white-space:nowrap; user-select:none; font-size:11.5px; text-transform:uppercase; letter-spacing:.04em; color:#8a92a6; }
.jeda-istirahat-wrap .ji-table thead th.sorted{ color:#556ee6; }
.jeda-istirahat-wrap .ji-table td{ vertical-align:middle; font-size:13px; }
.jeda-istirahat-wrap .ji-table td.mono, .jeda-istirahat-wrap .ji-table .mono{ font-variant-numeric:tabular-nums; }
.jeda-istirahat-wrap .ji-branch-tag{ display:block; font-size:11px; color:#8a92a6; }
.jeda-istirahat-wrap .ji-chip{ display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:600; }
.jeda-istirahat-wrap .ji-chip.r2s{ background:#e3f2ef; color:#2a4c46; }
.jeda-istirahat-wrap .ji-chip.s2r{ background:#f7ecd9; color:#966a1c; }
.jeda-istirahat-wrap .ji-count-pill{ display:inline-flex; align-items:center; justify-content:center; min-width:26px; padding:2px 7px; border-radius:999px; font-size:12px; font-weight:700; background:#f1f3f9; }
.jeda-istirahat-wrap .ji-count-pill.high{ background:#fbe4e4; color:#ae3b3b; }
.jeda-istirahat-wrap .ji-count-pill.mid{ background:#f7ecd9; color:#966a1c; }
.jeda-istirahat-wrap tr.ji-detail-row td{ background:#fafbfe; padding:0; }
.jeda-istirahat-wrap tr.ji-detail-row .ji-detail-inner{ padding:10px 16px 16px 34px; }
.jeda-istirahat-wrap tr.ji-detail-row table{ font-size:12.5px; }
.jeda-istirahat-wrap .ji-empty{ padding:30px 10px; text-align:center; color:#8a92a6; }
.jeda-istirahat-wrap .ji-footnote{ font-size:11.5px; color:#8a92a6; line-height:1.6; margin-top:6px; }
</style>

<script>
(function(){
    var BASE_URL = "<?= site_url('hr/jeda-istirahat/data') ?>";
    var EXPORT_URL = "<?= site_url('hr/jeda-istirahat/export') ?>";
    var DATA = [];
    var $ = function(sel){ return document.querySelector(sel); };

    var rMin = $('#ji-r-min'), rMax = $('#ji-r-max'), rMinVal = $('#ji-rmin-val'), rMaxVal = $('#ji-rmax-val'), trackFill = $('#ji-track-fill');
    var fPola = $('#ji-f-pola'), fNama = $('#ji-f-nama');
    var periodeType = $('#ji-periode-type');

    function fmtTanggalShort(iso){
        var p = iso.split('-'), bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        return parseInt(p[2],10) + ' ' + bulan[parseInt(p[1],10)-1];
    }
    function polaLabel(p){ return p === 'rest_ke_sholat' ? 'Istirahat &rarr; Sholat' : 'Sholat &rarr; Istirahat'; }
    function polaChipClass(p){ return p === 'rest_ke_sholat' ? 'r2s' : 's2r'; }

    /* ---- period field toggling ---- */
    function togglePeriodFields(){
        var t = periodeType.value;
        document.querySelectorAll('.ji-period-field').forEach(function(el){
            var forTypes = el.getAttribute('data-for').split(' ');
            el.style.display = forTypes.indexOf(t) !== -1 ? '' : 'none';
        });
        $('#ji-tanggal-label').textContent = t === 'minggu' ? 'Tanggal (dalam minggu)' : 'Tanggal';
    }
    periodeType.addEventListener('change', togglePeriodFields);
    togglePeriodFields();

    /* ---- dual range slider ---- */
    function updateRangeUI(){
        var lo = parseInt(rMin.value,10), hi = parseInt(rMax.value,10);
        if (lo > hi){ var tmp=lo; lo=hi; hi=tmp; }
        rMinVal.textContent = lo; rMaxVal.textContent = hi;
        var pctLo = (lo/60)*100, pctHi = (hi/60)*100;
        trackFill.style.left = pctLo + '%';
        trackFill.style.width = (pctHi-pctLo) + '%';
    }
    rMin.addEventListener('input', function(){ if(parseInt(rMin.value,10) > parseInt(rMax.value,10)) rMax.value = rMin.value; updateRangeUI(); render(); });
    rMax.addEventListener('input', function(){ if(parseInt(rMax.value,10) < parseInt(rMin.value,10)) rMin.value = rMax.value; updateRangeUI(); render(); });
    fPola.addEventListener('change', render);
    fNama.addEventListener('input', render);

    /* ---- tabs ---- */
    document.querySelectorAll('.ji-tab-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.querySelectorAll('.ji-tab-btn').forEach(function(b){ b.classList.remove('active','btn-primary'); b.classList.add('btn-outline-secondary'); });
            document.querySelectorAll('.ji-tab-panel').forEach(function(p){ p.classList.remove('active'); });
            btn.classList.add('active','btn-primary'); btn.classList.remove('btn-outline-secondary');
            $('#ji-tab-'+btn.getAttribute('data-tab')).classList.add('active');
        });
    });

    /* ---- sorting ---- */
    var sortRekap = { key:'kejadian', dir:-1 };
    var sortDaftar = { key:'tanggal', dir:-1 };
    document.querySelectorAll('#ji-tbl-rekap thead th[data-sort]').forEach(function(th){
        th.addEventListener('click', function(){
            var key = th.getAttribute('data-sort');
            sortRekap.dir = (sortRekap.key===key) ? -sortRekap.dir : (key==='nama'||key.indexOf('tgl')===0 ? 1 : -1);
            sortRekap.key = key; render();
        });
    });
    document.querySelectorAll('#ji-tbl-daftar thead th[data-sort]').forEach(function(th){
        th.addEventListener('click', function(){
            var key = th.getAttribute('data-sort');
            sortDaftar.dir = (sortDaftar.key===key) ? -sortDaftar.dir : (key==='selisih' ? -1 : 1);
            sortDaftar.key = key; render();
        });
    });
    function markSorted(tableSel, state){
        document.querySelectorAll(tableSel+' thead th').forEach(function(th){
            th.classList.remove('sorted');
            if (th.getAttribute('data-sort') === state.key){ th.classList.add('sorted'); }
        });
    }

    /* ---- filtering ---- */
    function currentFilters(){
        var lo = parseInt(rMin.value,10), hi = parseInt(rMax.value,10);
        if (lo > hi){ var tmp=lo; lo=hi; hi=tmp; }
        return { lo:lo, hi:hi, pola:fPola.value, nama:fNama.value.trim().toLowerCase() };
    }
    function filterData(f, applyRange){
        return DATA.filter(function(d){
            if (applyRange && (d.selisih < f.lo || d.selisih > f.hi)) return false;
            if (f.pola !== 'all' && d.pola !== f.pola) return false;
            if (f.nama && d.nama.toLowerCase().indexOf(f.nama) === -1) return false;
            return true;
        });
    }

    /* ---- histogram ---- */
    var hist = $('#ji-hist'), hctx = hist.getContext('2d');
    function drawHistogram(rows, lo, hi){
        var dpr = window.devicePixelRatio || 1;
        var cssW = hist.clientWidth || 1100, cssH = 140;
        hist.width = cssW*dpr; hist.height = cssH*dpr;
        hctx.setTransform(dpr,0,0,dpr,0,0);
        hctx.clearRect(0,0,cssW,cssH);

        var binSize = 2, maxBin = 60, nBins = maxBin/binSize;
        var bins = new Array(nBins).fill(0);
        rows.forEach(function(d){ var idx = Math.min(nBins-1, Math.floor(d.selisih/binSize)); if (idx>=0) bins[idx]++; });
        var maxCount = Math.max.apply(null, [1].concat(bins));

        var padL=34, padB=20, padT=8, padR=8;
        var plotW = cssW-padL-padR, plotH = cssH-padT-padB;
        var barW = plotW/nBins;

        hctx.strokeStyle = '#e3e6ef'; hctx.lineWidth = 1;
        hctx.beginPath(); hctx.moveTo(padL,padT); hctx.lineTo(padL,padT+plotH); hctx.lineTo(padL+plotW,padT+plotH); hctx.stroke();

        var xLo = padL+(lo/maxBin)*plotW, xHi = padL+(hi/maxBin)*plotW;
        hctx.fillStyle = '#e3f2ef';
        hctx.fillRect(xLo, padT, Math.max(1,xHi-xLo), plotH);

        for(var i=0;i<nBins;i++){
            var x = padL+i*barW, h = (bins[i]/maxCount)*(plotH-4);
            var binLo=i*binSize, binHi=binLo+binSize;
            var inRange = binLo < hi && binHi > lo;
            hctx.fillStyle = inRange ? '#3d6b63' : '#eef0f6';
            hctx.fillRect(x+1, padT+plotH-h, Math.max(1,barW-2), h);
        }
        hctx.fillStyle = '#8a92a6'; hctx.font='11px sans-serif'; hctx.textAlign='center'; hctx.textBaseline='top';
        for(var m=0;m<=maxBin;m+=10){ var xx = padL+(m/maxBin)*plotW; hctx.fillText(m+'m', xx, padT+plotH+5); }
        hctx.textAlign='right'; hctx.fillText('n='+rows.length, padL+plotW, padT-2);
    }

    /* ---- grouping / sorting ---- */
    function groupByEmployee(rows){
        var map = {};
        rows.forEach(function(d){
            if (!map[d.nama]) map[d.nama] = { nama:d.nama, cabang:d.cabang, kejadian:0, total:0, tglawal:d.tanggal, tglakhir:d.tanggal, items:[] };
            var g = map[d.nama];
            g.kejadian++; g.total += d.selisih; g.items.push(d);
            if (d.tanggal < g.tglawal) g.tglawal = d.tanggal;
            if (d.tanggal > g.tglakhir) g.tglakhir = d.tanggal;
        });
        return Object.keys(map).map(function(k){ var g = map[k]; g.rata2 = g.total/g.kejadian; return g; });
    }
    function sortRows(rows, state){
        var key = state.key, dir = state.dir;
        return rows.slice().sort(function(a,b){
            var va=a[key], vb=b[key];
            if (typeof va === 'string'){ va=va.toLowerCase(); vb=vb.toLowerCase(); }
            if (va<vb) return -1*dir; if (va>vb) return 1*dir; return 0;
        });
    }

    function renderRekap(rows){
        var grouped = sortRows(groupByEmployee(rows), sortRekap);
        markSorted('#ji-tbl-rekap', sortRekap);
        var tbody = $('#ji-body-rekap');
        if (!grouped.length){ tbody.innerHTML = '<tr><td colspan="6" class="ji-empty">Tidak ada kejadian yang cocok dengan filter ini.</td></tr>'; return; }
        var maxKejadian = Math.max.apply(null, grouped.map(function(g){ return g.kejadian; }));
        tbody.innerHTML = grouped.map(function(g,i){
            var pillClass = g.kejadian >= Math.max(10, maxKejadian*0.7) ? 'high' : (g.kejadian >= Math.max(5, maxKejadian*0.4) ? 'mid' : '');
            var itemsSorted = g.items.slice().sort(function(a,b){ return a.tanggal<b.tanggal?1:-1; });
            var detailRows = itemsSorted.map(function(it){
                return '<tr><td class="mono">'+fmtTanggalShort(it.tanggal)+'</td>'+
                    '<td><span class="ji-chip '+polaChipClass(it.pola)+'">'+polaLabel(it.pola)+'</span></td>'+
                    '<td>'+it.sholat+'</td>'+
                    '<td class="mono">'+it.istirahat_mulai+' &rarr; '+it.istirahat_selesai+'</td>'+
                    '<td class="mono">'+it.sholat_mulai+' &rarr; '+it.sholat_selesai+'</td>'+
                    '<td class="text-end mono">'+it.selisih+'m</td></tr>';
            }).join('');
            return '<tr class="ji-emp-row" data-idx="'+i+'" style="cursor:pointer;">'+
                '<td>'+g.nama+'<span class="ji-branch-tag">'+(g.cabang||'')+'</span></td>'+
                '<td class="text-end"><span class="ji-count-pill '+pillClass+'">'+g.kejadian+'</span></td>'+
                '<td class="text-end mono">'+g.total+'</td>'+
                '<td class="text-end mono">'+g.rata2.toFixed(1)+'</td>'+
                '<td class="mono">'+fmtTanggalShort(g.tglawal)+'</td>'+
                '<td class="mono">'+fmtTanggalShort(g.tglakhir)+'</td></tr>'+
                '<tr class="ji-detail-row" id="ji-detail-'+i+'" style="display:none;"><td colspan="6"><div class="ji-detail-inner">'+
                '<table class="table table-sm mb-0"><thead><tr><th>Tanggal</th><th>Pola</th><th>Sholat</th><th>Istirahat</th><th>Sholat</th><th class="text-end">Selisih</th></tr></thead>'+
                '<tbody>'+detailRows+'</tbody></table></div></td></tr>';
        }).join('');
        tbody.querySelectorAll('tr.ji-emp-row').forEach(function(tr){
            tr.addEventListener('click', function(){
                var d = $('#ji-detail-'+tr.getAttribute('data-idx'));
                d.style.display = d.style.display === 'none' ? 'table-row' : 'none';
            });
        });
    }

    function renderDaftar(rows){
        var sorted = sortRows(rows, sortDaftar);
        markSorted('#ji-tbl-daftar', sortDaftar);
        var tbody = $('#ji-body-daftar');
        if (!sorted.length){ tbody.innerHTML = '<tr><td colspan="8" class="ji-empty">Tidak ada kejadian yang cocok dengan filter ini.</td></tr>'; return; }
        tbody.innerHTML = sorted.map(function(d){
            return '<tr><td class="mono">'+fmtTanggalShort(d.tanggal)+'</td>'+
                '<td>'+d.nama+'</td><td>'+(d.cabang||'')+'</td>'+
                '<td><span class="ji-chip '+polaChipClass(d.pola)+'">'+polaLabel(d.pola)+'</span></td>'+
                '<td>'+d.sholat+'</td>'+
                '<td class="mono">'+d.istirahat_mulai+' &rarr; '+d.istirahat_selesai+'</td>'+
                '<td class="mono">'+d.sholat_mulai+' &rarr; '+d.sholat_selesai+'</td>'+
                '<td class="text-end mono">'+d.selisih+'m</td></tr>';
        }).join('');
    }

    function render(){
        updateRangeUI();
        var f = currentFilters();
        var rowsForHist = filterData(f, false);
        var rowsFiltered = filterData(f, true);
        drawHistogram(rowsForHist, f.lo, f.hi);

        var nEmp = {}, nDays = {}, sum = 0;
        rowsFiltered.forEach(function(d){ nEmp[d.nama]=1; nDays[d.tanggal]=1; sum += d.selisih; });
        var nEmpCount = Object.keys(nEmp).length, nDaysCount = Object.keys(nDays).length;
        var avg = rowsFiltered.length ? (sum/rowsFiltered.length) : 0;

        $('#ji-stat-total').textContent = rowsFiltered.length;
        $('#ji-stat-emp').textContent = nEmpCount;
        $('#ji-stat-avg').textContent = rowsFiltered.length ? avg.toFixed(1) : '—';
        $('#ji-stat-days').textContent = nDaysCount;
        $('#ji-result-hint').textContent = rowsFiltered.length + ' kejadian · jeda ' + f.lo + '–' + f.hi + ' menit';

        renderRekap(rowsFiltered);
        renderDaftar(rowsFiltered);
    }

    /* ---- period params (dipakai fetch & export) ---- */
    function periodParams(){
        var params = { periode_type: periodeType.value, branch_id: $('#ji-branch').value };
        if (periodeType.value === 'gaji'){ params.bulan = $('#ji-bulan').value; params.tahun = $('#ji-tahun').value; }
        else { params.tanggal = $('#ji-tanggal').value; }
        return params;
    }
    function toQuery(params){
        return Object.keys(params).map(function(k){ return encodeURIComponent(k)+'='+encodeURIComponent(params[k]==null?'':params[k]); }).join('&');
    }

    /* ---- export excel (menghormati filter yang tampil) ---- */
    $('#ji-export-btn').addEventListener('click', function(){
        var f = currentFilters();
        var params = periodParams();
        params.gap_min = f.lo;
        params.gap_max = f.hi;
        params.pola = f.pola;
        params.nama = f.nama;
        window.location.href = EXPORT_URL + '?' + toQuery(params);
    });

    /* ---- fetch period from server ---- */
    function fetchPeriod(){
        var params = periodParams();

        $('#ji-loading').style.display = '';
        var qs = toQuery(params);

        fetch(BASE_URL + '?' + qs, { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                $('#ji-loading').style.display = 'none';
                if (!res.status){ DATA = []; $('#ji-period-label').textContent = res.message || 'Gagal memuat data.'; render(); return; }
                DATA = res.data.map(function(d){ d.selisih = parseInt(d.selisih,10); return d; });
                $('#ji-period-label').textContent = res.periode.label + ' (' + res.periode.from + ' s/d ' + res.periode.to + ')';
                $('#ji-stat-total-sub').textContent = res.periode.label;
                $('#ji-stat-emp-sub').textContent = 'dari karyawan aktif';
                $('#ji-stat-days-sub').textContent = '';
                render();
            })
            .catch(function(){
                $('#ji-loading').style.display = 'none';
                $('#ji-period-label').textContent = 'Gagal memuat data dari server.';
            });
    }

    $('#ji-period-form').addEventListener('submit', function(e){ e.preventDefault(); fetchPeriod(); });

    updateRangeUI();
    fetchPeriod();
    window.addEventListener('resize', function(){ var f = currentFilters(); drawHistogram(filterData(f,false), f.lo, f.hi); });
})();
</script>

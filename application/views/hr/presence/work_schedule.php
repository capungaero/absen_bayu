<?php
    $branch_query = $this->role == 'admin' ? '?branch_id='.$branch_id : '';
    $quick_shift = ['P' => '', 'S' => ''];
    $prev_period = date('m/Y', strtotime('-1 month', strtotime($year.'-'.$month.'-16')));
    $next_period = date('m/Y', strtotime('+1 month', strtotime($year.'-'.$month.'-16')));
    $shift_meta = [];
    foreach($shift as $row){
        if(isset($quick_shift[$row['shift_code']])){
            $quick_shift[$row['shift_code']] = $row['id'];
        }
        $shift_meta[$row['id']] = [
            'code' => $row['shift_code'],
            'name' => $row['shift_name'],
            'start' => date('H:i', strtotime($row['start_time'])),
            'end' => date('H:i', strtotime($row['end_time']))
        ];
    }
    $shift_meta['free'] = ['code' => 'OFF', 'name' => 'Libur', 'start' => '-', 'end' => '-'];
?>
<style>
    .schedule-wrapper{ overflow:auto; max-height:70vh; border:1px solid #e9ecef; }
    .schedule-table{ min-width:1200px; margin-bottom:0; }
    .schedule-table th{ position:sticky; top:0; z-index:3; background:#f8f9fa; white-space:nowrap; }
    .schedule-table .sticky-no{ position:sticky; left:0; z-index:2; background:#fff; min-width:60px; }
    .schedule-table .sticky-col{ position:sticky; left:60px; z-index:2; background:#fff; min-width:130px; }
    .schedule-table .sticky-name{ position:sticky; left:190px; z-index:2; background:#fff; min-width:220px; }
    .schedule-table th.sticky-no,.schedule-table th.sticky-col,.schedule-table th.sticky-name{ z-index:4; background:#f8f9fa; }
    .schedule-select{ min-width:88px; }
    .schedule-sort{ cursor:pointer; user-select:none; }
    .schedule-sort .sort-icon{ color:#adb5bd; font-size:10px; margin-left:4px; }
    .schedule-sort.sort-active{ background:#e9f5ff !important; }
    .schedule-sort.sort-active .sort-icon{ color:#0d6efd; }
    #modalVisualSchedule{ overflow:auto; }
    .modal-fullscreen-schedule{ width:calc(100vw - 24px); max-width:none; margin:12px auto; }
    .modal-fullscreen-schedule .modal-content{ min-height:calc(100vh - 24px); max-height:none; }
    .modal-fullscreen-schedule .modal-body{ overflow:visible; padding:14px 16px 22px; }
    .visual-board{ overflow:auto; padding-right:0; padding-bottom:8px; max-height:calc(100vh - 245px); cursor:grab; user-select:none; overscroll-behavior:contain; border:1px solid #eef1f4; border-radius:8px; background:#fbfcfd; }
    .visual-board.dragging{ cursor:grabbing; }
    .visual-board .visual-week-layout{ margin:8px; }
    .visual-zoom-tools{ gap:4px; }
    .visual-zoom-value{ min-width:48px; text-align:center; font-weight:600; color:#495057; }
    .visual-toolbar{ gap:8px; }
    .visual-week-layout{ display:grid; grid-template-columns:54px max-content; gap:8px; align-items:start; width:max-content; min-width:100%; }
    .visual-hour-axis{ height:calc(42px + (var(--total-hours) * var(--hour-height))); border:1px solid #e3e7eb; border-radius:8px; background:#fff; overflow:hidden; margin-top:0; }
    .visual-hour-axis-title{ height:42px; padding:7px 4px; font-size:10px; font-weight:700; text-align:center; color:#6c757d; border-bottom:1px solid #eef1f4; }
    .visual-hour-slot{ height:var(--hour-height); padding:2px 4px; font-size:10px; color:#6c757d; border-bottom:1px solid #f0f2f4; display:flex; align-items:flex-start; justify-content:center; }
    .visual-week-grid{ display:grid; grid-template-columns:repeat(7, max-content); gap:8px; }
    .visual-day-card{ width:max(220px, calc(var(--shift-count) * var(--shift-column-width))); height:calc(42px + (var(--total-hours) * var(--hour-height))); border:1px solid #e3e7eb; border-left:5px solid var(--visual-color); border-radius:8px; background:#fff; overflow:hidden; }
    .visual-day-title{ height:42px; font-size:12px; font-weight:700; padding:7px 8px; color:var(--visual-color); border-bottom:1px solid #eef1f4; white-space:nowrap; }
    .visual-day-timeline{ display:grid; grid-template-columns:repeat(var(--shift-count), var(--shift-column-width)); height:calc(var(--total-hours) * var(--hour-height)); background:repeating-linear-gradient(to bottom, #fff 0, #fff calc(var(--hour-height) - 1px), #eef1f4 calc(var(--hour-height) - 1px), #eef1f4 var(--hour-height)); }
    .visual-shift-column{ position:relative; width:var(--shift-column-width); border-left:1px solid rgba(0,0,0,.08); background:rgba(255,255,255,.35); }
    .visual-shift-row{ position:absolute; left:2px; right:2px; display:flex; flex-direction:column; border:1px solid rgba(0,0,0,.08); border-radius:6px; background:var(--shift-bg); overflow:hidden; box-shadow:0 1px 2px rgba(0,0,0,.04); }
    .visual-shift-label{ font-size:9px; font-weight:700; padding:3px 4px; color:#343a40; background:var(--shift-bg-strong); line-height:11px; }
    .visual-shift-people{ min-height:35px; padding:3px; display:flex; flex-wrap:wrap; gap:2px; align-items:flex-start; align-content:flex-start; overflow:hidden; }
    .visual-person{ width:calc(30px * var(--visual-zoom, 1)); text-align:center; }
    .visual-person-icon{ width:calc(20px * var(--visual-zoom, 1)); height:calc(20px * var(--visual-zoom, 1)); line-height:calc(18px * var(--visual-zoom, 1)); border-radius:50%; margin:0 auto 1px; color:#fff; font-size:calc(9px * var(--visual-zoom, 1)); border:1px solid #cfd6df; }
    .visual-person-name{ font-size:calc(8px * var(--visual-zoom, 1)); line-height:calc(9px * var(--visual-zoom, 1)); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .visual-person-code{ font-size:calc(8px * var(--visual-zoom, 1)); color:#6c757d; line-height:calc(9px * var(--visual-zoom, 1)); }
    .visual-day-detail{ display:grid; grid-template-columns:130px repeat(var(--position-count), minmax(105px, 1fr)); border-left:5px solid var(--visual-color); border-radius:8px; overflow:hidden; }
    .visual-day-detail-header,.visual-day-detail-row{ display:contents; }
    .visual-day-detail-header div{ font-size:11px; font-weight:600; color:#495057; padding:6px; text-align:center; background:#fff; }
    .visual-day-detail-row > div{ border-top:1px solid rgba(0,0,0,.08); background:var(--shift-bg); min-height:58px; }
    .visual-shift-cell{ padding:8px; font-size:11px; color:#343a40; background:var(--shift-bg-strong) !important; }
    .visual-shift-code{ font-weight:700; display:block; }
    .visual-position-cell{ padding:6px; border-left:1px solid rgba(255,255,255,.85); display:flex; align-items:flex-start; justify-content:center; gap:5px; flex-wrap:wrap; }
    .game-scheduler{ border:1px solid #dfe6ee; border-radius:8px; background:#f8fafc; overflow:hidden; }
    .game-scheduler-toolbar{ display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end; padding:12px; border-bottom:1px solid #dfe6ee; background:#fff; }
    .game-shift-palette{ display:flex; gap:6px; overflow:auto; padding:10px 12px; border-bottom:1px solid #e9eef4; background:#fbfcfe; }
    .game-shift-chip{ flex:0 0 auto; border:1px solid #d8e0ea; border-radius:8px; padding:7px 10px; background:#fff; cursor:grab; font-size:12px; font-weight:700; color:#344054; box-shadow:0 1px 2px rgba(16,24,40,.05); }
    .game-shift-chip small{ display:block; font-weight:400; color:#667085; }
    .game-shift-chip:active{ cursor:grabbing; }
    .game-scheduler-body{ display:grid; grid-template-columns:280px minmax(0,1fr); min-height:560px; }
    .game-roster{ border-right:1px solid #dfe6ee; background:#fff; min-height:560px; display:flex; flex-direction:column; }
    .game-roster-head{ padding:12px; border-bottom:1px solid #edf1f5; }
    .game-roster-list{ padding:10px; overflow:auto; max-height:660px; display:flex; flex-direction:column; gap:8px; }
    .game-board{ padding:12px; overflow:auto; min-height:560px; }
    .game-board-grid{ display:block; min-width:900px; }
    .game-time-ruler{ display:grid; grid-template-columns:160px minmax(720px, 1fr); gap:10px; align-items:end; margin-bottom:8px; position:sticky; top:0; z-index:5; background:#f8fafc; padding-bottom:6px; }
    .game-time-ruler-label{ font-size:11px; font-weight:700; color:#667085; padding-left:4px; }
    .game-time-ruler-track{ position:relative; height:34px; border-bottom:2px solid #98a2b3; background:repeating-linear-gradient(to right, transparent 0, transparent calc(var(--hour-width) - 1px), #d0d5dd calc(var(--hour-width) - 1px), #d0d5dd var(--hour-width)); }
    .game-time-ruler-tick{ position:absolute; bottom:0; width:1px; height:11px; background:#667085; }
    .game-time-ruler-tick span{ position:absolute; bottom:13px; left:-18px; width:36px; text-align:center; font-size:10px; color:#475467; }
    .game-timeline-row{ display:grid; grid-template-columns:160px minmax(720px, 1fr); gap:10px; align-items:stretch; margin-bottom:10px; }
    .game-timeline-label{ border:1px solid #d8e0ea; border-radius:8px; background:#fff; padding:9px 10px; min-height:108px; }
    .game-timeline-label strong{ display:block; color:#263238; line-height:16px; }
    .game-timeline-label small{ display:block; color:#56616f; line-height:15px; margin-top:3px; }
    .game-timeline-track{ position:relative; min-height:108px; border:1px solid #d8e0ea; border-radius:8px; background:repeating-linear-gradient(to right, #fff 0, #fff calc(var(--hour-width) - 1px), #edf2f7 calc(var(--hour-width) - 1px), #edf2f7 var(--hour-width)); overflow:hidden; }
    .game-shift-lane{ position:absolute; top:10px; bottom:10px; min-height:88px; border:1px solid #d8e0ea; border-radius:8px; background:var(--lane-color); overflow:hidden; box-shadow:0 1px 3px rgba(16,24,40,.08); }
    .game-shift-lane.drag-over{ outline:3px solid rgba(13,110,253,.25); border-color:#0d6efd; }
    .game-shift-lane.highlight-lane{ box-shadow:0 0 0 3px rgba(13,110,253,.18); }
    .game-shift-lane-head{ min-height:40px; padding:7px 9px; background:rgba(255,255,255,.52); border-bottom:1px solid rgba(0,0,0,.06); }
    .game-shift-lane-head strong{ display:block; color:#263238; line-height:16px; }
    .game-shift-lane-head small{ color:#56616f; }
    .game-shift-lane-people{ min-height:48px; padding:5px; display:flex; align-content:flex-start; align-items:flex-start; flex-wrap:wrap; gap:4px; overflow:hidden; max-height:calc(100% - 40px); }
    .game-empty-lane{ color:#98a2b3; font-size:12px; padding:10px; }
    .game-employee-card{ width:122px; min-height:112px; border:1px solid #d6dde7; border-radius:8px; background:#fff; padding:8px; cursor:grab; text-align:center; position:relative; box-shadow:0 1px 2px rgba(16,24,40,.05); }
    .game-employee-card.game-employee-compact{ width:var(--compact-size, 30px); min-height:var(--compact-size, 30px); height:var(--compact-size, 30px); padding:2px; border-radius:999px; display:flex; align-items:center; justify-content:center; }
    .game-employee-card.game-employee-compact .game-employee-photo{ width:calc(var(--compact-size, 30px) - 6px); height:calc(var(--compact-size, 30px) - 6px); margin:0; }
    .game-employee-card.game-employee-compact .game-employee-name,
    .game-employee-card.game-employee-compact .game-employee-meta{ display:none; }
    .game-employee-card.game-employee-compact .game-status-dot{ top:0; right:0; width:8px; height:8px; border-width:1px; }
    .game-overflow-count{ align-self:center; height:24px; min-width:24px; border-radius:999px; padding:4px 6px; font-size:10px; font-weight:700; color:#475467; background:#fff; border:1px solid #d0d5dd; }
    .game-employee-card:active{ cursor:grabbing; }
    .game-employee-card.status-present{ background:#dcfce7; border-color:#22c55e; }
    .game-employee-card.status-late{ background:#fef3c7; border-color:#f59e0b; }
    .game-employee-card.status-absent{ background:#fee2e2; border-color:#ef4444; }
    .game-employee-card.status-sick,.game-employee-card.status-permit{ background:#e0f2fe; border-color:#0ea5e9; }
    .game-employee-photo{ width:44px; height:44px; border-radius:50%; object-fit:cover; border:2px solid #fff; box-shadow:0 0 0 1px rgba(0,0,0,.12); }
    .game-employee-name{ margin-top:5px; font-size:11px; font-weight:700; color:#253041; line-height:13px; height:27px; overflow:hidden; }
    .game-employee-meta{ font-size:10px; color:#667085; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .game-status-dot{ position:absolute; top:7px; right:7px; width:12px; height:12px; border-radius:50%; background:#ef4444; border:2px solid #fff; }
    .game-employee-card.status-present .game-status-dot{ background:#22c55e; }
    .game-employee-card.status-late .game-status-dot{ background:#f59e0b; }
    .game-employee-card.status-sick .game-status-dot,.game-employee-card.status-permit .game-status-dot{ background:#0ea5e9; }
    .game-off-zone{ border:1px dashed #cbd5e1; border-radius:8px; background:#f8fafc; padding:10px; color:#667085; font-size:12px; text-align:center; margin-top:10px; min-height:48px; }
    .game-off-zone.drag-over{ border-color:#6c757d; background:#eef2f7; }
    .game-legend{ display:flex; flex-wrap:wrap; gap:8px; font-size:12px; color:#475467; }
    .game-legend span{ display:inline-flex; align-items:center; gap:5px; }
    .game-legend i{ width:11px; height:11px; border-radius:50%; display:inline-block; }
    @media (max-width: 992px){ .game-scheduler-body{ grid-template-columns:1fr; } .game-roster{ border-right:0; border-bottom:1px solid #dfe6ee; } .game-roster-list{ max-height:260px; } }
    @media (max-width: 1200px){ .visual-week-layout{ min-width:100%; } .visual-week-grid{ grid-template-columns:repeat(7, max-content); } }
</style>

<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="dripicons-calendar"></i> Atur Jadwal Kerja Manual</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="<?= site_url('hr/presence') ?>">Presensi</a></li>
                    <li class="breadcrumb-item active">Jadwal Kerja</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="card-title mb-1">Jadwal <?= get_monthname($month).' '.$year ?></h6>
                    <small class="text-muted">Periode payroll <?= $daterange['list'][0] ?> s/d <?= end($daterange['list']) ?></small>
                </div>
                <div>
                    <a href="<?= site_url('hr/presence/'.$month.'/'.$year.$branch_query) ?>" class="btn btn-light"><i class="fa fa-arrow-left"></i> Kembali</a>
                    <button type="button" class="btn btn-outline-success" id="btnShowUploadSchedule"><i class="fa fa-upload"></i> Upload</button>
                    <a href="<?= site_url('export_work_schedule/'.$month.'/'.$year.'/'.$branch_id) ?>" class="btn btn-outline-danger"><i class="fa fa-download"></i> Template Excel</a>
                    <button type="button" class="btn btn-outline-primary" id="btnVisualSchedule"><i class="fa fa-users"></i> Jadwal Visual</button>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <?php if(isset($branch) && !empty($branch)){ ?>
                            <form method="GET" id="formBranchFilter">
                                <label>Cabang</label>
                                <select name="branch_id" class="form-control" onchange="this.form.submit()">
                                    <?php foreach($branch as $row){ ?>
                                        <option value="<?= $row['id'] ?>" <?= $row['id'] == $branch_id ? 'selected' : '' ?>><?= $row['branch_name'] ?></option>
                                    <?php } ?>
                                </select>
                            </form>
                        <?php } ?>
                    </div>
                    <div class="col-md-8 text-right">
                        <label class="d-block">Periode</label>
                        <a href="<?= site_url('hr/work-schedule/'.$prev_period.$branch_query) ?>" class="btn btn-light"><i class="fa fa-chevron-left"></i> Back</a>
                        <a href="<?= site_url('hr/work-schedule/'.$next_period.$branch_query) ?>" class="btn btn-light">Next <i class="fa fa-chevron-right"></i></a>
                    </div>
                </div>

                <div class="alert alert-info">
                    Pilih jadwal per karyawan dan tanggal. Default pilihan hanya <b>P</b>, <b>S</b>, dan <b>OFF</b>. Centang <b>Advanced</b> untuk menampilkan semua shift dari master shift cabang.
                </div>

                <div class="game-scheduler mb-4" id="gameScheduler">
                    <div class="game-scheduler-toolbar">
                        <div>
                            <label>Tanggal Papan</label>
                            <select class="form-control form-control-sm" id="gameScheduleDate">
                                <?php foreach($daterange['list'] as $date){ ?>
                                    <option value="<?= $date ?>"><?= get_dayname($date) ?> - <?= date('d/m/Y', strtotime($date)) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div>
                            <label>Cari Karyawan</label>
                            <input type="text" class="form-control form-control-sm" id="gameEmployeeSearch" placeholder="Nama / ID / posisi">
                        </div>
                        <div>
                            <label>Filter Shift</label>
                            <select class="form-control form-control-sm" id="gameShiftFilter">
                                <option value="">Semua shift</option>
                                <?php foreach($shift as $row){ ?>
                                    <option value="<?= $row['id'] ?>"><?= $row['shift_code'] ?> - <?= $row['shift_name'] ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="ml-auto">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnRenderGameBoard"><i class="fa fa-sync"></i> Load Jadwal</button>
                            <button type="submit" form="formManualWorkSchedule" class="btn btn-sm btn-success"><i class="fa fa-save"></i> Simpan Jadwal</button>
                            <a href="<?= site_url('hr/shift') ?>" class="btn btn-sm btn-outline-secondary"><i class="fa fa-plus"></i> Buat Shift</a>
                        </div>
                    </div>
                    <div class="game-shift-palette" id="gameShiftPalette">
                        <?php foreach($shift as $row){ ?>
                            <div class="game-shift-chip" draggable="true" data-shift-id="<?= $row['id'] ?>">
                                <?= htmlspecialchars($row['shift_code']) ?>
                                <small><?= htmlspecialchars($row['shift_name']) ?> <?= date('H:i', strtotime($row['start_time'])) ?>-<?= date('H:i', strtotime($row['end_time'])) ?></small>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="game-scheduler-body">
                        <aside class="game-roster">
                            <div class="game-roster-head">
                                <strong>Roster Karyawan</strong>
                                <div class="small text-muted">Drag foto/icon karyawan ke shift. Drop ke OFF untuk menghapus jadwal hari itu.</div>
                                <div class="game-off-zone" id="gameOffZone">Drop ke sini untuk OFF / tidak dijadwalkan</div>
                            </div>
                            <div class="game-roster-list" id="gameRosterList"></div>
                        </aside>
                        <section class="game-board">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <strong id="gameBoardTitle">Papan Jadwal</strong>
                                    <div class="small text-muted">Hijau: hadir tepat waktu, kuning: terlambat, merah: absen.</div>
                                </div>
                                <div class="game-legend">
                                    <span><i style="background:#22c55e"></i> Tepat waktu</span>
                                    <span><i style="background:#f59e0b"></i> Terlambat</span>
                                    <span><i style="background:#ef4444"></i> Absen</span>
                                </div>
                            </div>
                            <div class="game-board-grid" id="gameBoardGrid">
                                <div class="alert alert-info mb-0">Klik <b>Load Jadwal</b> untuk menampilkan timeline visual.</div>
                            </div>
                        </section>
                    </div>
                </div>


                <form id="formLoadWorkScheduleExcel" enctype="multipart/form-data" class="d-none">
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
                    <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
                    <input type="hidden" name="month" value="<?= $month ?>">
                    <input type="hidden" name="year" value="<?= $year ?>">
                    <input type="file" name="excel_file" id="excelScheduleFile" accept=".xls,.xlsx,.csv">
                </form>

                <form method="POST" action="<?= site_url('save_work_schedule_manual') ?>" id="formManualWorkSchedule">
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
                    <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
                    <input type="hidden" name="month" value="<?= $month ?>">
                    <input type="hidden" name="year" value="<?= $year ?>">

                    <div class="mb-3 d-flex align-items-center flex-wrap" style="gap: 8px;">
                        <button type="button" class="btn btn-outline-success btn-sm set-all-shift" data-value="<?= $quick_shift['P'] ?>" <?= $quick_shift['P'] == '' ? 'disabled' : '' ?>>Isi Semua P</button>
                        <button type="button" class="btn btn-outline-warning btn-sm set-all-shift" data-value="<?= $quick_shift['S'] ?>" <?= $quick_shift['S'] == '' ? 'disabled' : '' ?>>Isi Semua S</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm set-all-shift" data-value="free">Isi Semua OFF</button>
                        <button type="button" class="btn btn-outline-primary btn-sm set-all-shift" data-value="">Kosongkan Semua</button>
                        <button type="button" class="btn btn-outline-info btn-sm" id="btnCopyPreviousSchedule"><i class="fa fa-copy"></i> Copy Periode Sebelumnya</button>
                        <div class="custom-control custom-checkbox ml-2">
                            <input type="checkbox" class="custom-control-input" id="advancedShiftToggle">
                            <label class="custom-control-label" for="advancedShiftToggle">Advanced</label>
                        </div>
                        <small class="text-muted">Tampilkan semua pilihan shift</small>
                    </div>

                    <div class="schedule-wrapper">
                        <table class="table table-bordered table-sm schedule-table">
                            <thead>
                                <tr>
                                    <th class="sticky-no text-center">No</th>
                                    <th class="sticky-col schedule-sort" data-sort-type="text" data-sort-index="1">ID <span class="sort-icon">&#8645;</span></th>
                                    <th class="sticky-name schedule-sort" data-sort-type="text" data-sort-index="2">Nama <span class="sort-icon">&#8645;</span></th>
                                    <th class="schedule-sort" data-sort-type="text" data-sort-index="3">Posisi <span class="sort-icon">&#8645;</span></th>
                                    <?php foreach($daterange['list'] as $date_index => $date){ ?>
                                        <th class="text-center schedule-sort" data-sort-type="shift" data-sort-index="<?= $date_index + 4 ?>"><?= date('d/m', strtotime($date)) ?> <span class="sort-icon">&#8645;</span><br><small><?= get_dayname($date) ?></small></th>
                                    <?php } ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 0; foreach($employees as $employee){ $no++; ?>
                                    <tr data-employee-id="<?= $employee['id'] ?>" data-employee-code="<?= $employee['employee_code'] ?>" data-employee-name="<?= htmlspecialchars($employee['first_name'], ENT_QUOTES, 'UTF-8') ?>" data-position="<?= htmlspecialchars($employee['position_name'], ENT_QUOTES, 'UTF-8') ?>" data-division="<?= htmlspecialchars($employee['subdivision_name'] ?: $employee['position_name'], ENT_QUOTES, 'UTF-8') ?>" data-photo="<?= base_url('assets/images/users/'.($employee['photo'] ?: 'default_photo.jpg')) ?>">
                                        <td class="sticky-no text-center"><?= $no ?></td>
                                        <td class="sticky-col"><?= $employee['employee_code'] ?></td>
                                        <td class="sticky-name"><?= $employee['first_name'] ?></td>
                                        <td><?= $employee['position_name'] ?></td>
                                        <?php foreach($daterange['list'] as $date){
                                            $selected = isset($schedule_map[$employee['id']][$date]) ? $schedule_map[$employee['id']][$date] : '';
                                        ?>
                                            <td>
                                                <select class="form-control form-control-sm schedule-select" name="schedule[<?= $employee['id'] ?>][<?= $date ?>]">
                                                    <option value="">-</option>
                                                    <option value="free" <?= $selected == 'free' ? 'selected' : '' ?>>OFF</option>
                                                    <?php foreach($shift as $row){
                                                        $is_basic_shift = in_array($row['shift_code'], ['P', 'S']);
                                                        $is_selected_shift = (string)$selected == (string)$row['id'];
                                                        $advanced_class = !$is_basic_shift ? 'advanced-shift-option' : '';
                                                    ?>
                                                        <option class="<?= $advanced_class ?>" data-advanced="<?= !$is_basic_shift ? '1' : '0' ?>" value="<?= $row['id'] ?>" <?= $is_selected_shift ? 'selected' : '' ?>><?= $row['shift_code'] ?></option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex justify-content-between">
                        <small class="text-muted">Total karyawan: <?= count($employees) ?>. Jadwal kosong akan menghapus jadwal pada tanggal tersebut.</small>
                        <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Simpan Jadwal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVisualSchedule" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-schedule" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-users"></i> Jadwal Visual</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-1">Weekly Shift Schedule</h5>
                        <p class="text-muted mb-0">Ringkas untuk gambaran umum. Pilih harian untuk detail posisi, atau mingguan untuk overview 7 hari.</p>
                    </div>
                    <div class="text-right">
                        <small class="text-muted">Periode</small><br>
                        <strong><?= $daterange['list'][0] ?> s/d <?= end($daterange['list']) ?></strong>
                    </div>
                </div>
                <div class="d-flex align-items-end flex-wrap visual-toolbar mb-3">
                    <div>
                        <label>Mode</label>
                        <select class="form-control form-control-sm" id="visualScheduleMode">
                            <option value="week">Per Minggu</option>
                            <option value="day">Per Hari</option>
                        </select>
                    </div>
                    <div>
                        <label>Minggu</label>
                        <select class="form-control form-control-sm" id="visualScheduleWeek"></select>
                    </div>
                    <div id="visualDayFilterWrapper" style="display:none">
                        <label>Hari</label>
                        <select class="form-control form-control-sm" id="visualScheduleDay"></select>
                    </div>
                    <div>
                        <label>Zoom</label>
                        <div class="btn-group btn-group-sm visual-zoom-tools" role="group">
                            <button type="button" class="btn btn-outline-secondary" id="visualZoomOut" title="Perkecil">-</button>
                            <button type="button" class="btn btn-light visual-zoom-value" id="visualZoomValue" disabled>100%</button>
                            <button type="button" class="btn btn-outline-secondary" id="visualZoomIn" title="Perbesar">+</button>
                            <button type="button" class="btn btn-outline-primary" id="visualZoomReset" title="Reset zoom dan posisi">Reset</button>
                        </div>
                    </div>
                    <small class="text-muted mb-2">Geser area jadwal dengan klik-tahan lalu tarik.</small>
                </div>
                <div id="visualScheduleContent" class="visual-board"></div>
            </div>
        </div>
    </div>
</div>

<script>


var activeSort = { index: null, direction: 'asc' };
var shiftMeta = <?= json_encode($shift_meta) ?>;
var presenceMap = <?= json_encode($presence_map) ?>;
var visualColors = ['#0d6efd', '#198754', '#fd7e14', '#6f42c1', '#20c997', '#dc3545', '#0dcaf0', '#6610f2'];
var divisionColorMap = {};
var visualZoom = 1;
var shiftBgColors = [
    {soft:'rgba(13,110,253,.08)', strong:'rgba(13,110,253,.16)'},
    {soft:'rgba(25,135,84,.08)', strong:'rgba(25,135,84,.16)'},
    {soft:'rgba(253,126,20,.09)', strong:'rgba(253,126,20,.18)'},
    {soft:'rgba(111,66,193,.08)', strong:'rgba(111,66,193,.16)'},
    {soft:'rgba(32,201,151,.08)', strong:'rgba(32,201,151,.16)'},
    {soft:'rgba(220,53,69,.08)', strong:'rgba(220,53,69,.16)'}
];

function normalizeSortText(text){
    return $.trim((text || '').toString()).toLowerCase();
}

function getShiftSortValue(row, index){
    var select = $(row).children('td').eq(index).find('select');
    var label = normalizeSortText(select.find('option:selected').text());
    var order = {
        'p': 1,
        's': 2,
        'off': 3,
        '-': 4,
        '': 4
    };
    return typeof order[label] !== 'undefined' ? order[label] : 10 + label.charCodeAt(0);
}

function refreshRowNumbers(){
    $('.schedule-table tbody tr').each(function(index){
        $(this).children('td').eq(0).text(index + 1);
    });
}

function setSortIcon(header, direction){
    $('.schedule-sort').removeClass('sort-active').find('.sort-icon').html('&#8645;');
    header.addClass('sort-active').find('.sort-icon').html(direction == 'asc' ? '&#8593;' : '&#8595;');
}

$(document).on('click', '.schedule-sort', function(){
    var header = $(this);
    var index = parseInt(header.data('sort-index'), 10);
    var type = header.data('sort-type');
    var direction = activeSort.index == index && activeSort.direction == 'asc' ? 'desc' : 'asc';
    var rows = $('.schedule-table tbody tr').get();

    rows.sort(function(a, b){
        var valueA;
        var valueB;
        if(type == 'shift'){
            valueA = getShiftSortValue(a, index);
            valueB = getShiftSortValue(b, index);
        }else{
            valueA = normalizeSortText($(a).children('td').eq(index).text());
            valueB = normalizeSortText($(b).children('td').eq(index).text());
            if(index == 1){
                var numberA = parseFloat(valueA);
                var numberB = parseFloat(valueB);
                if(!isNaN(numberA) && !isNaN(numberB)){
                    valueA = numberA;
                    valueB = numberB;
                }
            }
        }

        if(valueA < valueB){ return direction == 'asc' ? -1 : 1; }
        if(valueA > valueB){ return direction == 'asc' ? 1 : -1; }
        return 0;
    });

    $.each(rows, function(_, row){
        $('.schedule-table tbody').append(row);
    });

    activeSort = { index: index, direction: direction };
    setSortIcon(header, direction);
    refreshRowNumbers();
});


function escapeHtml(text){
    return $('<div>').text(text || '').html();
}

function getGameEmployees(){
    var employees = [];
    $('.schedule-table tbody tr').each(function(){
        var row = $(this);
        employees.push({
            id: String(row.data('employee-id')),
            code: String(row.data('employee-code')),
            name: String(row.data('employee-name') || ''),
            position: String(row.data('position') || '-'),
            division: String(row.data('division') || '-'),
            photo: String(row.data('photo') || '')
        });
    });
    return employees;
}

function getScheduleSelect(userId, date){
    return $('[name="schedule[' + userId + '][' + date + ']"]');
}

function getEmployeeShiftForDate(userId, date){
    var select = getScheduleSelect(userId, date);
    return select.length ? String(select.val() || '') : '';
}

function setEmployeeShiftForDate(userId, date, shiftValue){
    var select = getScheduleSelect(userId, date);
    if(select.length == 0){ return; }
    if(shiftValue && shiftValue !== 'free' && select.find('option[value="' + shiftValue + '"][data-advanced="1"]').length > 0){
        $('#advancedShiftToggle').prop('checked', true);
        toggleAdvancedShiftOptions();
    }
    select.val(shiftValue);
    select.trigger('change');
}

function getGamePresenceStatus(userId, date, shiftValue){
    if(!shiftValue || shiftValue == 'free'){ return 'none'; }
    var presence = presenceMap[userId] && presenceMap[userId][date] ? presenceMap[userId][date] : null;
    if(!presence){ return 'absent'; }
    if(presence.status == 'late'){ return 'late'; }
    if(presence.status == 'present'){ return 'present'; }
    if(presence.status == 'sick'){ return 'sick'; }
    if(presence.status == 'permit'){ return 'permit'; }
    return 'absent';
}

function renderGameEmployeeCard(employee, date, shiftValue, source, compactSize){
    var status = getGamePresenceStatus(employee.id, date, shiftValue);
    var title = employee.name + ' - ' + employee.position;
    if(status == 'late' && presenceMap[employee.id] && presenceMap[employee.id][date]){
        title += ' | Terlambat ' + presenceMap[employee.id][date].entry_time_late + ' menit';
    }
    var compactClass = compactSize ? ' game-employee-compact' : '';
    var style = compactSize ? ' style="--compact-size:' + compactSize + 'px"' : '';
    var html = '';
    html += '<div class="game-employee-card status-' + status + compactClass + '" draggable="true" data-source="' + escapeHtml(source || 'roster') + '" data-user-id="' + escapeHtml(employee.id) + '" title="' + escapeHtml(title) + '"' + style + '>';
    html += '<span class="game-status-dot"></span>';
    html += '<img class="game-employee-photo" loading="lazy" src="' + escapeHtml(employee.photo) + '" alt="">';
    html += '<div class="game-employee-name">' + escapeHtml(employee.name) + '</div>';
    html += '<div class="game-employee-meta">' + escapeHtml(employee.code) + ' · ' + escapeHtml(employee.position) + '</div>';
    html += '</div>';
    return html;
}

function renderGameRoster(){
    var date = $('#gameScheduleDate').val();
    var query = normalizeSortText($('#gameEmployeeSearch').val());
    var employees = getGameEmployees();
    var html = '';

    $.each(employees, function(_, employee){
        var haystack = normalizeSortText(employee.name + ' ' + employee.code + ' ' + employee.position + ' ' + employee.division);
        if(query && haystack.indexOf(query) < 0){ return; }
        html += renderGameEmployeeCard(employee, date, getEmployeeShiftForDate(employee.id, date), 'roster', null);
    });

    $('#gameRosterList').html(html || '<div class="text-muted small p-2">Tidak ada karyawan yang cocok.</div>');
}

function getGameShiftKeys(shiftFilter){
    var keys = [];
    $.each(shiftMeta, function(shiftValue, meta){
        if(shiftValue == 'free'){ return; }
        if(meta && (meta.code == '-' || normalizeSortText(meta.name) == 'no schedule')){ return; }
        shiftValue = String(shiftValue);
        if(shiftFilter && shiftFilter != shiftValue){ return; }
        keys.push(shiftValue);
    });
    return sortShiftKeys(keys);
}

function getGameTimelineRange(shiftKeys){
    var minHour = 24;
    var maxHour = 0;
    $.each(shiftKeys, function(_, shiftValue){
        var meta = shiftMeta[shiftValue] || {};
        var start = timeToMinutes(meta.start);
        var end = timeToMinutes(meta.end);
        if(start === null || end === null){ return; }
        if(end <= start){ end += 24 * 60; }
        minHour = Math.min(minHour, Math.floor(start / 60));
        maxHour = Math.max(maxHour, Math.ceil(end / 60));
    });
    if(minHour == 24 && maxHour == 0){
        minHour = 7;
        maxHour = 22;
    }
    return {start: Math.max(0, minHour), end: Math.min(30, maxHour)};
}

function renderGameTimeRuler(range, hourWidth){
    var totalHours = Math.max(1, range.end - range.start);
    var html = '<div class="game-time-ruler" style="--hour-width:' + hourWidth + 'px">';
    html += '<div class="game-time-ruler-label">Penggaris waktu</div>';
    html += '<div class="game-time-ruler-track" style="width:' + (totalHours * hourWidth) + 'px">';
    for(var hour = range.start; hour <= range.end; hour++){
        var left = (hour - range.start) * hourWidth;
        var labelHour = hour % 24;
        html += '<div class="game-time-ruler-tick" style="left:' + left + 'px"><span>' + (labelHour < 10 ? '0' : '') + labelHour + ':00</span></div>';
    }
    html += '</div></div>';
    return html;
}

function renderGameBoard(){
    var date = $('#gameScheduleDate').val();
    var shiftFilter = String($('#gameShiftFilter').val() || '');
    var employees = getGameEmployees();
    var html = '';
    var shiftKeys = getGameShiftKeys(shiftFilter);
    var range = getGameTimelineRange(shiftKeys);
    var hourWidth = 96;
    var totalHours = Math.max(1, range.end - range.start);

    $('#gameBoardTitle').text('Papan Jadwal - ' + ($('#gameScheduleDate option:selected').text() || date));

    html += renderGameTimeRuler(range, hourWidth);
    $.each(shiftKeys, function(index, shiftValue){
        var meta = shiftMeta[shiftValue] || {code: shiftValue, name: shiftValue, start: '-', end: '-'};
        var bg = getShiftBackground(index);
        var startMinutes = timeToMinutes(meta.start);
        var endMinutes = timeToMinutes(meta.end);
        if(startMinutes === null || endMinutes === null){
            startMinutes = range.start * 60;
            endMinutes = startMinutes + 60;
        }
        if(endMinutes <= startMinutes){ endMinutes += 24 * 60; }
        var left = Math.max(0, ((startMinutes - (range.start * 60)) / 60) * hourWidth);
        var width = Math.max(140, ((endMinutes - startMinutes) / 60) * hourWidth);

        html += '<div class="game-timeline-row" style="--hour-width:' + hourWidth + 'px">';
        html += '<div class="game-timeline-label">';
        html += '<strong>' + escapeHtml(meta.code) + ' - ' + escapeHtml(meta.name) + '</strong>';
        html += '<small>' + escapeHtml(meta.start) + ' - ' + escapeHtml(meta.end) + '</small>';
        html += '<small>Durasi ' + Math.round(((endMinutes - startMinutes) / 60) * 10) / 10 + ' jam</small>';
        html += '</div>';
        html += '<div class="game-timeline-track" style="width:' + (totalHours * hourWidth) + 'px">';
        html += '<div class="game-shift-lane" data-shift-id="' + escapeHtml(shiftValue) + '" style="--lane-color:' + bg.soft + '; left:' + left + 'px; width:' + width + 'px">';
        html += '<div class="game-shift-lane-head">';
        html += '<strong>' + escapeHtml(meta.code) + '</strong>';
        html += '<small>' + escapeHtml(meta.start) + ' - ' + escapeHtml(meta.end) + '</small>';
        html += '</div><div class="game-shift-lane-people">';

        var people = [];
        $.each(employees, function(_, employee){
            if(getEmployeeShiftForDate(employee.id, date) != shiftValue){ return; }
            people.push(employee);
        });

        var compactSize = width < 220 ? 22 : (people.length > 18 ? 22 : (people.length > 10 ? 26 : 30));
        var maxVisible = Math.max(1, Math.floor((Math.max(80, width - 12) / (compactSize + 4)) * 2));
        $.each(people.slice(0, maxVisible), function(_, employee){
            html += renderGameEmployeeCard(employee, date, shiftValue, 'board', compactSize);
        });
        if(people.length > maxVisible){
            html += '<span class="game-overflow-count">+' + (people.length - maxVisible) + '</span>';
        }

        if(people.length == 0){
            html += '<div class="game-empty-lane">Drop karyawan ke sini</div>';
        }
        html += '</div></div></div></div>';
    });

    $('#gameBoardGrid').html(shiftKeys.length ? html : '<div class="alert alert-warning mb-0">Tidak ada shift aktif untuk filter ini.</div>');
    renderGameRoster();
    $('#gameScheduler').data('loaded', 1);
}

function refreshGameBoard(){
    renderGameBoard();
}

$(document).on('dragstart', '.game-employee-card', function(event){
    event.originalEvent.dataTransfer.setData('text/plain', JSON.stringify({
        type: 'employee',
        userId: String($(this).data('user-id'))
    }));
});

$(document).on('dragstart', '.game-shift-chip', function(event){
    event.originalEvent.dataTransfer.setData('text/plain', JSON.stringify({
        type: 'shift',
        shiftId: String($(this).data('shift-id'))
    }));
});

$(document).on('dragover', '.game-shift-lane, #gameOffZone, .game-board', function(event){
    event.preventDefault();
    $(this).addClass('drag-over');
});

$(document).on('dragleave drop', '.game-shift-lane, #gameOffZone, .game-board', function(){
    $(this).removeClass('drag-over');
});

$(document).on('drop', '.game-shift-lane', function(event){
    event.preventDefault();
    var payload;
    try { payload = JSON.parse(event.originalEvent.dataTransfer.getData('text/plain')); } catch(e){ return; }
    var date = $('#gameScheduleDate').val();
    var shiftValue = String($(this).data('shift-id'));
    if(payload.type == 'employee'){
        setEmployeeShiftForDate(payload.userId, date, shiftValue);
        refreshGameBoard();
    }
});

$(document).on('drop', '#gameOffZone', function(event){
    event.preventDefault();
    var payload;
    try { payload = JSON.parse(event.originalEvent.dataTransfer.getData('text/plain')); } catch(e){ return; }
    if(payload.type == 'employee'){
        setEmployeeShiftForDate(payload.userId, $('#gameScheduleDate').val(), 'free');
        refreshGameBoard();
    }
});

$(document).on('drop', '.game-board', function(event){
    var payload;
    try { payload = JSON.parse(event.originalEvent.dataTransfer.getData('text/plain')); } catch(e){ return; }
    if(payload.type == 'shift'){
        $('#gameShiftFilter').val(payload.shiftId);
        refreshGameBoard();
        $('.game-shift-lane[data-shift-id="' + payload.shiftId + '"]').addClass('highlight-lane');
        setTimeout(function(){ $('.game-shift-lane').removeClass('highlight-lane'); }, 900);
    }
});

$(document).on('change', '#gameScheduleDate, #gameShiftFilter', refreshGameBoard);
$(document).on('keyup', '#gameEmployeeSearch', function(){
    if($('#gameScheduler').data('loaded')){
        renderGameRoster();
    }
});
$(document).on('click', '#btnRenderGameBoard', refreshGameBoard);

function getPositionIcon(position){
    var text = normalizeSortText(position);
    if(text.indexOf('admin') >= 0){ return 'fa-user-tie'; }
    if(text.indexOf('kasir') >= 0 || text.indexOf('cashier') >= 0){ return 'fa-cash-register'; }
    if(text.indexOf('security') >= 0 || text.indexOf('satpam') >= 0){ return 'fa-shield-alt'; }
    if(text.indexOf('gudang') >= 0 || text.indexOf('warehouse') >= 0){ return 'fa-box'; }
    if(text.indexOf('driver') >= 0 || text.indexOf('supir') >= 0){ return 'fa-car'; }
    if(text.indexOf('sales') >= 0){ return 'fa-handshake'; }
    return 'fa-user';
}

function getVisualDates(){
    var dates = [];
    $('.schedule-table thead th[data-sort-type="shift"]').each(function(){
        var index = parseInt($(this).data('sort-index'), 10);
        var firstSelect = $('.schedule-table tbody tr:first').children('td').eq(index).find('select');
        var name = firstSelect.attr('name') || '';
        var match = name.match(/\[(\d{4}-\d{2}-\d{2})\]$/);
        if(match){
            dates.push({date: match[1], label: $(this).clone().children().remove().end().text().trim(), day: $(this).find('small').text()});
        }
    });
    return dates;
}

function collectVisualScheduleByDate(date){
    var result = {};
    $('.schedule-table tbody tr').each(function(){
        var row = $(this);
        var select = row.find('[name$="[' + date + ']"]');
        if(select.length == 0){ return; }
        var shiftValue = select.val();
        if(shiftValue == ''){ return; }
        var position = row.data('position') || '-';
        if(typeof result[shiftValue] === 'undefined'){
            result[shiftValue] = {};
        }
        if(typeof result[shiftValue][position] === 'undefined'){
            result[shiftValue][position] = [];
        }
        result[shiftValue][position].push({
            code: row.data('employee-code'),
            name: row.data('employee-name'),
            position: position,
            division: row.data('division') || position
        });
    });
    return result;
}

function sortShiftKeys(keys){
    return keys.sort(function(a, b){
        var order = {'P': 1, 'S': 2, 'OFF': 3};
        var codeA = shiftMeta[a] ? shiftMeta[a].code : a;
        var codeB = shiftMeta[b] ? shiftMeta[b].code : b;
        var valueA = typeof order[codeA] !== 'undefined' ? order[codeA] : 10 + codeA.charCodeAt(0);
        var valueB = typeof order[codeB] !== 'undefined' ? order[codeB] : 10 + codeB.charCodeAt(0);
        return valueA - valueB;
    });
}

function buildPositionList(dayData){
    var positions = {};
    $.each(dayData, function(_, groups){
        $.each(groups, function(position){
            positions[position] = position;
        });
    });
    return Object.keys(positions).sort();
}

function buildPositionListForShifts(dayData, shiftKeys){
    var positions = {};
    $.each(shiftKeys, function(_, shiftValue){
        $.each(dayData[shiftValue] || {}, function(position){
            positions[position] = position;
        });
    });
    return Object.keys(positions).sort();
}

function getDivisionColor(division){
    division = division || '-';
    if(typeof divisionColorMap[division] === 'undefined'){
        divisionColorMap[division] = visualColors[Object.keys(divisionColorMap).length % visualColors.length];
    }
    return divisionColorMap[division];
}

function isOffShift(shiftValue){
    var meta = shiftMeta[shiftValue];
    return shiftValue == 'free' || (meta && meta.code == 'OFF');
}

function getShiftBackground(shiftIndex){
    return shiftBgColors[shiftIndex % shiftBgColors.length];
}

function timeToMinutes(time){
    if(!time || time == '-'){ return null; }
    var parts = time.split(':');
    if(parts.length < 2){ return null; }
    return (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
}

function getShiftDurationHours(meta){
    var start = timeToMinutes(meta.start);
    var end = timeToMinutes(meta.end);
    if(start === null || end === null){ return 1; }
    if(end <= start){ end += 24 * 60; }
    return Math.max(1, Math.ceil((end - start) / 60));
}

function getWeekHourRange(week){
    var minHour = 24;
    var maxHour = 0;
    $.each(week, function(_, dateInfo){
        var dayData = collectVisualScheduleByDate(dateInfo.date);
        var shiftKeys = sortShiftKeys(Object.keys(dayData)).filter(function(shiftValue){ return !isOffShift(shiftValue); });
        $.each(shiftKeys, function(_, shiftValue){
            var meta = shiftMeta[shiftValue] || {start:'-', end:'-'};
            var start = timeToMinutes(meta.start);
            var end = timeToMinutes(meta.end);
            if(start === null || end === null){ return; }
            if(end <= start){ end += 24 * 60; }
            minHour = Math.min(minHour, Math.floor(start / 60));
            maxHour = Math.max(maxHour, Math.ceil(end / 60));
        });
    });
    if(minHour == 24 && maxHour == 0){ minHour = 7; maxHour = 22; }
    return {start: Math.max(0, minHour), end: Math.min(30, maxHour)};
}

function getAdaptiveHourHeight(dates, baseHeight){
    var maxPeoplePerHour = 0;
    $.each(dates, function(_, dateInfo){
        var dayData = collectVisualScheduleByDate(dateInfo.date);
        var shiftKeys = sortShiftKeys(Object.keys(dayData)).filter(function(shiftValue){ return !isOffShift(shiftValue); });
        $.each(shiftKeys, function(_, shiftValue){
            var meta = shiftMeta[shiftValue] || {start:'-', end:'-'};
            var duration = getShiftDurationHours(meta);
            var people = [];
            $.each(dayData[shiftValue], function(_, group){ people = people.concat(group); });
            maxPeoplePerHour = Math.max(maxPeoplePerHour, people.length / Math.max(1, duration));
        });
    });
    return Math.round(Math.min(86, Math.max(baseHeight, Math.ceil(maxPeoplePerHour / 3) * 34)) * visualZoom);
}

function getShiftColumnWidth(dayData, shiftKeys){
    var maxPeople = 0;
    $.each(shiftKeys, function(_, shiftValue){
        var people = [];
        $.each(dayData[shiftValue], function(_, group){ people = people.concat(group); });
        maxPeople = Math.max(maxPeople, people.length);
    });
    return Math.round(Math.min(190, Math.max(104, Math.ceil(Math.sqrt(Math.max(1, maxPeople))) * 34 + 18)) * visualZoom);
}

function renderHourAxis(range, hourHeight){
    var html = '<div class="visual-hour-axis" style="--hour-height:' + hourHeight + 'px; --total-hours:' + (range.end - range.start) + '">';
    html += '<div class="visual-hour-axis-title">Jam</div>';
    for(var hour = range.start; hour < range.end; hour++){
        var labelHour = hour % 24;
        html += '<div class="visual-hour-slot">' + (labelHour < 10 ? '0' : '') + labelHour + ':00</div>';
    }
    html += '</div>';
    return html;
}


function renderPerson(person, colorIndex){
    var color = getDivisionColor(person.division);
    var firstName = (person.name || '').split(' ')[0];
    var html = '';
    html += '<div class="visual-person" title="' + escapeHtml(person.name) + ' - ' + escapeHtml(person.position) + '">';
    html += '<div class="visual-person-icon" style="background:' + color + '"><i class="fa ' + getPositionIcon(person.position) + '"></i></div>';
    html += '<div class="visual-person-name">' + escapeHtml(firstName) + '</div>';
    html += '<div class="visual-person-code">' + escapeHtml(person.code) + '</div>';
    html += '</div>';
    return html;
}

function getVisualWeeks(){
    var dates = getVisualDates();
    var weeks = [];
    for(var index = 0; index < dates.length; index += 7){
        weeks.push(dates.slice(index, index + 7));
    }
    return weeks;
}

function populateVisualFilters(){
    var weeks = getVisualWeeks();
    var weekSelect = $('#visualScheduleWeek');
    var currentWeek = weekSelect.val();
    weekSelect.empty();
    $.each(weeks, function(index, week){
        if(week.length == 0){ return; }
        weekSelect.append('<option value="' + index + '">Minggu ' + (index + 1) + ' (' + week[0].date + ' s/d ' + week[week.length - 1].date + ')</option>');
    });
    if(currentWeek !== null && weekSelect.find('option[value="' + currentWeek + '"]').length > 0){
        weekSelect.val(currentWeek);
    }
    populateVisualDayFilter();
}

function populateVisualDayFilter(){
    var weeks = getVisualWeeks();
    var weekIndex = parseInt($('#visualScheduleWeek').val() || 0, 10);
    var daySelect = $('#visualScheduleDay');
    var currentDay = daySelect.val();
    daySelect.empty();
    $.each(weeks[weekIndex] || [], function(_, dateInfo){
        daySelect.append('<option value="' + dateInfo.date + '">' + dateInfo.day + ' - ' + dateInfo.date + '</option>');
    });
    if(currentDay !== null && daySelect.find('option[value="' + currentDay + '"]').length > 0){
        daySelect.val(currentDay);
    }
}

function renderWeeklyVisual(){
    var weeks = getVisualWeeks();
    var weekIndex = parseInt($('#visualScheduleWeek').val() || 0, 10);
    var week = weeks[weekIndex] || [];
    var hourHeight = getAdaptiveHourHeight(week, 44);
    var hourRange = getWeekHourRange(week);
    var html = '<div class="visual-week-layout" style="--visual-zoom:' + visualZoom + '">' + renderHourAxis(hourRange, hourHeight) + '<div class="visual-week-grid">';
    var rendered = 0;

    $.each(week, function(dateIndex, dateInfo){
        var dayData = collectVisualScheduleByDate(dateInfo.date);
        var shiftKeys = sortShiftKeys(Object.keys(dayData));
        var color = visualColors[dateIndex % visualColors.length];
        shiftKeys = shiftKeys.filter(function(shiftValue){ return !isOffShift(shiftValue); });
        var shiftColumnWidth = getShiftColumnWidth(dayData, shiftKeys);
        html += '<div class="visual-day-card" style="--visual-color:' + color + '; --hour-height:' + hourHeight + 'px; --total-hours:' + (hourRange.end - hourRange.start) + '; --shift-count:' + Math.max(1, shiftKeys.length) + '; --shift-column-width:' + shiftColumnWidth + 'px">';
        html += '<div class="visual-day-title">' + escapeHtml(dateInfo.day) + '<br><small class="text-muted">' + escapeHtml(dateInfo.date) + '</small></div>';
        html += '<div class="visual-day-timeline">';
        if(shiftKeys.length == 0){
            html += '<div class="p-2 text-muted small">Tidak ada jadwal kerja</div>';
        }
        $.each(shiftKeys, function(shiftIndex, shiftValue){
            rendered++;
            var bg = getShiftBackground(shiftIndex);
            var meta = shiftMeta[shiftValue] || {code: shiftValue, name: shiftValue, start: '-', end: '-'};
            var startMinutes = timeToMinutes(meta.start);
            var endMinutes = timeToMinutes(meta.end);
            if(startMinutes === null || endMinutes === null){ startMinutes = hourRange.start * 60; endMinutes = startMinutes + 60; }
            if(endMinutes <= startMinutes){ endMinutes += 24 * 60; }
            var rangeStartMinutes = hourRange.start * 60;
            var rangeEndMinutes = hourRange.end * 60;
            var top = Math.max(0, ((startMinutes - rangeStartMinutes) / 60) * hourHeight);
            var height = Math.max(hourHeight, ((Math.min(endMinutes, rangeEndMinutes) - Math.max(startMinutes, rangeStartMinutes)) / 60) * hourHeight);
            html += '<div class="visual-shift-column">';
            html += '<div class="visual-shift-row" style="--shift-bg:' + bg.soft + '; --shift-bg-strong:' + bg.strong + '; top:' + top + 'px; height:' + height + 'px">';
            html += '<div class="visual-shift-label" title="' + escapeHtml(meta.name) + ' ' + escapeHtml(meta.start) + ' - ' + escapeHtml(meta.end) + '">' + escapeHtml(meta.code) + '<br><small>' + escapeHtml(meta.start) + '-' + escapeHtml(meta.end) + '</small></div>';
            html += '<div class="visual-shift-people">';
            var people = [];
            $.each(dayData[shiftValue], function(_, group){ people = people.concat(group); });
            $.each(people.slice(0, 24), function(personIndex, person){
                html += renderPerson(person, personIndex + shiftIndex);
            });
            if(people.length > 24){
                html += '<span class="badge badge-light" title="Total ' + people.length + ' orang">+' + (people.length - 24) + '</span>';
            }
            html += '</div></div></div>';
        });
        html += '</div></div>';
    });

    html += '</div></div>';
    $('#visualScheduleContent').html(rendered == 0 ? '<div class="alert alert-warning">Belum ada jadwal pada minggu ini.</div>' : html);
}

function renderDailyVisual(){
    var date = $('#visualScheduleDay').val();
    if(!date){ $('#visualScheduleContent').html('<div class="alert alert-warning">Pilih hari terlebih dahulu.</div>'); return; }
    var dayData = collectVisualScheduleByDate(date);
    var shiftKeys = sortShiftKeys(Object.keys(dayData)).filter(function(shiftValue){ return !isOffShift(shiftValue); });
    if(shiftKeys.length == 0){ $('#visualScheduleContent').html('<div class="alert alert-warning">Belum ada jadwal kerja pada hari ini.</div>'); return; }
    var dayLabel = $('#visualScheduleDay option:selected').text() || date;
    var dateInfo = {day: dayLabel.split(' - ')[0] || 'Hari', date: date};
    var hourRange = getWeekHourRange([dateInfo]);
    var hourHeight = getAdaptiveHourHeight([dateInfo], 54);
    var color = visualColors[0];
    var shiftColumnWidth = Math.max(150, getShiftColumnWidth(dayData, shiftKeys));
    var html = '<div class="visual-week-layout visual-day-layout" style="--visual-zoom:' + visualZoom + '">' + renderHourAxis(hourRange, hourHeight) + '<div class="visual-week-grid">';
    html += '<div class="visual-day-card visual-day-card-single" style="--visual-color:' + color + '; --hour-height:' + hourHeight + 'px; --total-hours:' + (hourRange.end - hourRange.start) + '; --shift-count:' + Math.max(1, shiftKeys.length) + '; --shift-column-width:' + shiftColumnWidth + 'px">';
    html += '<div class="visual-day-title">' + escapeHtml(dateInfo.day) + '<br><small class="text-muted">' + escapeHtml(dateInfo.date) + '</small></div>';
    html += '<div class="visual-day-timeline">';
    $.each(shiftKeys, function(shiftIndex, shiftValue){
        var bg = getShiftBackground(shiftIndex);
        var meta = shiftMeta[shiftValue] || {code: shiftValue, name: shiftValue, start: '-', end: '-'};
        var startMinutes = timeToMinutes(meta.start);
        var endMinutes = timeToMinutes(meta.end);
        if(startMinutes === null || endMinutes === null){ startMinutes = hourRange.start * 60; endMinutes = startMinutes + 60; }
        if(endMinutes <= startMinutes){ endMinutes += 24 * 60; }
        var rangeStartMinutes = hourRange.start * 60;
        var rangeEndMinutes = hourRange.end * 60;
        var top = Math.max(0, ((startMinutes - rangeStartMinutes) / 60) * hourHeight);
        var height = Math.max(hourHeight, ((Math.min(endMinutes, rangeEndMinutes) - Math.max(startMinutes, rangeStartMinutes)) / 60) * hourHeight);
        html += '<div class="visual-shift-column">';
        html += '<div class="visual-shift-row" style="--shift-bg:' + bg.soft + '; --shift-bg-strong:' + bg.strong + '; top:' + top + 'px; height:' + height + 'px">';
        html += '<div class="visual-shift-label" title="' + escapeHtml(meta.name) + ' ' + escapeHtml(meta.start) + ' - ' + escapeHtml(meta.end) + '">' + escapeHtml(meta.code) + ' / ' + escapeHtml(meta.name) + '<br><small>' + escapeHtml(meta.start) + '-' + escapeHtml(meta.end) + '</small></div>';
        html += '<div class="visual-shift-people">';
        var people = [];
        $.each(dayData[shiftValue], function(_, group){ people = people.concat(group); });
        $.each(people, function(personIndex, person){
            html += renderPerson(person, personIndex + shiftIndex);
        });
        html += '</div></div></div>';
    });
    html += '</div></div></div></div>';
    $('#visualScheduleContent').html(html);
}


function setVisualZoom(nextZoom, keepPosition){
    var board = $('#visualScheduleContent');
    var oldWidth = board.get(0) ? board.get(0).scrollWidth : 1;
    var oldHeight = board.get(0) ? board.get(0).scrollHeight : 1;
    var oldLeftRatio = oldWidth > 0 ? board.scrollLeft() / oldWidth : 0;
    var oldTopRatio = oldHeight > 0 ? board.scrollTop() / oldHeight : 0;
    visualZoom = Math.max(0.6, Math.min(1.8, Math.round(nextZoom * 10) / 10));
    $('#visualZoomValue').text(Math.round(visualZoom * 100) + '%');
    renderVisualSchedule();
    if(keepPosition !== false){
        var newBoard = $('#visualScheduleContent');
        newBoard.scrollLeft(newBoard.get(0).scrollWidth * oldLeftRatio);
        newBoard.scrollTop(newBoard.get(0).scrollHeight * oldTopRatio);
    }
}

function resetVisualPan(){
    $('#visualScheduleContent').scrollLeft(0).scrollTop(0);
}

$(document).on('click', '#visualZoomIn', function(){ setVisualZoom(visualZoom + 0.1, true); });
$(document).on('click', '#visualZoomOut', function(){ setVisualZoom(visualZoom - 0.1, true); });
$(document).on('click', '#visualZoomReset', function(){ setVisualZoom(1, false); resetVisualPan(); });

$(document).on('wheel', '#visualScheduleContent', function(event){
    if(!event.ctrlKey){ return; }
    event.preventDefault();
    setVisualZoom(visualZoom + (event.originalEvent.deltaY < 0 ? 0.1 : -0.1), true);
});

(function(){
    var isDragging = false;
    var startX = 0;
    var startY = 0;
    var startLeft = 0;
    var startTop = 0;
    $(document).on('mousedown', '#visualScheduleContent', function(event){
        if(event.which !== 1 || $(event.target).closest('button, select, input, a').length){ return; }
        isDragging = true;
        startX = event.pageX;
        startY = event.pageY;
        startLeft = this.scrollLeft;
        startTop = this.scrollTop;
        $(this).addClass('dragging');
        event.preventDefault();
    });
    $(document).on('mousemove', function(event){
        if(!isDragging){ return; }
        var board = $('#visualScheduleContent');
        board.scrollLeft(startLeft - (event.pageX - startX));
        board.scrollTop(startTop - (event.pageY - startY));
    });
    $(document).on('mouseup mouseleave', function(){
        if(!isDragging){ return; }
        isDragging = false;
        $('#visualScheduleContent').removeClass('dragging');
    });
})();

function renderVisualSchedule(){
    var mode = $('#visualScheduleMode').val();
    $('#visualDayFilterWrapper').toggle(mode == 'day');
    populateVisualDayFilter();
    if(mode == 'day'){
        renderDailyVisual();
    }else{
        renderWeeklyVisual();
    }
}

$(document).on('click', '#btnVisualSchedule', function(){
    $('#visualZoomValue').text(Math.round(visualZoom * 100) + '%');
    populateVisualFilters();
    renderVisualSchedule();
    $('#modalVisualSchedule').modal('show');
});

$(document).on('change', '#visualScheduleMode, #visualScheduleWeek', function(){
    populateVisualDayFilter();
    renderVisualSchedule();
});

$(document).on('change', '#visualScheduleDay', function(){
    renderVisualSchedule();
});

$(document).on('change', '.schedule-select', function(){
    if($('#modalVisualSchedule').hasClass('show')){
        renderVisualSchedule();
    }
    if($('#gameScheduler').length){
        refreshGameBoard();
    }
});

function applyScheduleToPage(schedule){
    $.each(schedule, function(userId, dates){
        $.each(dates, function(date, value){
            var select = $('[name="schedule[' + userId + '][' + date + ']"]');
            if(select.length > 0){
                if(select.find('option[value="' + value + '"][data-advanced="1"]').length > 0){
                    $('#advancedShiftToggle').prop('checked', true);
                }
                select.val(value);
            }
        });
    });
    toggleAdvancedShiftOptions();
}

function showScheduleMessage(type, message){
    if(typeof show_modal === 'function'){
        show_modal(type, message);
    }else{
        alert(message);
    }
}

$(document).on('click', '#btnShowUploadSchedule', function(){
    $('#excelScheduleFile').click();
});

$(document).on('change', '#excelScheduleFile', function(){
    if(this.files.length == 0){ return; }
    var formData = new FormData($('#formLoadWorkScheduleExcel')[0]);
    $.ajax({
        url: "<?= site_url('load_work_schedule_excel') ?>",
        dataType: "json",
        method: "POST",
        data: formData,
        processData: false,
        contentType: false,
        beforeSend: function(){
            $('#btnShowUploadSchedule').attr('disabled', 'disabled').html('<i class="fa fa-spinner fa-spin"></i> Upload');
        },
        success: function(res){
            if(res.status){
                applyScheduleToPage(res.schedule);
                showScheduleMessage('success', res.message);
            }else{
                showScheduleMessage('info', res.message);
            }
        },
        complete: function(){
            $('#btnShowUploadSchedule').removeAttr('disabled').html('<i class="fa fa-upload"></i> Upload');
            $('#excelScheduleFile').val('');
        }
    });
});

$(document).on('click', '#btnCopyPreviousSchedule', function(){
    if(!confirm('Copy jadwal dari periode sebelumnya ke halaman ini? Data belum disimpan sampai tombol Simpan Jadwal diklik.')){ return; }
    $.ajax({
        url: "<?= site_url('copy_previous_work_schedule') ?>",
        dataType: "json",
        method: "POST",
        data: {
            '<?= $this->security->get_csrf_token_name() ?>': '<?= $this->security->get_csrf_hash() ?>',
            branch_id: '<?= $branch_id ?>',
            month: '<?= $month ?>',
            year: '<?= $year ?>'
        },
        beforeSend: function(){
            $('#btnCopyPreviousSchedule').attr('disabled', 'disabled').html('<i class="fa fa-spinner fa-spin"></i> Copy');
        },
        success: function(res){
            if(res.status){
                applyScheduleToPage(res.schedule);
                showScheduleMessage('success', res.message);
            }else{
                showScheduleMessage('info', res.message);
            }
        },
        complete: function(){
            $('#btnCopyPreviousSchedule').removeAttr('disabled').html('<i class="fa fa-copy"></i> Copy Periode Sebelumnya');
        }
    });
});

function toggleAdvancedShiftOptions(){
    var isAdvanced = $('#advancedShiftToggle').is(':checked');
    $('.schedule-select option[data-advanced="1"]').each(function(){
        var option = $(this);
        if(isAdvanced || option.is(':selected')){
            option.prop('disabled', false).show();
        }else{
            option.prop('disabled', true).hide();
        }
    });
}

$(document).on('click', '.set-all-shift', function(){
    var value = $(this).data('value');
    $('.schedule-select').val(value);
    toggleAdvancedShiftOptions();
    refreshGameBoard();
});

$(document).on('change', '#advancedShiftToggle', function(){
    toggleAdvancedShiftOptions();
});

toggleAdvancedShiftOptions();
</script>



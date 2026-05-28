<style type="text/css">

table{
  text-align: left;
  position: relative;
  border-collapse: collapse; 
}

th {
  background: white;
  position: sticky;
  top: 0; /* Don't forget this, required for the stickiness */
  box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.4);
}

table td,
table th {
  padding: 0.5rem 1rem;
}
table thead th {
  padding: 3px;
  position: sticky;
  top: 0;
  z-index: 1;
  width: 25vw;
  background: white;
}

table thead th:first-child {
  position: sticky;
  left: 0;
  z-index: 2;
}

table tbody th {
  position: sticky;
  left: 0;
  background: white;
  z-index: 1;
}

.presence-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.presence-toolbar .btn {
  min-height: 38px;
}

.presence-filter-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(180px, 1fr));
  gap: 14px;
  align-items: end;
}

.presence-filter-grid .filter-wide {
  grid-column: span 2;
}

.presence-sync-options {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  align-items: center;
  padding-top: 8px;
}

@media (max-width: 991px) {
  .presence-filter-grid {
    grid-template-columns: repeat(2, minmax(160px, 1fr));
  }

  .presence-filter-grid .filter-wide {
    grid-column: span 2;
  }
}

@media (max-width: 575px) {
  .presence-filter-grid {
    grid-template-columns: 1fr;
  }

  .presence-filter-grid .filter-wide {
    grid-column: span 1;
  }
}

</style>

<?php $role = $this->ion_auth->get_users_groups()->row()->name; ?>
<!-- end page title -->

<?php  
    $status = false;
    $now  = strtotime(date('Y-m-01'));
    $date_select = strtotime(date('Y-m-01', strtotime($year."-".$month."-01")));
    if($now < $date_select){
        $status = true;
    }
    $param = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];
    $sync_from_min = !empty($daterange['list']) ? reset($daterange['list']) : $year.'-'.$month.'-01';
    $sync_from_max = !empty($daterange['list']) ? end($daterange['list']) : date('Y-m-t', strtotime($year.'-'.$month.'-01'));
    $sync_from_default = date('Y-m-d', strtotime('-1 day'));
    if($sync_from_default < $sync_from_min || $sync_from_default > $sync_from_max){
        $sync_from_default = $sync_from_min;
    }
?>

<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0"><i class="dripicons-experiments"></i> Atur Jadwal Presensi</h4>

                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript:void(0);">Presensi</a></li>
                        <li class="breadcrumb-item active"><a href="<?= site_url('hr/presence/setting') ?>">Atur Jadwal</a></li>
                    </ol>
                </div>

            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title">Daftar Jadwal</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            <?= $this->session->flashdata('alert_message') ?>
                        </div>
                    </div>

                    <?php if($role == 'admin'){ ?>
                        <form class="mb-3">
                            <div class="presence-filter-grid">
                                <div class="filter-wide">
                                    <label>Pilih Cabang</label>
                                    <select class="form-control" name="branch_id" id="branch">
                                        <?php foreach ($branch as $row) { ?>
                                            <option <?= $branch_id == $row['id'] ? 'selected="selected"' : '' ?> value="<?= $row['id'] ?>"><?= $row['branch_code']." / ".$row['branch_name'] ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div>
                                    <button class="btn btn-primary w-100"><i class="fa fa-search"></i> Terapkan Cabang</button>
                                </div>
                                <div>
                                    <label>Sync mulai tanggal</label>
                                    <input type="date" class="form-control" id="syncFromDate" value="<?= $sync_from_default ?>" min="<?= $sync_from_min ?>" max="<?= $sync_from_max ?>">
                                </div>
                                <div class="presence-sync-options">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="useScheduleSync" checked>
                                        <label class="form-check-label" for="useScheduleSync">Pakai jadwal</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="autoSyncFive">
                                        <label class="form-check-label" for="autoSyncFive">Auto sync 5 menit</label>
                                    </div>
                                </div>
                            </div>
                        </form>
                    <?php }else if(in_array($this->role, ['admin-branch', 'supervisor'])){ ?>
                        <div class="presence-sync-options mb-3">
                            <div>
                                <label>Sync mulai tanggal</label>
                                <input type="date" class="form-control" id="syncFromDate" value="<?= $sync_from_default ?>" min="<?= $sync_from_min ?>" max="<?= $sync_from_max ?>">
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="useScheduleSync" checked>
                                <label class="form-check-label" for="useScheduleSync">Pakai jadwal</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="autoSyncFive">
                                <label class="form-check-label" for="autoSyncFive">Auto sync 5 menit</label>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="row">
                        <div class="col-md-7"></div>
                        <div class=""></div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="presence-toolbar mb-2">
                                <a href="<?= site_url('hr/presence') ?>" class="btn btn-light"><i class="fa fa-arrow-left"></i> Kembali</a>
                                <?php if(in_array($role, ['admin', 'admin-branch', 'hr', 'supervisor'])){ ?>
                                    <a href="<?= base_url('exports/report_absen_'.$year.'_'.str_pad($month, 2, '0', STR_PAD_LEFT).'_all.xlsx') ?>" target="_blank" class="btn btn-outline-success"><i class="mdi mdi-file-excel"></i> Report Performance Absen</a>
                                <?php } ?>
                                <?php if(in_array($this->role, ['admin', 'admin-branch', 'supervisor'])){ ?>
                                    <a href="javascript:void(0)" id="btnModalUpload" class="btn btn-success"><i class="fa fa-clock"></i> Upload</a>
                                    <a href="<?= site_url('export_work_schedule/'.$month.'/'.$year.'/'.$branch_id.'?with_schedule=1') ?>" class="btn btn-outline-secondary"><i class="fa fa-download"></i> Download Jadwal</a>
                                    <a href="javascript:void(0)" id="btnSyncCloud" class="btn btn-info"><i class="fa fa-sync"></i> Sync Reguler</a>
                                    <a href="javascript:void(0)" id="btnSyncPrayCloud" class="btn btn-primary"><i class="fa fa-mosque"></i> Sync Sholat</a>
                                    <a href="javascript:void(0)" id="btnClearPresence" class="btn btn-danger"><i class="fa fa-trash"></i> Hapus</a>
                                <?php } ?>
                            </div>
                            <div class="mt-2 text-muted small">
                                <?php if(!empty($last_import)){ ?>
                                    Update terakhir: <?= indonesian_date($last_import['created_at']) ?> <?= date('H:i:s', strtotime($last_import['created_at'])) ?>
                                    via <?= strtoupper($last_import['method']) ?> (<?= $last_import['total_rows'] ?> data)
                                <?php }else{ ?>
                                    Update terakhir: belum ada data upload/sync
                                <?php } ?>
                            </div>
                            
                        </div>

                        <div class="col-md-4">
                            Status Ketepatan Waktu
                            <div class="progress progress-xl animated-progess mt-1 mb-1 p-1">
                                <div id="progress" class="progress-bar bg-info" role="progressbar" style="width: 5%" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <center><b style="margin-top:-30px" id="string_progress">0%</b></center>
                        </div>
                        
                        <?php 
                            $get = '';
                            if($role == 'admin'){
                                $get = $this->input->get('branch_id') ? '?branch_id='.$this->input->get('branch_id') : '';
                            }
                        ?>

                        <div class="col-md-4 text-center">
                            <div class="row">
                                <div class="col-md-3">
                                    <?php $prev = date('m', strtotime('-1 month', strtotime($year.'-'.$month.'-16')))."/".date('Y', strtotime('-1 month', strtotime($year.'-'.$month.'-16'))); ?>
                                    <a href="<?= site_url('hr/presence/'.$prev.$get) ?>" class="btn btn-light"><i class="fa fa-chevron-left"></i></a>
                                </div>
                                <div class="col-md-6">
                                    Waktu
                                    <h5><?= get_monthname($month)." ".$year ?></h5>
                                </div>
                                <div class="col-md-3">
                                    <?php $next = date('m', strtotime('+1 month', strtotime($year.'-'.$month.'-16')))."/".date('Y', strtotime('+1 month', strtotime($year.'-'.$month.'-16'))); ?>
                                    <a href="<?= site_url('hr/presence/'.$next.$get) ?>" class="btn btn-light"><i class="fa fa-chevron-right"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <small><i class="fa fa-list"></i> Keterangan</small>
                            <div class="mt-2">
                                <div class="row">
                                    <div class="col-md-12">
                                        <span class="bg-danger" style="padding: 1px; padding-right: 20px"></span> &nbsp;Tidak Hadir
                                        &emsp;

                                        <span class="bg-success" style="padding: 1px; padding-right: 20px"></span> &nbsp;Hadir
                                        &emsp;

                                        <span class="bg-warning" style="padding: 1px; padding-right: 20px"></span> &nbsp;Hadir Sebagian
                                    </div>

                                    <div class="col-md-12">
                                        <div class="mt-2">
                                            <span class="mt-2" style="padding: 0.5px; padding-right: 20px; background-color: #cdcdcd"></span> &nbsp;Libur / Bukan hari kerja
                                            &emsp;
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="mt-2">
                                            <span class="mt-2" style="padding: 0.5px; padding-right: 20px; background-color: #5b73e8"></span> &nbsp;Pulang Lebih Awal
                                            &emsp;
                                        </div>
                                    </div>

                                    <div class="col-md-12 mt-2">
                                        <span class="badge bg-primary" style="border: 1px solid #5b73e8">K</span>
                                        &nbsp;Keterlambatan Presensi Kerja <br>
                                    </div>

                                    <div class="col-md-12 mt-2">
                                        <span class="badge bg-info" style="border: 1px solid #5b73e8">S</span>
                                         &nbsp;Keterlambatan Presensi Sholat <br>
                                    </div>

                                    <div class="col-md-12 mt-2">
                                        <span class="badge bg-light" style="border: 1px solid #5b73e8">L</span>
                                         &nbsp;Lembur Dalam Pengajuan<br>
                                    </div>

                                    <div class="col-md-12 mt-2">
                                        <span class="badge bg-secondary" style="border: 1px solid #5b73e8">L</span>
                                         &nbsp;Lembur Sudah Disetujui<br>
                                    </div>

                                    <div class="col-md-12 mt-2">
                                        <span class="badge bg-primary" style="border: 1px solid #5b73e8">PLA</span>
                                         &nbsp;Izin Pulang Lebih Awal<br>
                                    </div>

                                </div>

                            </div>
                        </div>

                        <div class="col-md-3">
                            <small><em class="fa fa-id-badge"></em> By Posisi</small>
                            <select class="select-plugin" style="width: 100%" id="positionFilter">
                                <option value="all">Semua</option>
                                <?php foreach ($position as $row) { ?>
                                    <option value="<?= $row['position_name'] ?>"><?= $row['position_name'] ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <small><em class="fa fa-users"></em> By CV</small>
                            <select class="select-plugin" style="width: 100%" id="subdivision">
                                <option value="all">Semua</option>
                                <?php foreach ($subdivision as $row) { ?>
                                    <option value="<?= $row['subdivision_name'] ?>"><?= $row['subdivision_name'] ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <small><em class="fa fa-search"></em> Pencarian</small>
                            <input id="autocomplete" type="text" class="form-control" placeholder="Cari Karyawan...">
                        </div>
                    </div>

                    <?php if($this->role == 'admin' || $status == true){ ?>
                        <!--<a href="javascript:void(0)" class="btn btn-outline-danger mt-3" data-bs-toggle="modal" data-bs-target="#modalReset">
                            <i class="fa fa-times-circle"></i> <i class="fa fa-clock"></i> Reset Shift Default
                        </a>-->
                    <?php } ?>
                    

                </div>
            </div>
            
        </div>
    </div>
</div>


<div class="row">
    <div class="col-md-12">
        <div class="">
                    <table class="table table-bordered mt-3" style="width: 2300px; border :1px solid #999">
                        <thead>
                            <tr>
                                <th valign="middle" rowspan="2" style="background-color: #fff; width: 40%; border-right: 1px solid #999">NAMA / ID FINGERPRINT</th>
                                <th valign="middle" rowspan="2" style="background-color: #fff; width: 30%; border-right: 1px solid #999">POSISI / SUB DEPARTENENT</th>
                                <th style="background-color: #cdcdcd; border : 1px solid #999" colspan="<?= $daterange['from']['count'] ?>">
                                    <?= strtoupper($daterange['from']['string_month']) ?>
                                </th>
                                <th style="background-color: #cdcdcd; border : 1px solid #999" colspan="<?= $daterange['to']['count'] ?>">
                                    <?= strtoupper($daterange['to']['string_month']) ?>
                                </th>

                                <th valign="middle" rowspan="2" style="border-left : 1px solid #999;background-color: #fff" class="text-center">TOTAL MASUK</th>
                            </tr>

                            <tr>
                                <?php foreach ($daterange['list'] as $row) { 
                                        $d = date('d', strtotime($row));
                                        $dayname = get_dayname($row);
                                        $s[$row] = 0;
                                ?>
                                    <th class="text-center" style="background-color:#fff;z-index: 0; font-weight: 400; width: 1%; border:1px solid #999; border: 1px solid #cdcdcd">
                                        <br><br><b><?= $d ?></b><br>
                                        <small style="font-weight: 400"><?= $dayname ?></small></th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody id="listPresence">
                            <?php $day = []; $n= 0; $num_present = 0; $all_work = 0;
                                  foreach ($attendance['list'] as $row){ $n++; 
                                    $total_work = $presence_in = 0;
                            ?>
                                <tr data-subdivision="<?= $row['employee']['subdivision'] ?>" data-position="<?= $row['employee']['position'] ?>">
                                    <th style="background-color: #fff; font-weight: 500; border: 1px solid #999;">
                                        <?= $row['employee']['name'] ?><br>
                                        <small class="text-muted">
                                            <?= $row['employee']['code'] ?>
                                        </small>
                                    </th>

                                    <td style="background-color: #fff">
                                        <?= $row['employee']['position'] ?> <br>
                                        <small class="text-muted">
                                            <?= $row['employee']['subdivision'] ?>
                                        </small>
                                    </td>

                                    <?php $x = 0; foreach ($row['workday'] as $workday){ $x++;
                                        $work_in = false;
                                        $string_date = date('Y-m-d', strtotime($workday['date']));
                                        $row_is_early_leave = !empty($workday['present']['detail']['is_early_leave']);
                                        $row_status = isset($workday['present']['detail']['presence_status']) ? $workday['present']['detail']['presence_status'] : '';
                                        $row_short_minutes = isset($workday['present']['detail']['early_leave_short_minutes']) ? (int)$workday['present']['detail']['early_leave_short_minutes'] : 0;

                                        if($workday['present']['status'] || $workday['present']['half'] != ''){
                                            $work_in = true;
                                            $presence_in++;

                                            if($row_is_early_leave && $row_status !== 'deny'){
                                                // Izin Pulang Cepat: warna biru, tetap hitung kehadiran.
                                                $color = '5b73e8';
                                                $num_present++;
                                            }else if($workday['present']['status']){
                                                $color = '34c38f';
                                                $num_present++;
                                            }else{
                                                $color = 'f1b44c';
                                            }

                                            $code  = $workday['code'];
                                            $total_work++;
                                            $s[$string_date]++;

                                        }else{
                                            if($workday['type'] == 'work'){
                                                $color = 'f46a6a';
                                                $code  = $workday['code'];
                                                $s[$string_date]++;

                                                if($workday['code'] != '-'){
                                                    $total_work++;
                                                }

                                            }else{
                                                $color = 'cdcdcd';
                                                $code  = 'OFF';
                                            }
                                        }

                                        if($workday['type'] == 'work'){
                                            if(isset($day[$workday['date']][$workday['code']]['num'])){
                                                $day[$workday['date']][$workday['code']]['num']++;
                                            }else{
                                                $day[$workday['date']][$workday['code']]['num'] = 1;
                                            }
                                        }

                                        $present = $workday['present']['detail'];
                                        $entry_late = isset($present['entry_time_late']) ? $present['entry_time_late'] : 0;
                                        $rest_late  = isset($present['rest_time_late']) ? $present['rest_time_late'] : 0;

                                        $presence_id = isset($present['presence_id']) ? $present['presence_id'] : '';
                                        $entry_time = isset($present['entry_time']) ? $present['entry_time'] : '';
                                        $out_time = isset($present['out_time']) ? $present['out_time'] : '';
                                        $rest_time_in = isset($present['rest_time_in']) ? $present['rest_time_in'] : '';
                                        $rest_time_out = isset($present['rest_time_out']) ? $present['rest_time_out'] : '';

                                        $input_by = isset($present['input_by']) ? $present['input_by'] : '';
                                        $created_at = isset($present['created_at']) ? $present['created_at'] : '';
                                        $input_by = strtoupper(substr($workday['present']['by'], 0, 1));
                                        $row_id = $row['employee']['id'].$x;

                                        $late_work_status = $late_pray_status = false;
                                        $late_work_txt = $late_pray_txt = '';

                                        if($work_in){
                                            if($entry_late > 0 || $rest_late > 0 || ($rest_time_out == '' && $rest_time_in != '')){
                                                $late_work_status = true;
                                                $late_work_txt = "<span style='border: 0.2px solid #5b73e8' class='badge bg-primary late_work_status' id='late_work_status_".$row_id."'>K</span>";
                                            }
                                        }

                                        // Badge PLA: tampil saat Izin Pulang Cepat dan kehadiran masih dihitung
                                        // (presence_status != deny). Hour short ditaruh di tooltip.
                                        $early_leave_badge_txt = '';
                                        if($row_is_early_leave && $row_status !== 'deny'){
                                            $short_label = $row_short_minutes > 0
                                                ? sprintf(' (-%dj %dm)', intdiv($row_short_minutes, 60), $row_short_minutes % 60)
                                                : '';
                                            $early_leave_badge_txt = "<span style='border: 0.2px solid #5b73e8' class='badge bg-primary early_leave_status' id='early_leave_status_".$row_id."' title='Izin Pulang Lebih Awal".$short_label."'>PLA</span>";
                                        }

                                        $attr = 'data-id="'.$row['employee']['id'].'"
                                                 data-code="'.$row['employee']['code'].'"
                                                 data-name="'.$row['employee']['name'].'"
                                                 data-date="'.$workday['date'].'"
                                                 data-workcode="'.$workday['code'].'"
                                                 data-workname="'.$workday['name'].'"
                                                 data-workstart="'.$workday['start_time'].'"
                                                 data-workend="'.$workday['end_time'].'"
                                                 data-type="'.$workday['type'].'"
                                                 data-reststart="'.$workday['start_time_rest'].'"
                                                 data-restend="'.date('H:i', strtotime($workday['start_time_rest']." + ".$workday['rest_time_range']."minutes")).'"

                                                 data-presence-id="'.$presence_id.'"
                                                 data-presence-half="'.$workday['present']['half'].'"
                                                 data-presence-overtime="'.$workday['present']['is_overtime'].'"
                                                 data-presence-type="'.$workday['present']['presence_type'].'"
                                                 data-presence-entry-time="'.$entry_time.'"
                                                 data-presence-out-time="'.$out_time.'"
                                                 data-presence-entry-late="'.$entry_late.'"
                                                 data-rest-time-in="'.$rest_time_in.'"
                                                 data-rest-time-out="'.$rest_time_out.'"
                                                 data-rest-time-late="'.$rest_late.'"
                                                 data-presence-input-by="'.$input_by.'"
                                                 data-presence-created-at="'.indonesian_date($created_at, true).'"
                                                 data-presence-input-by="'.$input_by.'"
                                                 data-presence-is-early-leave="'.($row_is_early_leave ? '1' : '0').'"
                                                 data-presence-early-leave-short="'.$row_short_minutes.'"
                                                 data-presence-status="'.htmlspecialchars($row_status, ENT_QUOTES).'"
                                                 data-row="'.$row_id.'"
                                                 ';

                                        foreach ($param as $pray) {
                                            $pray_in = $pray_out = ''; 
                                            $pray_late = 0;

                                            if(isset($present[$pray."_time_in"])){
                                                $pray_in = $present[$pray."_time_in"] != '' ? date('H:i', strtotime($present[$pray."_time_in"])) : '';
                                                $pray_out = $present[$pray."_time_out"] != '' ? date('H:i', strtotime($present[$pray."_time_out"])) : '';
                                                $pray_late = $present[$pray."_time_late"] != '' ? $present[$pray."_time_late"] : 0;

                                                if($work_in){
                                                    if($pray_late > 0 || ($pray_in != '' && $pray_out == '')){
                                                        $late_pray_status = true;
                                                        $late_pray_txt = "<span style='border: 0.5px solid #5b73e8' class='badge bg-info late_pray_status' id='late_pray_status_".$row_id."'>S</span>";
                                                    }
                                                }
                                            }

                                            $attr .= 'data-'.$pray.'-time-in ="'.$pray_in.'"';
                                            $attr .= 'data-'.$pray.'-time-out ="'.$pray_out.'"';
                                            $attr .= 'data-'.$pray.'-time-late ="'.$pray_late.'"';
                                        }

                                        $ovt = $workday['present']['is_overtime'];
                                        $presence_type = $workday['present']['presence_type'];
                                        $title_by = '';
                                        $ket = '';

                                        if($presence_type != ''){
                                            $ket = "<br><small>".$presence_type."</small>";
                                        }else if($ovt == '1'){
                                            $ket = "<br><small>lembur</small>";
                                        }
                                    ?>
                                        <td id="<?= $row_id ?>" class="attendance text-center" <?= $attr ?> 
                                            style="
                                                cursor:pointer; 
                                                padding: 11px 3px 11px 0px;
                                                background-color: #<?= $color ?>; 
                                                border : 1px solid #999"
                                        >
                                            
                                            <b><?= "<span id='".$row_id."_code'>".$code."</span><br><span id='". $row_id ."_input_by'>".$title_by."</span>" ?> <span id="overtime_<?= $row_id ?>"><?= $ket ?></span></b>

                                            <div>
                                                <span id="late_work_status_body_<?= $row_id ?>">
                                                    <?= $late_work_status ? $late_work_txt : "" ?>
                                                </span>
                                                <span id="late_pray_status_body_<?= $row_id ?>">
                                                    <?= $late_pray_status ? $late_pray_txt : "" ?>
                                                </span>
                                                <span id="early_leave_status_body_<?= $row_id ?>">
                                                    <?= $early_leave_badge_txt ?>
                                                </span>
                                                <span id="overtime_presence_<?= $row_id ?>">
                                                    <?php 
                                                        if($workday['present']['is_overtime_presence']){
                                                            if($workday['present']['is_overtime_approve']){
                                                                echo '<span class="badge bg-secondary">L</span>';
                                                            }else{
                                                                echo '<span class="badge bg-light">L</span>';
                                                            }
                                                        } 
                                                    ?>
                                                </span>
                                            </div>

                                        </td>
                                    <?php } ?>
                                    <td class="text-center" style="border : 1px solid #999; background-color: #fff">
                                        <?php $all_work += $total_work ?>
                                        <b><?= $presence_in." / ".$total_work ?></b>
                                    </td>
                                </tr>
                            <?php } ?>

                            <tr>
                                <th colspan="2" style="background-color: #fff" class="text-center">SHIFT</th>
                                <th colspan="<?= $d ?>"></th>
                            </tr>

                            <?php 
                                $all = []; 
                                foreach ($attendance['shift'] as $row){ 
                                    $total_all_shift = 0;
                                ?>
                                <tr>
                                    <th colspan="2" style="background-color: #fff">
                                        <?= $row['code']." / ".$row['name'] ?><br>
                                        <small class="text-muted"><?= date('H:i', strtotime($row['start']))." - ".date('H:i', strtotime($row['end'])) ?></small>
                                    </th>

                                    <?php 
                                        foreach ($daterange['list'] as $d) { 
                                            $num = isset($day[$d][$row['code']]['num']) ? $day[$d][$row['code']]['num'] : 0;
                                            $total_all_shift += $num;
                                            
                                            if(isset($all[$d]['num'])){
                                                if($num != 0){
                                                    $all[$d]['num'] += $num;
                                                }
                                                
                                            }else{
                                                if($num != 0){
                                                    $all[$d]['num'] = 1;
                                                }else{
                                                    $all[$d]['num'] = 0;
                                                }
                                            }
                                    ?>
                                        <td class="text-center" style="background-color: #fff"><?= $num ?></td>
                                    <?php } ?>
                                    <td style="border : 1px solid #999; background-color: #fff" class="text-center"><b><?= $total_all_shift ?></b></td>
                                </tr>
                            <?php } ?>

                            <th colspan="2" style="background-color: #fff" class="text-center">TOTAL</th>
                            <?php foreach ($s as $row){ ?>
                                <th style="z-index: 0; border: 1px solid #999; background-color: #fff" class="text-center"><?= $row ?></th>
                            <?php } ?>
                        </tbody>
                    </table>
    </div>
</div>


<form id="formUpdate">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" id="user_id" name="user_id">
<input type="hidden" id="date" name="date">
<input type="hidden" name="row_id" id="row_id">

<div id="modalAttendance" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel">
                    <?= in_array($role, ['admin', 'admin-branch']) ? '<i class="fa fa-edit"></i> Ubah Presensi</h5>' : '<i class="fa fa-search"></i> Detail Presensi</h5>' ?>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">
                    <div class="col-md-12">
                        <table class="table">
                            <tr>
                                <th style="width: 15%; background-color: #eee">Karyawan</th>
                                <td style="width: 40%" id="d_employee"></td>
                                <th style="width: 15%; background-color: #eee">Tanggal</th>
                                <td id="d_date"></td>
                            </tr>
                        </table>
                        <ul class="nav nav-tabs nav-tabs-custom nav-justified" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#home1" role="tab">
                                    <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                                    <span class="d-none d-sm-block">Hari Kerja</span>
                                </a>
                            </li>

                            <?php if($role == 'admin' || $now >= $date_select){ ?>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#profile1" role="tab">
                                        <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                                        <span class="d-none d-sm-block">Waktu Kerja</span>
                                    </a>
                                </li>

                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#tab3" role="tab">
                                        <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                                        <span class="d-none d-sm-block">Waktu Sholat</span>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>

                        <!-- Tab panes -->
                        <div class="tab-content p-3 text-muted">
                            <div class="tab-pane active" id="home1" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        Jadwal Saat Ini
                                        <h6 id="current_schedule">
                                            <?php if(empty($payroll)){ ?>
                                                <select class="form-control" name="shift_id" required="" id="shift">
                                                    <option value="">Pilih</option>
                                                    <option data-code="free" value="free">LIBUR</option>
                                                    <?php foreach ($shift as $row) { ?>
                                                        <option data-code="<?= $row['shift_code'] ?>" value="<?= $row['id'] ?>"><?= $row['shift_code']." / ".$row['shift_name'] ?></option>
                                                    <?php } ?>
                                                </select>

                                            <?php }else{ ?>
                                                <h5 id="d_shift"></h5>
                                            <?php } ?>
                                            
                                        </h6>
                                    </div>
                                    <div class="col-md-6">
                                        <!--Jadwal Asli
                                        <h6 id="real_schedule"></h6>-->
                                    </div>
                                </div>

                                <?php if(empty($payroll)){ ?>
                                    <hr>

                                    <div class="row">
                                        <div class="col-md-12 text-end">
                                            <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>

                                            <?php if($role == 'admin' || $role == 'admin-branch' || $role == 'supervisor'){ ?>
                                                <button id="btnUpdate" class="btn btn-warning waves-effect waves-light"><i class="fa fa-check"></i> Ubah</button>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php } ?>

                            </div>
                            <div class="tab-pane" id="profile1" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-12" id="hour_alert"></div>
                                </div>

                                <div id="bodyPresence">
                                    <div class="row mt-2">
                                        <div class="col-md-12">
                                            <h6><i class="dripicons-clock"></i> KERJA</h6>
                                        </div>
                                        <div class="col-md-4 inputPresence">
                                            <label>Jam Masuk</label>
                                            
                                            <?php if(empty($payroll) && (in_array($role, ['admin', 'admin-branch']))){ ?>
                                                <input <?= $role != 'admin' ? 'disabled' : '' ?> readonly type="text" name="entry_time" id="entry_time">
                                                <small><a class="text-danger" href="javascript:void(0)" onclick="$('#entry_time').val('')"><i class="fa fa-times"></i> Hapus</a></small>

                                            <?php }else{ ?>
                                                <h5 id="d_entry_time"><span></span></h5>
                                            <?php } ?>
                                        </div>
                                        <div class="col-md-4 inputPresence">
                                            <label>Jam Selesai</label>

                                            <?php if(empty($payroll) && (in_array($role, ['admin', 'admin-branch']))){ ?>
                                                <input <?= $role != 'admin' ? 'disabled' : '' ?> readonly type="text" name="out_time" id="out_time">
                                                <small><a class="text-danger" href="javascript:void(0)" onclick="$('#out_time').val('')"><i class="fa fa-times"></i> Hapus</a></small>

                                            <?php }else{ ?>
                                                <h5 id="d_out_time"><span></span></h5>
                                            <?php } ?>
                                        </div>
                                        <div class="col-md-4 inputPresence">
                                            <label>Keterlambatan</label>
                                            <h6 id="entry_time_late"></h6>
                                        </div>

                                        <?php if(empty($payroll) && ($role == 'admin' || $role == 'admin-branch')){ ?>
                                            <div class="col-md-6 mt-3">
                                                <a href="javascript:void(0)" id="btnTimeSheet" class="btn btn-outline-info btn-sm"><i class="fa fa-clock"></i> Input Otomatis Waktu Kerja</a>

                                            </div>
                                            <div class="col-md-6">
                                                <input class="mt-3" type="checkbox" name="overtime" id="overtime"> Lembur <br>
                                                <small>*Waktu kerja dianggap jam lembur</small>
                                            </div>
                                            <div class="col-md-12 mt-2">
                                                <input type="checkbox" name="is_early_leave" id="is_early_leave"> Izin Pulang Cepat (PLA) <br>
                                                <small>*Centang supaya bisa input Jam Selesai sebelum jam pulang shift.<br>
                                                Kekurangan jam dihitung otomatis dan jadi potongan gaji.
                                                Jika jam kerja efektif &lt; 5 jam, kehadiran ditandai TIDAK HADIR.</small>
                                                <div class="mt-1" id="early_leave_short_wrap" style="display:none">
                                                    <small><b>Kekurangan jam:</b> <span id="early_leave_short_text">-</span></small>
                                                </div>
                                            </div>
                                        <?php } ?>

                                        <div class="col-md-12 mt-3">
                                            <h6><i class="dripicons-clock"></i> ISTIRAHAT</h6>
                                        </div>
                                        <div class="col-md-4 inputPresence">
                                            <label>Jam Masuk</label>

                                            <?php if(empty($payroll) && (in_array($role, ['admin', 'admin-branch']))){ ?>
                                                <input <?= $role != 'admin' ? 'disabled' : '' ?> readonly type="text" name="rest_time_in" id="rest_time_in">
                                                <small><a class="text-danger" href="javascript:void(0)" onclick="$('#rest_time_in').val('')"><i class="fa fa-times"></i> Hapus</a></small>

                                            <?php }else{ ?>
                                                <h5 id="d_rest_time_in"><span></span></h5>
                                            <?php } ?>
                                        </div>
                                        <div class="col-md-4 inputPresence">
                                            <label>Jam Selesai</label>
                                            
                                            <?php if(empty($payroll) && (in_array($role, ['admin', 'admin-branch']))){ ?>
                                                <input <?= $role != 'admin' ? 'disabled' : '' ?> readonly type="text" name="rest_time_out" id="rest_time_out">
                                                <small><a class="text-danger" href="javascript:void(0)" onclick="$('#rest_time_out').val('')"><i class="fa fa-times"></i> Hapus</a></small>
                                            <?php }else{ ?>
                                                <h5 id="d_rest_time_out"><span></span></h5>
                                            <?php } ?>
                                        </div>
                                        <div class="col-md-4 inputPresence">
                                            <label>Keterlambatan</label>
                                            <h6 id="rest_time_late"></h6>
                                        </div>

                                        <div class="col-md-6 mt-3">
                                            <?php if(empty($payroll) && ($role == 'admin' || $role == 'admin-branch')){ ?>
                                                <a href="javascript:void(0)" id="btnRestSheet" class="btn btn-outline-info btn-sm"><i class="fa fa-clock"></i> <span style="font-size: 12px">Input Otomatis Waktu Istirahat</span></a>
                                            <?php } ?>
                                        </div>

                                        <div class="col-md-6 mt-3" id="bodyPresence-created-at">
                                            <b>Terakhir update :</b><br>
                                            <small id="presence-created-at"><b></b></small>
                                        </div>

                                    </div>

                                    <?php if(empty($payroll)){ ?>
                                    
                                        <hr>

                                        <div class="row">
                                            <div class="col-md-7">
                                                <?php if($role == 'admin'){ ?>
                                                    <a href="javascript:void(0)" id="cancel_presence" class="btn btn-outline-danger"><i class="fa fa-times-circle"></i> Batalkan Kehadiran</a>
                                                <?php } ?>
                                            </div>

                                            <div class="col-md-5 text-end">
                                                <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>

                                                <?php if($role == 'admin'){ ?>
                                                    <button id="btnUpdate_workhour" class="btn btn-warning waves-effect waves-light"><i class="fa fa-check"></i> Ubah</button>
                                                <?php } ?>
                                                
                                            </div>
                                        </div>
                                    <?php } ?>

                                </div>
                            </div>

                            <div class="tab-pane" id="tab3" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-12" id="pray_alert"></div>
                                </div>

                                <div id="bodyPray">
                                    <div class="row mt-2">

                            <?php 
                                foreach ($param as $row) { 
                                    $title = $row == 'friday' ? 'Jum\'at' : $row; ?>

                                    <div class="col-md-12 mt-2">
                                          <label><?= strtoupper($title) ?></label>
                                    </div>

                                      <div class="col-md-4">
                                        <div class="mb-3">
                                           <label>Mulai Izin</label>

                                            <?php if(empty($payroll) && (in_array($role, ['admin', 'admin-branch']))){ ?>
                                                <input type="text" name="<?= $row."_time_in" ?>" id="<?= $row."_time_in" ?>"  readonly="" class="timepicker" placeholder="--:--" autocomplete="off" />

                                                <small><a class="text-danger" href="javascript:void(0)" onclick="$('#<?= $row."_time_in" ?>').val('')"><i class="fa fa-times"></i> Hapus</a></small>

                                            <?php }else{ ?>
                                                <h5 id="d_<?= $row ?>_time_in"><span></span></h5>
                                            <?php } ?>
                                        </div>
                                      </div>

                                      <div class="col-md-4">
                                        <div class="mb-3">
                                           <label>Selesai Izin</label>
                                           <?php if(empty($payroll) && (in_array($role, ['admin', 'admin-branch']))){ ?>
                                                <input type="text" name="<?= $row."_time_out" ?>" id="<?= $row."_time_out" ?>" readonly="" class="timepicker" placeholder="--:--" autocomplete="off" />
                                                <small><a class="text-danger" href="javascript:void(0)" onclick="$('#<?= $row ?>_time_out').val('')"><i class="fa fa-times"></i> Hapus</a></small>
                                            <?php }else{ ?>
                                                <h5 id="d_<?= $row ?>_time_out"><span></span></h5>
                                            <?php } ?>
                                        </div>
                                      </div>

                                      <div class="col-md-4">
                                        <div class="mb-3">
                                           <label>Keterlambatan</label>
                                           <h6 id="<?= $row."_time_late" ?>"></h6>
                                        </div>
                                      </div>

                                      <hr>
                            <?php } ?>
                                    </div>

                            <?php if(empty($payroll)){ ?>
                                    <hr>

                                    <div class="row">
                                        <div class="col-md-7">
                                        </div>

                                        <div class="col-md-5 text-end">
                                            <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>

                                            <?php if($role == 'admin' || $role == 'admin-branch'){ ?>
                                                <button id="btnUpdate_workpray" class="btn btn-warning waves-effect waves-light"><i class="fa fa-check"></i> Ubah</button>
                                            <?php } ?>
                                            
                                        </div>
                                    </div>
                            <?php } ?>

                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
</form>

<?php if($this->role == 'admin' || $status == true){ ?>
<form id="formDelete" method="POST" action="<?= site_url('resetSchedule/'.$month.'/'.$year) ?>">
<div class="modal fade" id="modalReset" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="staticBackdropLabel" aria-hidden="true">

<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">

<input type="hidden" name="branch_id" value="<?= $branch_id ?>">

    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title" id="staticBackdropLabel" style="color: #fff">Reset Shift Default</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-3">
                        <img src="<?= base_url('assets/images/icon/question.png') ?>" class="img-fluid">
                    </div>
                    <div class="col-md-9">
                        <h6>Apakah anda yakin melakukan reset shift untuk bulan ini ?</h6>
                        Data penjadwalan kerja atau shift karyawan akan dikembalikan seperti semula sesuai settingan default dari system.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tidak</button>
                <button class="btn btn-danger" id="btnDelete">Ya, Kembalikan jadwal default</button>
            </div>
        </div>
    </div>
</div>
</form>
<?php } ?>

<div class="modal fade" id="modalUpload" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staticBackdropLabel">Upload Berkas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs nav-tabs-custom nav-justified" role="tablist">
                    <?php if(in_array($role, ['admin', 'admin-branch'])){ ?>
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#tab-presensi" role="tab">
                                <span class="d-block d-sm-none"><i class="fas fa-calendar"></i></span>
                                <span class="d-none d-sm-block">Presensi</span>
                            </a>
                        </li>
                    <?php } ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $role == 'supervisor' ? 'active' : '' ?>" data-bs-toggle="tab" href="#tab-kerja" role="tab">
                            <span class="d-block d-sm-none"><i class="far fa-clock"></i></span>
                            <span class="d-none d-sm-block">Jadwal Kerja</span>
                        </a>
                    </li>
                </ul>

                <!-- Tab panes -->
                <div class="tab-content p-3 text-muted">
                    <?php if(in_array($role, ['admin', 'admin-branch'])){ ?>
                        <div class="tab-pane active" id="tab-presensi" role="tabpanel">
                            <ul class="nav nav-tabs nav-tabs-custom nav-justified" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" data-bs-toggle="tab" href="#tab-upload-kerja" role="tab">
                                        <span class="d-block d-sm-none"><i class="fas fa-calendar"></i></span>
                                        <span class="d-none d-sm-block">Waktu Kerja</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#tab-upload-sholat" role="tab">
                                        <span class="d-block d-sm-none"><i class="far fa-clock"></i></span>
                                        <span class="d-none d-sm-block">Waktu Sholat</span>
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content p-3 text-muted">
                                <div class="tab-pane active" id="tab-upload-kerja" role="tabpanel">
                                    <form id="formUpload" method="POST">
                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">

                                        <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
                                        <input type="hidden" name="month" value="<?= $month ?>">
                                        <input type="hidden" name="year" value="<?= $year ?>">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <center>
                                                    <img style="width: 70px" src="<?= base_url('assets/images/icon/team.png') ?>">
                                                </center>
                                            </div>
                                            <div class="col-md-9">
                                                <h6>File Excel</h6>
                                                <input type="file" required="" name="excel_file"> <br>
                                                <small class="text-muted">Silahkan upload file excel yang sudah didownload dari mesin fingerprint</small> <br><br>
                                                <button class="btn btn-success" id="btnUpload">Upload Presensi Kerja</button> &nbsp;
                                                <a href="<?= base_url('assets/Template_Presensi.xlsx') ?>" class="btn btn-outline-danger"><i class="fa fa-download"></i>  Template</a>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <div class="tab-pane" id="tab-upload-sholat" role="tabpanel">
                                    <form id="formUploadSholat" method="POST">
                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">

                                        <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
                                        <input type="hidden" name="month" value="<?= $month ?>">
                                        <input type="hidden" name="year" value="<?= $year ?>">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <center>
                                                    <img style="width: 70px" src="<?= base_url('assets/images/icon/praying.png') ?>">
                                                </center>
                                            </div>
                                            <div class="col-md-9">
                                                <h6>File Excel</h6>
                                                <input type="file" required="" name="excel_file"> <br>
                                                <small class="text-muted">Silahkan upload file excel yang sudah didownload dari mesin fingerprint</small> <br><br>
                                                <button class="btn btn-success" id="btnUploadPray">Upload Presensi Sholat</button> &nbsp;
                                                <a href="<?= base_url('assets/Template_Presensi.xlsx') ?>" class="btn btn-outline-danger"><i class="fa fa-download"></i> Template</a>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                <?php } ?>

                    <div class="tab-pane <?= $role == 'supervisor' ? 'active' : '' ?>" id="tab-kerja" role="tabpanel">
                        <form id="formUploadWork" method="POST">
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">

                            <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
                            <input type="hidden" name="month" value="<?= $month ?>">
                            <input type="hidden" name="year" value="<?= $year ?>">
                            <div class="row">
                                <div class="col-md-3">
                                    <center>
                                        <img style="width: 70px" src="<?= base_url('assets/images/icon/schedule.png') ?>">
                                    </center>
                                </div>
                                <div class="col-md-9">
                                    <h6>File Excel</h6>
                                    <input type="file" required="" name="excel_file"> <br>
                                    <small class="text-muted">Silahkan upload file excel yang sudah diisi dengan list jadwal kerja</small> <br><br>
                                    <button class="btn btn-success" id="btnUploadWork">Upload Jadwal</button> &nbsp;
                                    <a href="<?= site_url('export_work_schedule/'.$month.'/'.$year.'/'.$branch_id) ?>" class="btn btn-outline-danger"><i class="fa fa-download"></i> Download Template</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var branch_id = "<?= $this->input->get('branch_id') ? '?branch_id='.$this->input->get('branch_id') : '' ?>";
    $(document).on('click', '#btnModalUpload', function(){
        $('#modalUpload').modal('show');
    });

    $(document).on('click', '.attendance', function(){
        $('#hour_alert').html('');
        var a = $(this);
        var id   = a.attr('data-id');
        var name = a.attr('data-workcode') + " / " + a.attr('data-workname');
        $('#d_employee').html(a.data('name')+"<br><small class='text-muted'>"+a.data('code')+"</small>");
        $('#d_date').text(a.data('date'));
        $('#cancel_presence').hide();

        <?php if(empty($payroll)){ ?>
            $('#shift option').removeAttr('selected');
            $('#shift option[data-code="'+ a.attr('data-workcode') +'"]').attr('selected', 'selected');

        <?php }else{ ?>
            var workname = a.attr('data-workcode') == 'free' ? 'LIBUR' : name;
            $('#d_shift').text(workname);
        <?php } ?>
        
        $('#user_id').val(a.attr('data-id'));
        $('#date').val(a.attr('data-date'));

        $('#btnTimeSheet').attr('data-workstart', a.attr('data-workstart')).attr('data-workend', a.attr('data-workend'));
        $('#btnRestSheet').attr('data-reststart', a.attr('data-reststart')).attr('data-restend', a.attr('data-restend'));

        var schedule = a.attr('data-type') == 'work' ? name : 'LIBUR';
        //$('#real_schedule').text(schedule);
        $('#modalAttendance').modal('show');
        $('#entry_time').val('');
        $('#out_time').val('');

        $('#cancel_presence').attr('data-row', a.attr('data-row'));
        $('#row_id').val(a.attr('data-row'));

        $('#overtime').prop('checked', a.attr('data-presence-overtime') == '1' ?  true : false);

        var elIsEarly = a.attr('data-presence-is-early-leave') == '1';
        var elShort   = parseInt(a.attr('data-presence-early-leave-short') || '0', 10);
        $('#is_early_leave').prop('checked', elIsEarly);
        if(elIsEarly && elShort > 0){
            var h = Math.floor(elShort / 60), m = elShort % 60;
            $('#early_leave_short_text').text(h + 'j ' + m + 'm');
            $('#early_leave_short_wrap').show();
        }else{
            $('#early_leave_short_wrap').hide();
        }

        if(a.attr('data-presence-id') != ''){
            $('#bodyPresence-created-at').show();
            $('#presence-created-at').text(a.attr('data-presence-created-at'));

        }else{
            $('#bodyPresence-created-at').hide();
        }

        if(a.attr('data-type') == 'work'){
            if(a.data('presence-type') != ''){
                $('#hour_alert').html("<center><img src='<?= base_url("assets/images/icon/times.png") ?>' style='width:50px'> <h5 class='text-center text-danger mt-2'><i class='fa fa-times-circle'></i> Jadwal sudah diberikan perizinan</h5><span class='text-muted'>Tidak dapat menambahkan waktu kerja karena jadwal yang anda pilih sudah diberi perizinan</span></center>");
                $('#bodyPresence').hide();

                $('#pray_alert').html("<center><img src='<?= base_url("assets/images/icon/times.png") ?>' style='width:50px'> <h5 class='text-center text-danger mt-2'><i class='fa fa-times-circle'></i> Jadwal sudah diberikan perizinan</h5><span class='text-muted'>Tidak dapat menambahkan waktu sholat karena jadwal yang anda pilih sudah diberi perizinan</span></center>").show();
                $('#bodyPray').hide();
                $('.inputPresence').hide();

            }else{
                $('#bodyPresence').show();
                $('#bodyPray').show();
                $('.inputPresence').show();
                $('#pray_alert').hide();
            }

            if(a.attr('data-presence-id') != ''){
                $('#cancel_presence').attr('data-id', a.attr('data-presence-id')).show();
                var entry = a.attr('data-presence-entry-time');
                var out   = a.attr('data-presence-out-time');
            }else{
                var entry = a.attr('data-presence-half');
                var out   = '';
            }

            <?php if((empty($payroll) && in_array($role, ['admin', 'admin-branch']))){ ?>
                $('#entry_time').val(entry);
                $('#out_time').val(out);
                $('#rest_time_in').val(a.attr('data-rest-time-in'));
                $('#rest_time_out').val(a.attr('data-rest-time-out'));

            <?php }else{ ?>
                var rest_in = a.attr('data-rest-time-in') != '' ? a.attr('data-rest-time-in') : '-';
                var rest_out = a.attr('data-rest-time-out') != '' ? a.attr('data-rest-time-out') : '-';
                var entry_in = a.attr('data-presence-entry-time') != '' ? a.attr('data-presence-entry-time') : '-';
                var entry_out = a.attr('data-presence-out-time') != '' ? a.attr('data-presence-out-time') : '-';

                $('#d_entry_time').text(entry_in);
                $('#d_out_time').text(entry_out);
                $('#d_rest_time_in').text(rest_in);
                $('#d_rest_time_out').text(rest_out);
            <?php } ?>
            
            $('#entry_time_late').text(a.attr('data-presence-entry-late')+" Menit");
            $('#rest_time_late').text(a.attr('data-rest-time-late')+" Menit");

            var param = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];
            for (var i = param.length - 1; i >= 0; i--){
                <?php if(empty($payroll) && in_array($role, ['admin', 'admin-branch'])){ ?>
                    $('#'+param[i]+"_time_in").val(a.attr('data-'+param[i]+'-time-in'));
                    $('#'+param[i]+"_time_out").val(a.attr('data-'+param[i]+'-time-out'));

                <?php }else{ ?>
                    var pray_in = a.data(param[i]+'-time-in') != '' ? a.data(param[i]+'-time-in') : '-';
                    var pray_out = a.data(param[i]+'-time-out') != '' ? a.data(param[i]+'-time-out') : '-';
                    $('#d_'+param[i]+"_time_in").text(pray_in);
                    $('#d_'+param[i]+"_time_out").text(pray_out);
                <?php } ?>

                $('#'+param[i]+"_time_late").text(a.attr('data-'+param[i]+'-time-late')+ " Menit");
            }

        }else{
            if(schedule == 'LIBUR'){
                $('#hour_alert').html("<center><img src='<?= base_url("assets/images/icon/times.png") ?>' style='width:50px'> <h5 class='text-center text-danger mt-2'><i class='fa fa-times-circle'></i> Tidak dapat memasukkan waktu kehadiran</h5><span class='text-muted'>Jadwal yang dipilih saat ini adalah hari LIBUR, harap ubah jadwal terlebih dulu</span></center>");
                $('#bodyPresence').hide();

                $('#pray_alert').html("<center><img src='<?= base_url("assets/images/icon/times.png") ?>' style='width:50px'> <h5 class='text-center text-danger mt-2'><i class='fa fa-times-circle'></i> Tidak dapat memasukkan waktu sholat</h5><span class='text-muted'>Jadwal yang dipilih saat ini adalah hari LIBUR, harap ubah jadwal terlebih dulu</span></center>").show();
                $('#bodyPray').hide();

            }else{
                $('#bodyPresence').show();
                $('#bodyPray').show();
            }
        } 
    });

    $(document).on('click', '#cancel_presence', function(){
        r = confirm('Apakah kamu yakin membatalkan kehadiran ini ?');
        if(r){
            var btn = $('#cancel_presence');
            $.ajax({
                url         : "<?= site_url('cancel_presence') ?>",
                dataType    : "json",
                method      : "POST",
                data        : {
                    presence_id : $(this).attr('data-id'),
                    myToken     : "<?php echo $this->security->get_csrf_hash() ?>"
                },
                beforeSend  : function(){
                    btn.html(show_loading()).attr('disabled', 'disabled');
                },
                success : function(res){
                    $('#modalAttendance').modal('hide');
                    if(res.status){
                        $('#'+$('#row_id').val()).attr('style', 'background-color:#f46a6a;cursor:pointer').attr('data-presence-entry-time', '').attr('data-presence-out-time','').attr('data-presence-id','');
                        $('#'+$('#row_id').val()+"_input_by").text('');
                        $('#overtime_'+ $('#row_id').val()).text('');
                        //$('#formUpdate')[0].reset();
                        //window.location.reload();
                    }

                    var type = (res.status) ? 'success' : 'info';
                    show_modal(type, res.message);
                },
                complete : function(){
                    btn.html('<i class="fa fa-times-circle"></i> Batalkan Kehadiran').removeAttr('disabled');
                }
            });
        }
    })

    var total_present = <?= $num_present ?>;
    var total_work    = <?= $all_work ?>;
    var progress      = (total_present / total_work) * 100;

    var percent = total_work == 0 ? 0 : progress.toFixed(2);

    $('#progress').attr('style', 'width : ' + percent + '%');
    $('#string_progress').text(percent+" %");

    <?php if($role == 'admin' || $role == 'supervisor' || $date_select > $now){ ?>

    $(document).on('click', '#btnUpdate', function(e){
        e.preventDefault();

        var status  = true;
        var btn     = $('#btnUpdate');
        var message = "";

        $.ajax({
            url         : "<?= site_url('update_presence') ?>",
            dataType    : "json",
            method      : "POST",
            data        : {
                user_id  : $('#user_id').val(),
                date     : $('#date').val(),
                shift_id : $('#shift').val(),
                row_id   : $('#row_id').val(),
                myToken     : "<?php echo $this->security->get_csrf_hash() ?>"
            },
            beforeSend  : function(){
                btn.html(show_loading()).attr('disabled', 'disabled');
            },
            success : function(res){
                $('#modalAttendance').modal('hide');
                var txt   = '';
                var color = '';
                var r = res.row;

                if(res.status){
                    if(r.type == 'free'){
                        if(!r.presence){
                            $('#'+r.id).attr('style', 'background-color:#cdcdcd;cursor:pointer');
                            $('#'+r.id+"_input_by").text('')
                        }

                    }else{
                        txt = r.shift_code != 'free' ? r.shift_code : '';
                        if(!r.presence){
                            $('#'+r.id).attr('style', 'background-color:#f46a6a;cursor:pointer');
                        }
                        
                    }

                    $('#'+r.id+"_code").text(txt);
                    $('#'+r.id).attr('data-type', r.type).attr('data-workcode', r.shift_code);
                    //$('#formUpdate')[0].reset();
                    //window.location.reload();
                }

                var type = (res.status) ? 'success' : 'info';
                show_modal(type, res.message);
            },
            complete : function(){
                btn.html('Ubah').removeAttr('disabled');
            }
        });

        return false;
    });

    $(document).on('click', '#btnUpdate_workhour', function(e){
        e.preventDefault();

        var status  = true;
        var btn     = $('#btnUpdate_workhour');
        var message = "";
        var overtime = $('#overtime').prop("checked") ? '1' : '0';
        var isEarlyLeave = $('#is_early_leave').prop("checked") ? '1' : '0';

        $.ajax({
            url         : "<?= site_url('update_workhour') ?>",
            dataType    : "json",
            method      : "POST",
            data        : {
                user_id    : $('#user_id').val(),
                date       : $('#date').val(),
                entry_time : $('#entry_time').val(),
                out_time   : $('#out_time').val(),
                rest_time_in : $('#rest_time_in').val(),
                rest_time_out : $('#rest_time_out').val(),
                row_id     : $('#row_id').val(),
                overtime   : overtime,
                is_early_leave : isEarlyLeave,
                myToken    : "<?php echo $this->security->get_csrf_hash() ?>"
            },
            beforeSend  : function(){
                btn.html(show_loading()).attr('disabled', 'disabled');
            },
            success : function(res){
                $('#modalAttendance').modal('hide');
                if(res.status){
                    var r = res.callback;
                    var row_id = $('#row_id').val();

                    $('#'+row_id).attr('style', 'background-color:#' + r.color +';cursor:pointer').attr('data-presence-entry-time', r.entry_time).attr('data-presence-out-time', r.out_time).attr('data-presence-id', r.id);

                    $('#'+row_id).attr('data-presence-entry-late', r.entry_time_late);

                    var ovt = r.overtime == '1' ? 'lembur' : '';
                    $('#overtime_'+$('#row_id').val()).text(ovt);

                    $('#'+row_id).attr('data-presence-overtime', r.overtime);
                    $('#'+row_id).attr('data-presence-created-at', r.update);
                    $('#'+row_id).attr('data-rest-time-in', r.rest_in);
                    $('#'+row_id).attr('data-rest-time-out', r.rest_out);
                    $('#'+row_id).attr('data-rest-time-late', r.rest_time_late);

                    if(!r.late_work_status){
                        $('#late_work_status_'+ row_id).remove();
                    }else{
                        $('#late_work_status_body_'+ row_id).html("<span style='border: 0.2px solid #5b73e8' class='badge bg-primary late_work_status' id='late_work_status_"+row_id+"'>K</span>")
                    }

                    // PLA badge: tampil/hilang sesuai is_early_leave dan presence_status != deny.
                    var elShort = parseInt(r.early_leave_short_minutes || 0, 10);
                    var elIs    = (r.is_early_leave == '1' || r.is_early_leave === 1) && r.presence_status !== 'deny';
                    if(elIs){
                        var shortLabel = '';
                        if(elShort > 0){
                            shortLabel = ' (-' + Math.floor(elShort/60) + 'j ' + (elShort % 60) + 'm)';
                        }
                        $('#early_leave_status_body_'+ row_id).html(
                            "<span style='border: 0.2px solid #5b73e8' class='badge bg-primary early_leave_status' id='early_leave_status_"+row_id+"' title='Izin Pulang Lebih Awal"+shortLabel+"'>PLA</span>"
                        );
                    }else{
                        $('#early_leave_status_body_'+ row_id).empty();
                    }
                    $('#'+row_id).attr('data-presence-is-early-leave', elIs ? '1' : '0');
                    $('#'+row_id).attr('data-presence-early-leave-short', elShort);
                    $('#'+row_id).attr('data-presence-status', r.presence_status || '');

                    //$('#formUpdate')[0].reset();
                    //window.location.reload();
                }

                var type = (res.status) ? 'success' : 'info';
                show_modal(type, res.message);
            },
            complete : function(){
                btn.html('Ubah').removeAttr('disabled');
            }
        });

        return false;
    });

    $(document).on('click', '#btnUpdate_workpray', function(e){
        e.preventDefault();

        var status  = true;
        var btn     = $('#btnUpdate_workpray');
        var message = "";
        var overtime = $('#overtime').prop("checked") ? '1' : '0';
        
        $.ajax({
            url         : "<?= site_url('update_workpray') ?>" + branch_id,
            dataType    : "json",
            method      : "POST",
            data        : {
                user_id             : $('#user_id').val(),
                date                : $('#date').val(),
                subuh_time_in       : $('#subuh_time_in').val(),
                subuh_time_out      : $('#subuh_time_out').val(),
                dzuhur_time_in      : $('#dzuhur_time_in').val(),
                dzuhur_time_out     : $('#dzuhur_time_out').val(),
                ashar_time_in       : $('#ashar_time_in').val(),
                ashar_time_out      : $('#ashar_time_out').val(),
                maghrib_time_in     : $('#maghrib_time_in').val(),
                maghrib_time_out    : $('#maghrib_time_out').val(),
                isha_time_in        : $('#isha_time_in').val(),
                isha_time_out       : $('#isha_time_out').val(),
                friday_time_in      : $('#friday_time_in').val(),
                friday_time_out     : $('#friday_time_out').val(),
                myToken    : "<?php echo $this->security->get_csrf_hash() ?>"
            },
            beforeSend  : function(){
                btn.html(show_loading()).attr('disabled', 'disabled');
            },
            success : function(res){
                $('#modalAttendance').modal('hide');

                if(res.status){
                    var r = res.callback;
                    var row_id = $('#row_id').val();
                    var com = $('#'+$('#row_id').val());
                    com.attr('data-subuh-time-in', r.subuh_time_in);
                    com.attr('data-subuh-time-out', r.subuh_time_out);
                    com.attr('data-subuh-time-late', r.subuh_time_late);

                    com.attr('data-dzuhur-time-in', r.dzuhur_time_in);
                    com.attr('data-dzuhur-time-out', r.dzuhur_time_out);
                    com.attr('data-dzuhur-time-late', r.dzuhur_time_late);

                    com.attr('data-maghrib-time-in', r.maghrib_time_in);
                    com.attr('data-maghrib-time-out', r.maghrib_time_out);
                    com.attr('data-maghrib-time-late', r.maghrib_time_late);

                    com.attr('data-isha-time-in', r.isha_time_in);
                    com.attr('data-isha-time-out', r.isha_time_out);
                    com.attr('data-isha-time-late', r.isha_time_late);

                    com.attr('data-friday-time-in', r.friday_time_in);
                    com.attr('data-friday-time-out', r.friday_time_out);
                    com.attr('data-friday-time-late', r.friday_time_late);

                    if(!r.pray_late){
                        $('#late_pray_status_'+$('#row_id').val()).remove();
                    }else{
                        $('#late_pray_status_body_'+ row_id).html("<span style='border: 0.5px solid #5b73e8' class='badge bg-info late_pray_status' id='late_pray_status_"+row_id+"'>S</span>")
                    }

                }
                var type = (res.status) ? 'success' : 'info';
                show_modal(type, res.message);
            },
            complete : function(){
                btn.html('Ubah').removeAttr('disabled');
            }
        });

        return false;
    });

    <?php } ?>

    <?php if($date_select == $now || in_array($role, ['admin', 'admin-branch', 'supervisor'])){ ?>
        $(document).on('submit', '#formUpload', function(e){
            e.preventDefault();
            var status  = true;
            var btn     = $('#btnUpload');
            var message = "";

            var formData = new FormData(this);

            $.ajax({
                url         : "<?= site_url('upload_presence') ?>",
                dataType    : "json",
                method      : "POST",
                data        : formData,
                processData : false,
                contentType : false,
                beforeSend  : function(){
                    btn.html(show_loading()).attr('disabled', 'disabled');
                },
                success : function(res){
                    $('#modalUpload').modal('hide');

                    var type = (res.status) ? 'success' : 'info';
                    show_modal(type, res.message);

                    if(res.status){
                        window.location.reload();
                    }
                },
                complete : function(){
                    btn.html('Upload Presensi').removeAttr('disabled');
                }
            });

            return false;
        });

        $(document).on('click', '#btnSyncCloud', function(e){
            e.preventDefault();

            var btn = $('#btnSyncCloud');
            var originalText = btn.html();

            $.ajax({
                url      : "<?= site_url('sync_presence_cloud') ?>",
                dataType : "json",
                method   : "POST",
                data     : buildSyncData(true),
                beforeSend : function(){
                    $('#btnModalUpload, #btnSyncCloud, #btnSyncPrayCloud, #btnClearPresence').attr('disabled', 'disabled').addClass('disabled');
                    btn.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Sync reguler...');
                    show_modal('info', 'Sedang mengambil data presensi reguler dari mesin absensi. Mohon tunggu sampai proses selesai.');
                },
                success : function(res){
                    var type = (res.status) ? 'success' : 'info';
                    show_modal(type, res.message);

                    if(res.status){
                        window.location.reload();
                    }
                },
                complete : function(){
                    $('#btnModalUpload, #btnSyncCloud, #btnSyncPrayCloud, #btnClearPresence').removeAttr('disabled').removeClass('disabled');
                    btn.html(originalText);
                }
            });

            return false;
        });

        $(document).on('click', '#btnSyncPrayCloud', function(e){
            e.preventDefault();

            var btn = $('#btnSyncPrayCloud');
            var originalText = btn.html();

            $.ajax({
                url      : "<?= site_url('sync_pray_cloud') ?>",
                dataType : "json",
                method   : "POST",
                data     : buildSyncData(false),
                beforeSend : function(){
                    $('#btnModalUpload, #btnSyncCloud, #btnSyncPrayCloud, #btnClearPresence').attr('disabled', 'disabled').addClass('disabled');
                    btn.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Sync sholat...');
                    show_modal('info', 'Sedang mengambil data presensi sholat dari mesin BWXP212161070. Mohon tunggu sampai proses selesai.');
                },
                success : function(res){
                    var type = (res.status) ? 'success' : 'info';
                    show_modal(type, res.message);

                    if(res.status){
                        window.location.reload();
                    }
                },
                complete : function(){
                    $('#btnModalUpload, #btnSyncCloud, #btnSyncPrayCloud, #btnClearPresence').removeAttr('disabled').removeClass('disabled');
                    btn.html(originalText);
                }
            });

            return false;
        });

        var autoSyncTimer = null;
        var autoSyncRunning = false;
        var autoSyncStorageKey = 'presence_auto_sync_<?= $branch_id ?>_<?= $month ?>_<?= $year ?>';
        var useScheduleStorageKey = 'presence_use_schedule_<?= $branch_id ?>';
        var syncFromStorageKey = 'presence_sync_from_<?= $branch_id ?>_<?= $month ?>_<?= $year ?>';

        function buildSyncData(includeSchedule){
            var data = {
                branch_id : "<?= $branch_id ?>",
                month     : "<?= $month ?>",
                year      : "<?= $year ?>",
                sync_from_date : $('#syncFromDate').length ? $('#syncFromDate').val() : '',
                myToken   : "<?php echo $this->security->get_csrf_hash() ?>"
            };

            if(includeSchedule){
                data.use_schedule = ($('#useScheduleSync').length == 0 || $('#useScheduleSync').is(':checked')) ? '1' : '0';
            }

            return data;
        }

        function setAutoSyncLabel(text){
            $('#autoSyncFive').closest('.form-check').find('label').text(text);
        }

        function runAutoSync(){
            if(autoSyncRunning){
                return;
            }

            autoSyncRunning = true;
            setAutoSyncLabel('Auto sync berjalan...');

            $.ajax({
                url      : "<?= site_url('sync_presence_cloud') ?>",
                dataType : "json",
                method   : "POST",
                data     : buildSyncData(true)
            }).always(function(){
                $.ajax({
                    url      : "<?= site_url('sync_pray_cloud') ?>",
                    dataType : "json",
                    method   : "POST",
                    data     : buildSyncData(false)
                }).always(function(){
                    autoSyncRunning = false;
                    setAutoSyncLabel($('#autoSyncFive').is(':checked') ? 'Auto sync aktif 5 menit' : 'Auto sync 5 menit');
                });
            });
        }

        function startAutoSync(runNow){
            clearInterval(autoSyncTimer);
            autoSyncTimer = setInterval(runAutoSync, 300000);
            setAutoSyncLabel('Auto sync aktif 5 menit');
            if(runNow){
                runAutoSync();
            }
        }

        $(document).on('change', '#autoSyncFive', function(){
            if($(this).is(':checked')){
                localStorage.setItem(autoSyncStorageKey, '1');
                startAutoSync(true);
            }else{
                localStorage.setItem(autoSyncStorageKey, '0');
                clearInterval(autoSyncTimer);
                autoSyncTimer = null;
                autoSyncRunning = false;
                setAutoSyncLabel('Auto sync 5 menit');
            }
        });

        $(document).on('change', '#useScheduleSync', function(){
            localStorage.setItem(useScheduleStorageKey, $(this).is(':checked') ? '1' : '0');
        });

        $(document).on('change', '#syncFromDate', function(){
            localStorage.setItem(syncFromStorageKey, $(this).val());
        });

        if(localStorage.getItem(syncFromStorageKey)){
            $('#syncFromDate').val(localStorage.getItem(syncFromStorageKey));
        }

        if(localStorage.getItem(useScheduleStorageKey) !== '0'){
            $('#useScheduleSync').prop('checked', true);
        }

        if(localStorage.getItem(autoSyncStorageKey) == '1'){
            $('#autoSyncFive').prop('checked', true);
            startAutoSync(true);
        }

        $(document).on('click', '#btnClearPresence', function(e){
            e.preventDefault();

            if(!confirm('Kosongkan data absen periode ini? Data bisa dimuat ulang lewat Upload atau Sync.')){
                return false;
            }

            var btn = $('#btnClearPresence');

            $.ajax({
                url      : "<?= site_url('clear_presence_period') ?>",
                dataType : "json",
                method   : "POST",
                data     : {
                    branch_id : "<?= $branch_id ?>",
                    month     : "<?= $month ?>",
                    year      : "<?= $year ?>",
                    myToken   : "<?php echo $this->security->get_csrf_hash() ?>"
                },
                beforeSend : function(){
                    btn.html(show_loading()).attr('disabled', 'disabled');
                },
                success : function(res){
                    var type = (res.status) ? 'success' : 'info';
                    show_modal(type, res.message);

                    if(res.status){
                        window.location.reload();
                    }
                },
                complete : function(){
                    btn.html('<i class="fa fa-trash"></i> Hapus').removeAttr('disabled');
                }
            });

            return false;
        });

        $(document).on('submit', '#formUploadSholat', function(e){
            e.preventDefault();
            var status  = true;
            var btn     = $('#btnUploadPray');
            var message = "";

            var formData = new FormData(this);

            $.ajax({
                url         : "<?= site_url('upload_pray') ?>",
                dataType    : "json",
                method      : "POST",
                data        : formData,
                processData : false,
                contentType : false,
                beforeSend  : function(){
                    btn.html(show_loading()).attr('disabled', 'disabled');
                },
                success : function(res){
                    $('#modalUpload').modal('hide');

                    var type = (res.status) ? 'success' : 'info';
                    show_modal(type, res.message);

                    if(res.status){
                        window.location.reload();
                    }
                },
                complete : function(){
                    btn.html('Upload Presensi').removeAttr('disabled');
                }
            });

            return false;
        });

        $(document).on('submit', '#formUploadWork', function(e){
            e.preventDefault();
            var status  = true;
            var btn     = $('#btnUploadWork');
            var message = "";

            var formData = new FormData(this);

            $.ajax({
                url         : "<?= site_url('upload_work_schedule') ?>",
                dataType    : "json",
                method      : "POST",
                data        : formData,
                processData : false,
                contentType : false,
                beforeSend  : function(){
                    btn.html(show_loading()).attr('disabled', 'disabled');
                },
                success : function(res){
                    $('#modalUpload').modal('hide');

                    var type = (res.status) ? 'success' : 'info';
                    show_modal(type, res.message);

                    if(res.status){
                        window.location.reload();
                    }
                },
                complete : function(){
                    btn.html('Upload Jadwal Kerja').removeAttr('disabled');
                }
            });

            return false;
        });
    <?php } ?>

    $(document).on('click', '#btnTimeSheet', function(){
        var a = $(this);
        $('#entry_time').val(a.attr('data-workstart'));
        $('#out_time').val(a.attr('data-workend'));
    })

    $(document).on('click', '#btnRestSheet', function(){
        var a = $(this);
        $('#rest_time_in').val(a.attr('data-reststart'));
        $('#rest_time_out').val(a.attr('data-restend'));
    })

    $(document).on('keyup', '#autocomplete', function(){
        var keyword = $(this).val();
        var subdivision = $('#subdivision').val();
        var position = $('#positionFilter').val();
        EmployeeFilter(keyword, subdivision, position); 
    });

    $(document).on('change', '#subdivision', function(){
        var keyword = $('#autocomplete').val();
        var subdivision = $(this).val();
        var position = $('#positionFilter').val();
        EmployeeFilter(keyword, subdivision, position);
    });

    $(document).on('change', '#positionFilter', function(){
        var keyword = $('#autocomplete').val();
        var subdivision = $('#subdivision').val();
        var position = $(this).val();
        EmployeeFilter(keyword, subdivision, position);
    });

    function EmployeeFilter(keyword, subdivision, position){
        $('#listPresence tr').each(function(){
            var count = 0;
            var searchKeyword = $(this).text().search(new RegExp(keyword, "i"));
            var searchSubdivision = false;
            var searchPosition = false;

            if($(this).data('subdivision') == subdivision || subdivision == 'all'){
                searchSubdivision = true;
            }

            if($(this).data('position') == position || position == 'all'){
                searchPosition = true;
            }

            if(searchKeyword < 0 || !searchSubdivision || !searchPosition) {
              $(this).hide(); 

            } else {
              $(this).show();
              count++;
            }
        });
    }
</script>

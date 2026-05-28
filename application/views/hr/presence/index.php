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
<!-- end page title -->

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title">Daftar Jadwal</h6>
            </div>
            <div class="card-body">
                <form>
                    <div class="row">
                        <div class="col-md-2">
                            <label>Tahun</label>
                            <select class="form-control" required="" name="year">
                                <?php 

                                $now = date('Y');
                                for ($i=2021; $i <= $now + 1; $i++) { 
                                    $selected = '';
                                    if($this->input->get('year')){
                                        if($this->input->get('year') == $i){
                                            $selected = 'selected="selected"';
                                        }
                                        
                                    }else{
                                        if($now == $i){
                                            $selected = 'selected="selected"';
                                        }
                                    }
                                ?>
                                    
                                    <option <?= $selected ?> value="<?= $i ?>"><?= $i ?></option>

                                <?php }

                                ?>
                            </select>
                        </div>
                        <div class="col-md-3"><br>
                            <button class="btn btn-primary mt-2"><i class="fa fa-search"></i> Lihat</button>
                        </div>
                    </div>
                </form>

                <div class="row mt-3">
                    <div class="col-md-12">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 5%" class="text-center">NO</th>
                                    <th>BULAN</th>
                                    <!--<th style="width: 25%" class="text-center">STATUS KETEPATAN WAKTU</th>-->
                                    <th style="width: 10%" class="text-center"><i class="fa fa-cog"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $n = 0; foreach($list as $row){ $n++; 
                                    if($row['month'] < 10){
                                        $row['month'] = "0".$row['month'];
                                    }

                                    $url = $row['month'].'/'.$year;
                                ?>
                                    <tr>
                                        <td><?= $n ?></td>
                                        <td><?= get_monthname($row['month'])." ".$year ?></td>
                                        <!--<td class="text-center">
                                            <div class="progress progress-xl animated-progess mb-1 p-1">
                                                <div class="progress-bar bg-info" role="progressbar" style="width: 5%" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <center><b style="margin-top:-30px">0%</b></center>
                                        </td>-->
                                        <td class="text-center">
                                            <a href="<?= site_url('hr/presence/'.$url) ?>" class="btn btn-sm btn-light" title="Lihat presensi">
                                                <i class="dripicons-search"></i>
                                            </a>
                                            <?php if(in_array($this->role, ['admin', 'admin-branch', 'hr', 'supervisor'])){ ?>
                                                <a href="<?= site_url('hr/work-schedule/'.$url) ?>" class="btn btn-sm btn-success" title="Atur jadwal kerja">
                                                    <i class="dripicons-calendar"></i>
                                                </a>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

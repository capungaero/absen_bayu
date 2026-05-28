<?php $role = $this->ion_auth->get_users_groups()->row()->name; ?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-flex align-items-center justify-content-between">
            <h4 class="mb-0"><i class="dripicons-experiments"></i> Penggajian</h4>

            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript:void(0);">Penggajian</a></li>
                    <li class="breadcrumb-item active"><a href="<?= site_url('hr/payroll') ?>">Daftar Penggajian</a></li>
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
                <h6 class="card-title">Daftar Penggajian</h6>
            </div>
            <div class="card-body">
                <?php if( $role != 'admin' && $role != 'admin-branch'){ ?>

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

                        <?php if($role == 'admin'){ ?>

                            <div class="col-md-4">
                                <label>Pilih Cabang</label>
                                <select class="form-control" name="branch_id" id="branch">
                                    <?php foreach ($branch as $row) { ?>
                                        <option <?= $branch_id == $row['id'] ? 'selected="selected"' : '' ?> value="<?= $row['id'] ?>"><?= $row['branch_code']." / ".$row['branch_name'] ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-3"><br>
                                <button class="btn btn-primary mt-2"><i class="fa fa-search"></i> Lihat</button>
                            </div>
                        <?php } ?>
                        
                        
                    </div>
                </form>

                <div class="row mt-3">
                    <div class="col-md-12">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width: 5%" class="text-center">NO</th>
                                    <th rowspan="2">BULAN</th>
                                    <th>KODE</th>
                                    <th class="text-center" style="width: 18%">JUMLAH KARYAWAN</th>
                                    <th style="width: 20%" class="text-center">TOTAL PENGGAJIAN</th>
                                    <th rowspan="2" style="width: 20%" class="text-center">WAKTU GENERATE</th>
                                    <th rowspan="2" style="width: 10%" class="text-center"><i class="fa fa-cog"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    $n = $total = 0; 
                                    $p = $this->input->get();
                                    $branch_string = isset($p['branch_id']) ? '?branch_id='.$p['branch_id'] : '';
                                    foreach($list as $row){ $n++;
                                        if($row['num'] < 10){
                                            $row['num'] = "0".$row['num'];
                                        }

                                        $url    = $row['num'].'/'.$row['year'].$branch_string;
                                        $total += $row['total_salary_thp'];
                                        
                                ?>
                                    <tr>
                                        <td><?= $n ?></td>
                                        <td><?= $row['month'] ?></td>
                                        <td><?= $row['payroll_code'] ?></td>
                                        <td class="text-center"><?= $row['id'] != '' ? $row['total_employee'] : '' ?></td>
                                        <td class="text-end"><?= $row['id'] != '' ? format_rp($row['total_salary_thp']) : '' ?></td>
                                        <td>
                                            <?= $row['id'] ? indonesian_date($row['created_at'], true) : '' ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?= site_url('hr/payroll/'.$url) ?>">
                                                <i class="dripicons-search"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php } ?>

                                <tr class="bg-light">
                                    <th colspan="3">TOTAL</th>
                                    <th class="text-end"><?= format_rp($total) ?></th>
                                    <th colspan="2"></th>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php }else{ ?>

                <form>
                        <div class="row">
                            <div class="col-md-2">
                                <label>Bulan</label>
                                <select class="form-control" required="" name="month">
                                    <?php 

                                    $now = $this->input->get('month') ? $this->input->get('month') : date('m');

                                    for ($i=1; $i <= 12; $i++) { 
                                    ?>
                                        
                                        <option <?= $i == $now ? "selected='selected'" : '' ?> value="<?= $i ?>"><?= get_monthname($i) ?></option>

                                    <?php }

                                    ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label>Tahun</label>
                                <select class="form-control" required="" name="year">
                                    <?php 

                                    $now = date('Y');
                                    for ($i=2021; $i <= $now; $i++) { 
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
                                        <th rowspan="2" style="width: 5%" class="text-center">NO</th>
                                        <th rowspan="2">CABANG</th>
                                        <th>KODE</th>
                                        <th class="text-center" style="width: 13%">JML KARYAWAN</th>
                                        <th style="width: 18%" class="text-center">TOTAL PENGGAJIAN</th>
                                        <th rowspan="2" style="width: 15%" class="text-center">WAKTU GENERATE</th>
                                        <th rowspan="2" style="width: 5%" class="text-center"><i class="fa fa-cog"></i></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        $n = $total = 0; 
                                        $p = $this->input->get();
                                        $branch_string = isset($p['branch_id']) ? '?branch_id='.$p['branch_id'] : ''; 

                                        foreach($list as $row){ $n++; 
                                            $url    = $month.'/'.$year.'?branch_id='.$row['branch_master_id'];
                                            $total += $row['total_salary_thp'];
                                            
                                    ?>
                                        <tr>
                                            <td><?= $n ?></td>
                                            <td><?= $row['branch_name']." <br><small>".$row['branch_code'].' - '.$row['city']."</small> " ?></td>

                                            <?php if($row['is_final'] == '0'){ ?>
                                                <td colspan="4" class="text-center">
                                                    <span class="badge bg-warning">TAHAP LOCK</span>
                                                </td>
                                            <?php }else{ ?>
                                                <td><?= $row['payroll_code'] ?></td>
                                                <td class="text-center"><?= $row['id'] != '' ? $row['total_employee'] : '' ?></td>
                                                <td class="text-end"><?= $row['id'] != '' ? format_rp($row['total_salary_thp']) : '' ?></td>
                                                <td>
                                                    <?= $row['id'] ? indonesian_date($row['created_at'], true) : '' ?>
                                                </td>
                                            <?php } ?>

                                            <td class="text-center">
                                                <a href="<?= site_url('hr/payroll/'.$url) ?>">
                                                    <i class="dripicons-search"></i>
                                                </a>
                                            </td>
            
                                        </tr>
                                    <?php } ?>

                                    <tr class="bg-light">
                                        <th colspan="4">TOTAL</th>
                                        <th class="text-end"><?= format_rp($total) ?></th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>


            <?php } ?>
            </div>
        </div>
        
    </div>
</div>

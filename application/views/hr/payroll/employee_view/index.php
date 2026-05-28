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

                <table class="table table-bordered table-hover mt-3">
                    <thead class="bg-light">
                        <tr>
                            <th>BULAN</th>
                            <th>TAHUN</th>
                            <th>TAKE HOME PAY</th>
                            <th style="width: 15%" class="text-center">STATUS</th>
                            <th style="width: 10%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $row){ ?>
                            <tr>
                                <td><?= $row['monthname'] ?></td>
                                <td><?= $row['year'] ?></td>
                                <?php if(!empty($row['detail'])){ ?>
                                    <td class="text-end"><?= format_rp($row['detail']['salary_thp']) ?></td>
                                    <td><span class="badge bg-success"><i class="fa fa-check-circle"></i> Sudah Dibayarkan</span></td>
                                    <td class="text-center"><a href="<?= site_url('hr/payroll/detail/'.$row['month'].'/'.$row['year']) ?>" class="btn btn-primary btn-sm"><i class="fa fa-search"></i></a></td>

                                <?php }else{ ?>
                                    <td class="text-end">-</td>
                                    <td>
                                        <span class="badge bg-danger"><i class="fa fa-clock"></i> Belum dibayarkan</span>
                                    </td>
                                    <td></td>
                                <?php } ?>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>

            </div>
        </div>
        
    </div>
</div>

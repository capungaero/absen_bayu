<?php $role = $this->ion_auth->get_users_groups()->row()->name ?>

<style type="text/css">

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
</style>

<div class="table-responsive">
<table class="table table-bordered" style="overflow: scroll; overflow: auto; width: 2200px">
    <thead class="bg-light">
        <tr>
            <th rowspan="3" class="align-middle" style="width: 10%; background-color: #f5f6f8">NAMA / <br>ID FINGERPRINT</th>
            <th rowspan="3" class="align-middle" style="width: 9%">POSISI / SUBDIVISI</th>
            <th rowspan="3" class="text-center align-middle" style="width:7%">KEHADIRAN</th>
            <th colspan="3" class="text-center" style="background-color:#eee">INCOME</th>
            <th colspan="5" class="text-center" style="background-color:#eee">OUTOME</th>
            <th rowspan="3" class="text-center align-middle" style="width: 11%">THP</th>
            <th rowspan="3"></th>
        </tr>
        <tr>
            <td class="text-center bg-success text-white" style="width: 9%">Fee Income</td>
            <th class="text-center bg-success text-white" style="width: 9%">Komisi Lembur</th>
            <th class="text-center bg-success text-white" style="width: 10%">Komisi Lainnya</th>
            <th class="text-center bg-danger text-white" style="width:11%">Kekurangan Hari Kerja</th>
            <th class="text-center bg-danger text-white" style="width: 9%">Denda</th>
            <th class="text-center bg-danger text-white">Ketenagakerjaan</th>
            <th class="text-center bg-danger text-white" style="width: 9%">Potongan Pribadi</th>
            <th class="text-center bg-danger text-white" style="width: 13%">Potongan Bersama</th>
        </tr>
    </thead>

    <tbody id="listPayroll">
        <?php 
            $n = 0;
            $all_salary_in_basic = $all_salary_in_overtime = $all_total_overtime_hour = $all_salary_in_insentive = $all_salary_out_fine = $all_salary_out_work = $all_salary_out_off_work = $all_salary_out_health = $all_salary_out_together = $all_salary_out_deduction = $all_salary_thp = 0;

            foreach ($attendance as $sub){ $n++; 
                $salary_in_basic = $salary_in_overtime = $total_overtime_hour = $salary_in_insentive = $salary_out_fine = $salary_out_work = $salary_out_health = $salary_out_together = $salary_out_off_work = $salary_out_deduction = $salary_thp = 0;

        ?>
            <tr data-subdivision="<?= $sub['subdivision_name'] ?>" style="background-color: #ffd182">
                <th style="background-color: #ffd182"><?= $sub['subdivision_name'] ?></th>
                <td colspan="12"></td>
            </tr>

                <?php foreach ($sub['list'] as $row) { 
                        $salary_in_basic += $row['salary_in_basic'];
                        $salary_in_overtime += $row['salary_in_overtime'];
                        $total_overtime_hour += $row['total_overtime_hour'];
                        $salary_in_insentive += $row['salary_in_insentive'];
                        $salary_out_fine += $row['salary_out_fine'];
                        $salary_out_work += $row['salary_out_work'];
                        $salary_out_health += $row['salary_out_health'];
                        $salary_out_deduction += $row['salary_out_deduction'];
                        $salary_out_together += $row['salary_out_together'];
                        $salary_out_off_work += $row['salary_basic_out_off_work'];
                        $salary_thp += $row['salary_thp'];

                        $all_salary_in_basic += $row['salary_in_basic'];
                        $all_salary_in_overtime += $row['salary_in_overtime'];
                        $all_total_overtime_hour += $row['total_overtime_hour'];
                        $all_salary_in_insentive += $row['salary_in_insentive'];
                        $all_salary_out_fine += $row['salary_out_fine'];
                        $all_salary_out_work += $row['salary_out_work'];
                        $all_salary_out_health += $row['salary_out_health'];
                        $all_salary_out_deduction += $row['salary_out_deduction'];
                        $all_salary_out_together += $row['salary_out_together'];
                        $all_salary_out_off_work += $row['salary_basic_out_off_work'];
                        $all_salary_thp += $row['salary_thp'];
                ?>
                    
                    <tr data-subdivision="<?= $sub['subdivision_name'] ?>">
                        <th style="background-color: #fff">
                            <?= $row['first_name'] ?>
                            <br>
                            <small class="text-muted">
                                <?= $row['employee_code'] ?>
                            </small>
                        </th>
                        <td>
                            <?= $row['position_name'] ?><br>
                            <small class="text-muted"><?= $row['subdivision_name'] ?></small>
                        </td>
                        <td class="text-center">
                            <a href="javascript:void(0)" 
                            data-code="<?= $row['contract_number'] ?>" 
                            data-name="<?= $row['first_name'] ?>" 
                            data-id="<?= $row['id'] ?>" 
                            data-presence="<?= htmlentities($row['payroll_fine'], ENT_QUOTES, 'UTF-8'); ?>"
                            class="presensi">
                                <?= $row['presence_count']." / ".$row['presence_max'] ?>
                            </a>
                        </td>
                        
                        <td class="text-end"><?= format_rp($row['salary_in_basic']);  ?></td>
                        <td class="text-end"><?= format_rp($row['salary_in_overtime'])."<br><small class='text-muted'>".$row['total_overtime_hour']." Jam</small>" ?></td>
                        <td class="text-end">
                            <a class="insentif" 
                            data-code="<?= $row['contract_number'] ?>" 
                            data-name="<?= $row['first_name'] ?>" 
                            data-id="<?= $row['id'] ?>" 
                            data-insentive="<?= htmlentities($row['payroll_insentive'], ENT_QUOTES, 'UTF-8'); ?>"
                            href="javascript:void(0)">
                                <span class="insentif_nominal">
                                    <?= format_rp($row['salary_in_insentive']) ?>
                                </span> &nbsp; <i class="fa fa-search"></i></a>
                        </td>

                        <td class="text-end"><?= format_rp($row['salary_basic_out_off_work']) ?></td>

                        <td class="text-end">
                            <a class="fine" 
                            data-code="<?= $row['contract_number'] ?>" 
                            data-name="<?= $row['first_name'] ?>" 
                            data-id="<?= $row['id'] ?>" 
                            data-fine="<?= htmlentities($row['payroll_fine'], ENT_QUOTES, 'UTF-8'); ?>"
                            href="javascript:void(0)">
                                <span><?= format_rp($row['salary_out_fine']) ?></span> &nbsp; 
                                <i class="fa fa-search"></i>
                            </a>
                        </td>

                        <td class="text-end">
                            <?php if($payroll['is_final'] == '1'){ ?>
                                    <?= format_rp($row['salary_out_work']) ?>
                            <?php }else{ ?>
                                    <a 
                                    class="out_work"
                                    id="pd_<?= $row['user_id'] ?>" 
                                    data-subdivision-id="<?= $sub['subdivision_id'] ?>"
                                    data-id="<?= $row['payroll_detail_id'] ?>" 
                                    data-user-id="<?= $row['user_id'] ?>"
                                    data-value="<?= $row['salary_out_work'] ?>" 
                                    data-code="<?= $row['contract_number'] ?>"
                                    data-name="<?= $row['first_name'] ?>"
                                    href="javascript:void(0)">
                                        <?= format_rp($row['salary_out_work']) ?> &nbsp; <i class="fa fa-edit"></i>
                                    </a>
                            <?php } ?>
                        </td>

                        <td class="text-end">
                            <a class="deduction" 
                            data-code="<?= $row['contract_number'] ?>" 
                            data-name="<?= $row['first_name'] ?>" 
                            data-id="<?= $row['id'] ?>" 
                            data-deduction="<?= htmlentities($row['payroll_deduction'], ENT_QUOTES, 'UTF-8'); ?>"
                            href="javascript:void(0)">
                                <span><?= format_rp($row['salary_out_deduction']) ?></span> &nbsp; 
                                <i class="fa fa-search"></i>
                            </a>
                        </td>

                        <td class="text-end">
                            <?php if($payroll['is_final'] == '1'){ ?>
                                    <?= format_rp($row['salary_out_together']) ?>
                            <?php }else{ ?>
                                    <a 
                                    class="out_together"
                                    id="pdt_<?= $row['user_id'] ?>" 
                                    data-subdivision-id="<?= $sub['subdivision_id'] ?>"
                                    data-id="<?= $row['payroll_detail_id'] ?>" 
                                    data-user-id="<?= $row['user_id'] ?>"
                                    data-value="<?= $row['salary_out_together'] ?>" 
                                    data-code="<?= $row['contract_number'] ?>"
                                    data-name="<?= $row['first_name'] ?>"
                                    href="javascript:void(0)">
                                        <?= format_rp($row['salary_out_together']) ?> &nbsp; <i class="fa fa-edit"></i>
                                    </a>
                            <?php } ?>
                        </td>

                        <td id="total_receive_employee<?= $row['user_id'] ?>" class="total_receive text-end"><?= format_rp($row['salary_thp']) ?></td>
                        <td>
                            <?php 
                                if($payroll['is_final'] == '1'){
                                    $branch_url = $this->input->get('branch_id') ? '?branch_id='.$this->input->get('branch_id') : '';
                            ?>
                                    <a href="<?= site_url('hr/payroll/'.$payroll['month'].'/'.$payroll['year'].'/print/'.$row['user_id']).$branch_url ?>" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fa fa-print"></i></a>
                            <?php
                                }
                                
                            ?>
                            
                        </td>
                    </tr>

                <?php } ?>

            <tr style="background-color: #ffe3b3">
                <th style="background-color: #ffe3b3">Total</th>
                <td></td>
                <td></td>
                <td class="text-end"><b><?= format_rp($salary_in_basic) ?></b></td>
                <td class="text-end"><b><?= format_rp($salary_in_overtime) ?></b></td>
                <td class="text-end"><b><?= format_rp($salary_in_insentive) ?></b></td>
                <td class="text-end"><b><?= format_rp($salary_out_off_work) ?></b></td>
                <td class="text-end"><b><?= format_rp($salary_out_fine) ?></b></td>
                <td class="text-end"><b id="total_adjustment_work<?= $sub['subdivision_id'] ?>"><?= format_rp($salary_out_work) ?></b></td>
                <td class="text-end"><b><?= format_rp($salary_out_deduction) ?></b></td>
                <td class="text-end"><b id="total_adjustment_together<?= $sub['subdivision_id'] ?>"><?= format_rp($salary_out_together) ?></b></td>
                <td class="text-end"><b id="total_receive<?= $sub['subdivision_id'] ?>"><?= format_rp($salary_thp) ?></b></td>
                <td></td>
            </tr>

        <?php } ?>
    </tbody>
    <tbody>

        <?php if($role != 'employee' && $role != 'supervisor'){ ?>
            <tr style="background-color: #cdcdcd">
                <th style="background-color: #cdcdcd">TOTAL SEMUA</th>
                <td></td>
                <td class="text-center"></td>
                <td class="text-end"><b><?= format_rp($all_salary_in_basic) ?></b></td>
                <td class="text-end"><b><?= format_rp($all_salary_in_overtime) ?></b></td>
                <td class="text-end" id="total_adjustment"><b><?= format_rp($all_salary_in_insentive) ?></b></td>
                <td class="text-end" id="total_out_off_work"><b><?= format_rp($all_salary_out_off_work) ?></b></td>
                <td class="text-end" id="total_fine"><b><?= format_rp($all_salary_out_fine) ?></b></td>
                <td class="text-end"><b id="total_adjustment_work"><?= format_rp($all_salary_out_work) ?></b></td>
                <td class="text-end" id="total_adjustment_health"><b><?= format_rp($all_salary_out_deduction) ?></b></td>
                <td class="text-end"><b id="total_adjustment_together"><?= format_rp($all_salary_out_together) ?></b></td>
                <td class="text-end"><b id="total_receive"><?= format_rp($all_salary_thp) ?></b></td>
                <td></td>
            </tr>
        <?php } ?>

    </tbody>
</table>
</div>

<div id="modalInsentif" class="modal fade" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-search"></i> Insentif Karyawan</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">
                    <div class="col-md-12">
                        <table class="table">
                            <tr>
                                <th style="width: 15%" class="bg-light">Kode</th>
                                <td id="i_code" style="width: 30%"></td>
                                <th style="width: 15%" class="bg-light">Nama</th>
                                <td id="i_name"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div id="insentifBody" class="col-md-12">
                        
                    </div>
                </div>
                        
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<div id="modalDeduction" class="modal fade" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-search"></i> Pemotongan Gaji Karyawan</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">
                    <div class="col-md-12">
                        <table class="table">
                            <tr>
                                <th style="width: 15%" class="bg-light">Kode</th>
                                <td id="d_code" style="width: 30%"></td>
                                <th style="width: 15%" class="bg-light">Nama</th>
                                <td id="d_name"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div id="deductionBody" class="col-md-12">
                        
                    </div>
                </div>
                        
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<div id="modalPresensi" class="modal fade" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-search"></i> Presensi Karyawan</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">
                    <div class="col-md-12">
                        <table class="table">
                            <tr>
                                <th style="width: 15%" class="bg-light">Kode</th>
                                <td id="p_code" style="width: 30%"></td>
                                <th style="width: 15%" class="bg-light">Nama</th>
                                <td id="p_name"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <ul class="nav nav-tabs nav-tabs-custom nav-justified" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#presence_presence_tab" role="tab">
                                    <span class="d-block d-sm-none"><i class="dripicons-calendar"></i></span>
                                    <span class="d-none d-sm-block"><i class="dripicons-calendar"></i> PRESENSI</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#presence_pray_tab" role="tab">
                                    <span class="d-block d-sm-none"><i class="dripicons-clock"></i></span>
                                    <span class="d-none d-sm-block"><i class="dripicons-clock"></i> SHOLAT</span>
                                </a>
                            </li>
                        </ul>

                        <!-- Tab panes -->
                        <div class="tab-content text-muted">
                            <div class="tab-pane active" id="presence_presence_tab" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <table class="table">
                                            <tr class="bg-light">
                                                <td>Total Hadir Seluruhnya</td>
                                                <th class="text-end" id="presence_total"></th>
                                            </tr>
                                            <tr>
                                                <td>Total Hadir Penuh</td>
                                                <th id="presence_full_count" class="text-end"></th>
                                            </tr>
                                            <tr>
                                                <td>&emsp;&emsp; Tepat Waktu</td>
                                                <td class="text-end">
                                                    <span id="presence_full_on_time"></span> 
                                                    &emsp;&emsp;
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>&emsp;&emsp; Terlambat</td>
                                                <td class="text-end">
                                                    <span id="presence_full_late"></span> 
                                                    &emsp;&emsp;
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Total Hadir Setengah</td>
                                                <th id="presence_half" class="text-end"></th>
                                            </tr>
                                            <tr>
                                                <td>Total Izin</td>
                                                <th id="presence_leave" class="text-end"></th>
                                            </tr>
                                            <tr>
                                                <td>&emsp;&emsp; Izin</td>
                                                <td class="text-end">
                                                    <span id="leave_izin"></span> 
                                                    &emsp;&emsp;
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>&emsp;&emsp; Sakit</td>
                                                <td class="text-end">
                                                    <span id="leave_sakit"></span> 
                                                    &emsp;&emsp;
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>&emsp;&emsp; Cuti</td>
                                                <td class="text-end">
                                                    <span id="leave_cuti"></span> 
                                                    &emsp;&emsp;
                                                </td>
                                            </tr>

                                            <tr class="bg-light">
                                                <td>Total Alfa</td>
                                                <th class="text-end" id="total_alfa"></th>
                                            </tr>
                                            <tr>
                                                <td>&emsp;&emsp; Weekdays</td>
                                                <td class="text-end">
                                                    <span id="total_alfa_weekdays"></span> 
                                                    &emsp;&emsp;
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>&emsp;&emsp; Weekend</td>
                                                <td class="text-end">
                                                    <span id="total_alfa_weekend"></span> 
                                                    &emsp;&emsp;
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane" id="presence_pray_tab" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <table class="table">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th>Nama</th>
                                                    <th>Tepat Waktu</th>
                                                    <th>Terlambat</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>

                                            <tbody id="table_pray_count"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                        
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->


<div id="modalFine" class="modal fade" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-search"></i> Denda Karyawan</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">
                    <div class="col-md-12">
                        <table class="table">
                            <tr>
                                <th style="width: 15%" class="bg-light">Kode</th>
                                <td id="f_code" style="width: 30%"></td>
                                <th style="width: 15%" class="bg-light">Nama</th>
                                <td id="f_name"></td>
                            </tr>
                            <tr>
                                <th class="bg-light">Hadir</th>
                                <td id="in_count"></td>
                                <th class="bg-light">Alfa</th>
                                <td id="alfa_count"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div id="fineBody" class="col-md-12">
                        <div class="row mb-3">
                            <div class="col-md-12 text-center" style="border: 3px dashed #cdcdcd">
                                Total Denda Keseluruhan
                                <h5 id="fine_amount"></h5>
                            </div>
                        </div>

                        <ul class="nav nav-tabs nav-tabs-custom nav-justified" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#presence_tab" role="tab">
                                    <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                                    <span class="d-none d-sm-block">KEHADIRAN</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#rest_tab" role="tab">
                                    <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                                    <span class="d-none d-sm-block">ISTIRAHAT</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#pray_tab" role="tab">
                                    <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                                    <span class="d-none d-sm-block">SHOLAT</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#izin_tab" role="tab">
                                    <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                                    <span class="d-none d-sm-block">IZIN</span>
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content text-muted">
                            <div class="tab-pane active" id="presence_tab" role="tabpanel">
                                <table class="table">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width: 40%">Terlambat</th>
                                            <th class="text-end" id="presence_day_late"></th>
                                            <th class="text-end" id="presence_amount_in_late"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="table_late">
                                    </tbody>
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Hadir Setengah</th>
                                            <th id="presence_day_half" class="text-end"></th>
                                            <th id="presence_amount_in_half" class="text-end"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="table_half"> 
                                    </tbody>

                                    <thead class="bg-light">
                                        <tr>
                                            <th>Off Weekdays</th>
                                            <th class="text-end" id="presence_weekdays"></th>
                                            <th class="text-end" id="presence_weekdays_amount"></th>
                                        </tr>
                                        <tr>
                                            <th>Off Weekend</th>
                                            <th class="text-end" id="presence_weekend"></th>
                                            <th class="text-end" id="presence_weekend_amount"></th>
                                        </tr>
                                    </thead>

                                    <tbody id="table_weekend">
                                    </tbody>
                                    <thead class="bg-danger text-white">
                                        <tr>
                                            <th>TOTAL DENDA</th>
                                            <th id="total_presence_amount" class="text-end" colspan="2"></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>

                            <div class="tab-pane" id="rest_tab" role="tabpanel">
                                <table class="table">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width: 40%">Denda</th>
                                            <th class="text-end" id="rest_late"></th>
                                            <th id="rest_total_in_fine" class="text-end"></th>
                                        </tr>
                                    </thead>

                                    <tbody id="table_rest_late">
                                    </tbody>
                                    <tr class="bg-danger text-white">
                                        <th>TOTAL DENDA</th>
                                        <th class="text-end" colspan="2" id="total_in_fine"></th>
                                    </tr>
                                </table>
                            </div>

                            <div class="tab-pane" id="pray_tab" role="tabpanel">
                                <table class="table">
                                    <tbody id="table_pray">
                                    </tbody>
                                    <tr class="bg-danger text-white">
                                            <th>TOTAL DENDA</th>
                                            <th class="text-end" id="pray_amount" colspan="2"></th>
                                        </tr>
                                </table>
                            </div>

                            <div class="tab-pane" id="izin_tab" role="tabpanel">
                                <table class="table">
                                    <tbody>
                                        <tr style="background-color: #eee">
                                            <th>Jenis Izin</th>
                                            <th colspan="2" class="text-center">Potongan</th>
                                            <th>Total</th>
                                        </tr>

                                        <tr class="bg-light">
                                            <th><span class="badge bg-warning">Izin</span></th>
                                            <th>Jumlah</th>
                                            <th>Persentase</th>
                                            <th class="text-end" id="izin_amount"></th>
                                        </tr>
                                    </tbody>
                                    <tbody id="table_izin"></tbody>

                                    <tbody>
                                        <tr style="background-color: #eee">
                                            <th>Jenis Izin</th>
                                            <th colspan="2" class="text-center">Potongan</th>
                                            <th>Total</th>
                                        </tr>

                                        <tr class="bg-light">
                                            <th><span class="badge bg-warning">Sakit</span></th>
                                            <th>Jumlah</th>
                                            <th>Persentase</th>
                                            <th class="text-end" id="sakit_amount"></th>
                                        </tr>
                                    </tbody>
                                    <tbody id="table_sakit"></tbody>

                                    <tbody>
                                        <tr style="background-color: #eee">
                                            <th>Jenis Izin</th>
                                            <th colspan="2" class="text-center">Potongan</th>
                                            <th>Total</th>
                                        </tr>

                                        <tr class="bg-light">
                                            <th><span class="badge bg-warning">Cuti</span></th>
                                            <th>Jumlah</th>
                                            <th>Persentase</th>
                                            <th class="text-end" id="cuti_amount"></th>
                                        </tr>
                                    </tbody>
                                    <tbody id="table_cuti"></tbody>

                                    <tr class="bg-danger text-white">
                                        <th>TOTAL DENDA</th>
                                        <th class="text-end" colspan="3" id="leave_amount"></th>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                        
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<form id="formOutWork">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" name="payroll_detail_id" id="ow_id">
<input type="hidden" name="user_id" id="ow_user_id">
<input type="hidden" name="subdivision_id" id="ow_subdivision_id">
<div id="modalOutWork" class="modal fade" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-edit"></i> BPJS Ketenagakerjaan</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">
                    <div class="col-md-12">
                        <table class="table">
                            <tr>
                                <th style="width: 15%" class="bg-light">Kode</th>
                                <td id="ow_code" style="width: 30%"></td>
                                <th style="width: 15%" class="bg-light">Nama</th>
                                <td id="ow_name"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <input type="text" class="form-control rupiah" id="out_work_nominal" name="out_work_nominal">
                    </div>
                </div>
                        
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>
                <button class="btn btn-success" id="btnOutWork">Simpan</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
</form>

<form id="formOutTogether">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<input type="hidden" name="payroll_detail_id" id="ot_id">
<input type="hidden" name="user_id" id="ot_user_id">
<input type="hidden" name="subdivision_id" id="ot_subdivision_id">
<div id="modalOutTogether" class="modal fade" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-edit"></i> Pecah Bersama</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">
                    <div class="col-md-12">
                        <table class="table">
                            <tr>
                                <th style="width: 15%" class="bg-light">Kode</th>
                                <td id="ot_code" style="width: 30%"></td>
                                <th style="width: 15%" class="bg-light">Nama</th>
                                <td id="ot_name"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <input type="text" class="form-control rupiah" id="out_together_nominal" name="out_together_nominal">
                    </div>
                </div>
                        
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>
                <button class="btn btn-success" id="btnOutTogether">Simpan</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
</form>

<script type="text/javascript">
    var hide_zero = <?= $role == 'employee' ? true : 0 ?>;

    <?php if($payroll['is_final'] == '0'){ ?>
        $(document).on('click', '.out_work', function(){
            var a = $(this);
            $('#ow_id').val(a.attr('data-id'))
            $('#ow_user_id').val(a.attr('data-user-id'))
            $('#ow_code').text(a.attr('data-code'));
            $('#ow_name').text(a.attr('data-name'));
            $('#ow_subdivision_id').val(a.attr('data-subdivision-id'))
            $('#out_work_nominal').val(format_rp(a.attr('data-value')))
            $('#modalOutWork').modal('show');
        })

        $(document).on('click', '.out_together', function(){
            var a = $(this);
            $('#ot_id').val(a.attr('data-id'))
            $('#ot_user_id').val(a.attr('data-user-id'))
            $('#ot_code').text(a.attr('data-code'));
            $('#ot_name').text(a.attr('data-name'));
            $('#ot_subdivision_id').val(a.attr('data-subdivision-id'))
            $('#out_together_nominal').val(format_rp(a.attr('data-value')))
            $('#modalOutTogether').modal('show');
        })

        $(document).on('submit', '#formOutWork', function(e){
            e.preventDefault();

            var user_id =  $('#ow_user_id').val();
            var subdivision_id = $('#ow_subdivision_id').val()
            var nominal = format_angka($('#out_work_nominal').val())

            $.ajax({
                url : "<?= site_url('insert_out_work/'.$payroll['id']) ?>",
                method: "POST",
                dataType : "json",
                data : $('#formOutWork').serialize(),
                beforeSend : function(){
                    $('#btnOutWork').html('<i class="fa fa-spinner fa-spin"></i> Proses...').attr('disabled', 'disabled')
                },
                success : function(res){
                    if(res.status){
                        var payroll = res.payroll
                        $('#modalOutWork').modal('hide');
                        $('#pd_'+user_id).attr('data-value', nominal).html(format_rp(nominal)+ ' &nbsp; <i class="fa fa-edit"></i>')
                        $('#total_adjustment_work'+subdivision_id).text(format_rp(payroll.total_work_subdivision))
                        $('#total_adjustment_work').text(format_rp(payroll.total_work_all))
                        $('#total_receive'+subdivision_id).text(format_rp(payroll.total_receive_subdivision))
                        $('#total_receive').text(format_rp(payroll.total_receive_all))
                        $('#total_receive_employee'+user_id).text(format_rp(payroll.total_receive_employee))

                    }else{
                        alert(res.message)
                    }
                },
                complete : function(){
                    $('#btnOutWork').text('Simpan').removeAttr('disabled')
                }
            })

            return false;
        })

        $(document).on('submit', '#formOutTogether', function(e){
            e.preventDefault();

            var user_id =  $('#ot_user_id').val();
            var subdivision_id = $('#ot_subdivision_id').val()
            var nominal = format_angka($('#out_together_nominal').val())

            $.ajax({
                url : "<?= site_url('insert_out_together/'.$payroll['id']) ?>",
                method: "POST",
                dataType : "json",
                data : $('#formOutTogether').serialize(),
                beforeSend : function(){
                    $('#btnOutTogether').html('<i class="fa fa-spinner fa-spin"></i> Proses...').attr('disabled', 'disabled')
                },
                success : function(res){
                    if(res.status){
                        var payroll = res.payroll
                        $('#modalOutTogether').modal('hide');
                        $('#pdt_'+user_id).attr('data-value', nominal).html(format_rp(nominal)+ ' &nbsp; <i class="fa fa-edit"></i>')
                        $('#total_adjustment_together'+subdivision_id).text(format_rp(payroll.total_together_subdivision))
                        $('#total_adjustment_together').text(format_rp(payroll.total_together_all))
                        $('#total_receive'+subdivision_id).text(format_rp(payroll.total_receive_subdivision))
                        $('#total_receive').text(format_rp(payroll.total_receive_all))
                        $('#total_receive_employee'+user_id).text(format_rp(payroll.total_receive_employee))

                    }else{
                        alert(res.message)
                    }
                },
                complete : function(){
                    $('#btnOutTogether').text('Simpan').removeAttr('disabled')
                }
            })

            return false;
        })
    <?php } ?>

    $(document).on('click', '.insentif', function(){
        var a = $(this);
        var code = a.data('code');
        var name = a.data('name');
        var data = a.data('insentive');

        $('#i_code').text(code);
        $('#i_name').text(name);
        $('#modalInsentif').modal('show');

        var tbl = '<table class="table">';
        $.each(data.list, function(index, val){
            if(val.amount == 0 && hide_zero){

            }else{
                tbl += "<tr>";
                tbl += "<td>"+val.name+"</td>";
                tbl += "<td>"+format_rp(val.amount)+"</td>";
                tbl += "</tr>";
            }
        });
        tbl += "</table>";

        $('#insentifBody').html(tbl);
    });

    $(document).on('click', '.deduction', function(){
        var a = $(this);
        var code = a.data('code');
        var name = a.data('name');
        var data = a.data('deduction');

        $('#d_code').text(code);
        $('#d_name').text(name);
        $('#modalDeduction').modal('show');

        var tbl = '<table class="table">';
        $.each(data.list, function(index, val){
            if(val.amount == 0 && hide_zero){

            }else{
                tbl += "<tr>";
                tbl += "<td>"+val.name+"</td>";
                tbl += "<td>"+format_rp(val.amount)+"</td>";
                tbl += "</tr>";
            }
        });
        tbl += "</table>";

        $('#deductionBody').html(tbl);
    });

    $(document).on('click', '.presensi', function(){
        var a = $(this);
        var code = a.data('code');
        var name = a.data('name');
        var data = a.data('presence');

        var presence = data.detail.entry.presence;
        $('#p_code').text(code);
        $('#p_name').text(name);
        
        $('#presence_total').text(presence.count+" / "+presence.max);
        $('#presence_full_count').text(presence.full.count);
        $('#presence_full_on_time').text(presence.full.on_time);
        $('#presence_full_late').text(presence.full.late);
        $('#presence_half').text(presence.half);

        console.log(data.detail)
        var total_alfa = presence.max - presence.count;
        var total_alfa_weekdays = total_alfa - presence.weekend;
        var total_alfa_weekend  = presence.weekend;
        $('#total_alfa').text(total_alfa)
        $('#total_alfa_weekdays').text(total_alfa_weekdays);
        $('#total_alfa_weekend').text(total_alfa_weekend);

        var leave = presence.leave;
        $('#presence_leave').text(leave.count);
        $('#leave_izin').text(leave.cuti);
        $('#leave_sakit').text(leave.izin);
        $('#leave_cuti').text(leave.sakit);

        $('#modalPresensi').modal('show');

        var pray = data.detail.pray.detail;
        var tbl = '<table class="table">';
        var ontime = 0
        var late = 0
        var all = 0
        $.each(pray, function(index, val){
            ontime += val.total.on_time
            late += val.total.late
            all += val.total.count

            tbl += "<tr>";
            tbl += "<td>"+index.toUpperCase()+"</td>";
            tbl += "<td class='text-center'>"+val.total.on_time+"</td>";
            tbl += "<td class='text-center'>"+val.total.late+"</td>";
            tbl += "<th class='text-center'>"+val.total.count+"</th>";
            tbl += "</tr>";
        });

        tbl += "<tr>"
        tbl += "<th>Total</th>"
        tbl += "<th class='text-center'>"+ontime+"</th>"
        tbl += "<th class='text-center'>"+late+"</th>"
        tbl += "<th class='text-center'>"+all+"</th>"
        tbl += "</tr>"
        tbl += "</table>";
        $('#table_pray_count').html(tbl);
    });

    $(document).on('click', '.fine', function(){
        var a = $(this);
        var code = a.data('code');
        var name = a.data('name');
        var data = a.data('fine');

        $('#f_code').text(code);
        $('#f_name').text(name);
        $('#fine_amount').text(format_rp(data.amount));
        
        var entry = data.detail.entry;
        $('#presence_day_late').text(entry.day.late.length+"x");
        $('#presence_amount_in_late').text(format_rp(entry.amount_in_late));
        $('#presence_day_half').text(entry.day.half.length+"x");
        $('#presence_amount_in_half').text(format_rp(entry.amount_in_half));

        var all_total = parseInt(entry.amount_in_late + entry.amount_in_half + entry.amount_in_weekend + entry.amount_in_weekdays);

        $('#total_presence_amount').text(format_rp(all_total));
        $('#presence_weekend_amount').text(format_rp(parseInt(entry.amount_in_weekend)))
        $('#presence_weekend').text(entry.presence.weekend + "x")

        $('#presence_weekdays_amount').text(format_rp(parseInt(entry.amount_in_weekdays)))
        $('#presence_weekdays').text(entry.presence.weekdays + "x")

        var presence = entry.presence;
        var alfa  = presence.max - presence.count;
        $('#alfa_count').html(alfa+" <i class='fa fa-times-circle text-danger'></i>");
        $('#in_count').html(presence.count+" <i class='fa fa-check-circle text-success'></i>");

        var tbl = '';
        $.each(entry.day.late, function(index, val){
            tbl += "<tr>";
            tbl +=    "<td>&emsp;&emsp; "+indonesian_date(val.date)+" </td>";
            tbl +=    "<td class='text-end'>"+val.in_minute+" Menit</td>";
            tbl +=    "<td class='text-end'>"+format_rp(val.amount)+"</td>";
            tbl += "</tr>";
        });
        $('#table_late').html(tbl);

        var tbl = '';
        $.each(entry.day.half, function(index, val){
            tbl += "<tr>";
            tbl +=    "<td>&emsp;&emsp; "+indonesian_date(val.date)+" </td>";
            tbl +=    "<td class='text-end' colspan='2'>"+format_rp(val.amount)+"</td>";
            tbl += "</tr>";
        });
        $('#table_half').html(tbl);

        var tbl = '';
        $.each(entry.day.weekend, function(index, val){
            tbl += "<tr>";
            tbl +=    "<td>&emsp;&emsp; "+indonesian_date(val.date)+" </td>";
            tbl +=    "<td class='text-end' colspan='2'>"+format_rp(parseInt(val.amount))+"</td>";
            tbl += "</tr>";
        });
        $('#table_weekend').html(tbl);

        var rest = data.detail.rest;
        $('#rest_late').text(rest.late.length+"x");
        $('#rest_total_in_fine').text(format_rp(rest.total.in_fine));
        $('#total_in_fine').text(format_rp(rest.total.in_fine))

        var tbl = '';
        $.each(rest.late, function(index, val){
            var desc = val.half ? '<span class="text-danger"><i class="fa fa-times-circle"></i> Fingerprint</span>' : val.in_minute+" Menit";
            tbl += "<tr>";
            tbl +=    "<td>&emsp;&emsp; "+indonesian_date(val.date)+" </td>";
            tbl +=    "<td>"+desc+"</td>";
            tbl +=    "<td class='text-end'>"+format_rp(val.amount)+"</td>";
            tbl += "</tr>";
        });
        $('#table_rest_late').html(tbl);

        var leaves = data.detail.leave;
        $('#leave_amount').text(format_rp(leaves.total_amount));
        
        $('#cuti_amount').text(format_rp(leaves.type.cuti.total_amount))
        var tbl = leaves.type.cuti.day.length == 0 ? '<tr><td colspan="4" class="text-center">-</td></tr>' : '';
        $.each(leaves.type.cuti.day, function(index, val){
            tbl += "<tr>";
            tbl += "<td>"+indonesian_date(val.date)+"</td>";
            tbl += "<td class='text-center'>"+val.count+"</td>";
            tbl += "<td class='text-center'>"+val.percent+"</td>";
            tbl += "<th class='text-end'>"+format_rp(val.amount)+"</th>";
            tbl += "</tr>";
        });
        $('#table_cuti').html(tbl);

        $('#izin_amount').text(format_rp(leaves.type.izin.total_amount))
        var tbl = leaves.type.izin.day.length == 0 ? '<tr><td colspan="4" class="text-center">-</td></tr>' : '';
        $.each(leaves.type.izin.day, function(index, val){
            tbl += "<tr>";
            tbl += "<td>"+indonesian_date(val.date)+"</td>";
            tbl += "<td class='text-center'>"+val.count+"</td>";
            tbl += "<td class='text-center'>"+val.percent+"</td>";
            tbl += "<th class='text-end'>"+format_rp(val.amount)+"</th>";
            tbl += "</tr>";
        });
        $('#table_izin').html(tbl);

        $('#sakit_amount').text(format_rp(leaves.type.sakit.total_amount))
        var tbl = leaves.type.sakit.day.length == 0 ? '<tr><td colspan="4" class="text-center">-</td></tr>' : '';
        $.each(leaves.type.sakit.day, function(index, val){
            tbl += "<tr>";
            tbl += "<td>"+indonesian_date(val.date)+"</td>";
            tbl += "<td class='text-center'>"+val.count+"</td>";
            tbl += "<td class='text-center'>"+val.percent+"</td>";
            tbl += "<td class='text-end'>"+format_rp(val.amount)+"</td>";
            tbl += "</tr>";
        });
        $('#table_sakit').html(tbl);

        var pray = data.detail.pray;
        var tbl = '';
        $.each(pray.detail, function(index, val){
            tbl += "<tr class='bg-light'>";
            tbl +=    "<th>"+index.toUpperCase()+" </th>";
            tbl +=    "<th class='text-end'>"+val.late.length+"x</th>";
            tbl +=    "<th class='text-end'>"+format_rp(val.total.amount)+"</th>"
            tbl += "</tr>";

            if(val.late.length > 0){
                 $.each(val.late, function(key, row){
                    var desc = row.half ? '<span class="text-danger"><i class="fa fa-times-circle"></i> Fingerprint</span>' : row.in_minute+" Menit";
                    tbl += "<tr>";
                    tbl +=      "<td>"+row.date+"</td>"
                    tbl +=      "<td>"+desc+"</td>";
                    tbl +=      "<td class='text-end'>"+format_rp(row.amount)+"</td>";
                    tbl += "</tr>";
                });

            }else{
                tbl += "<tr>";
                tbl +=  "<td colspan='3' class='text-center'>-</td>";
                tbl += "</tr>";
            }
        });
        $('#table_pray').html(tbl);
        $('#pray_amount').text(format_rp(pray.amount));

        $('#modalFine').modal('show');
    });


    function indonesian_date(date){
        var date = new Date(date);
        var d = date.getDate();
        var m = date.getMonth() + 1;
        var y = date.getFullYear();

        //alert(date+ " = "+ d+"-"+m+"-"+y);

        return d+" "+get_monthname(m) +" "+ y;
    }

    function get_monthname(number){
        if(number == 1 || number ==  "01"){
            return "Januari";
        }else if(number == 2 || number == "02"){
            return "Februari";
        }else if(number == 3 || number ==  "03"){
            return "Maret";
        }else if(number == 4 ||  number == "04"){
            return "April";
        }else if(number == 5 ||  number == "05"){
            return "Mei";
        }else if(number == 6 ||  number == "06"){
            return "Juni";
        }else if(number == 7 ||  number == "07"){
            return "Juli";
        }else if(number == 8 ||  number == "08"){
            return "Agustus";
        }else if(number == 9 || number == "09"){
            return "September";
        }else if(number == "10"){
            return "Oktober";
        }else if(number == "11"){
            return "November";
        }else if(number == "12"){
            return "Desember";
        }
    }
</script>
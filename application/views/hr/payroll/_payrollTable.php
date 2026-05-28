<?php $role = $this->ion_auth->get_users_groups()->row()->name;?>

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
            <th rowspan="3" class="align-middle" style="width: 15%; background-color: #f5f6f8">NAMA / ID FINGERPRINT</th>
            <th rowspan="3" class="align-middle" style="width: 12%">POSISI / SUBDIVISI</th>
            <th rowspan="3" class="text-center align-middle" style="width:7%">KEHADIRAN</th>
            <th colspan="3" class="text-center" style="background-color:#eee">INCOME</th>
            <th colspan="5" class="text-center" style="background-color:#eee">OUTCOME</th>
            <th rowspan="3" class="text-center align-middle" style="width: 200px">THP</th>
        </tr>
        <tr>
            <td class="text-center bg-success text-white" style="width: 9%">Fee Income</td>
            <th class="text-center bg-success text-white" style="width: 9%">Komisi Lembur</th>
            <th class="text-center bg-success text-white" style="width: 10%">Komisi Lainnya</th>
            <th class="text-center bg-danger text-white" style="width:11%">Kekurangan Hari Kerja</th>
            <th class="text-center bg-danger text-white" style="width: 9%">Denda</th>
            <th class="text-center bg-danger text-white">Ketenagakerjaan</th>
            <th class="text-center bg-danger text-white" style="width: 9%">Potongan Pribadi</th>
            <th class="text-center bg-danger" style="width: 13%"><a class="text-white" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalPecahBersama">Potongan Bersama <i class="fa fa-plus-circle"></i></a></th>
        </tr>
    </thead>

    <tbody id="listPayroll">
        <form id="formGenerate">
        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
        <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
        <?php 
            $n = 0;
            $all_salary = $all_receive = $all_adjustment = $thp = $all_overtime = $all_deduction = 0;
            $all_work = $all_off_work = $all_in = $all_hour = $all_bpjs_work = $all_bpjs_health = $all_work = $all_debt_automate = $all_fine = 0;

            foreach ($attendance['list'] as $row){ $n++; 
                $total_work = $num_present = $num_late = $num_overtime = $max_work = $total_day = 0;
                $max_work_without_strip = $work_free = 0;
                $presence = $row['fine_detail']['detail']['entry']['presence'];

                /**foreach ($row['workday'] as $workday){ 
                    $max_work += $workday['code'] != 'free' ? 1 : 0;
                    $total_day++;
                    $max_work_without_strip += ($workday['code'] != 'free' && $workday['code'] != '-') ? 1 : 0; 

                    if($workday['present']['status'] || $workday['present']['half'] != ''){

                        if($workday['present']['status']){
                            if($workday['present']['is_overtime'] == '1'){
                                $num_overtime++;
                            }
                            
                            $num_present++;
                            
                        }else{
                            $num_present++;
                            $num_late++;
                        }
                        
                        $total_work++;

                    }else{
                        if($workday['type'] == 'work'){
                            $total_work++;
                        }else if($workday['type'] == 'free'){
                            $work_free++;
                        }
                    }
                }**/
                
                //$total_day = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $total_present = $num_present + $work_free;
                $salary_per_day = $presence['max'] > 0 ? $row['employee']['salary'] / $presence['max_for_generate'] : 0;
                $salary_basic_out_off_work = $salary_per_day * $presence['strip'];
                $all_off_work += $salary_basic_out_off_work;
                
                $tmpReceive = $row['employee']['salary']  - $row['alpha_weekdays_amount'];
                if($tmpReceive < $row['employee']['salary_minimum']){
                    $payment_receive = $row['employee']['salary_minimum'];
                }else{
                    $payment_receive = $row['employee']['salary'];
                }
                $thp = $payment_receive + $row['overtime']['amount'] + $row['insentif']['total'] - ($row['fine'] + $row['deduction']['total'] + $salary_basic_out_off_work);

                $debt_automate = $thp < 0 ? $thp : 0;
                
                $all_in += $payment_receive;
                $all_overtime += $row['overtime']['amount'];
                $all_salary += $row['employee']['salary'];
                $all_receive += $thp < 0 ? 0 : $thp;
                $all_work += $presence['count'];
                $all_hour += $row['overtime']['total_hour'];
                $all_adjustment += $row['insentif']['total'];
                $all_deduction += $row['deduction']['total'];
                $all_fine += $row['fine'];
                $all_debt_automate += $debt_automate;
        ?>
            <tr data-subdivision="<?= $row['employee']['subdivision'] ?>">
                <th style="background-color: #fff">
                    <?= $row['employee']['name'] ?>
                    <br>
                    <small class="text-muted">
                        <?= $row['employee']['code'] ?>
                    </small>
                </th>
                <td>
                    <?= $row['employee']['position'] ?><br>
                    <small class="text-muted"><?= $row['employee']['subdivision'] ?></small>
                </td>
                <td class="text-center"><a href="javascript:void(0)"  data-code="<?= $row['employee']['contract_number'] ?>" data-name="<?= $row['employee']['name'] ?>" data-id="<?= $row['employee']['id'] ?>" class="presensi"><?= $presence['count']." / ".$presence['max'] ?></a></td>
                
                <td id="pokok_<?= $row['employee']['id'] ?>" class="text-end"><?= format_rp($payment_receive) ?></td>
                <td class="text-end"><span id="overtime_<?= $row['employee']['id'] ?>"><?= format_rp($row['overtime']['amount'])."</span><br><small class='text-muted'>".$row['overtime']['total_hour']." Jam</small>" ?></td>

               
                <td class="text-end">
                    <a class="insentif" data-code="<?= $row['employee']['contract_number'] ?>" data-name="<?= $row['employee']['name'] ?>" data-id="<?= $row['employee']['id'] ?>" href="javascript:void(0)">
                        <span class="insentif_nominal" id="insentif_<?= $row['employee']['id'] ?>">
                            <?= format_rp($row['insentif']['total']) ?>
                        </span> &nbsp; <i class="fa fa-plus-circle"></i></a>
                </td>

                 <td class="text-end"><?= format_rp($salary_basic_out_off_work) ?></td>

                <td class="text-end">
                    <a class="fine" data-code="<?= $row['employee']['contract_number'] ?>" data-name="<?= $row['employee']['name'] ?>" data-id="<?= $row['employee']['id'] ?>" id="fine_<?= $row['employee']['id'] ?>" href="javascript:void(0)"><span><?= format_rp($row['fine']) ?></span> &nbsp; <i class="fa fa-search"></i></a>
                </td>

                <td class="text-end">
                     <input type="text" data-id="<?= $row['employee']['id'] ?>" id="work_<?= $row['employee']['id'] ?>" name="work[<?= $row['employee']['id'] ?>]" autocomplete="off" required class="form-control rupiah adjustment_work" value="<?= format_rp(0) ?>">
                </td>

                <td class="text-end">
                    <a class="deduction" data-code="<?= $row['employee']['contract_number'] ?>" data-name="<?= $row['employee']['name'] ?>" data-id="<?= $row['employee']['id'] ?>" href="javascript:void(0)">
                        <span class="deduction_nominal" id="deduction_<?= $row['employee']['id'] ?>">
                            <?= format_rp($row['deduction']['total']) ?>
                        </span> &nbsp; <i class="fa fa-plus-circle"></i></a>
                </td>

                <!--<td class="text-end">
                     <input type="text" data-id="<?= $row['employee']['id'] ?>" id="health_<?= $row['employee']['id'] ?>" name="health[<?= $row['employee']['id'] ?>]" autocomplete="off" required class="form-control rupiah adjustment_health" value="<?= format_rp(0) ?>">
                </td>-->

                <td class="text-end">
                    <span data-id="<?= $row['employee']['id'] ?>" id="pbt_<?= $row['employee']['id'] ?>" class="pecah_bersama_txt"><?= format_rp(0) ?></span><br>
                    <small><a onclick="$('#pbt_<?= $row['employee']['id'] ?>').text(format_rp(0)); $('#pbt_input_<?= $row['employee']['id'] ?>').val(0); calculation_together_exclude(<?= $row['employee']['id'] ?>)" class="text-danger" href="javascript:void(0)"><i class="fa fa-times"></i> Hapus</a></small>
                    <input data-id="<?= $row['employee']['id'] ?>" class="pecah_bersama" id="pbt_input_<?= $row['employee']['id'] ?>" type="hidden" name="together[<?= $row['employee']['id'] ?>]">
                </td>
                <td id="e_<?= $row['employee']['id'] ?>" data-id="<?= $row['employee']['id'] ?>" class="total_receive text-end" data-whithout-insentif="<?= $thp - $row['insentif']['total']  ?>" data-whithout-deduction="<?= $thp + $row['deduction']['total']  ?>" data-min="<?= $thp ?>"><?= format_rp($thp < 0 ? 0 : $thp) ?></td>
                
            </tr>

        <?php } ?>
        </form>
    </tbody>

    <tbody>
        <tr class="bg-light">
            <th style="background-color: #f5f6f8">TOTAL</th>
            <td></td>
            <td style="font-weight: 500" class="text-center"><?= $all_work ?></td>
            
            <td style="font-weight: 500" class="text-end"><?= format_rp($all_in) ?></td>
            <td style="font-weight: 500" class="text-end"><?= format_rp($all_overtime) ?></td>
            <td style="font-weight: 500" class="text-end"><?= format_rp($all_off_work) ?></td>
            <td style="font-weight: 500" class="text-end" id="total_adjustment"><?= format_rp($all_adjustment) ?></td>
            <td style="font-weight: 500" class="text-end" id="total_fine"><?= format_rp($all_fine) ?></td>
            <td style="font-weight: 500" class="text-end" id="total_adjustment_work"><?= format_rp($all_bpjs_work) ?></td>
            <!--<td style="font-weight: 500" class="text-end" id="total_adjustment_health"><?= format_rp($all_bpjs_health) ?></td>-->
            <td style="font-weight: 500" class="text-end" id="total_deduction"><?= format_rp($all_deduction) ?></td>
            <td style="font-weight: 500" class="text-end" id="total_adjustment_together"><?= format_rp(0) ?></td>
            <td style="font-weight: 500" class="text-end" id="total_receive"><?= format_rp($all_receive) ?></td>
        </tr>

    </tbody>
</table>
</div>


<form id="formAdd">
<input type="hidden" name="employee_id" id="employee_id">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<div id="modalInsentif" class="modal fade" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Insentif Karyawan</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">
                    <div class="col-md-12">
                        <table class="table">
                            <tr>
                                <th style="width: 15%" class="bg-light">Kode</th>
                                <td id="employee_code" style="width: 30%"></td>
                                <th style="width: 15%" class="bg-light">Nama</th>
                                <td id="employee_name"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div id="insentifBody" class="col-md-12">
                        <i class="fa fa-spinner fa-spin"></i> Sedang Mengambil Data...
                    </div>
                </div>
                        
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>
                <button id="btnSave" class="btn btn-success waves-effect waves-light"><i class="fa fa-check"></i> Simpan</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
</form>


<form id="formAddDeduction">
<input type="hidden" name="employee_id" id="employee_id_deduction">
<input type="hidden" name="<?php echo $this->security->get_csrf_token_name() ?>" value="<?php echo $this->security->get_csrf_hash() ?>">
<div id="modalDeduction" class="modal fade" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Potongan Pribadi Karyawan</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">
                    <div class="col-md-12">
                        <table class="table">
                            <tr>
                                <th style="width: 15%" class="bg-light">Kode</th>
                                <td id="employee_code_deduction" style="width: 30%"></td>
                                <th style="width: 15%" class="bg-light">Nama</th>
                                <td id="employee_name_deduction"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div id="deductionBody" class="col-md-12">
                        <i class="fa fa-spinner fa-spin"></i> Sedang Mengambil Data...
                    </div>
                </div>
                        
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>
                <button id="btnSaveDeduction" class="btn btn-success waves-effect waves-light"><i class="fa fa-check"></i> Simpan</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
</form>

<div id="modalFine" class="modal fade" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Denda Karyawan</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">
                    <div class="col-md-12">
                        <table class="table">
                            <tr>
                                <th style="width: 15%" class="bg-light">Kode</th>
                                <td id="f_employee_code" style="width: 30%"></td>
                                <th style="width: 15%" class="bg-light">Nama</th>
                                <td id="f_employee_name"></td>
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
                        <i class="fa fa-spinner fa-spin"></i> Sedang Mengambil Data...
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
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Presensi Karyawan</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">
                    <div class="col-md-12">
                        <table class="table">
                            <tr>
                                <th style="width: 15%" class="bg-light">Kode</th>
                                <td id="p_employee_code" style="width: 30%"></td>
                                <th style="width: 15%" class="bg-light">Nama</th>
                                <td id="p_employee_name"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div id="presensiBody" class="col-md-12">
                        <i class="fa fa-spinner fa-spin"></i> Sedang Mengambil Data...
                    </div>
                </div>
                        
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->


<div id="modalPecahBersama" class="modal fade" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 style="color: #fff" class="modal-title mt-0" id="myModalLabel"><i class="fa fa-plus"></i> Pecah Bersama</h5>
                <button style="color: #fff" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">

                <div class="row">
                    <div class="col-md-12">
                        <label>Nominal</label>
                        <input type="text" class="form-control rupiah" id="pecah_bersama" placeholder="Rp. 0" autocomplete="off">
                    </div>
                </div>
                        
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<script type="text/javascript">
    $(document).on('keyup keydown keypress', '.adjustment_work', function(){
        var total_adjustment = 0;
        var total_receive    = 0;

        $('.adjustment_work').each(function(){
            if($(this).val() != ''){
                total_adjustment += format_angka($(this).val());
            }
        });

        var id = $(this).attr('data-id');
        var field = $('#e_'+id);

        var pecah   = format_angka($('#pecah_bersama').val()) > 0 ? format_angka($('#pecah_bersama').val()) : 0;

        var insentif= format_angka($(this).val()) > 0 ? format_angka($(this).val()) : 0;
        
        //var health  = format_angka($('#health_'+id).val()) > 0 ? format_angka($('#health_'+id).val()) : 0;
        var health = 0;
        
        var together= pecah
        var receive = parseInt(field.attr('data-min')) - (insentif + health + together);

        if(receive < 0){
            //alert('Nominal yang anda masukkan mengakibatkan karyawan ini memiliki THP dibawah Rp. 0, silahkan ketik nominal yang sesuai');
            //receive += insentif;
            //total_adjustment -= insentif;
            //$(this).val(format_rp(0));
        }else{
            field.attr('data-max', receive);
        }

        field.text(format_rp(receive < 0 ? 0 : receive));

        $('.total_receive').each(function(){
            total_receive += format_angka($(this).text());
        });

        $('#total_adjustment_work').text(format_rp(total_adjustment));
        $('#total_receive').text(format_rp(total_receive < 0 ? 0 : total_receive));
    });

    $(document).on('keyup keydown keypress', '.adjustment_health', function(){
        var total_adjustment = 0;
        var total_receive    = 0;

        $('.adjustment_health').each(function(){
            if($(this).val() != ''){
                total_adjustment += format_angka($(this).val());
            }
        });

        var id = $(this).attr('data-id');
        var field = $('#e_'+id);
        var pecah   = format_angka($('#pecah_bersama').val()) > 0 ? format_angka($('#pecah_bersama').val()) : 0;

        var insentif= format_angka($(this).val()) > 0 ? format_angka($(this).val()) : 0;
        var work    = format_angka($('#work_'+id).val()) > 0 ? format_angka($('#work_'+id).val()) : 0;
        var together= pecah

        var receive = parseInt(field.attr('data-min')) - (insentif + work + together);

        if(receive < 0){
            //alert('Nominal yang anda masukkan mengakibatkan karyawan ini memiliki THP dibawah Rp. 0, silahkan ketik nominal yang sesuai');
            //receive += insentif;
            //total_adjustment -= insentif;
            //$(this).val(format_rp(0));
        }else{
            field.attr('data-max', receive);
        }

        field.text(format_rp(receive < 0 ? 0 : receive));

        $('.total_receive').each(function(){
            total_receive += format_angka($(this).text());
        });

        $('#total_adjustment_health').text(format_rp(total_adjustment));
        $('#total_receive').text(format_rp(total_receive < 0 ? 0 : total_receive));
    });

    $(document).on('click', '.insentif', function(){
        var a = $(this);
        var id = a.attr('data-id');

        $('#employee_id').val(id);
        $('#employee_name').text(a.attr('data-name'));
        $('#employee_code').text(a.attr('data-code'));
        $('#modalInsentif').modal('show');

        $.ajax({
            url      : "<?= site_url('getInsentif/form/') ?>"+id+"?branch_id=<?= $branch_detail['id'] ?>",
            method   : "POST",
            dataType : "json",
            data     : {
                myToken : "<?php echo $this->security->get_csrf_hash() ?>",
                month   : "<?= $month ?>",
                year    : "<?= $year ?>"
            },
            beforeSend : function(){
                $('#insentifBody').html('<center><i class="fa fa-spinner fa-spin"></i> Sedang Mengambil Data...</center>');
            },
            success : function(res){
                if(res.status){
                    $('#insentifBody').html(res.page);
                }else{
                    alert(res.message);
                }
            }
        })
    })


    $(document).on('click', '.deduction', function(){
        var a = $(this);
        var id = a.attr('data-id');

        $('#employee_id_deduction').val(id);
        $('#employee_name_deduction').text(a.attr('data-name'));
        $('#employee_code_deduction').text(a.attr('data-code'));
        $('#modalDeduction').modal('show');

        $.ajax({
            url      : "<?= site_url('getDeduction/form/') ?>"+id+"?branch_id=<?= $branch_detail['id'] ?>",
            method   : "POST",
            dataType : "json",
            data     : {
                myToken : "<?php echo $this->security->get_csrf_hash() ?>",
                month   : "<?= $month ?>",
                year    : "<?= $year ?>"
            },
            beforeSend : function(){
                $('#deductionBody').html('<center><i class="fa fa-spinner fa-spin"></i> Sedang Mengambil Data...</center>');
            },
            success : function(res){
                console.log(res.status)
                if(res.status){
                    $('#deductionBody').html(res.page);
                }else{
                    alert(res.message);
                }
            },
            complete:function(){
                console.log('complete')
            },
            error: function(xhr, status, error) {
                console.log("AJAX Error: " + status + "\nError: " + error);
            }
        })
    })

     $(document).on('click', '.fine', function(){
        var a = $(this);
        var id = a.attr('data-id');

        $('#f_employee_name').text(a.attr('data-name'));
        $('#f_employee_code').text(a.attr('data-code'));
        $('#modalFine').modal('show');

        $.ajax({
            url      : "<?= site_url('getFine/') ?>"+id+"?branch_id=<?= $branch_detail['id'] ?>",
            method   : "POST",
            dataType : "json",
            data     : {
                myToken : "<?php echo $this->security->get_csrf_hash() ?>",
                month   : "<?= $month ?>",
                year    : "<?= $year ?>"
            },
            beforeSend : function(){
                $('#fineBody').html('<center><i class="fa fa-spinner fa-spin"></i> Sedang Mengambil Data...</center>');
            },
            success : function(res){
                if(res.status){
                    console.log(res.fine);
                    var entry = res.fine.detail.entry.presence;
                    var alfa  = entry.max - entry.count;
                    $('#alfa_count').html(alfa+" <i class='fa fa-times-circle text-danger'></i>");
                    $('#in_count').html(entry.count+" <i class='fa fa-check-circle text-success'></i>");
                    
                    $('#fineBody').html(res.page);
                }else{
                    alert(res.message);
                }
            }
        })
    })

     $(document).on('click', '.presensi', function(){
        var a = $(this);
        var id = a.attr('data-id');

        $('#p_employee_name').text(a.attr('data-name'));
        $('#p_employee_code').text(a.attr('data-code'));
        $('#modalPresensi').modal('show');

        $.ajax({
            url      : "<?= site_url('getPresensi/') ?>"+id+"?branch_id=<?= $branch_detail['id'] ?>",
            method   : "POST",
            dataType : "json",
            data     : {
                myToken : "<?php echo $this->security->get_csrf_hash() ?>",
                month   : "<?= $month ?>",
                year    : "<?= $year ?>"
            },
            beforeSend : function(){
                $('#presensiBody').html('<center><i class="fa fa-spinner fa-spin"></i> Sedang Mengambil Data...</center>');
            },
            success : function(res){
                if(res.status){
                    $('#presensiBody').html(res.page);
                }else{
                    alert(res.message);
                }
            }
        })
    })

    $(document).on('submit', '#formAdd', function(e){
        e.preventDefault();
        var btn = $('#btnSave');

        $.ajax({
            url      : "<?= site_url('save_insentif') ?>" + "?branch_id=<?= $branch_detail['id']."&month=".$month."&year=".$year ?>",
            method   : "POST",
            dataType : "json",
            data     : $('#formAdd').serialize(),
            beforeSend : function(){
                btn.html('<center><i class="fa fa-spinner fa-spin"></i> Proses...</center>').attr('disabled', 'disabled');
            },
            success : function(res){
                if(res.status){
                    var id = $('#employee_id').val();
                    $('#modalInsentif').modal('hide');
                    $('#insentif_'+id).html(format_rp(res.total));
                    
                    var deduction = parseInt(format_angka($('#deduction_'+id).text()));
                    var pokok = parseInt(format_angka($('#pokok_'+id).text()));
                    var overtime = parseInt(format_angka($('#overtime_'+id).text()));
                    var fine = parseInt(format_angka($('#fine_'+id).text()));
                    var work     = format_angka($('#work_'+id).val()) > 0 ? format_angka($('#work_'+id).val()) : 0;
                    var together = parseInt(format_angka($('#pbt_'+id).text()));

                    var total = (pokok + res.total + overtime) - (deduction + fine + together + work);

                    $('#e_'+id).text(format_rp(total));
                    $('#e_'+id).attr('data-min', total);

                    var total_insentif = 0;
                    $('.insentif_nominal').each(function(){
                        total_insentif += format_angka($(this).text());
                    });
                    $('#total_adjustment').text(format_rp(total_insentif));

                    total_receive = 0;
                    $('.total_receive').each(function(){
                        var id = $(this).attr('data-id');
                        var total = format_angka($('#e_'+id).text());
                        total_receive += total < 0 ? 0 : total;
                        if(total < 0){
                            minus = true;
                        }
                    });
                    $('#total_receive').text(format_rp(total_receive));
                }

                alert(res.message);
                btn.html('<i class="fa fa-check"></i> Simpan').removeAttr('disabled');
            }
        })

        return false;
    })

    $(document).on('submit', '#formAddDeduction', function(e){
        e.preventDefault();
        var btn = $('#btnSaveDeduction');

        $.ajax({
            url      : "<?= site_url('save_deduction') ?>" + "?branch_id=<?= $branch_detail['id']."&month=".$month."&year=".$year ?>",
            method   : "POST",
            dataType : "json",
            data     : $('#formAddDeduction').serialize(),
            beforeSend : function(){
                btn.html('<center><i class="fa fa-spinner fa-spin"></i> Proses...</center>').attr('disabled', 'disabled');
            },
            success : function(res){
                if(res.status){
                    var id = $('#employee_id_deduction').val();
                    $('#modalDeduction').modal('hide');
                    $('#deduction_'+id).html(format_rp(res.total));

                    var insentif = parseInt(format_angka($('#insentif_'+id).text()));
                    var pokok = parseInt(format_angka($('#pokok_'+id).text()));
                    var overtime = parseInt(format_angka($('#overtime_'+id).text()));
                    var fine = parseInt(format_angka($('#fine_'+id).text()));
                    var work     = format_angka($('#work_'+id).val()) > 0 ? format_angka($('#work_'+id).val()) : 0;
                    var together = parseInt(format_angka($('#pbt_'+id).text()));
                    var total = (pokok + insentif + overtime) - (res.total + fine + together + work);

                    $('#e_'+id).text(format_rp(total < 0 ? 0 : total));
                    $('#e_'+id).attr('data-min', total);

                    var total_deduction = 0;
                    $('.deduction_nominal').each(function(){
                        total_deduction += format_angka($(this).text());
                    });
                    $('#total_deduction').text(format_rp(total_deduction));

                    total_receive = 0;
                    $('.total_receive').each(function(){
                        var id = $(this).attr('data-id');
                        var total = format_angka($('#e_'+id).text());

                        total_receive += total < 0 ? 0 : total;
                        if(total < 0){
                            minus = true;
                        }
                    });
                    $('#total_receive').text(format_rp(total_receive));
                }

                alert(res.message);
                btn.html('<i class="fa fa-check"></i> Simpan').removeAttr('disabled');
            }
        })

        return false;
    })

    $(document).on('keyup', '#pecah_bersama', function(){
        var val = format_angka($(this).val());
        calculation_together(val);
    })

    function calculation_together(nominal){
        if(isNaN(nominal) || nominal == ''){
            nominal = 0;
        }

        $('.pecah_bersama').val(format_rp(nominal));
        $('.pecah_bersama_txt').text(format_rp(nominal));
        var total = 0;
        var total_together = 0;
        $('.pecah_bersama').each(function(){
            if($(this).val() != ''){
                total_together += format_angka($(this).val());
            }
        });
        $('#total_adjustment_together').text(format_rp(total_together));

        var total_receive = 0; var minus = false;
        $('.total_receive').each(function(){
            var id = $(this).attr('data-id');
            //var health   = format_angka($('#health_'+id).val()) > 0 ? format_angka($('#health_'+id).val()) : 0;
            var health = 0;
            var work     = format_angka($('#work_'+id).val()) > 0 ? format_angka($('#work_'+id).val()) : 0;
            var together = nominal;

            total = $('#e_'+id).attr('data-min') - (health + work + together);
            $(this).text(format_rp(total < 0 ? 0 : total));

            total_receive += total < 0 ? 0 : total;
            if(total < 0){
                minus = true;
            }
        });
        
        $('#total_receive').text(format_rp(total_receive < 0 ? 0 : total_receive));

        if(minus){
            //alert('Nominal yang anda masukkan mengakibatkan salah satu karyawan memiliki THP dibawah Rp. 0, silahkan ketik nominal yang sesuai');
           /** total_receive = 0;
            $('.total_receive').each(function(){
                var id      = $(this).attr('data-id');
                var health  = format_angka($('#health_'+id).val());
                var work    = format_angka($('#work_'+id).val());
                var min     = $(this).attr('data-min');

                total = min - (health + work);
                total_receive += total;
                $(this).text(format_rp(total < 0 ? 0 : total));
            });**/
            
            //$('#total_receive').text(format_rp(total_receive));
            //calculation_together(0);
            //$('#pecah_bersama').val(format_rp(0));
        }
    }


    function calculation_together_exclude(employee_id){
        var nominal = format_angka($('#pecah_bersama').val());
        if(isNaN(nominal) || nominal == ''){
            nominal = 0;
        }

        var total = format_angka($('#e_'+employee_id).text()) + nominal;
        $('#e_'+employee_id).text(format_rp(total));

        $('#pbt_'+employee_id).text(format_rp(0));
        $('#pbt_input_'+employee_id).val(0);

        var total = 0;
        var total_together = 0;

        $('.pecah_bersama').each(function(){
            if($(this).attr('data-id') != employee_id){
                if($(this).val() != ''){
                    total_together += format_angka($(this).val());
                }
            }
        });
        $('#total_adjustment_together').text(format_rp(total_together));

        var total_receive = 0; var minus = false;
        $('.total_receive').each(function(){
            var id = $(this).attr('data-id');
            var total = format_angka($(this).text());
            $(this).text(format_rp(total < 0 ? 0 : total));

            total_receive += total;
            if(total < 0){
                minus = true;
            }
        });

        $('#total_receive').text(format_rp(total_receive < 0 ? 0 : total_receive));

        if(minus){
            /**alert('Nominal yang anda masukkan mengakibatkan salah satu karyawan memiliki THP dibawah Rp. 0, silahkan ketik nominal yang sesuai');
            total_receive = 0;
            $('.total_receive').each(function(){
                var id      = $(this).attr('data-id');
                var health  = format_angka($('#health_'+id).val());
                var work    = format_angka($('#work_'+id).val());
                var min     = $(this).attr('data-min');

                total = min - (health + work);
                total_receive += total;
                $(this).text(format_rp(total));
            });
            $('#total_receive').text(format_rp(total_receive));
            calculation_together(0);
            $('#pecah_bersama').val(format_rp(0));**/
        }
    }
</script>

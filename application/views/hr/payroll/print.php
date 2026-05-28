<!DOCTYPE html>
<html>
<head>
    <title><?= $payroll['payroll_code'] ?></title>

    <style type="text/css">
        body {
          -webkit-print-color-adjust: exact !important;
          color-adjust: exact !important; 
        }
        
        .tableList tr {
          border: 1px solid #999;
        }
    </style>
</head>
<body onload="window.print()">
    <table style="width: 100%">
        <tr>
            <td style="width: 20%; text-align: center"><img style="width: 100px" src="<?= base_url('assets/images/logo.png') ?>"></td>
            <td style="text-align: center;">
                <center>
                    <span style="font-size: 20px"><b>E-ABSENSI TIFFANY</b></span><br>
                    <span style="font-size: 18px"><?= $branch_detail['branch_name']." - ".$branch_detail['city'] ?></span><br>
                    <span><?= $branch_detail['address']." <br> Telp. ".$branch_detail['branch_phone'] ?></span>
                </center>
            </td>
            <td style="width: 20%"></td>
        </tr>
    </table>

    <hr>

    <center style="margin-top: 15px; margin-bottom: 15px">
        <span style="font-size: 16px"><b>LAPORAN PENGGAJIAN</b></span>
    </center>

    <table style="width: 100%;" border="0">
        <tr>
            <td><span style="color:#444; font-size: 12px">Kode Penggajian</span> <br>#<?= $payroll['payroll_code'] ?></td>
            <td>
                <span style="color:#444; font-size: 12px">Periode Penggajian</span><br>
                <?= get_monthname($month)." ".$year ?>
            </td>
            <td>
                <span style="color:#444; font-size: 12px">Tanggal Generate</span><br>
                <?= indonesian_date($payroll['created_at'], true) ?>
            </td>
            <td>
                <span style="color:#444; font-size: 12px">Total Penggajian</span><br>
                <?= format_rp($payroll['total_salary_thp'], '') ?>
            </td>
        </tr>
        <tr>
            <td colspan="4"><span style="color:#444; font-size: 12px">*Semua nominal dalam bentuk kurs Rupiah</span></td>
        </tr>
    </table>

    <br>

    <table class="table tableList" style="font-size: 12px;width: 100%">
    <thead style="background-color: #eee">
        <tr>
            <th rowspan="3" class="align-middle" style="width: 3%">NO</th>
            <th rowspan="3" class="align-middle" style="width: 10%">NAMA</th>
            <th rowspan="3" class="align-middle" style="width: 9%">JABATAN</th>
            <th rowspan="3" class="text-center align-middle" style="width:7%">KEHADIRAN</th>
            <th rowspan="3" class="text-center align-middle" style="width:7%">SHOLAT</th>
            <th colspan="3" class="text-center" style="background-color:#eee">URAIAN GAJI</th>
            <th colspan="4" class="text-center" style="background-color:#eee">PENGURANG</th>
            <th rowspan="3" class="text-center align-middle" style="width: 11%">THP</th>
        </tr>
        <tr>
            <th class="text-center bg-success text-white" style="width: 9%">Pokok</th>
            <th class="text-center bg-success text-white" style="width: 9%">Lembur</th>
            <th class="text-center bg-success text-white" style="width: 10%">Insentif</th>
            <th class="text-center bg-danger text-white" style="width: 9%">Denda</th>
            <th class="text-center bg-danger text-white">Ketenagakerjaan</th>
            <th class="text-center bg-danger text-white" style="width: 10%">Potongan Pribadi</th>
            <th class="text-center bg-danger text-white" style="width: 10%">Pecah Bersama</th>
        </tr>
    </thead>

    <tbody>
        <?php 
            $total_pray = 0;
            $param = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];
            foreach ($attendance as $sub){ 
                $n = 0; 
                $salary_in_basic = $salary_in_overtime = $total_overtime_hour = $salary_in_insentive = $salary_out_fine = $salary_out_work = $salary_out_health = $salary_out_together = $salary_thp = 0;
        ?>
                <tr style="background-color: #ffd182">
                    <td colspan="13"><?= $sub['subdivision_name'] ?></td>
                </tr>
        <?php 

                foreach ($sub['list'] as $row){ $n++;
                    $pray_count = 0;
                    foreach ($param as $pray) {
                        $pray_count += $row[$pray."_count"];
                    }
                    $total_pray += $pray_count; 
                    $salary_in_basic += $row['salary_in_basic'];
                    $salary_in_overtime += $row['salary_in_overtime'];
                    $total_overtime_hour += $row['total_overtime_hour'];
                    $salary_in_insentive += $row['salary_in_insentive'];
                    $salary_out_fine += $row['salary_out_fine'];
                    $salary_out_work += $row['salary_out_work'];
                    $salary_out_health += $row['salary_out_health'];
                    $salary_out_together += $row['salary_out_together'];
                    $salary_thp += $row['salary_thp'];
        ?>
                    <tr>
                        <td><?= $n ?></td>
                        <td>
                            <?= $row['first_name'] ?>
                            <br>
                            <small style="color:#74788d">
                                <?= $row['contract_number'] ?> <br>
                                Rek : <?= $row['account_number']." - ".$row['account_bank'] ?>
                            </small>
                        </td>
                        <td><?= $row['position_name'] ?></td>
                        <td class="text-center">
                            <?= $row['presence_count']." / ".$row['presence_max'] ?>
                        </td>
                        
                        <td class="text-center" align="center"><?= $pray_count ?></td>
                        <td align="right"><?= format_rp($row['salary_in_basic'], '');  ?></td>
                        <td align="right"><?= format_rp($row['salary_in_overtime'], '')."<br><small class='text-muted'>".$row['total_overtime_hour']." Jam</small>" ?></td>
                        <td align="right">
                            <?= format_rp($row['salary_in_insentive'], '') ?>
                        </td>

                        <td align="right">
                            <?= format_rp($row['salary_out_fine'], '') ?>
                        </td>

                        <td align="right">
                            <?= format_rp($row['salary_out_work'], '') ?>
                        </td>
                        <td align="right">
                            <?= format_rp($row['salary_out_health'], '') ?>
                        </td>

                        <td align="right">
                            <?= format_rp($row['salary_out_together'], '') ?>
                        </td>
                        <td class="total_receive" align="right"><?= format_rp($row['salary_thp'], '') ?></td>
                    </tr>
        <?php } ?>

            <tr style="background-color: #ffe3b3">
                <th colspan="3" style="background-color: #ffe3b3; text-align: left">Total</th>
                <td></td>
                <td></td>
                <td style="text-align: right" class="text-end"><b><?= format_rp($salary_in_basic, '') ?></b></td>
                <td style="text-align: right" class="text-end"><b><?= format_rp($salary_in_overtime, '') ?></b></td>
                <td style="text-align: right" class="text-end" id="total_adjustment"><b><?= format_rp($salary_in_insentive, '') ?></b></td>
                <td style="text-align: right" class="text-end" id="total_fine"><b><?= format_rp($salary_out_fine, '') ?></b></td>
                <td style="text-align: right" class="text-end" id="total_adjustment_work"><b><?= format_rp($salary_out_work, '') ?></b></td>
                <td style="text-align: right" class="text-end" id="total_adjustment_health"><b><?= format_rp($salary_out_health, '') ?></b></td>
                <td style="text-align: right" class="text-end" id="total_adjustment_together"><b><?= format_rp($salary_out_together, '') ?></b></td>
                <td style="text-align: right" class="text-end" id="total_receive"><b><?= format_rp($salary_thp, '') ?></b></td>
            </tr>

        <?php } ?>

        <tr class="bg-light">
            <th colspan="3" align="left">TOTAL</th>
            <th class="text-center"></th>
            <th></th>
            <th align="right"><?= format_rp($payroll['total_salary_in_basic'], '') ?></th>
            <th align="right"><?= format_rp($payroll['total_salary_in_overtime'], '') ?></th>
            <th align="right" id="total_adjustment"><?= format_rp($payroll['total_salary_in_insentive'], '') ?></th>
            <th align="right" id="total_fine"><?= format_rp($payroll['total_salary_out_fine'], '') ?></th>
            <th align="right" id="total_adjustment_work"><?= format_rp($payroll['total_salary_out_work'], '') ?></th>
            <th align="right" id="total_adjustment_health"><?= format_rp($payroll['total_salary_out_health'], '') ?></th>
            <th align="right" id="total_adjustment_together"><?= format_rp($payroll['total_salary_out_together'], '') ?></th>
            <th align="right" id="total_receive"><?= format_rp($payroll['total_salary_thp'], '') ?></th>
        </tr>

    </tbody>
</table>
</body>
</html>

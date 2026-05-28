<!DOCTYPE html>
<html>
<head>
	<title><?= $payroll['payroll_code'] ?></title>

	<style type="text/css">
		/*body {
		  -webkit-print-color-adjust: exact !important;
          color-adjust: exact !important; 
          background-image: url('<?= base_url('assets/images/background-logo.png') ?>');
          background-repeat: no-repeat;
          background-position: center;
		}*/

        body{
          -webkit-print-color-adjust:exact !important;
          print-color-adjust:exact !important;
        }
	</style>
</head>
<body onload="window.print()">
    <table style="width: 100%">
        <tr>
            <td style="width: 20%; text-align: center"><!--<img style="width: 100px" src="<?= base_url('assets/images/logo.png') ?>">--></td>
            <td style="text-align: center;">
                <center>
                    <span style="font-size: 20px"><b>E-ABSENSI</b></span><br>
                    <span style="font-size: 18px"><?= $branch_detail['branch_name']." - ".$branch_detail['city'] ?></span><br>
                    <span><?= $branch_detail['address']." <br> Telp. ".$branch_detail['branch_phone'] ?></span>
                </center>
            </td>
            <td style="width: 20%; text-align: center"><!--<img style="width: 100px" src="<?= base_url('assets/images/logo_2.png') ?>">--></td>
        </tr>
    </table>

	<hr>

	<center style="margin-top: 15px; margin-bottom: 15px">
		<span style="font-size: 16px"><b>FEE INCOME MITRA</b></span>
	</center>

    <?php $detail = $attendance[0]; ?>

    <table style="width: 100%;">
        <tr>
            <td style="width: 50%">
                <table style="width: 100%; font-size:12px" >
                    <tr style="text-align: left">
                        <th style="width: 30%; padding-top: 10px">ID </th>
                        <td style="padding-top: 10px"><?= $detail['employee_code'] ?></td>
                    </tr>
                    <tr style="text-align: left">
                        <th>Nama</th>
                        <td><?= $detail['first_name'] ?></td>
                    </tr>
                    <tr style="text-align: left">
                        <th>Posisi</th>
                        <td><?= $detail['position_name'] ?></td>
                    </tr>
                </table>
            </td>
            <td style="width: 50%">
                 <table style="width: 100%; font-size:12px" >
                    <tr style="text-align: left">
                        <th style="width: 30%; padding-top: 10px">Periode</th>
                        <td style="padding-top: 10px"><?= get_monthname($month)." ".$year ?></td>
                    </tr>
                    <tr style="text-align: left">
                        <th>Total Fee</th>
                        <td><?= format_rp($detail['salary_thp']) ?></td>
                    </tr>
                    <tr style="text-align: left">
                        <th>Generate</th>
                        <td>
                            <?= indonesian_date($payroll['created_at'], true) ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

	<br>

    <?php 
        $weekend_fine_alt = 0;
        $detail['salary_basic_out_alfa'] = $detail['salary_basic_out_alfa_weekdays'] + $detail['salary_basic_out_alfa_weekend'];

        $in = $detail['salary_in_insentive'] + $detail['salary_basic_in_full'] + $detail['salary_in_overtime'];
    ?>
    <table style="width: 100%; font-size: 12px;">
        <tr style="background-color:#eee; font-size: 14px;">
            <th colspan="3" style="padding-top: 8px; padding-bottom: 8px">FEE DETAILS</th>
        </tr>
        <tr>
            <th colspan="3" style="text-align: left;">INCOME</th>
        </tr>
        <tr>
            <th style="text-align: left">&emsp; Fee</th>
            <td></td>
            <th style="text-align: right"><?= format_rp($detail['salary_basic_in_full']) ?></th>
        </tr>
        <tr>
            <th style="text-align: left">&emsp; Komisi Lembur</th>
            <td></td>
            <th style="text-align: right"><?= format_rp($detail['salary_in_overtime']) ?></th>
        </tr>

        <?php $insentive = json_decode($detail['payroll_insentive'], TRUE); ?>
        <tr>
            <th style="text-align: left">&emsp; Komisi Lainnya</th>
            <td></td>
            <th style="text-align: right"><?= format_rp($detail['salary_in_insentive']) ?></th>
        </tr>

        <?php foreach ($insentive['list'] as $row) { 
                if($row['amount'] > 0){
                    ?>

                <tr>
                    <td>&emsp;&emsp;&emsp;&emsp; <?= $row['name'] ?></td>
                    <td style="text-align: right"><?= format_rp($row['amount']) ?></td>
                    <td></td>
                </tr>

        <?php } } ?>

        <tr style="background-color:#5cfac0">
            <th colspan="2" style="text-align: left;padding-top: 3px; padding-bottom: 3px">TOTAL</th>
            <th style="text-align: right"><?= format_rp($in) ?></th>
        </tr>
        
        <tr>
            <th colspan="3" style="text-align: left">OUTCOME</th>
        </tr>
        <?php $fine = json_decode($detail['payroll_fine'], TRUE); 
              $entry = $fine['detail']['entry'];
              $rest  = $fine['detail']['rest'];
              $pray  = $fine['detail']['pray'];
              $leave = $fine['detail']['leave'];

              $other_fine = $detail['salary_out_fine'] - ( $leave['total_amount'] + $detail['salary_basic_out_alfa_weekend'] + $detail['salary_basic_out_alfa_weekdays']);
        ?>

        <tr>
            <th style="text-align: left">&emsp; Kekurangan Hari Kerja</th>
            <td></td>
            <th style="text-align: right"><?= format_rp($detail['salary_basic_out_off_work']) ?></th>
        </tr>

        <tr>
            <th style="text-align: left">&emsp; Kehadiran</th>
            <td></td>
            <th style="text-align: right"><?= format_rp($detail['salary_basic_out_alfa'] + $leave['total_amount']) ?></th>
        </tr>

        <tr>
            <td>&emsp;&emsp;&emsp;&emsp;Alfa (Weekdays)</td>
            <td style="text-align: right"><?= format_rp($detail['salary_basic_out_alfa_weekdays']) ?></td>
            <td></td>
        </tr>

        <tr>
            <td>&emsp;&emsp;&emsp;&emsp;Alfa (Weekend)</td>
            <td style="text-align: right"><?= format_rp($detail['salary_basic_out_alfa_weekend']) ?></td>
            <td></td>
        </tr>
        <tr>
            <td>&emsp;&emsp;&emsp;&emsp;Izin</td>
            <td style="text-align: right"><?= format_rp($leave['type']['izin']['total_amount']) ?></td>
            <td></td>
        </tr>
        <tr>
            <td>&emsp;&emsp;&emsp;&emsp;Sakit</td>
            <td style="text-align: right"><?= format_rp($leave['type']['sakit']['total_amount']) ?></td>
            <td></td>
        </tr>

        <?php 
            $deduction = json_decode($detail['payroll_deduction'], TRUE); 
            $out = $detail['salary_basic_out_off_work'] + $detail['salary_basic_out_alfa'] + $leave['total_amount'] + $deduction['total'] + $other_fine;
        ?>
        <tr>
            <th style="text-align: left">&emsp; Potongan Pribadi</th>
            <td></td>
            <th style="text-align: right"><?= format_rp($deduction['total']) ?></th>
        </tr>

        <?php 
            if(isset($deduction['list'])){
                foreach ($deduction['list'] as $row) { 
                    if($row['amount'] > 0){
            
            
                    ?>

                <tr>
                    <td>&emsp;&emsp;&emsp;&emsp; <?= $row['name'] ?></td>
                    <td style="text-align: right"><?= format_rp($row['amount']) ?></td>
                    <td></td>
                </tr>

        <?php } } } ?>

        <tr>
            <th style="text-align: left">&emsp; Pengurangan Jam Kerja</th>
            <td></td>
            <th style="text-align: right"><?= format_rp($other_fine) ?></th>
        </tr>
        
        <tr>
            <td>&emsp;&emsp;&emsp;&emsp;Kehadiran Terlambat</td>
            <td style="text-align: right"><?= format_rp($entry['amount_in_late']) ?></td>
            <td></td>
        </tr>
        <tr>
            <td>&emsp;&emsp;&emsp;&emsp;Kehadiran Setengah Hari</td>
            <td style="text-align: right"><?= format_rp($entry['amount_in_half']) ?></td>
            <td></td>
        </tr>
        <tr>
            <td style="text-align: left">&emsp;&emsp;&emsp;&emsp;Keterlambatan Istirahat</td>
            <td style="text-align: right"><?= format_rp($rest['total']['in_fine']) ?></td>
            <td></td>
        </tr>
        <tr>
            <td style="text-align: left">&emsp;&emsp;&emsp;&emsp;Keterlambatan Sholat</td>
            <td style="text-align: right"><?= format_rp($pray['amount']) ?></td>
            <td></td>
        </tr>
        <tr>
            <th style="text-align: left">&emsp; Potongan Bersama</th>
            <td></td>
            <th style="text-align: right"><?= format_rp($detail['salary_out_together']) ?></th>
        </tr>

        <tr style="background-color:#ffb0b0">
            <th colspan="2" style="text-align: left;padding-top: 3px; padding-bottom: 3px">TOTAL</th>
            <th style="text-align: right"><?= format_rp($out) ?></th>
        </tr>
        <tr>
            <td colspan="2"></td>
            <td>
                <div style="width: 100%; border: 2px dashed #cdcdcd; margin-top: 15px; padding-top: 15px; padding-bottom: 15px; text-align: center">
                    TOTAL FEE INCOME <br>
                    <span style="font-size: 17px"><b><?= format_rp($detail['salary_thp']) ?></b></span>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
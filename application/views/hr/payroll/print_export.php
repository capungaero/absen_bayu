<!DOCTYPE html>
<html>
<head>
	<title><?= $payroll['payroll_code'] ?></title>

	<style type="text/css">
		/**body {
		  -webkit-print-color-adjust: exact !important;
          color-adjust: exact !important; 
          background-image: url('<?= './assets/images/background-logo.jpg' ?>');
          background-repeat: no-repeat;
          background-position: center;
		}**/

        body{
          -webkit-print-color-adjust:exact !important;
          print-color-adjust:exact !important;
        }
	</style>
</head>
<body onload="window.print()">
    <table style="width: 100%">
        <tr>
            <td style="width: 20%; text-align: center"><!--<img style="width: 100px" src="<?= './assets/images/logo.jpg' ?>">--></td>
            <td style="text-align: center;">
                <center>
                    <span style="font-size: 20px"><b>E-ABSENSI</b></span><br>
                    <span style="font-size: 18px"><?= $branch_detail['branch_name']." - ".$branch_detail['city'] ?></span><br>
                    <span><?= $branch_detail['address']." <br> Telp. ".$branch_detail['branch_phone'] ?></span>
                </center>
            </td>
            <td style="width: 20%; text-align: center"><!--<img style="width: 100px" src="<?= './assets/images/logo_2.jpg' ?>">--></td>
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
                        <th style="width: 30%; padding-top: 10px; text-align:left">ID Fingerprint</th>
                        <td style="padding-top: 10px"><?= $detail['employee_code'] ?></td>
                    </tr>
                    <tr style="text-align: left">
                        <th style="text-align:left">Nama</th>
                        <td><?= $detail['first_name'] ?></td>
                    </tr>
                    <tr style="text-align: left">
                        <th style="text-align:left">Posisi</th>
                        <td><?= $detail['position_name'] ?></td>
                    </tr>
                </table>
            </td>
            <td style="width: 50%">
                 <table style="width: 100%; font-size:12px" >
                    <tr style="text-align: left">
                        <th style="width: 30%; padding-top: 10px; text-align: left;">Periode</th>
                        <td style="padding-top: 10px"><?= get_monthname($month)." ".$year ?></td>
                    </tr>
                    <tr style="text-align: left">
                        <th style="text-align:left">Total Gaji</th>
                        <td><?= format_rp($detail['salary_thp']) ?></td>
                    </tr>
                    <tr style="text-align: left">
                        <th style="text-align:left">Generate</th>
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
        $detail['salary_basic_out_alfa'] = $detail['salary_basic_out_alfa_weekdays'] + $detail['salary_basic_out_alfa_weekend'];

        $in = $detail['salary_in_insentive'] + $detail['salary_basic_in_full'] + $detail['salary_in_overtime'];
        $out = $detail['salary_out_fine'] + $detail['salary_out_work'] + $detail['salary_out_deduction'] + $detail['salary_out_together'] + $detail['salary_basic_out_alfa_weekdays'] + $detail['salary_basic_out_off_work'];
    ?>
    <table style="width: 100%; font-size: 12px;">
        <tr style="background-color:#eee; font-size: 14px;">
            <th colspan="3" style="padding-top: 8px; padding-bottom: 8px">FEE DETAILS</th>
        </tr>
        <tr>
            <th colspan="3" style="text-align: left">INCOME</th>
        </tr>
        <tr>
            <th style="text-align: left"><span style="margin-left:20px">Fee</span></th>
            <td></td>
            <th style="text-align: right"><?= format_rp($detail['salary_basic_in_full']) ?></th>
        </tr>
        <tr>
            <th style="text-align: left"><span style="margin-left:20px">Komisi Lembur</span></th>
            <td></td>
            <th style="text-align: right"><?= format_rp($detail['salary_in_overtime']) ?></th>
        </tr>

        <?php $insentive = json_decode($detail['payroll_insentive'], TRUE); ?>
        <tr>
            <th style="text-align: left"><span style="margin-left:20px">Komisi Lainnya</span></th>
            <td></td>
            <th style="text-align: right"><?= format_rp($detail['salary_in_insentive']) ?></th>
        </tr>

        <?php foreach ($insentive['list'] as $row) { 
                if($row['amount'] > 0){
                    ?>

                <tr>
                    <td><span style="margin-left:40px"> <?= $row['name'] ?></span></td>
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
            <th style="text-align: left"><span style="margin-left:20px"> Kekurangan Hari Kerja</span></th>
            <td></td>
            <th style="text-align: right"><?= format_rp($detail['salary_basic_out_off_work']) ?></th>
        </tr>

        <tr>
            <th style="text-align: left"><span style="margin-left:20px"> Kehadiran</span></th>
            <td></td>
            <th style="text-align: right"><?= format_rp($detail['salary_basic_out_alfa'] + $leave['total_amount']) ?></th>
        </tr>

        <tr>
            <td><span style="margin-left:40px">Alfa (Weekdays)</span></td>
            <td style="text-align: right"><?= format_rp($detail['salary_basic_out_alfa_weekdays']) ?></td>
            <td></td>
        </tr>

        <tr>
            <td><span style="margin-left:40px">Alfa (Weekend)</span></td>
            <td style="text-align: right"><?= format_rp($detail['salary_basic_out_alfa_weekend']) ?></td>
            <td></td>
        </tr>

        <tr>
            <td><span style="margin-left:40px">Izin</span></td>
            <td style="text-align: right"><?= format_rp($leave['type']['izin']['total_amount']) ?></td>
            <td></td>
        </tr>
        <tr>
            <td><span style="margin-left:40px">Sakit</span></td>
            <td style="text-align: right"><?= format_rp($leave['type']['sakit']['total_amount']) ?></td>
            <td></td>
        </tr>

        <?php $deduction = json_decode($detail['payroll_deduction'], TRUE); ?>
        <tr>
            <th style="text-align: left"><span style="margin-left:20px"> Potongan Pribadi</span></th>
            <td></td>
            <th style="text-align: right"><?= format_rp($deduction['total']) ?></th>
        </tr>

        <?php 
            if(isset($deduction['list'])){
                foreach ($deduction['list'] as $row) { 
                    if($row['amount'] > 0){
            
            
                    ?>

                <tr>
                    <td><span style="margin-left:40px"> <?= $row['name'] ?></span></td>
                    <td style="text-align: right"><?= format_rp($row['amount']) ?></td>
                    <td></td>
                </tr>

        <?php } } } ?>

        <tr>
            <th style="text-align: left"><span style="margin-left:20px"> Pengurangan Jam Kerja</span></th>
            <td></td>
            <th style="text-align: right"><?= format_rp($other_fine) ?></th>
        </tr>
        
        <tr>
            <td><span style="margin-left:40px">Kehadiran Terlambat</span></td>
            <td style="text-align: right"><?= format_rp($entry['amount_in_late']) ?></td>
            <td></td>
        </tr>
        <tr>
            <td><span style="margin-left:40px">Kehadiran Setengah Hari</span></td>
            <td style="text-align: right"><?= format_rp($entry['amount_in_half']) ?></td>
            <td></td>
        </tr>
        <tr>
            <td style="text-align: left"><span style="margin-left:40px">Keterlambatan Istirahat</span></td>
            <td style="text-align: right"><?= format_rp($rest['total']['in_fine']) ?></td>
            <td></td>
        </tr>
        <tr>
            <td style="text-align: left"><span style="margin-left:40px">Keterlambatan Sholat</span></td>
            <td style="text-align: right"><?= format_rp($pray['amount']) ?></td>
            <td></td>
        </tr>

        <tr>
            <th style="text-align: left"><span style="margin-left:20px"> Potongan Bersama</span></th>
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
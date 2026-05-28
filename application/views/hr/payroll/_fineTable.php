<div class="row mb-3">
    <div class="col-md-12 text-center" style="border: 3px dashed #cdcdcd">
        Total Denda Keseluruhan
        <h5><?= format_rp($fine['amount']) ?></h5>
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

<!-- Tab panes -->
<?php 
    $entry = $fine['detail']['entry']; 
?>
<div class="tab-content text-muted">
    <div class="tab-pane active" id="presence_tab" role="tabpanel">
		<table class="table">
            <thead class="bg-light">
                <tr>
                    <th style="width: 50%">Terlambat</th>
                    <th style="width: 20%" class="text-end"><?= count($entry['day']['late']) ?>x</th>
                    <th class="text-end"><?= format_rp($entry['amount_in_late']) ?></th>
                </tr>
            </thead>
            <tbody>
            	<?php foreach ($entry['day']['late'] as $row) { ?>
            		<tr>
	                    <td>&emsp;&emsp; <?= indonesian_date($row['date']) ?></td>
	                    <td class="text-end"><?= $row['in_minute'] ?> Menit</td>
	                    <td class="text-end"><?= format_rp($row['amount']) ?></td>
	                </tr>
            	<?php } ?>
            </tbody>
            <thead class="bg-light">
                <tr>
                    <th>Hadir Setengah</th>
                    <th class="text-end"><?= count($entry['day']['half']) ?>x</th>
                    <th class="text-end"><?= format_rp($entry['amount_in_half']) ?></th>
                </tr>
            </thead>
            <tbody>
            	<?php foreach ($entry['day']['half'] as $row) { ?>
            		<tr>
	                    <td>&emsp;&emsp; <?= indonesian_date($row['date']) ?></td>
	                    <td class="text-end" colspan="2"><?= format_rp($row['amount']) ?></td>
	                </tr>
            	<?php } ?>	
            </tbody>

            <thead class="bg-light">
                <tr>
                    <th>Off Weekdays</th>
                    <th class="text-end"><?= $entry['presence']['weekdays'] ?>x</th>
                    <th class="text-end"><?= format_rp($entry['amount_in_weekdays']) ?></th>
                </tr>
                <tr>
                    <th>Off Weekend</th>
                    <th class="text-end"><?= count($entry['day']['weekend']) ?>x</th>
                    <th class="text-end"><?= format_rp($entry['amount_in_weekend']) ?></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($entry['day']['weekend'] as $row) { ?>
                    <tr>
                        <td>&emsp;&emsp; <?= indonesian_date($row['date']) ?><br>&emsp;&emsp;<small class="text-muted"><?= get_dayname($row['date']) ?></small></td>
                        <td class="text-end" colspan="2"><?= format_rp($row['amount']) ?></td>
                    </tr>
                <?php } ?>  
            </tbody>

            <thead class="bg-danger text-white">
                <tr>
                    <th>TOTAL DENDA</th>
                    <th class="text-end" colspan="2"><?= format_rp($entry['amount_in_late'] + $entry['amount_in_half'] + $entry['amount_in_weekend'] + $entry['amount_in_weekdays']) ?></th>
                </tr>
            </thead>
        </table>
	</div>

	<?php $rest = $fine['detail']['rest'] ?>
	<div class="tab-pane" id="rest_tab" role="tabpanel">
		<table class="table">
            <thead class="bg-light">
                <tr>
                    <th style="width: 50%">Denda</th>
                    <th style="width: 20%" class="text-end"><?= count($rest['late']) ?>x</th>
                    <th class="text-end"><?= format_rp($rest['total']['in_fine']) ?></th>
                </tr>
            </thead>
            <tbody>

                <?php if(empty($rest['late'])){ ?>
                		<tr>
		                    <td colspan="3" class="text-center">-</td>
		                </tr>
                <?php }else{
		                foreach ($rest['late'] as $row) { ?>
		                	<tr>
			                    <td>&emsp;&emsp; <?= indonesian_date($row['date']) ?></td>
			                    <td><?= $row['half'] ? '<i class="fa fa-times-circle"></i> Fingerprint' : $row['in_minute']." Menit" ?></td>
			                    <td class="text-end"><?= format_rp($row['amount']) ?></td>
			                </tr>

		                <?php } 
		              }
		           
		        ?>
                
                <tr class="bg-danger text-white">
                    <th>TOTAL DENDA</th>
                    <th class="text-end" colspan="2"><?= format_rp($rest['total']['in_fine']) ?></th>
                </tr>
            </tbody>
        </table>
	</div>

	<?php $pray = $fine['detail']['pray'] ?>
	<div class="tab-pane" id="pray_tab" role="tabpanel">
		<table class="table">
            <tbody>
            	<?php foreach ($pray['detail'] as $key => $val) { ?>
            		<tr class="bg-light">
	                    <th style="width: 50%"><?= ucfirst($key) ?></th>
                        <th style="width: 20%" class="text-end"><?= count($val['late']) ?>x</th>
	                    <th class="text-end"><?= format_rp($val['total']['amount']) ?></th>
	                </tr>

	                <?php if(empty($val['late'])){ ?>
	                		<tr>
			                    <td colspan="3" class="text-center">-</td>
			                </tr>
	                <?php }else{
			                foreach ($val['late'] as $row) { ?>
			                	<tr>
				                    <td>&emsp;&emsp; <?= indonesian_date($row['date']) ?></td>
				                    <td><?= $row['half'] ? '<i class="fa fa-times-circle"></i> Fingerprint' : $row['in_minute']." Menit" ?></td>
				                    <td class="text-end"><?= format_rp($row['amount']) ?></td>
				                </tr>

			                <?php } 
			              }
			           } 
			        ?>
                
                <tr class="bg-danger text-white">
                    <th>TOTAL DENDA</th>
                    <th class="text-end" colspan="2"><?= format_rp($pray['amount']) ?></th>
                </tr>
            </tbody>
        </table>
	</div>

    <?php $leave = $fine['detail']['leave'] ?>
    <div class="tab-pane" id="izin_tab" role="tabpanel">
        <table class="table">
            <tbody>
                <?php foreach ($leave['type'] as $key => $val) { ?>
                    <tr style="background-color: #eee">
                        <th>Jenis Izin</th>
                        <th colspan="2" class="text-center">Potongan</th>
                        <th>Total</th>
                    </tr>

                    <tr class="bg-light">
                        <th><span class="badge bg-warning"><?= strtoupper($key) ?></span></th>
                        <th>Jumlah</th>
                        <th>Persentase</th>
                        <th class="text-end"><?= format_rp($val['total_amount']) ?></th>
                    </tr>

                    <?php if(empty($val['day'])){ ?>
                            <tr>
                                <td colspan="4" class="text-center">-</td>
                            </tr>
                    <?php }else{
                            foreach ($val['day'] as $row) { ?>
                                <tr>
                                    <td><?= indonesian_date($row['date']) ?></td>
                                    <td class="text-center"><?= $row['count']."x" ?></td>
                                    <td class="text-center"><?= $row['percent']."%" ?></td>
                                    <td class="text-end"><?= format_rp($row['amount']) ?></td>
                                </tr>

                            <?php } 
                          }
                       } 
                    ?>
                
                <tr class="bg-danger text-white">
                    <th>TOTAL DENDA</th>
                    <th class="text-end" colspan="3"><?= format_rp($leave['total_amount']) ?></th>
                </tr>
            </tbody>
        </table>
    </div>
</div>
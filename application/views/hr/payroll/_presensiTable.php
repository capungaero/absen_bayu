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
<?php $presence = $data['detail']['entry']['presence'] ?>
<div class="tab-content text-muted">
    <div class="tab-pane active" id="presence_presence_tab" role="tabpanel">
        <div class="row mt-3">
            <div class="col-md-12">
                <table class="table">
                    <tr class="bg-light">
                        <td>Total Hadir Seluruhnya</td>
                        <th class="text-end"><?= $presence['count'] ?> / <?= $presence['max'] ?></th>
                    </tr>
                    <tr>
                        <td>Total Hadir Penuh</td>
                        <th class="text-end"><?= $presence['full']['count'] ?></th>
                    </tr>
                    <tr>
                        <td>&emsp;&emsp; Tepat Waktu</td>
                        <td class="text-end"><?= $presence['full']['on_time'] ?> &emsp;&emsp;</td>
                    </tr>
                    <tr>
                        <td>&emsp;&emsp; Terlambat</td>
                        <td class="text-end"><?= $presence['full']['late'] ?> &emsp;&emsp;</td>
                    </tr>
                    <tr>
                        <td>Total Hadir Setengah</td>
                        <th class="text-end"><?= $presence['half'] ?></th>
                    </tr>

                    <tr>
                        <td>Total Izin</td>
                        <th class="text-end"><?= $presence['leave']['count'] ?></th>
                    </tr>
                    <tr>
                        <td>&emsp;&emsp; Izin</td>
                        <td class="text-end"><?= $presence['leave']['izin'] ?> &emsp;&emsp;</td>
                    </tr>
                    <tr>
                        <td>&emsp;&emsp; Sakit</td>
                        <td class="text-end"><?= $presence['leave']['sakit'] ?> &emsp;&emsp;</td>
                    </tr>
                    <tr>
                        <td>&emsp;&emsp; Cuti</td>
                        <td class="text-end"><?= $presence['leave']['cuti'] ?> &emsp;&emsp;</td>
                    </tr>

                    <?php 
                        $total_alfa = $presence['max'] - $presence['full']['count'];
                        $total_alfa_weekdays = $total_alfa - $presence['weekend'];
                        $total_alfa_weekend = $presence['weekend'];
                    ?>

                    <tr class="bg-light">
                        <td>Total Alfa</td>
                        <th class="text-end"><?= $total_alfa ?></th>
                    </tr>
                    <tr>
                        <td>&emsp;&emsp; Weekdays</td>
                        <td class="text-end"><?= $total_alfa_weekdays ?> &emsp;&emsp;</td>
                    </tr>
                    <tr>
                        <td>&emsp;&emsp; Weekend</td>
                        <td class="text-end"><?= $total_alfa_weekend ?> &emsp;&emsp;</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <?php $pray = $data['detail']['pray']['detail'] ?>
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

                    <?php 
                    $ontime = $late = $all = 0;
                    foreach ($pray as $key => $val) { 
                        $ontime += $val['total']['on_time'];
                        $late += $val['total']['late'];
                        $all += $val['total']['count'];
                    ?>
                        <tr>
                            <td><?= ucfirst($key) ?></td>
                            <td class="text-center"><?= $val['total']['on_time'] ?></td>
                            <td class="text-center"><?= $val['total']['late'] ?></td>
                            <td class="text-center"><b><?= $val['total']['count'] ?></b></td>
                        </tr>
                    <?php } ?>

                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th><?= $ontime ?></th>
                            <th><?= $late ?></th>
                            <th><?= $all ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
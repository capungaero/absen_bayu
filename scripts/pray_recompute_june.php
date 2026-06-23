<?php
// Recompute kolom sholat presence Juni dari raw .dat + window cabang SAAT INI.
// Clear+rederive (membereskan orphan & data window lama). Default DRY-RUN.
// Pakai: php pray_recompute_june.php          -> dry run (lihat jumlah)
//        php pray_recompute_june.php --apply  -> terapkan (transaksi + backup revert)
error_reporting(E_ALL & ~E_DEPRECATED);
$APPLY = in_array('--apply', $argv);
$cnf = parse_ini_file(getenv('HOME').'/.absen.cnf', true);
$c = isset($cnf['client']) ? $cnf['client'] : $cnf;
$host = isset($c['host']) ? $c['host'] : 'localhost';
$m = @new mysqli($host, $c['user'], $c['password'], 'tifx3722_newtiffa_timesheet');
if ($m->connect_errno) { fwrite(STDERR, "DB FAIL: ".$m->connect_error."\n"); exit(1); }
$m->query("SET time_zone='+07:00'");

$from='2026-05-26'; $to='2026-06-25';
$prayers=['subuh','dzuhur','ashar','maghrib','isha','friday'];

// window per cabang (in/out/range)
$branch=[]; $cols='id';
foreach($prayers as $p){ $cols.=",{$p}_pray_time_in,{$p}_pray_time_out,{$p}_pray_time_range"; }
$r=$m->query("SELECT $cols FROM branch");
while($row=$r->fetch_assoc()) $branch[$row['id']]=$row;

// code -> daftar user aktif (deteksi dobel)
$userCode=[];
$r=$m->query("SELECT u.id,u.employee_code code,pos.branch_id bid FROM users u JOIN position pos ON pos.id=u.position_id WHERE u.active=1 AND u.employee_code IS NOT NULL AND u.employee_code<>''");
while($row=$r->fetch_assoc()) $userCode[$row['code']][$row['id']]=$row['bid'];

// raw taps mesin sholat (file Juni terbaru per mesin)
$dir='/home/tifx3722/public_html/absen/uploads/attendance/2026/06';
$taps=[];
foreach(['6339163400576','BWXP212161070'] as $sn){
  $f=glob("$dir/attlog_${sn}_*.dat"); if(!$f)continue; rsort($f);
  foreach(file($f[0]) as $line){ $a=preg_split('/\s+/',trim($line)); if(count($a)<3)continue; $ts=strtotime($a[1].' '.$a[2]); if(!$ts)continue; $d=date('Y-m-d',$ts); if($d<$from||$d>$to)continue; $taps[$a[0]][$d][]=date('H:i:s',$ts); }
}

function compute($times,$b,$friday,$prayers){
  $param=['in','out']; $tmp=[]; $out=[];
  foreach($prayers as $p){$tmp[$p]=['in'=>'','out'=>''];$out[$p]=['in'=>null,'out'=>null,'late'=>0];}
  $times=array_values(array_unique($times)); sort($times);
  foreach($times as $t) foreach($prayers as $p){
    if(($friday&&$p=='dzuhur')||(!$friday&&$p=='friday'))continue;
    $pin=$b[$p.'_pray_time_in'];$pout=$b[$p.'_pray_time_out'];
    if($pin===null||$pout===null||$pin===''||$pout==='')continue;
    foreach($param as $par) if($tmp[$p][$par]===''&&$t>=$pin&&$t<=$pout){$tmp[$p][$par]=$t;break;}
  }
  foreach($prayers as $p){
    $in=$tmp[$p]['in'];$o=$tmp[$p]['out'];
    if($in==='')continue;
    if($o!==''){
      $range=(int)$b[$p.'_pray_time_range']; $pout=$b[$p.'_pray_time_out'];
      $limit=date('H:i:s',strtotime($in.' +'.$range.' minutes')); $late=0;
      if($limit<=$pout && $o>$limit){ $late=((int)substr($o,0,2)*60+(int)substr($o,3,2)) - ((int)substr($limit,0,2)*60+(int)substr($limit,3,2)); }
      $out[$p]=['in'=>$in,'out'=>$o,'late'=>$late];
    } else { $out[$p]=['in'=>$in,'out'=>null,'late'=>0]; }
  }
  return $out;
}

$sel='id,user_id,flow_date';
foreach($prayers as $p)$sel.=",{$p}_time_in,{$p}_time_out,{$p}_time_late";
$rows=$m->query("SELECT p.$sel, u.employee_code code, pos.branch_id bid FROM presence p JOIN users u ON u.id=p.user_id JOIN position pos ON pos.id=u.position_id WHERE u.active=1 AND p.flow_date BETWEEN '$from' AND '$to'");

$updates=[]; $revert=[]; $changed=0; $dupskip=0;
while($row=$rows->fetch_assoc()){
  $code=$row['code'];
  if(isset($userCode[$code]) && count($userCode[$code])>1){ $dupskip++; continue; }
  $b=$branch[$row['bid']]; $date=$row['flow_date']; $friday=(date('N',strtotime($date))==5);
  $t=isset($taps[$code][$date])?$taps[$code][$date]:[];
  $rec=compute($t,$b,$friday,$prayers);
  $set=[];$rev=[];$diff=false;
  foreach($prayers as $p){
    $nin=$rec[$p]['in']?$date.' '.$rec[$p]['in']:null; $nout=$rec[$p]['out']?$date.' '.$rec[$p]['out']:null; $nlate=(int)$rec[$p]['late'];
    $oin=$row[$p.'_time_in']; $oout=$row[$p.'_time_out']; $olate=(int)$row[$p.'_time_late'];
    if($nin!=$oin||$nout!=$oout||$nlate!=$olate)$diff=true;
    $set[]="{$p}_time_in=".($nin?"'$nin'":"NULL").",{$p}_time_out=".($nout?"'$nout'":"NULL").",{$p}_time_late=$nlate";
    $rev[]="{$p}_time_in=".($oin?"'$oin'":"NULL").",{$p}_time_out=".($oout?"'$oout'":"NULL").",{$p}_time_late=$olate";
  }
  if($diff){ $changed++; $updates[]="UPDATE presence SET ".implode(',',$set)." WHERE id=".$row['id'].";"; $revert[]="UPDATE presence SET ".implode(',',$rev)." WHERE id=".$row['id'].";"; }
}
echo "Baris presence berubah: $changed ; skip (code dobel aktif): $dupskip ; mode: ".($APPLY?'APPLY':'DRY-RUN')."\n";

if($APPLY && $changed){
  $rf=getenv('HOME').'/recompute_pray_revert_'.date('Ymd_His').'.sql';
  file_put_contents($rf, implode("\n",$revert)."\n");
  $m->begin_transaction(); $ok=true;
  foreach($updates as $u){ if(!$m->query($u)){ $ok=false; fwrite(STDERR,"FAIL: ".$m->error."\n"); break; } }
  if($ok){
    // refresh laporan harian (dzuhur/ashar/maghrib/isha) dari presence
    $m->query("UPDATE presence_daily_report r JOIN presence p ON p.user_id=r.user_id AND p.flow_date=r.flow_date SET r.dzuhur_time_in=p.dzuhur_time_in,r.dzuhur_time_out=p.dzuhur_time_out,r.dzuhur_time_late=p.dzuhur_time_late,r.ashar_time_in=p.ashar_time_in,r.ashar_time_out=p.ashar_time_out,r.ashar_time_late=p.ashar_time_late,r.maghrib_time_in=p.maghrib_time_in,r.maghrib_time_out=p.maghrib_time_out,r.maghrib_time_late=p.maghrib_time_late,r.isha_time_in=p.isha_time_in,r.isha_time_out=p.isha_time_out,r.isha_time_late=p.isha_time_late,r.updated_at=NOW() WHERE p.flow_date BETWEEN '$from' AND '$to'");
    $drAffected=$m->affected_rows;
    $m->commit();
    echo "APPLIED $changed update presence (commit OK). daily_report refresh: $drAffected baris.\nRevert SQL: $rf\n";
  } else { $m->rollback(); echo "ROLLBACK karena error.\n"; }
}

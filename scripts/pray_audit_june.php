<?php
// READ-ONLY audit: bandingkan kolom sholat presence Juni dgn raw .dat mesin sholat.
// Jalankan di server: php /tmp/pray_audit_june.php
error_reporting(E_ALL & ~E_DEPRECATED);
$cnf = parse_ini_file(getenv('HOME').'/.absen.cnf', true);
$c = isset($cnf['client']) ? $cnf['client'] : $cnf;
$host = isset($c['host']) ? $c['host'] : 'localhost';
$db = 'tifx3722_newtiffa_timesheet';
$m = @new mysqli($host, $c['user'], $c['password'], $db);
if ($m->connect_errno) { fwrite(STDERR, "DB FAIL: ".$m->connect_error."\n"); exit(1); }

$from='2026-05-26'; $to='2026-06-25';
$prayers=['subuh','dzuhur','ashar','maghrib','isha','friday'];

// windows per branch
$branch=[];
$cols='id';
foreach($prayers as $p){ $cols.=",{$p}_pray_time_in,{$p}_pray_time_out"; }
$r=$m->query("SELECT $cols FROM branch");
while($row=$r->fetch_assoc()) $branch[$row['id']]=$row;

// active user(s) per employee_code
$userByCode=[];
$r=$m->query("SELECT u.id,u.employee_code,pos.branch_id FROM users u JOIN position pos ON pos.id=u.position_id WHERE u.active=1 AND u.employee_code IS NOT NULL AND u.employee_code<>''");
while($row=$r->fetch_assoc()) $userByCode[$row['employee_code']][]=$row;

// raw taps from pray machines (latest June .dat each)
$dir='/home/tifx3722/public_html/absen/uploads/attendance/2026/06';
$taps=[];
foreach(['6339163400576','BWXP212161070'] as $sn){
  $files=glob("$dir/attlog_${sn}_*.dat"); if(!$files)continue; rsort($files); $f=$files[0];
  foreach(file($f) as $line){
    $line=trim($line); if($line==='')continue;
    $a=preg_split('/\s+/',$line); if(count($a)<3)continue;
    $ts=strtotime($a[1].' '.$a[2]); if(!$ts)continue;
    $d=date('Y-m-d',$ts); if($d<$from||$d>$to)continue;
    $taps[$a[0]][$d][]=date('H:i:s',$ts);
  }
}

function expected($times,$b,$friday,$prayers){
  $param=['in','out']; $rec=[];
  foreach($prayers as $p){$rec[$p.'_in']='';$rec[$p.'_out']='';}
  $times=array_values(array_unique($times)); sort($times);
  foreach($times as $t) foreach($prayers as $p){
    if(($friday&&$p=='dzuhur')||(!$friday&&$p=='friday'))continue;
    $pin=$b[$p.'_pray_time_in']; $pout=$b[$p.'_pray_time_out'];
    if($pin===null||$pout===null||$pin===''||$pout==='')continue;
    foreach($param as $par) if($rec[$p.'_'.$par]===''&&$t>=$pin&&$t<=$pout){$rec[$p.'_'.$par]=$t;break;}
  }
  return $rec;
}

$mismatch=0; $windowCut=0; $noUser=0; $noRow=0; $repMis=[]; $repCut=[];
foreach($taps as $code=>$dates){
  if(!isset($userByCode[$code])){ $noUser++; continue; }
  foreach($dates as $date=>$times){
    // pilih user aktif yg punya presence row tgl itu
    $cand=$userByCode[$code]; $uid=null;$bid=null;
    foreach($cand as $cd){
      $q=$m->query("SELECT 1 FROM presence WHERE user_id=".(int)$cd['id']." AND flow_date='$date' LIMIT 1");
      if($q->num_rows){$uid=$cd['id'];$bid=$cd['branch_id'];break;}
    }
    if($uid===null){$uid=$cand[0]['id'];$bid=$cand[0]['branch_id'];}
    $b=$branch[$bid]; $friday=(date('N',strtotime($date))==5);
    $exp=expected($times,$b,$friday,$prayers);
    $q=$m->query("SELECT ".implode(',',array_map(function($p){return "{$p}_time_in,{$p}_time_out";},$prayers))." FROM presence WHERE user_id=$uid AND flow_date='$date' LIMIT 1");
    if(!$q->num_rows){$noRow++; continue;}
    $row=$q->fetch_assoc();
    $uniq=array_values(array_unique($times)); sort($uniq);
    foreach($prayers as $p){
      if(($friday&&$p=='dzuhur')||(!$friday&&$p=='friday'))continue;
      $ein=$exp[$p.'_in']?"$date ".$exp[$p.'_in']:null;
      $eout=$exp[$p.'_out']?"$date ".$exp[$p.'_out']:null;
      $din=$row[$p.'_time_in']; $dout=$row[$p.'_time_out'];
      if($ein!=$din || $eout!=$dout){
        $mismatch++;
        if($exp[$p.'_in']==='' && ($din||$dout)) $GLOBALS['orphan']=($GLOBALS['orphan']??0)+1;       // DB ada nilai, rap tak ada tap di window -> perlu CLEAR
        elseif($exp[$p.'_in']!=='' ) $GLOBALS['fixable']=($GLOBALS['fixable']??0)+1;                 // raw punya in -> re-sync (clear+rederive) bisa
        if(count($repMis)<40) $repMis[]="DRIFT  $code u$uid $date $p :: DB[".($din?substr($din,11):'-')."/".($dout?substr($dout,11):'-')."] EXP[".($exp[$p.'_in']?:'-')."/".($exp[$p.'_out']?:'-')."]";
      }
      // window-tightness: ada 'in' valid, out kosong, tapi ada tap berikut di luar window (<=90mnt stlh out)
      if($exp[$p.'_in']!=='' && $exp[$p.'_out']===''){
        $pout=$b[$p.'_pray_time_out'];
        foreach($uniq as $t){
          if($t>$exp[$p.'_in'] && $t>$pout){
            $gap=(strtotime($t)-strtotime($pout))/60;
            if($gap>0 && $gap<=90){ $windowCut++; if(count($repCut)<60) $repCut[]=sprintf("WINDOW %s u%d %s %-7s in=%s out_window=%s tap_pulang=%s (+%d mnt di luar)",$code,$uid,$date,$p,$exp[$p.'_in'],$pout,$t,$gap); }
            break;
          }
        }
      }
    }
  }
}
echo "=== AUDIT SHOLAT JUNI (raw vs DB) ===\n";
echo "DRIFT total (DB != hitung-ulang dari raw): $mismatch  [orphan/perlu-clear: ".($GLOBALS['orphan']??0).", bisa diperbaiki recompute: ".($GLOBALS['fixable']??0)."]\n";
echo "WINDOW (tap pulang ada tapi di luar window, perlu lebarkan window): $windowCut\n";
echo "code tanpa user aktif (skip): $noUser ; tap tanpa presence row: $noRow\n\n";
if($repMis){echo "--- contoh DRIFT ---\n".implode("\n",$repMis)."\n\n";}
if($repCut){echo "--- contoh WINDOW kesempitan ---\n".implode("\n",$repCut)."\n";}

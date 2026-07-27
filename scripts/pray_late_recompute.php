<?php
// Recompute presence.<pray>_time_late dari in/out yg sudah tersimpan
// (tanpa sentuh in/out), pakai formula excess yg benar:
//   late = (out - (in + pray_time_range)) menit, dibatasi <= pray_time_out.
// Membenerin baris yg late-nya kepotong salah oleh bug lama di
// scripts/vps/absen_sync.py (late = int(dur) alih-alih int(dur - max_min)).
//
// Pakai: php pray_late_recompute.php              -> dry run
//        php pray_late_recompute.php --apply       -> terapkan
//        php pray_late_recompute.php --apply --from=2026-01-01 --to=2026-12-31

error_reporting(E_ALL & ~E_DEPRECATED);
$APPLY = in_array('--apply', $argv);
$from = '2026-01-01';
$to   = date('Y-m-d');
foreach ($argv as $a) {
    if (strpos($a, '--from=') === 0) $from = substr($a, 7);
    if (strpos($a, '--to=') === 0)   $to   = substr($a, 5);
}

$cnf = parse_ini_file(getenv('HOME').'/.absen.cnf', true);
$c = isset($cnf['client']) ? $cnf['client'] : $cnf;
$host = isset($c['host']) ? $c['host'] : 'localhost';
$m = @new mysqli($host, $c['user'], $c['password'], 'tifx3722_newtiffa_timesheet');
if ($m->connect_errno) { fwrite(STDERR, "DB FAIL: ".$m->connect_error."\n"); exit(1); }
$m->query("SET time_zone='+07:00'");

$prayers = ['subuh', 'dzuhur', 'ashar', 'maghrib', 'isha', 'friday'];
define('START_PAYROLL_DATE', 26);

// window per cabang (in/out/range)
$branch = [];
$cols = 'id';
foreach ($prayers as $p) { $cols .= ",{$p}_pray_time_in,{$p}_pray_time_out,{$p}_pray_time_range"; }
$r = $m->query("SELECT $cols FROM branch");
while ($row = $r->fetch_assoc()) $branch[$row['id']] = $row;

// cabang terkunci per periode (month => set branch_id)
$locked = [];
$r = $m->query("SELECT branch_id, month, year FROM payroll");
while ($row = $r->fetch_assoc()) $locked[$row['year']]['_'.$row['month']][] = (int)$row['branch_id'];

function payroll_period($date) {
    $ts = strtotime($date);
    $d = (int)date('d', $ts); $mo = (int)date('n', $ts); $y = (int)date('Y', $ts);
    if ($d >= START_PAYROLL_DATE) { $mo++; if ($mo > 12) { $mo = 1; $y++; } }
    return [$mo, $y];
}

function is_locked($locked, $branch_id, $date) {
    [$mo, $y] = payroll_period($date);
    return in_array((int)$branch_id, $locked[$y]['_'.$mo] ?? [], true);
}

// user_id -> branch_id
$userBranch = [];
$r = $m->query("SELECT u.id uid, pos.branch_id bid FROM users u JOIN position pos ON pos.id=u.position_id");
while ($row = $r->fetch_assoc()) $userBranch[$row['uid']] = (int)$row['bid'];

$cols2 = 'id,user_id,flow_date';
foreach ($prayers as $p) { $cols2 .= ",{$p}_time_in,{$p}_time_out,{$p}_time_late"; }
$sel = $m->query("SELECT $cols2 FROM presence WHERE flow_date BETWEEN '$from' AND '$to'");

$total = 0; $changed = 0; $skippedLocked = 0; $skippedNoBranch = 0;
$sample = [];

while ($row = $sel->fetch_assoc()) {
    $bid = $userBranch[$row['user_id']] ?? null;
    if (!$bid || !isset($branch[$bid])) { $skippedNoBranch++; continue; }
    if (is_locked($locked, $bid, $row['flow_date'])) { $skippedLocked++; continue; }

    $b = $branch[$bid];
    $rowChanged = false;
    $update = [];

    foreach ($prayers as $p) {
        $in = $row[$p.'_time_in'];
        $out = $row[$p.'_time_out'];
        if (!$in || !$out) continue;

        $range = (int)$b[$p.'_pray_time_range'];
        $pout  = $b[$p.'_pray_time_out'];
        $inT   = date('H:i:s', strtotime($in));
        $outT  = date('H:i:s', strtotime($out));
        $limit = date('H:i:s', strtotime($inT.' +'.$range.' minutes'));

        $late = 0;
        if ($limit <= $pout && $outT > $limit) {
            $late = ((int)substr($outT,0,2)*60 + (int)substr($outT,3,2))
                  - ((int)substr($limit,0,2)*60 + (int)substr($limit,3,2));
        }

        $old = (int)$row[$p.'_time_late'];
        if ($late !== $old) {
            $update[$p.'_time_late'] = $late;
            $rowChanged = true;
            if (count($sample) < 20) {
                $sample[] = "id={$row['id']} {$row['flow_date']} {$p}: {$old} -> {$late} (in={$inT} out={$outT} range={$range})";
            }
        }
    }

    $total++;
    if ($rowChanged) {
        $changed++;
        if ($APPLY) {
            $set = [];
            foreach ($update as $k => $v) $set[] = "`$k`=".(int)$v;
            $m->query("UPDATE presence SET ".implode(',', $set)." WHERE id=".(int)$row['id']);
        }
    }
}

echo ($APPLY ? "APPLY" : "DRY-RUN")." range=$from..$to\n";
echo "checked=$total changed=$changed skipped_locked=$skippedLocked skipped_no_branch=$skippedNoBranch\n";
echo "contoh perubahan:\n".implode("\n", $sample)."\n";

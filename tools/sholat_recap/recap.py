#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Rekap Absen Sholat — standalone, TANPA server.

Alur:
  1. Tarik data tap dari file .dat mesin sholat (folder/berkas).
  2. Bandingkan dengan jadwal window sholat + toleransi (config.json).
  3. Hasilkan laporan HTML: hadir, telat, tidak absen, tidak lengkap per karyawan.

Pakai:
  python recap.py --dat ./dat --employees employees.csv --config config.json \
                  --from 2026-06-01 --to 2026-06-30 --out laporan_sholat.html

Hanya butuh Python 3 (stdlib). Tidak perlu koneksi ke server saat runtime.
"""
import argparse, csv, glob, html, json, os, re, sys
from collections import defaultdict
from datetime import datetime, date

PRAYER_LABEL = {
    'subuh': 'Subuh', 'dzuhur': 'Dzuhur', 'ashar': 'Ashar',
    'maghrib': 'Maghrib', 'isha': 'Isya', 'friday': 'Jumat',
}
ORDER = ['subuh', 'dzuhur', 'friday', 'ashar', 'maghrib', 'isha']
DAYS_ID = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']
TAP_RE = re.compile(r'^\s*(\S+)\s+(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})')


def hms_to_sec(s):
    parts = [int(x) for x in s.split(':')]
    while len(parts) < 3:
        parts.append(0)
    return parts[0] * 3600 + parts[1] * 60 + parts[2]


def sec_to_hm(sec):
    if sec is None:
        return '—'
    return '%02d:%02d' % (sec // 3600, (sec % 3600) // 60)


# ---------- 1. PARSE .dat ----------
def parse_dat(paths, dfrom, dto):
    seen = set()           # dedup (finger, date, time)
    taps = defaultdict(lambda: defaultdict(list))   # finger -> date -> [sec,...]
    files = []
    for p in paths:
        if os.path.isdir(p):
            files += sorted(glob.glob(os.path.join(p, '*.dat')))
        elif os.path.isfile(p):
            files.append(p)
    if not files:
        sys.exit('Tidak ada berkas .dat ditemukan di: %s' % ', '.join(paths))
    total = 0
    for f in files:
        with open(f, 'r', encoding='utf-8', errors='ignore') as fh:
            for line in fh:
                m = TAP_RE.match(line)
                if not m:
                    continue
                finger, d, t = m.group(1), m.group(2), m.group(3)
                if dfrom and d < dfrom:
                    continue
                if dto and d > dto:
                    continue
                key = (finger, d, t)
                if key in seen:
                    continue
                seen.add(key)
                taps[finger][d].append(hms_to_sec(t))
                total += 1
    for finger in taps:
        for d in taps[finger]:
            taps[finger][d].sort()
    return taps, files, total


# ---------- employees ----------
def load_employees(path):
    emp = {}
    if not path or not os.path.isfile(path):
        return emp
    with open(path, 'r', encoding='utf-8-sig', newline='') as fh:
        for row in csv.DictReader(fh):
            fid = (row.get('finger_id') or '').strip()
            if not fid:
                continue
            emp[fid] = {
                'name': (row.get('name') or '').strip() or fid,
                'branch': (row.get('branch') or '').strip(),
            }
    return emp


# ---------- 2. PAIRING + EVALUASI ----------
def applicable_prayers(windows, d):
    """Daftar (key, start, end) untuk tanggal d. Jumat: friday gantikan dzuhur."""
    is_fri = date.fromisoformat(d).weekday() == 4
    out = []
    for k in ORDER:
        if k not in windows:
            continue
        if is_fri and k == 'dzuhur':
            continue
        if (not is_fri) and k == 'friday':
            continue
        s, e = windows[k]
        out.append((k, hms_to_sec(s), hms_to_sec(e)))
    out.sort(key=lambda x: x[1])
    return out


def evaluate_day(times, prayers, tol_sec):
    """
    Pasangkan tap ke slot sholat dengan benar:
      - in  = tap pertama DALAM window [s,e]
      - out = tap berikut setelah in, sebelum window sholat berikutnya mulai
              (boleh melewati e — memperbaiki 'selesai sholat di luar window hilang')
    Mengembalikan dict key -> {in,out,late,status}.
    """
    used = [False] * len(times)
    starts = [p[1] for p in prayers]
    res = {}
    for idx, (key, s, e) in enumerate(prayers):
        next_start = starts[idx + 1] if idx + 1 < len(starts) else 24 * 3600
        t_in = t_out = None
        for i, t in enumerate(times):
            if used[i]:
                continue
            if s <= t <= e:
                t_in = t
                used[i] = True
                break
        if t_in is not None:
            for i, t in enumerate(times):
                if used[i]:
                    continue
                if t_in < t < next_start:
                    t_out = t
                    used[i] = True
                    break
        if t_in is None:
            status, late = 'absen', 0
        elif t_out is None:
            status, late = 'lengkapno', 0
        else:
            late = max(0, t_out - (t_in + tol_sec))
            status = 'telat' if late > 0 else 'hadir'
        res[key] = {'in': t_in, 'out': t_out, 'late': late, 'status': status}
    return res


# ---------- 3. LAPORAN HTML ----------
CSS = """
*{box-sizing:border-box} body{font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;
 margin:0;background:#f3f4fb;color:#1f2740}
.wrap{max-width:1180px;margin:0 auto;padding:22px 16px 60px}
h1{font-size:22px;margin:0 0 4px} .sub{color:#7a829e;font-size:13px;margin-bottom:18px}
.cards{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px}
.card{background:#fff;border-radius:14px;padding:14px 16px;box-shadow:0 4px 14px rgba(30,40,80,.06);min-width:120px;flex:1}
.card .n{font-size:24px;font-weight:800} .card .l{font-size:12px;color:#7a829e;margin-top:2px}
.card.green .n{color:#1f9d6b} .card.amber .n{color:#d9892a} .card.red .n{color:#e0494a} .card.gray .n{color:#7a829e}
h2{font-size:15px;margin:24px 0 8px}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 14px rgba(30,40,80,.05)}
th,td{padding:8px 10px;font-size:13px;text-align:left;border-bottom:1px solid #eef0f7}
th{background:#5b73e8;color:#fff;font-weight:600;position:sticky;top:0}
tr:last-child td{border-bottom:0}
.b{display:inline-block;padding:2px 7px;border-radius:20px;font-size:11px;font-weight:700}
.b-h{background:#e6f7f0;color:#1f9d6b} .b-t{background:#fff3df;color:#d9892a}
.b-a{background:#fdeeee;color:#e0494a} .b-n{background:#eef0f8;color:#8a92ad}
.search{width:100%;max-width:340px;padding:9px 12px;border:1px solid #d8dcec;border-radius:10px;font-size:14px;margin-bottom:10px}
.num{text-align:center;font-variant-numeric:tabular-nums}
details{background:#fff;border-radius:12px;margin-bottom:8px;box-shadow:0 3px 10px rgba(30,40,80,.04);overflow:hidden}
summary{padding:11px 14px;cursor:pointer;font-weight:700;font-size:14px;list-style:none}
summary::-webkit-details-marker{display:none}
summary .meta{font-weight:400;color:#7a829e;font-size:12px;margin-left:8px}
details table{box-shadow:none;border-radius:0}
.muted{color:#9aa1bb}
.legend{font-size:12px;color:#7a829e;margin:6px 0 14px}
"""

JS = """
function flt(q){q=q.toLowerCase();document.querySelectorAll('[data-emp]').forEach(function(r){
 r.style.display=r.getAttribute('data-emp').toLowerCase().indexOf(q)>-1?'':'none';});}
"""

DAILY_CSS = """
td.c{font-size:12px;font-variant-numeric:tabular-nums;white-space:nowrap}
td.nm{font-weight:600;font-size:13px;position:sticky;left:0;background:#fff}
.c-h{color:#1f9d6b} .c-t{color:#d9892a;font-weight:700} .c-n{color:#8a92ad}
.c-a{color:#cfd4e6;text-align:center} .lt{color:#e0494a;font-weight:700;font-size:11px}
.cc{display:inline-block;padding:2px 8px;border-radius:6px;margin-right:6px;background:#f3f4fb;font-weight:700}
details table{margin-top:0} details summary{background:#eef0fb}
"""


def badge(status):
    return {
        'hadir': '<span class="b b-h">Hadir</span>',
        'telat': '<span class="b b-t">Telat</span>',
        'absen': '<span class="b b-a">Tidak Absen</span>',
        'lengkapno': '<span class="b b-n">Tdk Lengkap</span>',
    }[status]


def build_html(data, meta):
    P = meta['eval_prayers']
    rows_emp, details = [], []
    tot = defaultdict(int)
    per_prayer = defaultdict(lambda: defaultdict(int))

    for finger in sorted(data, key=lambda f: data[f]['name'].lower()):
        e = data[finger]
        cnt = defaultdict(int)
        late_total = 0
        for d in sorted(e['days']):
            for key, r in e['days'][d].items():
                evalp = key in P or (key == 'friday' and 'dzuhur' in P)
                if evalp:
                    cnt[r['status']] += 1
                    tot[r['status']] += 1
                    per_prayer[key][r['status']] += 1
                late_total += r['late']
        e['cnt'] = cnt
        e['late_total'] = late_total
        rows_emp.append(
            '<tr data-emp="%s %s"><td>%s</td><td>%s</td>'
            '<td class="num">%d</td><td class="num">%d</td><td class="num">%d</td>'
            '<td class="num">%d</td><td class="num">%s</td></tr>' % (
                html.escape(e['name']), html.escape(finger),
                html.escape(e['name']), html.escape(e['branch'] or '-'),
                cnt['hadir'], cnt['telat'], cnt['absen'], cnt['lengkapno'],
                ('%dm' % (late_total // 60)) if late_total else '-'))

        # detail harian
        drows = []
        for d in sorted(e['days']):
            for key, r in e['days'][d].items():
                evalp = key in P or (key == 'friday' and 'dzuhur' in P)
                # sholat di luar jam kerja (tak dinilai) hanya ditampilkan bila benar absen tap
                if not evalp and r['status'] == 'absen':
                    continue
                lt = ('%dm' % (r['late'] // 60)) if r['late'] else ''
                drows.append(
                    '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s %s</td></tr>' % (
                        d, PRAYER_LABEL.get(key, key),
                        sec_to_hm(r['in']), sec_to_hm(r['out']),
                        badge(r['status']), lt))
        details.append(
            '<details data-emp="%s %s"><summary>%s <span class="meta">%s · '
            'H %d · T %d · A %d</span></summary>'
            '<table><tr><th>Tanggal</th><th>Sholat</th><th>Masuk</th><th>Keluar</th><th>Status</th></tr>'
            '%s</table></details>' % (
                html.escape(e['name']), html.escape(finger), html.escape(e['name']),
                html.escape(finger), cnt['hadir'], cnt['telat'], cnt['absen'],
                ''.join(drows) or '<tr><td colspan="5" class="muted">Tidak ada data</td></tr>'))

    # ringkasan per sholat
    pp_rows = []
    for k in ORDER:
        if k not in per_prayer:
            continue
        c = per_prayer[k]
        evald = c['hadir'] + c['telat'] + c['absen']
        pct = (100.0 * c['hadir'] / evald) if evald else 0
        pp_rows.append('<tr><td>%s</td><td class="num">%d</td><td class="num">%d</td>'
                       '<td class="num">%d</td><td class="num">%.0f%%</td></tr>' % (
                           PRAYER_LABEL.get(k, k), c['hadir'], c['telat'], c['absen'], pct))

    cards = [
        ('gray', meta['emp_n'], 'Karyawan'),
        ('gray', meta['workday_n'], 'Hari kerja (≥1 tap)'),
        ('green', tot['hadir'], 'Hadir'),
        ('amber', tot['telat'], 'Telat'),
        ('red', tot['absen'], 'Tidak Absen'),
        ('gray', tot['lengkapno'], 'Tdk Lengkap'),
    ]
    cards_html = ''.join('<div class="card %s"><div class="n">%s</div><div class="l">%s</div></div>'
                         % (c, n, l) for c, n, l in cards)

    return """<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rekap Absen Sholat</title><style>%s</style></head><body><div class="wrap">
<h1>Rekap Absen Sholat</h1>
<div class="sub">Periode <b>%s s/d %s</b> · dibuat %s · %d berkas .dat · %d tap unik ·
toleransi %d menit · sholat dinilai: %s</div>
<div class="cards">%s</div>
<div class="legend">Status: <span class="b b-h">Hadir</span> dalam toleransi ·
<span class="b b-t">Telat</span> lewat toleransi ·
<span class="b b-a">Tidak Absen</span> tak ada tap di window ·
<span class="b b-n">Tdk Lengkap</span> ada masuk tanpa keluar. Hari kerja = hari dengan ≥1 tap.</div>
<h2>Ringkasan per Sholat</h2>
<table><tr><th>Sholat</th><th>Hadir</th><th>Telat</th><th>Tidak Absen</th><th>%% Hadir</th></tr>%s</table>
<h2>Rekap per Karyawan</h2>
<input class="search" placeholder="Cari nama / finger id..." oninput="flt(this.value)">
<table><tr><th>Nama</th><th>Cabang</th><th>Hadir</th><th>Telat</th><th>Tdk Absen</th><th>Tdk Lengkap</th><th>Total Telat</th></tr>%s</table>
<h2>Detail Harian (klik untuk buka)</h2>%s
<script>%s</script></div></body></html>""" % (
        CSS, meta['from'], meta['to'], meta['now'], meta['file_n'], meta['tap_n'],
        meta['tol_min'], ', '.join(PRAYER_LABEL.get(p, p) for p in P),
        cards_html, ''.join(pp_rows), ''.join(rows_emp), ''.join(details), JS)


def day_name(d):
    return DAYS_ID[date.fromisoformat(d).weekday()]


def cell(r):
    """Render sel sholat: jam masuk-keluar + warna status + telat."""
    if r is None or r['status'] == 'absen':
        return '<td class="c c-a">—</td>'
    lt = (' <span class="lt">+%dm</span>' % (r['late'] // 60)) if r['late'] else ''
    rng = sec_to_hm(r['in']) + '–' + sec_to_hm(r['out'])
    klass = {'hadir': 'c-h', 'telat': 'c-t', 'lengkapno': 'c-n'}[r['status']]
    return '<td class="c %s">%s%s</td>' % (klass, rng, lt)


def build_daily_html(data, meta):
    """Laporan harian: per tanggal, semua karyawan x tiap sholat."""
    # inversi: tanggal -> [(name, finger, branch, prayers_dict)]
    by_date = defaultdict(list)
    for finger in data:
        e = data[finger]
        for d, pr in e['days'].items():
            by_date[d].append((e['name'], finger, e['branch'], pr))

    P = meta['eval_prayers']
    sections = []
    for d in sorted(by_date):
        is_fri = date.fromisoformat(d).weekday() == 4
        mid_key, mid_lbl = ('friday', 'Jumat') if is_fri else ('dzuhur', 'Dzuhur')
        cols = [('subuh', 'Subuh'), (mid_key, mid_lbl), ('ashar', 'Ashar'),
                ('maghrib', 'Maghrib'), ('isha', 'Isya')]
        rows = []
        cnt = defaultdict(int)
        for name, finger, branch, pr in sorted(by_date[d], key=lambda x: x[0].lower()):
            tds = []
            for key, _ in cols:
                r = pr.get(key)
                tds.append(cell(r))
                if (key in P or (key == 'friday' and 'dzuhur' in P)) and r is not None:
                    cnt[r['status']] += 1
            rows.append('<tr data-emp="%s %s"><td class="nm">%s</td>%s</tr>' % (
                html.escape(name), html.escape(finger), html.escape(name), ''.join(tds)))
        head = ''.join('<th>%s</th>' % c[1] for c in cols)
        summ = 'Hadir %d · Telat %d · Tidak Absen %d' % (cnt['hadir'], cnt['telat'], cnt['absen'])
        sections.append(
            '<details %s><summary>%s, %s <span class="meta">%d karyawan · %s</span></summary>'
            '<table><tr><th>Nama</th>%s</tr>%s</table></details>' % (
                'open' if d == min(by_date) else '', day_name(d),
                '-'.join(reversed(d.split('-'))), len(by_date[d]), summ, head, ''.join(rows)))

    return """<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rekap Sholat Harian</title><style>%s%s</style></head><body><div class="wrap">
<h1>Rekap Absen Sholat — Harian Detail</h1>
<div class="sub">Periode <b>%s s/d %s</b> · dibuat %s · toleransi %d menit ·
sel: <b>jam masuk–keluar</b>, <span class="lt">+Nm</span> = telat. Klik tanggal untuk buka.</div>
<div class="legend"><span class="c c-h cc">Hadir</span> <span class="c c-t cc">Telat</span>
<span class="c c-n cc">Tdk Lengkap</span> <span class="c c-a cc">— Tidak Absen</span></div>
<input class="search" placeholder="Cari nama / finger id (semua tanggal)..." oninput="dfilter(this.value)">
%s
<script>function dfilter(q){q=q.toLowerCase();document.querySelectorAll('tr[data-emp]').forEach(function(r){
 r.style.display=r.getAttribute('data-emp').toLowerCase().indexOf(q)>-1?'':'none';});
 if(q)document.querySelectorAll('details').forEach(function(d){d.open=true;});}</script>
</div></body></html>""" % (
        CSS, DAILY_CSS, meta['from'], meta['to'], meta['now'], meta['tol_min'], ''.join(sections))


def main():
    ap = argparse.ArgumentParser(description='Rekap absen sholat dari file .dat mesin (standalone).')
    ap.add_argument('--dat', nargs='+', required=True, help='Folder/berkas .dat mesin sholat')
    ap.add_argument('--employees', help='CSV: finger_id,name,branch')
    ap.add_argument('--config', default=os.path.join(os.path.dirname(__file__), 'config.json'))
    ap.add_argument('--from', dest='dfrom', help='Tanggal mulai YYYY-MM-DD')
    ap.add_argument('--to', dest='dto', help='Tanggal akhir YYYY-MM-DD')
    ap.add_argument('--out', default='laporan_sholat.html')
    ap.add_argument('--daily-out', dest='daily_out', default='laporan_sholat_harian.html')
    ap.add_argument('--mode', choices=['summary', 'daily', 'both'], default='both',
                    help="Laporan yang dibuat: ringkasan, harian-detail, atau keduanya (default).")
    a = ap.parse_args()

    with open(a.config, 'r', encoding='utf-8') as fh:
        cfg = json.load(fh)
    tol_min = int(cfg.get('tolerance_minutes', 15))
    tol_sec = tol_min * 60
    eval_p = cfg.get('evaluate_prayers', ['dzuhur', 'ashar', 'maghrib'])
    default_branch = cfg.get('default_branch') or next(iter(cfg['branches']))

    taps, files, tap_n = parse_dat(a.dat, a.dfrom, a.dto)
    emp = load_employees(a.employees)

    data = {}
    workdays = 0
    all_dates = []
    for finger, days in taps.items():
        info = emp.get(finger, {'name': finger, 'branch': ''})
        br = info['branch'] if info['branch'] in cfg['branches'] else default_branch
        windows = cfg['branches'][br]['windows']
        ddata = {}
        for d, times in days.items():
            workdays += 1
            all_dates.append(d)
            prayers = applicable_prayers(windows, d)
            ddata[d] = evaluate_day(times, prayers, tol_sec)
        data[finger] = {'name': info['name'], 'branch': info['branch'], 'days': ddata}

    if not all_dates:
        sys.exit('Tidak ada tap dalam rentang tanggal yang diberikan.')

    meta = {
        'from': a.dfrom or min(all_dates), 'to': a.dto or max(all_dates),
        'now': datetime.now().strftime('%Y-%m-%d %H:%M'),
        'file_n': len(files), 'tap_n': tap_n, 'tol_min': tol_min,
        'emp_n': len(data), 'workday_n': workdays, 'eval_prayers': eval_p,
    }
    written = []
    if a.mode in ('summary', 'both'):
        with open(a.out, 'w', encoding='utf-8') as fh:
            fh.write(build_html(data, meta))
        written.append(a.out)
    if a.mode in ('daily', 'both'):
        with open(a.daily_out, 'w', encoding='utf-8') as fh:
            fh.write(build_daily_html(data, meta))
        written.append(a.daily_out)

    unmapped = sum(1 for f in data if f not in emp)
    print('OK: %s' % ', '.join(written))
    print('  %d berkas, %d tap unik, %d karyawan, %d hari-kerja' % (
        len(files), tap_n, len(data), workdays))
    if unmapped:
        print('  PERHATIAN: %d finger_id tak ada di employees.csv (tampil sebagai angka).' % unmapped)


if __name__ == '__main__':
    main()

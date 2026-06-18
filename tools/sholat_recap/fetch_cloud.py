#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Tarik file .dat mesin sholat LANGSUNG dari Solution Cloud (solutioncloud.co.id).
Tanpa server hosting. Login pakai SN (+password bila ada), lalu download.asp.

Pakai:
  python fetch_cloud.py                      # tarik semua mesin di config (default: pray)
  python fetch_cloud.py --type pray --out ./dat
  python fetch_cloud.py --sn BWXP212161070 --pass "" --out ./dat

Hasil: berkas ./dat/attlog_<SN>_<timestamp>.dat siap diproses recap.py.
Hanya butuh Python 3 (stdlib).
"""
import argparse, http.cookiejar, json, os, sys, urllib.parse, urllib.request
from datetime import datetime


def fetch_one(base_url, sn, password, timeout=60):
    """Login ke sc_pro.asp lalu GET download.asp. Return raw .dat string atau (None, err)."""
    base = base_url.rstrip('/') + '/'
    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
    opener.addheaders = [('User-Agent', 'Mozilla/5.0')]
    # 1) login
    body = urllib.parse.urlencode({'sn': sn, 'pass': password or ''}).encode()
    try:
        opener.open(base + 'sc_pro.asp', data=body, timeout=timeout).read()
    except Exception as e:
        return None, 'login gagal: %s' % e
    # 2) download
    try:
        raw = opener.open(base + 'download.asp', timeout=timeout).read().decode('utf-8', 'ignore')
    except Exception as e:
        return None, 'download gagal: %s' % e
    low = raw.lower()
    if '<html' in low or '<!doctype' in low or '\t' not in raw:
        return None, 'respons tidak valid (mungkin SN/password salah atau sesi login gagal)'
    return raw, None


def main():
    here = os.path.dirname(__file__)
    ap = argparse.ArgumentParser(description='Tarik .dat mesin dari Solution Cloud (standalone).')
    ap.add_argument('--config', default=os.path.join(here, 'config.json'))
    ap.add_argument('--type', default='pray', help="Filter jenis mesin (default 'pray'). 'all' = semua.")
    ap.add_argument('--sn', help='SN mesin tunggal (override config).')
    ap.add_argument('--pass', dest='passwd', default='', help='Password mesin (bila --sn dipakai).')
    ap.add_argument('--base-url', help='Override base url cloud.')
    ap.add_argument('--out', default=os.path.join(here, 'dat'), help='Folder simpan .dat')
    a = ap.parse_args()

    with open(a.config, 'r', encoding='utf-8') as fh:
        cfg = json.load(fh)
    cloud = cfg.get('cloud', {})
    base_url = a.base_url or cloud.get('base_url', 'http://solutioncloud.co.id/')

    if a.sn:
        machines = [{'sn': a.sn, 'pass': a.passwd, 'name': a.sn, 'type': a.type}]
    else:
        machines = cloud.get('machines', [])
        if a.type != 'all':
            machines = [m for m in machines if m.get('type', 'pray') == a.type]
    if not machines:
        sys.exit('Tidak ada mesin di config (cek bagian "cloud.machines").')

    os.makedirs(a.out, exist_ok=True)
    stamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    ok = 0
    for m in machines:
        sn = m['sn']
        print('Menarik %s (%s)...' % (m.get('name', sn), sn), end=' ', flush=True)
        raw, err = fetch_one(base_url, sn, m.get('pass', ''))
        if err:
            print('GAGAL — %s' % err)
            continue
        path = os.path.join(a.out, 'attlog_%s_%s.dat' % (sn, stamp))
        with open(path, 'w', encoding='utf-8') as fh:
            fh.write(raw)
        nlines = raw.count('\n')
        print('OK (%d baris) -> %s' % (nlines, path))
        ok += 1
    if ok == 0:
        sys.exit('Tidak ada mesin yang berhasil ditarik.')
    print('\nSelesai. Lanjut buat laporan:')
    print('  python recap.py --dat "%s" --employees employees.csv' % a.out)


if __name__ == '__main__':
    main()

#!/usr/bin/env python3
"""
Download attendance logs from Solution Cloud machines.

Examples:
  python tools/download_attlog_cloud.py
  python tools/download_attlog_cloud.py --type pray
  python tools/download_attlog_cloud.py --sn BWXP212160931 --password solution
  python tools/download_attlog_cloud.py --month 05 --year 2026
"""

from __future__ import annotations

import argparse
import http.cookiejar
import sys
import urllib.parse
import urllib.request
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path


BASE_URL = "http://solutioncloud.co.id/"

ATTENDANCE_MACHINES = [
    ("BWXP212160931", "solution"),
    ("BWXP212161065", "solution"),
    ("6339163400576", "solution"),
]

PRAY_MACHINES = [
    ("BWXP212161070", "solution"),
]


@dataclass
class DownloadResult:
    sn: str
    ok: bool
    path: Path | None = None
    message: str = ""
    rows: int = 0


def request_url(
    opener: urllib.request.OpenerDirector,
    url: str,
    data: dict[str, str] | None = None,
    timeout: int = 120,
) -> str:
    body = None
    headers = {"User-Agent": "Mozilla/5.0"}
    if data is not None:
        body = urllib.parse.urlencode(data).encode("utf-8")
        headers["Content-Type"] = "application/x-www-form-urlencoded"

    request = urllib.request.Request(url, data=body, headers=headers)
    with opener.open(request, timeout=timeout) as response:
        raw = response.read()

    for encoding in ("utf-8", "latin-1"):
        try:
            return raw.decode(encoding)
        except UnicodeDecodeError:
            continue
    return raw.decode("utf-8", errors="replace")


def download_machine(sn: str, password: str, output_dir: Path, stamp: str) -> DownloadResult:
    cookie_jar = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookie_jar))

    try:
        request_url(opener, urllib.parse.urljoin(BASE_URL, "sc_pro.asp"), {"sn": sn, "pass": password})
        data = request_url(opener, urllib.parse.urljoin(BASE_URL, "download.asp"))
    except Exception as exc:  # noqa: BLE001 - CLI should report any network/protocol failure.
        return DownloadResult(sn=sn, ok=False, message=str(exc))

    if "\t" not in data:
        preview = data.strip().replace("\r", " ").replace("\n", " ")[:120]
        return DownloadResult(sn=sn, ok=False, message=f"download response is not attlog data: {preview}")

    output_dir.mkdir(parents=True, exist_ok=True)
    path = output_dir / f"attlog_{sn}_{stamp}.dat"
    path.write_text(data, encoding="utf-8", newline="")
    rows = sum(1 for line in data.splitlines() if line.strip())
    return DownloadResult(sn=sn, ok=True, path=path, rows=rows)


def parse_args() -> argparse.Namespace:
    now = datetime.now()
    parser = argparse.ArgumentParser(description="Download DAT absensi dari mesin Solution Cloud.")
    parser.add_argument(
        "--type",
        choices=["attendance", "pray", "all"],
        default="attendance",
        help="Kelompok mesin default yang didownload.",
    )
    parser.add_argument("--sn", action="append", help="Serial number mesin custom. Bisa diulang.")
    parser.add_argument("--password", default="solution", help="Password mesin untuk --sn custom.")
    parser.add_argument("--month", default=now.strftime("%m"), help="Bulan output, format 01-12.")
    parser.add_argument("--year", default=now.strftime("%Y"), help="Tahun output, format YYYY.")
    parser.add_argument(
        "--output-dir",
        type=Path,
        help="Folder output. Default: uploads/attendance/<year>/<month>.",
    )
    return parser.parse_args()


def select_machines(args: argparse.Namespace) -> list[tuple[str, str]]:
    machines: list[tuple[str, str]] = []
    if args.type in ("attendance", "all"):
        machines.extend(ATTENDANCE_MACHINES)
    if args.type in ("pray", "all"):
        machines.extend(PRAY_MACHINES)
    if args.sn:
        machines = [(sn, args.password) for sn in args.sn]
    return machines


def main() -> int:
    args = parse_args()
    month = str(args.month).zfill(2)
    year = str(args.year)
    output_dir = args.output_dir or Path("uploads") / "attendance" / year / month
    stamp = datetime.now().strftime("%Y%m%d_%H%M%S")

    machines = select_machines(args)
    if not machines:
        print("Tidak ada mesin yang dipilih.", file=sys.stderr)
        return 2

    print(f"Output: {output_dir.resolve()}")
    results = [download_machine(sn, password, output_dir, stamp) for sn, password in machines]

    ok_count = 0
    for result in results:
        if result.ok:
            ok_count += 1
            print(f"[OK] {result.sn}: {result.rows} baris -> {result.path}")
        else:
            print(f"[GAGAL] {result.sn}: {result.message}", file=sys.stderr)

    return 0 if ok_count > 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())

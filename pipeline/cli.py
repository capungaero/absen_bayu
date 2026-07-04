#!/usr/bin/env python3
"""CLI pipeline absen_ai. Jalankan dgn python venv Hermes:
/usr/local/lib/hermes-agent/venv/bin/python pipeline/cli.py <stage> --period YYYY-MM

Stage: fetch | parse | enrich | reconcile | all
"""
import argparse
import json
import sys
from datetime import date

sys.path.insert(0, __file__.rsplit("/", 1)[0] if "/" in __file__ else ".")

from absen_pipeline import config  # noqa: E402
from absen_pipeline import datparse, enrich, fetch, reconcile  # noqa: E402


def main():
    ap = argparse.ArgumentParser(description="Pipeline absen_ai")
    ap.add_argument("stage", choices=["fetch", "parse", "enrich", "reconcile", "all"])
    ap.add_argument("--period", default=config.period_of(date.today()),
                    help="YYYY-MM periode payroll (default: periode berjalan)")
    ap.add_argument("--from-file", nargs="*", help="fetch: ingest file .dat lokal")
    args = ap.parse_args()

    config.load_env()
    out = {}
    if args.stage in ("fetch", "all"):
        out["fetch"] = fetch.run(args.period, from_files=args.from_file)
    if args.stage in ("parse", "all"):
        out["parse"] = datparse.run(args.period)
    if args.stage in ("enrich", "all"):
        out["enrich"] = enrich.run(args.period)
    if args.stage in ("reconcile", "all"):
        out["reconcile"] = reconcile.run(args.period)
    print(json.dumps(out, indent=2, default=str, ensure_ascii=False))


if __name__ == "__main__":
    main()

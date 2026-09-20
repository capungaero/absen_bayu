#!/usr/bin/env bash
set -euo pipefail

POOL_CONFIG="/etc/php/8.3/fpm/pool.d/absen.conf"

key=$(grep '^env\[ADMIN_API_KEY\]' "$POOL_CONFIG" | sed 's/^[^=]*= //')

if [[ -z "$key" ]]; then
    echo "ADMIN_API_KEY belum tersedia." >&2
    exit 1
fi

curl --fail --silent --show-error \
    --resolve absen.4dm1n.my.id:443:127.0.0.1 \
    --header "Authorization: Bearer $key" \
    "https://absen.4dm1n.my.id/temuan/cron"

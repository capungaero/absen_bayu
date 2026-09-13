#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/absen.4dm1n.my.id"
POOL_CONFIG="/etc/php/8.3/fpm/pool.d/absen.conf"

while IFS= read -r line; do
    name=${line#env[}
    name=${name%%]*}
    value=${line#*= }
    export "$name=$value"
done < <(grep '^env\[' "$POOL_CONFIG")

export MYSQL_PWD="$ABSEN_DB_PASS"
token=$(mysql \
    --host="$ABSEN_DB_HOST" \
    --port="$ABSEN_DB_PORT" \
    --user="$ABSEN_DB_USER" \
    --database="$ABSEN_DB_NAME" \
    --batch --skip-column-names \
    --execute="SELECT cron_token FROM wa_config ORDER BY id DESC LIMIT 1")
unset MYSQL_PWD

if [[ -z "$token" ]]; then
    echo "Token cron WA belum tersedia." >&2
    exit 1
fi

curl --fail --silent --show-error \
    --resolve absen.4dm1n.my.id:443:127.0.0.1 \
    "https://absen.4dm1n.my.id/wa/cron/$token"

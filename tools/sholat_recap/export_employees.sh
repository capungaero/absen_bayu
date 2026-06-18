#!/usr/bin/env bash
# Ekspor SEKALI daftar karyawan (finger_id -> nama, cabang) dari server ke employees.csv.
# Setelah file ini ada, recap.py jalan 100% offline (tak perlu server lagi).
# Jalankan:  bash export_employees.sh
set -euo pipefail
OUT="$(dirname "$0")/employees.csv"
SQL="SELECT u.employee_code AS finger_id,
  TRIM(CONCAT(u.first_name,' ',IFNULL(u.last_name,''))) AS name,
  CASE p.branch_id WHEN 1 THEN 'RG' WHEN 2 THEN 'GBR' ELSE 'RG' END AS branch
FROM users u
LEFT JOIN position p ON p.id=u.position_id
WHERE u.active=1 AND u.employee_code IS NOT NULL AND u.employee_code<>''
ORDER BY u.employee_code"

echo "Mengambil daftar karyawan dari server..."
{
  echo "finger_id,name,branch"
  ssh -p 2223 tiffany.my.id \
    "mysql --defaults-extra-file=\$HOME/.absen.cnf tifx3722_newtiffa_timesheet -N -B -e \"$SQL\"" \
    2>/dev/null | grep -v 'fork: retry' | awk -F'\t' 'NF>=1{printf "%s,\"%s\",%s\n",$1,$2,$3}'
} > "$OUT"
echo "Tersimpan: $OUT ($(wc -l < "$OUT") baris)"

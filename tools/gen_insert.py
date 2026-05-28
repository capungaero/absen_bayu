import openpyxl
import time

def read_excel(path):
    wb = openpyxl.load_workbook(path)
    ws = wb.active
    rows = []
    for row in ws.iter_rows(min_row=4, values_only=True):
        if row[0] is not None and str(row[0]).strip() != '' and str(row[0]).isdigit():
            rows.append(row)
    return rows

POS_MAP = {
    'PRM': 50, 'ADM': 1, 'CS': 61, 'SPV': 51, 'KSR': 48,
    'FNC': 49, 'SCR': 54, 'HRD': 52, 'GD': 59, 'MC': 58,
    'CSO': 57, 'KPS': 56, 'KPA': 55, 'MD': 53, 'KT': 62,
    'KOD': 63, 'PP': 72, 'SM': 74, 'ME': 75, 'ASM': 77
}

SUB_MAP = {
    'BRL': 6, 'CHI': 6, 'TFNY': 6, 'KIA': 6, 'RYG': 13,
    'REY': 13, 'MOI': 12, 'DRG': 6, 'CLS': 6,
    'MART-SDR': 7, 'HW-GAMBIR': 9, 'HW-SDR': 6
}

sdr = read_excel(r'e:\VIBECODING\absen_bayu\Daftar Karyawan TIFFANY HOUSEWARE SDR - Kota Kota Payakumbuh.xlsx')

NEW_CODES = {536, 537, 538, 539, 540, 541}
new_employees = [r for r in sdr if int(r[0]) in NEW_CODES]

PASSWORD = '$2y$10$d9cCwlRia3rE2bSm0gVX.O7w1hUonRTO3GTqaloMKjKdYxSoA6q96'
CREATED_ON = int(time.time())

lines = []
lines.append('-- Insert 6 karyawan baru (536-541)')
lines.append('SET @now = NOW();')
lines.append('')

for r in sorted(new_employees, key=lambda x: int(x[0])):
    emp_code = str(int(r[0]))
    nik      = str(r[1]) if r[1] else ''
    nama     = str(r[2]).replace("'", "''")
    join_date= str(r[3]) if r[3] else ''
    pos_code = str(r[4]).strip() if r[4] else ''
    sub_code = str(r[5]).strip() if r[5] else ''
    salary   = int(r[6]) if r[6] else 0
    sal_min  = int(r[7]) if r[7] else 0
    overtime = int(r[8]) if r[8] else 10000
    phone    = str(r[9]) if r[9] else ''
    email    = str(r[10]) if r[10] else ''
    addr     = str(r[11]).replace("'", "''") if r[11] else ''
    status_work = str(r[12]).lower() if r[12] else 'training'
    status_exp  = str(r[13]) if r[13] else None
    acc_no   = str(r[14]) if r[14] else ''
    bank     = str(r[15]).replace("'", "''") if r[15] else ''
    acc_name = str(r[16]).replace("'", "''") if r[16] else ''

    pos_id = POS_MAP.get(pos_code, 50)
    sub_id = SUB_MAP.get(sub_code, 6)
    sw_map = {'tetap': 'permanent', 'kontrak': 'contract', 'training': 'training'}
    sw = sw_map.get(status_work, 'training')
    exp_sql = "'" + status_exp + "'" if status_exp else 'NULL'

    lines.append(f'-- {emp_code}: {nama}')
    lines.append(
        "INSERT INTO users (position_id, subdivision_id, ip_address, password, email, created_on, active, "
        "first_name, last_name, join_date, employee_code, phone, salary, salary_minimum, overtime_hour_rate, "
        "employee_address, status_work, status_work_expiration, account_number, account_bank, account_name, "
        "npwp_number, ptkp_status, created_time) VALUES"
    )
    lines.append(
        f"({pos_id}, {sub_id}, '0.0.0.0', '{PASSWORD}', '{email}', {CREATED_ON}, 1, "
        f"'{nama}', NULL, '{join_date}', '{emp_code}', '{phone}', {salary}, {sal_min}, {overtime}, "
        f"'{addr}', '{sw}', {exp_sql}, '{acc_no}', '{bank}', '{acc_name}', '', 'TK/0', @now);"
    )
    lines.append('SET @new_id = LAST_INSERT_ID();')
    lines.append("INSERT INTO users_groups (user_id, group_id) VALUES (@new_id, 2);")
    lines.append('')

sql = '\n'.join(lines)
out_path = r'e:\VIBECODING\absen_bayu\database\insert_karyawan_baru_536_541.sql'
with open(out_path, 'w', encoding='utf-8') as f:
    f.write(sql)
print('Done. Lines:', len(lines))
print(sql[:1000])

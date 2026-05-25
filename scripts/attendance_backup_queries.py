from __future__ import annotations

from typing import Dict, List


EMPLOYEE_BACKUP_FIELDS = [
    "id",
    "employee_code",
    "employee_name",
    "active",
    "branch_code",
    "branch_name",
    "division_name",
    "position_name",
    "location",
    "join_date",
    "status_work",
    "status_work_expiration",
]

SCHEDULE_BACKUP_FIELDS = [
    "id",
    "user_id",
    "employee_code",
    "employee_name",
    "additional_date",
    "additional_type",
    "shift_id",
    "shift_code",
    "shift_name",
    "start_time",
    "end_time",
    "start_time_late",
    "created_at",
    "updated_at",
    "deleted_at",
]

PRESENCE_BACKUP_FIELDS = [
    "id",
    "user_id",
    "employee_code",
    "employee_name",
    "flow_date",
    "entry_time",
    "entry_time_late",
    "out_time",
    "rest_time_in",
    "rest_time_out",
    "rest_time_late",
    "presence_type",
    "presence_status",
    "input_by",
    "created_at",
    "updated_at",
    "flag",
]

SHIFT_BACKUP_FIELDS = [
    "id",
    "branch_id",
    "shift_code",
    "shift_name",
    "is_active",
    "start_time",
    "end_time",
    "start_time_in",
    "start_time_out",
    "start_time_late",
    "end_time_in",
    "end_time_out",
    "deleted_at",
]

LEAVE_BACKUP_FIELDS = [
    "id",
    "user_id",
    "employee_code",
    "employee_name",
    "leave_type",
    "leave_start",
    "leave_end",
    "leave_status",
    "created_at",
    "updated_at",
    "deleted_at",
]

BACKUP_FIELD_MAP: Dict[str, List[str]] = {
    "backup_employees.csv": EMPLOYEE_BACKUP_FIELDS,
    "backup_schedules.csv": SCHEDULE_BACKUP_FIELDS,
    "backup_presence.csv": PRESENCE_BACKUP_FIELDS,
    "backup_shifts.csv": SHIFT_BACKUP_FIELDS,
    "backup_leave.csv": LEAVE_BACKUP_FIELDS,
}


def q(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def build_backup_queries(start: str, end: str, users: str) -> Dict[str, str]:
    employees_sql = f"""
        SELECT
            u.id,
            u.employee_code,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
            u.active,
            COALESCE(b.branch_code, '') AS branch_code,
            COALESCE(b.branch_name, '') AS branch_name,
            COALESCE(sd.subdivision_name, '') AS division_name,
            COALESCE(p.position_name, '') AS position_name,
            COALESCE(u.location, '') AS location,
            u.join_date,
            u.status_work,
            u.status_work_expiration
        FROM users u
        LEFT JOIN position p ON p.id = u.position_id
        LEFT JOIN branch b ON b.id = p.branch_id
        LEFT JOIN subdivision sd ON sd.id = u.subdivision_id
        WHERE u.id IN ({users})
        ORDER BY employee_name
    """
    schedules_sql = f"""
        SELECT
            usa.id,
            usa.user_id,
            u.employee_code,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
            usa.additional_date,
            usa.additional_type,
            usa.shift_id,
            s.shift_code,
            s.shift_name,
            s.start_time,
            s.end_time,
            s.start_time_late,
            usa.created_at,
            usa.updated_at,
            usa.deleted_at
        FROM users_shift_additional usa
        LEFT JOIN users u ON u.id = usa.user_id
        LEFT JOIN shift s ON s.id = usa.shift_id
        WHERE usa.additional_date BETWEEN {q(start)} AND {q(end)}
          AND usa.user_id IN ({users})
        ORDER BY usa.user_id, usa.additional_date, usa.id
    """
    presence_sql = f"""
        SELECT
            p.id,
            p.user_id,
            u.employee_code,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
            p.flow_date,
            p.entry_time,
            p.entry_time_late,
            p.out_time,
            p.rest_time_in,
            p.rest_time_out,
            p.rest_time_late,
            p.presence_type,
            p.presence_status,
            p.input_by,
            p.created_at,
            p.updated_at,
            p.flag
        FROM presence p
        LEFT JOIN users u ON u.id = p.user_id
        WHERE p.flow_date BETWEEN {q(start)} AND {q(end)}
          AND p.user_id IN ({users})
        ORDER BY p.user_id, p.flow_date, p.id
    """
    shifts_sql = """
        SELECT
            id,
            branch_id,
            shift_code,
            shift_name,
            is_active,
            start_time,
            end_time,
            start_time_in,
            start_time_out,
            start_time_late,
            end_time_in,
            end_time_out,
            deleted_at
        FROM shift
        ORDER BY branch_id, shift_code, id
    """
    leaves_sql = f"""
        SELECT
            l.id,
            l.user_id,
            u.employee_code,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS employee_name,
            l.leave_type,
            l.leave_start,
            l.leave_end,
            l.leave_status,
            l.created_at,
            l.updated_at,
            l.deleted_at
        FROM `leave` l
        LEFT JOIN users u ON u.id = l.user_id
        WHERE l.leave_start <= {q(end)}
          AND l.leave_end >= {q(start)}
          AND l.user_id IN ({users})
        ORDER BY l.user_id, l.leave_start, l.id
    """
    return {
        "backup_employees.csv": employees_sql,
        "backup_schedules.csv": schedules_sql,
        "backup_presence.csv": presence_sql,
        "backup_shifts.csv": shifts_sql,
        "backup_leave.csv": leaves_sql,
    }

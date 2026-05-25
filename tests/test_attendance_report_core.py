import sys
import unittest
from datetime import date
from pathlib import Path


PROJECT_ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(PROJECT_ROOT / "scripts"))

from attendance_report_core import build_report, classify_day, date_range


class AttendanceReportCoreTest(unittest.TestCase):
    def test_absen_when_work_schedule_has_no_presence(self):
        status, notes = classify_day(
            date(2026, 5, 18),
            date(2026, 5, 24),
            {"additional_type": "work", "shift_id": "1", "shift_code": "P", "shift_name": "Pagi"},
            None,
            {"all": [], "approved": []},
            [],
        )
        self.assertEqual(status, "ABSEN")
        self.assertEqual(notes, [])

    def test_late_uses_entry_time_late(self):
        status, notes = classify_day(
            date(2026, 5, 18),
            date(2026, 5, 24),
            {"additional_type": "work", "shift_id": "1", "shift_code": "P", "shift_name": "Pagi"},
            {
                "presence_type": "normal",
                "entry_time": "2026-05-18 07:55:00",
                "out_time": "2026-05-18 18:00:00",
                "entry_time_late": "5",
            },
            {"all": [], "approved": []},
            [],
        )
        self.assertEqual(status, "TERLAMBAT")
        self.assertEqual(notes, [])

    def test_presence_without_schedule_is_anomaly(self):
        status, notes = classify_day(
            date(2026, 5, 18),
            date(2026, 5, 24),
            None,
            {"presence_type": "normal", "entry_time": "2026-05-18 08:00:00", "out_time": "2026-05-18 18:00:00"},
            {"all": [], "approved": []},
            [],
        )
        self.assertEqual(status, "PRESENSI_TANPA_JADWAL")
        self.assertIn("Presensi ada tanpa jadwal", notes)

    def test_leave_approve_without_presence_counts_as_leave_type(self):
        status, notes = classify_day(
            date(2026, 5, 18),
            date(2026, 5, 24),
            {"additional_type": "work", "shift_id": "1", "shift_code": "P", "shift_name": "Pagi"},
            None,
            {"all": [], "approved": []},
            [{"leave_status": "approve", "leave_type": "cuti"}],
        )
        self.assertEqual(status, "CUTI")
        self.assertIn("Leave approve tanpa row presence", notes)

    def test_build_report_summarizes_employee_and_dates(self):
        employees = [
            {
                "id": "10",
                "employee_code": "337",
                "employee_name": "ANNISA",
                "branch_code": "SDR",
                "branch_name": "TIFFANY HOUSEWARE",
                "division_name": "SALES",
                "position_name": "KRU",
                "location": "HOUSEWARE",
            }
        ]
        days = date_range(date(2026, 5, 18), date(2026, 5, 19))
        schedules = {
            "10|2026-05-18": {
                "schedule_id": "1",
                "additional_type": "work",
                "shift_id": "1",
                "shift_code": "P",
                "shift_name": "Pagi",
                "start_time": "07:50:00",
                "end_time": "18:00:00",
                "start_time_late": "07:50:00",
            },
            "10|2026-05-19": {
                "schedule_id": "2",
                "additional_type": "free",
            },
        }
        presence = {
            "10|2026-05-18": {
                "all": [],
                "approved": [
                    {
                        "id": "99",
                        "presence_status": "approved",
                        "presence_type": "normal",
                        "entry_time": "2026-05-18 08:00:00",
                        "out_time": "2026-05-18 18:00:00",
                        "entry_time_late": "10",
                    }
                ],
            }
        }
        report = build_report(employees, days, date(2026, 5, 19), schedules, presence, {})
        self.assertEqual(len(report["details"]), 2)
        self.assertEqual(report["details"][0]["status"], "TERLAMBAT")
        self.assertEqual(report["employee_summaries"][0]["terlambat"], 1)
        self.assertEqual(report["employee_summaries"][0]["libur"], 1)
        self.assertEqual(report["date_summaries"][0]["menit_telat"], 10)


if __name__ == "__main__":
    unittest.main()

from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]


def test_notification_settings_are_available_from_tools_menu():
    menu = (ROOT / "application/views/layout/admin.php").read_text(encoding="utf-8")
    routes = (ROOT / "application/config/routes.php").read_text(encoding="utf-8")

    assert "Tools" in menu
    assert "tools/notifikasi-absensi" in menu
    assert "Notifikasi WA Absensi" in menu
    assert "if($role === 'admin')" in menu
    assert menu.count('id="topnav-tools"') == 1
    assert "$route['tools/notifikasi-absensi'] = 'Wa/config';" in routes


def test_notification_form_exposes_operational_controls():
    view = (ROOT / "application/views/wa/config.php").read_text(encoding="utf-8")

    for control in (
        'id="target_phones"',
        'id="send_morning_enabled"',
        'id="morning_time"',
        'id="send_afternoon_enabled"',
        'id="afternoon_time"',
        'id="notif_absent_enabled"',
        'id="absent_notif_time"',
    ):
        assert control in view

    assert "Pengaturan Gateway" in view
    assert "Status Pengiriman" in view
    assert "value=\"<?= htmlspecialchars($cfg['secret']" not in view


def test_report_pdf_is_published_atomically_and_manual_result_is_visible():
    library = (ROOT / "application/libraries/Attendance_daily_report.php").read_text(encoding="utf-8")
    controller = (ROOT / "application/controllers/Wa.php").read_text(encoding="utf-8")

    assert "tempnam($directory, '.rekap_')" in library
    assert "rename($temporary_path, $path)" in library
    assert "chown($directory, $app_owner)" in library
    assert controller.count("$this->session->set_flashdata($flash_key") == 2
    assert "Gagal membuat PDF laporan." in controller

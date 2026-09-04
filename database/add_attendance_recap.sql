-- Tabel rekap harian absen KERJA saja (bukan sholat), sudah final dihitung
-- terhadap jadwal shift. Diisi/diperbarui oleh proses independen tiap 30
-- menit (hr/presence/attendance_recap_refresh), sumbernya attendance_tap +
-- users_shift_additional + attendance_day -- lepas dari kesegaran/siklus
-- sync presence utama. Tidak menangani sholat.
CREATE TABLE IF NOT EXISTS attendance_recap (
    user_id INT(11) NOT NULL,
    flow_date DATE NOT NULL,
    employee_code VARCHAR(100) DEFAULT NULL,
    employee_name VARCHAR(255) DEFAULT NULL,
    branch_id INT(11) DEFAULT NULL,
    branch_name VARCHAR(200) DEFAULT NULL,
    position_name VARCHAR(200) DEFAULT NULL,
    shift_id INT(11) DEFAULT NULL,
    shift_name VARCHAR(200) DEFAULT NULL,
    shift_time_in TIME DEFAULT NULL,
    shift_time_out TIME DEFAULT NULL,
    entry_time TIME DEFAULT NULL,
    out_time TIME DEFAULT NULL,
    late_minutes INT(11) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'belum_absen' COMMENT 'hadir|terlambat|belum_absen',
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, flow_date),
    KEY idx_recap_date_branch (flow_date, branch_id),
    KEY idx_recap_status (flow_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

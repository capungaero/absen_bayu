-- SQLite-compatible subset dari tabel `presence` (sumber asli:
-- newtiffa_timesheet.sql.txt). Tidak semua kolom diikutkan — hanya yang
-- ditulis oleh Attlog_parser::classify_taps() + kolom yang ada DEFAULT.
-- enum digant TEXT dengan CHECK supaya tetap sama validasinya.

CREATE TABLE presence (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    entry_time TEXT DEFAULT NULL,
    entry_time_late INTEGER DEFAULT 0,
    out_time TEXT DEFAULT NULL,
    rest_time_in TEXT DEFAULT NULL,
    rest_time_out TEXT DEFAULT NULL,
    rest_time_late INTEGER DEFAULT 0,
    subuh_time_in TEXT DEFAULT NULL,
    subuh_time_out TEXT DEFAULT NULL,
    subuh_time_late INTEGER DEFAULT 0,
    dzuhur_time_in TEXT DEFAULT NULL,
    dzuhur_time_out TEXT DEFAULT NULL,
    dzuhur_time_late INTEGER DEFAULT 0,
    ashar_time_in TEXT DEFAULT NULL,
    ashar_time_out TEXT DEFAULT NULL,
    ashar_time_late INTEGER DEFAULT 0,
    maghrib_time_in TEXT DEFAULT NULL,
    maghrib_time_out TEXT DEFAULT NULL,
    maghrib_time_late INTEGER DEFAULT 0,
    isha_time_in TEXT DEFAULT NULL,
    isha_time_out TEXT DEFAULT NULL,
    isha_time_late INTEGER DEFAULT 0,
    friday_time_in TEXT DEFAULT NULL,
    friday_time_out TEXT DEFAULT NULL,
    friday_time_late INTEGER DEFAULT 0,
    flow_date TEXT DEFAULT NULL,
    created_at TEXT DEFAULT NULL,
    updated_at TEXT DEFAULT NULL,
    input_by TEXT DEFAULT 'system' CHECK (input_by IN ('system','manual')),
    input_by_user_id INTEGER DEFAULT NULL,
    presence_get_paid INTEGER DEFAULT 100,
    presence_type TEXT DEFAULT 'normal' CHECK (presence_type IN ('normal','izin','cuti','sakit')),
    presence_status TEXT DEFAULT 'approved' CHECK (presence_status IN ('approved','deny','pending')),
    is_overtime TEXT DEFAULT '0' CHECK (is_overtime IN ('0','1')),
    flag TEXT DEFAULT '0' CHECK (flag IN ('0','1'))
);

CREATE INDEX idx_presence_user_flow ON presence (user_id, flow_date);
CREATE INDEX idx_presence_flow_user ON presence (flow_date, user_id);

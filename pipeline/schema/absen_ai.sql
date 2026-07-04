-- ==========================================================================
-- Skema database absen_ai — data mart pipeline AI absensi/payroll Tiffany
-- Lokasi: MariaDB di VPS Hermes. Dibuat idempoten (IF NOT EXISTS).
-- Konvensi: period = 'YYYY-MM' (periode payroll 26 (M-1) .. 25 M),
--           branch_id mengikuti produksi (1=SDR, 2=GBR).
-- Aturan tulis: tiap stage me-REPLACE isi periodenya sendiri (DELETE WHERE
-- period=? lalu INSERT batch) — tidak pernah menyentuh DB produksi.
-- ==========================================================================
CREATE DATABASE IF NOT EXISTS absen_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE absen_ai;

-- ------------------------------------------------------------------ LAPIS RAW
CREATE TABLE IF NOT EXISTS etl_runs (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  stage        VARCHAR(20) NOT NULL,          -- fetch|parse|enrich|compute|report|reconcile
  period       CHAR(7)     NOT NULL,
  branch_id    TINYINT     NULL,
  started_at   DATETIME    NOT NULL,
  finished_at  DATETIME    NULL,
  status       VARCHAR(10) NOT NULL DEFAULT 'running',  -- running|ok|error
  detail       JSON        NULL,              -- stats parser, jumlah baris, error, dsb
  KEY idx_runs (period, stage, started_at)
);

CREATE TABLE IF NOT EXISTS raw_files (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  period      CHAR(7)      NOT NULL,
  branch_id   TINYINT      NOT NULL,
  file_path   VARCHAR(255) NOT NULL,          -- arsip immutable di PIPELINE_RAW_DIR
  sha256      CHAR(64)     NOT NULL,
  source      VARCHAR(30)  NOT NULL,          -- cloud|manual
  fetched_at  DATETIME     NOT NULL,
  UNIQUE KEY uq_rawfile (sha256),
  KEY idx_rawfiles (period, branch_id)
);

CREATE TABLE IF NOT EXISTS raw_taps (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  period      CHAR(7)     NOT NULL,
  branch_id   TINYINT     NOT NULL,
  finger_id   VARCHAR(20) NOT NULL,           -- = users.employee_code
  tap_date    DATE        NOT NULL,
  tap_time    TIME        NOT NULL,
  raw_file_id INT         NULL,
  anomaly     VARCHAR(30) NULL,               -- odd_count|single_tap|excessive|dup_code
  KEY idx_taps (period, branch_id, finger_id, tap_date)
);

-- ------------------------------------------------------------- LAPIS SNAPSHOT
-- Salinan master produksi yang DIBEKUKAN per periode (anti anakronisme config)
CREATE TABLE IF NOT EXISTS config_snapshot (
  period      CHAR(7)     NOT NULL,
  scope       VARCHAR(30) NOT NULL,           -- branch|shift|insentif|deduction|bpjs_config|double_deduction_date|constants
  payload     JSON        NOT NULL,           -- seluruh baris master scope tsb
  snapped_at  DATETIME    NOT NULL,
  PRIMARY KEY (period, scope)
);

CREATE TABLE IF NOT EXISTS ref_employee (
  period        CHAR(7)      NOT NULL,
  user_id       INT          NOT NULL,
  employee_code VARCHAR(20)  NOT NULL,
  full_name     VARCHAR(120) NOT NULL,
  branch_id     TINYINT      NOT NULL,
  position_name VARCHAR(80)  NULL,
  subdivision   VARCHAR(120) NULL,
  salary        BIGINT       NOT NULL DEFAULT 0,
  salary_minimum BIGINT      NOT NULL DEFAULT 0,
  ptkp_status   VARCHAR(20)  NULL,
  status_work   VARCHAR(20)  NULL,
  join_date     DATE         NULL,
  is_fine_system  TINYINT    NOT NULL DEFAULT 0,   -- diturunkan dari branch
  is_pray_system  TINYINT    NOT NULL DEFAULT 0,
  active        TINYINT      NOT NULL DEFAULT 1,
  PRIMARY KEY (period, user_id),
  KEY idx_refemp_code (period, employee_code)
);

CREATE TABLE IF NOT EXISTS ref_shift_schedule (
  period      CHAR(7)  NOT NULL,
  user_id     INT      NOT NULL,
  sched_date  DATE     NOT NULL,
  shift_id    INT      NULL,
  shift_code  VARCHAR(20) NULL,
  sched_type  VARCHAR(10) NOT NULL,            -- work|free
  is_no_sc    TINYINT  NOT NULL DEFAULT 0,
  shift_json  JSON     NULL,                   -- jam & tarif denda shift (start/end/rest/late_amount_*)
  PRIMARY KEY (period, user_id, sched_date)
);

CREATE TABLE IF NOT EXISTS ref_leave (
  period      CHAR(7) NOT NULL,
  leave_id    INT     NOT NULL,
  user_id     INT     NOT NULL,
  leave_type  VARCHAR(10) NOT NULL,            -- izin|sakit|cuti
  leave_start DATE    NOT NULL,
  leave_end   DATE    NOT NULL,
  leave_range INT     NOT NULL,
  has_proof   TINYINT NOT NULL DEFAULT 0,
  status      VARCHAR(10) NOT NULL,            -- approve|... (hanya approve yang dipakai engine)
  PRIMARY KEY (period, leave_id),
  KEY idx_refleave (period, user_id)
);

CREATE TABLE IF NOT EXISTS ref_manual_inputs (
  period      CHAR(7) NOT NULL,
  user_id     INT     NOT NULL,
  input_type  VARCHAR(20) NOT NULL,            -- overtime|insentif_manual|deduction_manual|bpjs_payment
  ref_id      INT     NOT NULL,                -- id baris asal di produksi
  payload     JSON    NOT NULL,
  PRIMARY KEY (period, input_type, ref_id),
  KEY idx_refmanual (period, user_id, input_type)
);

-- ---------------------------------------------------------- LAPIS SIAP-PAKAI
CREATE TABLE IF NOT EXISTS rekap_absensi_harian (
  period        CHAR(7) NOT NULL,
  user_id       INT     NOT NULL,
  tanggal       DATE    NOT NULL,
  shift_code    VARCHAR(20) NULL,
  status        VARCHAR(15) NOT NULL,          -- hadir|hadir_sebagian|alfa|off|no_sc|izin|sakit|cuti|belum
  entry_time    TIME NULL,
  out_time      TIME NULL,
  entry_late_m  INT  NOT NULL DEFAULT 0,
  rest_in       TIME NULL,
  rest_out      TIME NULL,
  rest_late_m   INT  NOT NULL DEFAULT 0,
  early_leave_m INT  NOT NULL DEFAULT 0,
  source        VARCHAR(10) NOT NULL DEFAULT 'dat',   -- dat|presence (fase transisi)
  PRIMARY KEY (period, user_id, tanggal),
  KEY idx_rekap_tgl (tanggal)
);

CREATE TABLE IF NOT EXISTS rekap_sholat_harian (
  period     CHAR(7) NOT NULL,
  user_id    INT     NOT NULL,
  tanggal    DATE    NOT NULL,
  waktu      VARCHAR(10) NOT NULL,             -- subuh|dzuhur|ashar|maghrib|isha|friday
  time_in    TIME NULL,
  time_out   TIME NULL,
  late_m     INT  NOT NULL DEFAULT 0,
  denda      INT  NOT NULL DEFAULT 0,
  PRIMARY KEY (period, user_id, tanggal, waktu)
);

CREATE TABLE IF NOT EXISTS rekap_denda_harian (
  id         BIGINT AUTO_INCREMENT PRIMARY KEY,
  period     CHAR(7) NOT NULL,
  user_id    INT     NOT NULL,
  tanggal    DATE    NULL,                     -- NULL utk item periode (mis. pulang awal akumulatif)
  jenis      VARCHAR(30) NOT NULL,             -- telat_masuk|telat_istirahat|istirahat_tak_tercatat|sholat|setengah_hari|potongan_izin|alfa_weekday|alfa_weekend|alfa_double|pulang_awal
  menit      INT     NOT NULL DEFAULT 0,
  nominal    INT     NOT NULL DEFAULT 0,
  rule_trace JSON    NULL,                     -- {rule, params, inputs, doc:"03-denda#..."}
  KEY idx_denda (period, user_id, jenis)
);

CREATE TABLE IF NOT EXISTS rekap_lembur_periode (
  period     CHAR(7) NOT NULL,
  user_id    INT     NOT NULL,
  total_jam  DECIMAL(6,2) NOT NULL DEFAULT 0,
  nominal    INT     NOT NULL DEFAULT 0,
  detail     JSON    NULL,
  PRIMARY KEY (period, user_id)
);

CREATE TABLE IF NOT EXISTS rekap_komisi_periode (
  period     CHAR(7) NOT NULL,
  user_id    INT     NOT NULL,
  insentif_id INT    NOT NULL,                 -- id master produksi
  nama       VARCHAR(80) NOT NULL,
  sumber     VARCHAR(10) NOT NULL,             -- auto|manual|formula
  eligible   TINYINT NULL,                     -- utk 5 komisi auto
  nominal    INT     NOT NULL DEFAULT 0,
  syarat     JSON    NULL,                     -- [{label, value, ok}] utk penjelasan AI
  PRIMARY KEY (period, user_id, insentif_id)
);

CREATE TABLE IF NOT EXISTS rekap_payroll_periode (
  period            CHAR(7) NOT NULL,
  user_id           INT     NOT NULL,
  -- bentuk meniru payroll_detail produksi utk paritas kolom-per-kolom
  presence_count    INT NOT NULL DEFAULT 0,
  presence_max      INT NOT NULL DEFAULT 0,
  salary_in_basic   BIGINT NOT NULL DEFAULT 0,
  salary_in_overtime BIGINT NOT NULL DEFAULT 0,
  salary_in_insentive BIGINT NOT NULL DEFAULT 0,
  salary_out_fine   BIGINT NOT NULL DEFAULT 0,
  salary_out_work   BIGINT NOT NULL DEFAULT 0,
  salary_out_together BIGINT NOT NULL DEFAULT 0,
  salary_out_deduction BIGINT NOT NULL DEFAULT 0,
  salary_basic_out_off_work BIGINT NOT NULL DEFAULT 0,
  salary_basic_out_alfa BIGINT NOT NULL DEFAULT 0,
  salary_thp        BIGINT NOT NULL DEFAULT 0,
  salary_debt       BIGINT NOT NULL DEFAULT 0,
  detail            JSON   NULL,               -- breakdown lengkap + rule_trace agregat
  PRIMARY KEY (period, user_id)
);

CREATE TABLE IF NOT EXISTS rekonsiliasi_diff (
  id         BIGINT AUTO_INCREMENT PRIMARY KEY,
  period     CHAR(7) NOT NULL,
  level      VARCHAR(5)  NOT NULL,             -- L1|L2|L3
  user_id    INT     NULL,
  tanggal    DATE    NULL,
  field      VARCHAR(60) NOT NULL,
  nilai_ai   VARCHAR(120) NULL,
  nilai_app  VARCHAR(120) NULL,
  klasifikasi VARCHAR(20) NOT NULL DEFAULT 'UNCLASSIFIED', -- MANUAL_EDIT|PHANTOM|CONFIG_DRIFT|RULE_CHANGE|BUG|UNCLASSIFIED
  keterangan VARCHAR(255) NULL,
  run_at     DATETIME NOT NULL,
  KEY idx_rekon (period, level, klasifikasi)
);

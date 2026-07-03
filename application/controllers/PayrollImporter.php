<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once FCPATH.'lib/vendor/autoload.php';

/**
 * Payroll Importer — alat impor komisi/insentif & potongan manual dari Excel
 * dengan format judul kolom bebas. Admin memetakan kolom Excel ke kolom DB
 * lewat UI tarik-garis (tools/payroll_importer). Data masuk ke tabel
 * payroll_insentif & payroll_deduction (sama seperti import_component bawaan).
 *
 * Auth: ion_auth session + role check (admin/admin-branch/hr). Endpoint
 * dikecualikan dari CSRF (lihat config csrf_exclude_uris) — sama pola dgn
 * payroll_sim — karena SPA same-origin & sudah dilindungi sesi login.
 */
class PayrollImporter extends CI_Controller {

    public function __construct() {
        parent::__construct();
        if (!$this->ion_auth->logged_in()) {
            $this->_die(['error' => 'Unauthorized'], 401);
        }
        $this->role     = $this->ion_auth->get_users_groups()->row()->name;
        $this->userdata = $this->ion_auth->user()->row();
        if (!in_array($this->role, ['admin', 'admin-branch', 'hr'])) {
            $this->_die(['error' => 'Forbidden'], 403);
        }
        $this->load->model('payroll_model', 'payroll');
    }

    /** Hard-fail dari constructor: echo langsung + exit (CI _display tak jalan setelah exit). */
    private function _die($data, $code) {
        $this->output->set_status_header($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    private function _json($data, $code = 200) {
        $this->output
            ->set_status_header($code)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    private function _body() {
        $raw = file_get_contents('php://input');
        $j = json_decode($raw, true);
        return is_array($j) ? $j : [];
    }

    /** Cabang yang boleh diakses user. admin = semua; lainnya = cabang sendiri. */
    private function _allowed_branch($branch_id) {
        $branch_id = (int)$branch_id;
        if ($this->role === 'admin') return $branch_id ?: null;
        $own = (int)$this->userdata->branch_id;
        return ($branch_id === 0 || $branch_id === $own) ? $own : false;
    }

    private function _locked($branch_id, $month, $year) {
        return $this->payroll->get_detail([
            'branch_id' => (int)$branch_id,
            'month'     => (int)$month,
            'year'      => (int)$year,
        ])->num_rows() > 0;
    }

    private function _norm($s) {
        $s = strtolower(trim((string)$s));
        $s = preg_replace('/\s+/', ' ', $s);
        return $s;
    }

    // GET payroll_import/branches
    public function branches() {
        if ($this->role === 'admin') {
            $rows = $this->db->query(
                "SELECT id, branch_name FROM branch WHERE is_active=1 ORDER BY branch_name"
            )->result_array();
        } else {
            $rows = $this->db->query(
                "SELECT id, branch_name FROM branch WHERE id = ? AND is_active=1",
                [(int)$this->userdata->branch_id]
            )->result_array();
        }
        $this->_json($rows);
    }

    // GET payroll_import/targets?branch_id=&month=&year=
    public function targets() {
        $branch_id = $this->_allowed_branch($this->input->get('branch_id'));
        if ($branch_id === false || $branch_id === null) {
            return $this->_json(['error' => 'Cabang tidak valid'], 422);
        }
        $month = (int)$this->input->get('month');
        $year  = (int)$this->input->get('year');

        $insentif = $this->db->where('branch_id', $branch_id)->where('is_active', '1')
            ->where('deleted_at', null)->order_by('insentif_name', 'ASC')
            ->get('insentif')->result_array();
        $deduction = $this->db->where('branch_id', $branch_id)->where('is_active', '1')
            ->where('deleted_at', null)->order_by('deduction_name', 'ASC')
            ->get('deduction')->result_array();

        $targets = [[
            'key'      => 'employee',
            'label'    => 'Karyawan (ID / Nama)',
            'kind'     => 'key',
            'required' => true,
        ]];
        foreach ($insentif as $r) {
            $targets[] = ['key' => 'insentif_'.$r['id'], 'label' => $r['insentif_name'],
                          'kind' => 'insentif', 'id' => (int)$r['id']];
        }
        foreach ($deduction as $r) {
            $targets[] = ['key' => 'deduction_'.$r['id'], 'label' => $r['deduction_name'],
                          'kind' => 'deduction', 'id' => (int)$r['id']];
        }

        $this->_json([
            'branch_id' => (int)$branch_id,
            'month'     => $month,
            'year'      => $year,
            'locked'    => ($month && $year) ? $this->_locked($branch_id, $month, $year) : false,
            'targets'   => $targets,
        ]);
    }

    // POST payroll_import/parse  (multipart: file)
    public function parse() {
        if ($this->input->method() !== 'post') return $this->_json(['error' => 'POST required'], 405);
        if (empty($_FILES['file']['name'])) return $this->_json(['error' => 'File belum dipilih'], 422);
        if (!empty($_FILES['file']['error'])) return $this->_json(['error' => 'Upload gagal (kode '.$_FILES['file']['error'].')'], 422);

        $tmp = $_FILES['file']['tmp_name'];
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmp);
            $reader->setReadDataOnly(true);
            // formatData=false → ambil nilai numerik mentah (float), bukan string terformat
            // (mis. "103.932,69"). _amount() lalu membulatkannya dengan benar.
            $sheet = $reader->load($tmp)->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Exception $e) {
            return $this->_json(['error' => 'Gagal membaca file Excel: '.$e->getMessage()], 422);
        }

        // Buang baris kosong di awal, temukan baris header (baris pertama yg punya >=2 sel terisi)
        $header_idx = -1;
        for ($i = 0; $i < count($sheet); $i++) {
            $filled = 0;
            foreach ($sheet[$i] as $c) { if (trim((string)$c) !== '') $filled++; }
            if ($filled >= 2) { $header_idx = $i; break; }
        }
        if ($header_idx < 0) return $this->_json(['error' => 'File Excel kosong atau tidak ada header'], 422);

        $headers = $sheet[$header_idx];
        $data_rows = array_slice($sheet, $header_idx + 1);

        // Kolom + contoh nilai (maks 4 sampel non-kosong)
        $columns = [];
        foreach ($headers as $idx => $name) {
            $name = trim((string)$name);
            if ($name === '') $name = 'Kolom '.($idx + 1);
            $samples = [];
            foreach ($data_rows as $row) {
                if (count($samples) >= 4) break;
                $v = isset($row[$idx]) ? trim((string)$row[$idx]) : '';
                if ($v !== '') $samples[] = mb_strlen($v) > 24 ? mb_substr($v, 0, 24).'…' : $v;
            }
            $columns[] = ['index' => (int)$idx, 'name' => $name, 'samples' => $samples];
        }

        $token = bin2hex(random_bytes(16));
        $this->session->set_userdata('payroll_import_'.$token, [
            'rows'       => $data_rows,
            'created_at' => time(),
        ]);

        // Tebakan kolom kunci karyawan (mengandung "id"/"no"/"kode"/"karyawan"/"finger")
        $guess_key = null;
        foreach ($columns as $c) {
            $n = $this->_norm($c['name']);
            if (preg_match('/\b(id|no|nik|kode|finger)\b/', $n) || strpos($n, 'karyawan') !== false) {
                $guess_key = $c['index']; break;
            }
        }

        $this->_json([
            'token'     => $token,
            'columns'   => $columns,
            'row_count' => count($data_rows),
            'guess_key' => $guess_key,
        ]);
    }

    // POST payroll_import/commit  (json: token, branch_id, month, year, mapping{})
    public function commit() {
        if ($this->input->method() !== 'post') return $this->_json(['error' => 'POST required'], 405);
        $b = $this->_body();

        $branch_id = $this->_allowed_branch(isset($b['branch_id']) ? $b['branch_id'] : 0);
        if ($branch_id === false || $branch_id === null) return $this->_json(['error' => 'Cabang tidak valid'], 422);

        $month = (int)($b['month'] ?? 0);
        $year  = (int)($b['year'] ?? 0);
        if ($month < 1 || $month > 12 || $year < 2000) return $this->_json(['error' => 'Bulan/tahun tidak valid'], 422);

        if ($this->_locked($branch_id, $month, $year)) {
            return $this->_json(['error' => 'Periode penggajian '.str_pad($month,2,'0',STR_PAD_LEFT).'/'.$year.
                ' sudah dikunci (payroll telah dibuat). Rollback penggajian periode tersebut dulu untuk impor ulang.'], 423);
        }

        $token = preg_replace('/[^a-f0-9]/i', '', (string)($b['token'] ?? ''));
        $store = $token ? $this->session->userdata('payroll_import_'.$token) : null;
        if (empty($store) || !is_array($store)) {
            return $this->_json(['error' => 'Sesi data Excel kedaluwarsa. Unggah ulang file.'], 422);
        }
        $rows = $store['rows'];

        $mapping = isset($b['mapping']) && is_array($b['mapping']) ? $b['mapping'] : [];
        if (!isset($mapping['employee']) || $mapping['employee'] === '' || $mapping['employee'] === null) {
            return $this->_json(['error' => 'Kolom kunci "Karyawan" belum dipetakan'], 422);
        }
        $key_col = (int)$mapping['employee'];

        // Pisahkan mapping insentif & deduction → [id => colIndex]
        $ins_map = $ded_map = [];
        foreach ($mapping as $k => $col) {
            if ($k === 'employee' || $col === '' || $col === null) continue;
            if (strpos($k, 'insentif_') === 0)  $ins_map[(int)substr($k, 9)]  = (int)$col;
            elseif (strpos($k, 'deduction_') === 0) $ded_map[(int)substr($k, 10)] = (int)$col;
        }
        if (empty($ins_map) && empty($ded_map)) {
            return $this->_json(['error' => 'Belum ada kolom komisi/potongan yang dipetakan'], 422);
        }

        // Validasi id insentif/deduction milik cabang ini (cegah injeksi id liar)
        $valid_ins = $this->_valid_ids('insentif', $branch_id);
        $valid_ded = $this->_valid_ids('deduction', $branch_id);
        foreach (array_keys($ins_map) as $id) if (!isset($valid_ins[$id])) unset($ins_map[$id]);
        foreach (array_keys($ded_map) as $id) if (!isset($valid_ded[$id])) unset($ded_map[$id]);

        // Peta karyawan cabang: code→user dan nama→user
        list($by_code, $by_name) = $this->_employee_maps($branch_id);

        $time = date('Y-m-d H:i:s');
        $ins_rows = $ded_rows = $user_ids = [];
        $matched = 0; $missing = 0; $missing_keys = [];

        foreach ($rows as $row) {
            $key = isset($row[$key_col]) ? trim((string)$row[$key_col]) : '';
            if ($key === '') continue;

            $user = null;
            if (isset($by_code[$key])) {
                $user = $by_code[$key];
            } else {
                $nk = $this->_norm($key);
                if (isset($by_name[$nk])) $user = $by_name[$nk];
            }
            if (!$user) {
                $missing++;
                if (count($missing_keys) < 50 && !in_array($key, $missing_keys)) $missing_keys[] = $key;
                continue;
            }
            $uid = (int)$user['id'];
            $user_ids[$uid] = $uid;
            $matched++;

            foreach ($ins_map as $id => $col) {
                $amount = $this->_amount(isset($row[$col]) ? $row[$col] : 0);
                if ($amount <= 0) continue;
                $ins_rows[] = ['user_id' => $uid, 'insentif_id' => $id, 'insentif_year' => $year,
                               'insentif_month' => $month, 'insentif_amount' => $amount, 'created_at' => $time];
            }
            foreach ($ded_map as $id => $col) {
                $amount = $this->_amount(isset($row[$col]) ? $row[$col] : 0);
                if ($amount <= 0) continue;
                $ded_rows[] = ['user_id' => $uid, 'deduction_id' => $id, 'deduction_year' => $year,
                               'deduction_month' => $month, 'deduction_amount' => $amount, 'deduction_note' => '', 'created_at' => $time];
            }
        }

        if (empty($user_ids)) {
            return $this->_json(['error' => 'Tidak ada karyawan yang cocok. Cek pemetaan kolom kunci & isi data.',
                                 'missing' => $missing, 'missing_keys' => $missing_keys], 422);
        }

        $this->db->trans_begin();
        // Hapus nilai lama HANYA untuk insentif/deduction id yang dipetakan, milik karyawan terproses.
        $uids = array_values($user_ids);
        if (!empty($ins_map)) {
            $this->db->where_in('user_id', $uids)->where_in('insentif_id', array_keys($ins_map))
                ->where(['insentif_month' => $month, 'insentif_year' => $year])->delete('payroll_insentif');
        }
        if (!empty($ded_map)) {
            $this->db->where_in('user_id', $uids)->where_in('deduction_id', array_keys($ded_map))
                ->where(['deduction_month' => $month, 'deduction_year' => $year])->delete('payroll_deduction');
        }
        if (!empty($ins_rows)) $this->db->insert_batch('payroll_insentif', $ins_rows);
        if (!empty($ded_rows)) $this->db->insert_batch('payroll_deduction', $ded_rows);

        if (!$this->db->trans_status()) {
            $this->db->trans_rollback();
            return $this->_json(['error' => 'Gagal menyimpan ke database (transaksi dibatalkan).'], 500);
        }
        $this->db->trans_commit();
        $this->session->unset_userdata('payroll_import_'.$token);

        $this->_json([
            'status'          => true,
            'matched'         => $matched,
            'insentif_saved'  => count($ins_rows),
            'deduction_saved' => count($ded_rows),
            'missing'         => $missing,
            'missing_keys'    => $missing_keys,
        ]);
    }

    private function _valid_ids($table, $branch_id) {
        $rows = $this->db->select('id')->where('branch_id', $branch_id)->where('is_active', '1')
            ->where('deleted_at', null)->get($table)->result_array();
        $out = [];
        foreach ($rows as $r) $out[(int)$r['id']] = true;
        return $out;
    }

    private function _employee_maps($branch_id) {
        $rows = $this->db->query("
            SELECT u.id, u.employee_code, u.first_name, u.last_name
            FROM users u JOIN position p ON p.id = u.position_id
            WHERE p.branch_id = ? AND u.active = 1
        ", [(int)$branch_id])->result_array();
        $by_code = $by_name = [];
        foreach ($rows as $r) {
            $code = trim((string)$r['employee_code']);
            if ($code !== '') $by_code[$code] = $r;
            $full = $this->_norm($r['first_name'].' '.$r['last_name']);
            if ($full !== '') $by_name[$full] = $r;
        }
        return [$by_code, $by_name];
    }

    private function _amount($value) {
        // Nilai numerik asli dari Excel (float/int) → bulatkan langsung.
        // JANGAN buang titik desimal dgn preg_replace: 103932.69 akan jadi
        // "10393269..." (belasan triliun) lalu dipotong int(11) ke 2.147.483.647.
        if (is_int($value) || is_float($value)) return (int)round((float)$value);

        $s = trim((string)$value);
        if ($s === '') return 0;
        $neg = (strpos($s, '-') !== false);
        $s = preg_replace('/[^0-9.,]/', '', $s); // sisakan digit + pemisah . ,
        if ($s === '') return 0;

        // Anggap pemisah ('.' atau ',') terakhir yg diikuti 1–2 digit di ujung
        // sbg desimal; pemisah lain = ribuan (dibuang). Tangani format ID & EN.
        if (preg_match('/[.,](\d{1,2})$/', $s, $m)) {
            $dec     = $m[1];
            $intpart = preg_replace('/[.,]/', '', substr($s, 0, -(strlen($dec) + 1)));
            $num     = (float)($intpart . '.' . $dec);
        } else {
            $num = (float)preg_replace('/[.,]/', '', $s);
        }
        $num = (int)round($num);
        return $neg ? -$num : $num;
    }
}

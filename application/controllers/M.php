<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Mobile Controller — Aplikasi mobile karyawan & atasan.
 *
 * Semua logika bisnis reuse model existing (presence, overtime, leave, payroll).
 * Tidak ada sumber data baru: absensi tetap dari mesin fingerprint (view-only),
 * pengajuan lembur/izin memakai alur & validasi yang sama dengan panel HR,
 * dan approval atasan mereplikasi side-effect controller hr/Overtime & hr/Leave.
 *
 * Role:
 *   - employee            : lihat absensi/jadwal/slip, ajukan lembur & izin
 *   - supervisor/admin*   : semua di atas + ACC lembur & izin (scope cabang)
 */
class M extends CI_Controller {

    private $approver_roles = ['admin', 'admin-branch', 'supervisor'];

    public function __construct() {
        parent::__construct();

        if (!$this->ion_auth->logged_in()) {
            redirect('');
        }

        $this->userdata    = $this->ion_auth->user()->row();
        $this->role        = $this->ion_auth->get_users_groups()->row()->name;
        $this->is_approver = in_array($this->role, $this->approver_roles);

        $this->load->model('user_model', 'employee');
        $this->load->model('shift_model', 'shift');
        $this->load->model('overtime_model', 'overtime');
        $this->load->model('leave_model', 'leave');
        $this->load->model('payroll_model', 'payroll');
    }

    // =====================================================================
    // HELPERS INTERNAL
    // =====================================================================

    /** Render view konten ke dalam layout mobile. */
    private function _view($view, $data = []) {
        $data['active_menu'] = isset($data['active_menu']) ? $data['active_menu'] : '';
        $data['role']        = $this->role;
        $data['is_approver'] = $this->is_approver;
        $data['userdata']    = $this->userdata;
        $data['pending_total'] = $this->is_approver ? $this->_pending_count() : 0;
        $data['contents']    = $this->load->view('m/' . $view, $data, TRUE);
        $this->load->view('layout/mobile', $data);
    }

    /** Output JSON + sertakan csrf hash baru untuk request berikutnya. */
    private function _json($res) {
        $res['csrf'] = $this->security->get_csrf_hash();
        $this->output->set_content_type('application/json')
                     ->set_output(json_encode($res));
    }

    /** Rentang periode payroll untuk bulan/tahun (26 bln lalu s/d 25 bln ini). */
    private function _period_range($month, $year) {
        $raw  = strtotime("$year-$month-10 -1 months");
        $from = date('Y-m-' . START_PAYROLL_DATE, $raw);
        $to   = date('Y-m-' . END_PAYROLL_DATE, strtotime("$year-$month-10"));
        return [$from, $to];
    }

    /** Jumlah pengajuan pending (lembur + izin) pada cabang approver. */
    private function _pending_count() {
        $branch_id = $this->userdata->branch_id;

        $ot = $this->db->join('users', 'users.id = overtime.user_id')
                       ->join('position', 'position.id = users.position_id')
                       ->where('position.branch_id', $branch_id)
                       ->where('overtime_status', 'pending')
                       ->count_all_results('overtime');

        $lv = $this->db->join('users', 'users.id = leave.user_id')
                       ->join('position', 'position.id = users.position_id')
                       ->where('position.branch_id', $branch_id)
                       ->where('leave_status', 'pending')
                       ->count_all_results('leave');

        return (int)$ot + (int)$lv;
    }

    // =====================================================================
    // HOME / DASHBOARD
    // =====================================================================

    public function index() {
        $user_id = $this->userdata->user_id;
        $month   = date('m');
        $year    = date('Y');
        $today   = date('Y-m-d');

        list($from, $to) = $this->_period_range($month, $year);

        $presences = $this->db->where('user_id', $user_id)
                              ->where('presence_status', 'approved')
                              ->where('flow_date >=', $from)
                              ->where('flow_date <=', $to)
                              ->order_by('flow_date', 'DESC')
                              ->get('presence')->result_array();

        // Jadwal kerja (untuk hitung alpha) pada periode berjalan s/d hari ini
        $schedule = $this->db->where('user_id', $user_id)
                             ->where('additional_type', 'work')
                             ->where('additional_date >=', $from)
                             ->where('additional_date <=', min($to, $today))
                             ->get('users_shift_additional')->result_array();

        $present = $late = $izin = $alpha = 0;
        $present_dates = [];
        foreach ($presences as $p) {
            if ($p['presence_type'] === 'normal') {
                if ($p['entry_time']) {
                    $present++;
                    if ((int)$p['entry_time_late'] > 0) $late++;
                    $present_dates[$p['flow_date']] = true;
                }
            } else { // izin/cuti/sakit
                $izin++;
                $present_dates[$p['flow_date']] = true;
            }
        }
        foreach ($schedule as $s) {
            if (!isset($present_dates[$s['additional_date']])) $alpha++;
        }

        $data['stats'] = [
            'present' => $present,
            'late'    => $late,
            'absent'  => $alpha,
            'permit'  => $izin,
        ];

        // Jadwal & presensi hari ini
        $data['today_schedule'] = $this->db
            ->select('usa.*, s.shift_code, s.shift_name, s.start_time, s.end_time')
            ->join('shift s', 's.id = usa.shift_id', 'LEFT')
            ->where('usa.user_id', $user_id)
            ->where('usa.additional_date', $today)
            ->get('users_shift_additional usa')->row_array();

        $data['today_presence'] = $this->db->where('user_id', $user_id)
                                           ->where('flow_date', $today)
                                           ->get('presence')->row_array();

        $data['recent']    = array_slice($presences, 0, 5);
        $data['employee']  = $this->employee->get_detail('users.id', $user_id)->row_array();
        $data['month_name'] = get_monthname($month);

        $this->_view('home', $data + ['active_menu' => 'home']);
    }

    // =====================================================================
    // RIWAYAT ABSENSI (view-only)
    // =====================================================================

    public function presence() {
        $user_id = $this->userdata->user_id;
        $month   = (int)($this->input->get('month') ?: date('m'));
        $year    = (int)($this->input->get('year') ?: date('Y'));

        $data = $this->_month_nav($month, $year);
        list($from, $to) = $this->_period_range($month, $year);

        $data['presences'] = $this->db->where('user_id', $user_id)
                                      ->where('presence_status', 'approved')
                                      ->where('flow_date >=', $from)
                                      ->where('flow_date <=', $to)
                                      ->order_by('flow_date', 'DESC')
                                      ->get('presence')->result_array();

        $schedules = $this->db->select('additional_date, additional_type')
                              ->where('user_id', $user_id)
                              ->where('additional_date >=', $from)
                              ->where('additional_date <=', $to)
                              ->get('users_shift_additional')->result_array();

        $data['schedule_map'] = [];
        foreach ($schedules as $s) {
            $data['schedule_map'][$s['additional_date']] = $s;
        }

        $this->_view('presence', $data + ['active_menu' => 'presence']);
    }

    // =====================================================================
    // JADWAL KERJA
    // =====================================================================

    public function schedule() {
        $user_id = $this->userdata->user_id;
        $month   = (int)($this->input->get('month') ?: date('m'));
        $year    = (int)($this->input->get('year') ?: date('Y'));

        $data = $this->_month_nav($month, $year);

        $first = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
        $last  = date('Y-m-t', strtotime($first));

        $schedules = $this->db
            ->select('usa.additional_date, usa.additional_type, s.shift_code, s.shift_name, s.start_time, s.end_time')
            ->join('shift s', 's.id = usa.shift_id', 'LEFT')
            ->where('usa.user_id', $user_id)
            ->where('usa.additional_date >=', $first)
            ->where('usa.additional_date <=', $last)
            ->order_by('usa.additional_date', 'ASC')
            ->get('users_shift_additional usa')->result_array();

        $data['schedules'] = $schedules;
        $this->_view('schedule', $data + ['active_menu' => 'schedule']);
    }

    // =====================================================================
    // IZIN / CUTI / SAKIT
    // =====================================================================

    public function leave() {
        $data['leaves'] = $this->leave->get_detail('leave.user_id', $this->userdata->user_id)->result_array();
        $this->_view('leave', $data + ['active_menu' => 'leave']);
    }

    public function submit_leave() {
        if (!$this->input->is_ajax_request() || $this->input->method() !== 'post') {
            return $this->_json(['status' => false, 'message' => 'Bad Request']);
        }

        $p = $this->input->post();
        $this->form_validation->set_data($p);
        $this->form_validation->set_rules('leave_start', 'Tanggal Mulai', 'required|regex_match[/^\d{4}-\d{2}-\d{2}$/]');
        $this->form_validation->set_rules('leave_end', 'Tanggal Selesai', 'required|regex_match[/^\d{4}-\d{2}-\d{2}$/]');
        $this->form_validation->set_rules('leave_type', 'Jenis Izin', 'required|in_list[izin,sakit,cuti]');
        $this->form_validation->set_rules('leave_reason', 'Alasan', 'required|min_length[3]');

        if ($this->form_validation->run() != TRUE) {
            return $this->_json(['status' => false, 'message' => strip_tags(validation_errors())]);
        }

        $user_id = $this->userdata->user_id;
        if (strtotime($p['leave_start']) > strtotime($p['leave_end'])) {
            return $this->_json(['status' => false, 'message' => 'Tanggal mulai harus sebelum/sama dengan tanggal selesai']);
        }

        $listDay = get_daterange_list($p['leave_start'], $p['leave_end']);

        $checkPresence = $this->db->where('flow_date >=', $p['leave_start'])
                                  ->where('flow_date <=', $p['leave_end'])
                                  ->where('user_id', $user_id)
                                  ->count_all_results('presence');

        $checkOff = $this->db->where('user_id', $user_id)
                             ->where('additional_date >=', $p['leave_start'])
                             ->where('additional_date <=', $p['leave_end'])
                             ->where('additional_type', 'work')
                             ->group_by('additional_date')
                             ->get('users_shift_additional')->num_rows();

        if (!($checkPresence == 0 && $checkOff == count($listDay))) {
            return $this->_json(['status' => false, 'message' => 'Rentang tanggal sudah memiliki presensi atau bukan hari jadwal kerja Anda. Pilih tanggal yang merupakan jadwal shift dan belum ada presensi.']);
        }

        $overlap = $this->db->group_start()
                                ->group_start()
                                    ->where('leave_start >=', $p['leave_start'])
                                    ->where('leave_start <=', $p['leave_end'])
                                ->group_end()
                                ->or_group_start()
                                    ->where('leave_end >=', $p['leave_start'])
                                    ->where('leave_end <=', $p['leave_end'])
                                ->group_end()
                            ->group_end()
                            ->where('user_id', $user_id)
                            ->where('leave_status', 'pending')
                            ->count_all_results('leave');

        if ($overlap > 0) {
            return $this->_json(['status' => false, 'message' => 'Rentang tanggal sedang dalam proses pengajuan. Pilih tanggal lain.']);
        }

        // Upload bukti (opsional untuk izin/cuti, wajib untuk sakit)
        $proof = '';
        if (!empty($_FILES['leave_proof']['name'])) {
            $config['upload_path']   = './assets/images/hr/leave/';
            $config['allowed_types'] = 'png|jpeg|jpg';
            $config['file_name']     = 'leave_' . $user_id . '_' . generateRandom(5) . '_' . time();
            $config['max_size']      = 10240;
            $config['max_width']     = 6000;
            $config['max_height']    = 6000;
            $this->load->library('upload', $config);

            if (!$this->upload->do_upload('leave_proof')) {
                return $this->_json(['status' => false, 'message' => strip_tags($this->upload->display_errors())]);
            }
            $upl = $this->upload->data();
            $cfg = ['image_library' => 'gd2', 'source_image' => $upl['full_path'],
                    'quality' => '80%', 'maintain_ratio' => TRUE, 'width' => 800];
            $this->load->library('image_lib', $cfg);
            $this->image_lib->resize();
            $proof = $upl['file_name'];
        } elseif ($p['leave_type'] === 'sakit') {
            return $this->_json(['status' => false, 'message' => 'Surat keterangan sakit (foto) wajib diunggah.']);
        }

        $user = $this->db->where('id', $user_id)->get('users')->row_array();
        $totalDay = 0;
        foreach ($listDay as $d) {
            $totalDay += in_array(get_dayname($d), ['Sabtu', 'Minggu']) ? 2 : 1;
        }

        $ok = $this->leave->insert([
            'user_id'              => $user_id,
            'leave_start'          => $p['leave_start'],
            'leave_end'            => $p['leave_end'],
            'leave_range'          => diffInDays($p['leave_start'], $p['leave_end']) + 1,
            'leave_proof'          => $proof,
            'leave_type'           => $p['leave_type'],
            'leave_reason'         => $p['leave_reason'],
            'default_potongan'     => GetPotonganIzin($user['status_work']),
            'request_potongan'     => null,
            'jumlah_hari_potongan' => $totalDay,
            'leave_status'         => 'pending',
            'created_at'           => date('Y-m-d H:i:s'),
        ]);

        return $this->_json($ok
            ? ['status' => true, 'message' => 'Pengajuan izin berhasil dikirim']
            : ['status' => false, 'message' => 'Gagal menyimpan pengajuan']);
    }

    // =====================================================================
    // LEMBUR
    // =====================================================================

    public function overtime() {
        $data['overtimes'] = $this->overtime->get_detail('overtime.user_id', $this->userdata->user_id)->result_array();
        $this->_view('overtime', $data + ['active_menu' => 'overtime']);
    }

    public function submit_overtime() {
        if (!$this->input->is_ajax_request() || $this->input->method() !== 'post') {
            return $this->_json(['status' => false, 'message' => 'Bad Request']);
        }

        $p = $this->input->post();
        $this->form_validation->set_data($p);
        $this->form_validation->set_rules('overtime_date', 'Tanggal Lembur', 'required|regex_match[/^\d{4}-\d{2}-\d{2}$/]');
        $this->form_validation->set_rules('overtime_hour', 'Lama Lembur', 'required|numeric|greater_than[0]');

        if ($this->form_validation->run() != TRUE) {
            return $this->_json(['status' => false, 'message' => strip_tags(validation_errors())]);
        }

        $user_id = $this->userdata->user_id;
        $date    = date('Y-m-d', strtotime($p['overtime_date']));

        // Cegah duplikat lembur (sudah ada yang pending/approve di tanggal sama)
        $dup = $this->overtime->get_detail(['user_id' => $user_id, 'overtime_date' => $date]);
        if ($dup->num_rows() > 0 && in_array($dup->row_array()['overtime_status'], ['approve', 'pending'])) {
            return $this->_json(['status' => false, 'message' => 'Lembur untuk tanggal ini sudah pernah diajukan.']);
        }

        if (empty($_FILES['overtime_proof']['name'])) {
            return $this->_json(['status' => false, 'message' => 'Foto bukti lembur wajib diunggah.']);
        }

        $config['upload_path']   = './assets/images/hr/overtime/';
        $config['allowed_types'] = 'png|jpeg|jpg';
        $config['file_name']     = 'overtime_' . $user_id . '_' . generateRandom(5) . '_' . time();
        $config['max_size']      = 10240;
        $config['max_width']     = 10000;
        $config['max_height']    = 10000;
        $this->load->library('upload', $config);

        if (!$this->upload->do_upload('overtime_proof')) {
            return $this->_json(['status' => false, 'message' => strip_tags($this->upload->display_errors())]);
        }
        $upl = $this->upload->data();
        $cfg = ['image_library' => 'gd2', 'source_image' => $upl['full_path'],
                'quality' => '80%', 'maintain_ratio' => TRUE, 'width' => 800];
        $this->load->library('image_lib', $cfg);
        $this->image_lib->resize();

        $ok = $this->overtime->insert([
            'user_id'        => $user_id,
            'overtime_hour'  => $p['overtime_hour'],
            'overtime_date'  => $date,
            'overtime_proof' => $upl['file_name'],
            'overtime_status'=> 'pending',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        return $this->_json($ok
            ? ['status' => true, 'message' => 'Pengajuan lembur berhasil dikirim']
            : ['status' => false, 'message' => 'Gagal menyimpan pengajuan']);
    }

    // =====================================================================
    // SLIP GAJI
    // =====================================================================

    public function payroll() {
        $user_id = $this->userdata->user_id;
        $year    = (int)($this->input->get('year') ?: date('Y'));

        $data['year']      = $year;
        $data['prev_year'] = $year - 1;
        $data['next_year'] = $year + 1;

        $data['slips'] = $this->db
            ->select('pd.*, p.month, p.year, p.is_final')
            ->from('payroll_detail pd')
            ->join('payroll p', 'p.id = pd.payroll_id')
            ->where('pd.user_id', $user_id)
            ->where('p.year', $year)
            ->where('p.is_final', '1')
            ->order_by('p.month', 'DESC')
            ->get()->result_array();

        $this->_view('payroll', $data + ['active_menu' => 'payroll']);
    }

    // =====================================================================
    // APPROVAL ATASAN (lembur + izin)
    // =====================================================================

    public function approvals() {
        if (!$this->is_approver) show_404();

        $branch_id = $this->userdata->branch_id;

        $data['ot_pending'] = $this->overtime->get_detail([
            'overtime_status'    => 'pending',
            'position.branch_id' => $branch_id,
        ])->result_array();

        $data['lv_pending'] = $this->leave->get_detail([
            'leave_status'       => 'pending',
            'position.branch_id' => $branch_id,
        ])->result_array();

        // Riwayat singkat 10 keputusan terakhir (lembur + izin)
        $data['ot_history'] = $this->overtime->get_detail([
            'overtime_status !=' => 'pending',
            'position.branch_id' => $branch_id,
        ], '', 10)->result_array();

        $data['lv_history'] = $this->leave->get_detail([
            'leave_status !=' => 'pending',
            'position.branch_id' => $branch_id,
        ], '', 10)->result_array();

        $this->_view('approvals', $data + ['active_menu' => 'approvals']);
    }

    public function approve_overtime() {
        if (!$this->is_approver || !$this->input->is_ajax_request()) {
            return $this->_json(['status' => false, 'message' => 'Tidak diizinkan']);
        }

        $id     = (int)$this->input->post('id');
        $status = $this->input->post('status'); // approve | deny
        $reason = $this->input->post('reject_reason');

        if (!in_array($status, ['approve', 'deny'])) {
            return $this->_json(['status' => false, 'message' => 'Status tidak valid']);
        }

        $find = ['overtime.id' => $id, 'overtime_status' => 'pending'];
        if ($this->role != 'admin') $find['position.branch_id'] = $this->userdata->branch_id;

        if ($this->overtime->get_detail($find)->num_rows() == 0) {
            return $this->_json(['status' => false, 'message' => 'Data lembur tidak ditemukan']);
        }

        $this->overtime->update([
            'overtime_status' => $status,
            'confirm_at'      => date('Y-m-d H:i:s'),
            'reject_reason'   => $status == 'deny' ? $reason : '',
        ], $id);

        return $this->_json(['status' => true, 'message' => $status == 'approve' ? 'Lembur disetujui' : 'Lembur ditolak']);
    }

    public function approve_leave() {
        if (!$this->is_approver || !$this->input->is_ajax_request()) {
            return $this->_json(['status' => false, 'message' => 'Tidak diizinkan']);
        }

        $id     = (int)$this->input->post('id');
        $status = $this->input->post('status'); // approve | deny
        $reason = $this->input->post('reject_reason');

        if (!in_array($status, ['approve', 'deny'])) {
            return $this->_json(['status' => false, 'message' => 'Status tidak valid']);
        }

        $find = ['leave.id' => $id, 'leave_status' => 'pending'];
        if ($this->role != 'admin') $find['position.branch_id'] = $this->userdata->branch_id;

        $tr = $this->leave->get_detail($find);
        if ($tr->num_rows() == 0) {
            return $this->_json(['status' => false, 'message' => 'Data izin tidak ditemukan']);
        }
        $leave = $tr->row_array();
        $now   = date('Y-m-d H:i:s');

        $this->db->trans_begin();

        // Potongan: sakit selalu 0%, lainnya pakai request bila ada, jika tidak default.
        $potongan = 0;
        if ($status == 'approve') {
            if ($leave['leave_type'] == 'sakit') {
                $potongan = 0;
            } else {
                $potongan = ($leave['request_potongan'] !== null && $leave['request_potongan'] !== '')
                    ? (int)$leave['request_potongan']
                    : (int)$leave['default_potongan'];
            }
        }

        $this->leave->update([
            'leave_status'  => $status,
            'acc_potongan'  => $status == 'approve' ? $potongan : null,
            'confirm_at'    => $now,
            'reject_reason' => $status == 'deny' ? $reason : '',
        ], $id);

        if ($status == 'approve') {
            $range = get_daterange_list($leave['leave_start'], $leave['leave_end']);
            $this->db->where('user_id', $leave['user_id'])
                     ->where_in('flow_date', $range)
                     ->delete('presence');

            $rows = [];
            foreach ($range as $d) {
                $rows[] = [
                    'user_id'           => $leave['user_id'],
                    'flow_date'         => $d,
                    'created_at'        => $now,
                    'input_by'          => 'manual',
                    'presence_get_paid' => 100 - $potongan,
                    'presence_type'     => $leave['leave_type'],
                    'presence_status'   => 'approved',
                    'input_by_user_id'  => $this->userdata->user_id,
                    'is_overtime'       => '0',
                ];
            }
            if (!empty($rows)) $this->db->insert_batch('presence', $rows);
        }

        if ($this->db->trans_status()) {
            $this->db->trans_commit();
            return $this->_json(['status' => true, 'message' => $status == 'approve' ? 'Izin disetujui' : 'Izin ditolak']);
        }
        $this->db->trans_rollback();
        return $this->_json(['status' => false, 'message' => 'Terjadi kesalahan, coba lagi nanti']);
    }

    // =====================================================================
    // HELPER NAVIGASI BULAN
    // =====================================================================

    private function _month_nav($month, $year) {
        $pm = $month - 1; $py = $year;
        if ($pm < 1) { $pm = 12; $py--; }
        $nm = $month + 1; $ny = $year;
        if ($nm > 12) { $nm = 1; $ny++; }

        return [
            'month'      => $month,
            'year'       => $year,
            'month_name' => get_monthname($month),
            'prev'       => ['month' => $pm, 'year' => $py],
            'next'       => ['month' => $nm, 'year' => $ny],
        ];
    }
}

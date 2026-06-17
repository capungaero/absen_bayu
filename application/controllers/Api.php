<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * API JSON untuk PWA karyawan (stateless, token bearer).
 * Endpoint: POST api/login, GET api/profile, GET api/schedule, GET api/payroll.
 * Dipakai oleh aplikasi terpisah (tiffany.my.id/app). CSRF dikecualikan di config.
 */
class Api extends CI_Controller {

    private $user = null;

    public function __construct(){
        parent::__construct();
        $this->load->library('Api_token', null, 'apitoken');
        $this->load->model('leave_model', 'leave');
        $this->load->model('overtime_model', 'overtime');
        $this->output->set_header('Access-Control-Allow-Origin: *');
        $this->output->set_header('Access-Control-Allow-Headers: Authorization, Content-Type');
        $this->output->set_header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        if(strtolower($this->input->method()) === 'options'){ $this->_json(['status'=>true]); exit; }
    }

    private function _json($data, $code = 200){
        $this->output->set_status_header($code)
                     ->set_content_type('application/json')
                     ->set_output(json_encode($data));
    }

    private function _bearer(){
        $h = $this->input->get_request_header('Authorization');
        if($h && preg_match('/Bearer\s+(.+)/i', $h, $m)){ return trim($m[1]); }
        return $this->input->get('token') ?: null;
    }

    /** Pastikan request ber-token valid; isi $this->user atau keluar 401. */
    private function _auth(){
        $uid = $this->apitoken->verify($this->_bearer());
        if(!$uid){ $this->_json(['status'=>false,'message'=>'Sesi berakhir, silakan login ulang'], 401); return false; }
        $u = $this->db->select('users.*, position.branch_id, branch.branch_code, branch.branch_name, position.position_name, subdivision.subdivision_name')
            ->join('position','position.id=users.position_id','left')
            ->join('branch','branch.id=position.branch_id','left')
            ->join('subdivision','subdivision.id=users.subdivision_id','left')
            ->where('users.id', $uid)->where('users.active', 1)
            ->get('users')->row_array();
        if(!$u){ $this->_json(['status'=>false,'message'=>'Akun tidak aktif'], 401); return false; }
        $this->user = $u;
        return true;
    }

    private function _body(){
        $raw = $this->input->raw_input_stream;
        $j = $raw ? json_decode($raw, true) : null;
        return is_array($j) ? $j : ($this->input->post() ?: []);
    }

    // POST api/login {email, password}
    public function login(){
        $p = $this->_body();
        $email = isset($p['email']) ? trim($p['email']) : '';
        $password = isset($p['password']) ? $p['password'] : '';
        if($email === '' || $password === ''){
            $this->_json(['status'=>false,'message'=>'Email & password wajib diisi'], 400); return;
        }
        $ok = $this->ion_auth->login($email, $password, FALSE);
        if(!$ok){
            $this->_json(['status'=>false,'message'=>'Email atau password salah'], 401); return;
        }
        $u = $this->ion_auth->user()->row();
        $this->ion_auth->logout(); // stateless: tidak pakai session, hanya token
        $this->_json([
            'status' => true,
            'token'  => $this->apitoken->issue($u->id),
            'user'   => ['name'=>trim($u->first_name.' '.$u->last_name), 'code'=>$u->employee_code]
        ]);
    }

    // GET api/profile
    public function profile(){
        if(!$this->_auth()) return;
        $u = $this->user;
        $this->_json(['status'=>true, 'user'=>[
            'name'     => trim($u['first_name'].' '.$u['last_name']),
            'code'     => $u['employee_code'],
            'position' => $u['position_name'],
            'division' => $u['subdivision_name'],
            'branch'   => $u['branch_name'],
            'location' => $u['location'],
            'photo'    => !empty($u['photo']) && $u['photo'] !== 'default-photo.jpg' ? base_url('assets/images/users/'.$u['photo']) : null,
        ]]);
    }

    // GET api/schedule?month=&year=
    public function schedule(){
        if(!$this->_auth()) return;
        $month = str_pad((int)($this->input->get('month') ?: date('m')), 2, '0', STR_PAD_LEFT);
        $year  = (int)($this->input->get('year') ?: date('Y'));
        $range = getRangeWorkDate($month, $year);
        $list  = $range['list'];
        $from  = reset($list); $to = end($list);
        $today = date('Y-m-d');

        $rows = $this->db->select('users_shift_additional.additional_date d, users_shift_additional.additional_type t, shift.shift_code, shift.shift_name, shift.start_time, shift.end_time')
            ->join('shift','shift.id=users_shift_additional.shift_id','left')
            ->where('users_shift_additional.user_id', $this->user['id'])
            ->where('additional_date >=', $from)->where('additional_date <=', $to)
            ->where(latest_schedule_subquery(), null, false)
            ->get('users_shift_additional')->result_array();
        $smap = []; foreach($rows as $r){ $smap[$r['d']] = $r; }

        $pres = $this->db->select('flow_date, entry_time, out_time, entry_time_late, presence_type,
                subuh_time_in, subuh_time_out, subuh_time_late, dzuhur_time_in, dzuhur_time_out, dzuhur_time_late,
                ashar_time_in, ashar_time_out, ashar_time_late, maghrib_time_in, maghrib_time_out, maghrib_time_late,
                isha_time_in, isha_time_out, isha_time_late, friday_time_in, friday_time_out, friday_time_late')
            ->where('user_id', $this->user['id'])->where('flow_date >=', $from)->where('flow_date <=', $to)
            ->where('presence_status', 'approved')->get('presence')->result_array();
        $pmap = []; foreach($pres as $p){ $pmap[$p['flow_date']] = $p; }

        $days = [];
        foreach($list as $d){
            $s  = isset($smap[$d]) ? $smap[$d] : null;
            $pr = isset($pmap[$d]) ? $pmap[$d] : null;
            $is_nosched = $s && is_no_schedule_shift($s['shift_code']);
            $is_work = $s && $s['t'] === 'work' && !$is_nosched;
            $status = 'none';
            if($is_work){
                if($pr && ($pr['entry_time'] || $pr['out_time'])) $status = ((int)$pr['entry_time_late'] > 0) ? 'late' : 'present';
                else if($pr && in_array($pr['presence_type'], ['izin','cuti','sakit'])) $status = 'permit';
                else if($d < $today) $status = 'absent';
                else $status = 'scheduled';
            } else if($s){ $status = 'off'; }

            // Rekap sholat hari ini (urut: subuh, dzuhur/jumat, ashar, maghrib, isya)
            $friday = (get_dayname($d) === 'Jumat');
            $prayDefs = [['subuh','Subuh'], ($friday ? ['friday','Jumat'] : ['dzuhur','Dzuhur']), ['ashar','Ashar'], ['maghrib','Maghrib'], ['isha','Isya']];
            $prayItems = []; $prayCount = 0;
            foreach($prayDefs as $pd){
                $k = $pd[0];
                $pin  = $pr && !empty($pr[$k.'_time_in'])  ? substr($pr[$k.'_time_in'], 11, 5)  : null;
                $pout = $pr && !empty($pr[$k.'_time_out']) ? substr($pr[$k.'_time_out'], 11, 5) : null;
                if($pin) $prayCount++;
                $prayItems[] = ['label'=>$pd[1], 'in'=>$pin, 'out'=>$pout, 'late'=>$pr ? (int)$pr[$k.'_time_late'] : 0];
            }

            $days[] = [
                'date'   => $d,
                'day'    => get_dayname($d),
                'code'   => $is_nosched ? 'NO-SC' : ($is_work ? $s['shift_code'] : ($s ? 'OFF' : '-')),
                'name'   => $is_work ? $s['shift_name'] : ($is_nosched ? 'No Schedule' : ($s ? 'Libur' : 'Belum dijadwalkan')),
                'time'   => $is_work && $s['start_time'] ? substr($s['start_time'],0,5).' - '.substr($s['end_time'],0,5) : null,
                'status' => $status,
                'entry'  => $pr && $pr['entry_time'] ? substr($pr['entry_time'],11,5) : null,
                'out'    => $pr && $pr['out_time'] ? substr($pr['out_time'],11,5) : null,
                'pray'   => ['count'=>$prayCount, 'items'=>$prayItems],
            ];
        }
        $this->_json(['status'=>true, 'month'=>(int)$month, 'year'=>$year, 'month_name'=>get_monthname($month), 'from'=>$from, 'to'=>$to, 'days'=>$days]);
    }

    // GET api/payroll?year=
    public function payroll(){
        if(!$this->_auth()) return;
        $year = (int)($this->input->get('year') ?: date('Y'));
        $slips = $this->db->select('pd.*, p.month, p.year, p.is_final')
            ->from('payroll_detail pd')->join('payroll p','p.id=pd.payroll_id')
            ->where('pd.user_id', $this->user['id'])->where('p.year', $year)->where('p.is_final','1')
            ->order_by('p.month','DESC')->get()->result_array();

        $out = [];
        foreach($slips as $s){
            $potongan_alpha = (int)$s['salary_basic_out_alfa'] + (int)$s['salary_basic_out_off_work'];
            $potongan_lain  = (int)$s['salary_out_fine'] + (int)$s['salary_out_deduction'] + (int)$s['salary_out_health'] + (int)$s['salary_out_work'] + (int)$s['salary_out_together'] + (int)$s['salary_debt'];
            $bonus = (int)$s['salary_in_overtime'] + (int)$s['salary_in_insentive'];
            $out[] = [
                'month'      => (int)$s['month'],
                'month_name' => get_monthname($s['month']),
                'year'       => (int)$s['year'],
                'thp'        => (int)$s['salary_thp'],
                'total_bonus'    => $bonus,
                'total_potongan' => $potongan_alpha + $potongan_lain,
                'pendapatan' => [
                    ['label'=>'Gaji Pokok', 'value'=>(int)$s['salary_in_basic']],
                    ['label'=>'Lembur',     'value'=>(int)$s['salary_in_overtime']],
                    ['label'=>'Insentif / Bonus', 'value'=>(int)$s['salary_in_insentive']],
                ],
                'potongan' => [
                    ['label'=>'Potongan Alpha',     'value'=>(int)$s['salary_basic_out_alfa']],
                    ['label'=>'Potongan Tdk Masuk', 'value'=>(int)$s['salary_basic_out_off_work']],
                    ['label'=>'Denda',              'value'=>(int)$s['salary_out_fine']],
                    ['label'=>'Potongan Lain',      'value'=>(int)$s['salary_out_deduction']],
                    ['label'=>'BPJS Kesehatan',     'value'=>(int)$s['salary_out_health']],
                    ['label'=>'BPJS Ketenagakerjaan','value'=>(int)$s['salary_out_work']],
                    ['label'=>'Iuran Bersama',      'value'=>(int)$s['salary_out_together']],
                    ['label'=>'Kasbon / Hutang',    'value'=>(int)$s['salary_debt']],
                ],
                'kehadiran' => [
                    'hadir'      => (int)$s['presence_count'],
                    'telat'      => (int)$s['presence_count_on_late'],
                    'lembur_jam' => (float)$s['total_overtime_hour'],
                ],
            ];
        }
        $this->_json(['status'=>true, 'year'=>$year, 'slips'=>$out]);
    }

    // GET api/requests — riwayat izin & lembur karyawan
    public function requests(){
        if(!$this->_auth()) return;
        $uid = $this->user['id'];
        $leaves = $this->db->select('id, leave_type, leave_start, leave_end, leave_reason, leave_status, leave_proof, reject_reason, created_at')
            ->where('user_id', $uid)->order_by('created_at','DESC')->limit(40)->get('leave')->result_array();
        $ot = $this->db->select('id, overtime_date, overtime_hour, overtime_status, overtime_proof, reject_reason, created_at')
            ->where('user_id', $uid)->order_by('created_at','DESC')->limit(40)->get('overtime')->result_array();
        $L = array_map(function($r){ return [
            'id'=>(int)$r['id'], 'type'=>$r['leave_type'], 'start'=>$r['leave_start'], 'end'=>$r['leave_end'],
            'reason'=>$r['leave_reason'], 'status'=>$r['leave_status'], 'reject'=>$r['reject_reason'],
            'proof'=> $r['leave_proof'] ? base_url('assets/images/hr/leave/'.$r['leave_proof']) : null,
            'created'=>$r['created_at'] ]; }, $leaves);
        $O = array_map(function($r){ return [
            'id'=>(int)$r['id'], 'date'=>$r['overtime_date'], 'hour'=>(float)$r['overtime_hour'],
            'status'=>$r['overtime_status'], 'reject'=>$r['reject_reason'],
            'proof'=> $r['overtime_proof'] ? base_url('assets/images/hr/overtime/'.$r['overtime_proof']) : null,
            'created'=>$r['created_at'] ]; }, $ot);
        $this->_json(['status'=>true, 'leaves'=>$L, 'overtimes'=>$O]);
    }

    // POST api/submit_leave (multipart: leave_type, leave_start, leave_end, leave_reason, leave_proof[file])
    public function submit_leave(){
        if(!$this->_auth()) return;
        $user_id = $this->user['id'];
        $p = $this->input->post();
        $this->form_validation->set_data($p);
        $this->form_validation->set_rules('leave_start', 'Tanggal Mulai', 'required|regex_match[/^\d{4}-\d{2}-\d{2}$/]');
        $this->form_validation->set_rules('leave_end', 'Tanggal Selesai', 'required|regex_match[/^\d{4}-\d{2}-\d{2}$/]');
        $this->form_validation->set_rules('leave_type', 'Jenis Izin', 'required|in_list[izin,sakit,cuti]');
        $this->form_validation->set_rules('leave_reason', 'Alasan', 'required|min_length[3]');
        if($this->form_validation->run() != TRUE){ $this->_json(['status'=>false,'message'=>strip_tags(validation_errors())]); return; }

        if(strtotime($p['leave_start']) > strtotime($p['leave_end'])){ $this->_json(['status'=>false,'message'=>'Tanggal mulai harus sebelum/sama dengan tanggal selesai']); return; }
        $listDay = get_daterange_list($p['leave_start'], $p['leave_end']);

        $checkPresence = $this->db->where('flow_date >=', $p['leave_start'])->where('flow_date <=', $p['leave_end'])
            ->where('user_id', $user_id)->count_all_results('presence');
        $checkOff = $this->db->where('user_id', $user_id)->where('additional_date >=', $p['leave_start'])
            ->where('additional_date <=', $p['leave_end'])->where('additional_type','work')
            ->group_by('additional_date')->get('users_shift_additional')->num_rows();
        if(!($checkPresence == 0 && $checkOff == count($listDay))){
            $this->_json(['status'=>false,'message'=>'Rentang tanggal sudah ada presensi atau bukan hari jadwal kerja Anda. Pilih tanggal jadwal shift yang belum ada presensi.']); return;
        }
        $overlap = $this->db->group_start()->group_start()->where('leave_start >=',$p['leave_start'])->where('leave_start <=',$p['leave_end'])->group_end()
            ->or_group_start()->where('leave_end >=',$p['leave_start'])->where('leave_end <=',$p['leave_end'])->group_end()->group_end()
            ->where('user_id',$user_id)->where('leave_status','pending')->count_all_results('leave');
        if($overlap > 0){ $this->_json(['status'=>false,'message'=>'Rentang tanggal sedang dalam proses pengajuan. Pilih tanggal lain.']); return; }

        $proof = '';
        if(!empty($_FILES['leave_proof']['name'])){
            $config = ['upload_path'=>'./assets/images/hr/leave/','allowed_types'=>'png|jpeg|jpg',
                'file_name'=>'leave_'.$user_id.'_'.generateRandom(5).'_'.time(),'max_size'=>10240,'max_width'=>8000,'max_height'=>8000];
            $this->load->library('upload', $config);
            if(!$this->upload->do_upload('leave_proof')){ $this->_json(['status'=>false,'message'=>strip_tags($this->upload->display_errors())]); return; }
            $upl = $this->upload->data();
            $this->load->library('image_lib', ['image_library'=>'gd2','source_image'=>$upl['full_path'],'quality'=>'80%','maintain_ratio'=>TRUE,'width'=>800]);
            $this->image_lib->resize();
            $proof = $upl['file_name'];
        } else if($p['leave_type'] === 'sakit'){
            $this->_json(['status'=>false,'message'=>'Surat keterangan sakit (foto) wajib diunggah.']); return;
        }

        $totalDay = 0;
        foreach($listDay as $d){ $totalDay += in_array(get_dayname($d), ['Sabtu','Minggu']) ? 2 : 1; }
        $ok = $this->leave->insert([
            'user_id'=>$user_id, 'leave_start'=>$p['leave_start'], 'leave_end'=>$p['leave_end'],
            'leave_range'=>diffInDays($p['leave_start'],$p['leave_end'])+1, 'leave_proof'=>$proof,
            'leave_type'=>$p['leave_type'], 'leave_reason'=>$p['leave_reason'],
            'default_potongan'=>GetPotonganIzin($this->user['status_work']), 'request_potongan'=>null,
            'jumlah_hari_potongan'=>$totalDay, 'leave_status'=>'pending', 'created_at'=>date('Y-m-d H:i:s'),
        ]);
        $this->_json($ok ? ['status'=>true,'message'=>'Pengajuan izin berhasil dikirim'] : ['status'=>false,'message'=>'Gagal menyimpan pengajuan']);
    }

    // POST api/submit_overtime (multipart: overtime_date, overtime_hour, overtime_proof[file])
    public function submit_overtime(){
        if(!$this->_auth()) return;
        $user_id = $this->user['id'];
        $p = $this->input->post();
        $this->form_validation->set_data($p);
        $this->form_validation->set_rules('overtime_date', 'Tanggal Lembur', 'required|regex_match[/^\d{4}-\d{2}-\d{2}$/]');
        $this->form_validation->set_rules('overtime_hour', 'Lama Lembur', 'required|numeric|greater_than[0]');
        if($this->form_validation->run() != TRUE){ $this->_json(['status'=>false,'message'=>strip_tags(validation_errors())]); return; }

        $date = date('Y-m-d', strtotime($p['overtime_date']));
        $dup = $this->overtime->get_detail(['user_id'=>$user_id, 'overtime_date'=>$date]);
        if($dup->num_rows() > 0 && in_array($dup->row_array()['overtime_status'], ['approve','pending'])){
            $this->_json(['status'=>false,'message'=>'Lembur untuk tanggal ini sudah pernah diajukan.']); return;
        }
        if(empty($_FILES['overtime_proof']['name'])){ $this->_json(['status'=>false,'message'=>'Foto bukti lembur wajib diunggah.']); return; }

        $config = ['upload_path'=>'./assets/images/hr/overtime/','allowed_types'=>'png|jpeg|jpg',
            'file_name'=>'overtime_'.$user_id.'_'.generateRandom(5).'_'.time(),'max_size'=>10240,'max_width'=>10000,'max_height'=>10000];
        $this->load->library('upload', $config);
        if(!$this->upload->do_upload('overtime_proof')){ $this->_json(['status'=>false,'message'=>strip_tags($this->upload->display_errors())]); return; }
        $upl = $this->upload->data();
        $this->load->library('image_lib', ['image_library'=>'gd2','source_image'=>$upl['full_path'],'quality'=>'80%','maintain_ratio'=>TRUE,'width'=>800]);
        $this->image_lib->resize();

        $ok = $this->overtime->insert([
            'user_id'=>$user_id, 'overtime_hour'=>$p['overtime_hour'], 'overtime_date'=>$date,
            'overtime_proof'=>$upl['file_name'], 'overtime_status'=>'pending', 'created_at'=>date('Y-m-d H:i:s'),
        ]);
        $this->_json($ok ? ['status'=>true,'message'=>'Pengajuan lembur berhasil dikirim'] : ['status'=>false,'message'=>'Gagal menyimpan pengajuan']);
    }
}

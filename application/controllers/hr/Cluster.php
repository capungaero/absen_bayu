<?php 

class Cluster extends CI_Controller{

	function __construct(){
		parent::__construct();	
		$this->load->model('shift_model', 'shift');
		$this->load->model('branch_model', 'branch');

		if(!$this->ion_auth->logged_in()){
			redirect('');
		}

		$this->role     = $this->ion_auth->get_users_groups()->row()->name;
		$this->userdata = $this->ion_auth->user()->row();
	}

	public function index(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr'])){
			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
				$data['branch'] = $this->branch->get_data(['branch_name' => 'ASC'])->result_array();
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$find = [
				'branch_id' => $branch_id
			];
			$data['list']  = $this->shift->get_cluster_dataTable($find);
			$data['shift'] = $this->shift->get_active($find)->result_array();
			$data['branch_detail'] = $this->branch->get_detail('id', $branch_id)->row_array();
			$data['branch_id']     = $branch_id;
			$this->template->load('layout/admin','hr/schedule/cluster', $data);
		}else{
			redirect();
		}
	}

	public function insert(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){

			$p 		= $this->input->post();
			$shift  = $p['shift']; unset($p['shift']);

			$unique = [
				'cluster_name' => 'Nama cluster',
				'cluster_code' => 'Kode cluster'
			];
			$status = true; $title = '';
			$branch_id = $this->role == 'admin' ? $p['branch_id'] : $this->userdata->branch_id;

			foreach ($unique as $row => $val){
				$find = [];
				$find[$row] = $p[$row];
				$find['branch_id'] = $branch_id;

				$exist = $this->shift->get_cluster_detail($find);
				if($exist->num_rows() > 0){
					$title  = $val;
					$status = false;
					break;
				}
			}

			if($status){
				$this->db->trans_begin();

				$p['branch_id']  = $branch_id;
				$p['created_at'] = date('Y-m-d H:i:s');
				$this->shift->insert_cluster($p);

				$cluster = $this->shift->get_cluster_detail($p)->row_array();
				if(!empty($shift)){
					$rotation = [];
					$n = 0;
					foreach ($shift as $row) { $n++;
						$rotation[] = [
							'shift_cluster_id' => $cluster['id'],
							'shift_id'		   => $row['shift_id'] != 'free' ? $row['shift_id'] : null,
							'num'			   => $n,
							'shift_type'	   => $row['shift_id'] == 'free' ? 'free' : 'work'
						];
					}
					$this->shift->insert_rotation($rotation);

					if($this->db->trans_status()){
						$this->db->trans_commit();
						$res = [
							'status'  => true,
							'message' => 'Data berhasil dimasukkan'
						];

					}else{
						$this->db->trans_rollback();
						$res = [
							'status'  => false,
							'message' => 'Terjadi kesalahan, coba lagi nanti'
						];
					}

				}else{
					$res = [
						'status'  => false,
						'message' => 'Harap isi urutan jadwal'
					];
				}

			}else{
				$res = [
					'status'  => false,
					'message' => $title. ' sudah ada'
				];
			}

			echo json_encode($res);

		}else{
			show_404();
		}
	}

	public function update(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){

			$p     = $this->input->post();
			$shift = $p['shift']; unset($p['shift']);
			$id    = $p['id_cluster']; unset($p['id_cluster']);

			$find['branch_id'] = $this->role == 'admin' ? $p['branch_id'] : $this->userdata->branch_id;
			$find['shift_cluster.id'] = $id;

			$cek = $this->shift->get_cluster_detail($find);

			if($cek->num_rows() > 0){

				$unique = [
					'cluster_name' => 'Nama cluster',
					'cluster_code' => 'Kode cluster'
				];
				$status = true; $title = '';

				$find = [
					'branch_id'	=> $this->userdata->branch_id,
					'id'		=> $id
				];
				$cek = $this->shift->get_cluster_detail($find)->row_array();
				foreach ($unique as $row => $val) {
					$find = [];
					$find[$row] = $p[$row];
					$find['branch_id'] = $this->userdata->branch_id;

					$exist = $this->shift->get_cluster_detail($find);
					if($exist->num_rows() > 0){
						$exist = $exist->row_array();

						if($cek[$row] != $exist[$row]){
							$title  = $val;
							$status = false;
							break;
						}
					}
				}

				if($status){
					$this->db->trans_begin();

					$p['updated_at'] = date('Y-m-d H:i:s');
					$this->shift->update_cluster($p, $id);
					$this->shift->delete_rotation_by_cluster_id($id);

					$rotation = [];
					$n = 0;
					foreach ($shift as $row) { $n++;
						$rotation[] = [
							'shift_cluster_id' => $id,
							'shift_id'		   => $row['shift_id'] != 'free' ? $row['shift_id'] : null,
							'num'			   => $n,
							'shift_type'	   => $row['shift_id'] == 'free' ? 'free' : 'work'
						];
					}
					$this->shift->insert_rotation($rotation);

					if($this->db->trans_status()){
						$this->db->trans_commit();
						$res = [
							'status'  => true,
							'message' => 'Data berhasil dimasukkan'
						];

					}else{
						$this->db->trans_rollback();
						$res = [
							'status'  => false,
							'message' => 'Data gagal dimasukkan'
						];
					}

				}else{
					$res = [
						'status'  => false,
						'message' => $title. ' sudah ada'
					];
				}

				echo json_encode($res);

			}else{
				show_404();
			}

		}else{
			show_404();
		}
	}

	public function delete(){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){
			$find = [
				'branch_id' => $this->role == 'admin' ? $this->input->post('branch_id') : $this->userdata->branch_id,
				'id'		=> $this->input->post('id')
			];
			$data = $this->shift->get_cluster_detail($find);

			if($data->num_rows() > 0){
				if($this->shift->delete_cluster($this->input->post('id'))){
					$res = [
						'status'  => true,
						'message' => 'Data berhasil dihapus'
					];
				}else{
					$res = [
						'status'  => false,
						'message' => 'Data gagal dihapus'
					];
				}

				echo json_encode($res);

			}else{
				show_404();
			}
			
		}else{
			show_404();
		}
	}

	public function get_rotation($id){
		if(in_array($this->role, ['admin', 'admin-branch', 'hr']) && $this->input->is_ajax_request()){
			$find['id'] = $id;

			if($this->role == 'admin'){
				$branch_id = $this->input->get('branch_id') ? $this->input->get('branch_id') : $this->userdata->branch_id;
			}else{
				$branch_id = $this->userdata->branch_id;
			}

			$find['branch_id'] = $branch_id;
			$data = $this->shift->get_cluster_detail($find);

			if($data->num_rows() > 0){

				$rotation = $this->shift->get_rotation_by_cluster_id($id);
				$shift    = $this->shift->get_active('branch_id', $branch_id)->result_array();
				
				if(!empty($rotation)){
					$html = ''; $n = 0;
					foreach ($rotation as $rot){
						$free = $rot['shift_type'] == 'free' ? 'selected="selected"' : '';
						$html .= '<div data-repeater-item class="row"><div  class="col-md-8 mb-3"><label class="form-label" for="name">Shift</label><select class="form-control" name="shift['.$n++.'][shift_id]" required=""><option value="">Pilih</option><option value="free" '.$free.'>LIBUR</option>';

                        foreach ($shift as $row){
                        	$selected = ($row['id'] == $rot['shift_id']) ? "selected" : '';

                            $html .='<option '.$selected.' data-start-time="'.date('H:i', strtotime($row['start_time'])).'data-end-time="'.date('H:i', strtotime($row['end_time'])).'
                                            value="'.$row['id'].'">'.$row['shift_code']." / ".$row['shift_name'].'</option>';
                        }
                        
                        $html .= '</select></div><div class="col-md-1 mt-2 align-self-center d-grid"><button data-repeater-delete type="button" class="btn btn-danger btn-sm"/><i class="dripicons-trash"></i></button></div></div>';
					}

					$res = [
						'status' => true,
						'data'   => $html
					];

				}else{
					$res = [
						'status'  => false,
						'message' => 'Cluster tidak mempunyai rotasi'
					];
				}

				echo json_encode($res);

			}else{
				show_404();
			}
			
		}else{
			show_404();
		}
	}
}

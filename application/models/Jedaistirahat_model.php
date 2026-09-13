<?php

Class Jedaistirahat_model extends CI_Model{

	/**
	 * Ambil kejadian "istirahat berdekatan dengan sholat" pada rentang tanggal.
	 * Jeda dibatasi 0-60 menit di level query; pemilihan ambang batas yang lebih
	 * sempit (mis. <=30 menit) dilakukan di sisi klien (JS) supaya slider instan
	 * tanpa round-trip ke server.
	 *
	 * @param string $from      Y-m-d
	 * @param string $to        Y-m-d
	 * @param int|null $branch_id  null = semua cabang
	 * @return array
	 */
	public function get_events($from, $to, $branch_id = null){
		$sql = "
			SELECT
			  u.first_name AS nama,
			  b.branch_name AS cabang,
			  t.flow_date AS tanggal,
			  t.pola AS pola,
			  t.sholat_name AS sholat,
			  TIME_FORMAT(t.rest_in, '%H:%i:%s') AS istirahat_mulai,
			  TIME_FORMAT(t.rest_out, '%H:%i:%s') AS istirahat_selesai,
			  TIME_FORMAT(t.sholat_in, '%H:%i:%s') AS sholat_mulai,
			  TIME_FORMAT(t.sholat_out, '%H:%i:%s') AS sholat_selesai,
			  t.selisih_menit AS selisih
			FROM (
			  SELECT p.user_id, p.flow_date, 'rest_ke_sholat' AS pola, s.sholat_name,
			         p.rest_time_in AS rest_in, p.rest_time_out AS rest_out,
			         s.sholat_in AS sholat_in, s.sholat_out AS sholat_out,
			         TIMESTAMPDIFF(MINUTE, p.rest_time_out, s.sholat_in) AS selisih_menit
			  FROM presence p
			  JOIN (
			    SELECT user_id, flow_date, 'subuh' sholat_name, subuh_time_in sholat_in, subuh_time_out sholat_out FROM presence WHERE subuh_time_in IS NOT NULL
			    UNION ALL SELECT user_id, flow_date, 'dzuhur', dzuhur_time_in, dzuhur_time_out FROM presence WHERE dzuhur_time_in IS NOT NULL
			    UNION ALL SELECT user_id, flow_date, 'ashar', ashar_time_in, ashar_time_out FROM presence WHERE ashar_time_in IS NOT NULL
			    UNION ALL SELECT user_id, flow_date, 'maghrib', maghrib_time_in, maghrib_time_out FROM presence WHERE maghrib_time_in IS NOT NULL
			    UNION ALL SELECT user_id, flow_date, 'isha', isha_time_in, isha_time_out FROM presence WHERE isha_time_in IS NOT NULL
			    UNION ALL SELECT user_id, flow_date, 'friday', friday_time_in, friday_time_out FROM presence WHERE friday_time_in IS NOT NULL
			  ) s ON s.user_id = p.user_id AND s.flow_date = p.flow_date
			  WHERE p.rest_time_out IS NOT NULL
			    AND p.flow_date BETWEEN ? AND ?
			    AND NOT (s.sholat_in BETWEEN p.rest_time_in AND p.rest_time_out)
			    AND TIMESTAMPDIFF(MINUTE, p.rest_time_out, s.sholat_in) BETWEEN 0 AND 60

			  UNION ALL

			  SELECT p.user_id, p.flow_date, 'sholat_ke_rest' AS pola, s.sholat_name,
			         p.rest_time_in AS rest_in, p.rest_time_out AS rest_out,
			         s.sholat_in AS sholat_in, s.sholat_out AS sholat_out,
			         TIMESTAMPDIFF(MINUTE, s.sholat_out, p.rest_time_in) AS selisih_menit
			  FROM presence p
			  JOIN (
			    SELECT user_id, flow_date, 'subuh' sholat_name, subuh_time_in sholat_in, subuh_time_out sholat_out FROM presence WHERE subuh_time_out IS NOT NULL
			    UNION ALL SELECT user_id, flow_date, 'dzuhur', dzuhur_time_in, dzuhur_time_out FROM presence WHERE dzuhur_time_out IS NOT NULL
			    UNION ALL SELECT user_id, flow_date, 'ashar', ashar_time_in, ashar_time_out FROM presence WHERE ashar_time_out IS NOT NULL
			    UNION ALL SELECT user_id, flow_date, 'maghrib', maghrib_time_in, maghrib_time_out FROM presence WHERE maghrib_time_out IS NOT NULL
			    UNION ALL SELECT user_id, flow_date, 'isha', isha_time_in, isha_time_out FROM presence WHERE isha_time_out IS NOT NULL
			    UNION ALL SELECT user_id, flow_date, 'friday', friday_time_in, friday_time_out FROM presence WHERE friday_time_out IS NOT NULL
			  ) s ON s.user_id = p.user_id AND s.flow_date = p.flow_date
			  WHERE p.rest_time_in IS NOT NULL
			    AND p.flow_date BETWEEN ? AND ?
			    AND NOT (s.sholat_out BETWEEN p.rest_time_in AND p.rest_time_out)
			    AND TIMESTAMPDIFF(MINUTE, s.sholat_out, p.rest_time_in) BETWEEN 0 AND 60
			) t
			JOIN users u ON u.id = t.user_id
			LEFT JOIN position ps ON ps.id = u.position_id
			LEFT JOIN branch b ON b.id = ps.branch_id
		";

		$binds = [$from, $to, $from, $to];

		if(!empty($branch_id)){
			$sql .= " WHERE b.id = ? ";
			$binds[] = (int)$branch_id;
		}

		$sql .= " ORDER BY t.flow_date, nama ";

		$query = $this->db->query($sql, $binds);
		return $query->result_array();
	}

}

<?php defined('BASEPATH') OR exit('No direct script access allowed');

  /**
  * CIgniter DataTables
  * CodeIgniter library for Datatables server-side processing / AJAX, easy to use :3
  *
  * @package    CodeIgniter
  * @subpackage libraries
  * @version    1.5
  *
  * @author     Izal Fathoni (izal.fat23@gmail.com)
  * @link 		https://github.com/nacasha/CIgniter-Datatables
  */
class DatatablesBuilder
{

    private $CI;
    private $limits;
    private $searchable 	= array();
    private $style 			= '';
	private $connection 	= 'default';

	private $dt_options		= array(
		'searchDelay' 	=> '1000',
		'autoWidth' 	=> 'false'
	);
	private $ax_options 	= '';

    /**
     * Load the necessary library from codeigniter and caching the query
     * We use Codeigniter Active Record to generate query
     */
    public function __construct()
    {
        $this->CI =& get_instance();

        $this->_db = $this->CI->load->database($this->connection, TRUE);
        $this->CI->load->helper('url');
        $this->CI->load->library('table');

        $this->_db->start_cache();
    }

    public function __destruct()
    {
        $this->_db->stop_cache();
        $this->_db->flush_cache();
    }

    /**
     * Select column want to fetch from database
     *
     * @param  string
     * @return object
     */
    public function select($columns)
    {
        $this->_db->select($columns);

        $this->searchable = $columns;
        return $this;
    }

    public function from($table)
    {
        $this->_db->from($table);

        $this->table = $table;
        return $this->_db;
    }
	
    public function where($data,$table)
    {
        $this->_db->where($data);

        $this->table = $table;
        return $this->_db;
    }
	
    public function like($data,$table)
    {
        $this->_db->like($data);

        $this->table = $table;
        return $this->_db;
    }

    public function style($data)
    {
        foreach ($data as $option => $value) {
            $this->style .= "$option=\"$value\"";
        }

        return $this;
    }

    /**
     * Set heading for the table
     *
     * @param  string $label    heading label
     * @param  string $source   column names
     * @param  method $function formatting the output
     * @return object
     */
    public function column($label, $source, $function = null, $adt = null)
    {
        $this->table_heading[] 		= $label;
        $this->columns[] 			= array($label, $source, $function, $adt);

        return $this;
    }

    /**
     * Initialize Datatables
     */
    public function init($dt_name)
    {
		if (isset($_REQUEST['dt_name'])) {
			if ($_REQUEST['dt_name'] == $dt_name) {
				if(isset($_REQUEST['draw']) && isset($_REQUEST['length']) && isset($_REQUEST['start']))
				{
					$this->json();
					exit;
				}
			}
        }
    }

    /**
     * Set searchable columns from table
     *
     * @param  string $data columns name
     * @return object
     */
    public function searchable($data)
    {
        $this->searchable = $data;
        return $this;
    }

    /**
     *	Add options to datatables jquery
     *
     * @param array / string 	$option options name
     * @param string 			$value  value
     */
    public function set_options($option, $value = null)
    {
		if ($option == 'ajax.data') {
			$this->ax_options .= $value;
		} else {
			if(is_array($option)) {
				foreach ($option as $opt => $value){
					$this->dt_options[$opt] = $value;
				}
			} else {
				$this->dt_options[$option] = $value;
			}
		}

        return $this;
	}

	/**
     * Generate the datatables table (lol)
     *
     * @return html table
     */
    public function generate($id)
    {
		$this->CI->table->set_template(array(
            'table_open' => "<table id=\"$id\" $this->style>"
        ));
        $this->CI->table->set_heading($this->table_heading);

        echo $this->CI->table->generate();
    }

    /**
     * Jquery for datatables
     *
     * @return javascript
     */
    public function jquery($id)
    {

        $CI =& get_instance();

		$dt_options	= '';
		$ax_options = $this->ax_options;

		foreach ($this->dt_options as $opt => $value){
      if($opt != 'limit'){
        $dt_options .= "$opt: $value, \n";
      }
		}

    $param = !$_SERVER['QUERY_STRING'] == '' ? '?'.$_SERVER['QUERY_STRING'] : '';

		$output = "
        <script type=\"text/javascript\" defer=\"defer\">
            function createDatatable() {
				erTable_{$id} = $(\"#{$id}\").DataTable({
                    processing: true,
                    serverSide: true,
                    bSort : false,
                    aaSorting: [],
                    {$dt_options}
                    ajax: {
                        url: \"". site_url(uri_string().$param) ."\",
						type: \"POST\",
                        data: function (d, dt) {
							d.dt_name = \"{$id}\",
                            d.myToken = \"".$CI->security->get_csrf_hash()."\"

							{$ax_options}
						}
					}
                });
            };

            createDatatable();
        </script>";

        echo $output;
    }

    /**
     * Generate JSON for datatables
     *
     * @return json
     */
    public function json()
    {
        $draw		= $_REQUEST['draw'];
        $length		= $_REQUEST['length'];
        $start		= $_REQUEST['start'];

        if(isset($_REQUEST['order'])){
            $order_by   = $_REQUEST['order'][0]['column'];
            $order_dir  = $_REQUEST['order'][0]['dir'];
        }
       
        $search		= $_REQUEST['search']["value"];

        $output['data'] 	= array();

        if($this->searchable == '*'){
            $field = $this->_db->list_fields($this->table);
            $this->searchable = implode(',', $field);
		    }

        $pattern = "/[,]+(?![^\(]*\))/";
        $column = preg_split($pattern, $this->searchable);
        $get = $column;
		    $this->searchable = array();

        foreach($column as $key => $col){
          if($col != ''){
            $col = strtolower($col);
            $col = str_replace('%d %m %y %h:%i', '%d %M %Y %H:%i', $col);
            $col = strstr($col, ' as ', true) ?: $col;
            $this->searchable[] = $col;
          } 
		}

		if($search != "") {
      $this->_db->group_start();
			for($i=0; $i< count($this->searchable);$i++){

        if(!strpos($this->searchable[$i], 'max') && !strpos($this->searchable[$i], 'min')){
          if($i==0) $this->_db->like($this->searchable[$i], $search);
          else $this->_db->or_like($this->searchable[$i], $search);
        }
			}

      $this->_db->group_end();
		}

        /** ---------------------------------------------------------------------- */
        /** Count records in database */
        /** ---------------------------------------------------------------------- */

        $total = $this->_db->count_all_results();

        $output['query_count'] 	= $this->_db->last_query();
        $output['recordsTotal'] = $output['recordsFiltered'] = $total;

        /** ---------------------------------------------------------------------- */
        /** Generate JSON */
		/** ---------------------------------------------------------------------- */

		if ($length != -1){
      if(!array_key_exists('limit', $this->dt_options)){
        $this->_db->limit($length, $start);
      }else{
        $this->_db->limit($this->dt_options['limit']);
      }

		}else{
      if(array_key_exists('limit', $this->dt_options)){
        $this->_db->limit($this->dt_options['limit']);
      }
    }

        if(isset($_REQUEST['order'])){

            if(substr($this->columns[$order_by][1], 0, 6) == 'num_dt'){
                $col = str_replace('num_dt ', '', $this->columns[$order_by][1]);
                $this->_db->order_by($col, $order_dir);
            }else{
                $this->_db->order_by($this->columns[$order_by][1], $order_dir);
            }

        }else{
            if(substr($this->columns[0][1], 0, 6) == 'num_dt'){
                $col = str_replace('num_dt ', '', $this->columns[0][1]);
                $this->_db->order_by($col, 'DESC');
            }
        }

        $result 			    = $this->_db->get()->result_array();
        $output['query'] 	=  $this->_db->last_query();

        $num = $start;

        foreach ($result as $row){
            $arr = array();
            $num++;
            $arr[] = $num;
            
            foreach ($this->columns as $key => $column){

                if(substr($column[1], 0, 6) != 'num_dt'){
                    $row_output = $row[$column[1]];
                    if(isset($this->columns[$key][2])){

                        if(isset($this->columns[$key][3])){
                            $row['adt'] = $this->columns[$key][3];
                        }

                        $row_output = call_user_func_array($this->columns[$key][2], array($row_output, $row));
                        
                    }
                    $arr[] = $row_output;
                }
                
            }
            $output['data'][] = $arr;

        }

        $output['column'] = $get;

        echo json_encode($output);
    }

}

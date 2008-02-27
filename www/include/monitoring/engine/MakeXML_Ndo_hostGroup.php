<?php
/**
Centreon is developped with GPL Licence 2.0 :
http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
Developped by : Cedrick Facon

The Software is provided to you AS IS and WITH ALL FAULTS.
OREON makes no representation and gives no warranty whatsoever,
whether express or implied, and without limitation, with regard to the quality,
safety, contents, performance, merchantability, non-infringement or suitability for
any particular or intended purpose of the Software found on the OREON web site.
In no event will OREON be liable for any direct, indirect, punitive, special,
incidental or consequential damages however they may arise and even if OREON has
been previously advised of the possibility of such damages.

For information : contact@oreon-project.org
*/

	# if debug == 0 => Normal, debug == 1 => get use, debug == 2 => log in file (log.xml)
	$debugXML = 0;
	$buffer = '';
	$oreonPath = '/srv/oreon/';
	
	/* security check 1/2*/
	if($oreonPath == '@INSTALL_DIR_OREON@')
		get_error('please set your oreonPath');
	/* security end 1/2 */

	include_once($oreonPath . "etc/centreon.conf.php");
	include_once($oreonPath . "www/DBconnect.php");
	include_once($oreonPath . "www/DBndoConnect.php");
	include_once($oreonPath . "www/include/common/common-Func-ACL.php");
	include_once($oreonPath . "www/include/common/common-Func.php");

	$ndo_base_prefix = getNDOPrefix();
	
	/* security check 2/2*/
	if(isset($_GET["sid"]) && !check_injection($_GET["sid"])){

		$sid = $_GET["sid"];
		$sid = htmlentities($sid);
		$res =& $pearDB->query("SELECT * FROM session WHERE session_id = '".$sid."'");
		if($res->fetchInto($session)){
			;
		}else
			get_error('bad session id');
	}
	else
		get_error('need session identifiant !');
	/* security end 2/2 */

	/* requisit */
	if(isset($_GET["instance"]) && !check_injection($_GET["instance"])){
		$instance = htmlentities($_GET["instance"]);
	}else
		$instance = "ALL";
	if(isset($_GET["num"]) && !check_injection($_GET["num"])){
		$num = htmlentities($_GET["num"]);
	}else
		get_error('num unknown');
	if(isset($_GET["limit"]) && !check_injection($_GET["limit"])){
		$limit = htmlentities($_GET["limit"]);
	}else
		get_error('limit unknown');


	/* options */
	if(isset($_GET["search"]) && !check_injection($_GET["search"])){
		$search = htmlentities($_GET["search"]);
	}else
		$search = "";

	if(isset($_GET["sort_type"]) && !check_injection($_GET["sort_type"])){
		$sort_type = htmlentities($_GET["sort_type"]);
	}else
		$sort_type = "host_name";

	if(isset($_GET["order"]) && !check_injection($_GET["order"])){
		$order = htmlentities($_GET["order"]);
	}else
		$oreder = "ASC";

	if(isset($_GET["date_time_format_status"]) && !check_injection($_GET["date_time_format_status"])){
		$date_time_format_status = htmlentities($_GET["date_time_format_status"]);
	}else
		$date_time_format_status = "d/m/Y H:i:s";

	if(isset($_GET["o"]) && !check_injection($_GET["o"])){
		$o = htmlentities($_GET["o"]);
	}else
		$o = "h";
	if(isset($_GET["p"]) && !check_injection($_GET["p"])){
		$p = htmlentities($_GET["p"]);
	}else
		$p = "2";



	/* security end*/

	# class init
	class Duration
	{
		function toString ($duration, $periods = null)
	    {
	        if (!is_array($duration)) {
	            $duration = Duration::int2array($duration, $periods);
	        }
	        return Duration::array2string($duration);
	    }
	    function int2array ($seconds, $periods = null)
	    {
	        // Define time periods
	        if (!is_array($periods)) {
	            $periods = array (
	                    'y'	=> 31556926,
	                    'M' => 2629743,
	                    'w' => 604800,
	                    'd' => 86400,
	                    'h' => 3600,
	                    'm' => 60,
	                    's' => 1
	                    );
	        }
	        // Loop
	        $seconds = (int) $seconds;
	        foreach ($periods as $period => $value) {
	            $count = floor($seconds / $value);
	            if ($count == 0) {
	                continue;
	            }
	            $values[$period] = $count;
	            $seconds = $seconds % $value;
	        }
	        // Return
	        if (empty($values)) {
	            $values = null;
	        }
	        return $values;
	    }

	    function array2string ($duration)
	    {
	        if (!is_array($duration)) {
	            return false;
	        }
	        foreach ($duration as $key => $value) {
	            $segment = $value . '' . $key;
	            $array[] = $segment;
	        }
	        $str = implode(' ', $array);
	        return $str;
	    }
	}


	$DBRESULT_OPT =& $pearDB->query("SELECT color_ok,color_warning,color_critical,color_unknown,color_pending,color_up,color_down,color_unreachable FROM general_opt");
	if (PEAR::isError($DBRESULT_OPT))
		print "DB Error : ".$DBRESULT_OPT->getDebugInfo()."<br />";
	$DBRESULT_OPT->fetchInto($general_opt);


	function get_hosts_status($host_group_id, $status){
		global $pearDBndo, $ndo_base_prefix;
		global $general_opt;

		$rq = "SELECT count( nhs.host_object_id ) AS nb".
			" FROM " .$ndo_base_prefix."hoststatus nhs".
			" WHERE nhs.current_state = '".$status."'".
			" AND nhs.host_object_id".
			" IN (".
			" SELECT nhgm.host_object_id".
			" FROM " .$ndo_base_prefix."hostgroup_members nhgm".
			" WHERE nhgm.hostgroup_id =".$host_group_id.
			")";

		$DBRESULT =& $pearDBndo->query($rq);
		if (PEAR::isError($DBRESULT))
			print "DB Error : ".$DBRESULT->getDebugInfo()."<br />";
		$DBRESULT->fetchInto($tab);

		return($tab["nb"]);
	}

	function get_services_status($host_group_id, $status){
		global $pearDBndo, $ndo_base_prefix;
		global $general_opt, $instance,$lcaSTR, $is_admin;


		$rq = "SELECT count( nss.service_object_id ) AS nb".
		" FROM " .$ndo_base_prefix."servicestatus nss".
		" WHERE nss.current_state = '".$status."'".
		" AND nss.service_object_id".
		" IN (".
		" SELECT nno.object_id".
		" FROM " .$ndo_base_prefix."objects nno".
		" WHERE nno.objecttype_id =2";

		if(!$is_admin)
			$rq .= " AND nno.name1 IN (".$lcaSTR." )";



	if($instance != "ALL")
		$rq .= " AND nno.instance_id = ".$instance;

		$rq .= " AND nno.name1".
		" IN (".

		" SELECT no.name1".
		" FROM " .$ndo_base_prefix."objects no, " .$ndo_base_prefix."hostgroup_members nhgm".
		" WHERE nhgm.hostgroup_id =".$host_group_id.
		" AND no.object_id = nhgm.host_object_id".
		" )".
		" )";

		$DBRESULT =& $pearDBndo->query($rq);
		if (PEAR::isError($DBRESULT))
			print "DB Error : ".$DBRESULT->getDebugInfo()."<br />";
		$DBRESULT->fetchInto($tab);

		return($tab["nb"]);
	}

	/* LCA */
	// check is admin
	$res1 =& $pearDB->query("SELECT user_id FROM session WHERE session_id = '".$sid."'");
	$res1->fetchInto($user);
	$user_id = $user["user_id"];
	$res2 =& $pearDB->query("SELECT contact_admin FROM contact WHERE contact_id = '".$user_id."'");
	$res2->fetchInto($admin);
	$is_admin = 0;
	$is_admin = $admin["contact_admin"];

	// if is admin -> lca
	if(!$is_admin){
		$_POST["sid"] = $sid;
		$lca =  getLCAHostByName($pearDB);
		$lcaSTR = getLCAHostStr($lca["LcaHost"]);
		$lcaSTR_HG = getLCAHostStr($lca["LcaHostGroup"]);
	}


	$service = array();
	$host_status = array();
	$service_status = array();
	$host_services = array();
	$metaService_status = array();
	$tab_host_service = array();


	$tab_color_service = array();
	$tab_color_service[0] = $general_opt["color_ok"];
	$tab_color_service[1] = $general_opt["color_warning"];
	$tab_color_service[2] = $general_opt["color_critical"];
	$tab_color_service[3] = $general_opt["color_unknown"];
	$tab_color_service[4] = $general_opt["color_pending"];

	$tab_color_host = array();
	$tab_color_host[0] = $general_opt["color_up"];
	$tab_color_host[1] = $general_opt["color_down"];
	$tab_color_host[2] = $general_opt["color_unreachable"];

	$tab_status_svc = array("0" => "OK", "1" => "WARNING", "2" => "CRITICAL", "3" => "UNKNOWN", "4" => "PENDING");
	$tab_status_host = array("0" => "UP", "1" => "DOWN", "2" => "UNREACHABLE");


	/* Get Host status */
	$rq1 = "SELECT " .
			" no.name1 as hostgroup_name," .
			" hg.hostgroup_id" .
			" FROM " .$ndo_base_prefix."hostgroups hg, ". //$ndo_base_prefix."hostgroup_members hgm, ".
			$ndo_base_prefix."objects no ".
			" WHERE no.object_id = hg.hostgroup_object_id";

	if($search != ""){
		$rq1 .= " AND no.name1 like '%" . $search . "%' ";
	}

	if($instance != "ALL")
		$rq1 .= " AND no.instance_id = ".$instance;
		
	$rq1 .= " group by no.name1 ";
	$rq1 .= " order by no.name1 ". $order;


	$rq_pagination = $rq1;

	/* Get Pagination Rows */
	$DBRESULT_PAGINATION =& $pearDBndo->query($rq_pagination);
	if (PEAR::isError($DBRESULT_PAGINATION))
		print "DB Error : ".$DBRESULT_PAGINATION->getDebugInfo()."<br />";
	$numRows = $DBRESULT_PAGINATION->numRows();
	/* End Pagination Rows */


	$rq1 .= " LIMIT ".($num * $limit).",".$limit;

	$buffer .= '<reponse>';
	$buffer .= '<i>';
	$buffer .= '<numrows>'.$numRows.'</numrows>';
	$buffer .= '<num>'.$num.'</num>';
	$buffer .= '<limit>'.$limit.'</limit>';
	$buffer .= '<p>'.$p.'</p>';
	$buffer .= '</i>';
	$DBRESULT_NDO1 =& $pearDBndo->query($rq1);
	if (PEAR::isError($DBRESULT_NDO1))
		print "DB Error : ".$DBRESULT_NDO1->getDebugInfo()."<br />";
	$class = "list_one";
	$ct = 0;
	$flag = 0;
	while($DBRESULT_NDO1->fetchInto($ndo))
	{
		$nb_host_up = 0 + get_hosts_status($ndo["hostgroup_id"], 0);
		$nb_host_down = 0 + get_hosts_status($ndo["hostgroup_id"], 1);
		$nb_host_unreachable = 0 + get_hosts_status($ndo["hostgroup_id"], 2);

		$nb_service_k = 0 + get_services_status($ndo["hostgroup_id"], 0);
		$nb_service_w = 0 + get_services_status($ndo["hostgroup_id"], 1);
		$nb_service_c = 0 + get_services_status($ndo["hostgroup_id"], 2);
		$nb_service_u = 0 + get_services_status($ndo["hostgroup_id"], 3);
		$nb_service_p = 0 + get_services_status($ndo["hostgroup_id"], 4);

//		$color_host = $tab_color_host[$ndo["current_state"]]; //"#FF0000";
		$passive = 0;
		$active = 1;
		$last_check = " ";
		$duration = " ";
		/*
		if($ndo["last_state_change"] > 0)
			$duration = Duration::toString(time() - $ndo["last_state_change"]);
*/
		if($class == "list_one")
			$class = "list_two";
		else
			$class = "list_one";
//		$host_status[$ndo["host_name"]] = $ndo;

				$buffer .= '<l class="'.$class.'">';
		$buffer .= '<o>'. $ct++ . '</o>';
		$buffer .= '<hn><![CDATA['. $ndo["hostgroup_name"]  . ']]></hn>';
		$buffer .= '<hu>'. $nb_host_up  . '</hu>';
		$buffer .= '<huc>'. $tab_color_host[0]  . '</huc>';
		$buffer .= '<hd>'. $nb_host_down  . '</hd>';
		$buffer .= '<hdc>'. $tab_color_host[1]  . '</hdc>';
		$buffer .= '<hur>'. $nb_host_unreachable  . '</hur>';
		$buffer .= '<hurc>'. $tab_color_host[2]  . '</hurc>';

		$buffer .= '<sk>'. $nb_service_k  . '</sk>';
		$buffer .= '<skc>'. $tab_color_service[0]  . '</skc>';
		$buffer .= '<sw>'. $nb_service_w  . '</sw>';
		$buffer .= '<swc>'. $tab_color_service[1]  . '</swc>';
		$buffer .= '<sc>'. $nb_service_c  . '</sc>';
		$buffer .= '<scc>'. $tab_color_service[2]  . '</scc>';
		$buffer .= '<su>'. $nb_service_u  . '</su>';
		$buffer .= '<suc>'. $tab_color_service[3]  . '</suc>';
		$buffer .= '<sp>'. $nb_service_p  . '</sp>';
		$buffer .= '<spc>'. $tab_color_service[4]  . '</spc>';
		$buffer .= '</l>';
	}
	/* end */

	if(!$ct){
		$buffer .= '<infos>';
		$buffer .= 'none';
		$buffer .= '</infos>';
	}

	$buffer .= '</reponse>';
	header('Content-Type: text/xml');
	echo $buffer;

?>

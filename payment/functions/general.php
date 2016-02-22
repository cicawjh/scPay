<?php
	function gf_return_trans($string) {
		if (is_numeric($string['y_mid']) && $string['y_verify_result'] == 0) {
			
			$mid = $string['y_mid'];
			$trans_fee_rate = gf_get_transfee($mid);
			
			$trans_amount = $string['x_amount'];
			$trans_fee = -(($trans_amount * $trans_fee_rate[0])/100 + $trans_fee_rate[1]);
			$trans_net = $trans_amount + $trans_fee;

			$agtransid = 'SELF_'.date('YmdHis').mt_rand(100,999);

			gf_db_query("insert into ".TABLE_PTRANS." set mid=".$mid.",transtypeid=1,transtatusid=1,is_calculate=0,trans_date='".date('Y-m-d H:i:s')."',trans_amount=".$trans_amount.",trans_fee=".$trans_fee.",trans_net = ".$trans_net.",trans_invoice='".$string['x_invoice_num']."',trans_ccowner='".gf_db_input($string['x_first_name'].' '.$string['x_last_name'])."', trans_ccnum='".$string['x_card_num']."',agtransid='".$agtransid."',gatewayid='".$string['y_gatewayid']."'");

			gf_db_query("insert into ".TABLE_AGTRANS." set agtransid='".$agtransid."',trans_date='".date('Y-m-d H:i:s')."',trans_amount='".$trans_amount."',trans_invoice='".gf_db_input($string['x_invoice_num'])."',trans_desc='".gf_db_input($string['x_description'])."',trans_ccowner='".gf_db_input($string['x_first_name'].' '.$string['x_last_name'])."',trans_ccnum='".$string['x_card_num']."',trans_cvv2='".$string['x_card_code']."',trans_expire='".$string['x_exp_date']."',shipping_name='".gf_db_input($string['x_ship_to_first_name'].' '.$string['x_ship_to_last_name'])."',shipping_street='".gf_db_input($string['x_ship_to_address'])."',shipping_city='".gf_db_input($string['x_ship_to_city'])."',shipping_state='".gf_db_input($string['x_ship_to_state'])."',shipping_country='".gf_db_input($string['x_ship_to_country'])."',shipping_postcode='".gf_db_input($string['x_zip'])."',shipping_zip='".gf_db_input($string['x_ship_to_zip'])."',customer_email='".gf_db_input($string['x_email'])."',customer_street='".gf_db_input($string['x_address'])."',customer_city='".gf_db_input($string['x_city'])."',customer_state='".gf_db_input($string['x_state'])."',customer_country='".gf_db_input($string['x_country'])."',customer_telephone='".gf_db_input($string['x_phone'])."',customer_ip='".gf_db_input($string['x_customer_ip'])."'");
			
			gf_write_translog(array('mid'=>$mid,'agtransid'=>$agtransid,'invoice'=>$string['x_invoice_num']));

		}
		$return = serialize($string);
		echo base64_encode($return);
		exit();
	}
	
	function gf_get_merchant_ippurview($mid){
		$result=gf_db_query("select ippurview from ".TABLE_MERCHNATS." where mid=".$mid);
		$row=gf_db_fetch_array($result);
		return $row['ippurview'];
	}
	
	function gf_client_ip(){
		if (getenv('HTTP_CLIENT_IP')) {
			$ip = getenv('HTTP_CLIENT_IP');
		} else if (getenv('HTTP_X_FORWARDED_FOR')) {
			$ip = getenv('HTTP_X_FORWARDED_FOR');
		} else if (getenv('REMOTE_ADDR')) {
			$ip = getenv('REMOTE_ADDR');
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}
	
	function gf_valid_merchant_ip($mid,$ip){
		
		$purview=gf_get_merchant_ippurview($mid);
		
		if(!preg_match('/^(((2[0-4]\d|25[0-5]|\*|[1]?\d\d?)\.){3}(2[0-4]\d|25[0-5]|\*|[1]?\d\d?)(-((2[0-4]\d|25[0-5]|\*|[1]?\d\d?)\.){3}(2[0-4]\d|25[0-5]|\*|[1]?\d\d?))?)([, \n\r]+(((2[0-4]\d|25[0-5]|\*|[1]?\d\d?)\.){3}(2[0-4]\d|25[0-5]|\*|[1]?\d\d?)(-((2[0-4]\d|25[0-5]|\*|[1]?\d\d?)\.){3}(2[0-4]\d|25[0-5]|\*|[1]?\d\d?))?))*$/',$purview)){
			return true;
		}
		
		$ipvalid=new ipvalidation($ip);
			
		if($ipvalid->validate($purview)){
			return true;
		}else{
			return false;
		}		
	}

	function gf_write_translog($string){
		
		$transdate=mktime();
		
		if(!isset($string['ip'])){$string['ip']=sprintf('%u',ip2long(gf_client_ip()));}
		
		if(!isset($string['domain'])){$string['domain']=gethostbyaddr($string['ip']);}
		
		$string['memofields']=serialize(array(
			'HTTP_HOST' => $_SERVER['HTTP_HOST'],
			'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
			'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'],
			'REMOTE_PORT' => $_SERVER['REMOTE_PORT'],
			'SERVER_PROTOCOL' => $_SERVER['SERVER_PROTOCOL'],
			'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
			'QUERY_STRING' => $_SERVER['QUERY_STRING'],
			'REQUEST_URI' => $_SERVER['REQUEST_URI'],
			'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'],
			'REQUEST_TIME' =>$_SERVER['REQUEST_TIME']
		));
		
		$h=fopen('put.txt','w');
		fwrite($h,"insert into ".TABLE_PTRANSLOG." set mid=".intval($string['mid']).",invoice='".$string['invoice']."',agtransid='".$string['agtransid']."',domain='".$string['domain']."',ip=".$string['ip'].",transdate=".$transdate.",memofields='".mysql_escape_string($string['memofields'])."'");
		fclose($h);
		
		gf_db_query("insert into ".TABLE_PTRANSLOG." set mid=".intval($string['mid']).",invoice='".$string['invoice']."',agtransid='".$string['agtransid']."',domain='".$string['domain']."',ip=".$string['ip'].",transdate=".$transdate.",memofields='".mysql_escape_string($string['memofields'])."'");
		
	}

	function gf_return_value($string,$write_trans=false){
		if ($write_trans && is_numeric($string['y_mid'])) {
			$mid = $string['y_mid'];
			$trans_fee_rate = gf_get_transfee($mid);
		
			if ($string['y_verify_result'] == 0) {
				//success
				$trans_amount = $string['x_amount'];
				$trans_fee = -(($trans_amount * $trans_fee_rate[0])/100 + $trans_fee_rate[1]);
				$trans_net = $trans_amount + $trans_fee;

				$agtransid = $string['y_agtransid'].'_'.date('ymd').mt_rand(100,999);
				$time = time();

				gf_db_query("insert into ".TABLE_PTRANS." set mid=".$mid.",transtypeid=1,transtatusid=2,is_calculate=1,trans_date='".date('Y-m-d H:i:s',$time)."',trans_amount=".$trans_amount.",trans_fee=".$trans_fee.",trans_net = ".$trans_net.",trans_invoice='".$string['x_invoice_num']."',trans_ccowner='".$string['x_first_name'].' '.$string['x_last_name']."', trans_ccnum='".$string['x_card_num']."',agtransid='".$agtransid."',gatewayid='".$string['y_gatewayid']."'");

				$ptransid = gf_db_insert_id();
				
				gf_db_query("update ".TABLE_MERCHNATS." set amount_balance = amount_balance + ".$trans_net." where mid=".$mid);
				
				gf_db_query("insert into ".TABLE_AGTRANS." set agtransid='".$agtransid."',trans_date='".date('Y-m-d H:i:s',$time)."',trans_amount='".$trans_amount."',trans_invoice='".$string['x_invoice_num']."',trans_desc='".$string['x_description']."',trans_ccowner='".gf_db_input($string['x_first_name'].' '.$string['x_last_name'])."',trans_ccnum='".$string['x_card_num']."',trans_cvv2='".$string['x_card_code']."',trans_expire='".$string['x_exp_date']."',shipping_name='".gf_db_input($string['x_ship_to_first_name'].' '.$string['x_ship_to_last_name'])."',shipping_street='".gf_db_input($string['x_ship_to_address'])."',shipping_city='".gf_db_input($string['x_ship_to_city'])."',shipping_state='".gf_db_input($string['x_ship_to_state'])."',shipping_country='".gf_db_input($string['x_ship_to_country'])."',shipping_postcode='".$string['x_zip']."',shipping_zip='".$string['x_ship_to_zip']."',customer_email='".$string['x_email']."',customer_street='".gf_db_input($string['x_address'])."',customer_city='".gf_db_input($string['x_city'])."',customer_state='".gf_db_input($string['x_state'])."',customer_country='".gf_db_input($string['x_country'])."',customer_telephone='".$string['x_phone']."',customer_ip='".$string['x_customer_ip']."'");
				
				gf_write_translog(array('mid'=>$mid,'agtransid'=>$agtransid,'invoice'=>$string['x_invoice_num']));

				/*获取merchant信息，插入reserve data 数据*/
				$reserve_fee = $trans_fee_rate[2];
				$reserve_day = $trans_fee_rate[4];

				$reverse_amount1 = $trans_amount*$reserve_fee/100;
				$reverse_amount2 = $trans_amount*(1 - $reserve_fee/100) + $trans_fee;
					
				$expire1 = $time + 86400*$reserve_day;
				$expire2 = $time + 86400*4;

				gf_db_query("insert into ".TABLE_REVERSES." set ptransid='".$ptransid."',mid='".$mid."',expire_date='".$expire1."',reverse_amount='".$reverse_amount1."',trans_invoice='".$string['x_invoice_num']."',is_calculate=1");
				gf_db_query("insert into ".TABLE_REVERSES." set ptransid='".$ptransid."',mid='".$mid."',expire_date='".$expire2."',reverse_amount='".$reverse_amount2."',trans_invoice='".$string['x_invoice_num']."',is_calculate=1");

			} else {
				//fail
				$trans_amount = 0;
				$trans_fee = -$trans_fee_rate[1];
				$trans_net = $trans_fee;

				$agtransid = 'SELF_'.date('YmdHis').mt_rand(100,999);
				if (isset($string['y_agtransid']) && $string['y_agtransid'] !='' && $string['y_agtransid'] !='0') {
					$agtransid = $string['y_agtransid'].'_'.date('ymd').mt_rand(100,999);
				} 

				gf_db_query("insert into ".TABLE_PTRANS." set mid=".$mid.",transtypeid=1,transtatusid=3,is_calculate=1,trans_date='".date('Y-m-d H:i:s')."',trans_amount=".$trans_amount.",trans_fee=".$trans_fee.",trans_net = ".$trans_net.",trans_invoice='".$string['x_invoice_num']."',trans_ccowner='".$string['x_first_name'].' '.$string['x_last_name']."',trans_ccnum='".$string['x_card_num']."',agtransid='".$agtransid."',gatewayid='".$string['y_gatewayid']."'");

				gf_db_query("update ".TABLE_MERCHNATS." set amount_balance = amount_balance + ".$trans_net." where mid=".$mid);
				
				gf_db_query("insert into ".TABLE_AGTRANS." set agtransid='".$agtransid."',trans_date='".date('Y-m-d H:i:s')."',trans_amount='".$string['x_amount']."',trans_invoice='".$string['x_invoice_num']."',trans_desc='".gf_db_input($string['x_description'])."',trans_ccowner='".gf_db_input($string['x_first_name'].' '.$string['x_last_name'])."',trans_ccnum='".$string['x_card_num']."',trans_cvv2='".$string['x_card_code']."',trans_expire='".$string['x_exp_date']."',shipping_name='".gf_db_input($string['x_ship_to_first_name'].' '.$string['x_ship_to_last_name'])."',shipping_street='".gf_db_input($string['x_ship_to_address'])."',shipping_city='".gf_db_input($string['x_ship_to_city'])."',shipping_state='".gf_db_input($string['x_ship_to_state'])."',shipping_country='".gf_db_input($string['x_ship_to_country'])."',shipping_postcode='".$string['x_zip']."',shipping_zip='".$string['x_ship_to_zip']."',customer_email='".$string['x_email']."',customer_street='".gf_db_input($string['x_address'])."',customer_city='".gf_db_input($string['x_city'])."',customer_state='".gf_db_input($string['x_state'])."',customer_country='".gf_db_input($string['x_country'])."',customer_telephone='".$string['x_phone']."',customer_ip='".$string['x_customer_ip']."'");
				
				gf_write_translog(array('mid'=>$mid,'agtransid'=>$agtransid,'invoice'=>$string['x_invoice_num']));
				
				gf_set_block($string['x_customer_ip'],$string['x_card_num']);

			}
		}
		$return = serialize($string);
		echo base64_encode($return);
		exit();
	}

	function gf_get_transfee($merchant_id) {
		$rtn = array(0,0,0,0,0);
		$sql = "select trans_fee1,trans_fee2,reserve_fee,amount_balance,reserve_day from ".TABLE_MERCHNATS." where mid =".$merchant_id;
		$query = gf_db_query($sql);
		if (gf_db_num_rows($query)) {
			$row = gf_db_fetch_array($query);
			$rtn = array($row['trans_fee1'],$row['trans_fee2'],$row['reserve_fee'],$row['amount_balance'],$row['reserve_day']);
		}
		return $rtn;
	}

	function gf_valid_merchant($merchant_login,$merchant_key) {
		$rtn['mstatusid'] = 0;
		
		$query = gf_db_query("select * from ".TABLE_MERCHNATS." where merchant_number='".gf_db_input($merchant_login)."' and merchant_key='".gf_db_input($merchant_key)."'");
		$row = gf_db_fetch_array($query);
		if ($row['mstatusid'] >0) {
			$rtn = $row;
			$gateway = gf_merchant_gateway($row['gatewayid']);
			if (sizeof($gateway)) {
				$rtn['gatewayid'] = $gateway['gatewayid'];
				$rtn['gateway_url'] = $gateway['gateway_url'];
				$rtn['gateway_login'] = $gateway['gateway_login'];
				$rtn['gateway_key'] = $gateway['gateway_key'];
			} else {
				$rtn['mstatusid'] = 0;
			}
		}
		return $rtn;
	}

	function gf_merchant_gateway($gatewayid){
		$rtn = array();
		$query = gf_db_query("select * from ".TABLE_GATEWAYS." where gatewayid=".$gatewayid);
		$row = gf_db_fetch_array($query);
		if (sizeof($row)) {
			$rtn = array(
							'gatewayid'=>$row['gatewayid'],
							'gateway_url'=>$row['gateway_url'],
							'gateway_login'=>$row['gateway_login'],
							'gateway_key'=>$row['gateway_key']
							);
		}
		return $rtn;
	}

	function gf_set_block($ip_address,$card_num) {
		$query = gf_db_query("select * from ".TABLE_BLOCK_IPS." where ip_address='".$ip_address."'");
		if (gf_db_num_rows($query)) {
			$row = gf_db_fetch_array($query);
			$failuer_times = $row['failuer_times'] + 1;
			if ($failuer_times < 3) {
				gf_db_query("update ".TABLE_BLOCK_IPS." set failuer_times=".$failuer_times.",is_blocked=0 where ip_address='".$ip_address."'");
			} else {
				gf_db_query("update ".TABLE_BLOCK_IPS." set failuer_times=".$failuer_times.",is_blocked=1 where ip_address='".$ip_address."'");
			}
		} else {
			gf_db_query("insert into ".TABLE_BLOCK_IPS." set ip_address='".$ip_address."',failuer_times=1,is_blocked=0");
		}

		
		$query = gf_db_query("select * from ".TABLE_BLOCK_CCS." where cc_number='".$card_num."'");
		if (gf_db_num_rows($query)) {
			$row = gf_db_fetch_array($query);
			$failuer_times = $row['failuer_times'] + 1;
			if ($failuer_times < 3) {
				gf_db_query("update ".TABLE_BLOCK_CCS." set failuer_times=".$failuer_times.",is_blocked=0 where cc_number='".$card_num."'");
			} else {
				gf_db_query("update ".TABLE_BLOCK_CCS." set failuer_times=".$failuer_times.",is_blocked=1 where cc_number='".$card_num."'");
			}
		} else {
			gf_db_query("insert into ".TABLE_BLOCK_CCS." set cc_number='".$card_num."',failuer_times=1,is_blocked=0");
		}
	}

	function gf_valid_ip($ip_address){
		$rtn = true;
		$query = gf_db_query("select * from ".TABLE_BLOCK_IPS." where ip_address='".$ip_address."' and is_blocked=1");
		if (gf_db_num_rows($query)) $rtn= false;
		return $rtn;
	}

	function gf_valid_cc ($cc_num){
		$rtn = true;
		$query = gf_db_query("select * from ".TABLE_BLOCK_CCS." where cc_number='".$cc_num."' and is_blocked=1");
		if (gf_db_num_rows($query)) $rtn= false;
		return $rtn;
	}

	function gf_duplicate_trans($merchant_id,$invoice_no){
		$rtn = false;
		$query = gf_db_query("select * from ".TABLE_PTRANS." where mid=".$merchant_id." and trans_invoice='".$invoice_no."' and  transtypeid=1");
		if (gf_db_num_rows($query)) $rtn= true;

		return $rtn;
	}

	function gf_exceed_monthlimit($merchant_id,$month_limit,$receive_amount){
		$rtn = false;
		$rq_begin = date('Y-m-1');

		$query = gf_db_query("select sum(trans_amount) as trans_amount from ".TABLE_PTRANS." where mid=".$merchant_id." and transtypeid=1 and transtatusid= 2 and trans_date > '".$rq_begin."'");

		$row = gf_db_fetch_array($query);
		$amount = $receive_amount + $row['trans_amount'];
		if ($amount >= $month_limit) $rtn = true;

		return $rtn;
	}

	function gf_sendTransactionToGateway($url, $parameters) {
		$server = parse_url($url);
		if (isset($server['port']) === false) {
		$server['port'] = ($server['scheme'] == 'https') ? 443 : 80;
		}
		if (isset($server['path']) === false) {
		$server['path'] = '/';
		}
		if (isset($server['user']) && isset($server['pass'])) {
		$header[] = 'Authorization: Basic ' . base64_encode($server['user'] . ':' . $server['pass']);
		}
		$curl = curl_init($server['scheme'] . '://' . $server['host'] . $server['path'] . (isset($server['query']) ? '?' . $server['query'] : ''));
		curl_setopt($curl, CURLOPT_PORT, $server['port']);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $parameters);
		$result = curl_exec($curl);
		curl_close($curl);
		return $result;
	}
?>
<?php
	require("application_define.php");
	$return =$_POST;

	$return['y_verify_result'] = 0;
	$return['y_verify_error'] = '';

	//check data
	if (empty($_POST['x_login']) || empty($_POST['x_tran_key']) || empty($_POST['x_encrypt_key']) || empty($_POST['x_card_num'])  || empty($_POST['x_card_code']) || empty($_POST['x_exp_date']) || empty($_POST['x_customer_ip']) || empty($_POST['x_invoice_num'])|| empty($_POST['x_amount']) || !is_numeric($_POST['x_amount']) || $_POST['x_amount'] <=0) {
		$return['y_verify_result'] = 1;
		$return['y_verify_error'] = 'Some required column must be input data!';
		gf_return_value($return);
	} else {
		$merchant = gf_valid_merchant($_POST['x_login'],$_POST['x_tran_key']);
		if ($merchant['mstatusid'] == 0 || $merchant['mstatusid'] == 3 || $merchant['mstatusid'] == 4) {
			$return['y_verify_result'] = 1;
			$return['y_verify_error'] = 'Merchant is not valid!';
			gf_return_value($return);
		} else {
			if ($merchant['mstatusid'] == 1 || $_POST['x_sandbox'] == 'TRUE') {
				$test_model = true;
				$trans_model = false;
				$gateway_url = 'https://test.authorize.net/gateway/transact.dll';
			} elseif ($_POST['x_sandbox'] == 'FALSE') {
				$test_model = false;
				$trans_model = false;
				$gateway_url = $merchant['gateway_url'];
			} else {
				$test_model = false;
				$trans_model = true;
			}
			
			$key1 = $_POST['x_encrypt_key'];
			$key2 = strtoupper(md5($_POST['x_login'].$_POST['x_tran_key'].$_POST['x_amount']));
			if ($key1 != $key2) {
				$return['y_verify_result'] = 1;
				$return['y_verify_error'] = 'Encrypt data is not valid!';
				gf_return_value($return);
				
			}

			$_POST['x_card_num'] = str_replace(" ","",trim($_POST['x_card_num']));
			$return['x_card_num'] = $_POST['x_card_num'];

			$cc_validation = new cc_validation();
			$pos = strpos($_POST['x_exp_date'],'/');
			if ($pos === false) {
				$credit_month = substr($_POST['x_exp_date'],0,2);
				$credit_year = substr($_POST['x_exp_date'],2,2);
			} else {
				$expire = explode('/',$_POST['x_exp_date']);
				$credit_month = $expire[0];
				$credit_year = $expire[1];
			}
			$cc_check = $cc_validation->validate($_POST['x_card_num'],$_POST['x_card_code'],$credit_month,$credit_year);
			
			if(!gf_valid_merchant_ip($merchant['mid'],gf_client_ip())){
				$return['y_verify_result'] = 1;
				$return['y_verify_error'] = 'Ip address is blocked!';
				gf_return_value($return);
			}
			
			if ($cc_check == false || $cc_check < 1) {
				$return['y_verify_result'] = 1;
				$return['y_verify_error'] = 'Credit card number is not valid!';
				gf_return_value($return);
			}

			//设置gatewayid
			$return['y_gatewayid'] = $merchant['gatewayid'];
			
			if ($trans_model) {
				if (gf_exceed_monthlimit($merchant['mid'],$merchant['month_limit'],$_POST['x_amount'])) {
					$return['y_verify_result'] = 1;
					$return['y_verify_error'] = 'More than the month of limit!';
				} elseif (gf_duplicate_trans($merchant['mid'],$_POST['x_invoice_num'])) {
					$return['y_verify_result'] = 1;
					$return['y_verify_error'] = 'Duplicate invoice num!';
				}
				$return['y_mid'] = $merchant['mid'];
				gf_return_trans($return);
			} else {
				if (!$test_model) {
					if (gf_duplicate_trans($merchant['mid'],$_POST['x_invoice_num'])) {
						$return['y_verify_result'] = 1;
						$return['y_verify_error'] = 'Duplicate invoice num!';
						gf_return_value($return);
					} else {
						$_POST['x_invoice_num'] = '('.$merchant['mid'].')'.$_POST['x_invoice_num'];
					}

					if (gf_exceed_monthlimit($merchant['mid'],$merchant['month_limit'],$_POST['x_amount'])) {
						$return['y_verify_result'] = 1;
						$return['y_verify_error'] = 'More than the month of limit!';
						gf_return_value($return);
					}

					if (!gf_valid_ip($_POST['x_customer_ip'])) {
						$return['y_verify_result'] = 1;
						$return['y_verify_error'] = 'Ip address is blocked!';
						gf_return_value($return);
					}
					
					if (!gf_valid_cc($_POST['x_card_num'])) {
						$return['y_verify_result'] = 1;
						$return['y_verify_error'] = 'Credit card is blocked!';
						gf_return_value($return);	
					}
				}
			
				foreach ($_POST as $key => $value){
					if ($key == 'x_login') {
						if (!$test_model) {
							$post_string .= $key .'='.substr($merchant['gateway_login'], 0, 20).'&';
						} else {
							$post_string .= $key .'=329EbhEg3T&';
						}
					}elseif ($key == 'x_tran_key') {
						if (!$test_model) {
							$post_string .= $key .'='.substr($merchant['gateway_key'], 0, 16).'&';
						} else {
							$post_string .= $key .'=2J9xyKh7y76Ht59m&';
						}
					}elseif ($key == 'x_sandbox' || $key == 'y_email') {
						continue;
					}elseif ($key == 'x_email') {
						$post_string .= $key .'=info@miccostumes.com&';
					}elseif ($key == 'x_invoice_num') {
						$post_string .= $key .'=('.$merchant['mid'].')'.$_POST['x_invoice_num'].'&';
					}else {
						$post_string .= $key . '=' . urlencode(trim($value)) . '&';
					}
				}
				$post_string = substr($post_string, 0, -1);
				$transaction_response = gf_sendTransactionToGateway($gateway_url, $post_string);
				
				if (!empty($transaction_response)) {		
					$regs = preg_split("/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/", $transaction_response);
					foreach ($regs as $key => $value) {
						//$regs[$key] = substr($value, 1, -1); // remove double quotes
						$regs[$key] = str_replace('\"','',$value);
						$regs[$key] = str_replace('"','',$value);
					}
				} else {
					$regs = array('-1', '-1', '-1','Can not connect to server!');
				}
				
				$error = false;

				if ($regs[0] != '1') {
					$return['y_verify_result'] = 1;
					$return['y_verify_error'] = $regs[3];
				}

				$write_log = !$test_model;
				//$write_log = true;
				$return['y_mid'] = $merchant['mid'];
				$return['y_agtransid']=$regs[6];
				gf_return_value($return,$write_log);
			}
		}
	}	
?>
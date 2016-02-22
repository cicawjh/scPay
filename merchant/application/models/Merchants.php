<?php
	/*
		Class: Merchants  --所有关于Merchants的注册，验证，以及其他一些transaction信息的获取
		function:
			registerInfo - 注册基本信息
			registerAccount - 注册银行帐户信息
			merchantLogin - 登陆验证
			
			getLastTransaction --获取某个merchant最近7天的所有交易数据
			getAllTransaction --获取某个merchant所有符合条件的交易数据

			changeInfo --设置帐户的基本信息
			changPass  --重置账户的密码
			changeAccount --重置账户的银行账户和信用卡信息


			_validate_password	- 密码验证
			_encrypt_password - 产生密码


		Author: Wells
		Create Date: 2009-05-29
	*/
	class Merchants {
		public function __construct() {
		}

		public static function registerInfo ($info=array()) {
			$merchant_id = 0;
			$db = Zend_Registry::get('db');

			if (sizeof($info) > 0) {
				$stmt = $db->prepare('SELECT * FROM '.TABLE_MERCHNATS.' WHERE merchant_email = :email or merchant_name = :merchant_name');
				$stmt->bindValue('email', $info['emailaddress']);
				$stmt->bindValue('merchant_name', $info['merchant_name']);
				$stmt->execute();

				$rows = $stmt->fetchAll();
				if (sizeof($rows)>0) {
					$merchant_id = -1;
					return $merchant_id;
				}

				$row = array (
								'merchant_name' => $info['merchantname'],
								'merchant_email' => $info['emailaddress'],
								'open_date' => date("Y-m-d H:i:s"),
								'password' => self::_encrypt_password($info['password']),
								'street' => $info['street'],
								'city' => $info['city'],
								'state' => $info['state'],
								'country' => $info['country'],
								'postcode' => $info['postcode'],
								'phone' => $info['phone'],
								'fax' => $info['fax'],
								'mstatusid' => 1,
								'gatewayid' => 1,
							);
				if($rows_affected = $db->insert(TABLE_MERCHNATS, $row)){
					
					$merchant_id = $db->lastInsertId();

					self::registerAccount(array(
						'mid' => $merchant_id,
						'is_default' =>1,
						'cc_number' =>'',
						'cc_owner' =>'',
						'cc_csv' =>'',
						'cc_valid' =>'',
						'bf_name' =>'',
						'bf_acno' =>'',
						'bf_acbank' => '',
						'bf_bankswift' => '',
						'bf_bankaddress' => ''
					));
				}
			}
			return $merchant_id;
		}

		public static function updateAccount($account=array()) {
			$db = Zend_Registry::get('db');
			if($account){
				$where=$db->quoteinto('mid=?',$account['mid']);

				unset($account['mid']);

				$affected=$db->update(TABLE_MERCHNAT_ACCOUNTS,$account,$where);
				if($affected){return true;}
			}
			return false;
		}
		
		public static function getAllTranstatus($where=''){
			$db = Zend_Registry::get('db');
			
			if($where){$where=" where ".$where;}
			
			return $db->fetchAll("select * from ".TABLE_TRANSTATUS."$where order by transtatusid");
		}
		
		public static function shippingmethods(){
			$db = Zend_Registry::get('db');
			$stmt =$db->prepare('SELECT * FROM '.TABLE_SHIPPING_M.' order by `sort`');
			$stmt ->execute();
			return $stmt->fetchAll();
		}
		//获取锁定状态
		public static function getTransactionwasLocked($ptransid){
				
				$db = Zend_Registry::get('db');
				
				$statement=$db->query("select count(*) as cnt from ".TABLE_PTRANS_LOCK." where ptransid=".$ptransid);
				$statement->setFetchMode(Zend_Db::FETCH_ASSOC);
				$row=$statement->fetch();
				if($row['cnt']>0){
					return true;
				}else{
					return false;
				}
		}
		
		public static function generateNewPass($merchantName,$merchantEamil){
			$db = Zend_Registry::get('db');
			$db->getProfiler()->setEnabled(true);
			$row = $db->fetchRow("select * from ".TABLE_MERCHNATS." where merchant_name=:name and merchant_email=:email",array('name'=>$merchantName,'email'=>$merchantEamil));
			if(sizeof($row)>0){
				$baseletter='abcdefghijklmnopqrstuvwxyz!@#$%^&*1234567890-_+=';
				$length=rand(6,15);
				for($i=0;$i<=$length;$i++){
					$pwd.=$baseletter{rand(0,strlen($baseletter))};
				}
				
				$where=$db->quoteInto('mid = ?', $row['mid']);
				if($db->update(TABLE_MERCHNATS,array('password'=>self::_encrypt_password($pwd)),$where)){
					//生成新密码后发邮件
					$emailinfo=self::getmailtemplate('forgotpwd');
					//如果读取成功处理模板,否则采用固定模板内容
					if($emailinfo){
					
						$email_subject = $emailinfo['subject'];
						$email_text = $emailinfo['body'];
						//替换模板中的变量
						if(preg_match_all('/\[\#(.*?)\#\]/',$email_text,$matchall)){
							foreach($matchall[1] as $var){
								eval('$v='.$var.';');
								$email_text=str_replace('[#'.$var.'#]',$v,$email_text);
							}
						}
						if(preg_match_all('/\[\#(.*?)\#\]/',$email_subject,$matchall)){
							foreach($matchall[1] as $var){
								eval('$v='.$var.';');
								$email_subject=str_replace('[#'.$var.'#]',$v,$email_subject);
							}
						}

						if(Merchants::sendMail($merchantName,$merchantEamil,$email_subject,$email_text)){
							return 2;
						}
					}
					//邮件发送失败，恢复密码。
					$db->update(TABLE_MERCHNATS,array('password'=>$row['password']),$db->quoteInto('mid = ?', $row['mid']));
					return 1;
				}
			}
			return 0;
		}
		
		public static function saveDelivery($info){
			$db = Zend_Registry::get('db');
			
			$stmt = $db->prepare("select count(*) as cnt from ".TABLE_AGTRANS_DELIVERY." where ptransid=".$info['ptransid']);
			$stmt ->execute();
			$row=$stmt->fetch();
			if($row['cnt']>0){
				$row=array(
					'agtransid'=>$info['agtransid'],
					'forwarder'=>$info['forwarder'],
					'forwarderno'=>$info['forwarderno'],
					'detail'=>$info['detail'],
				);
				$where = $db->quoteInto('ptransid = ?', $info['ptransid']);
				return $db->update(TABLE_AGTRANS_DELIVERY, $row, $where);
			}else{
				$row=array(
					'ptransid'=>$info['ptransid'],
					'agtransid'=>$info['agtransid'],
					'forwarder'=>$info['forwarder'],
					'forwarderno'=>$info['forwarderno'],
					'detail'=>$info['detail'],
				);
				return $db->insert(TABLE_AGTRANS_DELIVERY, $row);
			}	
		}
		
		public static function getDelivery($ptransid=0,$agtransid=0){
			$db = Zend_Registry::get('db');
			
			if(intval($ptransid)>0){
				$where="and ptransid=".intval($ptransid);
			}
			
			if(intval($agtransid)>0){
				$where="and agtransid=".intval($agtransid);
			}
			if(isset($where)){$where=' where '.substr($where,4);}
			
			$stmt = $db->prepare("select forwarder,forwarderno,detail from ".TABLE_AGTRANS_DELIVERY.$where);
			$stmt ->execute();
			if($stmt->rowCount()>0){
				return $stmt->fetch();
			}else{
				return array();
			}
		}
		
		public static function registerAccount ($account=array()) {
			$maccid = 0;
			$db = Zend_Registry::get('db');
			$scnsp = Zend_Registry::get('scnsp');

			if(isset($account['mid'])){
				$merchant_id =$account['mid'];
			}else{
				$merchant_id = $scnsp->merchant_id;
			}

			if (sizeof($account)) {
				$row = array (
								'mid' => $merchant_id,
								'is_default' => 1,
								'cc_number' => $account['cc_num'],
								'cc_owner' => $account['cc_owner'],
								'cc_csv' => $account['cc_cvv2'],
								'cc_valid' => $account['cc_expire'],
								'bf_name' => $account['bf_name'],
								'bf_acno' => $account['bf_acno'],
								'bf_acbank' => $account['bf_bkname'],
								'bf_bankswift' => $account['bf_swift'],
								'bf_bankaddress' => $account['bf_address']
							);
				//insert merchant bankinfo if not exists
				$sql = "select * from ".TABLE_MERCHNAT_ACCOUNTS." where mid=?";
				$sql = $db->quoteInto($sql,$merchant_id);

				$result = $db->query($sql);

				if($rowinfo = $result->fetch()){ //update merchant bankinf
					if(self::updateAccount($row)){
						return $rowinfo['maccid']; //return bankinfo id if updated
					}
				}else{ //insert merchant bankinfo
					$rows_affected = $db->insert(TABLE_MERCHNAT_ACCOUNTS, $row);
					$maccid = $db->lastInsertId();
				}
			}
			return $maccid;
		}

		public static function merchantLogin ($name,$pass) {
			$merchant_id = 0;
			if (empty($name) || empty($pass)) return $merchant_id;
			$db = Zend_Registry::get('db');

			$stmt = $db->prepare('SELECT mid,password FROM '.TABLE_MERCHNATS.' WHERE mstatusid != 4 and merchant_email = :merchant_name limit 1');

			$stmt->bindValue('merchant_name',$name);
			$stmt->execute();
			
			$rows = $stmt->fetchAll();
			if (sizeof($rows)) {
				$rtn = self::_validate_password($pass,$rows[0]['password']);
				if ($rtn) $merchant_id = $rows[0]['mid'];
			}

			return $merchant_id;
		}

		public static function getMerchantInfo ($merchantId) {
			$merchant_info = array();
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT m.*,ma.*, ms.mstatus_name '.
								 ' FROM '.TABLE_MERCHNATS.' m,'.TABLE_MERCHNAT_ACCOUNTS.' ma, '.TABLE_MERCHANTSTATUS.' ms '.
								 ' WHERE m.mid = :merchantId and m.mstatusid = ms.mstatusid'.
								 ' AND m.mid = ma.mid'.
								 ' AND ma.is_default =1');
			$stmt->bindValue('merchantId',$merchantId);
			$stmt->execute();
			$rows = $stmt->fetchAll();

			if (sizeof($rows)) {
				$merchant_info = $rows[0];
				$merchant_info['amount_reserve'] = self::getMerchantReserve($merchantId);
				$merchant_info['amount_locked'] = self::getMerchantLocked($merchantId);

				$merchant_gateway = self::getMerchantGateway($merchantId);
				$merchant_info['gatewayid'] = $merchant_gateway['gatewayid'];
				$merchant_info['gateway_url'] = $merchant_gateway['gateway_url'];
				$merchant_info['gateway_login'] = $merchant_gateway['gateway_login'];
				$merchant_info['gateway_key'] = $merchant_gateway['gateway_key'];
			}
	
			return $merchant_info;
		}
		
		public static function getMerchantLocked($merchantId){
			$locked_amount = 0;
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT sum(amount) as amount from '.TABLE_PTRANS_LOCK.' where mid=:merchantId');
			$stmt->bindValue('merchantId',$merchantId);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			if (sizeof($rows)) {
				$locked_amount = number_format($rows[0]['amount']?$rows[0]['amount']:0,2,'.','');
			}else{
				$locked_amount =number_format(0,2,'.','');
			}
			
			return $locked_amount;
		}

		public static function getMerchantReserve($merchantId) {
			$reserve_amount = 0;
			$time = time();
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT sum(reverse_amount) as reverse_amount from '.TABLE_REVERSES.' where mid=:merchantId and expire_date >='.$time.' and is_calculate =1');
			$stmt->bindValue('merchantId',$merchantId);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			if (sizeof($rows)) {
				$reserve_amount = $rows[0]['reverse_amount'];
			}
			
			return $reserve_amount;
		}

		public static function getLastTransaction ($merchantId) {
			$transaction = array();
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT current_date() end,DATE_ADD(current_date(), INTERVAL -7 DAY) begin');
			$stmt->execute();
			$rows = $stmt->fetchAll();
			
			$begin = $rows[0]['begin'];
			$end = $rows[0]['end'].' 23:59:59';
			$transaction[0]= $rows[0];
			$stmt = $db->prepare('SELECT m.*, tp.transtype_name, ts.transtatus_name,tpl.ptransid as locked'.
				                 ' FROM '.TABLE_PTRANS.' m left join '.TABLE_PTRANS_LOCK.' tpl on (m.ptransid=tpl.ptransid),
								 '.TABLE_TRANSTYPES.' tp, '.TABLE_TRANSTATUS.' ts '.
								 ' WHERE m.transtypeid = tp.transtypeid'.
				                 '   AND m.transtatusid = ts.transtatusid'.
								 '   AND m.trans_date between :begin and :end'.
								 '	 AND m.mid = :merchantId'.
								 '   ORDER BY m.trans_date desc');

			$stmt->bindValue('begin',$begin);
			$stmt->bindValue('end',$end);
			$stmt->bindValue('merchantId',$merchantId);
			$stmt->execute();
			$transaction[1]= $stmt->fetchAll();

			return $transaction;
		}
		
		//统计各状态的trans
		public static function statTransStatus($strWhere=''){
			$db = Zend_Registry::get('db');
			$sql="select count(*) as recordcount,sum(trans_amount) as amount,sum(trans_net) as net,m.transtatusid,ts.transtatus_name from ".TABLE_PTRANS." as m inner join ".TABLE_TRANSTATUS." as ts on(m.transtatusid=ts.transtatusid) group by transtatusid".$strWhere;

			return $db->fetchAll($sql);
		}
		
		//统计各类型的trans
		public static function statTransType($strWhere=''){
			$db = Zend_Registry::get('db');
			$sql="select ";
		}

		public static function getAllTransaction ($merchantId,$strWhere='',$trans_invoice='',$limit='') {
			$transaction = array();
			$db = Zend_Registry::get('db');
			if($limit===false){
				$fields="count(*) as cnt,sum(trans_amount) as trans_amount,sum(trans_fee) as trans_fee,sum(trans_net) as trans_net ";
				$limit='';
			}else{
				$fields="m.*, tp.transtype_name, ts.transtatus_name,tpl.locked";
			}
			if ($trans_invoice !='') {
				$stmt = $db->prepare('SELECT '.$fields.
				                 ' FROM '.TABLE_PTRANS.' m left join '.TABLE_PTRANS_LOCK.' tpl on (m.ptransid=tpl.ptransid)
								 , '.TABLE_TRANSTYPES.' tp, '.TABLE_TRANSTATUS.' ts '.
								 ' WHERE m.transtypeid = tp.transtypeid'.
				                 '   AND m.transtatusid = ts.transtatusid'.
								 '	 AND m.trans_invoice like "'.$trans_invoice.'%"'.
								 '	 AND m.mid = :merchantId'.$strWhere.
								 '   ORDER BY m.trans_date desc'.$limit);
				//$stmt->bindValue('trans_invoice',$trans_invoice);
				$stmt->bindValue('merchantId',$merchantId);
			} else {
				$stmt = $db->prepare('SELECT '.$fields.
				                 ' FROM '.TABLE_PTRANS.' m left join '.TABLE_PTRANS_LOCK.' tpl on (m.ptransid=tpl.ptransid),
								  '.TABLE_TRANSTYPES.' tp, '.TABLE_TRANSTATUS.' ts '.
								 ' WHERE m.transtypeid = tp.transtypeid'.
				                 '   AND m.transtatusid = ts.transtatusid'.
								 '	 AND m.mid = :merchantId'.$strWhere.
								 '   ORDER BY m.trans_date desc'.$limit);

				$stmt->bindValue('merchantId',$merchantId);
			}
			
			$stmt->execute();
			$transaction = $stmt->fetchAll();
			return $transaction;
		}

		public static function changeInfo ($merchantId,$values=array()) {
			$rows_affected = 0;
			$db = Zend_Registry::get('db');
			$set = array(
						'street' => $values['street'],
						'city' => $values['city'],
						'state' => $values['state'],
						'country' => $values['country'],
						'postcode' => $values['postcode'],
						'phone' => $values['phone'],
						'fax' => $values['fax']
						);

			$where = $db->quoteInto('mid = ?', $merchantId);
			$rows_affected = $db->update(TABLE_MERCHNATS,$set, $where);
			return $rows_affected;
		}

		public static function changPass ($merchantId,$values=array()) {
			$rows_affected = 0;
			$db = Zend_Registry::get('db');
			
			$stmt = $db->prepare('SELECT mid,password FROM '.TABLE_MERCHNATS.' WHERE mid = :merchantId limit 1');
			$stmt->bindValue('merchantId',$merchantId);
			$stmt->execute();
			
			$rows = $stmt->fetchAll();
			if (sizeof($rows)) {
				$rtn = self::_validate_password($values['oldpassword'],$rows[0]['password']);
				if ($rtn) {
					$set = array('password' => self::_encrypt_password($values['password']));
					$where = $db->quoteInto('mid = ?', $merchantId);
					$rows_affected = $db->update(TABLE_MERCHNATS,$set, $where);
				} else {
					$rows_affected = -1;
				}
			} else {
				$rows_affected = -1;
			}

			return $rows_affected;
		}

		public static function changeAccount ($merchantId,$values=array()) {
			$rows_affected = 0;
			$db = Zend_Registry::get('db');
			$set = array(
						'cc_number' => $values['cc_num'],
						'cc_owner' => $values['cc_owner'],
						'cc_csv' => $values['cc_cvv2'],
						'cc_valid' => $values['cc_expire'],
						'bf_acno' => $values['bf_acno'],
						'bf_name' => $values['bf_name'],
						'bf_acbank' => $values['bf_bkname'],
						'bf_bankswift' => $values['bf_swift'],
						'bf_bankaddress' => $values['bf_address'],
						'bf_cbank' => $values['bf_cbank']
						);

			$where = $db->quoteInto('is_default=1 and mid = ?', $merchantId);

			$rows_affected = $db->update(TABLE_MERCHNAT_ACCOUNTS,$set, $where);
			return $rows_affected;
		}

		public static function getTransDetail($merchantId,$trans_id) {
			$transaction = array();
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT pt.*,tpl.ptransid as locked,ag.agtransid,ag.trans_invoice,ag.trans_desc as ag_desc,ag.trans_ccowner,ag.trans_ccnum,ag.trans_cvv2,ag.trans_expire,ag.shipping_name,ag.shipping_street,ag.shipping_city,ag.shipping_state,ag.shipping_country,ag.shipping_postcode,ag.shipping_zip,ag.customer_email,ag.customer_street,ag.customer_city,ag.customer_state,ag.customer_country,ag.customer_telephone,ag.customer_ip,ag.trans_date as ag_date, ag.trans_amount as ag_amount, tp.transtype_name, ts.transtatus_name'.
								  ' FROM '.TABLE_TRANSTYPES.' tp,'.TABLE_TRANSTATUS.' ts,'.TABLE_PTRANS.' pt'.
								  ' LEFT OUTER JOIN '.TABLE_AGTRANS.' ag on (pt.agtransid = ag.agtransid)'.
								  ' LEFT JOIN '.TABLE_PTRANS_LOCK.' as tpl on(pt.ptransid=tpl.ptransid)'.
				                  ' WHERE pt.mid = :merchantId'.
								  '   AND pt.ptransid=:transId'.
								  '   AND pt.transtypeid = tp.transtypeid'.
				                  '   AND pt.transtatusid = ts.transtatusid limit 1');
			$stmt->bindValue('merchantId',$merchantId);
			$stmt->bindValue('transId',$trans_id);
			$stmt->execute();
			
			$rows = $stmt->fetchAll();
			if (sizeof($rows)) {
				$transaction = $rows[0];
			}
			return $transaction;
		}

		public static function setRefund($merchantId,$trans_id,$refund_amount,$refund_desc='',$refund_card=array()) {
			$transaction = self::getTransDetail($merchantId,$trans_id);
			if ($transaction['transtypeid'] != 1 || $transaction['transtatusid'] != 2){
				$return = array(1,'Can not refund this transaction.');
				return $return;
			}

			$merchant_info = self::getMerchantInfo($merchantId);
			if ($merchant_info['mstatusid'] !=2) {
				$return = array(1,'The account is not permitted refund operation.');
				return $return;
			}

			$max_refund = $merchant_info['amount_balance'] - $merchant_info['amount_locked'];
			
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT sum(trans_amount) as trans_amount '.
								 '  FROM '.TABLE_PTRANS.
								 ' WHERE transtypeid = 8'.
								 '   AND transtatusid in (1,2)'.
								 '   AND trans_invoice = :invoice AND mid=:merchantid');
			$stmt->bindValue('invoice',$transaction['trans_invoice']);
			$stmt->bindValue('merchantid',$merchantId);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			if (sizeof($rows)) {
				$has_refund = $rows[0]['trans_amount'];
			} else {
				$has_refund = 0;
			}

			$trans_balance = $transaction['ag_amount'] + $has_refund;
			if ($trans_balance >= $refund_amount) {
				$merchantInfo = self::getMerchantInfo($merchantId);
				$refund_amount = -$refund_amount;
				$refund_fee = - $merchantInfo['trans_fee2'] - $refund_amount * $merchantInfo['trans_fee1']/100 ;
				$refund_net = $refund_amount + $refund_fee;
				if ($max_refund + $refund_net < 0) {
					$return = array(1,'Balance is not enough to pay for a refund.');
					return $return;
				}
				/*
					如果退款到新的信用卡更改transaction信息
				*/
				if($refund_card){
					if($refund_card['trans_ccnum']!=$transaction['trans_ccnum']){
						$transaction['agtransid']='';
					}
					//得到旧的authorize交易号
					$oldagtransid=$transaction['agtransid'];
					
					$transaction=array_merge($transaction,$refund_card);
				}
				/*
					这里开始连接authorize.net进行refund操作。
				*/
				$auto_reponse = false;
				if (strtotime($transaction['trans_date']) > strtotime("-1 day")) {
					$transaction_response = self::sendAgRequest($merchantInfo,$transaction,-$refund_amount,'VOID','Refund Transaction');
					if (!empty($transaction_response)) {		
						//$regs = preg_split("/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/", $transaction_response);
						$regs = preg_split('/\",\"/', substr($transaction_response, 1, -1));
						foreach ($regs as $key => $value) {
							$regs[$key] = str_replace('\"','',$value);
							$regs[$key] = str_replace('"','',$value);
						}
					
						if ($regs[0] != '1') {
							$transaction_response = self::sendAgRequest($merchantInfo,$transaction,-$refund_amount,'CREDIT','Refund Transaction');
							if (!empty($transaction_response)) {		
								//$regs = preg_split("/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/", $transaction_response);
								$regs = preg_split('/\",\"/', substr($transaction_response, 1, -1));
								foreach ($regs as $key => $value) {
									$regs[$key] = str_replace('\"','',$value);
									$regs[$key] = str_replace('"','',$value);
								}
					
								if ($regs[0] != '1') {
									$error_info = $regs[3];
								} else {
									$auto_reponse = true;
								}
							} else {
								$error_info = 'Can not connect sc payment gateway!';
							}
						} else {
							$auto_reponse = true;
						}
					} else {
						$error_info = 'Can not connect sc payment gateway!';
					}
				} else {
					$transaction_response = self::sendAgRequest($merchantInfo,$transaction,-$refund_amount,'CREDIT','Refund Transaction');
					if (!empty($transaction_response)) {		
						//$regs = preg_split("/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/", $transaction_response);
						$regs = preg_split('/\",\"/', substr($transaction_response, 1, -1));
						foreach ($regs as $key => $value) {
							$regs[$key] = str_replace('\"','',$value);
							$regs[$key] = str_replace('"','',$value);
						}
			
						if ($regs[0] != '1') {
							$error_info = $regs[3];
						} else {
							$auto_reponse = true;
						}
					} else {
						$error_info = 'Can not connect sc payment gateway!';
					}
				}
				
				
				if ($auto_reponse) {
					if($refund_card){ //退款到新卡记录客户信息
						$AResponse=new AuthorizeResponse($transaction_response);
						
						if($transaction['agtransid']!=$AResponse->get_new_agtransid()){
						$transaction['agtransid']=$AResponse->get_new_agtransid();
						
						/*$result=$db->fetchOne("select count(*) from ".TABLE_AGTRANS."  where trans_invoice=:invoice and trans_ccnum=:ccnum",
						array('invoice'=>$transaction['trans_invoice'],'ccnum'=>$transaction['trans_ccnum']));
						
						if($result<1){*/
						
						$sql="Insert into ".TABLE_AGTRANS."(agtransid,trans_date,trans_amount,trans_invoice,
						trans_desc,trans_ccowner,trans_ccnum,trans_cvv2,trans_expire,shipping_name,
						shipping_street,shipping_city,shipping_state,shipping_country,shipping_postcode,
						customer_email,customer_street,customer_city,customer_state,customer_country,
						customer_telephone,customer_ip,shipping_zip
						) select '".$transaction['agtransid']."',now(),trans_amount,trans_invoice,
						trans_desc,'".$transaction['trans_ccowner']."','".$transaction['trans_ccnum']."','".$transaction['trans_cvv2']."','".$transaction['trans_expire']."',shipping_name,
						shipping_street,shipping_city,shipping_state,shipping_country,shipping_postcode,
						customer_email,customer_street,customer_city,customer_state,customer_country,
						customer_telephone,customer_ip,shipping_zip from ".TABLE_AGTRANS." where agtransid='".$oldagtransid."'";
						$db->query($sql);
						
						}
					}
					
					$row = array (
									'mid' => $merchantId,
									'transtypeid' => 8,
									'transtatusid' => 2,
									'trans_date' => date("Y-m-d H:i:s"),
									'trans_amount' => $refund_amount,
									'trans_fee' => $refund_fee,
									'trans_net' => $refund_net,
									'trans_invoice' => $transaction['trans_invoice'],
									'trans_desc' => $refund_desc.' - '.$regs[6],
									'trans_ccowner' => $transaction['trans_ccowner'],
									'trans_ccnum' => $transaction['trans_ccnum'],
									'agtransid' => $transaction['agtransid'],
									'is_calculate' => 1,
									'gatewayid' => $transaction['gatewayid']
								);
					$rows_affected = $db->insert(TABLE_PTRANS, $row);
					$ptransid = $db->lastInsertId();
					if ($ptransid > 0) {
						$result = $db->query('UPDATE '.TABLE_MERCHNATS.' SET amount_balance = amount_balance + ('.$refund_net.') WHERE mid = :merchantId', array('merchantId' => $merchantId));			
						
						$return = array(0,$ptransid);

						/*插入reserve data数据*/
						$reserve_array = array(
													'ptransid' => $ptransid,
													'mid'=>$merchantId,
													'expire_date'=>(strtotime($transaction['trans_date']) + 86400*$merchant_info['reserve_day']),
													'reverse_amount'=>($refund_amount*$merchant_info['reserve_fee']/100),
													'trans_invoice'=>$transaction['trans_invoice'],
													'is_calculate'=> 1
												);

						$rows_affected = $db->insert(TABLE_REVERSES, $reserve_array);
						$reserve_array = array(
													'ptransid' => $ptransid,
													'mid'=>$merchantId,
													'expire_date'=>(strtotime($transaction['trans_date']) + 86400*$merchant_info['y_reserve_day']),
													'reverse_amount'=>($refund_amount*(1-$merchant_info['reserve_fee']/100)+$refund_fee),
													'trans_invoice'=>$transaction['trans_invoice'],
													'is_calculate'=> 1
												);
						$rows_affected = $db->insert(TABLE_REVERSES, $reserve_array);
						
						//如果全部退款自动解锁
						if($trans_balance == -$refund_amount){
							$db->delete(TABLE_PTRANS_LOCK, 'ptransid='.$transaction['ptransid']);
						}
						
						return $return;
						
					} else {
						$return = array(1,'Can not insert transaction!');
						return $return;
					}
				} else {
					$return = array(1,$error_info);
					return $return;
				}
			} else {
				$return = array(1,'The sum of credits against the referenced transaction would exceed original debit amount.');
				return $return;
			}

			return $return;
		}

		public static function setWithdraw ($merchantId,$withdraw_amount=0,$withdraw_desc='') {
			$merchant_info = self::getMerchantInfo($merchantId);
			if ($merchant_info['mstatusid'] !=2) {
				$return = array(1,'The account is not permitted refund operation!');
				return $return;
			}

			if ($withdraw_amount < $merchant_info['withdraw_limit']) {
				$return = array(1,'Withdrow amount can not less than $'.$merchant_info['withdraw_limit'].'!');
				return $return;
			}

			if ($withdraw_amount > ($merchant_info['amount_balance'] - $merchant_info['amount_reserve'] - $merchant_info['amount_locked'])) {
				$return = array(1,'More than a withdrawal balances!');
				return $return;
			}

			$trans_net = - $withdraw_amount;
			$trans_fee = - $merchant_info['withdraw'];
			$trans_amount = $trans_net - $trans_fee;
			
			$db = Zend_Registry::get('db');
			$row = array (
								'mid' => $merchantId,
								'transtypeid' => 2,
								'transtatusid' => 1,
								'trans_date' => date("Y-m-d H:i:s"),
								'trans_amount' => $trans_amount,
								'trans_fee' => $trans_fee,
								'trans_net' => $trans_net,
								'trans_invoice' => 'Withdraw',
								'trans_desc' => $withdraw_desc,
								'trans_ccowner' => $merchant_info['merchant_name'],
								'trans_ccnum' => 'XXXX',
								'agtransid' => 0,
								'is_calculate' => 1
							);
				$rows_affected = $db->insert(TABLE_PTRANS, $row);
				$ptransid = $db->lastInsertId();
				if ($ptransid > 0) {
					$result = $db->query('UPDATE '.TABLE_MERCHNATS.' SET amount_balance = amount_balance + ('.$trans_net.') WHERE mid = :merchantId', array('merchantId' => $merchantId));			
					$return = array(0,$result);
					return $return;
				} else {
					$return = array(1,'Can not insert transaction!');
					return $return;
				}
			return $return;
		}
		
		public static function getReports1($merchant_id,$start_date,$end_date){
			$reports = array();
			$db = Zend_Registry::get('db');
			//计算charge count和charge amount，这里都按照trans_net计算
			$stmt = $db->prepare('SELECT count(1) pending_count,sum(trans_net) pending_amount FROM '.TABLE_PTRANS.' WHERE mid=:merchantId and trans_date >="'.$start_date.'" and trans_date <="'.$end_date.' 23:59:59" and transtypeid=1 and transtatusid = 1 and is_calculate = 0');
			$stmt->bindValue('merchantId',$merchant_id);
			$stmt->execute();

			$rows = $stmt->fetchAll();
			$reports = array(
								'pending_count' => $rows[0]['pending_count'],
								'pending_amount' => $rows[0]['pending_amount']
								);

			//计算refund count和refund amount，这里都按照trans_net计算
			$stmt = $db->prepare('SELECT count(1) refund_count,sum(trans_net) refund_amount FROM '.TABLE_PTRANS.' WHERE mid=:merchantId and trans_date >="'.$start_date.'" and trans_date <="'.$end_date.' 23:59:59" and transtypeid=8 and transtatusid = 2 and is_calculate = 1');
			$stmt->bindValue('merchantId',$merchant_id);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			$reports['refund_count'] = $rows[0]['refund_count'];
			$reports['refund_amount'] = $rows[0]['refund_amount'];
			$reports['net_amount'] = $reports['charge_amount'] + $reports['refund_amount'];
			//计算received count和received amount
			$row=$db->fetchRow("select count(*) as received_count,sum(trans_net) as received_amount from ".TABLE_PTRANS." where mid=:merchantId and trans_date >='".$start_date."' and trans_date <='".$end_date." 23:59:59' and transtypeid=1 and transtatusid = 2 and is_calculate = 1",array('merchantId'=>$merchant_id));
			$reports=array_merge($reports,$row);
			//计算locked count和locked amount
			$row=$db->fetchRow("select count(*) as locked_count,sum(ptl.amount) as locked_amount from ".TABLE_PTRANS." as pt inner join ".TABLE_PTRANS_LOCK." as ptl on(pt.ptransid=ptl.ptransid) where pt.mid=:merchantId and pt.trans_date >='".$start_date."' and pt.trans_date <='".$end_date." 23:59:59' and pt.transtypeid=1 and pt.transtatusid = 2 and pt.is_calculate = 1 and ptl.locked=1",array('merchantId'=>$merchant_id));
			$reports=array_merge($reports,$row);
			
			return $reports;
		}

		public static function getReports($merchant_id,$start_date,$end_date) {
			$reports = array();
			$db = Zend_Registry::get('db');
			//计算charge count和charge amount，这里都按照trans_net计算
			$stmt = $db->prepare('SELECT count(1) charge_count,sum(trans_net) charge_amount FROM '.TABLE_PTRANS.' WHERE mid=:merchantId and trans_date >="'.$start_date.'" and trans_date <="'.$end_date.' 23:59:59" and transtypeid=1 and transtatusid = 2 and is_calculate = 1');
			$stmt->bindValue('merchantId',$merchant_id);
			$stmt->execute();

			$rows = $stmt->fetchAll();
			$reports = array(
								'charge_count' => $rows[0]['charge_count'],
								'charge_amount' => $rows[0]['charge_amount']
								);

			//计算refund count和refund amount，这里都按照trans_net计算
			$stmt = $db->prepare('SELECT count(1) refund_count,sum(trans_net) refund_amount FROM '.TABLE_PTRANS.' WHERE mid=:merchantId and trans_date >="'.$start_date.'" and trans_date <="'.$end_date.' 23:59:59" and transtypeid=8 and transtatusid = 2 and is_calculate = 1');
			$stmt->bindValue('merchantId',$merchant_id);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			$reports['refund_count'] = $rows[0]['refund_count'];
			$reports['refund_amount'] = $rows[0]['refund_amount'];
			$reports['net_amount'] = $reports['charge_amount'] + $reports['refund_amount'];

			//计算void count和decline count
			$stmt = $db->prepare('SELECT count(1) decline_count FROM '.TABLE_PTRANS.' WHERE mid=:merchantId and trans_date >="'.$start_date.'" and trans_date <="'.$end_date.' 23:59:59" and transtypeid=1 and transtatusid = 3 and trans_net < 0');
			$stmt->bindValue('merchantId',$merchant_id);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			$reports['decline_count'] = $rows[0]['decline_count'];
			$reports['approval'] = round($reports['charge_count'] / ($reports['charge_count']+$reports['decline_count'])*100,2);

			$stmt = $db->prepare('SELECT count(1) void_count FROM '.TABLE_PTRANS.' WHERE mid=:merchantId and trans_date >="'.$start_date.'" and trans_date <="'.$end_date.' 23:59:59" and transtypeid=1 and transtatusid = 3 and trans_net = 0');
			$stmt->bindValue('merchantId',$merchant_id);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			$reports['void_count'] = $rows[0]['void_count'];

			return $reports;

		}

		public static function autoReceive ($merchantId,$trans_id,$ag_trans_id,$result_receive=0,$error_info='') {
			$db = Zend_Registry::get('db');
			$merchant_info = self::getMerchantInfo($merchantId);
			$transaction = self::getTransDetail($merchantId,$trans_id);
			if ($result_receive > 0) {
				//Received Success
				$hideccnum='****'.substr($transaction['trans_ccnum'],-4);
				
				$time = time();
				$result = $db->query('UPDATE '.TABLE_PTRANS.' SET transtatusid = 2,is_calculate=1,agtransid="'.$ag_trans_id.'",trans_date="'.date("Y-m-d H:i:s",$time).'",trans_ccnum="'.$hideccnum.'" WHERE ptransid = :ptransid', array('ptransid' => $trans_id));
				$result = $db->query('UPDATE '.TABLE_AGTRANS.' SET agtransid="'.$ag_trans_id.'",trans_ccnum="'.$hideccnum.'" WHERE agtransid = :agtransid', array('agtransid' => $transaction['agtransid']));
				$result = $db->query('UPDATE '.TABLE_MERCHNATS.' SET amount_balance = amount_balance + ('.$transaction['trans_net'].') WHERE mid = :merchantId', array('merchantId' => $merchantId));
				
				/*插入reserve data数据*/
				$reserve_array = array(
											'ptransid' => $trans_id,
											'mid'=>$merchantId,
											'expire_date'=>($time + 86400*$merchant_info['reserve_day']),
											'reverse_amount'=>($transaction['trans_amount']*$merchant_info['reserve_fee']/100),
											'trans_invoice'=>$transaction['trans_invoice'],
											'is_calculate'=> 1
										);

				$rows_affected = $db->insert(TABLE_REVERSES, $reserve_array);
				$reserve_array = array(
											'ptransid' => $trans_id,
											'mid'=>$merchantId,
											'expire_date'=>($time + 86400*$merchant_info['y_reserve_day']),
											'reverse_amount'=>($transaction['trans_amount']*(1-$merchant_info['reserve_fee']/100)+$transaction['trans_fee']),
											'trans_invoice'=>$transaction['trans_invoice'],
											'is_calculate'=> 1
										);
				$rows_affected = $db->insert(TABLE_REVERSES, $reserve_array);

			} else {
				//Received Failer

				$trans_amount = 0;
				$trans_fee = -$merchant_info['trans_fee2'];
				$trans_net = $trans_fee;

				$result = $db->query('UPDATE '.TABLE_PTRANS.' SET transtatusid = 3,is_calculate=1,agtransid="'.$ag_trans_id.'",trans_amount="'.$trans_amount.'",trans_fee="'.$trans_fee.'",trans_net="'.$trans_net.'",trans_desc="'.$error_info.'",trans_date="'.date("Y-m-d H:i:s").'" WHERE ptransid = :ptransid', array('ptransid' => $trans_id));
				$result = $db->query('UPDATE '.TABLE_AGTRANS.' SET agtransid="'.$ag_trans_id.'" WHERE agtransid = :agtransid', array('agtransid' => $transaction['agtransid']));
				$result = $db->query('UPDATE '.TABLE_MERCHNATS.' SET amount_balance = amount_balance + ('.$trans_net.') WHERE mid = :merchantId', array('merchantId' => $merchantId));	
			}
		}

		public static function voidReceive ($merchantId,$trans_id) {
			$db = Zend_Registry::get('db');
			$result = $db->query('UPDATE '.TABLE_PTRANS.' SET transtatusid = 3,is_calculate=1,trans_amount=0,trans_fee=0,trans_net=0,trans_desc="Void" WHERE ptransid = :ptransid', array('ptransid' => $trans_id));
		}

		public static function getConfig () {
			$configs = array();
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT * '.' FROM '.TABLE_CONFIGURATIONS);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			foreach ($rows as $key => $value) {
				$configs[$value['configuration_key']] = $value['configuration_value'];
			}
			return $configs;
		}
		protected static function _validate_password($plain, $encrypted) {
			if (!empty($plain) && !empty($encrypted)) {
				$stack = explode(':', $encrypted);
				if (sizeof($stack) != 2) return false;
				if (md5($stack[1] . $plain) == $stack[0]) {
					return true;
				}
			}
			return false;
		}


		protected static function _encrypt_password($plain) {
			$password = '';

			for ($i=0; $i<10; $i++) {
			  $password .= mt_rand();
			}

			$salt = substr(md5($password), 0, 2);
			$password = md5($salt . $plain) . ':' . $salt;
			return $password;
		}

		public static function sendAgRequest($merchants,$transaction,$trans_amount,$trans_type='AUTH_CAPTURE',$trans_desc='Online process') {		
			$customer_name = explode(" ",$transaction['trans_ccowner']);
			$shipping_name = explode(" ",$transaction['shipping_name']);
			
			if ($trans_type=='AUTH_CAPTURE') {
				$trans_id = '';

				//$gateways = self::getMerchantGateway($merchants['mid']);
				$gateways = self::getTransGateway($transaction['ptransid']);
				$gateway_url = $gateways['gateway_url'];
				$agpay_login = $gateways['gateway_login'];
				$agpay_txkey = $gateways['gateway_key'];
				$credit_num = $transaction['trans_ccnum'];
			} else {
				$trans_id_array = explode("_",$transaction['agtransid']);
				$trans_id = $trans_id_array[0];

				$gateways = self::getTransGateway($transaction['ptransid']);
				$gateway_url = $gateways['gateway_url'];
				$agpay_login = $gateways['gateway_login'];
				$agpay_txkey = $gateways['gateway_key'];
				$credit_num =  substr($transaction['trans_ccnum'], -4);
			}
			$params = array(
							  'x_login' => $agpay_login,
							  'x_tran_key' => $agpay_txkey,
							  'x_version' => '3.1',
							  'x_delim_data' => 'TRUE',
							  'x_delim_char' => ',',
							  'x_encap_char' => '"',
							  'x_relay_response' => 'FALSE',
							  'x_first_name' => $customer_name[0],
							  'x_last_name' => $customer_name[1],
							  'x_address' => $transaction['customer_street'],
							  'x_city' => $transaction['customer_city'],
							  'x_state' => $transaction['customer_state'],
							  'x_zip' => $transaction['shipping_postcode'],
							  'x_country' => $transaction['customer_country'],
							  'x_phone' => $transaction['customer_telephone'],
							  'x_cust_id' => $merchants['mid'],
							  'x_email' => 'info@miccostumes.com',
							  'x_description' => $trans_desc,
							  'x_amount' => $trans_amount,
							  'x_invoice_num' => '('.$merchants['mid'].')'.$transaction['trans_invoice'],
							  'x_method' => 'CC',
							  'x_type' => $trans_type,
							  'x_trans_id' => $trans_id,
							  'x_card_num' => $credit_num,
							  'x_exp_date' => $transaction['trans_expire'],
							  'x_card_code' => $transaction['trans_cvv2'],
							  'x_customer_ip' => $transaction['customer_ip'],
							  'x_ship_to_first_name' => $shipping_name[0],
							  'x_ship_to_last_name' => $shipping_name[1],
						      'x_ship_to_address' => $transaction['shipping_street'],
							  'x_ship_to_city' => $transaction['shipping_city'],
							  'x_ship_to_state' => $transaction['shipping_state'],
							  'x_ship_to_zip' => $transaction['shipping_zip'],
				              'x_ship_to_country' => $transaction['shipping_country'],
							  'x_encrypt_key' => strtoupper(md5($agpay_login.$agpay_txkey.$transaction['trans_amount']))
							);
			
			$post_string = '';
			foreach ($params as $key => $value){
				$post_string .= $key . '=' . urlencode(trim($value)) . '&';
			}
			$post_string = substr($post_string, 0, -1);
			
			$transaction_response = self::sendTransactionToGateway($gateway_url, $post_string);
			return $transaction_response;
		}

		public static function getMerchantGateway($merchantId) {
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT g.* FROM '.TABLE_GATEWAYS.' g, '.TABLE_MERCHNATS.' m WHERE m.gatewayid = g.gatewayid and m.mid =:merchantId');
			$stmt->bindValue('merchantId',$merchantId);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			return $rows[0];
		}

		public static function getTransGateway($trans_id) {
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT g.* FROM '.TABLE_GATEWAYS.' g, '.TABLE_PTRANS.' p WHERE p.gatewayid = g.gatewayid and p.ptransid =:trans_id');
			$stmt->bindValue('trans_id',$trans_id);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			return $rows[0];
		}

		static public function getmailtemplate($tpl_id){
			$config=Zend_Registry::get('config');
			$subject='';$Tpl='';
			$mailTpls = new Zend_Config_Xml(APPLICATION_PATH.$config->mail->templatepath.'/config.xml');
			$mailTplPath=APPLICATION_PATH.$config->mail->templatepath;
			
			eval('$tplset=isset($mailTpls->'.$tpl_id.')?$mailTpls->'.$tpl_id.':"";');
			if(isset($tplset)&&$tplset){
				$TplFile=APPLICATION_PATH.$config->mail->templatepath.'/'.$tplset->filename;
			}else{
				$TplFile=APPLICATION_PATH.$config->mail->templatepath.'/'.$tpl_id.'.html';
			}
			if(file_exists($TplFile)){
						
				$Tpl=file_get_contents($TplFile);
				if(preg_match('/\[\:title\=(.*?)\:\]/',$Tpl,$m)){$subject=$m[1];}
				elseif(isset($tplset->subject)){
					$subject=$tplset->subject;
				}
				$Tpl=preg_replace('/\[\:title\=(.*?)\:\]/','',$Tpl);
				return array('subject'=>$subject,'body'=>$Tpl);
			}
			return ;
		}

		public static function sendTransactionToGateway($url, $parameters) {
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

		public static function sendMail ($to_name, $to_email_address, $email_subject, $email_text, $cc_name='',$from_email_name='', $from_email_address='') {
			$scnsp = Zend_Registry::get('scnsp');
			$mail_config = $scnsp->mail_parameter;

			if ($from_email_name== '') $from_email_name= $mail_config['sendname'];
			if ($from_email_address== '') $from_email_address= $mail_config['sendby'];

			if ($mail_config['ssl'] == 'ssl') {
				$config = array('ssl' => 'ssl',
								'port' => $mail_config['port'],
								'auth' => 'login',
								'username' => $mail_config['username'],
								'password' => $mail_config['password']);
			} else {
				$config = array(
								'auth' => 'login',
								'username' => $mail_config['username'],
								'password' => $mail_config['password']);
			}

			$transport = new Zend_Mail_Transport_Smtp($mail_config['server'], $config);
			try{
			$mail = new Zend_Mail('utf-8');
			$mail->setBodyHtml($email_text);
			$mail->setReturnPath($from_email_address, $from_email_name);
			$mail->setFrom($mail_config['username'], $from_email_name);
			$mail->addTo($to_email_address, $to_name);
			$cc_array = explode(';',$cc_name);
			for ($i=0;$i<sizeof($cc_array);$i++){
				if ($cc_array[$i]!='' && !empty($cc_array[$i])) {
					$mail->addBcc($cc_array[$i]);
				}
			}
			$mail->setSubject($email_subject);
			$mail->send($transport);
			return true;
			}catch(Exception $e){
				return false;
			}
		}
		
		public static function getNewagtrans($transaction,$fields='agt.trans_ccowner,agt.trans_ccnum,agt.trans_cvv2,agt.trans_expire'){
			$db =Zend_Registry::get('db');
			$where ="pt.trans_invoice='".$transaction['trans_invoice']."' and pt.mid=".$transaction['mid']." and agt.agtransid=pt.agtransid";
			$result=$db->query("select $fields from ".TABLE_AGTRANS." as agt,".TABLE_PTRANS." as pt where $where order by agt.trans_date desc limit 1");
			$rows = $result->fetchAll();
			if(sizeof($rows)>0){
				return $rows[0];
			}
			return ;
		}
		
		public static function getrefundlogs($params,$numPerPage=20){
			$where='';$limit='';
		
			if(isset($params['begain'])&&$params['begain']!=''){
				$where .=' and optime>='.strtotime($params['begain']);
			}

			if(isset($params['to'])&&$params['to']!=''){
				$where .=' and optime<='.strtotime($params['to']);
			}

			if(isset($params['inv_no'])&&$params['inv_no']!=''){
				$where .=' and refound.invoice="'.$params['inv_no'].'"';
			}

			if(isset($params['customer'])&&$params['customer']){
				if(is_numeric($params['customer'])){
					$where .=' and m_id='.$params['customer'];
				}else{
					$where .=' and merchant_name="'.$params['customer'].'"';
				}
			}

			if(isset($params['transid'])&&is_numeric($params['transid'])){
				$where .=' and trans_id='.$params['transid'];
			}

			if($where){$where=' where '.substr($where,4);}

			$order=' order by optime desc';

			$params['page']= isset($params['page'])&&$params['page']>0?$params['page']:1;
			//$numPerPage=20;
			$limit=' limit '.$numPerPage*($params['page']-1).','.$numPerPage;
			//only show latest 1000
			$limit = ' limit 1000';

			$sql="select refound.*,merchants.*,pt.*,u.user_name from ".TABLE_REFOUNDLOG." as refound inner join ".TABLE_MERCHNATS.' as merchants
			 on(refound.m_id=merchants.mid) 
			 inner join '.TABLE_USERS.' as u on(refound.user_id=u.userid)
			 inner join '.TABLE_PTRANS.' as pt on(refound.trans_id=pt.ptransid)'.$where.$order.$limit;

			$db = Zend_Registry::get('db');
			$rs=$db->Query($sql);
			return $rs->fetchAll();
		}
	}
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
								'merchant_number' => $info['merchantnum'],
								'merchant_key' => $info['merchant_key'],
								'gatewayid' => $info['gatewayid'],
								'open_date' => date("Y-m-d H:i:s"),
								'password' => self::_encrypt_password($info['password']),
								'street' => $info['street'],
								'city' => $info['city'],
								'state' => $info['state'],
								'country' => $info['country'],
								'postcode' => $info['postcode'],
								'phone' => $info['phone'],
								'fax' => $info['fax'],
								'mstatusid' => 1
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
		
		public static function getReport2Data($request){
			$db = Zend_Registry::get('db');
			
			if($request['begin_date']!=''){
				$where .=$db->quoteInto(" and pt.trans_date>=?",$request['begin_date']);
			}
			if($request['end_date']!=''){
				$where .=$db->quoteInto(" and pt.trans_date<=?",$request['end_date']);
			}
			if($request['merchants']!=""){
				$request['merchants']=explode(' ',str_replace(',',' ',$request['merchants']));
				foreach($request['merchants'] as $Merchant){
					if(is_numeric($Merchant)){
						$tempWhere.=($tempWhere?' or ':'').' m.mid='.$Merchant;
					}else{
						$tempWhere.=($tempWhere?' or ':'').$db->quoteInto(' m.merchant_name =?',$Merchant);
					}
				}
				if($tempWhere){$where.=" and (".$tempWhere.')';}
			}
			
			if(in_array('Received',$request['showline'])){
				//received amount
				$TempReport = $db->fetchAll("select sum(trans_amount) as amount,count(*) as count,DATE_FORMAT(trans_date,'%m/%d/%Y') as trans_date
				from ".TABLE_PTRANS." as pt,".TABLE_MERCHNATS." as m 
				where m.mid=pt.mid and pt.transtypeid=1 and pt.transtatusid=2 and is_calculate = 1".$where." group by DATE_FORMAT(trans_date,'%Y%m%d')");
				$Report['merchant_received']['label']='Received';
				foreach($TempReport as $datepoint){
					$Report['merchant_received']['data'][$datepoint['trans_date']]=$datepoint;
				}
			}
			
			if(in_array('Refund',$request['showline'])){
				//received amount
				$TempReport = $db->fetchAll("select sum(trans_amount) as amount,sum(trans_fee) as trans_fee,
				sum(trans_net) as trans_net,count(*) as refundcount,DATE_FORMAT(pt.trans_date,'%m/%d/%Y') as trans_date
				 from ".TABLE_PTRANS." as pt,".TABLE_MERCHNATS." as m 
				where m.mid=pt.mid and pt.transtypeid=8 and pt.transtatusid=2 and is_calculate = 1".$where." group by DATE_FORMAT(trans_date,'%Y%m%d')");
				$Report['merchant_refund']['label']='Refund';
				foreach($TempReport as $datepoint){
					$Report['merchant_refund']['data'][$datepoint['trans_date']]=$datepoint;
				}
			}
			
			if(in_array('Net Fee',$request['showline'])){
				 //net sum
				 $TempReport = $db->fetchAll("select sum(trans_amount),sum(trans_fee) as amount
				 ,DATE_FORMAT(pt.trans_date,'%m/%d/%Y') as trans_date  
				 from ".TABLE_PTRANS." as pt,".TABLE_MERCHNATS." as m where m.mid=pt.mid and is_calculate = 1".$where." group by DATE_FORMAT(pt.trans_date,'%Y%m%d')");
				 $Report['net']['label']='Net Fee';
				 foreach($TempReport as $datepoint){
					$Report['net']['data'][$datepoint['trans_date']]=$datepoint;
				 }
			}
			return $Report;
		}
		
		public static function getReport1Data($beginDate,$endDate,$Merchants){
			
			$db = Zend_Registry::get('db');
			
			if($beginDate!=''){
				$where .=$db->quoteInto(" and pt.trans_date>=?",$beginDate);
			}
			if($endDate!=''){
				$where .=$db->quoteInto(" and pt.trans_date<=?",$endDate);
			}
			if($Merchants!=""){
				$Merchants=explode(' ',str_replace(',',' ',$Merchants));
				foreach($Merchants as $Merchant){
					if(is_numeric($Merchant)){
						$tempWhere.=($tempWhere?' or ':'').' m.mid='.$Merchant;
					}else{
						$tempWhere.=($tempWhere?' or ':'').$db->quoteInto(' m.merchant_name =?',$Merchant);
					}
				}
				if($tempWhere){$where.=" and (".$tempWhere.')';}
			}
			
			$Report['merchant_count']=$db->fetchAll("select count(*) as merchant_count,ms.mstatus_name from ".TABLE_MERCHNATS." as m,
			".TABLE_MERCHANTSTATUS." as ms where m.mstatusid=ms.mstatusid".str_replace('pt.trans_date','m.open_date',$where)."
			 group by m.mstatusid");
			
			//received amount
			$Report['merchant_received'] = $db->fetchRow("select sum(trans_amount) as trans_amount,count(*) as receivedcount,sum(trans_net) as trans_net  
			from ".TABLE_PTRANS." as pt,".TABLE_MERCHNATS." as m 
			where m.mid=pt.mid and pt.transtypeid=1 and pt.transtatusid=2 and is_calculate = 1".$where);
			
			//Locked amount
			$Report['merchant_locked'] = $db->fetchRow("select sum(trans_amount) as trans_amount,count(*) as lockedcount
			 from ".TABLE_PTRANS." as pt,".TABLE_MERCHNATS." as m,".TABLE_PTRANS_LOCK." as ptl where
			  m.mid=pt.mid and pt.ptransid=ptl.ptransid and m.mid=pt.mid 
			 ".$where);
			
			//Refund amount
			$Report['merchant_refund'] = $db->fetchRow("select sum(trans_amount) as trans_amount,sum(trans_fee) as trans_fee,
			sum(trans_net) as trans_net,count(*) as refundcount from ".TABLE_PTRANS." as pt,".TABLE_MERCHNATS." as m 
			where m.mid=pt.mid and pt.transtypeid=8 and pt.transtatusid=2 and is_calculate = 1".$where);	
			
			//pending amount
			$Report['merchant_pending'] = $db->fetchRow("select sum(trans_amount) as trans_amount,sum(trans_fee) as trans_fee,
			sum(trans_net) as trans_net,count(*) as pendingcount from ".TABLE_PTRANS." as pt,".TABLE_MERCHNATS." as m where m.mid=pt.mid and 
			pt.transtypeid=1 and pt.transtatusid=1".$where);
			
			//rejected count
			$Report['rejected'] = $db->fetchOne("select count(*) as cnt from ".TABLE_PTRANS." as pt ,".TABLE_MERCHNATS." as m
			 where m.mid=pt.mid and transtatusid=3".$where);
			 
			 //net amount
			 $Report['net']['trans_amount'] = $db->fetchOne("select sum(trans_net) as trans_net 
			 from ".TABLE_PTRANS." as pt,".TABLE_MERCHNATS." as m where m.mid=pt.mid and pt.transtypeid!=2 and pt.transtypeid!=5 and pt.transtypeid!=7 and is_calculate = 1".$where);
			 //net sum
			 $Report['net']['trans_fee'] = $db->fetchOne("select sum(trans_fee) as trans_fee 
			 from ".TABLE_PTRANS." as pt,".TABLE_MERCHNATS." as m where m.mid=pt.mid and is_calculate = 1".$where);
			 
			 //reverse
			 $Report['reverse']=$db->fetchRow('SELECT sum(r.reverse_amount) as reverse_amount from '.TABLE_REVERSES." as r,
			 ".TABLE_PTRANS." as pt ,".TABLE_MERCHNATS." as m where m.mid=pt.mid and r.ptransid=pt.ptransid
			  and r.is_calculate =1 and r.expire_date >=".time().$where);
					
			return $Report;
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
		
		public static function shippingmethods(){
			$db = Zend_Registry::get('db');
			$stmt =$db->prepare('SELECT * FROM '.TABLE_SHIPPING_M.' order by sort');
			$stmt ->execute();
			return $stmt->fetchAll();
		}

		public static function merchantLogin ($name,$pass) {
			$merchant_id = 0;
			if (empty($name) || empty($pass)) return $merchant_id;
			$db = Zend_Registry::get('db');

			$stmt = $db->prepare('SELECT mid,password FROM '.TABLE_MERCHNATS.' WHERE merchant_name = :merchant_name limit 1');

			$stmt->bindValue('merchant_name',$name);
			$stmt->execute();
			
			$rows = $stmt->fetchAll();
			if (sizeof($rows)) {
				$rtn = self::_validate_password($pass,$rows[0]['password']);
				if ($rtn) $merchant_id = $rows[0]['mid'];
			}

			return $merchant_id;
		}
		
		//锁定和解锁某笔交易的金额,如果$lock大于0为锁定，0为解锁。
		public static function lockOptransaction($mid,$ptransid,$amount,$info=array()){
			
			$db = Zend_Registry::get('db');
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			
			$transaction = self::getTransDetail($mid,$ptransid);
			
			if($amount>0){
				
				$row=array(
					'ptransid' => $ptransid,
					'mid' => $mid,
					'agtransid' => $transaction['agtransid'],
					'lastlockdate' => mktime(),
					'comment' =>$info['EmailNotice'],
					'amount' => $amount,
					'userid' => $user_id,
					'locked' => '1'
				);
				$db->insert(TABLE_PTRANS_LOCK, $row);
				
			}else{
				$db->delete(TABLE_PTRANS_LOCK, 'ptransid='.$ptransid);
			}
			
			return true;
		}
		
		//获取锁定状态,大于0为被锁定金额
		public static function getTransactionLocked($ptransid){
				
				$db = Zend_Registry::get('db');
				
				$statement=$db->query("select sum(amount) as cnt from ".TABLE_PTRANS_LOCK." where ptransid=".$ptransid);
				$statement->setFetchMode(Zend_Db::FETCH_ASSOC);
				$row=$statement->fetch();
				return (float)$row['cnt'];
				
		}
		
		//获取锁定信息
		public static function getTransactionLockinfo($ptransid){
				$db = Zend_Registry::get('db');
				
				$statement=$db->query("select * from ".TABLE_PTRANS_LOCK." where ptransid=".$ptransid);
				$statement->setFetchMode(Zend_Db::FETCH_ASSOC);
				return $statement->fetch();
		}

		public static function getMerchantInfo($merchantId) {
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
			$stmt = $db->prepare('SELECT m.*, tp.transtype_name, ts.transtatus_name'.
				                 ' FROM '.TABLE_PTRANS.' m, '.TABLE_TRANSTYPES.' tp, '.TABLE_TRANSTATUS.' ts '.
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

		public static function getAllTransaction ($merchantId,$strWhere='') {
			$transaction = array();
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT m.*, tp.transtype_name, ts.transtatus_name,tpl.ptransid as locked'.
				                 ' FROM '.TABLE_TRANSTYPES.' tp, '.TABLE_TRANSTATUS.'  ts,'.TABLE_PTRANS.' m 
								 left join '.TABLE_PTRANS_LOCK.' tpl on (m.ptransid=tpl.ptransid)'.
								 ' WHERE m.transtypeid = tp.transtypeid'.
				                 '   AND m.transtatusid = ts.transtatusid'.
								 '	 AND m.mid = :merchantId'.$strWhere.
								 '   ORDER BY m.trans_date desc,m.ptransid desc');

			$stmt->bindValue('merchantId',$merchantId);
			$stmt->execute();
			$transaction = $stmt->fetchAll();
			return $transaction;
		}

		public static function getAllWithdraw() {
			$transaction = array();
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT pt.*,ts.transtatus_name'.
				                 ' FROM '.TABLE_PTRANS.' pt,'.TABLE_TRANSTATUS.' ts'.
								 ' WHERE pt.transtypeid = 2'.
				                 '   AND pt.transtatusid = ts.transtatusid'.
								 '   ORDER BY pt.trans_date desc');
			$stmt->execute();
			$rows = $stmt->fetchAll();
			
			for ($i=0;$i<sizeof($rows);$i++){
				$merchant_info = self::getMerchantInfo($rows[$i]['mid']);
				
				$transaction[] = array(
										'ptransid'=>$rows[$i]['ptransid'],
										'mid'=>$rows[$i]['mid'],
										'transtypeid'=>$rows[$i]['transtypeid'],
										'transtatusid'=>$rows[$i]['transtatusid'],
										'trans_date'=>$rows[$i]['trans_date'],
										'trans_date'=>$rows[$i]['trans_date'],
										'withdraw_amount'=>-$rows[$i]['trans_net'],
										'withdraw_fee'=>-$rows[$i]['trans_fee'],
										'apply_withdraw'=>-$rows[$i]['trans_amount'],
										'withdraw_desc'=>$rows[$i]['trans_desc'],
										'withdraw_status'=>$rows[$i]['transtatus_name'],
										'merchant_name'=>$merchant_info['merchant_name'],
										'merchant_status'=>$merchant_info['mstatus_name'],
										'amount_balance'=>$merchant_info['amount_balance'],
										'amount_locked'=>$merchant_info['amount_locked'],
										'amount_reserve'=>$merchant_info['amount_reserve'],
										'withdraw_limit'=>$merchant_info['withdraw_limit']
										);
			}
			return $transaction;
		}

		public static function getTransDetail($merchantId,$trans_id) {
			$transaction = array();
			$db = Zend_Registry::get('db');
		
			$stmt = $db->prepare('SELECT pt.*,ag.agtransid,ag.trans_invoice,ag.trans_desc as ag_desc,ag.trans_ccowner,ag.trans_ccnum,ag.trans_cvv2,ag.trans_expire,ag.shipping_name,ag.shipping_street,ag.shipping_city,ag.shipping_state,ag.shipping_country,ag.shipping_postcode,ag.shipping_zip,ag.customer_email,ag.customer_street,ag.customer_city,ag.customer_state,ag.customer_country,ag.customer_telephone,ag.customer_ip,ag.trans_date as ag_date, ag.trans_amount as ag_amount, tp.transtype_name, ts.transtatus_name'.
								  ' FROM '.TABLE_TRANSTYPES.' tp,'.TABLE_TRANSTATUS.' ts,'.TABLE_PTRANS.' pt'.
								  ' LEFT OUTER JOIN '.TABLE_AGTRANS.' ag on (pt.agtransid = ag.agtransid)'.
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

		public static function getWithdrawDetail($merchantId,$trans_id) {
			$transaction = array();
			$db = Zend_Registry::get('db');
		
			$stmt = $db->prepare('SELECT pt.*, tp.transtype_name, ts.transtatus_name'.
								  ' FROM '.TABLE_TRANSTYPES.' tp,'.TABLE_TRANSTATUS.' ts,'.TABLE_PTRANS.' pt'.
				                  ' WHERE pt.mid = :merchantId'.
								  '   AND pt.ptransid=:transId'.
								  '   AND pt.transtypeid = tp.transtypeid'.
								  '   AND pt.transtypeid = 2'.
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

		public static function changeInfo ($merchantId,$values=array()) {
			$rows_affected = 0;
			$db = Zend_Registry::get('db');
			$set = array(
						'merchant_name' => $values['merchantname'],
						'mstatusid' => $values['merchantstatus'],
						'gatewayid' => $values['gatewayid'],
						'merchant_number' => $values['merchantnum'],
						'merchant_key' => $values['merchant_key'],
						'merchant_email' => $values['emailaddress'],
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
				//$rtn = self::_validate_password($values['oldpassword'],$rows[0]['password']);
				$rtn = true;
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

			if (strlen($values['cc_num'])>8 && is_numeric($values['cc_num'])) {
				$set['cc_number'] = $values['cc_num'];
			}
			
			$where = $db->quoteInto('is_default=1 and mid = ?', $merchantId);

			$rows_affected = $db->update(TABLE_MERCHNAT_ACCOUNTS,$set, $where);
			return $rows_affected;
		}

		public static function setFee ($merchantId,$values=array()) {
			$rows_affected = 0;
			$db = Zend_Registry::get('db');
			$set = array(
						'trans_fee1' => $values['x_trans'],
						'trans_fee2' => $values['x_fee'],
						'month_fee' => $values['x_month'],
						'reserve_fee' => $values['x_reserve'],
						'reserve_day' => $values['x_days'],
						'y_reserve_day' => $values['y_reserve_day'],
						'setup_fee' => $values['x_setup'],
						'chargeback' => $values['x_charge'],
						'withdraw' => $values['x_withdraw_fee'],
						'withdraw_limit' => $values['x_withdraw_limit'],
						'month_limit' => $values['x_monthlimit']
						);

			$where = $db->quoteInto('mid = ?', $merchantId);

			$rows_affected = $db->update(TABLE_MERCHNATS,$set, $where);
			return $rows_affected;
		}

		public static function setCharge ($mid,$transid,$chargecomment='') {
			$ptrans = self::getTransDetail($mid,$transid);
			if (sizeof($ptrans) > 0) {
				//查找所有该笔交易的refund记录
				$db = Zend_Registry::get('db');
				$stmt = $db->prepare('SELECT sum(trans_amount) as trans_amount'.
								  ' FROM '.TABLE_PTRANS.
				                  ' WHERE transtypeid = 8'.
								  '   AND transtatusid = 2'.
								  '   AND agtransid = :agtransid');
				$stmt->bindValue('agtransid',$ptrans['agtransid']);
				$stmt->execute();
				$rows = $stmt->fetchAll();
				if (sizeof($rows)) {
					$refund_amount = $rows[0]['trans_amount'];
				} else {
					$refund_amount = 0;
				}

				if ($ptrans['trans_amount'] + $refund_amount > 0) {
					$refund_balance = -($ptrans['trans_amount'] + $refund_amount);
					$merchant_info = self::getMerchantInfo($mid);
					
					$refund_fee = - $merchant_info['trans_fee2'] - $refund_balance * $merchant_info['trans_fee1']/100 ;
					$refund_net = round($refund_balance + $refund_fee,2);
					

					if ($merchant_info['amount_balance']+$refund_net < ($merchant_info['chargeback'] + $merchant_info['amount_reserve'] + $merchant_info['amount_locked'])) {
						$result = $db->query('UPDATE '.TABLE_MERCHNATS.' SET mstatusid=3 WHERE mid = :merchantId', array('merchantId' => $mid));
						$return = array(0,'Balance is not enough to pay for charge!');
					} else {
						$row = array (
									'mid'=>$mid,
									'transtypeid'=>8,
									'transtatusid'=>2,
									'trans_date'=>date('Y-m-d H:i:s'),
									'trans_amount'=>$refund_balance,
									'trans_fee'=>$refund_fee,
									'trans_net'=>$refund_net,
									'trans_invoice'=>$ptrans['trans_invoice'],
									'trans_desc'=>'Charge Back for:'.$chargecomment,
									'trans_ccowner'=>$ptrans['trans_ccowner'],
									'trans_ccnum'=>$ptrans['trans_ccnum'],
									'agtransid'=>$ptrans['agtransid'],
									'is_calculate'=>1
								 );
						$rows_affected = $db->insert(TABLE_PTRANS, $row);
						$maccid = $db->lastInsertId();
						if ($maccid > 0) {
							$row = array (
									'mid'=>$mid,
									'transtypeid'=>4,
									'transtatusid'=>2,
									'trans_date'=>date('Y-m-d H:i:s'),
									'trans_amount'=>0,
									'trans_fee'=>-$merchant_info['chargeback'],
									'trans_net'=>-$merchant_info['chargeback'],
									'trans_invoice'=>$ptrans['trans_invoice'],
									'trans_desc'=>'Charge Back Fee for:'.$chargecomment,
									'trans_ccowner'=>$ptrans['trans_ccowner'],
									'trans_ccnum'=>$ptrans['trans_ccnum'],
									'agtransid'=>$ptrans['agtransid'],
									'is_calculate'=>1
								 );
							$rows_affected = $db->insert(TABLE_PTRANS, $row);
							$maccid = $db->lastInsertId();
							if ($maccid > 0) {
								$result = $db->query('UPDATE '.TABLE_MERCHNATS.' SET amount_balance = amount_balance + ('.($refund_net -$merchant_info['chargeback']).') WHERE mid = :merchantId', array('merchantId' => $mid));

								/*插入reserve data数据*/
								$reserve_array = array(
													'ptransid' => $transid,
													'mid'=>$mid,
													'expire_date'=>(strtotime($ptrans['trans_date']) + 86400*$merchant_info['reserve_day']),
													'reverse_amount'=>($refund_balance*$merchant_info['reserve_fee']/100),
													'trans_invoice'=>$ptrans['trans_invoice'],
													'is_calculate'=> 1
												);

								$rows_affected = $db->insert(TABLE_REVERSES, $reserve_array);
								$reserve_array = array(
													'ptransid' => $transid,
													'mid'=>$mid,
													'expire_date'=>(strtotime($ptrans['trans_date']) + 86400*$merchant_info['y_reserve_day']),
													'reverse_amount'=>($refund_balance*(1-$merchant_info['reserve_fee']/100)+$refund_fee),
													'trans_invoice'=>$ptrans['trans_invoice'],
													'is_calculate'=> 1
												);
								$rows_affected = $db->insert(TABLE_REVERSES, $reserve_array);
								$return = array($mid,'Charge Success!');
							}
						} else {
							$return = array(0,'Data processing business failure!');
						}
					}

				} else {
					$return = array(0,'This transaction has been refunded or charged back!');
				}
			} else {
				$return = array(0,'Receive transaction is not found!');
			}
			
			return $return;
		}

		public static function monthCharge($emailaddress,$collecttype=5,$paymentamount,$chargecomment='') {
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT *'.
								  ' FROM '.TABLE_MERCHNATS.
				                  ' WHERE merchant_email = :emailaddress');
			$stmt->bindValue('emailaddress',$emailaddress);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			if (sizeof($rows)) {
				$merchant_info = self::getMerchantInfo($rows[0]['mid']);
				if ($collecttype == 5) {
					$trans_amount = $merchant_info['month_fee'];
					$invoice = 'Month Fee';
				} else {
					$trans_amount = $paymentamount;
					$invoice = 'Other';
				}
				if ($merchant_info['amount_balance'] < ($trans_amount + $merchant_info['amount_reserve'] + $merchant_info['amount_locked'])) {
					$result = $db->query('UPDATE '.TABLE_MERCHNATS.' SET mstatusid=3 WHERE mid = :merchantId', array('merchantId' => $merchant_info['mid']));
					$return = array(0,'Balance is not enough to pay for charge!');
					return $return;
				} else {
					$trans_amount = -$trans_amount;
					$trans_fee = 0;
					$trans_net = $trans_amount + $trans_fee;
					$row = array (
									'mid'=>$merchant_info['mid'],
									'transtypeid'=>$collecttype,
									'transtatusid'=>2,
									'trans_date'=>date('Y-m-d H:i:s'),
									'trans_amount'=>$trans_amount,
									'trans_fee'=>$trans_fee,
									'trans_net'=>$trans_net,
									'trans_invoice'=>$invoice,
									'trans_desc'=>$chargecomment,
									'trans_ccowner'=>$merchant_info['merchant_name'],
									'trans_ccnum'=>'XXXX',
									'agtransid'=>0,
									'is_calculate'=>1
								 );
					$rows_affected = $db->insert(TABLE_PTRANS, $row);
					$maccid = $db->lastInsertId();
					if ($maccid >0) {
						$result = $db->query('UPDATE '.TABLE_MERCHNATS.' SET amount_balance = amount_balance + ('.$trans_net.'),last_charge="'.date('Y-m-d H:i:s').'" WHERE mid = :merchantId', array('merchantId' => $merchant_info['mid']));
						$return = array($merchant_info['mid'],'Charge Success!');
						return $return;
					} else {
						$return = array(0,'Data processing business failure!');
						return $return;
					}
				}
			} else {
				$return = array(0,'The merchant does not exist!');
				return $return;
			}
		}

		public static function changeStatus ($merchantId,$trans_id,$newStatus) {
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT pt.*'.
								  ' FROM '.TABLE_PTRANS.' pt'.
				                  ' WHERE pt.mid = :merchantId'.
								  '   AND pt.ptransid=:transId');
			$stmt->bindValue('merchantId',$merchantId);
			$stmt->bindValue('transId',$trans_id);
			$stmt->execute();
			
			$rows = $stmt->fetchAll();
			if (sizeof($rows)) {
				$transaction = $rows[0];
				if ($newStatus == 3) {
					//Reject
					$result = $db->query('UPDATE '.TABLE_PTRANS.' SET transtatusid = '.$newStatus.',is_calculate=0 WHERE mid = :merchantId and ptransid = :transId', array('merchantId' => $merchantId,'transId'=>$trans_id));

					$result = $db->query('UPDATE '.TABLE_MERCHNATS.' SET amount_balance = amount_balance - ('.$transaction['trans_net'].') WHERE mid = :merchantId', array('merchantId' => $merchantId));
				} else {
					//Success
					$result = $db->query('UPDATE '.TABLE_PTRANS.' SET transtatusid = '.$newStatus.' WHERE mid = :merchantId and ptransid = :transId', array('merchantId' => $merchantId,'transId'=>$trans_id));
				}
			}
		}

		public static function resetBillingInfo($merchantId,$trans_id,$values=array()){
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT pt.*'.
								  ' FROM '.TABLE_PTRANS.' pt'.
				                  ' WHERE pt.mid = :merchantId'.
								  '   AND pt.ptransid=:transId');
			$stmt->bindValue('merchantId',$merchantId);
			$stmt->bindValue('transId',$trans_id);
			$stmt->execute();
			
			$rows = $stmt->fetchAll();
			
			if (sizeof($rows)) {
				$transaction = $rows[0];
				$set = array(
							'trans_ccowner' => $values['trans_ccowner'],
							'trans_ccnum' => $values['trans_ccnum'],
							'gatewayid'=> $values['gatewayid'],
							);

				$where = $db->quoteInto('ptransid = ?', $trans_id);
				$rows_affected = $db->update(TABLE_PTRANS,$set, $where);


				$set = array(
							'trans_ccowner' => $values['trans_ccowner'],
							'trans_ccnum' => $values['trans_ccnum'],
							'trans_cvv2' => $values['trans_cvv2'],
							'trans_expire' => $values['trans_expire'],
							'customer_street' => $values['customer_street'],
							'customer_city' => $values['customer_city'],
							'customer_state' => $values['customer_state'],
							'customer_country' => $values['customer_country'],
							'shipping_postcode' => $values['shipping_postcode'],
							'trans_amount'=>$values['ag_amount'],
							);
							
				$where = $db->quoteInto('agtransid = ?', $transaction['agtransid']);
				$rows_affected = $db->update(TABLE_AGTRANS,$set, $where);
			}
		}

		public static function setPending ($merchantId,$trans_id,$newStatus) {
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT pt.transtatusid,pt.transtypeid,pt.trans_fee,ag.trans_amount,ag.agtransid,ag.trans_invoice,m.trans_fee1,m.trans_fee2'.
								  ' FROM '.TABLE_PTRANS.' pt, '.TABLE_AGTRANS.' ag, '.TABLE_MERCHNATS.' m '.
				                  ' WHERE pt.mid = :merchantId'.
								  '	  AND pt.mid = m.mid'.
								  '   AND pt.ptransid=:transId'.
								  '	  AND pt.agtransid = ag.agtransid');
			$stmt->bindValue('merchantId',$merchantId);
			$stmt->bindValue('transId',$trans_id);
			$stmt->execute();
			
			$rows = $stmt->fetchAll();
			if (sizeof($rows)) {
				$transaction = $rows[0];
				if ($newStatus == 1 && $transaction['transtatusid']== 3 && $transaction['transtypeid'] == 1) {
					$inv_no = $transaction['trans_invoice'];
					//$inv_no = explode('(',$inv_no);
					$pos = strpos($inv_no,'(');
					if ($pos === false) {
						$inv_no .= '(1)';
					} else {
						$inv_no_left = substr($inv_no,0,$pos);
						$inv_no_right = substr($inv_no,$pos+1); 
						$inv_no_right = str_replace(')','',$inv_no_right);
						$inv_no_right += 1;
						$inv_no = $inv_no_left.'('.$inv_no_right.')';
					}

					$trans_amount = $transaction['trans_amount'];
					$trans_fee = $trans_amount * $transaction['trans_fee1']/100 + $transaction['trans_fee2'];
					$trans_fee = - $trans_fee;
					$trans_net = $trans_amount + $trans_fee;
					//Reject
					$result = $db->query('UPDATE '.TABLE_PTRANS.' SET transtatusid = 1,trans_amount="'.$trans_amount.'",trans_fee="'.$trans_fee.'",trans_net="'.$trans_net.'",trans_invoice="'.$inv_no.'",trans_desc="Ret to pending" WHERE mid = :merchantId and ptransid = :transId', array('merchantId' => $merchantId,'transId'=>$trans_id));

					$result = $db->query('UPDATE '.TABLE_AGTRANS.' SET trans_invoice="'.$inv_no.'" WHERE agtransid=:agtransId',array('agtransId'=>$transaction['agtransid']));

					$result = $db->query('UPDATE '.TABLE_MERCHNATS.' SET amount_balance = amount_balance - '.$transaction['trans_fee'].' WHERE mid = :merchantId', array('merchantId' => $merchantId));
				}
			}
		}

		public static function getAllMerchants ($strWhere='') {
			$merchantList = array();
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT mid from '.TABLE_MERCHNATS.' WHERE 1=1 '.$strWhere.' order by mid asc');

			$stmt->execute();
			$rows = $stmt->fetchAll();
			for ($i=0;$i<sizeof($rows);$i++){
				$merchantList[] = self::getMerchantInfo($rows[$i]['mid']);
			}
			
			return $merchantList;
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

		public static function autoReceive ($merchantId,$trans_id,$ag_trans_id,$result_receive=0,$error_info='') {
			$db = Zend_Registry::get('db');
			$merchant_info = self::getMerchantInfo($merchantId);
			$transaction = self::getTransDetail($merchantId,$trans_id);
			if ($result_receive > 0) {
				//Received Success
				$hideccnum=str_repeat('*',strlen($transaction['trans_ccnum'])-4).substr($transaction['trans_ccnum'],-4);
				
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
				$refund_net = round($refund_amount + $refund_fee,2);
				
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
						// write a refund log by jason
						$scnsp = Zend_Registry::get('scnsp');
						$user_id = $scnsp->user_id;
						$rowlog = array(
							'trans_id' => $trans_id,
							'user_id' => $user_id,
							'm_id' => $merchantId,
							'invoice' =>$transaction['trans_invoice'],
							'amount' =>$refund_amount,
							'optime' => time()
						);
						
						self::writeRefundLog($rowlog);
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

		public static function writeRefundLog($row){
			$db = Zend_Registry::get('db');
			$db->insert(TABLE_REFOUNDLOG, $row);
			return $db->lastInsertId();
		}

		public static function sendAgRequest($merchants,$transaction,$trans_amount,$trans_type='AUTH_CAPTURE',$trans_desc='Online process') {
			$customer_name = explode(" ",$transaction['trans_ccowner']);
			$shipping_name = explode(" ",$transaction['shipping_name']);
			
			if ($trans_type=='AUTH_CAPTURE') {
				$trans_id = '';

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

		public static function getGatewayList() {
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT * '.' FROM '.TABLE_GATEWAYS);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			return $rows;
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

			try{
			$transport = new Zend_Mail_Transport_Smtp($mail_config['server'], $config);

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
		
		//保存用户角色信息，返回影响行，最后插入id数组
		static public function saveRole($Roleinfo){
			$db = Zend_Registry::get('db');
			
			if($row=$db->fetchRow("select * from ".TABLE_USER_ROLE." where rolename=:name and roleid!=".intval($Roleinfo['roleid']),array('name'=>$Roleinfo['rolename']))){
				return array('errmsg'=>'A role with this name already exists','affected'=>0);
			}
			$row=array(
				'rolename'=>$Roleinfo['rolename'],
				'intro'=>$Roleinfo['intro']?$Roleinfo['intro']:'',
				'inherit'=>serialize($Roleinfo['inherit']?$Roleinfo['inherit']:''),
				'purview'=>serialize($Roleinfo['purview']?$Roleinfo['purview']:'')
			);
			if($Roleinfo['roleid']){
				$where = $db->quoteInto('roleid = ?', intval($Roleinfo['roleid']));
				
				return array('affected'=>$db->update(TABLE_USER_ROLE,$row,$where));
				
			}else{
				return array('affected'=>$db->insert(TABLE_USER_ROLE,$row),'insertid'=>$db->lastInsertId());
			}
		}
		//获取角色，数组
		static public function getAllRole(){
			$db = Zend_Registry::get('db');
			return $db->fetchAll("select * from ".TABLE_USER_ROLE." order by roleid");
		}
		//删除角色
		static public function deleteRole($where){
			$db = Zend_Registry::get('db');
			return $db->delete(TABLE_USER_ROLE,$where);
		}
		
		//取得角色信息
		static public function getRoleinfo($id){
			$db = Zend_Registry::get('db');
			return $db->fetchRow("select * from ".TABLE_USER_ROLE." where roleid=:roleid",array('roleid'=>$id));
		}
		
		static public function getAllPurviews(){
			require(APPLICATION_PATH.'data/resources.php');
			return $resources;
		}
		
		static public function getmailtemplate($tpl_id){
			$config=Zend_Registry::get('config');
			$subject='';$Tpl='';
			$mailTpls = new Zend_Config_Xml(APPLICATION_PATH.$config->mail->templatepath.'/config.xml');
			$mailTplPath=APPLICATION_PATH.$config->mail->templatepath;
			
			eval('$tplset=isset($mailTpls->'.$tpl_id.')?$mailTpls->'.$tpl_id.':"";');

			if(isset($tplset)){
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

		public static function rebuildReserve($merchantId) {
			$rtn = 0;
			$merchant_info = self::getMerchantInfo($merchantId);
			$reserve_array = array();
			if (sizeof($merchant_info)) {
				$db = Zend_Registry::get('db');
				/*获取merchant所有收款数据*/
				$stmt = $db->prepare('SELECT * '.
									 '  FROM '.TABLE_PTRANS.
									 ' WHERE mid = :merchantId'.
									 '   AND is_calculate = 1'.
									 '	 AND transtatusid = 2'.
								     '   AND transtypeid = 1'.
									 ' ORDER BY	trans_date DESC');
				$stmt->bindValue('merchantId',$merchantId);
				$stmt->execute();
				$rows = $stmt->fetchAll();
				for ($i=0;$i<sizeof($rows);$i++){
					$trans_date = strtotime($rows[$i]['trans_date']);
					$reserve_array[] = array(
												'ptransid' => $rows[$i]['ptransid'],
												'mid'=>$rows[$i]['mid'],
												'expire_date'=>($trans_date + 86400*$merchant_info['reserve_day']),
												'reverse_amount'=>($rows[$i]['trans_amount']*$merchant_info['reserve_fee']/100),
												'trans_invoice'=>$rows[$i]['trans_invoice'],
												'is_calculate'=> 1
											);

					$reserve_array[] = array(
												'ptransid' => $rows[$i]['ptransid'],
												'mid'=>$rows[$i]['mid'],
												'expire_date'=>($trans_date + 86400*$merchant_info['y_reserve_day']),
												'reverse_amount'=>($rows[$i]['trans_amount']*(1-$merchant_info['reserve_fee']/100)+$rows[$i]['trans_fee']),
												'trans_invoice'=>$rows[$i]['trans_invoice'],
												'is_calculate'=> 1
											);
					
				}

				/*获取merchant所有的退款数据*/
				$stmt = $db->prepare('SELECT p.ptransid,p.mid,p.trans_amount,p.trans_invoice,p.trans_fee,r.trans_date '.
									 '  FROM '.TABLE_PTRANS.' p, '.TABLE_PTRANS.' r'.
									 ' WHERE p.mid = :merchantId'.
									 '   AND p.is_calculate = 1'.
									 '	 AND p.transtatusid = 2'.
								     '   AND p.transtypeid = 8'.
									 '   AND p.agtransid = r.agtransid'.
									 '   AND r.transtypeid = 1'.
									 '   AND r.transtatusid = 2'.
									 '   AND r.is_calculate = 1'.
									 '	 AND p.mid = r.mid'.
									 ' ORDER BY	r.trans_date DESC');  
   				$stmt->bindValue('merchantId',$merchantId);
				$stmt->execute();
				$rows = $stmt->fetchAll();
				for ($i=0;$i<sizeof($rows);$i++){
					$trans_date = strtotime($rows[$i]['trans_date']);
					$reserve_array[] = array(
												'ptransid' => $rows[$i]['ptransid'],
												'mid'=>$rows[$i]['mid'],
												'expire_date'=>($trans_date + 86400*$merchant_info['reserve_day']),
												'reverse_amount'=>($rows[$i]['trans_amount']*$merchant_info['reserve_fee']/100),
												'trans_invoice'=>$rows[$i]['trans_invoice'],
												'is_calculate'=> 1
											);

					$reserve_array[] = array(
												'ptransid' => $rows[$i]['ptransid'],
												'mid'=>$rows[$i]['mid'],
												'expire_date'=>($trans_date + 86400*$merchant_info['y_reserve_day']),
												'reverse_amount'=>($rows[$i]['trans_amount']*(1-$merchant_info['reserve_fee']/100) + $rows[$i]['trans_fee']),
												'trans_invoice'=>$rows[$i]['trans_invoice'],
												'is_calculate'=> 1
											);
					
				}

				/*先构造数组，最后删除数据再插入数据，防止计算过程中的提款操作*/
				$stmt = $db->prepare('DELETE FROM '.TABLE_REVERSES.' WHERE mid =:mid');
				$stmt->bindValue('mid',$merchantId);
				$stmt->execute();
				
				/*插入数据*/
				for ($i=0;$i<sizeof($reserve_array);$i++) {
					$rows_affected = $db->insert(TABLE_REVERSES, $reserve_array[$i]);
					$maccid = $db->lastInsertId();
				}
				
				$rtn = 1;

			}

			return $rtn;
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
		
		public static function setIpPurview($mid,$purviewinfo){
			$db =Zend_Registry::get('db');
			
			if(isset($purviewinfo['ipPurviewText'])&&(preg_match('/^(((2[0-4]\d|25[0-5]|\*|[1]?\d\d?)\.){3}(2[0-4]\d|25[0-5]|\*|[1]?\d\d?)(-((2[0-4]\d|25[0-5]|\*|[1]?\d\d?)\.){3}(2[0-4]\d|25[0-5]|\*|[1]?\d\d?))?)([, \n\r]+(((2[0-4]\d|25[0-5]|\*|[1]?\d\d?)\.){3}(2[0-4]\d|25[0-5]|\*|[1]?\d\d?)(-((2[0-4]\d|25[0-5]|\*|[1]?\d\d?)\.){3}(2[0-4]\d|25[0-5]|\*|[1]?\d\d?))?))*$/',$purviewinfo['ipPurviewText'])||$purviewinfo['ipPurviewText']=='')){
				$statement=$db->prepare('Update '.TABLE_MERCHNATS.' set ippurview=:ipcollect where mid='.$mid);	
				$statement->bindValue('ipcollect',$purviewinfo['ipPurviewText']);
				return $statement->execute();
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

		public static function getTransactionsData($begin,$end,$ccno){
			$db = Zend_Registry::get('db');
			if (strlen($ccno) == 3) {				
				$stmt = $db->prepare('SELECT p.*,m.merchant_name '.
									 '  FROM '.TABLE_PTRANS.' p,'.TABLE_MERCHNATS.' m'.
									 ' WHERE p.transtypeid = 1'.
									 '   AND p.transtatusid = 2'.
									 '	 AND right(p.trans_ccnum,3) ="'.$ccno.'"'.
									 '   AND p.trans_date >= "'.$begin.'"'.
									 '   AND p.trans_date <= "'.$end.'"'.
									 '   AND p.mid = m.mid'.
									 '	ORDER BY p.trans_date ASC');

			} else {
				$stmt = $db->prepare('SELECT p.*,m.merchant_name '.
									 '  FROM '.TABLE_PTRANS.' p,'.TABLE_MERCHNATS.' m'.
									 ' WHERE p.transtypeid = 1'.
									 '   AND p.transtatusid = 2'.
									 '	 AND right(p.trans_ccnum,4) ="'.$ccno.'"'.
									 '   AND p.trans_date >= "'.$begin.'"'.
									 '   AND p.trans_date <= "'.$end.'"'.
									 '   AND p.mid = m.mid'.
									 '	ORDER BY p.trans_date ASC');
			}
			$stmt->execute();
			$rows = $stmt->fetchAll();
			return $rows;
		}
	}
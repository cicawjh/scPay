<?php
	/*
		Class: Users  --所有关于Users的登陆，验证，以及其他一些transaction信息的获取
		function:
			userLogin -- 檢查後臺用戶登錄
			getTopMerchants --獲取收款額最高的merchants列表

		Author: Wells
		Create Date: 2009-06-11
	*/
	class Users {
		public function __construct() {
		}

		public static function userLogin ($name,$pass) {
			$user_id = 0;
			if (empty($name) || empty($pass)) return $user_id;
			$db = Zend_Registry::get('db');

			$stmt = $db->prepare('SELECT * FROM '.TABLE_USERS.' WHERE user_name = :user_name limit 1');

			$stmt->bindValue('user_name',$name);
			$stmt->execute();
			
			$rows = $stmt->fetchAll();
			if (sizeof($rows)) {
				$rtn = self::_validate_password($pass,$rows[0]['password']);
				if ($rtn) $user_id = $rows[0]['userid'];
			}

			return $user_id;
		}

		public static function getTopMerchants ($top=5) {
			$merchants = array();
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT current_date() end,DATE_ADD(current_date(), INTERVAL -1 MONTH) begin');
			$stmt->execute();
			$rows = $stmt->fetchAll();
			
			$begin = $rows[0]['begin'];
			$end = $rows[0]['end'].' 23:59:59';

			$stmt = $db->prepare('SELECT m.mid,max(m.merchant_name) as merchant_name,'.
								 '       max(m.amount_balance) as balance,sum(p.trans_amount) as received'.
								 '	FROM '.TABLE_MERCHNATS.' m, '.TABLE_PTRANS.' p'.
								 ' WHERE m.mid = p.mid'.
								 '	 AND p.transtypeid = 1'.
				                 '   AND p.transtatusid = 2'.
								 '	 AND p.trans_date between :begin AND :end'.
				                 ' GROUP BY m.mid'.
								 ' ORDER BY received desc limit '.$top);
			$stmt->bindValue('begin',$begin);
			$stmt->bindValue('end',$end);
			$stmt->execute();
			$merchants= $stmt->fetchAll();
			return $merchants;
		}

		public static function getLastYearTotal () {
			$lastTotal = array();
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT current_date() end,DATE_ADD(current_date(), INTERVAL -1 YEAR) begin');
			$stmt->execute();
			$rows = $stmt->fetchAll();
			
			$begin = $rows[0]['begin'];
			$end = $rows[0]['end'].' 23:59:59';

			$stmt = $db->prepare('SELECT date_format(trans_date,\'%y/%m\') as yd, sum(trans_amount) as received'.
				                '  FROM '.TABLE_PTRANS.
								' WHERE transtypeid = 1'.
								'   AND transtatusid = 2'.
								'   AND trans_date between :begin AND :end'.
								'	GROUP BY date_format(trans_date,\'%y/%m\')'.
								'	ORDER BY yd ASC');
			$stmt->bindValue('begin',$begin);
			$stmt->bindValue('end',$end);
			$stmt->execute();
			$lastTotal = $stmt->fetchAll();
			return $lastTotal;
		}

		public static function getConfiguration() {
			$configuran = array();
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT * FROM '.TABLE_CONFIGURATIONS);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			for ($i=0;$i<sizeof($rows);$i++){
				$configuran[$rows[$i]['configuration_key']] = $rows[$i]['configuration_value'];
			}

			return $configuran;
		}

		public static function setConfiguration($values) {
			$db = Zend_Registry::get('db');
			$set = array('configuration_value' => $values['aglogin']);
			$where = $db->quoteInto('configuration_key = ?', 'CFG_MODULE_PAYMENT_AUTHORIZENET_LOGIN');
			$rows_affected = $db->update(TABLE_CONFIGURATIONS,$set, $where);

			$set = array('configuration_value' => $values['agkey']);
			$where = $db->quoteInto('configuration_key = ?', 'CFG_MODULE_PAYMENT_AUTHORIZENET_TXNKEY');
			$rows_affected = $db->update(TABLE_CONFIGURATIONS,$set, $where);
		}

		public static function setAdminPass ($user_id,$values) {
			$db = Zend_Registry::get('db');
			$set = array('password' => self::_encrypt_password($values['password']));
			$where = $db->quoteInto('userid = ?', $user_id);
			$rows_affected = $db->update(TABLE_USERS,$set, $where);
		}

		public static function getGateways() {
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT * '.' FROM '.TABLE_GATEWAYS);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			return $rows;
		}

		public static function getGatewayById($gatewayid){
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT * FROM '.TABLE_GATEWAYS.' WHERE gatewayid = :gatewayid limit 1');

			$stmt->bindValue('gatewayid',$gatewayid);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			return $rows[0];
		}

		public static function changeGateway($gatewayid,$values){
			$rows_affected = 0;
			$db = Zend_Registry::get('db');
			$set = array(
						'gateway_title' => $values['gateway_title'],
						'gateway_url' => $values['gateway_url'],
						'gateway_login' => $values['gateway_login'],
						'gateway_key' => $values['gateway_key']
						);

			$where = $db->quoteInto('gatewayid = ?', $gatewayid);
			$rows_affected = $db->update(TABLE_GATEWAYS,$set, $where);
			return $rows_affected;
		}

		public static function addGateway($values){
			$gatewayid = 0;
			$db = Zend_Registry::get('db');
			if (sizeof($values)) {
				$row = array (
								'gateway_title' => $values['gateway_title'],
								'gateway_url' => $values['gateway_url'],
								'gateway_login' => $values['gateway_login'],
								'gateway_key' => $values['gateway_key']
							);
				$rows_affected = $db->insert(TABLE_GATEWAYS, $row);
				$gatewayid = $db->lastInsertId();
			}
			return $gatewayid;
		}

		public static function deleteGateway($gatewayid) {
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('DELETE FROM '.TABLE_GATEWAYS.' WHERE gatewayid = :gatewayid');
			$stmt->bindValue('gatewayid', $gatewayid);
			$stmt->execute();
		}

		public static function getUsers() {
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('SELECT * '.' FROM '.TABLE_USERS);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			return $rows;
		}

		public static function Adduser($values) {
			$uid = 0;
			$db = Zend_Registry::get('db');
			if (sizeof($values)) {
				$row = array (
								'roleid' => 1,
								'user_name' => $values['user_name'],
								'password' => self::_encrypt_password($values['password'])
							);
				$rows_affected = $db->insert(TABLE_USERS, $row);
				$uid = $db->lastInsertId();
			}
			return $uid;
		}

		public static function deleteUser($uid) {
			$db = Zend_Registry::get('db');
			$stmt = $db->prepare('DELETE FROM '.TABLE_USERS.' WHERE userid = :userid');
			$stmt->bindValue('userid', $uid);
			$stmt->execute();
		}
		
		public static function getUserInfo($uid){
			$db = Zend_Registry::get('db');
			$row=$db->fetchRow("select * from ".TABLE_USERS." where userid = :userid",array('userid'=>$uid));
			if($row['description']){
				$row['info']=unserialize($row['description']);
				unset($row['description']);
			}
			if($row['roleid']){
				$row['roleid']=unserialize($row['roleid']);
			}else{
				$row['roleid']=array();
			}
			
			if($row['purview']){
				$row['purview']=unserialize($row['purview']);
			}else{
				$row['purview']=array();
			}			
			return $row;
		}
		
		public static function getUserPurview($uid){
			$userinfo=self::getUserInfo($uid);
			if($userinfo['purview']){
				foreach($userinfo['purview'] as $key=>$val){
					if($val==0){$getPurview[$key]=$val;}
				}
			}
			if($userinfo['roleid']&&$getPurview){
				$userinfo['purview']=array_merge($userinfo['purview'],self::getRolePurview($userinfo['roleid'],$getPurview));
			}
			return $userinfo['purview'];
		}
		//取得角色的权限，$roles为角色id数组或者角色id
		public static function getRolePurview($roles,$getPurview,$statroles=array()){

			if(!is_array($roles)){
				if(preg_match('/^\d+$/',$roles)){
					$roles=array($roles);
				}else{
					return;
				}
			}

			$nextgetPurview=array();
			$nextroles=array();
			$hasgetPurview=array();
			$statroles=array_merge($statroles,$roles);
			$db = Zend_Registry::get('db');

			foreach($roles as $role){
				$row=$db->fetchRow("select purview,inherit from ".TABLE_USER_ROLE." where roleid=".$role);
				if($row){
					// 获取权限
					$nextgetPurview=array();
					$rolep=unserialize($row['purview']);
					if($getPurview){
						foreach($getPurview as $key=>$val){
							if($rolep[$key]!=0){
								$hasgetPurview[$key]=$rolep[$key];
							}else{
								$nextgetPurview[$key]=$rolep[$key];
							}
						}
					}

					if($nextgetPurview){
						$getPurview=$nextgetPurview;
					}else{
						break;
					}
					
					// 获取继承角色
					$inherit=unserialize($row['inherit']);
					if($inherit&&is_array($inherit)){
						foreach($inherit as $inh){
							if(!in_array($inh,$statroles)){
								$nextroles[]=$inh;
							}
						}
					}
					
				}
			}

			if(sizeof($nextgetPurview)>0&&sizeof($nextroles)>0){
				$hasgetPurview=array_merge($hasgetPurview,self::getRolePurview($nextroles,$nextgetPurview,$statroles));
			}
			return $hasgetPurview;
		}
		
		
		
		public static function saveUserInfo($userInfo,$uid){
			$db = Zend_Registry::get('db');
			
			$where=$db->quoteInto('userid = ?',$uid);
			
			$db->update(TABLE_USERS,$userInfo,$where);
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
	}
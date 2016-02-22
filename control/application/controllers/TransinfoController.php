<?php
	require_once(APPLICATION_PATH.'models/Merchants.php');
	class TransinfoController extends Zend_Controller_Action {
	
		function indexAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$get = $this->getRequest()->getParams();
			if (isset($get['mid']) && intval($get['mid']) > 0) {
				$merchant_id = intval($get['mid']);
			} else {
				$this->_redirect('/Merchantlist/index');
			}
			
			if (isset($get['id']) && intval($get['id']) > 0) {
				$trans_id = intval($get['id']);
			} else {
				$this->_redirect('/Merchanttrans/index/id/'.$merchant_id);
			}

			if (isset($get['error']) && $get['error'] != '') {
				$error_info = html_entity_decode($get['error']);
				$this->view->headScript()->setScript('alert("'.$error_info.'");');
			}

			$transaction = Merchants::getTransDetail($merchant_id,$trans_id);
			$transaction = array_merge($transaction,Merchants::getDelivery($trans_id));
			
			if (!sizeof($transaction)) $this->_redirect('/Merchanttrans/index/id/'.$merchant_id);
			
			$this->view->transaction = $transaction;
			$currency = new Zend_Currency('en_US');
			$this->view->symbol= $currency->getSymbol();
			
			$this->view->transaction['locked']=Merchants::getTransactionLocked($trans_id);
			$this->view->shippingmethods =Merchants::shippingmethods();

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('searchbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));
		}
		
		function locktransAction(){
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$get = $this->getRequest()->getParams();
			
			if (isset($get['merchantID']) && $get['merchantID'] > 0) {
				$merchant_id = $get['merchantID'];
			} else {
				$this->_redirect('/Merchantlist/index');
			}
			
			if (isset($get['transID']) && $get['transID'] > 0) {
				$trans_id = $get['transID'];
			} else {
				$this->_redirect('/Merchanttrans/index/id/'.$merchant_id);
			}
			
			$transaction = Merchants::getTransDetail($merchant_id,$trans_id);
			
			if (!sizeof($transaction)) $this->_redirect('/Merchanttrans/index/id/'.$merchant_id);
			
			
			if(isset($get['submit'])&&$get['submit']=='Confirm Locked'){
				$opsucess=Merchants::lockOptransaction($merchant_id,$trans_id,$get['lock_amount'],$get);
				//发送邮件设置
				$mailtpl='locktransnotice';
			}elseif(isset($get['submit'])&&$get['submit']=='Confirm UnLocked'){
				$opsucess=Merchants::lockOptransaction($merchant_id,$trans_id,$get['lock_amount'],$get);
				
				$mailtpl='unlocktransnotice';
			}
			
			if(isset($opsucess)&&$opsucess){
			
				if(isset($get['sendEmail'])&&$get['sendEmail']){
					$merchants = Merchants::getMerchantInfo($merchant_id);
					
					//读取模板
					$emailinfo=Merchants::getmailtemplate($mailtpl);
					
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
						Merchants::sendMail($merchants['merchant_name'],$merchants['merchant_email'],$email_subject,$email_text);	
					}
				}
				
				$this->_redirect('/Transinfo/index/mid/'.$merchant_id.'/id/'.$trans_id);
			}
			
			$haslocked=Merchants::getTransactionLocked($trans_id);
			if($lockinfo=Merchants::getTransactionLockinfo($trans_id)){
				$transaction=array_merge($transaction,$lockinfo);
			}
			if($haslocked){
				$this->view->submitValue='Confirm UnLocked';
				$transaction['lock_amount']=0;
				//默认通知内容
				$transaction['comment']='We are so happy to inform you that your transaction has unlocked. ';
			}else{
				$this->view->submitValue='Confirm Locked';
				$transaction['lock_amount']=$transaction['trans_amount'];
				//默认通知内容
				$transaction['comment']='We are so honored that you are one of our members. But now we are so sorry to inform you that your transaction has locked';				
			}
			
			$this->view->transaction = $transaction;
			
			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('searchbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));
			
		}

		function changestatusAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$get = $this->getRequest()->getParams();
			if (isset($get['mid']) && $get['mid'] > 0) {
				$merchant_id = $get['mid'];
			} else {
				$this->_redirect('/Merchantlist/index');
			}
			
			if (isset($get['id']) && $get['id'] > 0) {
				$trans_id = $get['id'];
			} else {
				$this->_redirect('/Merchanttrans/index/id/'.$merchant_id);
			}
			
			if (isset($get['newStatus']) && $get['newStatus'] > 0) {
				$newStatus = $get['newStatus'];
				Merchants::changeStatus($merchant_id,$trans_id,$newStatus);
			}

			$this->_redirect('/Transinfo/index/mid/'.$merchant_id.'/id/'.$trans_id);
		}

		function setpendingAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$get = $this->getRequest()->getParams();
			if (isset($get['mid']) && $get['mid'] > 0) {
				$merchant_id = $get['mid'];
			} else {
				$this->_redirect('/Merchantlist/index');
			}
			
			if (isset($get['id']) && $get['id'] > 0) {
				$trans_id = $get['id'];
			} else {
				$this->_redirect('/Merchanttrans/index/id/'.$merchant_id);
			}
			
			if (isset($get['newStatus']) && $get['newStatus'] > 0) {
				$newStatus = $get['newStatus'];
				Merchants::setPending($merchant_id,$trans_id,$newStatus);
			}

			$this->_redirect('/Transinfo/index/mid/'.$merchant_id.'/id/'.$trans_id);

		}

		function billingAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}


			$get = $this->getRequest()->getParams();
			if (isset($get['mid']) && $get['mid'] > 0) {
				$merchant_id = $get['mid'];
			} else {
				$this->_redirect('/Merchantlist/index');
			}
			
			if (isset($get['id']) && $get['id'] > 0) {
				$trans_id = $get['id'];
			} else {
				$this->_redirect('/Merchanttrans/index/id/'.$merchant_id);
			}


			$transaction = Merchants::getTransDetail($merchant_id,$trans_id);
			if (!sizeof($transaction)) $this->_redirect('/Merchanttrans/index/id/'.$merchant_id);

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));

			$this->view->info_form = $this->getForm($merchant_id,$transaction);
			return $this->render('billing');
		}
		
		//锁定收款记录
		function lockReceived(){
			
		}

		function resetbillingAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$get = $this->getRequest()->getParams();
			if (isset($get['mid']) && $get['mid'] > 0) {
				$merchant_id = $get['mid'];
			} else {
				$this->_redirect('/Merchantlist/index');
			}
			
			if (isset($get['id']) && $get['id'] > 0) {
				$trans_id = $get['id'];
			} else {
				$this->_redirect('/Merchanttrans/index/id/'.$merchant_id);
			}

			$transaction = Merchants::getTransDetail($merchant_id,$trans_id);
			if (!sizeof($transaction)) $this->_redirect('/Merchanttrans/index/id/'.$merchant_id);

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));

			$form = $this->getForm($merchant_id,$transaction);
			if (!$form->isValid($_POST)) {
				// Failed validation; redisplay form
				$this->view->info_form = $form;
				return $this->render('billing');
			}

			$values = $form->getValues();
			Merchants::resetBillingInfo($merchant_id,$trans_id,$values);
			$this->_redirect('/Transinfo/index/mid/'.$merchant_id.'/id/'.$trans_id);
		}

		function getForm($merchant_id,$trans_info=array()) {
			$form = new Zend_Form;
			$request = $this->getRequest();
			$baseurl = $request->getBaseUrl();

			$form->setAction($baseurl.'/Transinfo/resetbilling/mid/'.$merchant_id.'/id/'.$trans_info['ptransid'])
				 ->setMethod('post')
				 ->setAttrib('id','register');

			$trans_ccowner = $form->createElement('text', 'trans_ccowner',array('label' => 'Card Owner:'));
			$trans_ccowner->addValidator('stringLength', false, array(3))
						 ->setRequired(true);
			
			$trans_ccnum = $form->createElement('text', 'trans_ccnum',array('label' => 'Card Number:'));
			$trans_ccnum->addValidator('stringLength', false, array(3))
						 ->setRequired(true);

			$trans_cvv2 = $form->createElement('text', 'trans_cvv2',array('label' => 'Card CVV2:'));
			$trans_cvv2->addValidator('stringLength', false, array(3))
						 ->setRequired(true);
			
			$trans_expire = $form->createElement('text', 'trans_expire',array('label' => 'Card Expire:'));
			$trans_expire->addValidator('stringLength', false, array(3))
						 ->setRequired(true);
			
			$customer_street = $form->createElement('text', 'customer_street',array('label' => 'Street:'));
			$customer_street->addValidator('stringLength', false, array(3))
						 ->setRequired(true);
			
			$customer_city = $form->createElement('text', 'customer_city',array('label' => 'City:'));
			$customer_city->addValidator('stringLength', false, array(3))
						 ->setRequired(true);
			
			$customer_state = $form->createElement('text', 'customer_state',array('label' => 'State:'));
			$customer_state->addValidator('stringLength', false, array(3))
						 ->setRequired(true);
			
			$customer_country = $form->createElement('text', 'customer_country',array('label' => 'Country:'));
			$customer_country->addValidator('stringLength', false, array(3))
						 ->setRequired(true);

			$shipping_postcode = $form->createElement('text', 'shipping_postcode',array('label' => 'Post Code:'));
			$shipping_postcode->addValidator('stringLength', false, array(3))
						 ->setRequired(true);

			$gatewayid = $form->createElement('text', 'gatewayid',array('label' => 'GateWay ID:'));
			$gatewayid->setRequired(true);

			$ag_amount = $form->createElement('text', 'ag_amount',array('label' => 'Amount:'));
			$ag_amount->setRequired(true);


			$default_array = array(
										'trans_ccowner' => $trans_info['trans_ccowner'],
										'trans_ccnum' => $trans_info['trans_ccnum'],
										'trans_cvv2' => $trans_info['trans_cvv2'],
										'trans_expire' => $trans_info['trans_expire'],
										'customer_street' => $trans_info['customer_street'],
										'customer_city' => $trans_info['customer_city'],
										'customer_state' => $trans_info['customer_state'],
										'customer_country' => $trans_info['customer_country'],
										'shipping_postcode' => $trans_info['shipping_postcode'],
										'gatewayid'=>$trans_info['gatewayid'],
										'ag_amount'=>$trans_info['ag_amount'],
										);

			$form->addElement($trans_ccowner)
				 ->addElement($trans_ccnum)
				 ->addElement($trans_cvv2)
				 ->addElement($trans_expire)
				 ->addElement($customer_street)
				 ->addElement($customer_city)
				 ->addElement($customer_state)
				 ->addElement($customer_country)
				 ->addElement($shipping_postcode)
				 ->addElement($gatewayid)
				 ->addElement($ag_amount);

			$form->setDefaults($default_array);
			$form->addElement('submit', 'Complete', array('label' => 'Complete','class'=>'button'))
				  ->addElement('button', 'Cancel', array('label' => 'Cancel','class'=>'button', 'onClick'=>'location.href="'.$baseurl.'/Transinfo/index/mid/'.$merchant_id.'/id/'.$trans_info['ptransid'].'"'));
			return $form;
		}

		function receiveAction() {
			
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$post = $this->getRequest()->getParams();
			
//			//back to if submit same data by jason
//			if($scnsp->postinfo==md5(serialize($post))){
//				$this->_redirect('Transinfo/index/mid/'.$post['merchantID'].'/id/'.$post['transID']);
//			}
			$scnsp->postinfo=md5(serialize($post));

			if (isset($post['merchantID']) && $post['merchantID'] >0) {
				$merchant_id = $post['merchantID'];
			} else {
				$this->_redirect('/Index');
			}

			if (isset($post['transID']) && $post['transID'] > 0) {
				$trans_id = $post['transID'];
			} else {
				$this->_redirect('/Merchanttrans/Index/id/'.$merchant_id);
			}

			$transaction = Merchants::getTransDetail($merchant_id,$trans_id);
			
			if (!sizeof($transaction)) $this->_redirect('/Merchanttrans/Index/id/'.$merchant_id);
				
			$merchants = Merchants::getMerchantInfo($merchant_id);
			
			$transaction_response = Merchants::sendAgRequest($merchants,$transaction,$transaction['trans_amount'],'AUTH_CAPTURE');
			
			if (!empty($transaction_response)) {			
				//$regs = preg_split("/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/", $transaction_response);
				//$regs = explode(",",$transaction_response);
				$regs = preg_split('/\",\"/', substr($transaction_response, 1, -1));
				foreach ($regs as $key => $value) {
					//$regs[$key] = substr($value, 1, -1); // remove double quotes
					$regs[$key] = str_replace('\"','',$value);
					$regs[$key] = str_replace('"','',$value);
				}
				
				if (isset($regs[6]) && $regs[6] !='' && $regs[6] !='0') {
					$ag_trans_id = $regs[6].'_'.date('ymd').mt_rand(100,999);
				} else {
					$ag_trans_id = $transaction['agtransid'];
				}
				if ($regs[0] != '1') {
					$error_info = $regs[3];
					Merchants::autoReceive($merchant_id,$trans_id,$ag_trans_id,0,$error_info);
				} else {
					$error_info = '';
					Merchants::autoReceive($merchant_id,$trans_id,$ag_trans_id,1);
					
					/*下面分别向客户和merchant发送收款成功的邮件*/
					/*向merchant api 提交收款成功信息*/
					
					//读取模板
					$emailinfo=Merchants::getmailtemplate('receive');
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
					
					}else{}

					Merchants::sendMail($transaction['trans_ccowner'],$transaction['customer_email'],$email_subject,$email_text,$merchants['merchant_email']);

					if ($merchants['order_api'] != '') {
						$api_param = array(
											'v_invoice'=>$transaction['trans_invoice'],
											'v_customer_email'=>$transaction['customer_email'],
											'v_customer_name'=>$transaction['trans_ccowner'],
											'v_customer_ip'=>$transaction['customer_ip'],
											'v_trans_amount'=>$transaction['trans_amount'],
											'v_payment_date'=>date("Y-m-d H:i:s")
											);

						$post_string = '';

						foreach ($api_param as $key => $value) {
							$post_string .= $key . '=' . urlencode(trim($value)) . '&';
						}

						$post_string = substr($post_string, 0, -1);
						
						$merchant_api = $merchants['order_api'];
						$result = Merchants::sendTransactionToGateway($merchant_api, $post_string);
					}
				}
			} else {
				$error_info = 'Can not connect to server!';
			}
			
			if (strlen($error_info) > 0) {
				$this->_redirect('/Transinfo/index/mid/'.$merchant_id.'/id/'.$trans_id.'/error/'.htmlentities(urlencode($error_info)));
			} else {
				$this->_redirect('/Transinfo/index/mid/'.$merchant_id.'/id/'.$trans_id);
			}
			
		}

		function rejectAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$post = $this->getRequest()->getParams();
			if (isset($post['merchantID']) && $post['merchantID'] >0) {
				$merchant_id = $post['merchantID'];
			} else {
				$this->_redirect('/Index');
			}

			if (isset($post['transID']) && $post['transID'] > 0) {
				$trans_id = $post['transID'];
			} else {
				$this->_redirect('/Merchanttrans/Index/id/'.$merchant_id);
			}
			
			$transaction = Merchants::getTransDetail($merchant_id,$trans_id);
			if (!sizeof($transaction)) $this->_redirect('/Merchanttrans/Index/id/'.$merchant_id);

			Merchants::voidReceive($merchant_id,$trans_id);
			$this->_redirect('/Transinfo/index/mid/'.$merchant_id.'/id/'.$trans_id);
		}
	}
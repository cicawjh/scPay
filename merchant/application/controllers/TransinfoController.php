<?php
	require_once(APPLICATION_PATH.'models/Merchants.php');
	class TransinfoController extends Zend_Controller_Action {
		function indexAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null || $merchant_id <=0) {
				$this->_redirect('/index');
			}

			$get = $this->getRequest()->getParams();
			if (isset($get['id']) && $get['id'] > 0) {
				$trans_id = $get['id'];
			} else {
				$this->_redirect('/Transaction/index');
			}

			if (isset($get['error']) && $get['error'] != '') {
				$error_info = html_entity_decode($get['error']);
				$this->view->headScript()->setScript('alert("'.$error_info.'");');
			}

			$transaction = Merchants::getTransDetail($merchant_id,$trans_id);
			$transaction = array_merge($transaction,Merchants::getDelivery($trans_id));
			
			if (!sizeof($transaction)) $this->_redirect('/Transaction/index');
			$this->view->transaction = $transaction;
			$currency = new Zend_Currency('en_US');
			$this->view->symbol= $currency->getSymbol();
			
			$this->view->transaction['locked']=Merchants::getTransactionwasLocked($trans_id);
			$this->view->shippingmethods =Merchants::shippingmethods();
			
			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('searchbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));
		}
		
		//发货方式保存
		function deliveryAction(){
			
			if(!$_POST){
				$this->_redirect('/index');
			}
			
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null || $merchant_id <=0) {
				$this->_redirect('/index');
			}

			$get = $this->getRequest()->getParams();
			if (isset($get['id']) && intval($get['id']) > 0) {
				$trans_id = intval($get['id']);
			} else {
				$this->_redirect('/Transaction/index');
			}
			
			$transaction = Merchants::getTransDetail($merchant_id,$trans_id);
			
			$Delivery=array(
					'ptransid'=>$trans_id,
					'agtransid'=>$transaction['agtransid'],
					'forwarder'=>$_POST['forwarder'],
					'forwarderno'=>$_POST['shippingNumer'],
					'detail'=>$_POST['detail']
			);
			echo Merchants::saveDelivery($Delivery);
			
			exit();
		}

		function receiveAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null || $merchant_id <=0) {
				$this->_redirect('/index');
			}

			$post = $this->getRequest()->getParams();
			if (isset($post['transID']) && $post['transID'] > 0) {
				$trans_id = $post['transID'];
			} else {
				$this->_redirect('/Transaction/index');
			}

			$transaction = Merchants::getTransDetail($merchant_id,$trans_id);
			if (!sizeof($transaction)) $this->_redirect('/Transaction/index');
				
			$merchants = Merchants::getMerchantInfo($merchant_id);
			
			$transaction_response = Merchants::sendAgRequest($merchants,$transaction,$transaction['trans_amount'],'AUTH_CAPTURE');

			if (!empty($transaction_response)) {		
				//$regs = @preg_split("/,(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))/", $transaction_response);
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
						Merchants::sendMail($transaction['trans_ccowner'],$transaction['customer_email'],$email_subject,$email_text,$merchants['merchant_email']);
					}
					
					

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
				$this->_redirect('/Transinfo/index/id/'.$trans_id.'/error/'.htmlentities(urlencode($error_info)));
			} else {
				$this->_redirect('/Transinfo/index/id/'.$trans_id);
			}
		}

		function rejectAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null || $merchant_id <=0) {
				$this->_redirect('/index');
			}

			$post = $this->getRequest()->getParams();
			if (isset($post['transID']) && $post['transID'] > 0) {
				$trans_id = $post['transID'];
			} else {
				$this->_redirect('/Transaction/index');
			}
			
			$transaction = Merchants::getTransDetail($merchant_id,$trans_id);
			if (!sizeof($transaction)) $this->_redirect('/Transaction/index');

			Merchants::voidReceive($merchant_id,$trans_id);
			$this->_redirect('/Transinfo/index/id/'.$trans_id);
		}
	}
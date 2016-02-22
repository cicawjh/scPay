<?php
	require_once(APPLICATION_PATH.'models/Merchants.php');
	class RefundtransController extends Zend_Controller_Action {
		
		public function __construct(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response, array $invokeArgs = array())
		{
			parent::__construct($request,$response,$invokeArgs);
			$request = Zend_Controller_Front::getInstance()->getRequest(); 
			$base_url = $request->getBaseUrl();
			$this->view->headLink()->appendStylesheet($base_url."/public/css/refund.css");
			$this->view->headScript()->appendFile($base_url."/public/js/common.js");
		}
		
		function indexAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}
			if (!$this->getRequest()->isPost()) {
				return $this->_redirect('/Index');
			}

			$post = $this->getRequest()->getParams();
			if (isset($post['merchantID']) && $post['merchantID'] >0) {
				$merchant_id = $post['merchantID'];
			} else {
				$this->_redirect('/Index');
			}
			if (isset($post['transID']) && $post['transID'] >0) {
				$trans_id = $post['transID'];
			} else {
				$this->_redirect('/Merchanttrans/Index/id/'.$merchant_id);
			}

			$transaction = Merchants::getTransDetail($merchant_id,$trans_id);
			
			
			if (!sizeof($transaction)) $this->_redirect('/Merchanttrans/Index/id/'.$merchant_id);
			if ($transaction['transtypeid'] != 1 || $transaction['transtatusid'] != 2) $this->_redirect('Transinfo/index/mid/'.$merchant_id.'/id/'.$trans_id);
			
			if (strtotime($transaction['trans_date']) < strtotime("-1 day")) {
				$this->view->newAgtrans =Merchants::getNewagtrans($transaction);
			}
			
			$this->view->transaction = $transaction;
			$currency = new Zend_Currency('en_US');
			$this->view->symbol= $currency->getSymbol();
			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));
		}


		function refundlogAction(){

			$params = $this->getRequest()->getParams();

			$registry = Zend_Registry::getInstance();
			
			$numPerPage=20;
			$this->view->refoundLogs=Merchants::getrefundlogs($params,$numPerPage);

			$this->view->params=array(
				'begin' => isset($params['begin'])?$params['begin']:date('Y-m-d',mktime(0,0,0,date('m')-1,date('m'),date('Y'))),
				'to' => isset($params['to'])?$params['to']:date('Y-m-d'),
				'inv_no' => isset($params['inv_no'])?$params['inv_no']:'',
				'customer' => isset($params['customer'])?$params['customer']:'',
				'transid' => isset($params['transid'])?$params['transid']:''
			);

			$response = $this->getResponse();
			$params['page']=isset($params['page'])?$params['page']:'1';
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));

			$paginator = Zend_Paginator::factory($this->view->refoundLogs);
			$paginator->setCurrentPageNumber($params['page'])->setItemCountPerPage($numPerPage);
			$this->view->paginator = $paginator;
		}

		function refundAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			if (!$this->getRequest()->isPost()) {
				return $this->_redirect('/Index');
			}
			$post = $this->getRequest()->getParams();
			$error = 0;
			if (isset($post['merchantID']) && $post['merchantID'] >0) {
				$merchant_id = $post['merchantID'];
			} else {
				$this->_redirect('/Index');
			}
			
			if (isset($post['transID']) && $post['transID'] >0) {
				$trans_id = $post['transID'];
				if (isset($post['refund_amount']) && $post['refund_amount'] > 0) {
					//设置退到新卡
					if(isset($post['isnewcard'])&&$post['isnewcard']){
						$credit_card['trans_ccnum']=$post['credit_card_num'];
						$credit_card['trans_expire']=$post['credit_expires_month'].$post['credit_expires_year'];
						if($post['credit_card_cvv2']){$credit_card['trans_cvv2']=$post['credit_card_cvv2'];}
						if($post['credit_card_ccowner']){$credit_card['trans_ccowner']=$post['credit_card_ccowner'];}
					}
					
					$refund_amount = $post['refund_amount'];
					$refund_desc = $post['refund_desc'];
					if(isset($credit_card)){
						$rtn = Merchants::setRefund($merchant_id,$trans_id,$refund_amount,$refund_desc,$credit_card);
					}else{
						$rtn = Merchants::setRefund($merchant_id,$trans_id,$refund_amount,$refund_desc);
					}
					$error = $rtn[0];
					$error_info = $rtn[1];
				} else {
					$error = 1;
					$error_info = 'To enter a correct refund!';
				}
			} else {
				$this->_redirect('/Merchanttrans/Index/id/'.$merchant_id);
			}

			if ($error > 0) {
				$transaction = Merchants::getTransDetail($merchant_id,$trans_id);
				if (!sizeof($transaction))  $this->_redirect('/Merchanttrans/Index/id/'.$merchant_id);
				$this->view->transaction = $transaction;
				$currency = new Zend_Currency('en_US');
				$this->view->symbol= $currency->getSymbol();
				$response = $this->getResponse(); 
				$response->insert('header', $this->view->render('header2.phtml')); 
				$response->insert('footer', $this->view->render('footer.phtml'));
				$this->view->headScript()->setScript('alert("'.$error_info.'");');
				$this->render('index');
			} else {
				/*下面分别向客户和merchant发送收款成功的邮件*/
				$refund_trans_id = $rtn[1];
				$transaction = Merchants::getTransDetail($merchant_id,$refund_trans_id);
				if (!sizeof($transaction)) $this->_redirect('/Transaction/index');
				$merchants = Merchants::getMerchantInfo($merchant_id);

				//读取模板
				$emailinfo=Merchants::getmailtemplate('refund');
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

				$this->_redirect('/Merchanttrans/Index/id/'.$merchant_id);
			}
		}
	}
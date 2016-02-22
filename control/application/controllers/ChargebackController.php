<?php
	require_once(APPLICATION_PATH.'models/Merchants.php');
	class ChargebackController extends Zend_Controller_Action {
		function init(){
			$request = Zend_Controller_Front::getInstance()->getRequest(); 
			$base_url = $request->getBaseUrl();
			$base_url .= '/public';
			$this->view->headScript()->appendFile($base_url.'/js/datepicker/jquery.datepick.js');
			$this->view->headLink()->appendStylesheet($base_url.'/js/datepicker/redmond.datepick.css');	
			
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}
		}

		function indexAction() {
			$request=$this->_getRequest(true);
			
			$this->view->transactions=Merchants::getTransactionsData($request['begin_date'],$request['end_date'],$request['cc_no']);
			$this->view->params=$request;

			$response = $this->getResponse(); 
		
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));
		}

		function chargeAction() {
			//$request=$this->getRequest()->getParams();
			$request=$this->_getRequest(true);
			$chargecomment = $request['charge_description'];
			$charge_trans = explode("_",$request['charge_trans']);
			
			$mid = $charge_trans[0];
			$transid = $charge_trans[1];

			if ($mid == null || $transid == null) $this->_redirect('/Chargeback');
			
			$return = Merchants::setCharge($mid,$transid,$chargecomment);
			if ($return[0] == 0) {
				$error = $return[1];
				

				$this->view->transactions=Merchants::getTransactionsData($request['begin_date'],$request['end_date'],$request['cc_no']);
				$this->view->params=$request;

				$response = $this->getResponse(); 
		
				$response->insert('header', $this->view->render('header2.phtml')); 
				$response->insert('footer', $this->view->render('footer.phtml'));

				$this->view->headScript()->setScript('alert("'.$error.'");');
				return $this->render('index');
			} else {
				$this->_redirect('/Merchanttrans/Index/id/'.$mid);
			}
		}

		function _getRequest($mustDate=false){
			
			$request=$this->getRequest()->getParams();
					
			$dateRegex='#^(?:(?:1[6-9]|[2-9][0-9])[0-9]{2}([-/.]?)(?:(?:0?[1-9]|1[0-2])\1(?:0?[1-9]|1[0-9]|2[0-8])|(?:0?[13-9]|1[0-2])\1(?:29|30)|(?:0?[13578]|1[02])\1(?:31))|(?:(?:1[6-9]|[2-9][0-9])(?:0[48]|[2468][048]|[13579][26])|(?:16|[2468][048]|[3579][26])00)([-/.]?)0?2\2(?:29))$#';
			
			if(!$this->getRequest()->isPost()||$mustDate){
				if(!preg_match($dateRegex,$request['begin_date'])&&!preg_match($dateRegex,$request['end_date'])){
					$request['begin_date']=date('Y-m-d',mktime(0,0,0,date('n')-1,date('d'),date('Y')));
					$request['end_date']=date('Y-m-d');
				}elseif(!preg_match($dateRegex,$request['begin_date'])){
					$timestamp=strtotime($request['end_date']);
					$request['begin_date']=date('Y-m-d',mktime(0,0,0,date('n',$timestamp)-1,date('d',$timestamp),date('Y',$timestamp)));
				}elseif(!preg_match($dateRegex,$request['end_date'])){
					$timestamp=strtotime($request['begin_date']);
					$request['end_date']=date('Y-m-d',mktime(0,0,0,date('n',$timestamp)+1,date('d',$timestamp),date('Y',$timestamp)));
				}
			}
			
			return $request;
		}
	}
<?php
	require_once(APPLICATION_PATH.'models/Merchants.php');
	class WithdrawinfoController extends Zend_Controller_Action {
		function indexAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$get = $this->getRequest()->getParams();
			if (isset($get['mid']) && $get['mid'] > 0) {
				$merchant_id = $get['mid'];
			} else {
				$this->_redirect('/Withdrawtrans/index');
			}
			
			if (isset($get['id']) && $get['id'] > 0) {
				$trans_id = $get['id'];
			} else {
				$this->_redirect('/Withdrawtrans/index/');
			}

			$transaction = Merchants::getWithdrawDetail($merchant_id,$trans_id);
			
			if (!sizeof($transaction)) $this->_redirect('/Withdrawtrans/index/');
			$this->view->transaction = $transaction;
			$currency = new Zend_Currency('en_US');
			$this->view->symbol= $currency->getSymbol();
			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('searchbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));
		}

		function changestatusAction (){
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$get = $this->getRequest()->getParams();
			if (isset($get['mid']) && $get['mid'] > 0) {
				$merchant_id = $get['mid'];
			} else {
				$this->_redirect('/Withdrawtrans/index');
			}
			
			if (isset($get['id']) && $get['id'] > 0) {
				$trans_id = $get['id'];
			} else {
				$this->_redirect('/Withdrawtrans/index/');
			}


			if (isset($get['newStatus']) && $get['newStatus'] > 0) {
				$newStatus = $get['newStatus'];
				Merchants::changeStatus($merchant_id,$trans_id,$newStatus);
			}

			$this->_redirect('/Withdrawinfo/index/mid/'.$merchant_id.'/id/'.$trans_id);
		}
	}
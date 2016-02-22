<?php
	require_once(APPLICATION_PATH.'models/Merchants.php');
	class WithdrawController extends Zend_Controller_Action {
		function indexAction(){
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null || $merchant_id <=0) {
				$this->_redirect('/index');
			}

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml'));  
			$response->insert('footer', $this->view->render('footer.phtml')); 

			$merchant_info = Merchants::getMerchantInfo($merchant_id);
			$currency = new Zend_Currency('en_US');
			$merchant_info['symbol'] = $currency->getSymbol();

			$this->view->merchant_info = $merchant_info;

		}

		function confirmAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null || $merchant_id <=0) {
				$this->_redirect('/index');
			}

			$post = $this->getRequest()->getParams();
			if (isset($post['withdraw_amount']) && is_numeric($post['withdraw_amount']) && $post['withdraw_amount'] > 0) {
				$rtn = Merchants::setWithdraw($merchant_id,$post['withdraw_amount'],$post['widthdraw_desc']);
				$error = $rtn[0];
				$error_info = $rtn[1];
			} else {
				$error = 1;
				$error_info = 'Please input the correct amount of withdraw.';
			}

			if ($error == 1) {
				$this->view->headScript()->setScript('alert("'.$error_info.'");');
			} else {
				$this->_redirect('/Withdrawtrans');
			}

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml'));  
			$response->insert('footer', $this->view->render('footer.phtml')); 

			$merchant_info = Merchants::getMerchantInfo($merchant_id);
			$currency = new Zend_Currency('en_US');
			$merchant_info['symbol'] = $currency->getSymbol();

			$this->view->merchant_info = $merchant_info;

			$this->render('index');
		}
	}
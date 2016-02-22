<?php
	require_once(APPLICATION_PATH.'models/Merchants.php');
	class LoginController extends Zend_Controller_Action {
		function indexAction(){
			if (!$this->getRequest()->isPost()) {
				return $this->_redirect('/');
			}
			$post = $this->getRequest()->getParams();
			$merchant_id = Merchants::merchantLogin($post['login_id'],$post['login_pass']);
			if ($merchant_id > 0) {
				$scnsp = Zend_Registry::get('scnsp');
				$scnsp->merchant_id = $merchant_id;
				$this->_redirect('Merchantoverview');
			} else {
				$this->_redirect('/');
			}
		}

		function logoffAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null || $merchant_id <=0) {
			} else {
				$scnsp->merchant_id = null;
				Zend_Registry::set('scnsp', $scnsp);
			}
			$this->_redirect('/index');
		}
		
		function forgotpassAction(){
			if($_POST){
				if(!$_POST['merchantName']||!$_POST['merchantEmail']){
					echo 'Merchant name and email is required.';
				}else{
					$return=Merchants::generateNewPass($_POST['merchantName'],$_POST['merchantEmail']);
					if($return==2){
						echo 'We has sent a new password to your email address';
					}else{
						echo 'We are very sorry to failed to gernerate a new password,May your Merchant name and email is incorrect';
					}
				}
				die();
			}
		
			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header.phtml')); 
			$response->insert('right', $this->view->render('loginbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 
		}
	}
?>
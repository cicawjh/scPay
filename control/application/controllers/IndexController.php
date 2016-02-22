<?php
	class IndexController extends Zend_Controller_Action {
		function indexAction(){
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('right.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

			require_once(APPLICATION_PATH.'models/Users.php');
			$merchants = Users::getTopMerchants(5);
			$this->view->merchants = $merchants;

			$lastTotal = Users::getLastYearTotal();
			$this->view->lastTotal = $lastTotal;
			$currency = new Zend_Currency('en_US');
			$this->view->symbol = $currency->getSymbol();
			
		}
	}
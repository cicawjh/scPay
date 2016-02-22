<?php
	require_once(APPLICATION_PATH.'models/Merchants.php');
	class WithdrawtransController extends Zend_Controller_Action {
		function indexAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('right', $this->view->render('right.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

			$withdraw_trans = Merchants::getAllWithdraw();
			$numPerPage = 20;
			if (isset($get['page']) && $get['page'] > 0) {
				$page = $get['page'];
			} else {
				$page = 1;
			}
			$paginator = Zend_Paginator::factory($withdraw_trans);
			$paginator->setCurrentPageNumber($page)->setItemCountPerPage($numPerPage);
			$this->view->paginator = $paginator;
			$this->view->page = $page;
			$this->view->numPerPage = $numPerPage;
			$this->view->withdrawtrans = $withdraw_trans;
			$currency = new Zend_Currency('en_US');
			$this->view->symbol= $currency->getSymbol();
		}
	}
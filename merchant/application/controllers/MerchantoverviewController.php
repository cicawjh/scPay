<?php
	require_once(APPLICATION_PATH.'models/Merchants.php');
	class MerchantoverviewController extends Zend_Controller_Action {
		function indexAction(){
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null || $merchant_id <=0) {
				$this->_redirect('/index');
			}
			
			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('right', $this->view->render('merchantbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

			$merchant_info = Merchants::getMerchantInfo($merchant_id);
			$currency = new Zend_Currency('en_US');
			$merchant_info['symbol'] = $currency->getSymbol();

			$this->view->merchant_info = $merchant_info;

			$get = $this->getRequest()->getParams();
			if (isset($get['page']) && $get['page'] > 1) {
				$page = $get['page'];
			} else {
				$page = 1;
			}

			$merchant_trans = Merchants::getLastTransaction($merchant_id);

						
			$numPerPage = 20;
			$paginator = Zend_Paginator::factory($merchant_trans[1]);
			$paginator->setCurrentPageNumber($page)->setItemCountPerPage($numPerPage);
			$this->view->paginator = $paginator;
			$this->view->page = $page;
			$this->view->numPerPage = $numPerPage;
			$this->view->merchant_trans = $merchant_trans;

		}
	}
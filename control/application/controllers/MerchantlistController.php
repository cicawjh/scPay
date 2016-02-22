<?php
	require_once(APPLICATION_PATH.'models/Merchants.php');
	class MerchantlistController extends Zend_Controller_Action {
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

			$merchantsList = Merchants::getAllMerchants();
			
			$numPerPage = 20;

			if (isset($get['page']) && $get['page'] > 0) {
				$page = $get['page'];
			} else {
				$page = 1;
			}

			$paginator = Zend_Paginator::factory($merchantsList);
			$paginator->setCurrentPageNumber($page)->setItemCountPerPage($numPerPage);
			$this->view->paginator = $paginator;
			$this->view->page = $page;
			$this->view->numPerPage = $numPerPage;
			$this->view->merchantsList = $merchantsList;
			$currency = new Zend_Currency('en_US');
			$this->view->symbol = $currency->getSymbol();
		}

		function searchAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}
			
			$strWhere = '';
			
			$post = $this->getRequest()->getParams();
			if (isset($post['m_name']) && $post['m_name'] !='') {
				$strWhere .= ' and merchant_name ="'.$post['m_name'].'"';
				$this->view->m_name = $post['m_name'];
			}
			
			if (isset($post['m_number']) && $post['m_number'] !='') {
				$strWhere .= ' and merchant_number ="'.$post['m_number'].'"';
				$this->view->m_number = $post['m_number'];
			}

			if (isset($post['m_email']) && $post['m_email'] !='') {
				$strWhere .= ' and merchant_email ="'.$post['m_email'].'"';
				$this->view->m_email = $post['m_email'];
			}

			if (isset($post['m_status']) && $post['m_status'] >0) {
				$strWhere .= ' and mstatusid ="'.$post['m_status'].'"';
				$this->view->m_status = $post['m_status'];
			}

			$merchantsList = Merchants::getAllMerchants($strWhere);
	
			$get = $this->getRequest()->getParams();
			if (isset($get['page']) && $get['page'] > 1) {
				$page = $get['page'];
			} else {
				$page = 1;
			}
			
						
			$numPerPage = 20;

			$paginator = Zend_Paginator::factory($merchantsList);
			$paginator->setCurrentPageNumber($page)->setItemCountPerPage($numPerPage);
			$this->view->paginator = $paginator;
			$this->view->page = $page;
			$this->view->numPerPage = $numPerPage;
			$this->view->merchantsList = $merchantsList;

			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('right.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));

			$this->render("index");
		}
	}
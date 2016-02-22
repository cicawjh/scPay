<?php
	require_once(APPLICATION_PATH.'models/Users.php');
	require_once(APPLICATION_PATH.'models/Merchants.php');
	class MerchanttransController extends Zend_Controller_Action {
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

		function indexAction(){
			$get = $this->getRequest()->getParams();
			if (isset($get['id']) && $get['id'] > 0) {
				$merchant_id = $get['id'];
			} else {
				$this->_redirect('/Merchantlist');
			}
			
			$scnsp = Zend_Registry::get('scnsp');
			if (isset($get['begin']) && strtotime($get['begin']) !== false) {
				$begin = date('Y-m-d',strtotime($get['begin']));
				$scnsp->begin_date = $begin;
			}elseif (isset($scnsp->begin_date) && strtotime($scnsp->begin_date) !== false) {
				$begin = $scnsp->begin_date;
			} else {
				$begin = date('Y-m-d',mktime(0, 0, 0, date("m")-1, date("d"),   date("Y")));
			}

			if(isset($get['to']) && strtotime($get['to']) !== false) {
				$to = date('Y-m-d',strtotime($get['to']));
				$scnsp->to_date = $to;
			} elseif (isset($scnsp->to_date) && strtotime($scnsp->to_date) !== false) {
				$to = $scnsp->to_date;
			} else {
				$to = date('Y-m-d');
			}
			
				

			$merchant_info = Merchants::getMerchantInfo($merchant_id);
			if (!sizeof($merchant_info)) {
				$this->_redirect('/Merchantlist');
			}
			$currency = new Zend_Currency('en_US');
			$merchant_info['symbol'] = $currency->getSymbol();

			$str_where = ' and m.trans_date between "'.$begin.'" and "'.$to.' 23:59:59"';

			if(isset($get['inv_no']) && $get['inv_no'] !== '') {
				$str_where .= ' and m.trans_invoice like "'.$get['inv_no'].'%"'; 
				$this->view->inv_no = $get['inv_no'];
			}

			if(isset($get['customer']) && $get['customer'] !== '') {
				$str_where .= ' and m.trans_ccowner like "%'.$get['customer'].'%"'; 
				$this->view->customer = $get['customer'];
			}

			if(isset($get['agtransid']) && $get['agtransid'] !== '') {
				$str_where .= ' and m.agtransid ="'.$get['agtransid'].'"'; 
				$this->view->agtransid = $get['agtransid'];
			}

			if(isset($get['ccno']) && $get['ccno'] !== '' && is_numeric($get['ccno'])) {
				$str_where .= ' and m.trans_ccnum like "%'.$get['ccno'].'"'; 
				$this->view->ccno = $get['ccno'];
			}

			$merchant_trans = Merchants::getAllTransaction($merchant_id,$str_where);

			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('right.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

			$numPerPage = 20;
			//$numPerPage = 5;
			if (isset($get['page']) && $get['page'] > 0) {
				$page = $get['page'];
			} else {
				$page = 1;
			}
			$paginator = Zend_Paginator::factory($merchant_trans);
			$paginator->setCurrentPageNumber($page)->setItemCountPerPage($numPerPage);
			$this->view->paginator = $paginator;
			$this->view->page = $page;
			$this->view->numPerPage = $numPerPage;
			$this->view->transaction = $merchant_trans;
			$this->view->merchant_info = $merchant_info;
			$this->view->begin = $begin;
			$this->view->to = $to;
		}
	}
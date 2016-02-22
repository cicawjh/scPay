<?php
	require_once(APPLICATION_PATH.'models/Merchants.php');
	
	class TransactionController extends Zend_Controller_Action {
		function indexAction(){
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null || $merchant_id <=0) {
				$this->_redirect('/index');
			}
			
			$this->view->search_form = $this->getSearchForm();

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('right', $this->view->render('searchbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 
			
			$merchant_info = Merchants::getMerchantInfo($merchant_id);
			$currency = new Zend_Currency('en_US');
			$merchant_info['symbol'] = $currency->getSymbol();

			$get = $this->getRequest()->getParams();
			if (isset($get['page']) && $get['page'] > 1) {
				$page = $get['page'];
			} else {
				$page = 1;
			}
			
			$strWhere = ' and m.transtypeid !=2';
			$transaction = Merchants::getAllTransaction($merchant_id,$strWhere);
						
			$numPerPage = 20;
			$paginator = Zend_Paginator::factory($transaction);
			$paginator->setCurrentPageNumber($page)->setItemCountPerPage($numPerPage);
			$this->view->paginator = $paginator;
			$this->view->page = $page;
			$this->view->numPerPage = $numPerPage;
			$this->view->merchant_info = $merchant_info;
			$this->view->transaction = $transaction;
		}

		function searchAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null || $merchant_id <=0) {
				$this->_redirect('/index');
			}
			
			$form = $this->getSearchForm();
			
			$this->view->search_form = $form;
			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('right', $this->view->render('searchbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

			if (!$form->isValid($_POST)) {		
				return $this->_redirect('/Transaction/index');
			}
			
			if (!$this->getRequest()->isPost()) {
				$values=$scnsp->TransactionForm;
			}else{
				$scnsp->TransactionForm=$form->getValues();
				$values = $form->getValues();
			}
			
			$strWhere = ' and m.transtypeid !=2';
			if ($values['beginDate'] != '' && strtotime($values['beginDate'])) {
				$strWhere .= ' and m.trans_date >="'.$values['beginDate'].'"';
			}

			if ($values['endDate'] != '' && strtotime($values['endDate'])) {
				$strWhere .= ' and m.trans_date <="'.$values['endDate'].' 23:59:59"';
			}

			if ($values['credit_no'] !='' &&  is_numeric($values['credit_no'])) {
				$strWhere .= ' and m.trans_ccnum like "%'.$values['credit_no'].'"';
			}
			
			if ($values['trans_status'] !='' &&  is_numeric($values['trans_status'])) {
				if($values['trans_status']!='4'){ //非锁定transaction,直接查询状态
					$strWhere .= ' and m.transtatusid='.$values['trans_status'];
				}else{
					$strWhere .= ' and tpl.locked=1';
				}
			}

			if (isset($values['trans_invoice']) && $values['trans_invoice'] !='') {
				$trans_invoice = $values['trans_invoice'];
			} else {
				$trans_invoice = '';
			}

			$merchant_info = Merchants::getMerchantInfo($merchant_id);		
			$currency = new Zend_Currency('en_US');
			$merchant_info['symbol'] = $currency->getSymbol();

			$get = $this->getRequest()->getParams();
			if (isset($get['page']) && $get['page'] > 1) {
				$page = $get['page'];
			} else {
				$page = 1;
			}
			
			$transaction = Merchants::getAllTransaction($merchant_id,$strWhere,$trans_invoice);
			if ($values['transType'] == 'download') {
				$export_output = '';
				$export_fields = array('TransID','Inv#','Type','Status','Time','Customer','CC#','Amount','Fee','Net');
				$export_output .= $this->putCsv($export_fields);
				if (sizeof($transaction)) {
					foreach ($transaction as $key => $val) {
						$csvarray = array($val['ptransid'],$val['trans_invoice'],$val['transtype_name'],$val['transtatus_name'],$val['trans_date'],$val['trans_ccowner'],substr($val['trans_ccnum'],0,8) . str_repeat('X',strlen($val['trans_ccnum']) - 12).substr($val['trans_ccnum'],-4),$val['trans_amount'],$val['trans_fee'],$val['trans_net']);
						$export_output .= $this->putCsv($csvarray);
					}	
				}
				$export_file = 'export-' . date('Y-m-d-His') . '.csv';
				header ('Content-type: application/x-octet-stream');
				header ('Content-disposition: attachment; filename=' . $export_file);
				echo $export_output;
				exit();
			}
						
			$numPerPage = 20;
			$paginator = Zend_Paginator::factory($transaction);
			$paginator->setCurrentPageNumber($page)->setItemCountPerPage($numPerPage);
			$this->view->paginator = $paginator;
			$this->view->page = $page;
			$this->view->numPerPage = $numPerPage;
			$this->view->merchant_info = $merchant_info;
			$this->view->transaction = $transaction;
			$this->render('index');
		}

		function getSearchForm() {
			$form = new Zend_Form;
			$request = $this->getRequest();
			$baseurl = $request->getBaseUrl();
			$form->setAction($baseurl.'/Transaction/search')
				 ->setMethod('post')
				 ->setAttrib('id','search');
					
			// Create and configure username element:
			$beginDate = $form->createElement('text', 'beginDate',array('label' => 'Begin Date:'));
			$beginDate->addValidator('Date');

			$endDate = $form->createElement('text', 'endDate',array('label' => 'End Date:'));
			$endDate->addValidator('Date');

			$trans_invoice = $form->createElement('text', 'trans_invoice',array('label' => 'Invoice#:'));

			$credit_no = $form->createElement('text', 'credit_no',array('label' => 'Credit Card#(Last 4 digital):'));
			
			$trans_statusOption=Merchants::getAllTranstatus();
			
			$OptionArr=array(''=>'All Status');
			if(is_array($trans_statusOption)){
				foreach($trans_statusOption as $option){
					if($option['mss']){
						$OptionArr[$option['transtatusid']]=$option['transtatus_name'];
					}
				}
			}
			
			$trans_status = $form->createElement('select','trans_status',array('multiOptions' => $OptionArr,'label'=>'Status:'));

			$transType = $form->createElement('hidden', 'transType',array('value' => 'search'));

			$post = $request->getParams();
			$default_array = array(
									'beginDate' =>isset($post['beginDate'])?$post['beginDate']:'',
									'endDate'=>isset($post['endDate'])?$post['endDate']:'',
									'trans_invoice'=>isset($post['trans_invoice'])?$post['trans_invoice']:'',
									'credit_no'=>isset($post['credit_no'])?$post['credit_no']:''
									);

			// Add elements to form:
			$form->addElement($beginDate)
				 ->addElement($endDate)
				 ->addElement($trans_invoice)
				 ->addElement($credit_no)
				 ->addElement($trans_status)
				 ->addElement($transType)
				 ->addElement('submit', 'Search', array('label' => 'Search','class'=>'button'))
				 ->addElement('button', 'Download', array('label' => 'Download','class'=>'button','onclick'=>'document.getElementById("transType").value="download";document.getElementById("search").submit();'));
			
			$form->setDefaults($default_array);
			return $form;
		}

		function putCsv ($array, $deliminator=',') { 
			$line = ""; 
			foreach($array as $val) { 
				$val = str_replace("\r\n", "\n", $val); 
				if(ereg("[$deliminator\"\n\r]", $val)) { 
					$val = '"'.str_replace('"', '""', $val).'"'; 
				}
				$line .= $val.$deliminator; 
			}
			$line = substr($line, 0, (strlen($deliminator) * -1)); 
	  		$line .= "\n"; 
	  	  return $line;
		}
	}
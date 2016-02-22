<?php
	require_once(APPLICATION_PATH.'models/Merchants.php');
	class ReportController extends Zend_Controller_Action {
		
		function init(){
			$request = Zend_Controller_Front::getInstance()->getRequest(); 
			$base_url = $request->getBaseUrl();
			$base_url .= '/public';
			$this->view->headLink()->appendStylesheet($base_url.'/js/jquery.jqplot.0.9.7/jquery.jqplot.css');
			$this->view->headScript()->appendFile($base_url.'/js/jquery.jqplot.0.9.7/jquery.jqplot.js');
		}
		
		function indexAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null || $merchant_id <=0) {
				$this->_redirect('/index');
			}
			
			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('right', $this->view->render('reportright.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));
		}
		
		function dailyreportAction(){
		
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null || $merchant_id <=0) {
				$this->_redirect('/index');
			}
			
			$db = Zend_Registry::get('db');
			
			$this->view->beginDate=$this->view->beginDate?$this->view->beginDate:date('Y-m-d',mktime(0, 0, 0, date("m")-1, date("d"),date("Y")));;
			$endDate='2010-05-03';
			
			$begintimestamp=strtotime($beginDate);
			$endtimestamp=strtotime($endDate);
			
			while($begintimestamp<=$endtimestamp){
				$dailyPoint[date('Y-m-d',$begintimestamp)]=0;
				$begintimestamp+= 60*60*24;
			}
			
			//get received amount
			$sql="select sum(trans_amount) as trans_amount,sum(trans_net) as trans_net,DATE_FORMAT(trans_date,'%Y-%m-%d') as trans_date from ".TABLE_PTRANS." as pt where pt.mid=".$merchant_id." and pt.transtatusid=2 and pt.transtypeid=1 and trans_date BETWEEN '".$beginDate."' AND '".$endDate."' group by DATE_FORMAT(trans_date,'%Y%m%d') order by DATE_FORMAT(trans_date,'%Y%m%d')";
			
			$daily_receivedData=array();
			if($tempdaily_receivedData=$db->fetchAll($sql)){
			
				foreach($tempdaily_receivedData as $v){
					$daily_receivedData[$v['trans_date']]=$v;
				}
			}
			$daily_receivedData['data']=array_merge($dailyPoint,$daily_receivedData);
			$daily_receivedData['label']='Received';
			
			$this->view->Data=array();
			

			
			//get refund amount
			$sql="select abs(sum(trans_amount)) as trans_amount,abs(sum(trans_net)) as trans_net,DATE_FORMAT(trans_date,'%Y-%m-%d') as trans_date from ".TABLE_PTRANS." as pt where pt.mid=".$merchant_id." and pt.transtatusid=2 and pt.transtypeid=8 and trans_date BETWEEN '".$beginDate."' AND '".$endDate."' group by DATE_FORMAT(trans_date,'%Y%m%d') order by DATE_FORMAT(trans_date,'%Y%m%d')";
			
			$daily_refundData=array();
			if($tempdaily_refundData=$db->fetchAll($sql)){
			
				foreach($tempdaily_refundData as $v){
					$daily_refundData[$v['trans_date']]=$v;
				}
			}
			$daily_refundData['data']=array_merge($dailyPoint,$daily_refundData);
			$daily_refundData['label']='Refund';
			
			$this->view->Data['daily_refundData']=$daily_refundData;
			
			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));
		}
		
		function reporttransAction(){
			
			$request=$this->getRequest();
			
			$status=Merchants::statTransStatus();
			
			$this->view->begindate =$this->view->begindate?$this->view->begindate:date('Y-m-d',mktime(0, 0, 0, date("m")-1, date("d"),date("Y")));
			
			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('right', $this->view->render('reportright.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));
			
		}
		
		function reporttrans1Action(){
			
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			
			if ($merchant_id == null || $merchant_id <=0) {
				echo '<tr><td cols="10">please login</td></tr>';die();
			}
			
			$post=$this->getRequest()->getParams();
			
			
			if (isset($post['start_date']) && strtotime($post['start_date'])) {
				$start_date = $post['start_date'];
			} else {
				$start_date = date("Y-m-d");
			}

			if (isset($post['end_date']) && strtotime($post['end_date'])) {
				$end_date = $post['end_date'];
			} else {
				$end_date = date("Y-m-d");
			}
			
			$reports = Merchants::getReports1($merchant_id,$start_date,$end_date);
			echo Zend_Json::encode($reports);;
			die();
			
			
		}
		
		function gettlAction(){
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			
			if ($merchant_id == null || $merchant_id <=0) {
				echo '<tr><td cols="10">please login</td></tr>';die();
			}
			
			$request=$this->getRequest();
			
			if (!$request->isPost()) {
				$values=$scnsp->TransactionForm;
			}else{
				$scnsp->TransactionForm=$request->getPost();
				$values = $request->getPost();
			}
			
			//if($request->isPost()){
				//$strWhere = ' and m.transtypeid !=2';
			if ($values['start_date'] != '' && strtotime($values['start_date'])) {
				$strWhere .= ' and m.trans_date >="'.$values['start_date'].'"';
			}

			if ($values['end_date'] != '' && strtotime($values['end_date'])) {
				$strWhere .= ' and m.trans_date <="'.$values['end_date'].' 23:59:59"';
			}
			
			if ($values['trans_status'] !='' &&  is_numeric($values['trans_status'])) {
				if($values['trans_status']!='4'){ //非锁定transaction,直接查询状态
					$strWhere .= ' and m.transtatusid='.$values['trans_status'];
				}else{
					$strWhere .= ' and tpl.locked=1';
				}
			}
			
			if ($values['trans_type'] !='' &&  is_numeric($values['trans_type'])) {
				if($values['trans_type']!='4'){ //非锁定transaction,直接查询状态
					$strWhere .= ' and m.transtypeid='.$values['trans_type'];
				}else{
					$strWhere .= ' and tpl.locked=1';
				}
			}
				
			if (isset($values['trans_invoice']) && $values['trans_invoice'] !='') {
				$trans_invoice = $values['trans_invoice'];
			} else {
				$trans_invoice = '';
			}
			
			$query=$request->getParams();
			$values['page']=$query['page'];
			
			if (isset($values['page']) && $values['page'] > 1) {
				$page = $values['page'];
			} else {
				$page = 1;
			}
		//	}
			$numPerPage = 20;
			$start=intval($values['page'])?(intval($values['page'])-1)*$numPerPage:0;
			$limit=" limit $start,$numPerPage";
			
			$this->view->transtypes=array('1'=>'Receive','2'=>'Withdraw','3'=>'Dispute','4'=>'Chargeback','5'=>'Month Fee','6'=>'Bouns','7'=>'Other','8'=>'Refund');
			$this->view->transtatus=array('1'=>'Pending','2'=>'Completed','3'=>'Rejected','4'=>'Locked');
			
			$this->_helper->layout->disableLayout();
			$this->view->post=$values;
			$this->view->transaction=Merchants::getAllTransaction ($merchant_id,$strWhere,$trans_invoice,$limit);
			
			$statinfo=Merchants::getAllTransaction ($merchant_id,$strWhere,$trans_invoice,false);

			$recordcount=$statinfo[0]['cnt'];
			$this->view->statinfo=$statinfo[0];
			
			
			$paginator = Zend_Paginator::factory(range(1,$recordcount));
			$paginator->setCurrentPageNumber($page)->setItemCountPerPage($numPerPage);
			$this->view->paginator = $paginator;
			$this->view->page = $page;
			$this->view->numPerPage = $numPerPage;
			
		}

		function searchAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null || $merchant_id <=0) {
				$this->_redirect('/index');
			}
			
			if (!$this->getRequest()->isPost()) {
				return $this->_redirect('/Report');
			}
			
			$request = Zend_Controller_Front::getInstance()->getRequest(); 
			$base_url = $request->getBaseUrl();
			$base_url .= '/public';

			$post = $this->getRequest()->getParams();

			if (isset($post['start_date']) && strtotime($post['start_date'])) {
				$start_date = $post['start_date'];
			} else {
				$start_date = date("Y-m-d");
			}

			if (isset($post['end_date']) && strtotime($post['end_date'])) {
				$end_date = $post['end_date'];
			} else {
				$end_date = date("Y-m-d");
			}


			$this->view->headScript()->appendFile($base_url.'/JS/jquery.jqplot.0.9.7/plugins/jqplot.barRenderer.min.js');
			$this->view->headScript()->appendFile($base_url.'/JS/jquery.jqplot.0.9.7/plugins/jqplot.categoryAxisRenderer.min.js');
			$this->view->headScript()->appendFile($base_url.'/JS/jquery.jqplot.0.9.7/plugins/jqplot.highlighter.js');

			$reports = Merchants::getReports($merchant_id,$start_date,$end_date);
			$this->view->reports = $reports;
			$this->view->start_date = $start_date;
			$this->view->end_date = $end_date;
			$this->view->merchant_info = Merchants::getMerchantInfo($merchant_id);
			
			
			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('right', $this->view->render('reportright.phtml'));  
			$response->insert('footer', $this->view->render('footer.phtml'));
		}
	}
<?php
	require_once(APPLICATION_PATH.'models/Users.php');
	class SystemsetController extends Zend_Controller_Action {
		function indexAction(){
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}
			
			//获取所有的Gateway列表
			$this->view->gateways = Users::getGateways();
			$this->view->users = Users::getUsers();

			$db = Zend_Registry::get('db');
			$rs = $db -> Query("select count(*) as cnt from ".TABLE_REFOUNDLOG."");
			$this->view ->logCount = $rs -> fetch();
			//获取所有的Users列表
			
			//read mail templates infomation
			$config=Zend_Registry::get('config');
			$this->view -> mailTpls = new Zend_Config_Xml(APPLICATION_PATH.$config->mail->templatepath.'/config.xml');
			$this->view -> mailTplPath=APPLICATION_PATH.$config->mail->templatepath;
			
			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 
		}
		
		function mailconfigAction(){
			
			$config=Zend_Registry::get('config');
			$configfile=APPLICATION_PATH.$config->mail->templatepath.'/config.xml';
			
			if($this->getRequest()->isPost()){
				$h=fopen($configfile,'w');
				fwrite($h,stripslashes($this->getRequest()->getPost('mailconfig')));
				fclose($h);
			}
			
			$response = $this->getResponse(); 
			
			$this->view->mailconfig=file_get_contents($configfile);
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 	
		}
	}

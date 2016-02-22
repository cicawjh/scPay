<?php
	require_once(APPLICATION_PATH.'models/Users.php');
	class GatewayinfoController extends Zend_Controller_Action {
		function indexAction(){
			$this->_redirect('/Systemset');
		}

		function addAction (){
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}
			
			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('right.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

			$this->view->info_form = $this->getGatewayForm();

			return $this->render('index');
		}

		function editAction (){
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$get = $this->getRequest()->getParams();
			if (isset($get['gid']) && $get['gid'] > 0) {
				$gatewayid = $get['gid'];
			} else {
				$this->_redirect('/Systemset');
			}

			$gateway_info = Users::getGatewayById($gatewayid);
			if (!sizeof($gateway_info)) {
				$this->_redirect('/Systemset');
			}

			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('right.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

			$this->view->info_form = $this->getGatewayForm('Edit',$gateway_info);
			return $this->render('index');
		}

		function saveinfoAction(){
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$get = $this->getRequest()->getParams();

			if (isset($get['gid']) && $get['gid'] > 0) {
				$gatewayid = $get['gid'];
				$gateway_info = Users::getGatewayById($gatewayid);
				if (!sizeof($gateway_info)) {
					$this->_redirect('/Systemset');
				}
				$form = $this->getGatewayForm('Edit',$gateway_info);
			} else {
				$gatewayid = 0;
				$gateway_info = array();
				$form = $this->getGatewayForm();
			}

			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('right.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 
			
			if (!$form->isValid($_POST)) {
				// Failed validation; redisplay form
				$this->view->info_form = $form;
				return $this->render('index');
			}

			$values = $form->getValues();
			if ($gatewayid > 0) {
				Users::changeGateway($gatewayid,$values);
			} else {
				$gatewayid = Users::addGateway($values);
				if ($gatewayid >0){
				} else {
					$this->view->info_form = $form;
					$this->view->headScript()->setScript('alert("Can not add this gateway, please try again!");');
					return $this->render('index');
				}
			}

			$this->_redirect('/Systemset');
		}

		function deleteAction (){
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}
			
			$get = $this->getRequest()->getParams();

			if (isset($get['gid']) && $get['gid'] > 0) {
				$gatewayid = $get['gid'];
				Users::deleteGateway($gatewayid);
			}
			
			$this->_redirect('/Systemset');
			
		}

		function getGatewayForm ($action='New',$gateway_info = array()) {
			$form = new Zend_Form;
			$request = $this->getRequest();
			$baseurl = $request->getBaseUrl();

			if ($action != 'New') {
				$get_str = '/gid/'.$gateway_info['gatewayid'];
			} else {
				$get_str = '';
			}

			$form->setAction($baseurl.'/Gatewayinfo/saveinfo'.$get_str)
				 ->setMethod('post')
				 ->setAttrib('id','register');

			$gateway_title = $form->createElement('text', 'gateway_title',array('label' => 'Title:'));
			$gateway_title->addValidator('stringLength', false, array(5))
						  ->setRequired(true);
			
			$gateway_url = $form->createElement('text', 'gateway_url',array('label' => 'Url:'));
			$gateway_url->addValidator('stringLength', false, array(5))
						 ->setRequired(true);

			$gateway_login = $form->createElement('text', 'gateway_login',array('label' => 'Login:'));
			$gateway_login->addValidator('stringLength', false, array(5))
						 ->setRequired(true);

			$gateway_key = $form->createElement('text', 'gateway_key',array('label' => 'Key:'));
			$gateway_key->addValidator('stringLength', false, array(5))
						 ->setRequired(true);
			
			$default_array = array();
			if ($action != 'New') {
				$default_array = array(
										'gateway_title' => $gateway_info['gateway_title'],
										'gateway_url' => $gateway_info['gateway_url'],
										'gateway_login' => $gateway_info['gateway_login'],
										'gateway_key' => $gateway_info['gateway_key']
									  );
			}

			$form->addElement($gateway_title)
				 ->addElement($gateway_url)
				 ->addElement($gateway_login)
			     ->addElement($gateway_key)
				 ->setDefaults($default_array)
				 ->addElement('submit', 'Save', array('label' => 'Save','class'=>'button'))
				 ->addElement('button', 'Cancel', array('label' => 'Cancel','class'=>'button', 'onClick'=>'location.href="'.$baseurl.'/Systemset"'));

			return $form;
		}
	}
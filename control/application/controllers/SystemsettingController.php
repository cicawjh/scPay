<?php
	require_once(APPLICATION_PATH.'models/Users.php');
	class SystemsettingController extends Zend_Controller_Action {
		function indexAction(){
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			if ($user_id != 1) {
				//如果不是超级用户，则只有修改自己账户密码的权限
				$this->_redirect('/Systemsetting/resetpass');
			}

			$config_info = Users::getConfiguration();
			$this->view->config_form = $this->getConfigForm($config_info);

			$this->view->pass_form = $this->getPassForm();
			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 
		}

		function resetpassAction (){
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}
			
			if ($user_id == 1) {
				$this->_redirect('/Systemsetting');
			}

			$this->view->pass_form = $this->getPassForm();
			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 
		}

		function saveconfigAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}
			
			$config_info = Users::getConfiguration();
			$config_form = $this->getConfigForm($config_info);

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));

			$this->view->pass_form = $this->getPassForm();
			
			if (!$config_form->isValid($_POST)) {
				// Failed validation; redisplay form
				$this->view->config_form = $config_form;
				return $this->render('index');
			}
			
			$values = $config_form->getValues();
			Users::setConfiguration($values);
			$this->_redirect('/Index');
		}

		function savepassAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}
			$config_info = Users::getConfiguration();
			$this->view->config_form = $this->getConfigForm($config_info);

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));

			$pass_form = $this->getPassForm();
			
			if (!$pass_form->isValid($_POST)) {
				// Failed validation; redisplay form
				$this->view->pass_form = $pass_form;
				return $this->render('index');
			}

			$values = $pass_form->getValues();
			Users::setAdminPass($user_id,$values);
			$this->_redirect('/Index');
		}
		function getConfigForm ($config_info=array()) {
			$form = new Zend_Form;
			$request = $this->getRequest();
			$baseurl = $request->getBaseUrl();

			$form->setAction($baseurl.'/Systemsetting/saveconfig')
				 ->setMethod('post')
				 ->setAttrib('id','register');

			$aglogin = $form->createElement('text', 'aglogin',array('label' => 'Authorize Login:'));
			$aglogin->addValidator('stringLength', false, array(5))
						 ->setRequired(true);
			
			$agkey = $form->createElement('text', 'agkey',array('label' => 'Authorize Key:'));
			$agkey->addValidator('stringLength', false, array(5))
						 ->setRequired(true);

			$default_array = array(
									'aglogin' => $config_info['CFG_MODULE_PAYMENT_AUTHORIZENET_LOGIN'],
									'agkey' => $config_info['CFG_MODULE_PAYMENT_AUTHORIZENET_TXNKEY']
								  );

			$form->addElement($aglogin)
				 ->addElement($agkey)
				 ->setDefaults($default_array)
				 ->addElement('submit', 'Save', array('label' => 'Save','class'=>'button'));

			return $form;
		}

		function getPassForm () {
			$form = new Zend_Form;
			$request = $this->getRequest();
			$baseurl = $request->getBaseUrl();

			$form->setAction($baseurl.'/Systemsetting/savepass')
				 ->setMethod('post')
				 ->setAttrib('id','register');


			$password = $form->createElement('password', 'password',array('label' => 'New Password:'));
			$password->addValidator('StringLength', false, array(6))
					 ->setRequired(true);

			$password_confirm = $form->createElement('password', 'password_confirm',array('label' => 'Confirm Password:'));
			$password_confirm ->addValidator('PasswordConfirmation')
							  ->setRequired(true);

			$form->addElement($password)
				 ->addElement($password_confirm)
				 ->addElement('submit', 'Save', array('label' => 'Save','class'=>'button'));

			return $form;
		}
	}
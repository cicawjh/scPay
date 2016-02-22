<?php
	require_once(APPLICATION_PATH.'models/Merchants.php');
	
	class RegisterController extends Zend_Controller_Action {
		 function indexAction(){
				$response = $this->getResponse(); 
			
				$response->insert('header', $this->view->render('header.phtml')); 
				//$response->insert('right', $this->view->render('loginbox.phtml')); 
				$response->insert('footer', $this->view->render('footer.phtml')); 

				$this->view->register_form = $this->getForm();
				$this->render('index');
		 }

		 function baseinfoAction() {
			if (!$this->getRequest()->isPost()) {
				return $this->_redirect('/');
			}

			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header.phtml')); 
			//$response->insert('right', $this->view->render('loginbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

			 $form = $this->getForm();
			 if (!$form->isValid($_POST)) {
				// Failed validation; redisplay form
				$this->view->register_form = $form;
				return $this->render('index');
			 }
			
			//Insert data
			$values = $form->getValues();
			//print_r($values);
			
			$merchant_id = Merchants::registerInfo($values);
			if ($merchant_id <=0) {
				$this->view->register_form = $form;
				$this->view->headScript()->setScript('alert("Merchant is exists or duplicated email address!");');
				return $this->render('index');
			} else {
				$scnsp = Zend_Registry::get('scnsp');
				$scnsp->merchant_id = $merchant_id;
				
				//发送邮件
				//读取模板
				$emailinfo=Merchants::getmailtemplate('register');
				//如果读取成功处理模板,否则采用固定模板内容
				if($emailinfo){
				
					$email_subject = $emailinfo['subject'];
					$email_text = $emailinfo['body'];
					//替换模板中的变量
					if(preg_match_all('/\[\#(.*?)\#\]/',$email_text,$matchall)){
						foreach($matchall[1] as $var){
							eval('$v='.$var.';');
							$email_text=str_replace('[#'.$var.'#]',$v,$email_text);
						}
					}
					if(preg_match_all('/\[\#(.*?)\#\]/',$email_subject,$matchall)){
						foreach($matchall[1] as $var){
							eval('$v='.$var.';');
							$email_subject=str_replace('[#'.$var.'#]',$v,$email_subject);
						}
					}
					Merchants::sendMail($values['merchantname'],$values['emailaddress'],$email_subject,$email_text);
				}
				
				return $this->_redirect('/register/accountinfo');
			} 
		 }

		 function setaccountAction () {
			if (!$this->getRequest()->isPost()) {
				return $this->_redirect('/register/accountinfo');
			}
			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header.phtml')); 
			//$response->insert('right', $this->view->render('loginbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

			$form = $this->getAccountForm();
			if (!$form->isValid($_POST)) {
				// Failed validation; redisplay form
				$this->view->account_form = $form;
				return $this->render('account');
			}

			$values = $form->getValues();

			$maccid = Merchants::registerAccount($values);
			if ($maccid >0){
				return $this->_redirect('/register/complete');
			} else {
				$this->view->account_form = $form;
				$this->view->headScript()->setScript('alert("Can not register your account info, please try again!");');
				return $this->render('account');
			}
			
		 }

		 function accountinfoAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null) {
				$this->_redirect('/register/index');
			}

			$response = $this->getResponse(); 

			$response->insert('header', $this->view->render('header.phtml')); 
			//$response->insert('right', $this->view->render('loginbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

			$this->view->account_form = $this->getAccountForm();
			return $this->render('account');
		 }

		 function completeAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null) {
				$this->_redirect('/register/index');
			}

			$response = $this->getResponse(); 

			$response->insert('header', $this->view->render('header.phtml')); 
			//$response->insert('right', $this->view->render('loginbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 
			$this->render('complete');
		 }

		 function getForm(){
			$form = new Zend_Form;
			$request = $this->getRequest();
			$baseurl = $request->getBaseUrl();
			$form->setAction($baseurl.'/register/baseinfo')
				 ->setMethod('post')
				 ->setAttrib('id','register');
			

			// Create and configure username element:
			$merchantname = $form->createElement('text', 'merchantname',array('label' => 'Merchant Name:'));
			$merchantname->addValidator('stringLength', false, array(3))
						 ->setRequired(true);

			$emailaddress = $form->createElement('text', 'emailaddress',array('label' => 'Email Address:'));
			$emailaddress->addValidator('EmailAddress')
						 ->setRequired(true);


			$password = $form->createElement('password', 'password',array('label' => 'Password:'));
			$password->addValidator('StringLength', false, array(6))
					 ->setRequired(true);

			$password_confirm = $form->createElement('password', 'password_confirm',array('label' => 'Confirm Password:'));
			$password_confirm ->addValidator('PasswordConfirmation')
							  ->setRequired(true);

			$street = $form->createElement('text', 'street',array('label' => 'Street:'));
			$street ->addValidator('StringLength', false, array(6))
					 ->setRequired(true);

			$city = $form->createElement('text', 'city',array('label' => 'City:'));
			$city ->addValidator('StringLength', false, array(3))
					 ->setRequired(true);

			$state = $form->createElement('text', 'state',array('label' => 'State:'));
			$state ->addValidator('StringLength', false, array(3))
					 ->setRequired(true);

			$country = $form->createElement('text', 'country',array('label' => 'Country:'));
			$country ->addValidator('StringLength', false, array(3))
					 ->setRequired(true);

			$postcode = $form->createElement('text', 'postcode',array('label' => 'Postcode:'));
			$postcode ->addValidator('StringLength', false, array(3))
					 ->setRequired(true);

			$phone = $form->createElement('text', 'phone',array('label' => 'Phone:'));
			$phone ->addValidator('StringLength', false, array(3))
					 ->setRequired(true);

			$fax = $form->createElement('text', 'fax',array('label' => 'Fax:'));
			$fax ->addValidator('StringLength', false, array(3))
					 ->setRequired(true);


			// Add elements to form:
			$form->addElement($merchantname)
				 ->addElement($emailaddress)
				 ->addElement($password)
				 ->addElement($password_confirm)
				 ->addElement($street)
				 ->addElement($city)
				 ->addElement($state)
				 ->addElement($country)
				 ->addElement($postcode)
				 ->addElement($phone)
				 ->addElement($fax)
				 // use addElement() as a factory to create 'Login' button:
				 ->addElement('submit', 'Continue', array('label' => 'Continue','class'=>'button'));

			return $form;
		 }

		 function getAccountForm(){
			$form = new Zend_Form;
			$request = $this->getRequest();
			$baseurl = $request->getBaseUrl();
			$form->setAction($baseurl.'/register/setaccount')
				 ->setMethod('post')
				 ->setAttrib('id','register');

			// Create and configure username element:
			$cc_num = $form->createElement('text', 'cc_num',array('label' => 'Credit Card#:'));
			$cc_num->addValidator('Ccnum')
					->setRequired(true);

			$cc_owner = $form->createElement('text', 'cc_owner',array('label' => 'Owner:'));
			$cc_owner->addValidator('stringLength', false, array(3))
						 ->setRequired(true);

			
			$cc_cvv2 = $form->createElement('text', 'cc_cvv2',array('label' => 'Cvv2:'));
			$cc_cvv2->addValidator('StringLength', false, array(3))
					 ->setRequired(true);

			$cc_expire = $form->createElement('text', 'cc_expire',array('label' => 'Expire:'));
			$cc_expire ->addValidator('StringLength', false, array(3))
					   ->setRequired(true);

			$bf_acno = $form->createElement('text', 'bf_acno',array('label' => 'Bank Account#:'));
			$bf_acno ->addValidator('StringLength', false, array(10))
					 ->setRequired(true);

			$bf_name = $form->createElement('text', 'bf_name',array('label' => 'Account Name:'));
			$bf_name ->addValidator('StringLength', false, array(3))
					 ->setRequired(true);

			$bf_bkname = $form->createElement('text', 'bf_bkname',array('label' => 'Bank Name:'));
			$bf_bkname ->addValidator('StringLength', false, array(10))
					 ->setRequired(true);

			$bf_swift = $form->createElement('text', 'bf_swift',array('label' => 'Swift Code:'));
			$bf_swift ->addValidator('StringLength', false, array(10))
					 ->setRequired(true);

			$bf_address = $form->createElement('text', 'bf_address',array('label' => 'Bank Address:'));
			$bf_address ->addValidator('StringLength', false, array(10))
						->setRequired(true);


			// Add elements to form:
			$form->addElement($cc_num)
				 ->addElement($cc_owner)
				 ->addElement($cc_cvv2)
				 ->addElement($cc_expire)
				 ->addElement($bf_acno)
				 ->addElement($bf_name)
				 ->addElement($bf_bkname)
				 ->addElement($bf_swift)
				 ->addElement($bf_address)
				 // use addElement() as a factory to create 'Login' button:
				 ->addElement('submit', 'Complete', array('label' => 'Complete','class'=>'button'));

			return $form;
		 }
	}
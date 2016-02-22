<?php
	require_once(APPLICATION_PATH.'models/Merchants.php');
	class MerchantinfoController extends Zend_Controller_Action {
		function indexAction(){
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null) {
				$this->_redirect('/index');
			}

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('right', $this->view->render('merchantbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));
			
			$merchant_info = Merchants::getMerchantInfo($merchant_id);
			$this->view->merchant_info = $merchant_info;
			$this->view->info_form = $this->getInfoForm($merchant_info);
			$this->view->pass_form = $this->getPassForm();
			$this->view->account_form = $this->getAccountForm($merchant_info);

			
		}

		function saveinfoAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null) {
				$this->_redirect('/index');
			}

			if (!$this->getRequest()->isPost()) {
				return $this->_redirect('/Merchantinfo/index');
			}

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('right', $this->view->render('merchantbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));

			$merchant_info = Merchants::getMerchantInfo($merchant_id);
			$this->view->merchant_info = $merchant_info;
			$this->view->pass_form = $this->getPassForm();
			$this->view->account_form = $this->getAccountForm($merchant_info);

			$form = $this->getInfoForm($merchant_info);
			if (!$form->isValid($_POST)) {
				// Failed validation; redisplay form
				$this->view->info_form = $form;
				return $this->render('index');
			}

			$values = $form->getValues();
			$rtn = Merchants::changeInfo($merchant_id,$values);
			if ($rtn > 0){
				return $this->_redirect('/Merchantinfo/index');
			} else {
				$this->view->info_form = $form;
				//$this->view->headScript()->setScript('alert("Can not change your basic info, please try again!");');
				return $this->render('index');
			}
		}

		function savepassAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null) {
				$this->_redirect('/index');
			}

			if (!$this->getRequest()->isPost()) {
				return $this->_redirect('/Merchantinfo/index');
			}

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('right', $this->view->render('merchantbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));

			$merchant_info = Merchants::getMerchantInfo($merchant_id);
			$this->view->merchant_info = $merchant_info;
			$this->view->info_form = $this->getInfoForm($merchant_info);
			$this->view->account_form =	$this->getAccountForm($merchant_info);
			
			$form = $this->getPassForm();
			if (!$form->isValid($_POST)) {
				// Failed validation; redisplay form
				$this->view->pass_form = $form;
				return $this->render('index');
			}
			
			$values = $form->getValues();
			$rtn = Merchants::changPass($merchant_id,$values);
			if ($rtn > 0){
				return $this->_redirect('/Merchantinfo/index');
			} elseif ($rtn < 0) {
				$this->view->pass_form = $form;
				$this->view->headScript()->setScript('alert("Old password is not valid! Please input again");');
				return $this->render('index');
			} else {
				$this->view->pass_form = $form;
				return $this->render('index');
			}
		}

		function saveaccountAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$merchant_id = $scnsp->merchant_id;
			if ($merchant_id == null) {
				$this->_redirect('/index');
			}

			if (!$this->getRequest()->isPost()) {
				return $this->_redirect('/Merchantinfo/index');
			}

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('right', $this->view->render('merchantbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));

			$merchant_info = Merchants::getMerchantInfo($merchant_id);
			$this->view->merchant_info = $merchant_info;
			$this->view->info_form = $this->getInfoForm($merchant_info);
			$this->view->pass_form = $this->getPassForm();

			$form = $this->getAccountForm($merchant_info);
			if (!$form->isValid($_POST)) {
				// Failed validation; redisplay form
				$this->view->account_form = $form;
				return $this->render('index');
			}

			$values = $form->getValues();
			$rtn = Merchants::changeAccount($merchant_id,$values);
			if ($rtn > 0){
				return $this->_redirect('/Merchantinfo/index');
			} else {
				$this->view->account_form = $form;
				//$this->view->headScript()->setScript('alert("Can not change your account info, please try again!");');
				return $this->render('index');
			}
		}


		function getInfoForm ($merchant_info) {
			$form = new Zend_Form;
			$request = $this->getRequest();
			$baseurl = $request->getBaseUrl();
			$form->setAction($baseurl.'/Merchantinfo/saveinfo')
				 ->setMethod('post')
				 ->setAttrib('id','register');

			$street = $form->createElement('text', 'street',array('label' => 'Street:','value'=>$merchant_info['street']));
			$street ->addValidator('StringLength', false, array(6))
					 ->setRequired(true);

			$city = $form->createElement('text', 'city',array('label' => 'City:','value'=>$merchant_info['city']));
			$city ->addValidator('StringLength', false, array(3))
					 ->setRequired(true);

			$state = $form->createElement('text', 'state',array('label' => 'State:','value'=>$merchant_info['state']));
			$state ->addValidator('StringLength', false, array(3))
					 ->setRequired(true);

			$country = $form->createElement('text', 'country',array('label' => 'Country:','value'=>$merchant_info['country']));
			$country ->addValidator('StringLength', false, array(3))
					 ->setRequired(true);

			$postcode = $form->createElement('text', 'postcode',array('label' => 'Postcode:','value'=>$merchant_info['postcode']));
			$postcode ->addValidator('StringLength', false, array(3))
					 ->setRequired(true);

			$phone = $form->createElement('text', 'phone',array('label' => 'Phone:','value'=>$merchant_info['phone']));
			$phone ->addValidator('StringLength', false, array(3))
					 ->setRequired(true);

			$fax = $form->createElement('text', 'fax',array('label' => 'Fax:','value'=>$merchant_info['fax']));
			$fax ->addValidator('StringLength', false, array(3))
					 ->setRequired(true);


			// Add elements to form:
			$form->addElement($street)
				 ->addElement($city)
				 ->addElement($state)
				 ->addElement($country)
				 ->addElement($postcode)
				 ->addElement($phone)
				 ->addElement($fax)
				 // use addElement() as a factory to create 'Login' button:
				 ->addElement('submit', 'Save Info', array('label' => 'Save Info','class'=>'button'));

			return $form;
		}

		function getPassForm () {
			$form = new Zend_Form;
			$request = $this->getRequest();
			$baseurl = $request->getBaseUrl();
			$form->setAction($baseurl.'/Merchantinfo/savepass')
				 ->setMethod('post')
				 ->setAttrib('id','register');
			
			$oldpassword = $form->createElement('password', 'oldpassword',array('label' => 'Old Password:'));
			$oldpassword->addValidator('StringLength', false, array(6))
						->setRequired(true);

			$password = $form->createElement('password', 'password',array('label' => 'New Password:'));
			$password->addValidator('StringLength', false, array(6))
					 ->setRequired(true);

			$password_confirm = $form->createElement('password', 'password_confirm',array('label' => 'Confirm Password:'));
			$password_confirm ->addValidator('PasswordConfirmation')
							  ->setRequired(true);


			// Add elements to form:
			$form->addElement($oldpassword)
				 ->addElement($password)
				 ->addElement($password_confirm)
				 // use addElement() as a factory to create 'Login' button:
				 ->addElement('submit', 'Continue', array('label' => 'Save Password','class'=>'button'));

			return $form;
		}

		function getAccountForm($merchant_info){
			$form = new Zend_Form;
			$request = $this->getRequest();
			$baseurl = $request->getBaseUrl();
			$form->setAction($baseurl.'/Merchantinfo/saveaccount')
				 ->setMethod('post')
				 ->setAttrib('id','register');

			// Create and configure username element:
			$cc_num = $form->createElement('text', 'cc_num',array('label' => 'Credit Card#:', 'value'=>$merchant_info['cc_number']));
			$cc_num->addValidator('Ccnum')
					->setRequired(true);

			$cc_owner = $form->createElement('text', 'cc_owner',array('label' => 'Owner:', 'value'=>$merchant_info['cc_owner']));
			$cc_owner->addValidator('stringLength', false, array(3))
						 ->setRequired(true);

			
			$cc_cvv2 = $form->createElement('text', 'cc_cvv2',array('label' => 'Cvv2:', 'value'=>$merchant_info['cc_csv']));
			$cc_cvv2->addValidator('StringLength', false, array(3))
					 ->setRequired(true);

			$cc_expire = $form->createElement('text', 'cc_expire',array('label' => 'Expire:', 'value'=>$merchant_info['cc_valid']));
			$cc_expire ->addValidator('StringLength', false, array(3))
					   ->setRequired(true);

			$bf_acno = $form->createElement('text', 'bf_acno',array('label' => "Beneficiary's A/C No", 'value'=>$merchant_info['bf_acno']));
			$bf_acno ->addValidator('StringLength', false, array(10))
					 ->setRequired(true);

			$bf_name = $form->createElement('text', 'bf_name',array('label' => "Beneficiary's name:", 'value'=>$merchant_info['bf_name']));
			$bf_name ->addValidator('StringLength', false, array(3))
					 ->setRequired(true);

			$bf_bkname = $form->createElement('text', 'bf_bkname',array('label' => "Beneficiary's Bank:", 'value'=>$merchant_info['bf_acbank']));
			$bf_bkname ->addValidator('StringLength', false, array(10))
					 ->setRequired(true);

			$bf_swift = $form->createElement('text', 'bf_swift',array('label' => 'Swift Code:', 'value'=>$merchant_info['bf_bankswift']));
			$bf_swift ->addValidator('StringLength', false, array(10))
					 ->setRequired(true);

			$bf_address = $form->createElement('text', 'bf_address',array('label' => 'Bank Address:', 'value'=>$merchant_info['bf_bankaddress']));
			$bf_address ->addValidator('StringLength', false, array(10))
						->setRequired(true);

			$bf_cbank= $form->createElement('text', 'bf_cbank',array('label' => 'Intermediary Bank:','value'=>$merchant_info['bf_cbank']));
			
			// Add elements to form:
			$form->addElement($cc_num)
				 ->addElement($cc_owner)
				 ->addElement($cc_cvv2)
				 ->addElement($cc_expire)
				 ->addElement($bf_cbank)
				 ->addElement($bf_bkname)
				 ->addElement($bf_acno)
				 ->addElement($bf_name)
				 ->addElement($bf_swift)
				 ->addElement($bf_address)
				 // use addElement() as a factory to create 'Login' button:
				 ->addElement('submit', 'Complete', array('label' => 'Save Account','class'=>'button'));

			return $form;
		 }
	}
<?php
	require_once(APPLICATION_PATH.'models/Users.php');
	require_once(APPLICATION_PATH.'models/Merchants.php');
	class MerchantinfoController extends Zend_Controller_Action {
		var $scnsp;
		
		public function __construct(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response, array $invokeArgs = array())
		{
			parent::__construct($request,$response,$invokeArgs);
			$this->scnsp = Zend_Registry::get('scnsp');
			
			if($this->scnsp ->user_id == null){
				$this->_redirect('/Login');
			}
			
		}
		
		function indexAction(){
			$this->_redirect('/Merchantlist');
		}

		function addAction () {
			$scnsp = Zend_Registry::get('scnsp');
			

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('right.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

			$this->view->info_form = $this->getForm();
			$this->view->pass_form = null;

			return $this->render('index');
		}

		function editAction () {
			$scnsp = Zend_Registry::get('scnsp');

			$get = $this->getRequest()->getParams();
			if (isset($get['id']) && $get['id'] > 0) {
				$merchant_id = $get['id'];
			} else {
				$this->_redirect('/Merchantlist');
			}
			
			$merchant_info = Merchants::getMerchantInfo($merchant_id);
			if (!sizeof($merchant_info)) {
				$this->_redirect('/Merchantlist');
			}
			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('right.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

			$this->view->info_form = $this->getForm('Edit',$merchant_info);
			$this->view->pass_form = $this->changePassForm($merchant_info);
			$this->view->ip_purview = $this->merchantIpPurviewForm($merchant_info);
			return $this->render('index');
		}

		function setAction () {
			$scnsp = Zend_Registry::get('scnsp');

			$get = $this->getRequest()->getParams();
			if (isset($get['id']) && $get['id'] > 0) {
				$merchant_id = $get['id'];
			} else {
				$this->_redirect('/Merchantlist');
			}

			$merchant_info = Merchants::getMerchantInfo($merchant_id);
			if (!sizeof($merchant_info)) {
				$this->_redirect('/Merchantlist');
			}
			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('right.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

			$this->view->merchant_info = $merchant_info;
			return $this->render('setfee');
		}

		function rebuildAction (){
			$scnsp = Zend_Registry::get('scnsp');

			$get = $this->getRequest()->getParams();
			if (isset($get['id']) && $get['id'] > 0) {
				$merchant_id = $get['id'];
			} else {
				$this->_redirect('/Merchantlist');
			}

			$merchant_info = Merchants::getMerchantInfo($merchant_id);
			if (!sizeof($merchant_info)) {
				$this->_redirect('/Merchantlist');
			}

			/*开始向reservers 表中重新插入符合条件的数据*/
			$return = Merchants::rebuildReserve($merchant_id);
			if ($return == 0) {
				$this->view->headScript()->setScript('alert("Rebulid reserver data error!");');
			} else {
				$this->view->headScript()->setScript('alert("Rebulid reserver data success!");');
			}

			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('right.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

			$this->view->merchant_info = $merchant_info;
			return $this->render('setfee');
		}

		function saveinfoAction () {
			$scnsp = Zend_Registry::get('scnsp');

			$get = $this->getRequest()->getParams();

			if (isset($get['id']) && $get['id'] > 0) {
				$merchant_id = $get['id'];
				$merchant_info = Merchants::getMerchantInfo($merchant_id);
				$merchantstatus=$merchant_info['mstatusid'];
				if (!sizeof($merchant_info)) {
					$this->_redirect('/Merchantlist');
				}
				$form = $this->getForm('Edit',$merchant_info);
				$this->view->pass_form = $this->changePassForm($merchant_info);
			} else {
				$merchant_id = 0;
				$merchantstatus = 0;
				$merchant_info = array();
				$form = $this->getForm();
				$this->view->pass_form = null;
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
			if ($merchant_id > 0) {
				Merchants::changeInfo($merchant_id,$values);
							
				Merchants::changeAccount($merchant_id,$values);
			} else {
				$merchant_id = Merchants::registerInfo($values);
				if ($merchant_id >0){
					$scnsp->merchant_id = $merchant_id;
					$maccid = Merchants::registerAccount($values);
					if ($maccid <= 0){
						$this->view->account_form = $form;
						$this->view->headScript()->setScript('alert("Can not add this merchant account, please try again!");');
						return $this->render('index');
					}
				} else {
					$this->view->info_form = $form;
					$this->view->headScript()->setScript('alert("Can not add this merchant, please try again!");');
					return $this->render('index');
				}
			}
			
			//发送用户状态邮件
			if($merchant_id){
				if($merchantstatus==1&&$values['merchantstatus']==2){
					//发送审核通过邮件
					$emailinfo=Merchants::getmailtemplate('enabled');
				}elseif($merchantstatus==2&&$values['merchantstatus']==3){
					//发送暂停服务邮件
					$emailinfo=Merchants::getmailtemplate('suspend');
				}elseif($merchantstatus==3&&$values['merchantstatus']==2){
					//send email wheng status from suspended to enabled
					$emailinfo=Merchants::getmailtemplate('suspendedtoenabled');
				}

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
			}

			$this->_redirect('/Merchantlist');
		}

		function savefeeAction () {
			$scnsp = Zend_Registry::get('scnsp');
			
			if (isset($_POST['id']) && is_numeric($_POST['id']) && $_POST['id'] >0) {
				Merchants::setFee($_POST['id'],$_POST);
			}
			$this->_redirect('/Merchantlist');
		}

		function savepassAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$get = $this->getRequest()->getParams();
			if (isset($get['id']) && $get['id'] > 0) {
				$merchant_id = $get['id'];
			} else {
				$this->_redirect('/Merchantlist');
			}

			$merchant_info = Merchants::getMerchantInfo($merchant_id);
			if (!sizeof($merchant_info)) {
				$this->_redirect('/Merchantlist');
			}
			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('right.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

			$this->view->info_form = $this->getForm('Edit',$merchant_info);

			$form = $this->changePassForm($merchant_info);
			if (!$form->isValid($_POST)) {
				// Failed validation; redisplay form
				$this->view->pass_form = $form;
				return $this->render('index');
			}
			
			$values = $form->getValues();
			
			Merchants::changPass($merchant_id,$values);
			$this->_redirect('/Merchantlist');
		}
		
		function saveippurviewAction() {
			
			$this->view->ErrMsg=array();
			
			$get = $this->getRequest()->getParams();
			if (isset($get['id']) && $get['id'] > 0) {
				$merchant_id = $get['id'];
			} else {
				$this->_redirect('/Merchantlist');
			}
			
			if(!Merchants::setIpPurview($merchant_id,$get)){
				$this->view->ErrMsg['IpPurview']='Failed to save purview of ip.';
			}
			
			$this->editAction();
		}

		function changePassForm($merchant_info) {
			$form = new Zend_Form;
			$request = $this->getRequest();
			$baseurl = $request->getBaseUrl();
			$get_str = '/id/'.$merchant_info['mid'];
			$form->setAction($baseurl.'/Merchantinfo/savepass'.$get_str)
				 ->setMethod('post')
				 ->setAttrib('id','Changepass');

			$password = $form->createElement('password', 'password',array('label' => 'New Password:'));
			$password->addValidator('StringLength', false, array(6))
						 ->setRequired(true);

			$password_confirm = $form->createElement('password', 'password_confirm',array('label' => 'Confirm Password:'));
			$password_confirm ->addValidator('PasswordConfirmation')
								  ->setRequired(true);

			$form->addElement($password)
				 ->addElement($password_confirm);

			$form->addElement('submit', 'Save Pass', array('label' => 'Save Pass','class'=>'button'));

			return $form;
		}
		
		function merchantIpPurviewForm($merchant_info){
			$form=new Zend_Form();
			$request = $this->getRequest();
			$baseurl = $request->getBaseUrl();
			$get_str = '/id/'.$merchant_info['mid'];
			$form->setAction($baseurl.'/Merchantinfo/saveippurview'.$get_str)
				 ->setMethod('post')
				 ->setAttrib('name','ippurviewEdit');
				 
			$ipPurviewText = $form -> createElement('textarea','ipPurviewText',array('label'=>'Ip Filter(eg,192.168.1.* 192.168.*.* 192.168.1.3-192.168.1.127)','value'=>$merchant_info['ippurview']))
								   -> setAttrib('cols', '60')
								   -> setAttrib('rows' , '8');
			
			$form->addElement($ipPurviewText);
			
			$form->addElement('submit','Save',array('label'=>'Save'));
			
			return $form;
			
		}

		function getForm($action='New',$merchant_info = array()){
			$form = new Zend_Form;
			$request = $this->getRequest();
			$baseurl = $request->getBaseUrl();

			if ($action != 'New') {
				$get_str = '/id/'.$merchant_info['mid'];
			} else {
				$get_str = '';
			}
			$form->setAction($baseurl.'/Merchantinfo/saveinfo'.$get_str)
				 ->setMethod('post')
				 ->setAttrib('id','register');
			

			// Create and configure username element:
			$merchantname = $form->createElement('text', 'merchantname',array('label' => 'Merchant Name:'));
			$merchantname->addValidator('stringLength', false, array(3))
						 ->setRequired(true);
			
			$merchantstatus = $form->createElement('Select', 'merchantstatus',array('label' => 'Merchant Status:'));
			$merchantstatus->addMultiOption(1,'Sandbox')
						   ->addMultiOption(2,'Enable')
						   ->addMultiOption(3,'Suspend')
						   ->addMultiOption(4,'Close')
						   ->setRequired(true);

			$gatewayid = $form->createElement('Select', 'gatewayid',array('label' => 'GateWay:'));
			$gateways = Merchants::getGatewayList();
			for ($i=0;$i<sizeof($gateways);$i++){
				$gatewayid->addMultiOption($gateways[$i]['gatewayid'],$gateways[$i]['gateway_title']);
			}

			$emailaddress = $form->createElement('text', 'emailaddress',array('label' => 'Email Address:'));
			$emailaddress->addValidator('EmailAddress')
						 ->setRequired(true);

			
			if ($action == 'New') {
				$password = $form->createElement('password', 'password',array('label' => 'Password:'));
				$password->addValidator('StringLength', false, array(6))
						 ->setRequired(true);

				$password_confirm = $form->createElement('password', 'password_confirm',array('label' => 'Confirm Password:'));
				$password_confirm ->addValidator('PasswordConfirmation')
								  ->setRequired(true);
			}

			$merchantnum = $form->createElement('text', 'merchantnum',array('label' => 'Merchant Number:'));
			$merchantnum->addValidator('stringLength', false, array(8))
						 ->setRequired(true);

			$merchant_key = $form->createElement('text', 'merchant_key',array('label' => 'Merchant Key:'));
			$merchant_key->addValidator('stringLength', false, array(8))
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

			// Create and configure username element:
			$cc_num = $form->createElement('text', 'cc_num',array('label' => 'Credit Card#:'));
			if ($action == 'New') {
				$cc_num->addValidator('Ccnum')
						->setRequired(true);
			} else {
				$cc_num->addValidator('StringLength', false, array(8))
						->setRequired(true);
			}

			$cc_owner = $form->createElement('text', 'cc_owner',array('label' => 'Owner:'));
			$cc_owner->addValidator('stringLength', false, array(3))
						 ->setRequired(true);

			
			$cc_cvv2 = $form->createElement('text', 'cc_cvv2',array('label' => 'Cvv2:'));
			$cc_cvv2->addValidator('StringLength', false, array(3))
					 ->setRequired(true);

			$cc_expire = $form->createElement('text', 'cc_expire',array('label' => 'Expire:'));
			$cc_expire ->addValidator('StringLength', false, array(3))
					   ->setRequired(true);

			$bf_acno = $form->createElement('text', 'bf_acno',array('label' => "Beneficiary’s A/C No:"));
			$bf_acno ->addValidator('StringLength', false, array(10))
					 ->setRequired(true);

			$bf_name = $form->createElement('text', 'bf_name',array('label' => "Beneficiary’s name:"));
			$bf_name ->addValidator('StringLength', false, array(3))
					 ->setRequired(true);

			$bf_bkname = $form->createElement('text', 'bf_bkname',array('label' => "Beneficiary’s Bank:"));
			$bf_bkname ->addValidator('StringLength', false, array(10))
					 ->setRequired(true);

			$bf_swift = $form->createElement('text', 'bf_swift',array('label' => 'Swift Code:'));
			$bf_swift ->addValidator('StringLength', false, array(10))
					  ->setRequired(true);

			$bf_address = $form->createElement('text', 'bf_address',array('label' => 'Bank Address:'));
			$bf_address ->addValidator('StringLength', false, array(10))
						->setRequired(true);
						
			$bf_cbank= $form->createElement('text', 'bf_cbank',array('label' => 'Intermediary Bank:'));
			
			if ($action != 'New') {
				$default_array = array(
										'merchantname' => $merchant_info['merchant_name'],
										'merchantstatus' => $merchant_info['mstatusid'],
										'gatewayid' => $merchant_info['gatewayid'],
										'emailaddress' => $merchant_info['merchant_email'],
										'merchantnum' => $merchant_info['merchant_number'],
										'merchant_key' => $merchant_info['merchant_key'],
										'street' => $merchant_info['street'],
										'city' => $merchant_info['city'],
										'state' => $merchant_info['state'],
										'country' => $merchant_info['country'],
										'postcode' => $merchant_info['postcode'],
										'phone' => $merchant_info['phone'],
										'fax' => $merchant_info['fax'],
										'cc_num' => 'XXXX'.substr($merchant_info['cc_number'],-4,4),
										'cc_owner' => $merchant_info['cc_owner'],
										'cc_cvv2' => $merchant_info['cc_csv'],
										'cc_expire' => $merchant_info['cc_valid'],
										'bf_cbank' =>$merchant_info['bf_cbank'],
										'bf_bkname' => $merchant_info['bf_acbank'],
										'bf_acno' => $merchant_info['bf_acno'],
										'bf_name' => $merchant_info['bf_name'],
										'bf_swift' => $merchant_info['bf_bankswift'],
										'bf_address' => $merchant_info['bf_bankaddress']
										);
			}

			// Add elements to form:
			$form->addElement($merchantname)
				 ->addElement($merchantstatus)
				 ->addElement($gatewayid)
				 ->addElement($emailaddress);

			if ($action == 'New') {
				 $form->addElement($password)
					  ->addElement($password_confirm);
			}
			

			$form->addElement($merchantnum)
				 ->addElement('button', 'Generate Number', array('label' => 'Generate Number','class'=>'button', 'onClick'=>'document.getElementById(\'merchantnum\').value = rand(10000000000000);'));

			$form->addElement($merchant_key)
				 ->addElement('button', 'Generate Key', array('label' => 'Generate Key','class'=>'button', 'onClick'=>'document.getElementById(\'merchant_key\').value = rand(1000000000000000);'));
			
			$form->addElement($street)
				 ->addElement($city)
				 ->addElement($state)
				 ->addElement($country)
				 ->addElement($postcode)
				 ->addElement($phone)
				 ->addElement($fax)
				 ->addElement($cc_num)
				 ->addElement($cc_owner)
				 ->addElement($cc_cvv2)
				 ->addElement($cc_expire)
				 ->addElement($bf_cbank)
				 ->addElement($bf_bkname)
				 ->addElement($bf_acno)
				 ->addElement($bf_name)
				 ->addElement($bf_swift)
				 ->addElement($bf_address)
				 ;
			if ($action != 'New') {
				 $form->setDefaults($default_array);
			}
			 // use addElement() as a factory to create 'Login' button:
			 $form->addElement('submit', 'Complete', array('label' => 'Complete','class'=>'button'))
				  ->addElement('button', 'Cancel', array('label' => 'Cancel','class'=>'button', 'onClick'=>'location.href="'.$baseurl.'/Merchantlist"'));

			return $form;
		 }
		
	}
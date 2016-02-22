<?php
	require_once(APPLICATION_PATH.'models/Users.php');
	class UserinfoController extends Zend_Controller_Action {
		function indexAction(){
			$this->_redirect('/Systemset');
		}

		function addAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('right.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

			$this->view->info_form = $this->getUserForm();
			return $this->render('index');
		}
		
		function editAction(){
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}
			
			$post= $this->getRequest()->getParams();
			if($_POST){
				if(is_array($post['inherit'])){$roleid=serialize($post['inherit']);}
				$description=array(
					'realname'=>$post['realname'],
					'email'=>$post['email']
				);
				
				if($post['purview']){
					$purview=array();
					foreach($post['purview'] as $key=>$v){
						$purview[$v]=$post['purviewval'][$key];
					}
					$post['purview']=$purview;
				}
				
				Users::saveUserInfo(array(
					'roleid' =>$roleid,
					'purview' => serialize($post['purview']),
					'description'=>serialize($description)
				),$post['uid']);
			}
			
			$this->view->UserInfo=Users::getUserInfo($user_id);

			$this->view->AllRole=Merchants::getAllRole();
			$this->view->AllPurviews=Merchants::getAllPurviews();

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('right.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 
		}

		function deleteAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}
			
			$get = $this->getRequest()->getParams();

			if (isset($get['uid']) && $get['uid'] > 0) {
				$uid = $get['uid'];
				Users::deleteUser($uid);
			}
			
			$this->_redirect('/Systemset');
		}

		function changepassAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$get = $this->getRequest()->getParams();

			if (isset($get['uid']) && $get['uid'] > 0) {
				$uid = $get['uid'];
				$this->view->info_form = $this->getPassForm($uid);
				$response = $this->getResponse(); 
				
				$response->insert('header', $this->view->render('header2.phtml')); 
				$response->insert('footer', $this->view->render('footer.phtml')); 
				return $this->render('index');
			} else {
				$this->_redirect('/Systemset');
			}	
		}

		function saveuserAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));
			$info_form = $this->getUserForm();

			if (!$info_form->isValid($_POST)) {
				// Failed validation; redisplay form
				$this->view->info_form = $info_form;
				return $this->render('index');
			}

			$values = $info_form->getValues();
			$uid = Users::Adduser($values);
			if ($uid >0){
			} else {
				$this->view->info_form = $info_form;
				$this->view->headScript()->setScript('alert("Can not add this user, please try again!");');
				return $this->render('index');
			}
			$this->_redirect('/Systemset');
		}

		function savepassAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$response = $this->getResponse(); 
			$response->insert('header', $this->view->render('header2.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));

			$get = $this->getRequest()->getParams();

			if (isset($get['uid']) && $get['uid'] > 0) {
				$uid = $get['uid'];
			} else {
				$this->_redirect('/Systemset');
			}

			$info_form = $this->getPassForm($uid);
			
			if (!$info_form->isValid($_POST)) {
				// Failed validation; redisplay form
				$this->view->info_form = $info_form;
				return $this->render('index');
			}

			$values = $info_form->getValues();
			Users::setAdminPass($uid,$values);
			$this->_redirect('/Systemset');
		}
		
		function getUserForm() {
			$form = new Zend_Form;
			$request = $this->getRequest();
			$baseurl = $request->getBaseUrl();

			$form->setAction($baseurl.'/Userinfo/saveuser')
				 ->setMethod('post')
				 ->setAttrib('id','register');

			$user_name = $form->createElement('text', 'user_name',array('label' => 'User Name:'));
			$user_name->addValidator('stringLength', false, array(5))
						  ->setRequired(true);

			$password = $form->createElement('password', 'password',array('label' => 'New Password:'));
			$password->addValidator('StringLength', false, array(6))
					 ->setRequired(true);

			$password_confirm = $form->createElement('password', 'password_confirm',array('label' => 'Confirm Password:'));
			$password_confirm ->addValidator('PasswordConfirmation')
							  ->setRequired(true);

			$form->addElement($user_name)
				 ->addElement($password)
				 ->addElement($password_confirm)
				 ->addElement('submit', 'Save', array('label' => 'Save','class'=>'button'))
				 ->addElement('button', 'Cancel', array('label' => 'Cancel','class'=>'button', 'onClick'=>'location.href="'.$baseurl.'/Systemset"'));
			return $form;
		}

		function getPassForm ($uid) {
			$form = new Zend_Form;
			$request = $this->getRequest();
			$baseurl = $request->getBaseUrl();

			$form->setAction($baseurl.'/Userinfo/savepass/uid/'.$uid)
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
				 ->addElement('submit', 'Save', array('label' => 'Save','class'=>'button'))
				 ->addElement('button', 'Cancel', array('label' => 'Cancel','class'=>'button', 'onClick'=>'location.href="'.$baseurl.'/Systemset"'));
			return $form;
		}
	}
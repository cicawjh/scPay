<?php
	require_once(APPLICATION_PATH.'models/Users.php');
	class LoginController extends Zend_Controller_Action {
		function indexAction(){
			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header.phtml')); 
			//$response->insert('right', $this->view->render('loginbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 
		}

		function loginAction () {
			if (!$this->getRequest()->isPost()) {
				return $this->_redirect('/Index');
			}
			$post = $this->getRequest()->getParams();
			$user_id = Users::userLogin($post['login_id'],$post['login_pass']);
			if ($user_id > 0) {
				$scnsp = Zend_Registry::get('scnsp');
				$scnsp->user_id = $user_id;
				$scnsp->user_purview=Users::getUserPurview($user_id);
				$this->_redirect('/Index');
			} else {
				$this->_redirect('/Login');
			}
		}

		function logoffAction () {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null || $user_id <=0) {
			} else {
				$scnsp->user_id = null;
				Zend_Registry::set('scnsp', $scnsp);
			}
			$this->_redirect('/Login');
		}
	}
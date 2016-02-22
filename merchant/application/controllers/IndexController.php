<?php
	class IndexController extends Zend_Controller_Action {
		function indexAction(){
			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header.phtml')); 
			$response->insert('right', $this->view->render('loginbox.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 

		}
	}
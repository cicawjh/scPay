<?php
	require_once(APPLICATION_PATH.'models/Merchants.php');
	class MonthpaymentController extends Zend_Controller_Action {
		function indexAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('right.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml'));

			$this->view->pay_form = $this->getForm();
		}

		
		function chargeAction() {
			$scnsp = Zend_Registry::get('scnsp');
			$user_id = $scnsp->user_id;
			if ($user_id == null) {
				$this->_redirect('/Login');
			}

			$form = $this->getForm();

			$response = $this->getResponse(); 
			
			$response->insert('header', $this->view->render('header2.phtml')); 
			//$response->insert('right', $this->view->render('right.phtml')); 
			$response->insert('footer', $this->view->render('footer.phtml')); 
			
			if (!$form->isValid($_POST)) {
				// Failed validation; redisplay form
				$this->view->pay_form = $form;
				return $this->render('index');
			}

			$values = $form->getValues();
			
			$emailaddress = $values['emailaddress'];
			$paymentcomment = $values['paymentcomment'];
			$collecttype = $values['collecttype'];
			$paymentamount = $values['paymentamount'];
			$return = Merchants::monthCharge($emailaddress,$collecttype,$paymentamount,$paymentcomment);
			if ($return[0] == 0) {
				$error = $return[1];

				$this->view->pay_form = $form;
				$this->view->headScript()->setScript('alert("'.$error.'");');
				return $this->render('index');
			} else {
				$merchant_id = $return[0];
				$this->_redirect('/Merchanttrans/Index/id/'.$merchant_id);
			}
		}

		function getForm(){
			$form = new Zend_Form;
			$request = $this->getRequest();
			$baseurl = $request->getBaseUrl();

			$form->setAction($baseurl.'/Monthpayment/charge')
				 ->setMethod('post')
				 ->setAttrib('id','register');

			$emailaddress = $form->createElement('text', 'emailaddress',array('label' => 'Merchant Email:'));
			$emailaddress->addValidator('EmailAddress')
						 ->setRequired(true);

			$collecttype = $form->createElement('Select', 'collecttype',array('label' => 'Collect Type:','onChange'=>'update_payment(this.form)'));
			$collecttype->addMultiOption(5,'Month Fee')
					    ->addMultiOption(7,'Other')
					    ->setRequired(true);

			
			$paymentamount = $form->createElement('text', 'paymentamount',array('label' => 'Payment Amount:','value'=>'0','readOnly'=>'readOnly'));
			$paymentamount ->addValidator('Digits')
						   ->setRequired(true);

			$paymentcomment = $form->createElement('text', 'paymentcomment',array('label' => 'Description:'));

			$form->addElement($emailaddress)
				 ->addElement($collecttype)
				 ->addElement($paymentamount)
				 ->addElement($paymentcomment);

			$form->addElement('submit', 'Charge', array('label' => 'Charge','class'=>'button'))
				  ->addElement('button', 'Cancel', array('label' => 'Cancel','class'=>'button', 'onClick'=>'location.href="'.$baseurl.'/Merchantlist"'));

			return $form;
		}
	}
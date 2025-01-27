<?php

class Frontend_Form_Login extends CB_Form_Form {

	public function init(){
		$this->initFields();
	}

	public function initFields(){
		$email=new CB_Resource_Form_Element_Email('email');
		$email->setLabel('E-mail cím')->setRequired(true)->setAttrib('placeholder', 'e-mail cím');
		$password=new Zend_Form_Element_Password('password');
		$password->setLabel('Jelszó')->setRequired(true)->setAttrib('placeholder', 'jelszó');
        $url = new Zend_Form_Element_Hidden('popupurl');
		$submit=new Zend_Form_Element_Submit('Bejelentkezés');
		$submit->removeDecorator('label');

		//$rooturl=Zend_Layout::getMvcInstance()->getView()->url('bejelentkezes');
		//$this->setAction(!empty($_GET['r']) ? $rooturl.'?r='.$_GET['r'] :  $rooturl.'?r='.urlencode(Zend_Controller_Front::getInstance()->getRequest()->getUri()));
        $this->setAction(Zend_Layout::getMvcInstance()->getView()->url('bejelentkezes'));
		$this->addElements(array($email, $password, $url, $submit));
	}


}
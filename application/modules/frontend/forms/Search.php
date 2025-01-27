<?php

class Frontend_Form_Search extends CB_Form_Form {

	public function init(){
		$this->initFields();
		$this->setMethod('POST');
	}

	public function initFields(){
		$this->setAction('#');
		$catid=new Zend_Form_Element_Hidden('category_id');
		$catid->removeDecorator('label');
		$q=new CB_Form_Element_Search('q');
		$q->setRequired(true)->setAttrib('required', 'required')->setAttrib('placeholder', 'keresés...');
		$submit=new Zend_Form_Element_Submit('Keresés');
		$submit->removeDecorator('label');

		$this->addElements(array($q, $catid, $submit));
	}


}
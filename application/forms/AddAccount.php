<?php
/**
 *
 * @author Jakub Matejka <jakub@keboola.com>
 */
class Form_AddAccount extends App_Form
{
	public function init()
	{
		parent::init();

		$this->addElement('text', 'email', array(
			'required'	=> true,
			'label'		=> 'Email',
			'validators' => array('NotEmpty', 'EmailAddress')
		));

		$this->addElement('text', 'idGD', array(
			'label'		=> 'GoodData Project Id'
		));
		$this->addElement('hidden', 'oauthToken');
		$this->addElement('hidden', 'oauthVerifier');


		$this->addElement('submit', 'submit', array(
			'ignore'	=> true,
			'label'		=> 'Send'
		));

		$this->addDisplayGroup(array('email', 'idGD', 'submit'), 'basic');
	}
}

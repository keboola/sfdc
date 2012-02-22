<?php
/**
 * data check controller
 *
 * @author ondrej.hlavacek@keboola.com
 */
class DataCheckController extends Zend_Controller_Action
{

	public function init()
	{
		NDebugger::enable(true);
		$this->_helper->layout->setLayout("keboola-object");
		parent::init();
	}


	public function indexAction()
	{
		$users = new Model_BiUser();
		$user = $users->fetchRow(array("id =?" => $this->getRequest()->getParam("id")));

		$this->view->validData = $user->hasValidData();
	}


}

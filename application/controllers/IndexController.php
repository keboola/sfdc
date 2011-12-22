<?php
/**
 * index controller
 *
 * @author miroslav.cillik@keboola.com
 */
class IndexController extends Zend_Controller_Action
{

	public function init()
	{

		$session = new Zend_Session_Namespace('salesforceUser');
		$registry = Zend_Registry::getInstance();

		if ($this->getRequest()->getParam('id')) {
			$session->userId = $this->getRequest()->getParam('id');
		}

		if ($session->userId) {
			$userTable = new Model_BiUser();
			$userRow = $userTable->find(array('id' => $session->userId));
			$registry->user = $userRow->current();
			$registry->user->revalidateAccessToken();
		}
		parent::init();
	}


	public function indexAction()
	{
		$session = new Zend_Session_Namespace('salesforceUser');
		$registry = Zend_Registry::getInstance();
		if ($registry->user) {
			$this->view->user = $registry->user;
		}
		$users = new Model_BiUser();
		$usersRowset = $users->fetchAll();
		$this->view->users = $usersRowset->toArray();
	}


}

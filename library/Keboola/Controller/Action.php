<?php
/**
 *
 * User: Martin Halamíček
 * Date: 16.4.12
 * Time: 14:11
 *
 */

namespace Keboola\Controller;


class Action extends  \Zend_Controller_Action
{
	/**
	 * @var \Symfony\Component\DependencyInjection\ContainerInterface
	 */
	protected $_services;

	/**
	 * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
	 */
	protected $_eventDispatcher;

	protected $_config;

	/**
	 * @var \Keboola\Translate
	 */
	protected $_translator;

	/**
	 * @var \Keboola\Log\Log
	 */
	protected $_log;

	public function init()
	{
		parent::init();

		$bootstrap = $this->getInvokeArg('bootstrap');
		$this->_services = $bootstrap->getResource('services');
		$this->_eventDispatcher = $this->_services->get('eventDispatcher');
		$this->_log = $bootstrap->getResource('log');
		$this->_config = $bootstrap->getResource('config');
		$this->_translator = $bootstrap->getResource('translator');

		$this->view->baseUrl = $this->_config->app->url;
		$this->view->config = $this->_config;
		$this->view->pageTitle = $this->_translator->_($this->_request->getModuleName() . '.'
			. $this->_request->getControllerName()
			. '.' . $this->_request->getActionName().'.title');
	}

}
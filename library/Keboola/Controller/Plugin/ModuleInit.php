<?php
/**
 * Sets module name to log event item
 * User: Martin Halamíček
 * Date: 16.5.12
 * Time: 15:00
 *
 */

namespace Keboola\Controller\Plugin;

use	Keboola\Log\Log;

class ModuleInit extends \Zend_Controller_Plugin_Abstract
{
	/**
	 * @var \Keboola\Log\Log
	 */
	protected $_log;

	public function __construct(Log $log)
	{
		$this->_log = $log;
	}

	public function routeStartup(\Zend_Controller_Request_Abstract $request)
    {
		$httpEventItem = array(
			'url' => sprintf('[%s] [%s]', $request->getMethod(), $request->getRequestUri()),
		);

		if (count($request->getPost())) {
			$httpEventItem['post'] = $request->getPost();
		}

		if (!empty($_FILES)) {
			$httpEventItem['files'] = $_FILES;
		}

		if (isset($_SERVER['REMOTE_ADDR'])) {
			$httpEventItem['ip'] =  $_SERVER['REMOTE_ADDR'];
		}

		$this->_log->setEventItem('http', $httpEventItem);
	}

	public function routeShutdown(\Zend_Controller_Request_Abstract $request)
    {
		$this->_log->setEventItem('module', $request->getModuleName());
	}

}
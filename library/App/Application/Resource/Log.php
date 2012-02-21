<?php
/**
 * Json logger to syslog and file stream
 */
class App_Application_Resource_Log extends Zend_Application_Resource_ResourceAbstract
{
	/**
	 * @var Zend_Log
	 */
	protected $_log;

	/**
	 * Defined by Zend_Application_Resource_Resource
	 *
	 * @return Zend_View
	 */
	public function init()
	{
		$bootstrap = $this->getBootstrap();
		$config = $bootstrap->bootstrap('config')->getResource('config');

		$log = new Zend_Log();
		$log->setEventItem('app', $config->app->name);

		$syslogOptions = isset($options['syslog']) ? $options['syslog'] : array();
		$sysLogWriter = new Zend_Log_Writer_Syslog($syslogOptions);
		$sysLogWriter->setFormatter(new App_Log_Formatter_Json());
		$log->addWriter($sysLogWriter);
		Zend_Registry::set('log', $log);
		return $log;
	}

}

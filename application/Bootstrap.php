<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{

	protected function _initBase()
	{
		setlocale(LC_ALL, 'en_US.UTF8');
		ini_set("url_rewriter.tags","");
		date_default_timezone_set('Europe/Prague');

		$front = Zend_Controller_Front::getInstance();
	}

	protected function _initAutoload()
	{
		$autoloader = Zend_Loader_Autoloader::getInstance();
		$autoloader->registerNamespace('App_');
		$autoloader->registerNamespace('Ladybug');

		$resourceLoader = new Zend_Loader_Autoloader_Resource(array(
		    'basePath'      => APPLICATION_PATH,
		    'namespace'     => '',
		    'resourceTypes' => array(
		        'model' => array(
		            'path'      => 'models/',
					'namespace' => 'Model_',
				),
				'form' => array(
		            'path'      => 'forms/',
					'namespace' => 'Form_',
				)
			),
		));

		return $resourceLoader;
	}

	protected function _initConfig()
	{
		$config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/config.ini', 'config');
		Zend_Registry::set('config', $config);
		return $config;
	}

	protected function _initDb()
	{
		$registry = Zend_Registry::getInstance();

		// connect do db
		$db = Zend_Db::factory('pdo_mysql', array(
			'host'		=> $registry->config->db->host,
			'username'	=> $registry->config->db->login,
			'password'	=> $registry->config->db->password,
			'dbname'	=> $registry->config->db->name
		));

		// test připojení k db
		$db->getConnection();
		$db->query('SET NAMES utf8');

		// nastavení db adapteru pro všechny potomky Zend_Db_Table
		Zend_Db_Table::setDefaultAdapter($db);
		Zend_Registry::set('db', $db);
	}


	protected function _initDebug()
	{
		$config = $this->bootstrap('config')->getResource('config');

		if (!defined('APPLICATION_ENV')) {
			define('APPLICATION_ENV', $config->app->env);
		}

		if (APPLICATION_ENV == 'development') {
			ini_set('display_startup_errors', 1);
			ini_set('display_errors', 1);
			Ladybug\Loader::loadHelpers();
		}

		if (isset($_SERVER['HOSTNAME']))
			$_SERVER['SERVER_NAME'] = $_SERVER['HOSTNAME'];

		require_once 'Nette/NDebugger.php';
		NDebugger::enable();
		NDebugger::$logDirectory = ROOT_PATH.'/logs';
		NDebugger::$email = $config->app->admin;
		NDebugger::$productionMode = (APPLICATION_ENV == 'production');
		NLogger::$emailSnooze = 3600;
	}

	/**
	 * nastaví view a doctype a vybere skin tím, že nastaví prefix
	 */
    protected function _initViewSettings()
    {
		$this->bootstrap('view');
        $view = $this->getResource('view');

		//doctype pro xhtml verze
        $view->doctype('HTML5');

		$view->setBasePath(APPLICATION_PATH . '/views/');
		$view->addScriptPath(APPLICATION_PATH . '/views/scripts');
		$view->addScriptPath(APPLICATION_PATH . '/views/');
		$view->addScriptPath(APPLICATION_PATH . '/layouts/');

		// nastaveni helperu
		$view->setHelperPath(APPLICATION_PATH . '/views/helpers', 'App_View_Helper');
		$view->addHelperPath('ZendX/JQuery/View/Helper', 'ZendX_JQuery_View_Helper');

		$viewRenderer = new Zend_Controller_Action_Helper_ViewRenderer();
		$viewRenderer->setView($view);
		Zend_Controller_Action_HelperBroker::addHelper($viewRenderer);

		ZendX_JQuery::enableView($view);
    }

	protected function _initRouter()
	{
		$frontController = Zend_Controller_Front::getInstance();
		$router = $frontController->getRouter();

		$router->addRoute('dataCheck', new Zend_Controller_Router_Route(
			'data-check/:id',
			array(
				'module' => 'default',
				'controller' => 'data-check',
				'action' => 'index',
				'userId' => null
			)
		));


		return $router;
	}

}


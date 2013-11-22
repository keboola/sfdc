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
		$autoloader->registerNamespace('Keboola');
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

	protected function _initDebug()
	{
		$log = $this->bootstrap('log')->getResource('log');

		if ($this->getEnvironment() == 'development') {
			Ladybug\Loader::loadHelpers();
		}

		if (isset($_SERVER['HOSTNAME'])) {
			$_SERVER['SERVER_NAME'] = $_SERVER['HOSTNAME'];
		}

		require_once 'Nette/NDebugger.php';
		NDebugger::$strictMode = true;
		NDebugger::$logDirectory = ROOT_PATH.'/logs';
		// NDebugger::$productionMode = ($this->getEnvironment() == 'production');
		NDebugger::$productionMode = 'production';
		NDebugger::enable();

		NDebugger::$logger = new Keboola\Log\NetteLoggerProxy($log);
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

		$route = new Zend_Controller_Router_Route_Static(
			'last-import',
			array('controller' => 'index', 'action' => 'last-import')
		);
		$router->addRoute('last-import', $route);

		$route = new Zend_Controller_Router_Route_Static(
			'run-import',
			array('controller' => 'index', 'action' => 'run-import')
		);
		$router->addRoute('run-import', $route);

		$route = new Zend_Controller_Router_Route_Static(
			'run',
			array('controller' => 'index', 'action' => 'run')
		);
		$router->addRoute('run', $route);

		$route = new Zend_Controller_Router_Route_Static(
			'check',
			array('controller' => 'index', 'action' => 'check')
		);
		$router->addRoute('check', $route);

		$route = new Zend_Controller_Router_Route_Static(
			'last',
			array('controller' => 'index', 'action' => 'last')
		);
		$router->addRoute('last', $route);

		$route = new Zend_Controller_Router_Route_Static(
			'check-run',
			array('controller' => 'index', 'action' => 'check-run')
		);
		$router->addRoute('check-run', $route);

		$route = new Zend_Controller_Router_Route_Static(
			'accounts',
			array('controller' => 'index', 'action' => 'accounts')
		);
		$router->addRoute('accounts', $route);

		$route = new Zend_Controller_Router_Route_Static(
			'configs',
			array('controller' => 'index', 'action' => 'accounts')
		);
		$router->addRoute('configs', $route);

		$route = new Zend_Controller_Router_Route(
			'configs/:id',
			array('controller' => 'index', 'action' => 'accounts')
		);
		$router->addRoute('configs-wid', $route);

		$route = new Zend_Controller_Router_Route_Static(
			'oauth',
			array('controller' => 'index', 'action' => 'oauth')
		);
		$router->addRoute('oauth', $route);

		$route = new Zend_Controller_Router_Route_Static(
			'oauth-ui',
			array('controller' => 'index', 'action' => 'oauth-ui')
		);
		$router->addRoute('oauth-ui', $route);

		$route = new Zend_Controller_Router_Route_Static(
			'oauth-callback',
			array('controller' => 'index', 'action' => 'oauth-callback')
		);
		$router->addRoute('oauth-callback', $route);

		$route = new Zend_Controller_Router_Route_Static(
			'oauth-prepare',
			array('controller' => 'index', 'action' => 'oauth-prepare')
		);
		$router->addRoute('prepare-oauth', $route);



		return $router;
	}

}


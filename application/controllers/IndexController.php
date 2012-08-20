<?php
/**
 * index controller
 *
 * @author ondrej.hlavacek@keboola.com
 */
class IndexController extends Zend_Controller_Action
{

	public function init()
	{
		parent::init();
		NDebugger::$bar = FALSE;
		$this->_helper->layout()->disableLayout();
		$this->getHelper('viewRenderer')->setNoRender(TRUE);

		$errorHandler = $this->getFrontController()->getPlugin('Zend_Controller_Plugin_ErrorHandler');
		$errorHandler->setErrorHandlerAction('json-error');

		$this->getResponse()->setHeader('Access-Control-Allow-Origin', '*');
		set_time_limit(60 * 120);

		// CORS
		if ($this->getRequest()->getMethod() == "OPTIONS") {
			$this->getResponse()->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
			$this->getResponse()->setHeader('Access-Control-Allow-Headers', 'content-type, x-requested-with, x-requested-by');
			$this->getResponse()->setHeader('Access-Control-Max-Age', '86400');
			$this->getResponse()->sendResponse();
			die;
		}
	}

	/**
	 * Nothing
	 */
	public function indexAction()
	{
		$response = array();
		$this->_helper->json($response);
	}

	/**
	 * LastImport actions
	 *
	 * If multiple accounts, return the oldest date
	 *
	 */
	public function lastImportAction()
	{
		$token = $this->_getParam("token");
		$config = Zend_Registry::get("config");

		$sapi = new \Keboola\StorageApi\Client($token);
		\Keboola\StorageApi\Config\Reader::$client = $sapi;
		$sfdcConfig = \Keboola\StorageApi\Config\Reader::read($config->storageApi->configBucket);

		if (count($sfdcConfig["items"]) == 0) {
			throw new \Keboola\Exception("No configuration found", null, null, "SFDC_CONFIG");
		}

		$response = array();
		$response["forceImport"] = false;

		foreach($sfdcConfig["items"] as $sfdcTableName => $sfdcItemConfig) {

			if (!$response["lastImportDate"]) {
				$response["lastImportDate"] = $sfdcItemConfig["log"]["importDate"];
			} else {
				$response["lastImportDate"] = min($response["lastImportDate"], $sfdcItemConfig["log"]["importDate"]);
			}

			$tableInfo = $sapi->getTable($config->storageApi->configBucket . "." . $sfdcTableName);
			if ($tableInfo["lastImportDate"] > $sfdcItemConfig["log"]["importDate"]) {
				$response["forceImport"] = true;
			}
		}

		$this->_helper->json($response);
	}

	/**
	 * Run import action
	 *
	 * Run all accounts
	 *
	 */
	public function runImportAction()
	{

		if ($this->getRequest()->getMethod() != "POST") {
			throw new \Keboola\Exception("Wrong method, use POST", null, null, "SFDC_METHOD");
		}

		\NDebugger::timer("import");

		$body = $this->getRequest()->getRawBody();
		$jsonParams = array();
		if (strlen($body)) {
			$jsonParams = Zend_Json::decode($body);
		}

		$token = $this->_getParam("token");
		$config = Zend_Registry::get("config");
		$log = Zend_Registry::get("log");

		Keboola\StorageApi\Client::setLogger(function($message, $data) use($log) {
			$log->log($message, Zend_Log::INFO, $data);
		});
		$sapi = new \Keboola\StorageApi\Client($token);

		\Keboola\StorageApi\Config\Reader::$client = $sapi;
		$sfdcConfig = \Keboola\StorageApi\Config\Reader::read($config->storageApi->configBucket);

		$passed = false;


		foreach($sfdcConfig["items"] as $configName => $configInstance) {

			if (count($jsonParams) && $jsonParams["account"] != $configName) {
				continue;
			}

			$passed = true;

			$connectionConfig = $configInstance;
			unset($connectionConfig["items"]);
			$connectionConfig = new Zend_Config($connectionConfig, true);

			$soqlConfig = $configInstance["items"];
			$soqlConfig = new Zend_Config($soqlConfig, true);

			try {

				NDebugger::timer('account');

				$sfdc = new App_SalesForceImport($connectionConfig, $soqlConfig);

				$revalidation = $sfdc->revalidateAccessToken($connectionConfig->accessToken, $connectionConfig->clientId, $connectionConfig->clientSecret, $connectionConfig->refreshToken);

				$connectionConfig->accessToken = $revalidation['access_token'];
				$connectionConfig->instanceUrl = $revalidation['instance_url'];

				$sfdc->sApi = $sapi;
				$sfdc->storageApiBucket = "in.c-" . $configName;
				$sfdc->accessToken = $connectionConfig->accessToken;
				$sfdc->instanceUrl = $connectionConfig->instanceUrl;
				$sfdc->userId = $connectionConfig->id;
				$sfdc->username = $connectionConfig->username;
				$sfdc->passSecret = $connectionConfig->passSecret;

				$tmpDir = ROOT_PATH . "/tmp/" . $token . $configName . "/";

				if (!file_exists($tmpDir)) {
					mkdir($tmpDir);
				}

				if (!is_dir($tmpDir)) {
					throw new \Keboola\Exception("Temporary directory path is not a directory", null, null, "SFDC_TMP_DIR");
				}

				$sfdc->tmpDir = $tmpDir;

				$sfdc->importAll();

				$duration = NDebugger::timer('account');
				$log->log("SFDC Import {$configName} ({$token})", Zend_Log::INFO, array(
					'duration'	=> $duration
				));

				$tableId = $config->storageApi->configBucket . "." . $configName;
				$sapi->setTableAttribute($tableId, "accessToken", $sfdc->accessToken);
				$sapi->setTableAttribute($tableId, "instanceUrl", $sfdc->instanceUrl);
				$sapi->setTableAttribute($tableId, "log.importDate", date("Y-m-d H:i:s"));
				$sapi->setTableAttribute($tableId, "log.importDuration", $duration);

			} catch(Exception $e) {
				throw new \Keboola\Exception($e->getMessage(), null, null, "SFDC_IMPORT");
			}
		}


		if (!$passed) {
			throw new \Keboola\Exception("Account {$jsonParams["account"]} not found", null, null, "SFDC_ACCOUNT");
		}

		$duration = \NDebugger::timer("import");
		$response = array("status" => "ok", "duration" => $duration);
		$this->_helper->json($response);
	}

}

<?php
/**
 * index controller
 *
 * @author ondrej.hlavacek@keboola.com
 */
class IndexController extends Zend_Controller_Action
{

	/**
	 * @var \Keboola\StorageApi\Client
	 */
	public $storageApi;

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

	public function initStorageApi()
	{
		$token = $this->getRequest()->getHeader("X-StorageApi-Token");
		if (!$token) {
			throw new \Keboola\Exception("Missing Storage API token", null, null, "MISSING_TOKEN");
		}

		$config = Zend_Registry::get("config");
		$this->storageApi = new \Keboola\StorageApi\Client($token, $config->storageApi->url, $config->app->projectName);
		$log = Zend_Registry::get("log");
		Keboola\StorageApi\Client::setLogger(function($message, $data) use($log) {
			$log->log($message, Zend_Log::INFO, $data);
		});

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
	 * List Accounts
	 */
	public function accountsAction()
	{
		$this->initStorageApi();
		$config = Zend_Registry::get("config");
		\Keboola\StorageApi\Config\Reader::$client = $this->storageApi;
		$bucket = $this->storageApi->getBucket($config->storageApi->configBucket);

		$accounts = array();

		if(!$bucket["tables"] || count($bucket["tables"]) == 0) {
			$this->_helper->json(array("accounts" => $accounts));
			return;
		}

		foreach($bucket["tables"] as $table) {
			$accounts[] = $table["name"];
		}

		$this->_helper->json(array("accounts" => $accounts));
	}

	/**
	 * LastImport actions
	 *
	 * If multiple accounts, return the oldest date
	 *
	 */
	public function lastImportAction()
	{
		$this->initStorageApi();
		$config = Zend_Registry::get("config");

		\Keboola\StorageApi\Config\Reader::$client = $this->storageApi;
		$sfdcConfig = \Keboola\StorageApi\Config\Reader::read($config->storageApi->configBucket);

		if (count($sfdcConfig["items"]) == 0) {
			throw new \Keboola\Exception("No configuration found", null, null, "CONFIG");
		}

		$response = array();
		$response["forceImport"] = false;

		foreach($sfdcConfig["items"] as $sfdcTableName => $sfdcItemConfig) {
			if (!isset($response["lastImport"])) {
				$response["lastImport"] = $sfdcItemConfig["log"]["extractDate"];
			} else {
				$response["lastImport"] = min($response["lastImport"], $sfdcItemConfig["log"]["extractDate"]);
			}

			$tableInfo = $this->storageApi->getTable($config->storageApi->configBucket . "." . $sfdcTableName);
			if ($tableInfo["lastImportDate"] > $sfdcItemConfig["log"]["extractDate"]) {
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
		$this->initStorageApi();
		if ($this->getRequest()->getMethod() != "POST") {
			throw new \Keboola\Exception("Wrong method, use POST", null, null, "METHOD");
		}

		\NDebugger::timer("import");

		$body = $this->getRequest()->getRawBody();
		$jsonParams = array();
		if (strlen($body)) {
			$jsonParams = Zend_Json::decode($body);
		}

		$config = Zend_Registry::get("config");
		$log = Zend_Registry::get("log");

		\Keboola\StorageApi\Config\Reader::$client = $this->storageApi;
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

				\NDebugger::timer('account');

				$sfdc = new App_SalesForceImport($connectionConfig, $soqlConfig);

				$sfdc->sApi = $this->storageApi;
				$revalidation = $sfdc->revalidateAccessToken($connectionConfig->accessToken, $connectionConfig->clientId, $connectionConfig->clientSecret, $connectionConfig->refreshToken);

				$connectionConfig->accessToken = $revalidation['access_token'];
				$connectionConfig->instanceUrl = $revalidation['instance_url'];

				$sfdc->storageApiBucket = "in.c-" . $configName;
				if (!$this->storageApi->bucketExists($sfdc->storageApiBucket)) {
					$this->storageApi->createBucket($configName, \Keboola\StorageApi\Client::STAGE_IN, "SalesForce Data");
				}
				$sfdc->accessToken = $connectionConfig->accessToken;
				$sfdc->instanceUrl = $connectionConfig->instanceUrl;
				$sfdc->userId = $connectionConfig->id;
				$sfdc->username = $connectionConfig->username;
				$sfdc->passSecret = $connectionConfig->passSecret;
				$tokenInfo = $this->storageApi->getLogData();

				$tmpDir = "/tmp/" . $tokenInfo["token"] . "-" . uniqid($configName . "-") . "/";

				if (!file_exists($tmpDir)) {
					mkdir($tmpDir);
				}

				if (!is_dir($tmpDir)) {
					throw new \Keboola\Exception("Temporary directory path ($tmpDir) is not a directory", null, null, "TMP_DIR");
				}

				$sfdc->tmpDir = $tmpDir;

				$sfdc->importAll();

				$duration = NDebugger::timer('account');
				$log->log("SFDC Import {$configName}", Zend_Log::INFO, array(
					'duration'	=> $duration,
					'token' => $tokenInfo
				));

				$tableId = $config->storageApi->configBucket . "." . $configName;
				$this->storageApi->setTableAttribute($tableId, "accessToken", $sfdc->accessToken);
				$this->storageApi->setTableAttribute($tableId, "instanceUrl", $sfdc->instanceUrl);
				$this->storageApi->setTableAttribute($tableId, "log.extractDate", date("Y-m-d H:i:s"));
				$this->storageApi->setTableAttribute($tableId, "log.extractDuration", $duration);

				// Cleanup
				exec("rm -rf $tmpDir");

			} catch(\Exception $e) {
				throw new \Keboola\Exception($e->getMessage(), $e->getCode(), $e, "IMPORT");
			}
		}

		if (!$passed) {
			throw new \Keboola\Exception("Account {$jsonParams["account"]} not found", null, null, "ACCOUNT");
		}

		$duration = \NDebugger::timer("import");
		$response = array("status" => "ok", "duration" => $duration);
		$this->_helper->json($response);
	}

	/**
	 * Alias
	 */
	public function runAction()
	{
		$this->_forward("run-import");
	}

	public function checkAction()
	{
		$this->initStorageApi();
		$config = Zend_Registry::get("config");

		\Keboola\StorageApi\Config\Reader::$client = $this->storageApi;
		$sfdcConfig = \Keboola\StorageApi\Config\Reader::read($config->storageApi->configBucket);

		if (count($sfdcConfig["items"]) == 0) {
			throw new \Keboola\Exception("No configuration found", null, null, "CONFIG");
		}

		$response = array();
		$response["forceRun"] = false;
		$response["lastRunDate"] = null;

		foreach($sfdcConfig["items"] as $sfdcTableName => $sfdcItemConfig) {
			if (!isset($response["lastRunDate"])) {
				$response["lastRunDate"] = $sfdcItemConfig["log"]["extractDate"];
			} else {
				$response["lastRunDate"] = min($response["lastRun"], $sfdcItemConfig["log"]["extractDate"]);
			}

			$tableInfo = $this->storageApi->getTable($config->storageApi->configBucket . "." . $sfdcTableName);
			if ($tableInfo["lastImportDate"] > $sfdcItemConfig["log"]["extractDate"]) {
				$response["forceRun"] = true;
			}
		}

		$this->_helper->json($response);
	}

	public function checkRunAction()
	{
		$this->_forward("check");
	}

	public function lastAction()
	{
		$this->_forward("check");
	}

}

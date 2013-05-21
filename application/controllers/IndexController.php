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
			$this->getResponse()->setHeader('Access-Control-Allow-Headers', 'content-type, x-requested-with, x-requested-by', 'x-storageapi-token', 'x-storageapi-url', 'x-kbc-runid');
			$this->getResponse()->setHeader('Access-Control-Max-Age', '86400');
			$this->getResponse()->sendResponse();
			die;
		}
	}

	public function initStorageApi($tkn=null)
	{
		if ($tkn) {
			$token = $tkn;
		} else {
			$token = $this->getRequest()->getHeader("X-StorageApi-Token");
		}
		if (!$token) {
			throw new \Keboola\Exception("Missing Storage API token", null, null, "MISSING_TOKEN");
		}

		$config = Zend_Registry::get("config");
		$this->storageApi = new \Keboola\StorageApi\Client($token, $config->storageApi->url, $config->app->projectName);
		$log = Zend_Registry::get("log");
		Keboola\StorageApi\Client::setLogger(function($message, $data) use($log) {
			$registry = \Zend_Registry::getInstance();
			if (!isset($data["runId"]) && isset($registry["runId"])) {
				$data["runId"] = $registry["runId"];
			}
			$log->log($message, Zend_Log::INFO, $data);
		});
		$registry = Zend_Registry::getInstance();
		$registry->storageApi = $this->storageApi;
		if ($this->getRequest()->getHeader("X-KBC-RunId")) {
			$registry->runId = $this->getRequest()->getHeader("X-KBC-RunId");
		} else {
			$registry->runId = $this->storageApi->generateId();
		}
		$this->storageApi->setRunId($registry->runId);
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
				$registry = \Zend_Registry::getInstance();
				$loginUri = $connectionConfig->get("loginUri", $registry->config->salesForce->loginUri);
				$revalidation = $sfdc->revalidateAccessToken($connectionConfig->accessToken, $connectionConfig->clientId, $connectionConfig->clientSecret, $connectionConfig->refreshToken, $loginUri);

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
				$this->storageApi->setTableAttribute($tableId, "log.extractDate", date("Y-m-d\TH:i:sO"));
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
			if (isset($sfdcItemConfig["log"]["extractDate"])) {
				if (!$response["lastRunDate"]) {
					$response["lastRunDate"] = $sfdcItemConfig["log"]["extractDate"];
				} else {
					$response["lastRunDate"] = min($response["lastRunDate"], $sfdcItemConfig["log"]["extractDate"]);
				}
			}

			$tableInfo = $this->storageApi->getTable($config->storageApi->configBucket . "." . $sfdcTableName);
			if (!isset($sfdcItemConfig["log"]["extractDate"])) {
				$response["forceRun"] = true;
			} elseif ($tableInfo["lastImportDate"] > $sfdcItemConfig["log"]["extractDate"]) {
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

	public function oauthPrepareAction()
	{
		$config = Zend_Registry::get("config");
		$this->initStorageApi();

		$session = new Zend_Session_Namespace('oauth');
		$session->setExpirationSeconds(60);
		$session->token = $this->storageApi->getTokenString();

		$body = $this->getRequest()->getRawBody();
		$jsonParams = array();
		if (strlen($body)) {
			$jsonParams = Zend_Json::decode($body);
		}
		if (isset($jsonParams["account"])) {
			$session->account = $jsonParams["account"];
		}
		if (isset($jsonParams["loginUri"])) {
			$session->loginUri = $jsonParams["loginUri"];
		} else {
			$session->loginUri = $config->salesForce->loginUri;
		}

		$this->_helper->json(array(
			"status" => "ok",
			"uri" => $config->salesForce->oauthUri
		));
	}

	public function oauthAction()
	{
		$session = new Zend_Session_Namespace('oauth');
		if (!$session->token) {
			$this->_helper->json(array("status" => "error", "message" => "Session expired. Prepare again."));
		}
		$config = Zend_Registry::get("config");
		$auth_url = $session->loginUri;
		$auth_url .= "/services/oauth2/authorize?response_type=code&client_id="
			. $config->salesForce->clientId . "&redirect_uri=" . urlencode($config->salesForce->redirectUri)
			. "&scope=id api full refresh_token web";
		die(header('Location: ' . $auth_url));
	}

	public function oauthCallbackAction()
	{
		$session = new Zend_Session_Namespace('oauth');
		if (!$session->token) {
			$this->_helper->json(array("status" => "error", "message" => "Session expired. Prepare again."));
		}
		if ($session->token) {
			$this->initStorageApi($session->token);
		}

		$config = Zend_Registry::get("config");
		$token_url = $session->loginUri . "/services/oauth2/token";
		$code = $_GET['code'];

		if (!isset($code) || $code == "") {
			throw new Exception("Error - code parameter missing from request!");
		}

		$params = "code=" . $code
			. "&grant_type=authorization_code"
			. "&client_id=" . $config->salesForce->clientId
			. "&client_secret=" . $config->salesForce->clientSecret
			. "&redirect_uri=" . urlencode($config->salesForce->redirectUri);

		$curl = curl_init($token_url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $params);

		$json_response = curl_exec($curl);

		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if ( $status != 200 ) {
			throw new Exception("Error: call to token URL $token_url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
		}

		curl_close($curl);

		$response = json_decode($json_response, true);

		$access_token = $response['access_token'];
		$instance_url = $response['instance_url'];

		if (!isset($access_token) || $access_token == "") {
			throw new Exception("Error - access token missing from response!");
		}

		if (!isset($instance_url) || $instance_url == "") {
			throw new Exception("Error - instance URL missing from response!");
		}

		if (!$this->storageApi->bucketExists("sys.c-SFDC")) {
			$this->storageApi->createBucket("SFDC", "sys", "SFDC configuration");
		}
		if (isset($session->account)) {
			$tableName = $session->account;

		} else {
			$tableName = "SFDC01";
		}
		$tableId = "sys.c-SFDC." . $tableName;
		if (!$this->storageApi->tableExists($tableId)) {
			$this->storageApi->createTable("sys.c-SFDC", $tableName, ROOT_PATH . "/application/configs/tableTemplate.csv");
		}
		$this->storageApi->setTableAttribute($tableId, "clientId", $config->salesForce->clientId);
		$this->storageApi->setTableAttribute($tableId, "clientSecret", $config->salesForce->clientSecret);
		$this->storageApi->setTableAttribute($tableId, "instanceUrl", $response['instance_url']);
		$this->storageApi->setTableAttribute($tableId, "accessToken", $response['access_token']);
		$this->storageApi->setTableAttribute($tableId, "refreshToken", $response['refresh_token']);
		$this->storageApi->setTableAttribute($tableId, "loginUri", $session->loginUri);

		$session->unsetAll();

		$this->_helper->json(array("status" => "ok"));
	}

}

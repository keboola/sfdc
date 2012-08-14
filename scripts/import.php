<?php
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('APPLICATION_PATH', ROOT_PATH . '/application');
set_include_path(implode(PATH_SEPARATOR, array(realpath(ROOT_PATH . '/library'), get_include_path())));
require_once 'Zend/Application.php';
require_once ROOT_PATH . '/vendor/autoload.php';
$application = new Zend_Application('application', APPLICATION_PATH . '/configs/application.ini');
$application->bootstrap(array("base", "autoload", "config", "debug", "log"));

// Setup console input
$opts = new Zend_Console_Getopt(array(
	'token=s'	=> 'Storage API token',
	'clean|c-i' => "Delete all tables before loading and recreate them"
));
$opts->setHelp(array(
	'token'	=> 'Storage API token',
	'clean' => 'Cleanup',
));
try {
	$opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
	echo $e->getUsageMessage();
	exit;
}

$start = time();
echo 'Start: '.date('j. n. Y H:i:s', $start)."\n";

$config = Zend_Registry::get('config');
$log = Zend_Registry::get('log');

if (!$opts->getOption('token')) {
	echo $opts->getUsageMessage();
} else {



	$sapi = new \Keboola\StorageApi\Client($opts->getOption('token'));
	\Keboola\StorageApi\Config\Reader::$client = $sapi;

	$configArray = \Keboola\StorageApi\Config\Reader::read($config->storageApi->configBucket);

	foreach($configArray["items"] as $configName => $configInstance) {

		$connectionConfig = $configInstance;
		unset($connectionConfig["items"]);
		$connectionConfig = new Zend_Config($connectionConfig, true);

		$soqlConfig = $configInstance["items"];
		$soqlConfig = new Zend_Config($soqlConfig, true);

		try {
			//App_StorageApi::setDebug(true);
			Keboola\StorageApi\Client::setLogger(function($message, $data) use($log) {
				$log->log($message, Zend_Log::INFO, $data);
			});
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

			$tmpDir = ROOT_PATH . "/tmp/" . $opts->getOption('token') . "/";

			if (!file_exists($tmpDir)) {
				mkdir($tmpDir);
			}

			if (!is_dir($tmpDir)) {
				throw new Exception("Temporary directory path is not a directory.");
			}
			$sfdc->tmpDir = $tmpDir;

			if ($opts->getOption('clean')) {
				$sfdc->dropAll();
			}

			$sfdc->importAll();

			$duration = NDebugger::timer('account');
			$log->log("SalesForce Cron Import for user {$connectionConfig->id} ({$opts->getOption('token')})", Zend_Log::INFO, array(
				'duration'	=> $duration
			));

			$tableId = $config->storageApi->configBucket . "." . $configName;
			$sapi->setTableAttribute($tableId, "accessToken", $sfdc->accessToken);
			$sapi->setTableAttribute($tableId, "instanceUrl", $sfdc->instanceUrl);
			$sapi->setTableAttribute($tableId, "log.importDate", date("Y-m-d H:i:s"));
			$sapi->setTableAttribute($tableId, "log.importDuration", $duration);

		} catch(Exception $e) {
			$debugFile = NDebugger::log($e, NDebugger::ERROR);
			echo "ERROR: " . $e->getMessage() . PHP_EOL;
		}
	}
}

$end = time();
echo 'End: '.date('j. n. Y H:i:s', $end).', Run time: '.round(($end-$start)/60)." min\n";
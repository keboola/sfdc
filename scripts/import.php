<?php
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('APPLICATION_PATH', ROOT_PATH . '/application');
set_include_path(implode(PATH_SEPARATOR, array(realpath(ROOT_PATH . '/library'), get_include_path())));
require_once 'Zend/Application.php';
require_once ROOT_PATH . '/vendor/autoload.php';
$application = new Zend_Application('application', APPLICATION_PATH . '/configs/application.ini');
$application->bootstrap(array("base", "autoload", "config", "db", "debug", "log"));

// Setup console input
$opts = new Zend_Console_Getopt(array(
	'token=s'	=> 'Storage API token',
	//'query|q=s' => 'SOQL Query',
	//'table|t=s' => 'Storage API table',
	//'incremental|c-i' => 'Incremental',
	'clean|c-i' => "Delete all tables before loading and recreate them"
));
$opts->setHelp(array(
	'token'	=> 'Storage API token',
	//'q'	=> 'Custom SOQL Query',
	//'t'	=> 'Storage API table',
	//'c' => 'Incremental query',
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
	\Keboola\StorageApi\OneLiner::setClient($sapi);
	\Keboola\StorageApi\OneLiner::$tmpDir = ROOT_PATH . "/tmp/";
	$connectionConfig = new \Keboola\StorageApi\OneLiner($config->storageApi->configBucket . "." . $config->storageApi->name);
	$soqlConfig = Keboola\StorageApi\Client::parseCSV($sapi->exportTable($config->storageApi->configBucket . "." . $config->storageApi->name . "Queries"));

	if ($connectionConfig) {

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
			$sfdc->storageApiBucket = "in.c-" . $config->storageApi->name;
			$sfdc->accessToken = $connectionConfig->accessToken;
			$sfdc->instanceUrl = $connectionConfig->instanceUrl;
			$sfdc->userId = $connectionConfig->id;
			$sfdc->username = $connectionConfig->username;
			$sfdc->passSecret = $connectionConfig->passSecret;

			$tmpDir = ROOT_PATH . "/tmp/" . $connectionConfig->id . "/";
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

			$connectionConfig->lastImportDate = date("Y-m-d H:i:s");
			$connectionConfig->save();

			$duration = NDebugger::timer('account');
			$log->log("SalesForce Cron Import for user {$connectionConfig->id} ({$opts->getOption('token')})", Zend_Log::INFO, array(
				'duration'	=> $duration
			));

		} catch(Exception $e) {
			$debugFile = NDebugger::log($e, NDebugger::ERROR);
			echo "ERROR: " . $e->getMessage() . PHP_EOL;
		}

	} else {
		echo "Wrong user id!\n";
	}
}

$end = time();
echo 'End: '.date('j. n. Y H:i:s', $end).', Run time: '.round(($end-$start)/60)." min\n";
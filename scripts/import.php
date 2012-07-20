<?php
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('APPLICATION_PATH', ROOT_PATH . '/application');
set_include_path(implode(PATH_SEPARATOR, array(realpath(ROOT_PATH . '/library'), get_include_path())));
require_once 'Zend/Application.php';
$application = new Zend_Application('application', APPLICATION_PATH . '/configs/application.ini');
$application->bootstrap(array("base", "autoload", "config", "db", "debug", "log"));

// Setup console input
$opts = new Zend_Console_Getopt(array(
	'id|i=s'	=> 'Id of user',
	//'query|q=s' => 'SOQL Query',
	//'table|t=s' => 'Storage API table',
	//'incremental|c-i' => 'Incremental',
	'clean|c-i' => "Delete all tables before loading and recreate them"
));
$opts->setHelp(array(
	'i'	=> 'Id of user',
	//'q'	=> 'Custom SOQL Query',
	//'t'	=> 'Storage API table',
	//'c' => 'Incremental query',
	'c' => 'Cleanup',
));
try {
	$opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
	echo $e->getUsageMessage();
	exit;
}

$start = time();
echo 'Start: '.date('j. n. Y H:i:s', $start)."\n";

$userTable = new Model_BiUser();
$config = Zend_Registry::get('config');
$log = Zend_Registry::get('log');

if (!$opts->getOption('id')) {
	echo $opts->getUsageMessage();
} else {

	$user = $userTable->fetchRow(array('id=?' => $opts->getOption('id')));

	if ($user) {

		try {
			//App_StorageApi::setDebug(true);
			App_StorageApi::setLogger(function($message, $data) use($log) {
				$log->log($message, Zend_Log::INFO, $data);
			});
			NDebugger::timer('account');

			$user->revalidateAccessToken();
			if (!$user->sfdcConfig) {
				throw new Exception("Missing configuration for user " . $user->strId);
			}

			$importConfig = Zend_Json::decode($user->sfdcConfig, Zend_Json::TYPE_OBJECT);
			$sfdc = new App_SalesForceImport($user, $importConfig);

			$tmpDir = ROOT_PATH . "/tmp/" . $user->strId . "/";
			if (!file_exists($tmpDir)) {
				mkdir($tmpDir);
			}
			if (!is_dir($tmpDir)) {
				throw new Exception("Temporary directory path is not a directory.");
			}
			$sfdc->tmpDir = $tmpDir;
			/*
			if($opts->getOption('query')) {
				$dataset = "query";
				if ($opts->getOption('dataset')) {
					$dataset = $opts->getOption('dataset');
				}
				// Download Data
				$import->importQuery($opts->getOption('query'), "query", $opts->getOption('incremental'));
				// Upload to Storage API
				if ($opts->getOption('dataset')) {

				}
			} else {
			*/

			if ($opts->getOption('clean')) {
				$sfdc->dropAll();
			}

			$sfdc->importAll();

			$user->sfdcLastImportDate = date("Y-m-d H:i:s");
			$user->save();
			// }
			$duration = NDebugger::timer('account');
			$log->log("SalesForce Cron Import for user {$user->strId} ({$user->id})", Zend_Log::INFO, array(
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
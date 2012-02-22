<?php

define('ROOT_PATH', dirname(dirname(__FILE__)));
define('APPLICATION_PATH', ROOT_PATH . '/application');
set_include_path(implode(PATH_SEPARATOR, array(realpath(ROOT_PATH . '/library'), get_include_path())));
require_once 'Zend/Application.php';
$application = new Zend_Application('application', APPLICATION_PATH . '/configs/application.ini');
$application->bootstrap(array("base", "autoload", "config", "db", "debug", "log"));

// Setup console input
$opts = new Zend_Console_Getopt(array(
	'id|i=i'	=> 'Id of user'
));
$opts->setHelp(array(
	'i'	=> 'Id of user'
));

try {
	$opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
	echo $e->getUsageMessage();
	exit;
}

$lock = new App_Lock(Zend_Registry::get('db'), 'cron-import');
if (!$lock->lock()) {
	die('Already running' . PHP_EOL);
}

NDebugger::timer('cron');

$start = time();
echo 'Start: '.date('j. n. Y H:i:s', $start)."\n";

$usersTable = new Model_BiUser();
$config = Zend_Registry::get('config');
$log = Zend_Registry::get('log');

$log->log('SalesForce Cron Starting', Zend_Log::INFO);


$userTable = new Model_BiUser();
$idUser = $opts->getOption('id');

if ($idUser) {
	$usersQuery = $usersTable->fetchAll(array('id=?' => $idUser));
} else {
	$usersQuery = $usersTable->fetchAll();
}

foreach($usersQuery as $user) {

	if (!$user->export && !$user->import) {
		continue;
	}

	print "\n**************************************************\n";
	print "User {$user->strId}\n\n";

	// connect do db
	$dbData = Zend_Db::factory('pdo_mysql', array(
		'host'		=> $config->db->host,
		'username'	=> $config->db->login,
		'password'	=> $config->db->password,
		'dbname'	=> $user->dbName
	));

	// Konfigurace importu a exportu
	$importExportConfig = new Zend_Config_Ini(ROOT_PATH . '/gooddata/' . $user->strId . '/config.ini', 'salesforce', Array('allowModifications' => true));

	// test připojení k db
	$dbData->getConnection();
	$dbData->query('SET NAMES utf8');

	// nastavení db adapteru pro všechny potomky Zend_Db_Table
	Zend_Db_Table::setDefaultAdapter($dbData);

	if ($user->import) {
		// Import
		print "Importing data\n";
		NDebugger::timer('account');
		$user->revalidateAccessToken();
		$import = new App_SalesForceImport($user, $importExportConfig);
		try {
			$import->importAll();
			print "Importing done\n";
			$duration = NDebugger::timer('account');
			$user->lastImportDate = date("Y-m-d H:i:s");
			$user->save();

			$log->log("SalesForce Cron Import for user {$user->strId} ({$user->id})", Zend_Log::INFO, array(
				'duration'	=> $duration
			));
		} catch(Exception $e) {
			print "Import failed\n";
			$duration = NDebugger::timer('account');
			print $e->getMessage() . "\n";
			print $e->getTraceAsString();
			$log->log("SalesForce Cron Import for user {$user->strId} ({$user->id}) Failed", Zend_Log::ERR, array(
				'err'	=> $e->getMessage() . "\n". $e->getTraceAsString()
			));
			// Do not continue with exports
			continue;
		}

	}

	if ($user->export) {

		if (!$user->gdProject) {
			print "Missing GoodData project ID for user {$user->name} ({$user->id})\n";
		} else {
			// Export
			NDebugger::timer('account');
			$export = new App_GoodDataExport($user->gdProject, $user, $config, $importExportConfig);
			$export->loadData();
			$duration = NDebugger::timer('account');
			$log->log("SalesForce Cron Export for user {$user->strId} ({$user->id})", Zend_Log::INFO, array(
				'duration'	=> $duration
			));
			$user->lastExportDate = date("Y-m-d H:i:s");
			$user->save();
		}
	}
}

$end = time();
echo 'End: '.date('j. n. Y H:i:s', $end).', Run time: '.round(($end-$start)/60)." min\n";

$duration = NDebugger::timer('cron');
$log->log('SalesForce Cron Completed', Zend_Log::INFO, array(
	'duration'	=> $duration
));

$lock->unlock();
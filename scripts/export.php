<?php

define('ROOT_PATH', dirname(dirname(__FILE__)));
define('APPLICATION_PATH', ROOT_PATH . '/application');
set_include_path(implode(PATH_SEPARATOR, array(realpath(ROOT_PATH . '/library'), get_include_path())));
require_once 'Zend/Application.php';
$application = new Zend_Application('application', APPLICATION_PATH . '/configs/application.ini');
$application->bootstrap(array("base", "autoload", "config", "db", "debug", "log"));


// Setup console input
$opts = new Zend_Console_Getopt(array(
	'all|a-i'		=> 'load all',
	'dump|d-s'		=> 'table option, with required string parameter',
	'id|i=i'		=> 'user id',
	'load|l-i'		=> 'load data to datasets in GoodData',
	'project|p=s'	=> 'GoodData project PID for setup',
	'setup|s-i'		=> 'setup datasets in GoodData',
	'update|u-s'	=> 'update dataset structure in GoodData',
));
$opts->setHelp(array(
	'a' => 'load all',
	'd' => 'Name of the table to dump.',
	'i' => 'user id',
	'l' => 'Load data to datasets in GoodData',
	'p' => 'GoodData project PID for setup',
	's' => 'Setup datasets in GoodData',
	'u' => 'Update dataset structure in GoodData',
));
try {
	$opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
	echo $e->getUsageMessage();
	exit;
}

$config = Zend_Registry::get('config');

if($opts->getOption('id')) {
	$userTable = new Model_BiUser();
	$user = $userTable->fetchRow(array('id=?' => $opts->getOption('id')));

	if (!$user) {
		throw new Exception("User not found.");
	}
	if (!$user->gdProject) {
		throw new Exception("Missing GoodData project ID for user {$user->name} ({$user->id})");
	}

	$exportConfig = new Zend_Config_Ini(ROOT_PATH . '/gooddata/' . $user->strId . '/config.ini', 'salesforce', Array('allowModifications' => true));
	$gd = new App_GoodDataExport($user->gdProject, $user, $config, $exportConfig);

	if ($opts->getOption('setup'))	{
		$gd->setup();
	} elseif ($opts->getOption('load')) {
		$gd->loadData($opts->getOption('all'));
		$user->lastExportDate = date("Y-m-d H:i:s");
		$user->save();
	} elseif ($opts->getOption('update')) {
		$gd->updateStructure($opts->getOption('update'));
	} elseif ($opts->getOption('dump')) {
		echo $gd->dumpTable($opts->getOption('dump'), true, false, true);
	} else {
		echo $opts->getUsageMessage();
	}
} else {
	echo $opts->getUsageMessage();
}

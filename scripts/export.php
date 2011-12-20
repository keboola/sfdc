<?php
define('ROOT_PATH', dirname(dirname($_SERVER['SCRIPT_FILENAME'])));
define('APPLICATION_PATH', ROOT_PATH . '/application');
define('APPLICATION_ENV', 'production');

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
	realpath(APPLICATION_PATH . '/../library'),
	get_include_path(),
)));

/** Zend_Application */
require_once 'Zend/Application.php';

// Create application, bootstrap, and run
$application = new Zend_Application(
	'production',
	APPLICATION_PATH . '/configs/application.ini'
);
$application->bootstrap();

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

if($opts->getOption('project') && $opts->getOption('id')) {
	if($opts->getOption('setup')) {
		$fgd = new App_GoodDataExport($opts->getOption('project'), $opts->getOption('id'), $config);
		$fgd->setup();
	} else

		if($opts->getOption('load')) {
			$fgd = new App_GoodDataExport($opts->getOption('project'), $opts->getOption('id'), $config);
			$fgd->loadData($opts->getOption('all'));
		} else

			if ($opts->getOption('update')) {
				$fgd = new App_GoodDataExport($opts->getOption('project'), $opts->getOption('id'), $config);
				$fgd->updateStructure($opts->getOption('update'));
			} else

				if ($opts->getOption('dump')) {
					$fgd = new App_GoodDataExport($opts->getOption('project'), $opts->getOption('id'), $config);
					echo $fgd->dumpTable($opts->getOption('dump'), true, false, true);
				} else {
					echo $opts->getUsageMessage();
				}
} else {
	echo $opts->getUsageMessage();
}

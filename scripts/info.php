<?php
require_once("config.php");

defined('ROOT_PATH') || define('ROOT_PATH', realpath(dirname(__FILE__) . '/..'));
define('APPLICATION_PATH', ROOT_PATH . '/application');
defined('APPLICATION_ENV') || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV')
	: 'production'));

set_include_path(implode(PATH_SEPARATOR, array(
			realpath(APPLICATION_PATH . '/../library'), get_include_path(),
		)));
require_once 'Zend/Application.php';
$application = new Zend_Application(APPLICATION_ENV, APPLICATION_PATH . '/configs/application.ini');
$application->bootstrap(array('base', 'autoload', 'config', 'db'));

// Setup console input
$opts = new Zend_Console_Getopt(array(
	'id|i=s'	=> 'User ID',
	'describe|d=s' => 'Describe',
	'objects|o' => 'List objects'
));
$opts->setHelp(array(
	'i'	=> 'User ID',
	'd'	=> 'Describe an object',
	'o'	=> 'List all available objects'
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


if (!$opts->getOption('id')) {
	echo $opts->getUsageMessage();
} else {

	$user = $userTable->fetchRow(array('id=?' => $opts->getOption('id')));

	if ($user) {

		// connect do db
		$dbData = Zend_Db::factory('pdo_mysql', array(
			'host'		=> $config->db->host,
			'username'	=> $config->db->login,
			'password'	=> $config->db->password,
			'dbname'	=> $user->dbName
		));

		// test připojení k db
		$dbData->getConnection();
		$dbData->query('SET NAMES utf8');

		// nastavení db adapteru pro všechny potomky Zend_Db_Table
		Zend_Db_Table::setDefaultAdapter($dbData);

		$user->revalidateAccessToken();
		$sf = new App_SalesForceImport($user);

		if ($opts->getOption("describe")) {
			print_r($sf->describe($opts->getOption('describe')));
		} else if ($opts->getOption("objects")) {
			print_r($sf->listObjects());
		} else {
			echo "No action!\n";
		}

	} else {
		echo "Wrong user id!\n";
	}
}

$end = time();
echo 'End: '.date('j. n. Y H:i:s', $end).', Run time: '.round(($end-$start)/60)." min\n";
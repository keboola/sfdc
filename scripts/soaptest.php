<?php

define('ROOT_PATH', dirname(dirname(__FILE__)));
define('APPLICATION_PATH', ROOT_PATH . '/application');
set_include_path(implode(PATH_SEPARATOR, array(realpath(ROOT_PATH . '/library'), get_include_path())));
require_once 'Zend/Application.php';
$application = new Zend_Application('application', APPLICATION_PATH . '/configs/application.ini');
$application->bootstrap(array("base", "autoload", "config", "db", "debug", "log"));

define("USERNAME", "gooddata@hubspot.com");
define("PASSWORD", "Kebulacek23");
define("SECURITY_TOKEN", "PSQEInRFXzkaZpYZGRTAmVAb7");

require_once ('library/SalesForce/SforcePartnerClient.php');
$mySforceConnection = new SforcePartnerClient();
$mySforceConnection->createConnection("library/SalesForce/partner.wsdl.xml");
$mySforceConnection->login(USERNAME, PASSWORD.SECURITY_TOKEN);


var_dump($mySforceConnection->getDeleted("Contact", "2012-05-02T00:00:00Z", "2012-06-01T00:00:00Z"));

/*
// Setup console input
$opts = new Zend_Console_Getopt(array(
	'id|i=s'	=> 'Id of user',
	'full|f' => 'Full import'
));
$opts->setHelp(array(
	'i'	=> 'Id of user',
	'f'	=> 'Full import'
));
try {
	$opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
	echo $e->getUsageMessage();
	exit;
}

$start = time();
echo 'Start: '.date('j. n. Y H:i:s', $start)."\n";

$_u = new Model_BiUser();
$config = Zend_Registry::get('config');

$since = '';
if (!$opts->getOption('full')) {
	$since = date('Y-m-d', strtotime('-4 day')) . 'T00:00:00.000Z';
}

if (!$opts->getOption('id')) {
	echo $opts->getUsageMessage();
} else {
	$u = $_u->fetchRow(array('id=?' => $opts->getOption('id')));
	if ($u) {
		$u->revalidateAccessToken();
		$import = new App_SalesForceImport($u);
		$import->importContacts($since);
		$import->importUsers($since);
		$import->importOpportunities($since);
		$import->importOpportunityHistories($since);
		$import->importAccounts($since);
		$import->importTasks($since);
		$import->importEvents($since);
		$import->importCampaigns($since);


	} else {
		echo "Bad user id!\n";
	}
}

$end = time();
echo 'End: '.date('j. n. Y H:i:s', $end).', Run time: '.round(($end-$start)/60)." min\n";
*/
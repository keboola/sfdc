<?php

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


$start = time();
echo 'Start: '.date('j. n. Y H:i:s', $start)."\n";

$_u = new Model_Users();
$config = Zend_Registry::get('config');

$since = date('Ymd', strtotime('-4 days'));
$until = date('Ymd', strtotime('-1 day'));

foreach($_u->fetchAll() as $u) {
	$ao = new App_AdWordsImport($u->id, $u->oauthToken, $u->oauthTokenSecret, $config->adwords->developerToken,
		$config->adwords->oauthKey, $config->adwords->oauthSecret);
	$ao->importClients($config->adwords->managerId);
	if (!$u->isImported) {
		$since = date('Ymd', strtotime('-60 days'));
	}
	if ($ao->importCampaigns($since, $until)) {
		if (!$u->isImported) {
			$u->isImported = 1;
			$u->save();
		}

		if (!empty($u->idGD)) {
			$fgd = new App_GoodDataExport($u->idGD, $u->id, $config);
			$fgd->loadData();
		}
	}
}

$end = time();
echo 'End: '.date('j. n. Y H:i:s', $end).', Run time: '.round(($end-$start)/60)." min\n";
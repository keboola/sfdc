<?php
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('APPLICATION_PATH', ROOT_PATH . '/application');
set_include_path(implode(PATH_SEPARATOR, array(realpath(ROOT_PATH . '/library'), get_include_path())));
require_once 'Zend/Application.php';
$application = new Zend_Application('application', APPLICATION_PATH . '/configs/application.ini');
$application->bootstrap(array("base", "autoload", "config", "db", "debug", "log"));

$start = time();
echo 'Start: '.date('j. n. Y H:i:s', $start)."\n";

$userTable = new Model_BiUser();
$config = Zend_Registry::get('config');
$log = Zend_Registry::get('log');

App_StorageApi::setLogger(function($message, $data) use($log) {
	$log->log($message, Zend_Log::INFO, $data);
});

$storageApi = new App_StorageApi("bb05feb9cda885acedcb17d21da2b461", "https://connection-devel.keboola.com");

//var_dump($storageApi->getToken(3));
//var_dump($storageApi->createToken(array("in.c-SFDC" => "write")));
//$tokenId = $storageApi->createToken(array("in.c-SFDC" => "read"));
//var_dump($storageApi->updateToken($tokenId, array("in.c-SFDC" => "write"), "testDesc"));
//var_dump($storageApi->listTokens());
// var_dump($storageApi->refreshToken());

//$storageApi->dropTable("in.c-SFDC.Account");
//$storageApi->dropTable("in.c-SFDC.Opportunity");
//$storageApi->dropTable("in.c-SFDC.OpportunityLineItem");
//$storageApi->dropTable("in.c-SFDC.OpportunityLineItemSchedule");
//$storageApi->dropTable("in.c-SFDC.OpportunityStage");
//$storageApi->dropTable("in.c-SFDC.Split__c");
//$storageApi->dropTable("in.c-SFDC.User");
//var_dump($storageApi->getBuckets());
//$bucketTestId = $storageApi->createBucket("SFDC", App_StorageApi::STAGE_IN, "Salesforce Data");
//$bucketTest2Id = $storageApi->createBucket("Test2", App_StorageApi::STAGE_IN, "test2");
//$storageApi->createTable($bucketTestId, "TestTable", ROOT_PATH . "/tmp/shazam/in.sfdc.Split__c.csv", ",", '"', "Id", 1);

//var_dump($storageApi->listBuckets());
//var_dump($storageApi->getBucket("in.c-Test"));
//var_dump($storageApi->listTables("in.c-Test"));
//var_dump($storageApi->listTables("in.c-SFDC"));
//var_dump($storageApi->dropBucket("in.c-Test"));

var_dump($storageApi->getTable("in.c-SFDC.User"));
// TODO Does not work
var_dump($storageApi->getGdXmlConfig("in.c-SFDC.User"));
// TODO Does not work
var_dump($storageApi->exportTable("in.c-SFDC.User"));
// TODO Does not work
var_dump($storageApi->uploadFile("/opt/ebs-disk/www/bi-sfdc/tmp/shazam/Opportunity.csv"));

$end = time();
echo 'End: '.date('j. n. Y H:i:s', $end).', Run time: '.round(($end-$start)/60)." min\n";
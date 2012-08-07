<?php
define('ROOT_PATH', dirname(dirname(__FILE__)));
define('APPLICATION_PATH', ROOT_PATH . '/application');
set_include_path(implode(PATH_SEPARATOR, array(realpath(ROOT_PATH . '/library'), get_include_path())));
require_once 'Zend/Application.php';
require_once ROOT_PATH . '/vendor/autoload.php';
$application = new Zend_Application('application', APPLICATION_PATH . '/configs/application.ini');
$application->bootstrap(array("base", "autoload", "config", "db", "debug", "log"));

$start = time();
echo 'Start: '.date('j. n. Y H:i:s', $start)."\n";



$userTable = new Model_BiUser();
$config = Zend_Registry::get('config');
$log = Zend_Registry::get('log');
$db = Zend_Registry::get('db');

Keboola\StorageApi\Client::setLogger(function($message, $data) use($log) {
	$log->log($message, Zend_Log::INFO, $data);
});

// Transfer config to Storage API

$id = 11;
$user = $userTable->fetchRow(array('id=?' => $id));
$passSecret = $db->fetchOne("SELECT AES_DECRYPT(?, ?)", array($user->sfdcPassSecret, $config->app->salt));
// $passSecret = "Kebulacek24bqfxnagFLbGmNR6MtoRAvxYxv";
//var_dump($passSecret);
//$passSecretEncrypted = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($config->app->salt), $passSecret, MCRYPT_MODE_CBC, md5(md5($config->app->salt))));
//$passSecretDecrypted = $decrypted = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($config->app->salt), base64_decode($passSecretEncrypted), MCRYPT_MODE_CBC, md5(md5($config->app->salt))), "\0");
//var_dump($passSecretEncrypted);
//var_dump($passSecretDecrypted);
// var_dump($passSecret);

$data = array(
	"id" => $user->strId,
	"accessToken" => $user->sfdcAccessToken,
	"refreshToken" => $user->sfdcRefreshToken,
	"instanceUrl" => $user->sfdcInstanceUrl,
	"username" => $user->sfdcUsername,
	"passSecret" => $passSecret,
	"clientId" => $user->sfdcClientId,
	"clientSecret" => $user->sfdcClientSecret,
	"config" => str_replace(array("\r", "\n"), "", $user->sfdcConfig),
	"lastImportDate" => $user->sfdcLastImportDate
);

$storageApi = new Keboola\StorageApi\Client("f20cb34ada408a99fd2d2d3739f96d60", "https://connection.keboola.com");
Keboola\StorageApi\OneLiner::setClient($storageApi);
Keboola\StorageApi\OneLiner::$tmpDir = ROOT_PATH . "/tmp/";
$model = new Keboola\StorageApi\OneLiner("in.c-config.SFDC");

foreach($data as $key => $value) {
	$model->$key = $value;
}
// $model->save();

// $bucketId = $storageApi->createBucket("config", Keboola\StorageApi\Client::STAGE_IN, "Configuration");
// $tableId = $storageApi->createTable($config->storageApi->configBucket, $config->storageApi->name, ROOT_PATH . "/tmp/config.csv");
// $storageApi->writeTable($tableId, ROOT_PATH . "/tmp/config.csv");
// TODO oneline csv model - metadata, data, autosettery, autogettery

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
// $bucketTestId = $storageApi->createBucket("SFDC2", Keboola\StorageApi\Client::STAGE_IN, "Salesforce Data");
//$bucketTest2Id = $storageApi->createBucket("Test2", App_StorageApi::STAGE_IN, "test2");
//$storageApi->createTable($bucketTestId, "TestTable", ROOT_PATH . "/tmp/shazam/in.sfdc.Split__c.csv", ",", '"', "Id", 1);

//var_dump($storageApi->listBuckets());
//var_dump($storageApi->getBucket("in.c-Test"));
//var_dump($storageApi->listTables("in.c-Test"));
//var_dump($storageApi->listTables("in.c-SFDC"));
// var_dump($storageApi->dropBucket("in.c-SFDC2"));
// var_dump($storageApi->getTable("in.c-SFDC.User"));
//var_dump($storageApi->getGdXmlConfig("in.c-SFDC.User"));
//var_dump($storageApi->exportTable("in.c-SFDC.User"));
// var_dump($storageApi->uploadFile("/opt/ebs-disk/www/bi-sfdc/tmp/shazam/Opportunity.csv"));

$end = time();
echo 'End: '.date('j. n. Y H:i:s', $end).', Run time: '.round(($end-$start)/60)." min\n";
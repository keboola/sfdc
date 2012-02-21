<?php
// Define path to application directory
define('ROOT_PATH', dirname(__DIR__));
define('APPLICATION_PATH', ROOT_PATH . '/application');
define('TMP_PATH', ini_get('upload_tmp_dir'));
define('EXEC_PATH', ROOT_PATH . '/exec');
define('APPLICATION_ENV', 'production');

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
	realpath(ROOT_PATH . '/library'),
	realpath(ROOT_PATH . '/tests'),
	get_include_path(),
)));
ini_set('display_errors', true);

date_default_timezone_set('Europe/Prague');

require_once 'Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance()->registerNamespace('App_');

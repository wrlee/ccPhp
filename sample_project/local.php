<?php
/**
 * This file, if it exists, is included by config.php to configure local 
 * settings. If this file is not included in source control, then its settings
 * remain specific to a given location. This can be used to distinguish between
 * development, test, and production environments, for example. 
 */
$app->setDevMode(
	$app->getDevMode()|
	ccApp::MODE_TESTING);

$valid_ips = array(
//	'home' => 'xxx.xxx.xxx.xxx',
);

// Check if this is a valid debug user. 
if (in_array($_SERVER['REMOTE_ADDR'],$valid_ips) || isset($_SESSION['Debug']))
{
	if (isset($_SESSION['Debug']) && $_SESSION['Debug'] == 'DB')
		$app->setDevMode(
			ccApp::MODE_DEVELOPMENT);
	else
		$app->setDevMode(
			$app->getDevMode()|
			ccApp::MODE_DEVELOPMENT);

	$dispatch
		->addPage('TestController')
		;
ccTrace::setOutput(NULL);
// ccTrace::tr('testA...');
} // [END Debug only]
	
// RedBeanPHP DB settings. 
if ($app->getDevMode() & ccApp::MODE_PRODUCTION)
	$rb_db_server = Array('mysql:host=mysql.handthingsdown.com;dbname=htd_db','handthingsdown','chemar6');
else
	$rb_db_server = Array('mysql:host=mysql.handthingsdown.com;dbname=htd_db_dev','htd_ccphp','turtle');

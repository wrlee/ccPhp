<?php 
/**
 * This is an example of the configuration of a site that uses the ccPhp Framework. 
 *
 * File layout (so far):
 *   sitecode-+-|public-+-|js		Public/web facing directory
 *            |         +-|css
 *            |         index.php	Default base (includes config.php)
 *            |         .htaccess	Mod-rewrite to route all URIs to index.php
 *            +-|templates			Smarty templates
 *            +--app.php			Site/app config and startup file (includes ccApp)
 *   framework+-|core--cc*.php		ccPhp core Framework files.
 *            ccApp.php				"Root" framework file(include this to use ccPhp).
 *            cc*.php				Add'l non-core framework files.
 *
 * You can define searches for site's class files via a local __autoload() or other
 * function (which must be registered via spl_autoload_register())
 *
 */

//****
// 0. Create public, web-facing directory with index.php which includes this file.
//    And include the .htaccess file which directs all unresolved paths to that
//    index.php file.
//    ## .htaccess:
//    RewriteCond %{REQUEST_FILENAME} !-f
//    RewriteCond %{REQUEST_FILENAME} !-d
//    RewriteRule .* index.php

error_reporting(E_ALL|E_STRICT);
// error_reporting(ini_get('error_reporting')|E_STRICT);
// session_start();	// Req'd by Facebook (start session now to avoid output/header errors).

//****
// 1. "Activate" the ccPhp Framework from its directory, by including its primary
//    class, ccApp.
require($_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'ccPhp'.DIRECTORY_SEPARATOR.'ccFramework'.DIRECTORY_SEPARATOR.'ccApp.php');

// ccTrace::setHtml(TRUE);
// ccTrace::setSuppress();					// Ensure no accidental output
// ccTrace::setOutput('/home/wrlee/htd.log');	// Output to file.
// ccTrace::setLogging('/home/wrlee/htd.log');	// Log to file.

/**
 * App specific class extends the ccFramework's app class, ccApp. This will 
 * be a singularlity instantiated by ccApp::createApp(). 
 */
class uspsApp extends ccApp
{
	function __construct()
	{
		parent::__construct();
		$this->setWorkingDir('.var')
			 ->setDevMode( 							// Set app mode flags (CCAPP_PRODUCTION, CCAPP_DEVELOPMENT)
				CCAPP_DEVELOPMENT
			 )
			 ->addClassPath('classes')				// Site's support files (base-class)
													// Add classname->file mappings
			 ->addClassPath($this->getFrameworkPath().'..'.DIRECTORY_SEPARATOR.'Smarty'.DIRECTORY_SEPARATOR.'Smarty.class.php', 'Smarty')
			 ->addClassPath($this->getFrameworkPath().'..'.DIRECTORY_SEPARATOR.'LessPhp'.DIRECTORY_SEPARATOR.'lessc.inc.php', 'lessc')
//			 ->addClassPath('..'.DIRECTORY_SEPARATOR.'RedBeanPHP'.DIRECTORY_SEPARATOR.'rb.php','R')
//			 ->addClassPath('..'.DIRECTORY_SEPARATOR.'Facebook'.DIRECTORY_SEPARATOR.'facebook.php','Facebook')
//			 ->addPhpPath('/home/wrlee/php')		// My common library files
			;
											// If sessions are used, set the directory
											// specific to this app.
		session_save_path($this->createWorkingDir('sessions'));	
//		session_start();
											// Log directory.
		$logfile = $this->createWorkingDir('logs').basename($this->getUrlOffset()).'.log';
		ccTrace::setOutput($logfile);		// Output to file.
		ccTrace::setLogging($logfile);		// Log to file.

		//****
		// 5. Set the app's main "page" and "run" the app.
		$dispatch = new ccChainDispatcher();		// Allocate before local.php inclusion
		$dispatch									// Add controller pages to chain 
			->addPage(new uspsAjaxController())		// Item view and claiming
			->addPage(new ccSmartyController()) 	// Simple Smarty template support
			->addPage(new ccLessCssController()) 	// Less CSS support
//			->addPage(new ccTreeController())
//			->addPage('FacebookNotificationController')	// FB notifications
//			->addPage('FacebookAppController')		// FB App (should be last)
			;
		$this->setPage($dispatch);					// Set dispatcher as app's "page"
	} // __construct()
} // class uspsApp

$time=microtime(1);
//****
// 2. Create and configure the Application object (singleton)

session_save_path(dirname(__FILE__).'/.var/sessions');	
session_start();
//$sessActive = (session_status() == PHP_SESSION_ACTIVE);
//if (!$sessActive)					// If session support not running
//	session_start();				//   turn on to presist browser info
//
//if ( isset($_SESSION['ccApp']) ) 	// If already cached, return info
//{
//	$app = unserialize($_SESSION['ccApp']);
//    if ( !$sessActive )				// Session wasn't running
//    	session_commit();			//   So turn back off
//}
//else
	$app = ccApp::createApp(dirname(__FILE__),'uspsApp');	// Tell app where the site code is.
//ccTrace::tr($app);

//$debug = ($app->getDevMode() & CCAPP_DEVELOPMENT);
//ccTrace::setSuppress(!($app->getDevMode() & CCAPP_DEVELOPMENT));
ccTrace::tr('==='.$_SERVER['REMOTE_ADDR'].' '.(microtime(1)-$time).' createApp()');

//****
// 3. Create and configure stuff before attempting to include "local" settings.
//	  This allows a local file to reconfigure app during development 
//	  (or production) to distiguish between those separate distributions.

//****
// To set values that won't be deployed in production mode, create a local file
// (that is not deployed to production--probably not checked into source control)
//@include 'local.php';	//<*********		// If exists, load overridable settings
//@include '../production.php';	//<***		// Common production settings (shared by all installations)
	
//****
// 4. Configure plugins or other stuff you want to use.
// Config RedBean DB module

// if (!isset($rb_db_server) || !is_array($rb_db_server) || count($rb_db_server) != 3)
// {
// 	throw new Exception('$db_server not properly set');
// }
// else
// {
// 	R::setup($rb_db_server[0],$rb_db_server[1],$rb_db_server[2]);
// 	R::freeze( true );	// Freeze database, for now. 
// }
// R::debug($debug);

// echo "<pre>";
// var_dump($app->getUrlOffset(),$app->getRootUrl(),dirname($_SERVER['SCRIPT_NAME']),basename(dirname($_SERVER['SCRIPT_NAME'])),$_SERVER,$GLOBALS);
// echo "</pre>";
	
$time=microtime(1);
$request = new ccRequest();
ccTrace::tr('==='.$_SERVER['REMOTE_ADDR'].' '.(microtime(1)-$time).' ccRequest()');

ccTrace::log((isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'].' ' : '')
            .(isset($_COOKIE['PHPSESSID']) ? $_COOKIE['PHPSESSID'].' ' : '')
            .'"'.$request->getUserAgent().'" '.$request->getUrl() );
$time=microtime(1);
$app->dispatch($request);
ccTrace::tr('==='.$_SERVER['REMOTE_ADDR'].' '.(microtime(1)-$time).' dispatch()');


//**** END OF FILE ****//
// Return to index.php //

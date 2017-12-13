<?php
/**
 * This is an example of the configuration of a site that uses the ccPhp Framework.
 *
 * File layout (so far):
 *   sitecode-+-|public-+-|js		Public/web facing directory
 *            |         +-|css
 *            |         index.php	Default base (includes config.php)
 *            |         .htaccess	Mod-rewrite to route all URIs to index.php
 *            +--config.php			Site/app config and startup file (includes ccApp)
 *   framework+-|core--cc*.php		ccPhp core Framework files.
 *            ccApp.php				"Root" framework file(include this to use ccPhp).
 *            cc*.php				Add'l non-core framework files.
 *
 * You can define searches for site's class files via a local __autoload() or other
 * function (which must be registered via spl_autoload_register())
 *
 * Concepts:
 * ccApp:  There is an App singleton object that represents the application and
 *		its properties. (At the moment, there isn't much functionality.
 * ccRequest:  Represents the current page request. It parses the URL and
 *		request environment processing. In particular, it also parses the
 *		User-Agent string to determine characteristics about the requesting
 *		client.
 * ccPageInterface: Is an interface for any kind of class that renders a page.
 *		The interface contains a single method, render(), that returns TRUE
 *		(page was rendered) or FALSE (page was not rendered). When FALSE, it is
 *		assumed to be a 404 response. render() takes a single parameter,
 *		ccRequest, which the implementation can use to determine what to render.
 *		A controller type of page rendering object could implement its render()
 *		to correlate various URL paths with specific methods of its own. Other
 *		implmentations might dispatch to other ccPageInterface objects, thereby
 *		acting as dispatch-controller objects.
 *
 * Page Interface implementation examples:
 * ccChainDispatcher: Dispatches app flow to a "chain" of other page-interface
 *		objects to generate content. Each object is given a chance to process
 *		the request. If no objects process the request (i.e., all of their
 *		render() methods return FALSE), a 404 error results.
 *		Note: The ccRequest object is cloned for each page-object so that
 *		changes to the request-object won't cause side-effects with
 *		subsequent page-objects in the chain.
 * ccSimpleController: Uses the request object's URL path, mapping the next
 *		component to a method within this object, if it exists. If there is no
 *		URL component, then it maps to the default method name, "index", if it
 *		exists. If a matching method is found, before() is first called (if it
 *		exists) to perform common handling. If before() returns FALSE, the
 *		mapped method is not called. The return value of render() is the value
 *		of the before() (if FALSE) or mapped-method's return value, otherwise it
 *		returns FALSE.
 *		Note: This might be renamed to ccController
 */

//****
// 0. Create public, web-facing directory with index.php which includes this file.
//    And include the .htaccess file which directs all unresolved paths to that
//    index.php file.
//    ## .htaccess:
//    RewriteCond %{REQUEST_FILENAME} !-f
//    RewriteCond %{REQUEST_FILENAME} !-d
//    RewriteRule .* index.php

// !defined('DEV_MODE') && define('DEV_MODE',1);
if (!defined('DEV_MODE') || DEV_MODE != 0)	// Conservative, default to dev-mode
	error_reporting(E_ALL); 		// Dev/debug?  Include all (use E_ALL|E_STRICT for PHP < 5.4)
else
// error_reporting(error_reporting()|E_STRICT);	// Add "strict"
	error_reporting(E_STRICT);			// Relative quiet mode (production?)

// session_start();	// Req'd by Facebook (start session now to avoid output/header errors).

//****
// 1. "Activate" the ccPhp Framework from its directory, by including its primary
//    class, ccApp. CCPHP_DIR can be set, separately, in a installation-specific
//		setting. Use composer's autoload, if possible.
if ( file_exists(__DIR__.DIRECTORY_SEPARATOR.'vendor/autoload.php') )
	require(__DIR__.DIRECTORY_SEPARATOR.'vendor/autoload.php');
elseif ( defined('CCPHP_DIR') )
	require(CCPHP_DIR . DIRECTORY_SEPARATOR . 'ccApp.php');
else
	require(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'ccPhp' . DIRECTORY_SEPARATOR . 'ccApp.php');

//****
// 2. Create and configure the Application object (singleton)
$app = ccApp::createApp(__DIR__);	// Tell app where the site code is.
$app
	->setDevMode( 							// Set app mode flags (PRODUCTION, DEVELOPMENT, STAGING, TESTING)
		CCAPP_DEVELOPMENT
	)
	->setWorkingDir('.var')				// Set working dir (default, app directory)

//	->addClassPath('classes')			// Site's support files (base-class)
												// Add classname->file mappings
//	->addClassPath('..'.DIRECTORY_SEPARATOR.'RedBeanPHP'.DIRECTORY_SEPARATOR.'rb.php','R')
//	->addClassPath($app->getFrameworkPath().'..'.DIRECTORY_SEPARATOR.'LessPhp'.DIRECTORY_SEPARATOR.'lessc.inc.php', 'lessc')
//	->addClassPath('..'.DIRECTORY_SEPARATOR.'Facebook'.DIRECTORY_SEPARATOR.'facebook.php','Facebook')
//	->addPhpPath('/home/wrlee/php')			// My common library files
	;
// Set log output location. Logname based on public URL root
{
	$logfile = basename($app->getUrlOffset());
//	$logfile = $app->createWorkingDir('logs').basename($app->getUrlOffset()).'.log';
	$logfile = $app->createWorkingDir('logs').($logfile  ? $logfile : 'root' ).'.log';
	ccTrace::setOutput($logfile);					// Output to file.
	ccTrace::setLogging($logfile);				// Log to file.
}

// Move the following before createApp() if we need to debug ccApp's creation
//   but leave it here, if we want to debug other ccApp::* calls
//ccTrace::setHtml(TRUE);
//ccTrace::setSuppress();					// Ensure no accidental output
//ccTrace::setOutput('/home/wrlee/htd.log');	// Output to file.
//ccTrace::setLogging('/home/wrlee/htd.log');	// Log to file.

//****
// 3. Create and configure stuff before attempting to include "local" settings.
//	  This allows a local file to reconfigure app during development
//	  (or production) to distiguish between those separate distributions.
$dispatch = new ccChainDispatcher();		// Allocate before local.php inclusion

//****
// To set values that won't be deployed in production mode, create a local file
// (that is not deployed to production--probably not checked into source control)
@include 'local.php';	//<*********		// If exists, load overridable settings
@include '../production.php';	//<***		// Common production settings (shared by all installations)

//****
// 4. Configure plugins or other stuff you want to use.
/*
// Config RedBean DB module
$debug = ($app->getDevMode() & CCAPP_DEVELOPMENT);
//ccTrace::setSuppress(!($app->getDevMode() & CCAPP_DEVELOPMENT));

if (!isset($rb_db_server) || !is_array($rb_db_server) || count($rb_db_server) != 3)
{
	throw new Exception('$db_server not properly set');
}
else
{
	R::setup($rb_db_server[0],$rb_db_server[1],$rb_db_server[2]);
	R::freeze( true );	// Freeze database, for now.
}

// R::debug($debug);
// $smarty->setDebug($debug);
*/
//****
// 5. Set the app's main "page" and "run" the app.
$dispatch									// Add controller pages to chain
	->addPage(new ccSmartyController()) 	// Simple Smarty template support
	->addPage(new ccLessCssController()) 	// Less CSS support
//	->addPage('FacebookNotificationController')	// FB notifications
//	->addPage('FacebookAppController')		// FB App (should be last)
	;
$app->setPage($dispatch);					// Set dispatcher as app's "page"

/* If you want to take explicit control over the request (perhaps to interrogate it)
	$request = new ccRequest();
	ccTrace::log((isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'].' ' : '')
	            .(isset($_COOKIE['PHPSESSID']) ? $_COOKIE['PHPSESSID'].' ' : '')
	            .'"'.$request->getUserAgent().'" '.$request->getUrl() );
	$app->dispatch($request);
*/
$app->dispatch();

// Return to index.php //

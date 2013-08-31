<?php 
/**
 * This is an example of the configuration of a site that uses the ccPhp Framework. 
 *
 * File layout (so far):
 *   sitecode-+-|public-+-|js		Public/web facing directory
 *            |         +-|css
 *            |         index.php	Default base (includes config.php)
 *            |        .htaccess	Mod-rewrite to route all URIs to index.php
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
 *
 * @todo Consider a separate "site" object (to allow site specific configuration),
 *       currently part of the "app" object.
 * @todo Consider moving most of this code to index.php?
 *
 * Things I need to do soon:
 *  @todo Add example of DB/model component (Doctrine? RedBean?)
 *  @todo Add internal "redirection" support
 *  @todo Allow site paths to auto-generate paths. 
 *  @todo Debugging/tracing component (work in progress: ccTrace
 *	@todo Move error handling ccError class and refer through ccApp?
 *
 * Things I dunno how to do:
 *  @todo Need for session support?
 *  @todo Page caching 
 *  @todo ob_start() support
 *  @todo Create structure of simple front-end event mapping to support here.
 *  @todo CSS and JS compression/minimization support for production mode. 
 *  @todo Single ccApp.setDebug() setting that will cascade thru components.
 *	@todo Logging support.
 * 	@todo Reconsider DevMode handling (rename to AppMode). 
 * 	@todo Need a way to set "debug" setting that will cascade thru components.
 *	@todo Look into using AutoLoad package (by the Doctrine and Symfony folks)?
 */

//****
// 0. Create public, web-facing directory with index.php which includes this file.
//    And include the .htaccess file which directs all unresolved paths to that
//    index.php file.
//    ## .htaccess:
//    RewriteCond %{REQUEST_FILENAME} !-f
//    RewriteCond %{REQUEST_FILENAME} !-d
//    RewriteRule .* index.php

// error_reporting(E_ALL);
error_reporting(E_ALL|E_STRICT);
// error_reporting(E_STRICT);
// error_reporting(ini_get('error_reporting')|E_STRICT);
session_start();	// Req'd by Facebook (start session now to avoid output/header errors).

//****
// 1. "Activate" the ccPhp Framework from its directory, by including its primary
//    class, ccApp.
require(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'ccFramework'.DIRECTORY_SEPARATOR.'ccApp.php');
// ccTrace::setHtml(TRUE);
// ccTrace::setSuppress();					// Ensure no accidental output
ccTrace::setOutput('/home/wrlee/htd.log');	// Output to file.
ccTrace::setLogging('/home/wrlee/htd.log');	// Log to file.

//****
// 2. Create and configure the Application object (singleton)
$app = ccApp::createApp()
	->setSitePath(dirname(__FILE__))		// Tell app where the site code is.
	->setDevMode( 							// Set app mode flags (PRODUCTION, DEVELOPMENT, STAGING, TESTING)
		ccApp::MODE_PRODUCTION
	)
	->setWorkingDir('.var')					// Set working dir (default 'working')

	->addClassPath('classes')				// Site's support files (base-class)
											// Add classname->file mappings
	->addClassPath('..'.DIRECTORY_SEPARATOR.'Smarty'.DIRECTORY_SEPARATOR.'Smarty.class.php', 'Smarty')
	->addClassPath('..'.DIRECTORY_SEPARATOR.'RedBeanPHP'.DIRECTORY_SEPARATOR.'rb.php','R')
	->addClassPath('..'.DIRECTORY_SEPARATOR.'Facebook'.DIRECTORY_SEPARATOR.'facebook.php','Facebook')
	->addPhpPath('/home/wrlee/php')			// My common library files
											// Configure site (the following might
											//   be moved to its own ccSite object
//	->setSitePublicPath('public')			// Public facing web path
	;
// ccApp::tr($app->classpath);
											// Log directory.
$logfile = $app->createSiteDir($app->getWorkingPath().'logs').'htd.log';
ccTrace::setOutput($logfile);	// Output to file.
ccTrace::setLogging($logfile);	// Log to file.

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
// Config RedBean DB module
$debug = ($app->getDevMode() & ccApp::MODE_DEVELOPMENT);
//ccTrace::setSuppress(!($app->getDevMode() & ccApp::MODE_DEVELOPMENT));

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
	
//****
// 5. Set the app's main "page" and "run" the app.
$dispatch									// Add controller pages to chain 
	->addPage(new ItemController()) 		// Item view and claiming
	->addPage('ccCssController')			// CSS handler
	->addPage('FacebookNotificationController')	// FB notifications
	->addPage('AdminController')			// Admin functions
	->addPage('WebController')				// Misc web stuff
	->addPage('FacebookAppController')		// FB App (should be last)
	;
$app->setMainPage($dispatch);				// Set dispatcher as app's "page"
	
$request = new ccRequest();
ccTrace::log((isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'].' ' : '')
            .(isset($_COOKIE['PHPSESSID']) ? $_COOKIE['PHPSESSID'].' ' : '')
            .'"'.$request->getUserAgent().'" '.$request->getUrl() );
$app->dispatch($request);

//**** END OF FILE ****//
// Return to index.php //
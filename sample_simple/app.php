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

error_reporting(E_ALL); 			// Include all (use E_ALL|E_STRICT for PHP < 5.4)
// error_reporting(E_STRICT);			// Relative quiet mode
// error_reporting(error_reporting()|E_STRICT);	// Add "strict"

// session_start();	// Req'd by Facebook (start session now to avoid output/header errors).

//****
// 1. "Activate" the ccPhp Framework from its directory, by including its primary
//    class, ccApp.
require(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'ccPhp'.DIRECTORY_SEPARATOR.'ccApp.php');
// ccTrace::setHtml(TRUE);
// ccTrace::setSuppress();					// Ensure no accidental output
// ccTrace::setOutput('/home/wrlee/htd.log');	// Output to file.
// ccTrace::setLogging('/home/wrlee/htd.log');	// Log to file.

//****
// 2. Create and configure the Application object (singleton)
$app = ccApp::createApp(__DIR__);	// Tell app where the site code is.
$app
	->setDevMode( 							// Set app mode flags (PRODUCTION, DEVELOPMENT, STAGING, TESTING)
		CCAPP_DEVELOPMENT
	)
	->setWorkingDir('.var')				// Set working dir (default, app directory)

//	->addClassPath('classes')			// Site's support files (base-class)
	;
// Set log output location.
{
	$logfile = $app->createWorkingDir('logs').'app.log';
	ccTrace::setOutput($logfile);		// Output to file.
	ccTrace::setLogging($logfile);	// Log to file.
}

//****
// 3. Create and configure stuff before attempting to include "local" settings.
//	  This allows a local file to reconfigure app during development
//	  (or production) to distiguish between those separate distributions.

//****
// 4. Configure plugins or other stuff you want to use.
// Config RedBean DB module
$debug = ($app->getDevMode() & CCAPP_DEVELOPMENT);

/**
 * Sample "page" for ccPhp
 */
 class MyPage
 	implements ccPageInterface
 {
 	public function render(ccRequest $request)
 	{
 		echo "Your first page content.".PHP_EOL;
 		return true;		// Request "handled"
 	} // render()
 } // class MyPage


//****
// 5. Set the app's main "page" and "run" the app.
$app->setPage(new MyPage())		// Set dispatcher as app's "page"
    ->dispatch();						// Process request

// Return to index.php //

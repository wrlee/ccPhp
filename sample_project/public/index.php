<?php
/**
 * Application web "entry-point".
 * @author       name <email@cachecrew.com>
 */

/**
 * Look for local installation definitons. This is optional, you can, of course
 * define or hard-code whatever you want, in this file.
 */
// Set APP_DIR, and other installation dependent values (e.g., CCPHP_DIR).
is_file('local_defs.php') && include('local_defs.php');

// If not defined, set a default APP_DIR location
if (!defined('APP_DIR'))
	define( 'APP_DIR', dirname(dirname(__FILE__)) );

// Try to load PHP application
if (    !require_once(APP_DIR.DIRECTORY_SEPARATOR.'app.php')
	  && !require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'app.php') )
		die('APP_DIR was not defined for this application.<br/>');

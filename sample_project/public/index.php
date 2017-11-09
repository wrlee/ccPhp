<?php
/**
 * Application web "entry-point".
 *
 * This file could be as simple as:
 *		require_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'app.php');
 *
 * but then each installation would have to be modified, even as it moves.
 * Instead, this file looks for a definitons file which is not tracked and
 * is installation dependent.
 *
 * @author       name <email@cachecrew.com>
 */

/**
 * Look for local installation definitons. This is optional, you can, of course
 * define or hard-code whatever you want, in this file.
 */
// Set APP_DIR, and other installation dependent values (e.g., CCPHP_DIR).
is_file('local_defs.php') && include('local_defs.php');

// If APP_DIR defined and is valid,
if ( defined('APP_DIR') && is_file(APP_DIR . DIRECTORY_SEPARATOR . 'app.php') ) {
	require_once(APP_DIR . DIRECTORY_SEPARATOR . 'app.php');
elseif ( is_file(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'app.php') )
	// Default, if prior test fails assume parent dir contains app.php (as with symlink)
	require_once(dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'app.php');
else
	die('APP_DIR was not defined for this application.<br/>');

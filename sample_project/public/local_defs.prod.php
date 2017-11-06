<?php

define('DEV_MODE', 0);

define('APP_DIR', '/home/'.$_SERVER['DH_USER'].'/apps/'.basename(__DIR__));
if (DEV_MODE == 1) {
	define('CCPHP_DIR', '/home/'.$_SERVER['DH_USER'].'/ccPhp.dev');
	error_reporting(E_ALL); 		// Dev/debug?  Include all (use E_ALL|E_STRICT for PHP < 5.4)
}
else {
	define('CCPHP_DIR', '/home/'.$_SERVER['DH_USER'].'/ccPhp');
	error_reporting(E_STRICT); 		// Production?
}

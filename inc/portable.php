<?php
/**
 *
 */
// [BEGIN] Portability settings
// @see http://www.php.net/manual/en/function.phpversion.php
// @see http://www.php.net/manual/en/reserved.constants.php#reserved.constants.core
if (!defined('PHP_VERSION_ID'))
{
    $_version = explode('.', PHP_VERSION);

    define('PHP_VERSION_ID', ($_version[0] * 10000 + $_version[1] * 100 + $_version[2]));
}
if (PHP_VERSION_ID < 50400) {
	if (PHP_VERSION_ID < 50300)
	{
		if (PHP_VERSION_ID < 50207)
		{
			if (PHP_VERSION_ID < 50200)
				define('E_RECOVERABLE_ERROR',4096);
			define('PHP_MAJOR_VERSION', $_version[0]);
			define('PHP_MINOR_VERSION', $_version[1]);

			$_version = explode('-', $_version[2]);
			define('PHP_EXTRA_VERSION', $_version[0]);
			define('PHP_RELEASE_VERSION', isset($_version[1]) ? $_version[1] : '');
		}
		define('E_DEPRECATED', 8092);
		define('E_USER_DEPRECATED', 16384);
		define('__DIR__', dirname(__FILE__));
	}
	define('PHP_SESSION_DISABLED',0);
	define('PHP_SESSION_NONE',1);
	define('PHP_SESSION_ACTIVE',2);
	/**
	 * Return $status of session. Built-in available in 5.4
	 * @return int enum of $status
	 * @see php.net
	 */
	function session_status()
	{
		return \session_id() === '' ? PHP_SESSION_NONE : PHP_SESSION_ACTIVE;
	}
}
unset($_version);	// Not needed any longer
// [END] Portability settings

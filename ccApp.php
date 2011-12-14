<?php
/** File: ccApp.php
 * 
 * The ccApp class represents the application. It is a singleton
 *
 * @todo Look into using AutoLoad package (by the Doctrine and Symfony folks)
 * @todo Add session handling?
 * @todo Need a way to set "debug" setting that will cascade thru components.
 * @todo Move error handling ccError class and refer through ccApp
 *
 */
/*
 * 2010-10-22 404 uses exceptions
 *          - Extend classpath interpretation to enter specific class specs.
 */
//******************************************************************************
// [BEGIN] Portability settings
// @see http://www.php.net/manual/en/function.phpversion.php 
// @see http://www.php.net/manual/en/reserved.constants.php#reserved.constants.core
if (!defined('PHP_VERSION_ID')) 
{
    $_version = explode('.', PHP_VERSION);

    define('PHP_VERSION_ID', ($_version[0] * 10000 + $_version[1] * 100 + $_version[2]));
}
//echo PHP_VERSION_ID.'<br/>';
if (PHP_VERSION_ID < 50207) 
{
    define('PHP_MAJOR_VERSION', $_version[0]);
    define('PHP_MINOR_VERSION', $_version[1]);
	
	$_version = explode('-', $_version[2]);
	define('PHP_EXTRA_VERSION', $_version[0]);
	define('PHP_RELEASE_VERSION', isset($_version[1]) ? $_version[1] : '');
}
unset($_version);	// Not needed any longer
// [END] Portability settings
//******************************************************************************

set_error_handler(Array('ccApp','onError'));
set_exception_handler(Array('ccApp','onException'));
/*public function errorHandlerCallback($code, $string, $file, $line, $context) 
{
	$e = new Excpetion($string, $code);
	$e->line = $line;
	$e->file = $file;
	throw $e;
}
*/
/**
 * Capture last error to report errors that are not normally trapped by error-
 * handling functions, e.g., fatal and parsing errors.
 */
function cc_shutdown()
{
    $err=error_get_last();
	switch ($err['type'])
	{
		case E_WARNING:
		case E_NOTICE:
		case E_USER_ERROR:
		case E_USER_WARNING:
		case E_USER_NOTICE:
			return FALSE;
		break;
		
		case E_COMPILE_ERROR:
		case E_PARSE:
		default:
			return ccApp::onError($err['type'], $err['message'], $err['file'], $err['line'], $GLOBALS);
	}
//	trigger_error($err['message'],$err['type']);
}
register_shutdown_function('cc_shutdown');


// We are using spl_autoload_* features to simplify search for class files. If
// the app has defined an __autoload() of their own without chaining it with
// the spl_autoload_register() call, then this will add it automatically.
if (function_exists('__autoload')) 
{
	spl_autoload_register('__autoload', true, true); 
}
spl_autoload_register(array('ccApp','_autoload'), true);

//ccApp::setFrameworkPath(dirname(__FILE__));	// Function probably not needed
ccApp::$_fwpath = dirname(__FILE__).DIRECTORY_SEPARATOR;

/**
 * Framework class reprsenting the "application". You can derive this class, but
 * you are not allowed to instantiate it directly, use ccApp::createApp().
 *
 * @todo Consider consolidating createApp() with getApp()
 * @todo Support instance specific error and exception handlers
 */
class ccApp
{
	const MODE_DEVELOPMENT	= 1;	// Obsolete?
	const MODE_TESTING		= 2;	// Obsolete?
	const MODE_STAGING		= 4;	// Obsolete?
	const MODE_PRODUCTION	= 8;	// Obsolete?

	protected static $_me=NULL;			// Singleton ref to $this
	/*protected*/ static $_fwpath=NULL;		// Path to framework files.

	protected $config=Array();			// Configuration array

	protected $UrlOffset=NULL;			// Path from domain root for the site
	protected $devMode = self::MODE_DEVELOPMENT; 	// Obsolete
	protected $bDebug = FALSE; 			// Central place to hold debug status
	
	protected $sitepath=NULL;			// Path to site specific files.
	protected $temppath='working';		// Path to working directory (for cache, etc)
	
	protected $page=NULL; 				// Main page object for app
	protected $error404 = NULL;			// External ccPageInterface to render errors.
										// The following are rel to sitepath:
	protected $classpath=array();		// List of site paths to search for classes
//	protected $wwwpath='public';		// Path to web visible files
		
	protected $current_request; 		// Remember current request

	protected function __construct()	// As a singleton: block public allocation
	{
	} // __construct()

	/**
	 * Search for class definition from framework folder. If there is an
	 * instance of the app, call its autoload first where site specific searches
	 * will take precedence. 
	 *
	 * This method is appropriate to call this from __autoload() or 
	 * register via spl_autoload_register()
	 */
	public static function _autoload($className)
	{
		$classFilename = str_replace('_', DIRECTORY_SEPARATOR, $className).'.php';
											// Check instance specific autoload
		if (self::$_me && method_exists(self::$_me,'autoload'))
		{
			self::$_me->autoload($className); // Using spl_autoload_register()?
		}
		if (!class_exists($className))		// Check framework directories
		{
			if (file_exists(self::$_fwpath . 'core' . DIRECTORY_SEPARATOR .$classFilename)) 
			{
				include(self::$_fwpath . 'core' . DIRECTORY_SEPARATOR .$classFilename);
			}
			elseif (file_exists(self::$_fwpath . $classFilename)) 
			{
				include(self::$_fwpath . $classFilename);
			}
		}
	} // _autoload()

	/**
	 * Add a relative path to the list of site-secific paths to search when 
	 * loading site-specific classes. 
	 * @param string $path Is the path to be included in the search
	 *        or, if $classname is specified, then the full fliepath. If the first
	 *        char is not '/' (or '\', as appropriate) then the site dir is
	 *        assumed.
	 * @param string $classname is an optional class name that, when sought, will
	 *        load the specified file specified by $path. 
	 * @example
	 *    define('DS',DIRECTORY_SEPARATOR);
	 *	  $app->addClassPath('classes')		// Search app's directory
	 *	      ->addClassPath('..'.DS.'smarty'.DS.'Smarty.php','Smarty')
	 *	  	  ->addClassPath('..'.DS.'RedBeanPHP'.DS.'rb.php','R')
	 *	  	  ->addClassPath('..'.DS.'Facebook'.DS.'facebook.php','Facebook');
	 * @todo Allow array of directories to be passed in.
	 * @todo Test for path's existence.
	 */
	function addClassPath($path,$classname=NULL)
	{
		if ($classname)
		{
			if ($path[0] != DIRECTORY_SEPARATOR)
				$this->classpath[$classname] = $this->sitepath.$path;
			else
				$this->classpath[$classname] = $path;
		}
		else
		{
			if (substr($path,-1) != DIRECTORY_SEPARATOR)
				$path .= DIRECTORY_SEPARATOR;
			if ($path[0] != DIRECTORY_SEPARATOR)
				$this->classpath[] = $this->sitepath.$path;
			else
				$this->classpath[] = $path;
		}
		return $this;
	} // addClassPath()

	/**
	 * Convenience method to prefix or suffix the PHP search path.
	 * @param string $path Path component to add to search path.
	 * @param bool $prefix Prefix the search path with the new path. Default:
	 *        false, append new path to the end of the search path.
	 * @see set_include_path()
	 * @todo If not absolute path, prefix with site path.
	 */
	public function addPhpPath($path,$prefix=FALSE)
	{
		if ($prefix)
			set_include_path($path . PATH_SEPARATOR . get_include_path());
		else
			set_include_path(get_include_path() . PATH_SEPARATOR . $path);
		return $this;
	} // addPhpPath()

	/**
	 * Instance specific autoload() searches site specific paths.
	 */
	public function autoload($className)
	{

		$classFilename = str_replace('_', DIRECTORY_SEPARATOR, $className).'.php';
// global $bb,$eb, $bi,$ei, $btt,$ett, $rarr,$ldquo,$rdquo,$hellip,$nbsp,$nl;
// ccTrace::s_out( '#'.__LINE__.' '.ccTrace::getCaller(0,dirname(ccApp::getApp()->getSitePath())).': '.$className." $rarr ".$classFilename.$nl);
// ccTrace::s_out( '#'.__LINE__.' '.ccTrace::getCaller(3).': '.$className." $rarr ".$classFilename.$nl);
// echo '#'.__LINE__.' '.$className." $rarr ".$classFilename.$nl;
// self::tr('&nbsp;&nbsp;&nbsp;'.$this->sitepath.$classFilename);
// ccTrace::tr('&nbsp;&nbsp;&nbsp;'.$this->sitepath.$classFilename);

		// Check app paths, first
		if ($this->sitepath && file_exists($this->sitepath . $classFilename)) 
		{
			include($this->sitepath . $classFilename);
			return;
		}
		else foreach ($this->classpath as $class => $path)
		{
// ccTrace::s_out( $nbsp.$nbsp.'*'.$class.'*'.$className.'*'.(($class === $className)).'*'.$path.$nl);
// self::tr( '*'.$class.'*'.$className.'*'.(($class === $className)).'*'.$path);
			if ($class === $className)	// If specific class mapping
			{							// then load associated file
//				if (!file_exists($path))
//					throw new Exception($path . " does not exist in ".getcwd());
				if (require($path))
					return;
			}
			elseif (file_exists($path . $classFilename)) 
//			elseif (@include($path . $classFilename)) 
			{							// Else if assumed name exists...
				include($path . $classFilename);
				return;
			}
		}
// echo '#'.__LINE__.' '.$className.$rarr.$classFilename.$nl;
//		@include($classFilename);		// Finally, check include path.
// if (class_exists($className))		// Check framework directories
// echo '#'.__LINE__.$nbsp.$nbsp.$className.$rarr.$classFilename.' LOADED!!!'.$nl;
		// IF WE GET HERE, WE COULD NOT RESOLVE THE CLASS
	} // autoload()

	/**
	 * Create singleton instance of the app. 
	 * 
	 * @param string $className By default instance of ccApp is created, but 
	 *                          the name of a derived class can be instantiated.
	 * @todo Consider consolidating into getApp()
	 * @todo Consider allowing instance of ccApp to be passed. 
	 * @todo Consider blocking this from creating a 2nd instance.
	 */
	public static function createApp($className=NULL)
	{
		return self::$_me = ($className ? new $className : new self);
	} // createApp()
		
	/**
	 * Create site-specific directory, if it doesn't exist. 
	 *
	 * @param string $dir Directory name (relative to site-path), if not an 
	 *		  absolute path.
	 *
	 * @returns string Semi-normalized path name (suffixed with '/' prefixed w/
	 *			site-path.
	 * @todo Accept array of directory names (to move common code here)
	 */
	public function createSiteDir($dir)
	{
		if ( substr($dir, -1) != DIRECTORY_SEPARATOR )	// Ensure suffixed w/'/'
			$dir .= DIRECTORY_SEPARATOR;
		if ( $dir[0] != DIRECTORY_SEPARATOR )			// Not absolute path?
			$dir = $this->sitepath . $dir;				// Prefix with site's path
		if (!is_dir($dir))								// Path does not exist?
			mkdir($dir,0777,TRUE);						// Create path
		return $dir;									// Return modified path
	} // createSiteDir()

	/**
	 * This method is called to render pages for the web site. It invokes the 
	 * "main page" (which is usually a dispatcher or controller) to render 
	 * content. If no content is rendered (i.e., the page's render() method
	 * returns FALSE, then 404 handling is invoked. 
	 */
	public function dispatch(ccRequest $request)
	{
		try
		{
			$this->current_request = &$request;
			if (!$this->page->render($request))
			{
				throw new ccHttpStatusException(404);
			}
		}
		catch (ccHttpStatusException $e)
		{
			switch ($e->getCode())
			{
				case 300: case 301: case 302: case 303: 
				case 304: case 305: case 306: case 307: 
					$this->redirect($e->getLocation(), $e->getCode(), $e->getMessage());
					break;
				case 404: $this->show404($request);
					break;
				default:				// No other stati supported right now.
//					http_response_code($e->getCode());
					if (!headers_sent())
						header($_SERVER['SERVER_PROTOCOL'].' '.$e->getCode().' '.$e->getMessage(), TRUE, $e->getCode());
					throw $e;
			}
		}
	} // dispatch()

	/**
	 * A place to get/set settings that should be accessible across the application
	 * @param string $name Name of value to return.
	 *
	 * @see set()
	 * @todo Option to automatically save in session, dependent on dev mode.
	 */
	public function get($name, $default=NULL)
	{
		if (!isset($this->config[$name]))	// If not set,
			$this->set($name, $default);	//   set to default
		return $this->config[$name];		// and return value.
	} // get()
	/**
	 * A place to get/set settings that should be accessible across the application
	 * @param string $name Name of value to set (names starting with an underscore
	 *                     are reserved for "internal use" and should be avoided).
	 * @param mixed $value Value associated with $name.
	 *
	 * @see get()
	 * @todo Option to automatically save in session, dependent on dev mode.
	 */
	public function set($name, $value)
	{
		$this->config[$name] = $value;
		return $this;
	} // set()

	/**
	 * Handle 404 (page not found errors).
	 * @param ccPageInterface|string $error404page The object or classname that
	 *        would render a 404 page.
	 * @see show404() on404()
	 * @todo Add support for string name of class. 
	 */
	function set404Page(ccPageInterface $error404page)
	{
		$this->error404 = $error404page;
		return $this;
	} // set404handler()

	/**
	 * @return object App singleton instance
	 */
	public static function getApp()
	{
		return self::$_me;
	} // getApp()

	/**
	 * Default cookie setting to URL Offset. If the path is not specified or 
	 * not an absolute path, then the base is assumed to be the URL offset to
	 * the site.
	 *
	 * @see getUrlOffset()
	 */
	function setCookie(
		$name, 
		$value=NULL, 
		$expire = 0, 
		$path=NULL, 
		$domain=NULL, 
		$secure=false, 
		$httponly=false )
	{
		if ($path === NULL)
			$path = $this->getUrlOffset();
		elseif ($path[0] != '/')
			$path = $this->getUrlOffset() . $path;
		if ($expire === 0 && ($value === NULL || $value == ''))
			$expire = time()-3600;		// Delete cookie
		setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
	} // setCookie()

	/**
	 * Set debug setting
	 */
	public function getDebug()
	{
		return $this->bDebug;
	}
	public function setDebug($bDebug=TRUE)
	{
		$this->bDebug = $bDebug;
		return $this;
	}
	
	/**
	 * @obsolete
	 * Get/set app's disposition mask.
	 */
	public function getDevMode()
	{
		return $this->devMode;
	}
	public function setDevMode($mode)
	{
		$this->devMode = $mode;
		return $this;
	}

	/**
	 * Set the error handler; a convenience method for stylistic consistency.
	 * @param callback $function The name of the callback function or array,
	 *        when the callback is a class or object method.
	 * The callback function should look like: 
	 *   handler ( int $errno , string $errstr [, 
	 *             string $errfile [, int $errline [, array $errcontext ]]] )
	 * @see http://www.php.net/manual/en/function.set-error-handler.php
	 * @todo Probably don't need this if errors are chained to exceptions.
	 */
	function setErrorHandler($function)
	{
		set_error_handler($function);
		return $this;
	} // setErrorHandler()

	/**
	 * Set the exception handler; a convenience method for stylistic consistency.
	 * @param callback $function The name of the callback function or array,
	 *        when the callback is a class or object method.
	 * The callback function should look like: 
	 *   handler ( Exception $e )
	 * @see http://www.php.net/manual/en/function.set-error-handler.php
	 */
	function setExceptionHandler($function)
	{
		set_error_handler($function);
		return $this;
	} // setErrorHandler()

	/**
	 * Set server path to site's files (not the URL)
	 * @param string $path Full, absolute path (e.g., dirname(__FILE__) of
	 * ccApp.php)
	 */
	public function getFrameworkPath()
	{
		return self::$_fwpath;
	} // getFrameworkPath()
	/**
	 * @todo This method is probably not necessary
	 */
	private static function setFrameworkPath($path)
	{
		if (substr($path,-1) != DIRECTORY_SEPARATOR)
			$path .= DIRECTORY_SEPARATOR;

		self::$_fwpath = $path;
	} // setFrameworkPath()

	/**
	 * @return object app's main page (e.g., dispatcher or controller)
	 */
	public function getMainPage()
	{
		return $this->page;
	} // getMainPage()
	public function setMainPage(ccPageInterface $page)
	{
		$this->page = $page;
		return $this;
	} // setMainPage()

	/**
	 * Current request (set via dispatch())
	 */
	function getRequest()
	{
		return $this->current_request;
	} // getRequest()
		
	/**
	 * @see getUrlOffset()
	 */
	function getRootUrl()
	{
		$path = isset($_SERVER['REDIRECT_SCRIPT_URI']) 
			? $_SERVER['REDIRECT_SCRIPT_URI']
			: $_SERVER['SCRIPT_URI'];
			
		$p = strpos($path,'//');
		if ($p === FALSE)			// Don't know what to do here... bad input.
		{
		}
		else
		{
			$p = strpos($path,'/',$p+2);
			if ($p === FALSE)
				$path .= '/';
			else
				$path = substr($path,0,$p+1);
		}
		
		return $path . $this->getUrlOffset();
	} // getRootUrl()

	/**
	 * Get path to site's files (not the URL)
	 * @param string $path Full, absolute path (e.g., dirname(__FILE__) 
	 *        of caller)
	 */
	public function getSitePath()
	{
		return $this->sitepath;
	} // getSitePath()
	/**
	 * Get/set server path to site's files (not the URL). This method also sets
	 * this path as the current directory so that all subsequent relative
	 * paths are from a normalized location. 
	 * @param string $path Full, absolute path (e.g., dirname(__FILE__) 
	 *        of caller)
	 */
	public function setSitePath($path)
	{
		if (substr($path,-1) != DIRECTORY_SEPARATOR)	// Ensure path-spec
			$path .= DIRECTORY_SEPARATOR;				// suffixed w/'/'

		$this->sitepath = $path;						// Save path
		chdir($path);									// Set cd to "known" place
		return $this;
	} // setSitePath()

	/**
	 * The URL offset is the part of the URL that spans from the server name
	 * (and port, if any) to the app's "root"; usually "/" or "/index.php/".
	 *
	 * @see getRootUrl()
	 * @todo Consider moving to ccRequest?
	 */
	public function getUrlOffset()
	{
		if (!$this->UrlOffset)				// If not set, 
			$this->setUrlOffset(); 			//    Ensure init'd 
		return $this->UrlOffset;
	} // setUrlOffset()
	/**
	 * @todo This value is inferred from env settings, so shouldn't be public. 
	 */
	private function setUrlOffset($component=NULL)
	{
		if (!$component)
		{
			$component = dirname($_SERVER['SCRIPT_NAME']);
			if ($component != '/') 
				$component .= '/';
		}
		$this->UrlOffset = $component;
		return $this;
	} // setUrlOffset()
		
	/**
	 * Set the application's working directory 
	 * @param string $path Directory name, relative to site path (unless an 
	 *        absolute path-spec).
	 * @see setSitePath();
	 */
	public function setWorkingDir($path)
	{
		$this->temppath = $this->createSiteDir($path);
		return $this;
	} // setWorkingDir()
	public function getWorkingPath()
	{
		if ($this->temppath[0] != DIRECTORY_SEPARATOR)	// setWorkingDir() not 
			$this->setWorkingDir($this->temppath);		// called yet.
		return $this->temppath;
	} // setWorkingDir()

	/**
	 * Default 404 handler.
	 * @param ccRequest $request The current request
	 */
	protected function on404(ccRequest $request)
	{
//		http_response_code(404);
		if (!headers_sent())
		{
			header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found', TRUE, 404);
			header('Content-type: text/html');
		}
		?>
		<html><body><hr/>
		<?php print $_SERVER['SCRIPT_URI'] ?>
		<h1>404 Not Found</h1>
		This is not the page you are looking for.<hr/>
		<?php 
		if ($this->getDevMode() & self::MODE_DEVELOPMENT)
		{
			$trace = debug_backtrace();		// Get whole stack list
			array_shift($trace);			// Ignore this function
			ccTrace::showTrace($trace);		// Display stack.
		}
		?>
		</body></html>
		<?php
//		exit();
	} // on404()

	/**
	 * @todo Consider throwing exception (caveat, flow of control does not continue)
	 * @todo Add distinction between dev and production modes of output.
	 * @todo Consider moving to separate Trace class
	 */
	static function onError($errno, $errstr, $errfile, $errline, $errcontext)
	{
		if (ini_get('error_reporting') & $errno)
		{
			$errortype = Array(
//				E_ERROR			=> 'Error',
				E_PARSE			=> 'Parsing Error', // 4
//				E_CORE_ERROR	=> 'Core Error',
//				E_CORE_WARNING	=> 'Core Warning',
				E_COMPILE_ERROR	=> 'Compile Error',	// 64
//				E_COMPILE_WARNING => 'Compile Warning',
				E_WARNING		=> 'Warning',
				E_NOTICE		=> 'Notice',
				E_USER_ERROR	=> 'User Error',
				E_USER_WARNING	=> 'User Warning',
				E_USER_NOTICE	=> 'User Notice',
				E_STRICT		=> 'Strict'
			);
			if (PHP_VERSION_ID >= 50200)
				$errortype[E_RECOVERABLE_ERROR] = 'Recoverable Error';
			if (PHP_VERSION_ID >= 50300)
			{
//				$errortype[E_DEPRECATED] = 'Deprecated';
				$errortype[E_USER_DEPRECATED] = 'User Deprecated';
			}			
			if (!isset($errortype[$errno]))
				$errortype[$errno] = "Error($errno)";

			global $bred,$ered,$bb,$eb, $bi,$ei, $btt,$ett, $rarr,$ldquo,$rdquo,$hellip,$nbsp,$nl;
			error_log("$errortype[$errno]: $errstr in $errfile#$errline",0);
			$msg = "$bb$bred$errortype[$errno]$ered: $errstr$eb$nl"
				 // . "        in $errfile#$errline"; 
				 . "        in ".ccTrace::fmtPath($errfile,$errline); 
			print $msg.$nl;
//			self::log($msg);
			$trace = debug_backtrace();		// Get whole stack list
			array_shift($trace);			// Ignore this function
			ccTrace::showTrace($trace);		// Display stack.
			return TRUE;
		}
		else
			return FALSE; 	// chain to normal error handler
	} // onError()

	/**
	 * @todo Add distinction between dev and production modes of output.
	 * @todo See php.net on tips for proper handling of this handler.
	 * @todo Consider moving to separate Trace class
	 */
	static function onException($exception)
	{
		try
		{
			global $bred,$ered,$bb,$eb, $bi,$ei, $btt,$ett, $rarr,$ldquo,$rdquo,$hellip,$nbsp,$nl;
			$msg = $bb.get_class($exception).'('.$exception->getCode().'):'.$eb.' "'.$exception->getMessage().'" in '.ccTrace::fmtPath($exception->getFile()).'#'.$exception->getLine();
			print $msg.$nl;
			self::log($msg);
			ccTrace::showTrace($exception->getTrace());
//			die();
		}
		catch (Exception $e)
		{
			self::log(get_class($e)." thrown within the exception handler. Message: ".$e->getMessage()." on line ".$e->getLine());
//			die();
		}
	} // onException()

	/**
	 * @param string $url Send rediret to browser
	 * @todo Forward qstring, post  variables, and cookies. 
	 * @todo Allow "internal" redirect that does not return to the client.
	 */
	function redirect($url,$status = 302,$message='Redirect')
	{
		if (!headers_sent())
		{
			header($_SERVER['SERVER_PROTOCOL'].' '.$status.' '.$message, TRUE, $status);
			header('Location: '.$url);
			echo "Redirecting to {$url} via header&hellip;";
		}
		else
		{ 
			echo <<<EOD
			Redirecting to {$url} via scripting&hellip;
			<script>window.top.location.href="$url"</script>
EOD;
		}
		exit();
	} // redirect()

	/**
	 * For whatever reason, display 404 page response.
	 */
	protected function show404(ccRequest $request)
	{
		if ($this->error404)	// If 404 page defined
		{
			if (is_string($this->error404))
				$this->error404 = new $this->error404;
			if (($this->getDevMode() & self::MODE_DEVELOPMENT) 
				&& !($this->error404 instanceof ccPageInterface))
			{
				trigger_error(get_class($this->error404).' does not implement ccPageInterface', E_WARNING);
			}
			call_user_func(array($this->error404,'render'), $request);
		}
		else 						// No app specific page
			$this->on404($request);	// Perform local 404 rendering
	} // show404()

	
	// static function out($string)
	// {
		// if (!(ccApp::$_me->devMode & ccApp::MODE_DEVELOPMENT))
			// return;
//		//error_log($string,3,'/home/wrlee/htd.log');
		// echo $string;
	// }
	
	/**
	 * options: HTML, log, stderr, stdout, formatted, timestamp
	 */
	static function log()
	{
		return call_user_func_array(array('ccTrace','log'),func_get_args());
	} // tr()
	/**
	 * options: HTML, log, stderr, stdout, formatted, timestamp
	 */
	static function tr()
	{
		return call_user_func_array(array('ccTrace','tr'),func_get_args());
	} // tr()
} // class ccApp

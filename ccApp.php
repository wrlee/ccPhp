<?php
/** 
 * File: ccApp.php
 * 
 * The ccApp class represents the application. It is a singleton.
 *
 * @todo Look into using AutoLoad package (by the Doctrine and Symfony folks)
 * @todo Add session handling?
 * @todo Need a way to set "debug" setting that will cascade thru components.
 * @todo Move error handling ccError class and refer through ccApp
 * @todo Add on init-log-file(), to output info only when created.
 * @todo Add setLogFile(), setAppDir(js)
 * @todo Consider a separate "site" object (to allow site specific configuration),
 *       currently part of the "app" object.
 * 
 * @todo Consider moving most of this code to index.php? No. Keep most of this 
 *       out of public/hacking view
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
 *  @todo MODE_PRODUCITON should prevent revealing errors (hide path info)
 */
/*
 * @See http://php.net/manual/en/reserved.variables.php#Hcom55068
 * 2010-10-22 404 uses exceptions
 *          - Extend classpath interpretation to enter specific class specs.
 * 2013-08-28 Renamed get/setMainPage() get/setPage()
 *          - Removed setFrameworkPath()
 *          - setFrameworkPath() is now static
 *          - Fix getRootUrl()'s duplicate '/'
 *          - Redefined operational mode settings (ccApp::MODE_*)
 * 2013-09-02 Renamed get/setSitePath() to get/setAppPath()
 * 			- Renamed createSiteDir() to createAppDir() and protected it.
 * 			- Added createWorkingDir()
 * 2013-09-12 Remove getPage()
 */	
//******************************************************************************\
namespace {
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
} // namespace
namespace //! ccPhp 
{
// include 'ccPhp.inc';
//!use ccPhp\core\ccTrace;
//!use ccPhp\core\ccRequest;
//!use ccPhp\core\ccPageInterface;

//******************************************************************************

/**
 * Framework class representing the "application". You can derive this class, but
 * you are not allowed to instantiate it directly, use ccApp::createApp().
 *
 * @package ccPhp
 * @todo Consider consolidating createApp() with getApp()
 * @todo Support instance specific error and exception handlers
 * @todo Consider that flags can be user defined, with some pre-defined meanings.
 */
class ccApp
	implements \Serializable
{
	const MODE_DEBUG		= 1;	//* Debugging output
	const MODE_INFO			= 6;	//* PHP info msgs
	const MODE_WARN			= 2;	//* PHP warnings
	const MODE_ERR			= 4;	//* PHP errors
	const MODE_TRACEBACK	= 8;	//* Show tracebacks
	const MODE_REVEAL		= 16;	//* Reveal paths
	const MODE_MINIMIZE		= 32;	//* Use minimized resources (scripts, CSS, etc.)
	const MODE_PROFILE		= 64;	//* Enable profile
	const MODE_CACHE		= 128;	//* Enable caching where it can
	
	protected static $_me=NULL;			// Singleton ref to $this

//	protected $config=Array();			// Configuration array

	protected $UrlOffset=NULL;			// Path from domain root for the site
	protected $devMode = CCAPP_DEVELOPMENT;
//	protected $bDebug = FALSE; 			// Central place to hold debug status
	
	protected $sitepath=NULL;			// Path to site specific files.
	protected $temppath='';				// Path to working directory (for cache, etc)
	
	protected $page=NULL; 				// Main page object for app
	protected $error404 = NULL;			// External ccPageInterface to render errors.
										// The following are rel to sitepath:
	protected $classpath=array();		// List of site paths to search for classes
//	protected $classLoader=NULL;		// SplClassLoader reference
		
	protected $current_request; 		// Remember current request

	/**
	 * Save $_me as a singularity and (hack). 
	 */
	protected function __construct()	// As a singleton: block public allocation
	{
		self::$_me=$this;
		$callstack = (PHP_VERSION_ID < 50400) ? debug_backtrace(0) : debug_backtrace(0,2);

		// Hack: look up the call-stack to pull 1st arg from createApp()
		foreach ($callstack as $caller)	
			if ($caller['function'] == 'createApp') 
			{
				$this->sitepath = $caller['args'][0];
				if (substr($this->sitepath, -1) != DIRECTORY_SEPARATOR)
					$this->sitepath .= DIRECTORY_SEPARATOR;
				break;
			}
	} // __construct()

	/**
	 * Search for class definition from framework folders. 
	 * If there is an instance of the app, call its autoload first where 
	 * site specific searches will take precedence. 
	 *
	 * This method is appropriate to call this from __autoload() or 
	 * register via spl_autoload_register()
	 */
	public static function _autoload($className)
	{
// echo __METHOD__.'#'.__LINE__."($className) ".__NAMESPACE__.' '.__CLASS__. " <br>";
		if (self::$_me && method_exists(self::$_me,'autoload'))
		{
			self::$_me->autoload($className); // Using spl_autoload_register()?
		}
// if (strpos($className, 'ccTrace') > 0) {
// echo '<pre>';
// var_dump(debug_backtrace());
// echo '</pre>';
// }
											// Check instance specific autoload
		if (!class_exists($className))		// Check framework directories
		{
			if (__NAMESPACE__ != '' && __NAMESPACE__ == substr($className, 0, strlen(__NAMESPACE__)))
			{
				$className = explode('\\', $className);
//				// If __NAMESPACE__ in effect and namespace is not part of name 
//				// or no NS specified, return (not request a ccFramework class)
//				if ( $className[0] != __NAMESPACE__ || count($className) < 2)
//					return;
				$className = end($className);	// Use only classname of this f/w
			}
//			$classFilename = str_replace('_', DIRECTORY_SEPARATOR, $className).'.php';
			$classFilename = $className.'.php';
// echo __METHOD__.'#'.__LINE__."($className) ".self::getFrameworkPath() . 'core' . DIRECTORY_SEPARATOR .$classFilename. " <br>";
			if (file_exists(self::getFrameworkPath() . 'core' . DIRECTORY_SEPARATOR .$classFilename)) 
				include(self::getFrameworkPath() . 'core' . DIRECTORY_SEPARATOR .$classFilename);
			elseif (file_exists(self::getFrameworkPath() . $classFilename)) 
				include(self::getFrameworkPath() . $classFilename);
		}
	} // _autoload()

	/**
	 * Add a path to the list of site-specific paths to search when 
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
// ccTrace::s_out( '#'.__LINE__.' '.ccTrace::getCaller(0,dirname(self::getApp()->getAppPath())).': '.$className." $rarr ".$classFilename.$nl);
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
	 * @param string $appPath   Absolute path to the app's code.
	 * @param string $className By default instance of ccApp is created, but 
	 *                          the name of a derived class can be instantiated.
	 * @return ccApp  App object.
	 * @todo Consider consolidating into getApp()
	 * @todo Consider allowing instance of ccApp to be passed.
	 * @todo Consider blocking this from creating a 2nd instance.
	 * @todo Load cached version, if available.
	 * @todo Allow parameters passe to constructor.
	 */
	public static function createApp($appPath, $className=NULL)
	{
//		$sessActive = (session_status() == PHP_SESSION_ACTIVE);
//	    if (!$sessActive)					// If session support not running
//	    	session_start();				//   turn on to presist browser info
//
//	    if ( isset($_SESSION['ccApp']) ) 	// If already cached, return info
//	    {
//	    	self::$_me = unserialize($_SESSION['ccApp']);
//		    if ( !$sessActive )				// Session wasn't running
//		    	session_commit();			//   So turn back off
//	    	return self::$_me;				// Return serialized object
//	    }
//
		if (substr($appPath,-1) != DIRECTORY_SEPARATOR)	// Ensure path-spec
			$appPath .= DIRECTORY_SEPARATOR;			// suffixed w/'/'

		chdir($appPath);								// Set cd to "known" place
		$className ? new $className : new self;

//		$_SESSION['ccApp'] = serialize(self::$_me);
//	    if ( !$sessActive )					// Session wasn't running
//	    	session_commit();				//   So turn back off

		return self::$_me;
	} // createApp()
		
	/**
	 * Create site-specific directory, if it doesn't exist. 
	 *
	 * @param string $dir Directory name (relative to site-path), if not an 
	 *		  absolute path.
	 *
	 * @return string Semi-normalized path name (suffixed with '/' prefixed w/
	 *			site-path.
	 * @todo Accept array of directory names (to move common code here)
	 */
	public function createAppDir($dir)
	{
		if ( substr($dir, -1) != DIRECTORY_SEPARATOR )	// Ensure suffixed w/'/'
			$dir .= DIRECTORY_SEPARATOR;
		if ( $dir[0] != DIRECTORY_SEPARATOR )			// Not absolute path?
			$dir = $this->sitepath . $dir;				// Prefix with site's path
		if (!is_dir($dir))								// Path does not exist?
			mkdir($dir,0744,TRUE);						// Create path
		return $dir;									// Return modified path
	} // createAppDir()

	/**
	 * Create a directory under the app's working directory.
	 * @param  string $dir The name of a directory to be created under the working dir
	 * @return string The path to the directory created. 
	 */
	public function createWorkingDir($dir)
	{
		if ( substr($dir, -1) != DIRECTORY_SEPARATOR )	// Ensure suffixed w/'/'
			$dir .= DIRECTORY_SEPARATOR;
		if ( $dir[0] != DIRECTORY_SEPARATOR )			// Not absolute path?
			$dir = $this->sitepath.$this->temppath.$dir;// Prefix with site's working path
		if (!is_dir($dir))								// Path does not exist?
			mkdir($dir,0744,TRUE);						// Create path
		return $dir;									// Return modified path
	} // createWorkingDir()

	/**
	 * This method is called to render pages for the web site. It invokes the 
	 * "main page" (which is usually a dispatcher or controller) to render 
	 * content. If render() returns false, implies no content is rendered then
	 * 404, Page Not Found. handling is invoked. 
	 * @throws ccHttpStatusException If false, since this is the end of the line.
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
				case 305: case 306: case 307: 
					header($_SERVER['SERVER_PROTOCOL'].' '.$e->getCode().' '.$e->getMessage(), TRUE, $e->getCode());
					$this->redirect($e->getLocation(), $e->getCode(), $e->getMessage());
					break;
				case 304: 
					header($_SERVER['SERVER_PROTOCOL'].' '.$e->getCode().' '.$e->getMessage(), TRUE, $e->getCode());
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

//	/**
//	 * A place to get/set settings that should be accessible across the application
//	 * @param string $name Name of value to return.
//	 *
//	 * @see set()
//	 * @todo Option to automatically save in session, dependent on dev mode.
//	 */
//	public function get($name, $default=NULL)
//	{
//		if (!isset($this->config[$name]))	// If not set,
//			$this->set($name, $default);	//   set to default
//		return $this->config[$name];		// and return value.
//	} // get()
//	/**
//	 * A place to get/set settings that should be accessible across the application
//	 * @param string $name Name of value to set (names starting with an underscore
//	 *                     are reserved for "internal use" and should be avoided).
//	 * @param mixed $value Value associated with $name.
//	 *
//	 * @see get()
//	 * @todo Option to automatically save in session, dependent on dev mode.
//	 */
//	public function set($name, $value)
//	{
//		$this->config[$name] = $value;
//		return $this;
//	} // set()

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
	 * Get current app object singleton.
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

//	/**
//	 * Set debug setting
//	 */
//	public function getDebug()
//	{
//		return $this->bDebug;
//	}
//	public function setDebug($bDebug=TRUE)
//	{
//		$this->bDebug = $bDebug;
//		return $this;
//	}
	
	/**
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
	public static function getFrameworkPath()
	{
		return dirname(__FILE__).DIRECTORY_SEPARATOR;
	} // getFrameworkPath()

//	/**
//	 * @return object app's main page (e.g., dispatcher or controller)
//	 */
//	public function getPage()
//	{
//		return $this->page;
//	} // getPage()
	/**
	 * Set the primary page handler for the app. This is usually a controller
	 * that dispatches to other handlers (internally or externally; i.e., other
	 * ccPageInterface objects).
	 * @param ccPageInterface $page [description]
	 */
	public function setPage(ccPageInterface $page)
	{
		$this->page = $page;
		return $this;
	} // setPage()

	/**
	 * Current request (set via dispatch())
	 */
	function getRequest()
	{
		return $this->current_request;
	} // getRequest()
		
	/**
	 * Get the part of the URL which points to the root of this app, i.e., the 
	 * start of where this app resides. 
	 * @return string The URI
	 * @see getUrlOffset()
	 * @todo Handle case where URL does not have a scheme
	 * @todo Move to ccRequest
	 */
	function getRootUrl()
	{
		$path = isset($_SERVER['REDIRECT_SCRIPT_URI']) 
			? $_SERVER['REDIRECT_SCRIPT_URI']
			: $_SERVER['SCRIPT_URI'];
			
		$p = strpos($path,'//');	// Offset past the protocol scheme spec
		if ($p === FALSE)			// No protocol scheme.
		{							// Don't know what to do here... bad input.
		}
		else 								// Set $path to the part past the domain:port part
		{									// suffixed with a '/'
			$p = strpos($path,'/',$p+2);	// First path separator past the scheme
			if ($p === FALSE)				// No '/': this app is at the root.
				$path .= '/';				// Ensure it ends with a '/'
			else 
				$path = substr($path,0,$p+1);	// Ignore path after the domain portion.
		}
		
		return $path . substr($this->getUrlOffset(),1);
	} // getRootUrl()

	/**
	 * Get the server path to site's files (not the URL)
	 * @param string $path Full, absolute path (e.g., dirname(__FILE__) 
	 *        of caller)
	 */
	public function getAppPath()
	{
		return $this->sitepath;
	} // getAppPath()
//	/**
//	 * Get/set server path to site's files (not the URL). This method also sets
//	 * this path as the current directory so that all subsequent relative
//	 * paths are from a normalized location. 
//	 * @param string $path Full, absolute path (e.g., dirname(__FILE__) 
//	 *        of caller)
//	 */
//	public function setAppPath($path)
//	{
//		if (substr($path,-1) != DIRECTORY_SEPARATOR)	// Ensure path-spec
//			$path .= DIRECTORY_SEPARATOR;				// suffixed w/'/'
//
//		$this->sitepath = $path;						// Save path
//		chdir($path);									// Set cd to "known" place
//		return $this;
//	} // setAppPath()

	/**
	 * The path part of the URL starting from '/' up to the path where this 
	 * app's "root" starts; e.g., "/" or "/index.php/".
	 *
	 * @see getRootUrl()
	 * @todo Consider moving to ccRequest?
	 */
	public function getUrlOffset()
	{
		if (!$this->UrlOffset)			// If not set, 
			$this->initUrlOffset(); 	//    Ensure init'd 
// echo __METHOD__.'#'.__LINE__.' "'.$this->UrlOffset.'"<br/>';
		return $this->UrlOffset;
	} // getUrlOffset()
	/**
	 * This returns the part of the url that is the "root" of this app. 
	 * This value is inferred from env settings, so shouldn't be public.
	 */
	private function initUrlOffset()
	{
		$UrlOffset = dirname($_SERVER['SCRIPT_NAME']);
		if ($UrlOffset != '/') 
			$UrlOffset .= '/';
		$this->UrlOffset = $UrlOffset;
	} // initUrlOffset()
		
	/**
	 * Set the application's working directory relative to the app's code. It 
	 * is created, if necessary.
	 * @param string $dir Directory name, relative to app's path (unless an 
	 *        absolute path-spec).
	 * @see createAppDir();
	 */
	public function setWorkingDir($dir)
	{
		if ( $dir != '' && substr($dir, -1) != DIRECTORY_SEPARATOR )	// Ensure suffixed w/'/'
			$dir .= DIRECTORY_SEPARATOR;
		$this->createAppDir($dir);
		$this->temppath = $dir;
		return $this;
	} // setWorkingDir()

	/**
	 * Get the full path to the working directory.
	 * @return [type] [description]
	 */
	public function getWorkingPath()
	{
		return ($this->temppath[0] == DIRECTORY_SEPARATOR) 
				? $this->temppath 
				: $this->sitepath.$this->temppath;
	}

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
		<?php print $request->getUrl() ?>
		<h1>404 Not Found</h1>
		This is not the page you are looking for.<hr/>
		<?php 
		if ($this->getDevMode() & self::MODE_TRACEBACK)
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
	 * Php error handler directs output to destinations determined by ccTrace. This also 
	 * will output to stdout based on the app's devMode setting.
	 * @todo Consider throwing exception (caveat, flow of control does not continue)
	 * @todo Add distinction between dev and production modes of output.
	 * @todo Consider moving to separate Trace class
	 * @todo Display error to page based on display_errors setting
	 */
	static function onError($errno, $errstr, $errfile, $errline, $errcontext)
	{
		if (ini_get('error_reporting') & $errno)
		{
			$errortype = Array(
				E_ERROR			=> 'Error',			// 1
				E_PARSE			=> 'Parsing Error', // 4
//				E_CORE_ERROR	=> 'Core Error',	// 16
//				E_CORE_WARNING	=> 'Core Warning',	// 32
				E_COMPILE_ERROR	=> 'Compile Error',	// 64
//				E_COMPILE_WARNING => 'Compile Warning',	// 128
				E_WARNING		=> 'Warning',		// 2
				E_NOTICE		=> 'Notice',		// 8
				E_USER_ERROR	=> 'User Error',	// 256
				E_USER_WARNING	=> 'User Warning',	// 512
				E_USER_NOTICE	=> 'User Notice',	// 1024
				E_STRICT		=> 'Strict'			// 2048
			);
			if ( PHP_VERSION_ID >= 50200 )
			{
				$errortype[E_RECOVERABLE_ERROR] = 'Recoverable Error'; 	// 4096
				if ( PHP_VERSION_ID >= 50300 )
				{
					$errortype[E_DEPRECATED] = 'Deprecated';			// 8092
					$errortype[E_USER_DEPRECATED] = 'User Deprecated'; 	// 16384
				}			
			}
			if (!isset($errortype[$errno]))
				$errortype[$errno] = "Error($errno)";

			global $bred,$ered,$bb,$eb, $bi,$ei, $btt,$ett, $rarr,$ldquo,$rdquo,$hellip,$nbsp,$nl;
			error_log("$errortype[$errno]: $errstr in $errfile#$errline",0);
			$msg = "$bb$bred$errortype[$errno]$ered: $errstr$eb$nl"
//				 . "        in $errfile#$errline"; 
				 . "        in ".ccTrace::fmtPath($errfile,$errline); 
			$self = self::getApp();
			if ($self)					// In case this is invoked before constructor
				switch ($errno) 
				{
					case E_COMPILE_ERROR:
					case E_ERROR:
					case E_PARSE:
					case E_USER_ERROR:
						if ( ! ($self->devMode & self::MODE_ERR) ) break;
						$errno = E_ERROR;		// Normalize for next step
					case E_WARNING:
					case E_USER_WARNING:
					case E_RECOVERABLE_ERROR:
						if ( ! ( $errno == E_ERROR || $self->devMode & self::MODE_WARN ) ) break;
						$errno = E_WARNING; 	// Normalize for next step
					case E_NOTICE:
					case E_USER_NOTICE:
					case E_DEPRECATED:
					case E_USER_DEPRECATED:
						if ( ! ( $errno == E_WARNING || $self->devMode & self::MODE_INFO ) ) break;
						echo "$msg<br/>";
						break;
					default:
						echo "errno($errno): $msg<br/>";
						break;
				}
			else
				echo "errno($errno): $msg<br/>";
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
	 * Default exception handler. Works in conjunction with ccTrace to produce
	 * formatted output to the right destination.
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
	 * Redirect to a different URL. 
	 * @param string $url Send rediret to browser
	 * @todo Forward qstring, post  variables, and cookies. 
	 * @todo Allow "internal" redirect that does not return to the client.
	 * @todo Consider using ccHttpStatusException
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
			if (($this->getDevMode() & CCAPP_DEVELOPMENT) 
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
		// if (!(ccApp::$_me->devMode & CCAPPE_DEVELOPMENT))
			// return;
//		//error_log($string,3,'/home/wrlee/htd.log');
		// echo $string;
	// }
	
	/**
	 * Output to log file.
	 * options: HTML, log, stderr, stdout, formatted, timestamp
	 */
	static function log()
	{
		return call_user_func_array(array('ccTrace','log'),func_get_args());
	} // tr()
	/**
	 * Output debug output. 
	 * options: HTML, log, stderr, stdout, formatted, timestamp
	 */
	static function tr()
	{
		return call_user_func_array(array('ccTrace','tr'),func_get_args());
	} // tr()

	public function serialize ( )
	{
		return serialize($this);
	}
	public function unserialize ( $serialized )
	{
		self::$_me = $this;
		$temp = unserialize($serialized);
//		$this->config = $temp->config;
		$this->UrlOffset = $temp->UrlOffset;
		$this->devMode = $temp->devMode;
//		$this->bDebug = $temp->bDebug;
		$this->sitepath = $temp->sitepath;
		$this->temppath = $temp->temppath;
		$this->page = $temp->page;
		$this->error404 = $temp->error404;
		$this->classpath = $temp->classpath;
		$this->current_request = $temp->current_request;
	}
} // class ccApp

// We are using spl_autoload_* features to simplify search for class files. If
// the app has defined an __autoload() of their own without chaining it with
// the spl_autoload_register() call, then this will add it automatically.
if (function_exists('__autoload')) 
{
	spl_autoload_register('__autoload', true, true); 
}
spl_autoload_register(array(__NAMESPACE__.'\ccApp','_autoload'), true);

//set_include_path(get_include_path().PATH_SEPARATOR.__DIR__);
// require dirname(__DIR__).DIRECTORY_SEPARATOR.'SplClassLoader.php';
// $classLoader = new \SplClassLoader(__NAMESPACE__, dirname(__DIR__));
// $classLoader->register();

set_error_handler(Array(__NAMESPACE__.'\ccApp','onError'));
set_exception_handler(Array(__NAMESPACE__.'\ccApp','onException'));
/*public function errorHandlerCallback($code, $string, $file, $line, $context) 
{
	$e = new Excpetion($string, $code);
	$e->line = $line;
	$e->file = $file;
	throw $e;
}
*/
/**
 * Shutdown handler.
 * Capture last error to report errors that are not normally trapped by error-
 * handling functions, e.g., fatal and parsing errors.
 * @todo Activate only for debug mode.
 */
// register_shutdown_function(function ()
function cc_onShutdown()
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
// });
register_shutdown_function(__NAMESPACE__.'\cc_onShutdown');

// Just because PHP doesn't support setting class-consts via expressions, had to 
// create global consts :-(
define('CCAPP_DEVELOPMENT',(ccApp::MODE_DEBUG|ccApp::MODE_INFO|ccApp::MODE_WARN|ccApp::MODE_ERR|ccApp::MODE_TRACEBACK|ccApp::MODE_REVEAL));
define('CCAPP_PRODUCTION',(ccApp::MODE_CACHE*2)|ccApp::MODE_CACHE);
} // ccPhp


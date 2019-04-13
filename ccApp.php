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
 *    @todo Add example of DB/model component (Doctrine? RedBean?)
 *    @todo Add internal "redirection" support
 *    @todo Allow site paths to auto-generate paths.
 *    @todo Debugging/tracing component (work in progress: ccTrace
 *	   @todo Move error handling ccError class and refer through ccApp?
 *
 * Things I dunno how to do:
 *    @todo Need for session support?
 *    @todo Page caching
 *    @todo ob_start() support
 *    @todo Create structure of simple front-end event mapping to support here.
 *    @todo CSS and JS compression/minimization support for production mode.
 *    @todo Single ccApp.setDebug() setting that will cascade thru components.
 *	   @todo Logging support.
 * 	@todo Reconsider DevMode handling (rename to AppMode).
 * 	@todo Need a way to set "debug" setting that will cascade thru components.
 *	   @todo Look into using AutoLoad package (by the Doctrine and Symfony folks)?
 *    @todo MODE_PRODUCITON should prevent revealing errors (hide path info)
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
 * 2017-11-07 If no ccRequest is passed to dispatch(), it is created.
 * 2017-12-07 Renamed $sitepath --> $apppath to better reflect its purpose.
 * 2017-12-17 Simplfied DevMode usage: simple boolean
 */
//******************************************************************************\
namespace {
	include __DIR__.DIRECTORY_SEPARATOR.'inc/portable.php';

	// Load composer's autoloader
	if (  ! class_exists('\Composer\Autoload\ClassLoader', false)
		  && file_exists(__DIR__.DIRECTORY_SEPARATOR.'vendor/autoload.php') )
	{
//		echo __FILE__.'#'.__LINE__.'<br>'.PHP_EOL;
		// HACK! Remember class loader so it can be assigned w/in ccApp
		$composerClassloader = require(__DIR__.DIRECTORY_SEPARATOR.'vendor/autoload.php');
	}
} // namespace

namespace ccPhp
{
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
	implements
//		\Psr\Log\LoggerAwareInterface,// setLogger()
		\Serializable
{
	use \Psr\Log\LoggerAwareTrait;	// setLogger()
												/** Debugging output */
	const MODE_DEBUG	= 1;
												/** PHP info msgs */
	const MODE_INFO		= 6;
												/** PHP warnings */
	const MODE_WARN		= 2;
												/** PHP errors */
	const MODE_ERR		= 4;
												/** Show tracebacks */
	const MODE_TRACEBACK= 8;
												/** Reveal paths */
	const MODE_REVEAL	= 16;
								/** Use minimized resources (scripts, CSS, etc.) */
	const MODE_MINIMIZE	= 32;
												/** Enable profile */
	const MODE_PROFILE	= 64;
												/** Enable caching where it can */
	const MODE_CACHE	= 128;
								/** @var ccApp Reference to singleton self */
	protected static $_me=NULL;
								// Configuration array
//	protected $config=Array();
								/** @var string URL path offset to base of this app;
								 * path from domain root for the site.
								 */
	protected $UrlOffset=NULL;
								/** @var bool Dev mode active */
	protected $devMode = true;
								/** @var boolean Central place to hold debug status */
//	protected $bDebug = false;
								/** @var string Path to the root of app specific files (not the web-facing ones). */
	protected $apppath=NULL;
								/** @var string Path to working directory (for cache, etc) */
	protected $temppath='';
								/** @var ccPageInterface Main page object for app */
	protected $page=NULL;
								/** @var string|ccPageInterface class that renders 404 pages. */
	protected $error404 = NULL;
								/** @var ClassLoader|callback autoload handler */
	protected $classLoader=NULL;
								// The following are rel to apppath:
								/** @var array List of site paths to search for classes */
	protected $classpath=array();
								/** @var ccRequest Remember current request */
	protected $current_request;

	/**
	 * Save $_me as a singularity and (hack).
	 */
	protected function __construct(...$args)	// As a singleton: block public instantiation
	{
		self::$_me=$this;
		$callstack = (PHP_VERSION_ID < 50400) ? debug_backtrace(0) : debug_backtrace(0,2);

		// Hack: look up the call-stack to pull 1st arg from createApp()
		foreach ($callstack as $caller)
			if ($caller['function'] == 'createApp')
			{
				$this->apppath = $caller['args'][0];
				if (substr($this->apppath, -1) != DIRECTORY_SEPARATOR)
					$this->apppath .= DIRECTORY_SEPARATOR;
				break;
			}

		// Use Composer\Loader\ClassLoader, if it was found
		global $composerClassloader;
		if ($composerClassloader && !$this->classLoader)
			$this->classLoader = $composerClassloader;

		// Add app's root as an inclusion directory.
		if ($this->classLoader)
			$this->classLoader->addPsr4("", [$this->apppath], true);

		$this->setLogger( new ccLogger() );
////		$this->logger->enableHtml(false);
//		$this->logger->trace('testing',1,2,3, 'end of stuff');
//		$this->logger->info('testing',['one',2,'three']);
	} // __construct()

	/**
	 * Search for class definition from framework folders.
	 * If there autoload() exists, call it's autoload, first. Derivations of ccApp
	 * can override autoload().
	 *
	 * This method is appropriate to call this from __autoload() or
	 * register via spl_autoload_register()
	 * @deprecated This will be subsumed by ClassLoader-style functionality,
	 * 	separating this functionality into a separate class.
	 * @param string $className Name of class to load.
	 * @return bool|null True if loaded, null otherwise
	 * @see createApp() where this method is registered.
	 */
	public final static function _autoload($className)
	{
// echo __FUNCTION__.'#'.__LINE__."($className) "." <br>";
		// Check instance specific autoload. If a derived autoload exists, call it, it takes priority
		if (self::$_me && method_exists(self::$_me,'autoload'))
		{
			if (self::$_me->autoload($className)) // Using spl_autoload_register()?
				return true;
		}

		// Check framework-specific directories
//		if (!class_exists($className,false) && !trait_exists($className,false))
		{
			if (   __NAMESPACE__ != ''
				 && __NAMESPACE__ == substr($className, 0, strlen(__NAMESPACE__)))
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
			if (file_exists(self::getFrameworkPath() . 'classes' . DIRECTORY_SEPARATOR . $classFilename))
				include(self::getFrameworkPath() . 'classes' . DIRECTORY_SEPARATOR . $classFilename);
			elseif (file_exists(self::getFrameworkPath() . $classFilename))
				include(self::getFrameworkPath() . $classFilename);
		}
	} // _autoload()

	/**
	 * Add a path to the list of site-specific paths to search when
	 * loading site-specific classes.
	 * @param string $path Is the path to be included in the search
	 *        or, if $classname is specified, then the full filepath. If the first
	 *        char is not '/' (or '\', as appropriate) then the app dir is
	 *        assumed.
	 * @param string $classname is an optional class name that, when sought, will
	 *        load the specified file specified by $path. If prefixed with a '\',
	 *        then it represents a namespace. In this case, $path represents a
	 *        source directory for files of that namespace.
	 * @example
	 *    define('DS',DIRECTORY_SEPARATOR);
	 *	  $app->addClassPath('classes')		// Search app's directory
	 *	      ->addClassPath('..'.DS.'smarty'.DS.'Smarty.php','Smarty')
	 *	  	  ->addClassPath('..'.DS.'RedBeanPHP'.DS.'rb.php','R')
	 *	  	  ->addClassPath('..'.DS.'Facebook'.DS.'facebook.php','Facebook');
	 * @todo Allow array of directories to be passed in.
	 * @todo Test for path's existence.
	 * @deprecated This will be subsumed by ClassLoader-style functionality,
	 * 	separating this functionality into a separate class.
	 */
	function addClassPath($path,$classname=NULL)
	{
		if (!$path)
			$path = $this->apppath;
		elseif ($path[0] != DIRECTORY_SEPARATOR)
			$path = $this->apppath.$path;

		if (    $classname && $classname[0] == '\\'
			  && $this->classLoader && method_exists($this->classLoader,'addPsr4') )
		{
			$classname = substr($classname,1).$classname[0];	// Move '\' to end
			$this->classLoader->addPsr4($classname, [$path]);
		}
		elseif (    $classname && $this->classLoader
					&& method_exists($this->classLoader,'addClassMap') ) {
			$this->classLoader->addClassMap([ $classname => [ $path ] ]);
		}
		else
		{
			if ($classname)
				$this->classpath[$classname] = $path;
			else
			{
				if (substr($path,-1) != DIRECTORY_SEPARATOR)
					$path .= DIRECTORY_SEPARATOR;
				$this->classpath[] = $path;
			}
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
	 * @todo Automatically convert '/' or '\' to the correct DIRECTORY_SEPARATOR
	 *			for the current OS.
	 * @deprecated This will be subsumed by ClassLoader-style functionality,
	 * 	separating this functionality into a separate class.
	 */
	public function addIncludePath($path,$prefix=false)
	{
		set_include_path(
			$prefix
				? $path . PATH_SEPARATOR . get_include_path()
				: get_include_path() . PATH_SEPARATOR . $path
		);
		return $this;
	} // addIncludePath()

	/**
	 * Instance specific autoload() searches site specific paths.
	 * @param  string $className Name of class to load.
	 * @return bool|null True if loaded, null otherwise
	 * @deprecated This will be subsumed by ClassLoader-style functionality,
	 * 	separating this functionality into a separate class.
	 */
	public function autoload($className)
	{
		$classFilename = "$className.php";
//		$classFilename = str_replace('_', DIRECTORY_SEPARATOR, $className).'.php';
//		$classFilename = str_replace('\\', DIRECTORY_SEPARATOR, $className).'.php';

// global $bb,$eb, $bi,$ei, $btt,$ett, $rarr,$ldquo,$rdquo,$hellip,$nbsp,$nl;
// ccTrace::s_out( '#'.__LINE__.' '.ccTrace::getCallerString(0,dirname(self::getApp()->getAppPath())).': '.$className." $rarr ".$classFilename.$nl);
// ccTrace::s_out( '#'.__LINE__.' '.ccTrace::getCallerString(3).': '.$className." $rarr ".$classFilename.$nl);
// if (strpos($className, 'PicturesToc') > -1)
// echo __FUNCTION__.'#'.__LINE__." $className $rarr $classFilename".$nl;
// self::tr('&nbsp;&nbsp;&nbsp;'.$this->apppath.$classFilename);
// ccTrace::tr('&nbsp;&nbsp;&nbsp;'.$this->apppath.$classFilename);

		// Check app paths, first
		if ($this->apppath && file_exists($this->apppath . $classFilename))
		{
			include($this->apppath . $classFilename);
			return true;
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
					return true;
			}
			// If class-association registered w/ccApp is a namespace, check whether
			// class to load has a namespace; if namespace names match, then use
			// registered path as source.
// echo __METHOD__.'#'.__LINE__.' '.substr($class,1).'\\'." === ".substr($className,0,strlen($class)).$nl;
			if (   $class[0] === '\\' && strpos($className,'\\') !== false
				 && substr($class,1).'\\' === substr($className,0,strlen($class)) )
			{
				$namespaceClassName = substr($className,strlen($class)).'.php';
				if (include($path . $namespaceClassName))
					return true;
			}
			if (file_exists($path . $classFilename))
//			elseif (@include($path . $classFilename))
			{							// Else if assumed name exists...
				include($path . $classFilename);
				return true;
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
	 * @todo Allow parameters passed to constructor.
	 */
	public static function createApp($appPath, $className=NULL)
	{
/*		{ // TODO: argument block sets app settings
			$args=[];	// Formal parameters
			foreach ($args as $setting => $value) {
				$setting = strtolower($setting);	// Normalize to lowercase
				switch ($setting) {
					case 'appdir':
					case 'app-dir':
					case 'dir':
						$this->appPath = $value;
						break;

					case 'Logger':
						$this->logger = $value;
						break;

					case 'disable-errorhandler':
					case 'errorhandler':
						$this->errorhandler = $value;
						break;

					case 'devmode':
						$this->devMode = $value;
						break;

					default:
						// Unexpected setting
						break;
				}
			}
		}
*/
//		$sessActive = (session_status() == PHP_SESSION_ACTIVE);
//	    if (!$sessActive)					// If session support not running
//	    	session_start();				   //   turn on to presist browser info
//
//	    if ( isset($_SESSION['ccApp']) ) 	// If already cached, return info
//	    {
//	    	self::$_me = unserialize($_SESSION['ccApp']);
//		    if ( !$sessActive )				// Session wasn't running
//		    	session_commit();			   //   So turn back off
//	    	return self::$_me;				// Return serialized object
//	    }
//
// [BEGIN] Global hooks
		// If no composer, then handle class-loading locally.
		// @todo Separate this into external class or functions
		if ( ! class_exists('\Composer\Autoload\ClassLoader',false) ) {
			// We are using spl_autoload_* features to simplify search for class files. If
			// the app has defined an __autoload() of their own without chaining it with
			// the spl_autoload_register() call, then this will add it automatically.
			if (function_exists('__autoload'))
			{
				spl_autoload_register('__autoload', true, true);
			}
			spl_autoload_register(__CLASS__.'::_autoload', true);
		}
		else {
//			echo __FUNCTION__.'#'.__LINE__." ClassLoader loaded...<br>".PHP_EOL;
		}

		//set_include_path(get_include_path().PATH_SEPARATOR.__DIR__);
		// require dirname(__DIR__).DIRECTORY_SEPARATOR.'SplClassLoader.php';
		// $classLoader = new \SplClassLoader(__NAMESPACE__, dirname(__DIR__));
		// $classLoader->register();
		if ( !class_exists(__NAMESPACE__.'\ccErrorHandler', false) ) {
			set_error_handler(__CLASS__.'::onError');
			set_exception_handler(__CLASS__.'::onException');
			/**
			 * Shutdown handler.
			 * Capture last error to report errors that are not normally trapped by error-
			 * handling functions, e.g., fatal and parsing errors.
			 * @todo Activate only for debug mode.
			 */
			// function cc_onShutdown()
			register_shutdown_function(function ()
			{
			    $err=error_get_last();
				switch ($err['type'])
				{
					case E_WARNING:
					case E_NOTICE:
					case E_USER_ERROR:
					case E_USER_WARNING:
					case E_USER_NOTICE:
						return false;
					break;

					case E_COMPILE_ERROR:
					case E_PARSE:
					default:
						return ccApp::onError($err['type'], $err['message'], $err['file'], $err['line'], $GLOBALS);
				}
			//	trigger_error($err['message'],$err['type']);
			}
			); // register_shutdown_function()
			// register_shutdown_function(__NAMESPACE__.'\cc_onShutdown');
			}
		else {
// 		echo __FUNCTION__.'#'.__LINE__." ccErrorHandler loaded<br>".PHP_EOL;
		}
	/*		public function errorHandlerCallback($code, $string, $file, $line, $context)
		{
			$e = new Excpetion($string, $code);
			$e->line = $line;
			$e->file = $file;
			throw $e;
		}
*/
// [END] Global hooks

		if (substr($appPath,-1) != DIRECTORY_SEPARATOR)	// Ensure path-spec
			$appPath .= DIRECTORY_SEPARATOR;					// suffixed w/'/'

		chdir($appPath);						// Set cd to "known" place
		$className ? new $className : new self;

//		$_SESSION['ccApp'] = serialize(self::$_me);
//	    if ( !$sessActive )					// Session wasn't running
//	    	session_commit();					//   So turn back off

		return self::$_me;
	} // createApp()

	/**
	 * Create app-specific directory. If this is a relative path, it is assumed to
	 * be relative to the app's root path (this is not the web-facing directory,
	 * but the directory where the app sits).
	 *
	 * @param string $dir Relative or absolute directory name
	 *
	 * @return string Full path name (suffixed with '/' prefixed w/
	 *			site-path.
	 * @todo Accept array of directory names (to move common code here)
	 */
	public function createAppDir($dir)
	{
		if ( substr($dir, -1) != DIRECTORY_SEPARATOR )	// Ensure suffixed w/'/'
			$dir .= DIRECTORY_SEPARATOR;
		if ( $dir[0] != DIRECTORY_SEPARATOR )			// Not absolute path?
			$dir = $this->apppath . $dir;					// Prefix with site's path
		if (!is_dir($dir))							      // Path does not exist?
			mkdir($dir,0744,true);                    // Create path
		return $dir;									      // Return modified path
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
			$dir = $this->apppath.$this->temppath.$dir;// Prefix with site's working path
		if (!is_dir($dir))								// Path does not exist?
			mkdir($dir,0744,true);						// Create path
		return $dir;									// Return modified path
	} // createWorkingDir()

	/**
	 * This method is called to render pages for the web site. It invokes the
	 * "page" (which is usually a dispatcher or controller) to render
	 * content. ccPageInterface::render() returning false implies no content was
    * rendered then 404, Page Not Found. handling is invoked.
	 * @param  ccRequest $request Current HTTP/ccPhp request
	 * @throws ccHttpStatusException If false, since this is the end of the line.
	 */
	public function dispatch(ccRequest $request=NULL)
	{
      ($request === NULL) && $request = new ccRequest();

		try
		{
			$this->current_request = &$request;
			if (!$this->page->render($request))
			{
//          ccTrace::tr(getallheaders());
				throw new ccHttpStatusException(404);
			}
		}
		catch (ccHttpStatusException $e)
		{
			switch ($e->getCode())
			{
				case 300: case 301: case 302: case 303:
				case 305: case 306: case 307:
					header($_SERVER['SERVER_PROTOCOL'].' '.$e->getCode().' '.$e->getMessage(), true, $e->getCode());
					$this->redirect($e->getLocation(), $e->getCode(), $e->getMessage());
					break;
				case 304:
					header($_SERVER['SERVER_PROTOCOL'].' '.$e->getCode().' '.$e->getMessage(), true, $e->getCode());
					break;
				case 404: $this->show404($request);
					break;
				default:				// No other stati supported right now.
//					http_response_code($e->getCode());
					if (!headers_sent())
						header($_SERVER['SERVER_PROTOCOL'].' '.$e->getCode().' '.$e->getMessage(), true, $e->getCode());
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
	 * @param string  $name     Cookie name
	 * @param string  $value    Cookie value
	 * @param integer $expire   Expiration
	 * @param string  $path     URI sub-path
	 * @param string  $domain   Domain
	 * @param boolean $secure   https only?
	 * @param boolean $httponly http only?
	 *
	 * @see  http://php.net/manual/en/function.setcookie.php
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
	 * Set the autoload handler for the project. The default is to use the
	 * locally associated Composer\Loader\ClassLoader. However, this is only
	 * available if composer is used. There is no standard interface for
	 * a class-loader object, we currently assume Composer\Loader\ClassLoader.
	 *
	 * @todo Allow classloader to be a function/method
	 */
	function setClassLoader(object $classLoader)
	{
		$this->classLoader = $classLoader;
		return $this;
	}

	/**
	 * Get/set app's disposition mask.
	 * @return bool Dev mode active.
	 */
	public function getDevMode()
	{
		return $this->devMode;
	}
	/**
	 * Set "how" the app should behave based on the $mode bit-mask.
	 * @param bool $mode Dev mode?
	 */
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
		return __DIR__.DIRECTORY_SEPARATOR;
	} // getFrameworkPath()

	/**
	 * LoggerAwareInterface
	 */
	public function setLogger(\Psr\Log\LoggerInterface $logger)
	{
		$this->logger = $logger;
		return $this;
	}

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
		if ($p === false)			// No protocol scheme.
		{							// Don't know what to do here... bad input.
		}
		else 								// Set $path to the part past the domain:port part
		{									// suffixed with a '/'
			$p = strpos($path,'/',$p+2);	// First path separator past the scheme
			if ($p === false)				// No '/': this app is at the root.
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
		return $this->apppath;
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
//		$this->apppath = $path;						// Save path
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
	 * @return string Absolute path of working directory
	 */
	public function getWorkingPath()
	{
		return ($this->temppath[0] == DIRECTORY_SEPARATOR)
				? $this->temppath
				: $this->apppath.$this->temppath;
	}

	/**
	 * Default 404 handler.
	 * @param ccRequest $request The current request
	 */
	protected function on404(ccRequest $request)
	{
		if (!headers_sent())
		{
			http_response_code(404);
//			header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found', true, 404);
			header('Content-type: text/html');
		}
		?>
		<html><body><hr/>
		<?php print $request->getUrl() ?>
		<h1>404 Not Found</h1>
		This is not the page you are looking for.<hr/>
		<?php
		if ($this->devMode)
		{
			$trace = debug_backtrace();	// Get whole stack list
			array_shift($trace);				// Ignore this function
			ccTrace::showTrace($trace);	// Display stack.
		}
		?>
		</body></html>
		<?php
//		exit();
	} // on404()

	/**
	 * Php error handler directs output to destinations determined by ccTrace. This also
	 * will output to stdout based on the app's devMode setting.
	 *
	 * @param  integer 	$errno		Error number
	 * @param  string 	$errstr     Error text
	 * @param  string 	$errfile    Filename containing error
	 * @param  integer 	$errline   	Line number of error occurance in $errfile
	 * @param  array 		$errcontext Symbol table state when error occurred
	 *											(Deprecated as of v7.2.0)
	 *
	 * @todo Consider throwing exception (caveat, flow of control does not continue)
	 * @todo Add distinction between dev and production modes of output.
	 * @todo Consider moving to separate Trace class
	 * @todo Display error to page based on display_errors setting
	 */
	static function onError($errno, $errstr, $errfile, $errline, $errcontext=NULL)
	{
		if (error_reporting() & $errno)
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
				E_STRICT		=> 'Strict',			// 2048
				E_RECOVERABLE_ERROR => 'Recoverable Error', // 4096
				E_DEPRECATED => 'Deprecated',				// 8092
				E_USER_DEPRECATED => 'User Deprecated' // 16384
			);
			if (!isset($errortype[$errno]))
				$errortype[$errno] = "Error($errno)";

			// Output text version w/o HTML
			error_log("$errortype[$errno]: $errstr in $errfile#$errline",0);

			global $bred,$ered,$bb,$eb, $bi,$ei, $btt,$ett, $rarr,$ldquo,$rdquo,$hellip,$nbsp,$nl;
			$msg = "$bb$bred$errortype[$errno]$ered: $errstr$eb$nl"
//				 . "        in $errfile#$errline";
				 . "        in ".ccTrace::fmtPath($errfile,$errline);
/*			$self = self::getApp();
			if ($self)					// In case instance exists, we can access devMode
				switch ($errno)	// Classify errors
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
*/
			$trace = debug_backtrace();		// Get whole stack list
			array_shift($trace);					// Ignore this function
			ccTrace::showTrace($trace);		// Display stack.
			return true;
		}
		else
			return false; 	// chain to normal error handler
	} // onError()

	/**
	 * Default exception handler. Works in conjunction with ccTrace to produce
	 * formatted output to the right destination.
	 *
	 * @param  Exception $exception Exception object to report
	 *
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
	 * @param  string $url Send rediret to browser
	 * @param  integer $status  HTTP status code #
	 * @param  string  $message Status code text message
	 *
	 * @todo Forward qstring, post  variables, and cookies.
	 * @todo Allow "internal" redirect that does not return to the client.
	 * @todo Consider using ccHttpStatusException
	 */
	function redirect($url,$status = 302,$message='Redirect')
	{
		if (!headers_sent())
		{
			header($_SERVER['SERVER_PROTOCOL'].' '.$status.' '.$message, true, $status);
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
	 * @param  ccRequest $request Current HTTP/ccPhp request
	 */
	protected function show404(ccRequest $request)
	{
		ccTrace::log($request->getUrl());
		if ($this->error404)	// If 404 page defined
		{
			if (is_string($this->error404))
				$this->error404 = new $this->error404;
			if (    $this->getDevMode()
				  && ! ($this->error404 instanceof ccPageInterface))
			{
				trigger_error(get_class($this->error404).' does not implement ccPageInterface', E_WARNING);
			}
			call_user_func(array($this->error404,'render'), $request);
		}
		else 						// No app specific page
			$this->on404($request);	// Perform local 404 rendering
	} // show404()

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

	/**
	 * serializable implementation to save instance.
	 * @return [type] Serialized object
	 */
	public function serialize ( )
	{
		return serialize($this);
	}
	/**
	 * serializable implementation to restore object.
	 * @param  [type] $serialized Serialized object.
	 */
	public function unserialize ( $serialized )
	{
		self::$_me = $this;
		$temp = unserialize($serialized);
//		$this->config = $temp->config;
		$this->UrlOffset = $temp->UrlOffset;
		$this->devMode = $temp->devMode;
//		$this->bDebug = $temp->bDebug;
		$this->apppath = $temp->apppath;
		$this->temppath = $temp->temppath;
		$this->page = $temp->page;
		$this->error404 = $temp->error404;
		$this->classpath = $temp->classpath;
		$this->current_request = $temp->current_request;
	}
} // class ccApp

// Just because PHP doesn't support setting class-consts via expressions, had to
// create global consts :-(
//define('CCAPP_DEVELOPMENT',(ccApp::MODE_DEBUG|ccApp::MODE_INFO|ccApp::MODE_WARN|ccApp::MODE_ERR|ccApp::MODE_TRACEBACK|ccApp::MODE_REVEAL));
//define('CCAPP_PRODUCTION',(ccApp::MODE_CACHE*2)|ccApp::MODE_CACHE);
} // namespace ccPhp

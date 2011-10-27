<?php
/**
 * File: ccApp.php
 * 
 * The ccApp class represents the application. It is a singlton
 *
 * @todo Look into using AutoLoad package (by the Doctrine and Symfony folks)
 * @todo Add session handling.
 * @todo Need a way to set "debug" setting that will cascade thru components.
 * @todo Move error handling ccError class and refer through ccApp
 *
 */
/*
 * 2010-10-22 404 uses exceptions
 *          - Extend classpath interpretation to enter specific class specs.
 */

error_reporting(E_ALL|E_STRICT);
// error_reporting(E_STRICT);
// error_reporting(ini_get('error_reporting')|E_STRICT);
set_error_handler(Array('ccApp','onError'));
set_exception_handler(Array('ccApp','onException'));

// We are using spl_autoload_* features to simplify search for class files. If
// the app has defined an __autoload() of their own without chaining it with
// the spl_autoload_register() call, then this will add it automatically.
if (function_exists('__autoload')) 
{
	spl_autoload_register('__autoload', true, true); 
}
spl_autoload_register(array('ccApp','_autoload'), true);

ccApp::setFrameworkPath(dirname(__FILE__));	// Function probably not needed

/**
 * Framework class reprsenting the "application". You can derive this class, but
 * you are not allowed to instantiate it directly, use ccApp::createApp().
 *
 * @todo Consider consolidating createApp() with getApp()
 */
class ccApp
{
	const MODE_DEVELOPMENT	= 1;
	const MODE_TESTING		= 2;
	const MODE_STAGING		= 4;
	const MODE_PRODUCTION	= 8;

	protected static $_me=NULL;			// Singlton ref to $this
	protected static $_fwpath=NULL;		// Path to framework files.

	protected $config=Array();			// Configuration array

	protected $_UrlOffset=NULL;			// Path to site's root
	protected $devMode = self::MODE_DEVELOPMENT; 
	protected $sitepath=NULL;			// Path to site specific files.
	protected $page=NULL; 				// Main page for site
	protected $error404 = NULL;			// External ccPageInterface to render errors.
										// The following are rel to sitepath:
	protected $classpath=array();		// List of site paths to search for classes
	protected $_wwwpath='public';		// Path to web visible files

	protected function __construct()
	{
//		echo __METHOD__ . PHP_EOL;
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
			$this->classpath[] = $this->sitepath.$path;
		}
		return $this;
	} // addClassPath()

	/**
	 * Convenience method to prefix or suffix the PHP search path.
	 * @param string $path Path component to add to search path.
	 * @param bool $prefix Prefix the search path with the new path. Default:
	 *        false, append new path to the end of the search path.
	 * @see set_include_path()
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
// self::tr($className.'&rarr;'.$classFilename);
// self::tr('&nbsp;&nbsp;&nbsp;'.$this->sitepath.$classFilename);

		// Check app paths, first
		if ($this->sitepath && file_exists($this->sitepath . $classFilename)) 
		{
			include($this->sitepath . $classFilename);
		}
		else foreach ($this->classpath as $class => $path)
		{
			if ($class == $className)
			{
				if (!file_exists($path))
					throw new Exception($path . " does not exist in ".getcwd());
				include($path);
				return;
			}
			else if (file_exists($path . $classFilename)) 
			{
				include($path . $classFilename);
				return;
			}
		}
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
	 * This method is called to render pages for the web site. It invokes the 
	 * "main page" (which is usually a dispatcher or controller) to render 
	 * content. If no content is rendered (i.e., the page's render() method
	 * returns FALSE, then 404 handling is invoked. 
	 */
	function dispatch(ccRequest $request)
	{
		try
		{
			if (!$this->page->render($request))
			{
				throw new ccHttpStatusException(404);
			}
		}
		catch (ccHttpStatusException $e)
		{
			switch ($e->getStatus())
			{
				case 300: case 301: case 302: case 303: 
				case 304: case 305: case 306: case 307: 
					$this->redirect($e->getLocation(), $e->getStatus(), $e->getMessage());
					break;
				case 404: $this->show404($request);
					break;
				default:				// No other stati supported right now.
//					http_response_code($e->getStatus());
					if (!headers_sent())
						header($_SERVER['SERVER_PROTOCOL'].' '.$e->getStatus().' '.$e->getMessage());
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
// echo __METHOD__.'#'.__LINE__.' path: '	. self::$_fwpath.'<br/>'.PHP_EOL;
		return self::$_fwpath;
	} // getFrameworkPath()
	public static function setFrameworkPath($path)
	{
		if (substr($path,-1) != DIRECTORY_SEPARATOR)
			$path .= DIRECTORY_SEPARATOR;

		self::$_fwpath = $path;
// echo __METHOD__.'#'.__LINE__.' path: '	. self::$_fwpath.'<br/>'.PHP_EOL;
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
	 * Get/set server path to site's files (not the URL)
	 * @param string $path Full, absolute path (e.g., dirname(__FILE__) 
	 *                     of caller)
	 */
	public function getSitePath()
	{
		return $this->sitepath;
	} // getSitePath()
	public function setSitePath($path)
	{
		if (substr($path,-1) != DIRECTORY_SEPARATOR)
			$path .= DIRECTORY_SEPARATOR;

		$this->sitepath = $path;
		return $this;
	} // setSitePath()

	/**
	 * The URI offset is the part of the URL that spans from the server name
	 * (and port, if any) to the controller name.  This is the URL for the
	 * dispatcher, and is usually "/" or "/index.php/".
	 */
	public function getUrlOffset()
	{
		if (!$this->_UrlOffset)				// If not set, 
			$this->setUrlOffset(); 			//    Ensure init'd 
		return $this->_UrlOffset;
	} // setUrlOffset()
	public function setUrlOffset($component=NULL)
	{
		if (!$component)
		{
			$component = dirname($_SERVER['SCRIPT_NAME']);
			if ($component != '/') 
				$component .= '/';
		}
		$this->_UrlOffset = $component;
		return $this;
	} // setUrlOffset()

	/**
	 * Default 404 handler.
	 * @param ccRequest $request The current request
	 */
	protected function on404(ccRequest $request)
	{
//		http_response_code(404);
		if (!headers_sent())
			header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
		?>
		<hr/>
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
//		exit();
	} // on404()

	/**
	 * @todo Consider throwing exception (caveat, flow of control does not continue)
	 * @todo Add distinction between dev and production modes of output.
	 * @todo Consider moving to separate Trace class
	 */
	static function onError($errno, $errstr, $errfile, $errline, $errcontext)
	{
//		throw new ErrorException($errstr, $errno,0,$errfile,$errline);
		if (ini_get('error_reporting') & $errno)
		{
			$errortype[E_WARNING] = 'Warning';
			$errortype[E_NOTICE] = 'Notice';
			$errortype[E_USER_ERROR] = 'User Error';
			$errortype[E_USER_WARNING] = 'User Warning';
			$errortype[E_USER_NOTICE] = 'User Notice';
			$errortype[E_STRICT] = 'Strict';
			$errortype[E_RECOVERABLE_ERROR] = 'Recoverable Error';
			$errortype[E_USER_DEPRECATED] = 'User Deprecated';
			error_log("$errortype[$errno]: $errstr in $errfile#$errline",0);
			print "<br/><b><font color='red'>$errortype[$errno]</font>: $errstr</b>\n        in $errfile#$errline<br/>".PHP_EOL;
//			echo '<pre>';
//			var_dump($errcontext);
//			echo '</pre>';
			$trace = debug_backtrace();		// Get whole stack list
			array_shift($trace);			// Ignore this function
			ccTrace::showTrace($trace);		// Display stack.
//			die();
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
			print get_class($exception).' '.$exception->getMessage().' in '.$exception->getFile().'#'.$exception->getLine().'<br/>'.PHP_EOL;
			ccTrace::showTrace($exception->getTrace());
//			echo '<pre>';
//			print $exception->getTraceAsString();
//			echo '</pre>';
///			die();
		}
		catch (Exception $e)
		{
			print get_class($e)." thrown within the exception handler. Message: ".$e->getMessage()." on line ".$e->getLine();
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
			header($_SERVER['SERVER_PROTOCOL'].' '.$status.' '.$message);
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
		if ($this->error404)
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
		else 
			$this->on404($request);
	} // show404()

	/**
	 * options: HTML, log, stderr, stdout, formatted, timestamp
	 */
	static function tr($msg='')
	{
		if (!(ccApp::$_me->devMode & ccApp::MODE_DEVELOPMENT))
			return;
		$trace = debug_backtrace(
				// DEBUG_BACKTRACE_IGNORE_ARGS
				// | 
				// DEBUG_BACKTRACE_PROVIDE_OBJECT
				// ,2);
				// TRUE
				);
// echo '<pre>';
// echo __METHOD__.' ';
// var_dump($trace);
//		error_log( ccTrace::showTraceLine($trace[0],TRUE) );
//echo $trace[0]['file'].'#'.$trace[0]['line'].' '.$trace[1]['class'].'::'.$trace[1]['function'].'<br/>';
		if (FALSE)
		{
			$bb = $eb = 
			$bi = $ei = 
			$btt = $ett = '';
			list ($rarr,$ldquo,$rdquo,$hellip,$nl) = 
			array('->', '"',   '"',   '...',  PHP_EOL);
		}
		else
		{
			list ($bb,  $eb,   $bi,  $ei,   $btt,  $ett,   $rarr,   $ldquo,$rdquo,   $hellip,   $nl) =
			array('<b>','</b>','<i>','</i>','<tt>','</tt>','&rarr;','&ldquo;','&rdquo;','&hellip;','<br/>'.PHP_EOL);
		}
		$out = '';
		if (isset($trace[1]['class']))
			$out .= $bb.$trace[1]['class'].$eb;
		if (isset($trace[1]['object']) 
			&& get_class($trace[1]['object']) != $trace[1]['class'])
			$out .= $bi.'('.get_class($trace[1]['object']).')'.$ei;
		if (isset($trace[1]['type']))
			$out .= ($trace[1]['type'] == '->' ? $rarr : $trace[1]['type']);
		$out .= $bb.$trace[1]['function'].$eb.'()#'.$trace[0]['line'];

		if ($msg === '' || $msg === NULL || is_string($msg))
			echo $out.' '.$msg.$nl;
		else
		{
echo '<pre>';
			echo $out.' ';
			print_r($msg);
			echo PHP_EOL;
echo '</pre>';
		}
		// echo $msg.'<br/>'.PHP_EOL;
		// echo '<br/>'.PHP_EOL;
//		ccTrace::showTrace($trace);
		// echo '<pre>';
		// debug_print_backtrace();
		// echo '</pre>';
	} // tr()

} // class ccApp

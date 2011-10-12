<?php
/**
 * File: ccApp.php
 * 
 * The ccApp class represents the application. It is a singlton
 *
 * @todo Look into using AutoLoad package (by the Doctrine and Symfony folks)
 * @todo Add session handling.
 * @todo Need a way to set "debug" setting that will cascade thru components.
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
	const MODE_DEVELOPMENT = 1;
	const MODE_TESTING = 2;
	const MODE_STAGING = 4;
	const MODE_PRODUCTION = 6;

	protected static $_me=NULL;			// Singlton ref to $this
	protected static $_fwpath=NULL;		// Path to framework files.

	protected $config=Array();			// Configuration array

	protected $_UrlOffset=NULL;			// Path to site's root
	protected $devMode = self::MODE_DEVELOPMENT; 
	protected $pluginpath=NULL;			// Path to include
	protected $sitepath=NULL;			// Path to site specific files.
	protected $error404 = NULL;
										// The following are rel to sitepath:
	protected $classpath=array();
	protected $_wwwpath='public';		// Path to web visible files

	protected function __construct()
	{
//		echo __METHOD__ . PHP_EOL;
	} // __construct()
	
	/**
	 * Search for class definition from framework folder. If there is an  
	 * instance, call its autoload first (esp since it can be derived and,
	 * therefore, be more specific to the app). 
	 *
	 * This method is appropriate to call this from __autoload() or 
	 * register via spl_autoload_register()
	 */
	public static function _autoload($className)
	{
		$classFilename = str_replace('_', DIRECTORY_SEPARATOR, $className).'.php';
// echo basename(__FILE__).'#'.__LINE__.' '.__METHOD__.'(<b>'.$className.'</b>)<br/>'.PHP_EOL;
// echo __METHOD__.'(<b>'.$className.'</b>)#'.__LINE__ .' '.self::$_fwpath.$classFilename.'<br/>'.PHP_EOL;
											// Check instance specific autoload
		if (self::$_me && method_exists(self::$_me,'autoload'))
		{
			self::$_me->autoload($className); // Using spl_autoload_register()?
		}
		if (!class_exists($className))		// Check framework directories
		{
			if (file_exists(self::$_fwpath . 'core' . DIRECTORY_SEPARATOR .$classFilename)) 
			{
// echo __METHOD__.'(<b>'.$className.'</b>)#'.__LINE__.' '.self::$_fwpath. 'core'.'<br/>'.PHP_EOL;
				include(self::$_fwpath . 'core' . DIRECTORY_SEPARATOR .$classFilename);
			}
			elseif (file_exists(self::$_fwpath . $classFilename)) 
			{
// echo __METHOD__.'(<b>'.$className.'</b>)#'.__LINE__ .self::$_fwpath.'<br/>'.PHP_EOL;
				include(self::$_fwpath . $classFilename);
			}
		}
	} // _autoload()
	
	function addClassPath($path)
	{
		if (substr($path,-1) != DIRECTORY_SEPARATOR)
			$path .= DIRECTORY_SEPARATOR;
		$this->classpath[] = $this->sitepath.$path;
	}

	/**
	 * Add to PHP search path
	 * @param String $path Path component to add to search path.
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
	 * Rather than embed and dictate where external utilities are to be installed,
	 * this method allows the "installation" of the utility to be declared for 
	 * the app globally. 
	 * @see include()
	 * @see get/setPluginPath()
	 * @todo Check for colon and '\' for Windows paths
	 * @todo This should not be necessary... and is inefficient because it should
	 *       only be called when it is necessary to be used. Perhaps we should 
	 *       add searching/loading to the autoload handling. 
	 */
	public function includePlugin($pluginFilepath)
	{
		$pluginpath = ($this->pluginpath) 
			? $this->pluginpath
			: dirname(self::$_fwpath) . DIRECTORY_SEPARATOR;
		if (substr($pluginFilepath,0,1) != DIRECTORY_SEPARATOR)
			$pluginFilepath = $pluginpath . $pluginFilepath;
// echo __METHOD__.'#'.__LINE__.' '. $pluginFilepath.'<br/>'.PHP_EOL;
		require($pluginFilepath);
		return $this;
	}
	
	/**
	 * Instance specific autoload() checks site specific paths.
	 */
	public function autoload($className)
	{
		$classFilename = str_replace('_', DIRECTORY_SEPARATOR, $className).'.php';
// echo __METHOD__.'(<b>'.$className.'</b>)#'.__LINE__.' '.$this->sitepath.$classFilename.'<br/>'.PHP_EOL;

		// Check app paths, first
		if ($this->sitepath && file_exists($this->sitepath . $classFilename)) 
		{
// echo __METHOD__.'(<b>'.$className.'</b>)#'.__LINE__.' '.self::$_sitepath.$classFilename.'<br/>'.PHP_EOL;
			include($this->sitepath . $classFilename);
		}
		else foreach ($this->classpath as $path)
		{
// echo __METHOD__.'(<b>'.$className.'</b>)#'.__LINE__.' '.$path.$classFilename.'<br/>'.PHP_EOL;
			if (file_exists($path . $classFilename)) 
			{
				include($path . $classFilename);
				return;
			}
		}
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
	
	function dispatch(ccRequest $request)
	{
		if (!$this->page->render($request))
			$this->show404($request);
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
	 * @param ccPageInterface|$string $page404 The object or classname that would
	 *              render a 404 page.
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
	 * Default cookie setting to URL Offset
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
		if ($expire === 0 && ($value === NULL || $value == ''))
			$expire = time()-3600;		// Delete cookie
		setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
	} // setCookie()
	
	/**
	 * Get/set app's disposition.
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
	 * Set the error handler.
	 * @param callback $function The name of the callback function or array, when
	 *                           the callback is a class or object method.
	 * The callback function should look like: 
	 *   handler ( int $errno , string $errstr [, 
	 *             string $errfile [, int $errline [, array $errcontext ]]] )
	 * @see http://www.php.net/manual/en/function.set-error-handler.php
	 * @todo Probably don't need this since it is chained to exceptions.
	 */
	function setErrorHandler($function)
	{
		set_error_handler($function);
		return $this;
	} // setErrorHandler()
	
	/**
	 * Set the exception handler.
	 * @param callback $function The name of the callback function or array, when
	 *                           the callback is a class or object method.
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
		return $this->page = $page;
	} // setMainPage()


	public function getPluginPath()
	{
		return $this->pluginpath;
	}
	public function setPluginPath($path)
	{
		if (substr($path,-1) != DIRECTORY_SEPARATOR)
			$path .= DIRECTORY_SEPARATOR;
		$this->pluginpath = $path;
		return $this;
	}
	
	/**
	 * Get/set server path to site's files (not the URL)
	 * @param string $path Full, absolute path (e.g., dirname(__FILE__) 
	 *                     of caller)
	 */
	public function getSitePath()
	{
//echo __METHOD__.'('.$className.')#'.__LINE__ .self::$_sitepath.PHP_EOL;
		return $this->sitepath;
	}
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
			ccApp::getApp()->showTrace($trace);		// Display stack.
		}
//		exit();
	} // on404()

	/**
	 * @todo Consider moving this to ccApp
	 * @todo Consider throwing exception (caveat, flow of control does not continue)
	 * @todo Add distinction between dev and production modes of output.
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
			self::showTrace($trace);		// Display stack.
//			die();
			return TRUE;
		}
		else
			return FALSE; 	// chain to normal error handler
	} // onError()
	
	/**
	 * @todo Consider moving this to ccApp
	 * @todo Add distinction between dev and production modes of output.
	 * @todo See php.net on tips for proper handling of this handler.
	 */
	static function onException($exception)
	{
		try
		{
			print get_class($exception).' '.$exception->getMessage().' in '.$exception->getFile().'#'.$exception->getLine().'<br/>'.PHP_EOL;
			self::showTrace($exception->getTrace());
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
	function redirect($url)
	{
		if (!headers_sent())
		{
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
	
	function show404(ccRequest $request)
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
	
	static function showTrace(Array $trace)
	{
//		array_shift($trace);	// Ignore this method.
// echo '<pre>';
// echo __METHOD__.' ';
// var_dump($trace);
		$entry = 1;
		foreach ($trace as $key => $line)
		{
			if (isset($line['file']) && isset($line['line']))
			{
				echo ($entry++).'. '.self::showTraceline($line).'<br/>';
				if (   $line['function'] == 'call_user_func'
					 || $line['function'] == 'call_user_func_array')
				{
					echo '&nbsp;&nbsp;&nbsp;&nbsp;'.self::showTraceline($trace[$key-1]).'<br/>';
				}
			}
		}
// echo '</pre>';
	} // showTrace()
	
	protected static function showTraceLine($line)
	{
// echo __METHOD__.' ';
// var_dump($line);
// if (is_string($line))
	// return $line.'<br/>';
		$out = '';
		if (isset($line['class']))
			$out .= '<b>'.$line['class'].'</b>';
		if (isset($line['type']))
			$out .= ($line['type'] == '->' ? '&rarr;' : $line['type']);
		$out .= '<b>'.$line['function'].'</b>(';
		$first = true;
		foreach ($line['args'] as $arg)
		{
			if (!$first)
				$out .= ',';
			else
				$first = false;
			$out .= '<tt>';
			if ($arg === NULL)
				$out .= 'null';
			elseif (is_object($arg))
				$out .= get_class($arg);
			elseif (is_string($arg))
				$out .= '</tt>&ldquo;<i>'.$arg.'</i>&rdquo;<tt>';
			elseif (is_array($arg))
			{
				if ((   $line['function'] == 'call_user_func'
					 || $line['function'] == 'call_user_func_array')
					&& count($arg) == 2)
				{
					$out .= get_class($arg[0]);
					if (is_object($arg[0]))
						$out .= '&rarr;';
					else 
						$out .= '::';
					$out .= $arg[1].'()</tt>,&hellip;';
					break;
				}
				else
				{
				$out .= 'Array(';
				$firstarg = true;
				foreach ($arg as $argkey => $argval)
				{
					if (!$firstarg)
						$out .= ',';
					else
						$firstarg = false;
					$out .= $argkey.'&rArr;';
					if ($argval === NULL)
						$out .= 'null';
					elseif (is_object($argval))
						$out .= get_class($argval);
					elseif (is_string($argval))
						$out .= '</tt>&ldquo;<i>'.$argval.'</i>&rdquo;<tt>';
					else
						$out .= $argval;
				}
				$out .= ')';
				}
			}
			else
				$out .= $arg;
			$out .= '</tt>';
		}
		$out .= ')';
		if ($line['file'])
			$out .= ' in <tt>'.dirname($line['file']).'/</tt><b>'.basename($line['file']).'</b>#'.$line['line'];
		// echo ') in <tt>'.dirname($line['file']).'/<b>'.basename($line['file']).'</b>#</tt>'.$line['line'].'<br/>';
// var_dump($line['args']);
		// echo '&nbsp;&nbsp;&nbsp;'.implode(',',$line['args']).'<br/>';
		return $out;
	} // showTraceLine()

} // class ccApp


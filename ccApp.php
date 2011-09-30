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
	const MODE_DEVELOPMENT = 0;
	const MODE_TESTING = 2;
	const MODE_STAGING = 4;
	const MODE_PRODUCTION = 6;

	protected static $_me=NULL;			// Singlton ref to $this
	protected static $_fwpath=NULL;		// Path to framework files.
	protected $dispatch=NULL;			// Singlton dispatcher object 

	protected $config=Array();			// Configuration array

	protected $_UrlOffset=NULL;
	protected $devMode = self::MODE_DEVELOPMENT; 
	protected static $_sitepath=NULL;	// Path to site specific files.
										// The following are rel to _sitepath:
	protected $_wwwpath='public';		// Path to visible files

	protected function __construct()
	{
//		echo __METHOD__ . PHP_EOL;
	}
	
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

	/**
	 * Add to PHP search path
	 * @param String $path Path component to add to search path.
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
	 * @todo This should not be necessary... and is inefficient because it should
	 *       only be called when it is necessary to be used. Perhaps we should 
	 *       add searching/loading to the autoload handling. 
	 */
	public function addPlugin($pluginFilepath)
	{
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
// echo __METHOD__.'(<b>'.$className.'</b>)#'.__LINE__.' '.self::$_sitepath.$classFilename.'<br/>'.PHP_EOL;

		// Check app paths, first
		if (self::$_sitepath && file_exists(self::$_sitepath . $classFilename)) 
		{
// echo __METHOD__.'(<b>'.$className.'</b>)#'.__LINE__.' '.self::$_sitepath.$classFilename.'<br/>'.PHP_EOL;
			include(self::$_sitepath . $classFilename);
		}		
	} // autoload()
	
	/**
	 * Create singleton instance of the app. 
	 * 
	 * @param string $className By default instance of ccApp is created, but 
	 *                          the name of a derived class can be instantiated.
	 * @todo Consider consolidating into getApp()
	 * @todo Consider allowing instance of ccApp to be passed. 
	 */
	public static function createApp($className=NULL)
	{
		return self::$_me = ($className ? new $className : new self);
	} // createApp()
	
	/**
	 * Create instance of the dispatch module, registered with the App. 
	 * 
	 * @param string $className By default instance of ccDispatch is created, but 
	 *                          the name of a derived class can be instantiated.
	 * @todo Consider consolidating into getDispatch()
	 * @todo Consider allowing passing an instance of ccDispatch in rather than 
	 *       only the classname.
	 */
	public function createDispatch($className=NULL)
	{
		return $this->dispatch = ($className ? new $className : new ccDispatch());
	} // createApp()

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
	 * @return object App singleton instance
	 */
	public static function getApp()
	{
		return self::$_me;
	} // getApp()

	/**
	 * @return object app's dispatch "controller"
	 */
	public function getDispatch()
	{
		return $this->dispatch;
	} // getApp()

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
	 * Get/set server path to site's files (not the URL)
	 * @param string $path Full, absolute path (e.g., dirname(__FILE__) 
	 *                     of caller)
	 */
	public function getSitePath()
	{
//echo __METHOD__.'('.$className.')#'.__LINE__ .self::$_sitepath.PHP_EOL;
		return self::$_sitepath;
	}
	public function setSitePath($path)
	{
		if (substr($path,-1) != DIRECTORY_SEPARATOR)
			$path .= DIRECTORY_SEPARATOR;

		self::$_sitepath = $path;
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

} // class ccApp


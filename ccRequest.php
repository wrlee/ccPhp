<?php
/**
 * @author Bill Lee <github@cachecrew.com>
 * @copyright (c) LGPLv3
 */
//!namespace ccPhp;

/*
 * 2010-10-23 Better handling of path parsing into components and document values
 *          - Easier to override by implementing parseUrl()
 * 2013-08-30 Added isAjax(), getRequestVars()
 * 2013-09-01 Added getFormat() [tentatively?]
 * 2017-12-07 Updated is*() logic.
 */

/**
 * This is a holding place for request specific properties. It can be thought of
 * as a "parameter block." This object (there is usually only one for any
 * iteration, i.e., request) is passed to the dispatcher and on to
 * filters (which can change this object) and then to controllers that process
 * on behalf of the request.
 *
 * The URL components are parsed into an array that can be retrieved via
 * getUrlPath(). The first element can be pulled out of the list via
 * shiftUrlPath().
 *
 * @todo Rather than rely on globals,use constructor's $URI value.
 * @todo Enable the isAjax() method to be easily overridden or augmented.
 * @todo Add Cookie functions (consider secure-only cookies)
 * @todo Add getRequestValues ($_GET+$_POST or $_POST-only, for secure, non-debug)
 */
class ccRequest implements \ArrayAccess, \IteratorAggregate
{
	protected $defaultDoc = 'index';	// Default document name
	protected $userAgentInfo;			// Array of client characteristics
	protected $components = NULL;		// Relative path parsed as an array
	protected $truename = NULL;			// Full document name (potentially)
	protected $format; 					// Data request type: html|json|xml|text
//	protected $isAjax;

	/**
	 * Since the dispatcher passes copies of this to each controller (to insulate
	 * the request object from any changes made by any controller from another),
	 * cloning needs to be supported.
	 */
	function __clone()
	{
		$this->components = $this->components;	// Deep copy (array assignments are copied).
//		parent::__clone();
	} // __clone()

	/**
	 * The constructor parses the "relative path" to the "resource".
	 *
	 * @param string $URI Create a request object
	 * @todo Fix: Set other values for this object, here based on $URI rather
	 *       than referring to late binding to globals.
	 */
	function __construct($URI=NULL)
	{
		// Set client properties based on UserAgent string
		$this->userAgentInfo = $this->parseUserAgent();
//		ccTrace::tr($this->userAgentInfo);
		// Set values based on path.
		$url = $URI ? $URI : $this->getUrl();
		$this->parseUrl($url);
/*		echo '<pre>';
		echo $url . PHP_EOL;
		print_r($this->components);
		echo '</pre>';
*/
	} // __construct()

	function getDefaultDocument()
	{
		return $this->defaultDoc;
	}

	public function getFormat()
	{
		return $this->format;
	}

	/**
	 * @returns string 'get'|'post'|'put'|'delete'
	 */
	function getHttpMethod()
	{
		return strtolower($_SERVER['REQUEST_METHOD']);
	}

	/**
	 * @todo Use REDIRECT_URL
	 * @todo Check that prefix of path matches UrlOffset before truncating it.
	 */
	function getRelativeUrl()
	{
		return strpos($_SERVER['SCRIPT_URL'],ccApp::getApp()->getUrlOffset()) === 0
		       ? substr($_SERVER['SCRIPT_URL'], strlen(ccApp::getApp()->getUrlOffset()))
			   : $_SERVER['SCRIPT_URL'];
	} // getRelativeUrl()

	/**
	 * Return the request parameters, a combination of $_GET and/or $_POST. In
	 * development mode, this is a combination of the two. In production mode,
	 * POST requests will only return $_POST.
	 * @return Array Associative array of request variables.
	 *
	 * @todo The set of values should be either $_POST or $_GET, dependent on
	 *       the request http method (get or post) and the development mode.
	 */
	function getRequestVars()
	{
		return $_POST+$_GET;
	}

	/**
	 * Get the part of the URL which points to the root of this app, i.e., the
	 * start of where this app resides.
	 * @return string The URI
	 * @see getUrlOffset()
	 * @todo Handle case where URL does not have a scheme
	 */
	function getRootUrl()
	{
		$path = $this->getUrl();

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

		return $path . substr(ccApp::getApp()->getUrlOffset(),1);
	} // getRootUrl()

	/**
	 * @returns Type of data to return (based on request), e.g., HTML, JSON, etc.
	 */
	function getType()
	{
		return $this->format;
	}

	/**
	 * @returns Full original request URL
	 * @todo Rebuild full scheme:server/path?querystring
	 */
	function getUrl()
	{
		$url =
		 isset($_SERVER['REDIRECT_SCRIPT_URI'])
			? $_SERVER['REDIRECT_SCRIPT_URI']
			: isset($_SERVER['SCRIPT_URI'])
			  ? $_SERVER['SCRIPT_URI']
			  : $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].($_SERVER['SERVER_PORT'] != 80 ? ':'.$_SERVER['SERVER_PORT'] : '').preg_replace('/\?.*$/','',$_SERVER['REQUEST_URI']);
// echo __METHOD__.'#'.__LINE__.' "'.$url.'"<br/>';
		return $url;
	} // getUrl()

	function getUrlPort()
	{
		return $_SERVER['SERVER_PORT'];
	} // getUrlPort()

	function getQueryString()
	{
		return $_SERVER['QUERY_STRING'];
	}

	/**
	 * @returns The full filename (including extension) of the last path
	 *          component.
	 */
	function getTrueFilename()
	{
		return $this->truename;
	}

	/**
	 * @returns string 'http'|'https' (and, in theory, a bunch of other schemes)
	 */
	function getUrlScheme()
	{
		$scheme = isset($_SERVER['REQUEST_SCHEME'])
					? $_SERVER['REQUEST_SCHEME']
					: substr($_SERVER['SCRIPT_URI'],0,strpos($_SERVER['SCRIPT_URI'],':'));
//		$scheme = strstr($_SERVER['SCRIPT_URI'],':',true); // PHP 5.3+
		return $scheme;
	} // getUrlScheme()

	/**
	 * Get array representation of URL components
	 * @return array URL components; each component, the part delimited by '/'
	 */
	function getUrlPath()
	{
		return $this->components;
	}
	/**
	 * Return last (rightmost) component of URL and remove it from list.
	 */
	function popUrlPath()
	{
		if ($this->components)				// If array not empty
		{
			$rval = array_pop($this->components);
		}
		else
			$rval = NULL;
		return $rval;
	} // popUrlPath()
	/**
	 * Shift the component list "left", deleting the first component and
	 * returning that component.
	 * @return string The first component of the URL.
	 */
	function shiftUrlPath()
	{
		if ($this->components)				// If array not empty
		{
			$rval = array_shift($this->components);
		}
		else
			$rval = NULL;
		return $rval;
	} // shiftUrlPath()

	/**
	 * Get Usage-Agent string.
	 * @return string Usage-Agent string
	 */
	function getUserAgent()
	{
		return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	}
	function getUserAgentInfo()
	{
		return $this->userAgentInfo;
	}

	/**
	 * Is the current request an AJAX request? This may not be reliable.
	 * @return boolean [description]
	 */
	function isAjax()
	{
		// Windows: Firefox 20, IE 7-10, Safari 5.1
		if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest')
			return false;
		else
			return true;
	}
	/**
	 * Is request/connection from Internet Explorer?
	 * @return bool
	 */
	function isIE()
	{
//		ccTrace::tr($this->userAgentInfo);
		return isset($this->userAgentInfo['browser'])
				 ? $this->userAgentInfo['browser'] == 'IE'
				 	? $this->userAgentInfo['version']
					: false
			    : isset($this->userAgentInfo['Browser'])
				 	? $this->userAgentInfo['Browser'] == 'IE'
					  ? $this->userAgentInfo['version']
					  : false;
	}

	/**
	 * Is request/connection from mobile device?
	 * @return bool
	 */
	function isMobile()
	{
		return isset($this->userAgentInfo['ismobiledevice'])
				 ? $this->userAgentInfo['ismobiledevice']
				 : (isset($this->userAgentInfo['isMobileDevice']) && $this->userAgentInfo['isMobileDevice']);
	}
	/**
	 * Is request/connection from mobile device?
	 * @return bool
	 */
	function isTablet()
	{
		return isset($this->userAgentInfo['istablet'])
				 ? $this->userAgentInfo['istablet']
			    : (isset($this->userAgentInfo['isTablet']) && $this->userAgentInfo['isTablet']);
	}
	/**
	 * Is request/connection from iOS?
	 * @return bool
	 */
	function isiOS()
	{
		return isset($this->userAgentInfo['platform'])
				 ? ($this->userAgentInfo['platform'] == 'iOS')
				 : (isset($this->userAgentInfo['Platform']) && $this->userAgentInfo['Platform'] == 'iOS');
	}
	/**
	 * Is request/connection from iPad?
	 * @return bool
	 */
	function isiPad()
	{
		return $this->isiOS() && $this->isTablet();
	}
	/**
	 * Is request/connection from iPhone?
	 * @return bool
	 */
	function isiPhone()
	{
		return $this->isiOS() && ! $this->isTablet();
	}
	/**
	 * Is request/connection SSL?
	 * @return bool
	 */
	function isSecure()
	{
		return (isset($_SERVER['HTTPS']) &&  $_SERVER['HTTPS'] == 'on');
//		return $this->getUrlScheme() == 'https';
	}

	/**
	 * Mimics browscap and get_browser() functionality when get_browser() is not
	 * supported.
	 *
	 * @todo Check that get_browser() won't work before doing our own processing.
	 * @todo Pass .ini file in as a parameter.
	 * @todo Look for .ini in site path instead of framework path.
	 */
	protected function parseUserAgent()
	{
		$err = error_reporting(E_ERROR | E_PARSE);	// Turn off potential warnings
			$ini = ini_get( 'browscap' );			// generated by this statement
		error_reporting($err);						// Then restore error settings
		if ($ini) {
//			ccTrace::tr($ini);
			return get_browser(NULL, TRUE);
		}
		else {

		$sessActive = (session_status() == PHP_SESSION_ACTIVE);
	    $agent = $this->getUserAgent();
//	    ccTrace::tr($agent);

	    if (!$sessActive)				// If session support not running
	    	session_start();			//   turn on to presist browser info

	    if ( isset($_SESSION[$agent]) )	// If already cached, return info
	    	return unserialize($_SESSION[$agent]);

		if (ini_get('browscap'))		// If there's built-in support
		{								//  call it.
			ini_set('browscap',ccApp::getApp()->getFrameworkPath().'lite_php_browscap.ini');
			$hu = get_browser();
		}
		else 							// Simulate get_browser() call
		{
			$yu=array();
			$q_s=array("#\.#","#\*#","#\?#");
			$q_r=array("\.",".*",".?");
			if (defined('INI_SCANNER_RAW'))
				$brows=parse_ini_file(ccApp::getApp()->getFrameworkPath().'full_php_browscap.ini',true,INI_SCANNER_RAW);
			else
				$brows=parse_ini_file(ccApp::getApp()->getFrameworkPath().'full_php_browscap.ini',true);
			foreach($brows as $k=>$t){
				if(fnmatch($k,$agent)) {
					$yu['browser_name_pattern']=$k;
					$pat=preg_replace($q_s,$q_r,$k);
					$yu['browser_name_regex']=strtolower("^$pat$");
					foreach($brows as $g=>$r) {
						if(isset($t['Parent']) && $t['Parent']==$g) {
							foreach($brows as $a=>$b) {
								if($r['Parent']==$a) {
									$yu=array_merge($yu,$b,$r,$t);
									foreach($yu as $d=>$z) {
										$l=strtolower($d);
										$hu[$l]=$z;
									}
								}
							}
						}
					}
					break;
				}
			}
		}
		if (isset($hu)) {
			$_SESSION[$agent] = serialize($hu);
			if ( !$sessActive )			// Session wasn't running
				session_commit();		//   So turn back off
			return $hu;					// Return browser info array
		}
		else
			return [];

		} // else get_browser() not avail
	} // parseUserAgent()

	/**
	 * Find length of string of initial matching characters.
	 * @param string $string1 First string to match
	 * @param string $string1 Second string to match
	 * @return offset to beginning of non-matching part of strings
	 */
	private static function len_of_common_initial($string1, $string2) {
		$i = -1;
		for ($i=0; $i < strlen($string1) && $i < strlen($string2) && $string1[$i] == $string2[$i]; $i++);
		// return ($i < strlen($string1) && $i < strlen($string2)) ? $i : -1;
		return $i;
	}
	/**
	 * Overridable callback to parse the URL and set the components
	 * and format values.
	 * @param string $url The URL to decode.
	 * @todo Remove successive '/'s (remove blank entries in component array)
	 * @todo Untangle REDIRECT_URL hack. This accommodates .htaccess UrlRewrites
	 */
	protected function parseUrl($url)
	{
		$this->inferred = FALSE;					// Assume concrete path spec

		$components = parse_url($url);				// High level parse
// ccApp::tr($components);

		// We want to preserve the full path (if explicitly specified) and
		// assume that a "bare" path is really a path. But, pathinfo() does not
		// distinguish between paths trailing '/' or not, so a bit of hacking is
		// required:
/*		if (substr($components['path'],-1) == '/')	// This is an explicit path
		{											// So, force pathinfo()
			$components['path'] .= $this->defaultDoc;// to preserve path by adding
			$pathinfo = pathinfo($components['path']);// a document component.
			$this->inferred = TRUE;					// Flag as "doc assumed"
		}
		else										// Dunno if it's a path:
		{
			$pathinfo = pathinfo($components['path']);
			if (!isset($pathinfo['extension']))		// If no extension is spec'd
			{										// Assume default doc.
				$components['path'] .= '/' . $this->defaultDoc;
				$this->inferred = TRUE;				// Flag as "doc assumed"
			}
			$pathinfo = pathinfo($components['path']);
		}
*/
		$pathinfo = pathinfo(isset($_SERVER['REDIRECT_URL'])	// WRL HACK! Circumvents
			? $_SERVER['REDIRECT_URL'] 							// $url setting
			: $components['path']);

		$offset = self::len_of_common_initial($_SERVER['SCRIPT_NAME'],$_SERVER['REQUEST_URI']);
		$this->components = $offset == -1 ? '/' : substr($_SERVER['REQUEST_URI'], $offset );
// echo __FILE__.'#'.__LINE__.PHP_EOL;
// echo '<pre>';
// var_dump($offset);
// var_dump($_SERVER['REQUEST_URI']);
// var_dump($_SERVER['SCRIPT_NAME']);
// var_dump($this->components);
// var_dump(explode('/',$this->components));
		$this->components = array_values(array_filter(explode('/',$this->components)));
// var_dump($this->components);
// print_r($_SERVER);
		$path = $pathinfo['dirname'].'/'.$pathinfo['filename'];
		$this->truename = $pathinfo['basename'];
		$path = substr($path, strlen(ccApp::getApp()->getUrlOffset()));
//		var_dump($path);
// echo '</pre>';

//		if (count($this->components) > 1 && !$this->components[0])	// If leading '/' causes empty 1st entry
//			array_shift($this->components);							//   ignore it.

								// Determine format requested
		if (isset($pathinfo['extension']))
			$this->format = strtolower($pathinfo['extension']);
//		elseif (isset($_SERVER['CONTENT_TYPE']))
		elseif (isset($_SERVER['HTTP_ACCEPT']))
		{
			$accepting = explode(',',$_SERVER['HTTP_ACCEPT']);
			$accepting = explode('/',$accepting[0]);
			$this->format = end($accepting);
		}
		else
			$this->format = '';
		switch ($this->format)	// Normalize document format request type
		{
		case 'xhtml+xml':
			$this->format = 'xhtml';
			break;
		case 'csv':
		case 'xhtml':
		case 'text':
		case 'json':
		case 'xml':
			break;				// Nothing to do, already set.
		case 'txt':				// txt == text
			$this->format = 'text';
			break;
		default:				// All others default to HTML
			$this->format = 'html';
		}
	} // parseUrl()

	/**
	 * @var Synthetic instance properties.
	 */
	protected $properties = Array();
	/**
	 * "Serialize" to an array. This is particluarly useful for passing to
	 * template processors.
	 * @returns Array array of values representing current state.
	 */
	protected function initProperties()
	{
		$this->properties['defaultDocument'] = $this->getDefaultDocument();
		$this->properties['isSecure'] = $this->isSecure() ? 1 : 0;
		$this->properties['isiPhone'] = $this->isiPhone() ? 1 : 0;
		$this->properties['isiPad'] = $this->isiPad() ? 1 : 0;
		$this->properties['isiOS'] = $this->isiOS() ? 1 : 0;
		$this->properties['isIE'] = $this->isIE() ? 1 : 0;
		$this->properties['isAjax'] = $this->isAjax() ? 1 : 0;
		$this->properties['userAgent'] = $this->getUserAgent();
		$this->properties['root'] = ccApp::getApp()->getUrlOffset();
		$this->properties['scheme'] = $this->getUrlScheme();
		$this->properties['port'] = $this->getUrlPort();
//		$this->properties['relativeUrl'] = $this->getRelativeUrl();
		$this->properties['queryString'] = $this->getQueryString();
		$this->properties['truename'] = $this->getTrueFilename();
		$this->properties['type'] = $this->getType();
		$this->properties['method'] = $this->getHttpMethod();
		$this->properties['url_path'] = implode(DIRECTORY_SEPARATOR, $this->getUrlPath());
	} // initProperties()

	/***************************************************************************
	 * ArrayAccess, IteratorAggregate interface implementation
	 */
 	/**
 	 * Return whether element exists at... Satisfy interface requirements
 	 * @param $offset Index offset
	 * @return bool Element exists?
 	 */
	public function offsetExists( $offset )
	{
		if (!$this->properties)
			$this->initProperties();
		return isset($this->properties[$offset]) || isset($this->userAgentInfo[$offset]);
	}
	/**
	 * Return element at... Satisfy interface requirements
	 * @param $offset Index offset
	 * @return element
	 */
	public function offsetGet( $offset )
	{
		if (!$this->properties)
			$this->initProperties();
		return isset($this->properties[$offset])
			? $this->properties[$offset]
			: $this->userAgentInfo[$offset];
	}
	/**
	 * Satisfy interface requirements (unused)
	 * @param $offset Element offset
	 * @param $value value to set at element
	 */
	public function offsetSet( $offset, $value ) { }
	/**
	 * Satisfy interface requirements (unused)
	 * @param $offset Element offset
	 */
	public function offsetUnset( $offset ) { }
	/**
	 * Return iterator to satisfy interface requirements.
	 */
   public function getIterator()
	{
		if (!$this->properties)
			$this->initProperties();
        return new ArrayIterator($this->properties+$this->userAgentInfo);
    }
} // class ccRequest

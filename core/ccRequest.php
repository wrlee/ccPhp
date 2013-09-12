<?php 
/**
 *
 * @package ccPhp
 */
/*
 * 2010-10-23 Better handling of path parsing into components and document values
 *          - Easier to override by implementing parseUrl()
 * 2013-08-30 Added isAjax(), getRequestVars()
 * 2013-09-01 Added getFormat() [tentatively?]
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
 * @package ccPhp
 * @todo Rather than rely on globals,use constructor's $URI value.
 * @todo Enable the isAjax() method to be easily overridden or augmented.
 * @todo Add Cookie functions (consider secure-only cookies)
 * @todo Add getRequestValues ($_GET+$_POST or $_POST-only, for secure, non-debug)
 */
class ccRequest implements ArrayAccess, IteratorAggregate 
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
		$url = $URI ? $URI : isset($_SERVER['REDIRECT_SCRIPT_URI']) 
			? $_SERVER['REDIRECT_SCRIPT_URI']
			: $_SERVER['SCRIPT_URI'];
		$this->parseUrl($url);
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
		return isset($_SERVER['REDIRECT_SCRIPT_URI']) 
			? $_SERVER['REDIRECT_SCRIPT_URI']
			: $_SERVER['SCRIPT_URI'];
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
		$scheme = substr($_SERVER['SCRIPT_URI'],0,strpos($_SERVER['SCRIPT_URI'],':'));
//		$scheme = strstr($_SERVER['SCRIPT_URI'],':',true); // PHP 5.3+
		return $scheme;
	} // getUrlScheme()

	/**
	 * @returns array URL components; each component, the part delimited by '/'
	 */
	function getUrlPath()
	{
		return $this->components;
	}
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
	 * @returns string The first component of the URL.
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
	function isIE()
	{
		return $this->userAgentInfo['Browser'] == 'IE';
	}

	function isMobile()
	{
		return $this->userAgentInfo['isMobileDevice'];
	}
	function isiOS()
	{
		return $this->userAgentInfo['Platform'] == 'iPhone OSX' 
			|| $this->isiPhone() 
			|| $this->isiPad();
	}
	function isiPad()
	{
		return $this->userAgentInfo['Browser'] == 'iPad';
	}
	function isiPhone()
	{
		return $this->userAgentInfo['Browser'] == 'iPhone';
	}
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
						if($t['Parent']==$g) {
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
    	$_SESSION[$agent] = serialize($hu);
	    if ( !$sessActive )			// Session wasn't running
	    	session_commit();		//   So turn back off
		return $hu;					// Return browser info array
	} // parseUserAgent()

	/**
	 * Overridable callback to parse the URL and set the components
	 * and format values.
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
		$path = $pathinfo['dirname'].'/'.$pathinfo['filename'];
		$this->truename = $pathinfo['basename'];
		$path = substr($path, strlen(ccApp::getApp()->getUrlOffset()));

		$this->components = explode('/',$path);

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
	 * "Serialize" to an array. This is particluarly useful for passing to 
	 * template processors.
	 * @returns Array array of values representing current state.
	 */
	protected $properties = Array();
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
	} // initProperties()
	
	/***************************************************************************
	 * ArrayAccess, IteratorAggregate interface implementation
	 */
	public function offsetExists( $offset )
	{
		if (!$this->properties)
			$this->initProperties();
		return isset($this->properties[$offset]) || isset($this->userAgentInfo[$offset]);
	}
	public function offsetGet( $offset )
	{
		if (!$this->properties)
			$this->initProperties();
		return isset($this->properties[$offset]) 
			? $this->properties[$offset] 
			: $this->userAgentInfo[$offset];
	}
	public function offsetSet( $offset, $value )
	{
	}
	public function offsetUnset( $offset )
	{
	}
    public function getIterator() 
	{
		if (!$this->properties)
			$this->initProperties();
        return new ArrayIterator($this->properties+$this->userAgentInfo);
    }
} // class ccRequest

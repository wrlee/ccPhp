<?php 

/**
 * This is a holding place for request specific properties. It can be thought of
 * as a "parameter block." This object (there is usually only one for any 
 * iteration, i.e., request) is passed to the dispatcher and on to 
 * filters (which can change this object) and then to controllers that process
 * on behalf of the request.
 *
 * The URL components are parsed into an array that can be retrieved via
 * getUrlComponents(). The first element can be pulled out of the list via 
 * shiftUrlComponents(). 
 *
 * @todo Move defaultDocName to ccApp
 */
class ccRequest implements ArrayAccess, IteratorAggregate 
{
	protected $userAgentInfo;
	protected $components = NULL;
	protected $document = '';
	protected $docInferred = TRUE;
	protected $format; 					// Data request type: html|json|xml|text
//	protected $inferredDoc = 'index';
//	protected $isAjax;
//	protected $isRestful;
	
	/**
	 * Since the dispatcher passes copies of this to each controller (to insulate
	 * the request object from any changes made by any controller from another),
	 * cloning needs to be supported. 
	 */
	function __clone()
	{
		$this->components = $this->components;	// Deep copy.
//		parent::__clone();
	} // __clone()

	/**
	 * The constructor parses the "relative path" to the "resource". 
	 * 
	 * @param string $URI Create a request object 
	 * @param string $defaultDocName If path ends with '/' then this doc name 
	 *                    is assumed. If NULL or '', then the doc name is left
	 *                    as ''. Otherwise the docname is set AND in any event
	 *                    the extension is stripped from the docname. 
	 * @see getUrlDocument()
	 *
	 * @todo Fix: Set other values for this object, here based on $URI rather 
	 *       than referring to late binding to globals. 
	 */
	function __construct($URI=NULL,$defaultDocName='index')
	{
		$path = $URI ? $URI : $_SERVER['SCRIPT_URL'];
		$path = substr($path, strlen(ccApp::getApp()->getUrlOffset()));
		$this->components = explode('/',$path);
		$this->document = array_pop($this->components);
		if ($defaultDocName)	// If doc not specified, use default
		{
			$this->inferred = ($this->document === '');
			if ($this->inferred)
				$this->document = $defaultDocName;
		}
		else
			$this->inferred = FALSE;
								// Determine format requested
		$path = explode('.',$this->document);
		if (count($path) > 1)
			$this->format = strtolower(array_pop($path));
			
		switch ($this->format)	// Normalize document format request type
		{
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
		
		if ($defaultDocName)	// If we are auto-handling doc name
		{						// Reassemble doc name w/o extension (i.e., type)
			$this->document = implode('.',$path);
		}
		
		$this->userAgentInfo = $this->parseUserAgent();
// echo '<pre>';
// print_r($this->components);
	} // __construct()
	
	/**
	 * @returns string 'get'|'post'|'put'|'delete'
	 */
	function getHttpMethod() 
	{
		return strtolower($_SERVER['REQUEST_METHOD']);
	}
	
	function getRelativeUrl()
	{
		return strpos($_SERVER['SCRIPT_URL'],ccApp::getApp()->getUrlOffset()) === 0
		       ? substr($_SERVER['SCRIPT_URL'], strlen(ccApp::getApp()->getUrlOffset()))
			   : $_SERVER['SCRIPT_URL'];
	} // getRelativeUrl()
	
	function getType()
	{
		return $this->format;
	}

	function getUrlDocument() 
	{	
		return $this->document;
	} // getUrlDocument()
	
	function getUrlPort() 
	{	
		return $_SERVER['SERVER_PORT'];
	} // getUrlPort()
	
	function getQueryString()
	{
		return $_SERVER['QUERY_STRING'];
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
	function getUrlComponents()
	{
		return $this->components;
	}
	function popUrlComponent()
	{
		if ($this->components)				// If array not empty
		{
			$rval = array_pop($this->components);
		}
		else
			$rval = NULL;
		return $rval;
	} // popUrlComponent()
	/**
	 * Shift the component list "left", deleting the first component and 
	 * returning that component.
	 * @returns string The first component of the URL.
	 */
	function shiftUrlComponent()
	{
		if ($this->components)				// If array not empty
		{
			$rval = array_shift($this->components);
		}
		else
			$rval = NULL;
		return $rval;
	} // shiftUrlComponent()
	
	function getUserAgent()
	{
		return $_SERVER['HTTP_USER_AGENT'];
	}
	function getUserAgentInfo()
	{
		return $this->userAgentInfo;
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
	function parseUserAgent()
	{
// 		ini_set('browscap',$app->getFrameworkPath().'lite_php_browscap.ini');
// 		return get_browser();

		$browsedb = (parse_ini_file(ccApp::getApp()->getFrameworkPath().'lite_php_browscap.ini',TRUE,INI_SCANNER_RAW));
		foreach ($browsedb as $template => $prop)
		{
// echo $template.PHP_EOL;
			$pattern = preg_replace(array('/([\(\)\+\.])/','/([\?\*])/'),array('\\\\${1}','.${1}'),$template);
			if (preg_match('%'.$pattern.'%',$this->getUserAgent()))
			{
				$p2 = $prop;
				while (isset($prop['Parent']))
				{
// echo $prop['Parent'].PHP_EOL;
					$prop = $browsedb[$prop['Parent']];
					$p2 += $prop;
				}
// var_dump($p2);
				return $p2;
			}
		}
	} // parseUserAgent()
	
	/**
	 * "Serialize" to an array. This is particluarly useful for passing to 
	 * template processors.
	 * @returns Array array of values representing current state.
	 */
	protected $properties = Array();
	protected function initProperties()
	{
		$this->properties['isSecure'] = $this->isSecure() ? 1 : 0;
		$this->properties['isiPhone'] = $this->isiPhone() ? 1 : 0;
		$this->properties['isiPad'] = $this->isiPad() ? 1 : 0;
		$this->properties['isiOS'] = $this->isiOS() ? 1 : 0;
		$this->properties['isIE'] = $this->isIE() ? 1 : 0;
		$this->properties['userAgent'] = $this->getUserAgent();
		$this->properties['scheme'] = $this->getUrlScheme();
		$this->properties['port'] = $this->getUrlPort();
		$this->properties['relativeUrl'] = $this->getRelativeUrl();
		$this->properties['queryString'] = $this->getQueryString();
		$this->properties['docName'] = $this->getUrlDocument();
		$this->properties['type'] = $this->getType();
		$this->properties['method'] = $this->getHttpMethod();
		$this->properties['SCRIPT_URI'] = $_SERVER['SCRIPT_URI'];
		$this->properties['QUERY_STRING'] = $_SERVER['QUERY_STRING'];
	}
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

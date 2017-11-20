<?php
//!namespace ccPhp;

// use ccPhp\ccController;
//! use ccPhp\core\ccRequest;

/**
 * File: ccSimpleController
 * Simply dispatches to a method based on the first component of the URL...
 * no sub-path checking, etc., but if there is no component, it assumes a default
 * method called index.
 *
 * @package ccPhp
 */
// 2011-10-24 Call begin() only if method will be rendered.
// 2011-11-15 Call notfound() if no matching methods are found.
abstract class ccSimpleController
	extends ccController
{
	/**
	 * @var string If no method matches the next component of the URL and this is set,
	 * 	  it is invoked. It can preempt the default 404 handling.
	 */
	private $defaultHandler=NULL;
	/**
	 * @var bool If true, automatically calling begin()/after() is skipped and left
	 *		  to the defaultHandler to invoke, if necessary. This allows the handler
	 *		  to exit, false, if desired.
	 */
	private $defaultHandlerSuppressAuto=FALSE;
	/**
	 * If set then assume it's a URL offset. If the left-most path component matches,
	 * then this object is given control. The next path component is then matched
	 * against any actions in this class.
	 *
	 * If not set, then this
	 */
	protected $base;
	/**
	 * Default implementation is to call, first, a common 'begin()' method
	 * before every method.
	 *
	 * @returns bool|NULL Successfully handled? NULL: method not found.
	 *
	 * @todo Consider an "end()" call.
	 * @todo Consider an "on404()" call for local 404 handling.
	 */
	function render(ccRequest $request)
	{
		if ($this->base)	// If a "base" for this controller is set,
		{					//    then assume it's an offset and "eat" it.
			$action = $request->shiftUrlPath();
			if (!$action || strtolower($action) != $this->base)
				return FALSE;	// Path doesn't reflect the offset so, "no match"
		}
		// If default page is to be used, then this should look/act like a path
		// with a trailing '/', so redirect to proper page so client treats
		// relative hrefs properly.
		if ( count($request->getUrlPath()) == 0 		// Maps to default page?
			 && substr($request->getUrl(),-1) != '/' )	// So ensure '/' suffix
			ccApp::getApp()->redirect($request->getUrl().'/'.($request->getQueryString() ? '?'.$request->getQueryString() : ''),301);

		$method = $this->getMethodFromRequest($request);

		if (!$method && !$this->defaultHandlerSuppressAuto)
			$method = $this->defaultHandler;

		if ( $method )
		{
			if (method_exists($this,'begin'))
				if (!call_user_func(array($this,'begin'), $request))
					return FALSE;

			$rc = call_user_func(array($this,$method),$request);

			if ($rc && method_exists($this,'after'))
				call_user_func(array($this,'after'), $request);
			return $rc;
		}
		// In the default case, handler is reponsibile for calling begin/after
		elseif ($this->defaultHandler)
		{
			return call_user_func(array($this,$this->defaultHandler),$request);
		}
		else
			return NULL;		// Method not found.
	} // render()

	/**
	 * Set a default handler when action-specific method name does not exist.
	 * This allows the class to take control when method does not match the
	 * rules according to getMethodFromRequest(), rather than lead to a 404.
	 * Still, the handler might need to determine whether to process or not and
	 * may need to circumvent begin()/after() processing; so, supporessing those
	 * automatic invocations can be suppressed. Then it is up to the default
	 * handler to make those calls, if desired.
	 * @param string $method A method name to invoke when nothing matches a URL component.
	 * @param bool $suppressAuto Suppress execution of begin()/after() when
	 *						default case is to be invoked. This allows the default
	 *						handler to determine whether to process (by returning false)
	 *						rather than assuming it will do something useful.
	 * @todo Allow $method to be actual function/method (rather than string)
	 */
	protected function setDefaultHandler($method, $suppressAuto=FALSE)
	{
		if (method_exists($this,$method)) {
			$this->defaultHandler = $method;
			$this->defaultHandlerSuppressAuto = $suppressAuto;
		}
	} // setDefaultHandler
} // class ccSimpleController

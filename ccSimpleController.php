<?php 

/** File: ccSimpleController
 * Simply dispatches to a method based on the first component of the URL...
 * no sub-path checking, etc., but if there is no component, it assumes a default
 * method called index.
 *
 * @package ccPhp\PageController
 */
// 2011-10-24 Call begin() only if method will be rendered.
// 2011-11-15 Call notfound() if no matching methods are found.
class ccSimpleController extends ccController
{
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
		if ( $method )
		{
			if (method_exists($this,'begin'))
				if (!call_user_func(array($this,'begin'), $request))
					return FALSE;
					
			return call_user_func(array($this,$method),$request);
		}
		elseif (method_exists($this,'notfound'))
		{
			if (method_exists($this,'begin'))
				if (!call_user_func(array($this,'begin'), $request))
					return FALSE;
					
			return call_user_func(array($this,'notfound'), $request);
		}
		else
			return NULL;		// Method not found.
	} // render()
} // class ccSimpleController
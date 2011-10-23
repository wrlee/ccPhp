<?php 

/**
 * Simply dispatches to a method based on the first component of the URL...
 * no sub-path checking, etc., but if there is no component, it assumes a default
 * method called index.
 */
class ccSimpleController extends ccController
{
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
//		ccApp::tr('testing...');
		if (method_exists($this, 'begin'))
			if (!call_user_func(array($this,'begin'), $request))
				return FALSE;
				
		$method = $this->getMethodFromRequest($request);
		if ( $method )
			return call_user_func(array($this,$method),$request);
		else
			return NULL;		// Method not found.
	} // render()
} // class ccSimpleController
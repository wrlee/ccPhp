<?php 
/**
 *
 * @package ccPhp\PageController
 */

/**
 * Classes based on this class render implement page rendering in methods,
 * consolidating the handling for serveral pages in one "controller" file.
 *
 * The render() method can use the getMethodFromRequest() to 
 *
 *
 * @param ccRequest $request a request block representing the current URI
 * @return BOOL True if handled and no subsequent controller need handle req't
 *              False, not handled, next controller in list will handle it.
 * @package ccPhp\PageController
 */
abstract class ccController 
	implements ccPageInterface
{
	/**
	 * Map request to method name. 
	 * If a public method matching the next URL 
	 * component, exists in this object, then its name (the method's name) is 
	 * returned. 
	 *
	 * function render($request) {
	 *  $method = $this->getMethodFromRequest($request);
	 *  if ($method)
	 *		return call_user_func(array($this,$method),$request);
	 *	else
	 *		return FALSE;
	 * }
	 *
	 * This can be overridden to implement a different algorithm. 
	 */
	protected function getMethodFromRequest(ccRequest $request)
	{
		$action = $request->getUrlPath();			// Base method on first part
													//   of path.
		if (!isset($action[0]) || !$action[0])		// If no path portion
			$action = $request->getDefaultDocument();	//   use "default" name
		else
			$action = $action[0];					// Just keep the 1st component

		$valid_count = strspn($action,'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890');		
		if ($valid_count < strlen($action))
		{
			$request->extra = substr($action,$valid_count);	// WRL HACK!!
			$action = substr($action,0,$valid_count);
		}
													// Check that method exists
		if (method_exists( $this, $action ))		// If method exists
		{											//   check that it's public
			$refl = new ReflectionMethod($this, $action);
			if ($refl->isPublic()) 					// Handlers must be public
			{
				$request->shiftUrlPath();			// Swallow parsed component
				return $action;						// Rtn name of public handler
			}
			else
				return NULL;						// No public method to handle
		}
		else
			return NULL;							// Method does not exist
	} // getMethodFromRequest()

} // class ccController
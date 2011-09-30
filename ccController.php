<?php 


/**
 * Classes based on this class render implement page rendering in methods, consolidating the 
 * handling for serveral pages in one "controller" file.
 *
 * @param ccRequest $request a request block representing the current URI
 * @return BOOL True if handled and no subsequent controller need handle req't
 *              False, not handled, next controller in list will handle it.
 */
abstract class ccController implements ccPageInterface
{
//	abstract function render(ccRequest $request); 
	
	/**
	 * Map the "action" name to a method name. This method can be overridden to 
	 * implement different action-name to method mapping. 
	 * @param string $action Action name to map to a method.
	 * @returns string Callable method name or NULL if method does not exist. 
	 */
	function findMethodName($action)
	{
		if ($action)
			$methodName = strtoupper($action[0]).strtolower(substr($action,1));
		else
			$action = 'Index';
		return method_exists( $this, $methodName ) ? $methodName : NULL;
	} // findMethodName()
	
	/**
	 * Maps the given action name to a method and calls that method.  
	 * @param string $action The name of the action for which a method should be
	 *                    invoked.
	 * @returns BOOL|NULL Returns the BOOL return value of the method or NULL, 
	 *                    if the method is not found. Be sure to use === when
	 *                    checking the return value of this method.
	 */
	function invokeAction($action, ccRequest $request)
	{
// echo __METHOD__.'#'.__LINE__.' '.$action . ' -->'.$this->findMethodName($action).'<br/>';
		$methodName = $this->findMethodName($action);
		if ( $methodName )
		{
// echo __METHOD__.'#'.__LINE__.' '.$action . ' -->'.$this->findMethodName($action).'<br/>';
//			return $this->initPage($request) &&
			return call_user_func(array($this,$methodName),$request);
		}
		else
			return NULL;

	} // invokeAction()
} // class ccController
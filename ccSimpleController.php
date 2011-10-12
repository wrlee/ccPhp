<?php 

/**
 * Simply dispatches to a method based on the first component of the URL...
 * no sub-path checking, etc., but if there is no component, it assumes a default
 * method called index.
 */
class ccSimpleController extends ccController
{
	function render(ccRequest $request)
	{
		$action = $request->shiftUrlComponent();
		if (!$action || $action == '')
			$action = $request->getUrlDocument();
// echo '<pre>';
// echo implode(',',$request->getUrlComponents()).PHP_EOL;
// echo $action.PHP_EOL;

//		if ( method_exists( $this, $action) )
//		{
///			return $this->initPage($request) &&
//			return call_user_func(array($this,$action),$request);
//		}	
//		else
		{
		$rv = $this->invokeAction($action, $request);
// echo __METHOD__.'#'.__LINE__.' '.$rv.'<br/>';
		if ( $rv === NULL )
		{
//			trigger_error('Action method '.get_class($this).'::'.$action.' does not exist.',E_USER_NOTICE);
			return $this->handle404($request);
		}
		else
			return $rv;
		}
	} // handleRequest()

	/**
	 * Consider a default 404 handler that would, by default, do nothing; but
	 * provides a hook for the controller to easily provide a catch all for 
	 * its class of handling, before returning to dispatcher.
	 */
	function handle404(ccRequest $request)
	{
		return FALSE;
	}
} // class ccSimpleController
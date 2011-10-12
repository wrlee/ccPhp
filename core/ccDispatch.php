<?php 

/**
 * @todo Move error handling ccError class and refer through ccApp
 */
class ccDispatch implements ccPageInterface
{
	protected $filterChainPrefix = Array();
	protected $controllerChain = Array();
	protected $controllerChainSuffix = Array();
	
	// function __construct()
	// {
	// }
	
	/**
	 * Add controller to handle dispatches of requests.
	 * @param ccController|string $controller Controller object or name of class.
	 *        If string, it is instantiated at invocation time. 
	 */
	function addPageRenderer($controller)
	{
		$this->controllerChain[] = $controller;
		if (!is_string($controller) && !($controller instanceof ccPageInterface))
			throw ErrrorException(get_class($controller).' rendering object needs to implement ccPageInterface.');
		return $this;
	} // function addPageRenderer()
	
	/**
	 * Add filter to modify request object.
	 * @param ccFilter $filter Filter object
	 */
	function addFilter(ccDispatchFilter $filter, $suffix=FALSE)
	{
		if ($suffix)
			$this->filterChainSuffix[] = $filter;
		else
			$this->filterChainPrefix[] = $filter;
		return $this;
	} // function addController
	
	/**
	 * Dispatch "request" for processing, first through the chain of filters
	 * (if any) then through the list of controllers (of which there should be 
	 * at least one.  If no controller handles the request, then invoke the 404
	 * handler.
	 * @param ccRequest $request The request object that represents the current req't
	 */
	function render(ccRequest $request)
	{
		foreach ($this->filterChainPrefix as $filter)
		{
			$filter->filterRequest($request); // Can modify request
		}

		foreach ($this->controllerChain as $key => $controller)
		{
			if (is_string($controller))
			{
				$this->controllerChain[$key] = new $controller;
				if (!is_string($this->controllerChain[$key]) && !($this->controllerChain[$key] instanceof ccPageInterface))
					throw ErrrorException($controller.' rendering object needs to implement ccPageInterface.');
				$controller = $this->controllerChain[$key];
			}
			if ($controller->render(clone $request))
				return TRUE;
		}
		return FALSE;
	} // render()

	// function getDebug()
	// {
		// return ccApp::getApp()->getDevMode() & ccApp::MODE_DEVELOPMENT;
	// }
	
} // class ccDispatch
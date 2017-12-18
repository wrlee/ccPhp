<?php
namespace ccPhp;
//!use ccPhp\core\ccPageInterface;
//!use ccPhp\core\ccRequest;

/**
 * This is a page renderer of sorts; it is a controller; but it is a controller
 * of controllers, more simply called a dispatcher. In particular this dipatcher
 * simply chains other page renderers together (usually controllers, but they
 * can be any sort of page renderer).
 *
 * Each page renderer (implementing ccPageInterface) is added via the
 * addPage() method (by name or as an object). When this dispatcher is
 * invoked via its render() method, walks down the pages added, invoking each
 * one in turn until one returns TRUE. If none of the added controllers returns
 * true, then this render() returns FALSE (causing ccApp's dispatch() method to
 * activate 404 page handling).
 *
 * @package ccPhp
 * @todo Move error handling ccError class and refer through ccApp
 * @todo Eliminate filter handling--not sure that it is needed.
 */
class ccChainDispatcher implements ccPageInterface
{
	protected $filterChainPrefix = Array();		// List of filters
	protected $controllerChain = Array();		// List of controllers
//	protected $controllerChainSuffix = Array();	// List of "suffix" handlers

	/**
	 * Add page or controller to handle dispatches of requests.
	 * @param ccPageInterface|string $controller Page object or name of one.
	 *        If string, it is instantiated if needed.
	 */
	function addPage($controller)
	{
		$this->controllerChain[] = $controller;
		if (!is_string($controller) && !($controller instanceof ccPageInterface))
			throw new ErrorException(get_class($controller).' rendering object needs to implement ccPageInterface.');
		return $this;			// Allow property chaining
	} // function addPage()

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
		// Iterate thru chain of filters
		foreach ($this->filterChainPrefix as $filter)
		{
			$filter->filterRequest($request); 	// Filters can modify request
		}
		// Now iterate thru chain of pages
		foreach ($this->controllerChain as $key => $controller)
		{
			if (is_string($controller))	// If entry is name of class,
			{										//    Instantiate it...
				$this->controllerChain[$key] = new $controller;
													// Make sure it is a valid type
				if (!($this->controllerChain[$key] instanceof ccPageInterface))
					throw new ErrorException($controller.' rendering object needs to implement ccPageInterface.');
				$controller = $this->controllerChain[$key];
			}
			if ($controller->render(clone $request))// This one handled rendering
				return TRUE;					// So we are done.
		} // foreach
		return FALSE;							// No handler, so we failed.
	} // render()

	// function getDebug()
	// {
	//		return ccApp::getApp()->getDevMode() & ccApp::MODE_DEVELOPMENT;
	// }

} // class ccChainDispatcher

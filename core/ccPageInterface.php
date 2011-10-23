<?php 

/**
 * This interface represents an object that can render a page, callable by the 
 * framework's dispatcher. Likely controllers will implement this interface and 
 * dispatch processing to their own methods or even other objects that implement
 * this interface. 
 */
interface ccPageInterface
{
	/**
	 * Display page content based on current request.
	 * 
	 * @param ccRequest $request The request block containing properties related
	 *                  to the current page request. 
	 * @return BOOL TRUE: Request handled (presumably, displayed); 
	 *              FALSE: request not handled (the dispatcher will try the next)
	 */
	function render(ccRequest $request);
} // interface ccPageInterface
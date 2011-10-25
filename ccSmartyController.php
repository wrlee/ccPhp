<?php 

/**
 * This controller processes page requests that map to Smarty templates. 
 */
class ccSmartyController extends ccSimpleController
{
	protected $smarty;
	
	function __construct()
	{
		$this->smarty=new ccSmarty();	// Smarty wrapper
	} // __construct()
	
	/**
	 * @todo Consider, rather than pathing deeper to look for template, passing
	 *       remaining path components as a 'params' argument to the template.
	 */
	// 2011-10-24 Call begin() only if method or template will be rendered.
	function render(ccRequest $request)
	{
		// Check to see if method exists based and run it. 
		$rv = parent::render($request);
		if ( $rv !== NULL )				// If method found and executed,
			return $rv;					//    return method's return value

		// If no method exists, attempt to load a Smarty template.
		elseif ( $this->smarty )		// Smarty object, try to render template
		{
			$template = $request->shiftUrlPath();
			if (!$template)
				$template = $request->getDefaultDocument();
			{							// Use fullpath to find template
				$path = implode('/',$request->getUrlPath());
				if ($path)
					$template .=  '/' . $path;
			}
//			{
//				$this->smarty->assign('_params', $request->getUrlPath());
//			}
			if ($this->smarty->templateExists($template))
			{
				if (method_exists($this,'begin'))	// See parent
					if (!call_user_func(array($this,'begin'), $request))
						return FALSE;

				if ( $this->display($request,$template) )
					return TRUE;			// Template found, return
			}
		}		
		return FALSE;					// No method and no template
	} // render()
	
	protected function display(ccRequest $request, $template, $args=NULL)
	{
		$this->smarty->assign('_request', $request);
		return $this->smarty->render(($this->base ? $this->base.'/' : '').$template, (Array)$args);
	} // display()
} // class ccSmartyController
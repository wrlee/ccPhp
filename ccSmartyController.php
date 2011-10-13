<?php 

/**
 * This controller processes page requests that map to Smarty templates. 
 */
class ccSmartyController extends ccSimpleController
{
	public $ext='.tpl';				// Template extension
	protected $smarty;
//	protected $vars; 				// Common variables to be passed in.
	
	function __construct()
	{
		$this->smarty=new ccSmarty();	// Smarty wrapper
	} // __construct()
	
	function render(ccRequest $request)
	{
// echo '<pre>';
// echo implode(',',$request->getUrlComponents()).PHP_EOL;

		// Check to see if method exists based on first component of URL if no
		// first component exists, use "index".
		$rv = parent::render($request);	// Note, this invokes begin()
// echo __METHOD__.'#'.__LINE__.' '.'"'.$methodName.'"'.PHP_EOL;
		if ( $rv !== NULL )			// If method found and run,
			return $rv;				//    return its return value
		// If no method exists, based on first component of the URL, look for 
		// a Smarty template based on the full component path. 
		else
		{
			$template = $request->shiftUrlComponents();
			if (!$template || $template == '')
				$template = $request->getUrlDocument();
//			trigger_error('Method '.get_class($this).'::'.$template.' does not exist. Looking for template.',E_USER_NOTICE);
// echo __METHOD__.'#'.__LINE__.' '.$template.'<br/>';
			$path = implode('/',$request->getUrlComponents());
			if ($path !== '')
				$template =  $path . '/' . $template;
			$template .= $this->ext;
			return $this->display($template,Array('_request' => $request)); 
		}
	} // render()
	
	protected function display($template, $args=NULL)
	{
//		echo '<pre>';
//		echo __METHOD__.'()#'.__LINE__.' "'.$template.'"<br/>'.PHP_EOL;
		return  $this->smarty->render($template, (Array)$args);
	} // display()
	
	function setDebug($debug)
	{
		$this->smarty->debugging = $debug;
		return $this;
	}
	
	function setExt($ext)
	{
		$this->ext = $ext;
		return $this;
	}
} // class ccSmartyController
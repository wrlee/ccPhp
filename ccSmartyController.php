<?php 

/**
 * This controller processes page requests that map to Smarty templates. 
 */
class ccSmartyController extends ccController
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
		$template = $request->getUrlComponents();
		$template = $template[0];
		if (!$template || $template == '')
			$template = $request->getUrlDocument();
// echo '<pre>';
// echo __METHOD__.'#'.__LINE__.' '.'"'.$template.'"'.PHP_EOL;

// echo implode(',',$request->getUrlComponents()).PHP_EOL;

		// Check to see if method exists based on first component of URL if no
		// first component exists, use "index".
		$methodName = $this->findMethodName($template);
// echo __METHOD__.'#'.__LINE__.' '.'"'.$methodName.'"'.PHP_EOL;
		if ( $methodName )
		{
			$request->shiftUrlComponent();
			return call_user_func(array($this,$methodName), $request);
		}	
		// If no method exists, based on first component of the URL, look for 
		// a Smarty template based on the full component path. 
		else
		{
//			trigger_error('Method '.get_class($this).'::'.$template.' does not exist. Looking for template.',E_USER_NOTICE);
// echo __METHOD__.'#'.__LINE__.' '.$template.'<br/>';
			if (!$template || $template == '')
				$template = 'index';
			$path = implode('/',$request->getUrlComponents());
			if ($path !== '')
				$template =  $path . '/' . $template;
			$template .= $this->ext;
			return $this->display($template,Array('_request' => $request)); 
		}
	} // render()
	
	/**
	 * Placeholder for overridable method to perform any common setup before
	 * a page is rendered. 
	 */
	function initPage()
	{
		return TRUE;
	} // initPage()
	
	function display($template, $args=NULL)
	{
//		echo '<pre>';
//		echo __METHOD__.'()#'.__LINE__.' "'.$template.'"<br/>'.PHP_EOL;
		return   $this->initPage()
		       && $this->smarty->render($template, (Array)$args);
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
<?php 

/**
 * This controller processes page requests that map to Smarty templates. 
 */
class ccSmartyController extends ccSimpleController
{
	protected $smarty;
	protected $ext = '.tpl';
	
	protected function initSmarty()
	{
		if (!$this->smarty)
		{
			$this->smarty = new ccSmarty();
			if (isset($this->base))
			{
//				$this->smarty->base = $this->base;
				$dirs = $this->smarty->getTemplateDir();
				$dir = end($dirs);		// WRL HACK: This could break since we dont really know if the last entry is the "base" entry.
				array_unshift($dirs, $dir . $this->base);
				$this->smarty->setTemplateDir( $dirs );
			}
// ccApp::tr($this->smarty->getPluginsDir());
//			$this->smarty->default_template_handler_func = 'ccSmartyController::onNotFound';
		}
		return $this->smarty; 
	}
	
	static function onNotFound($type, $name, &$content, &$modified, Smarty_Internal_Template $smarty) 
	{
		ccApp::tr('"'.$name.'" not found.');
		// ccApp::tr($type);
		// ccApp::tr($name);
		// ccApp::tr($content);
		// ccApp::tr($modified);
		// echo '<pre>'; var_dump($smarty);

if ( isset($smarty->parent->smarty->base))
{
	$s = $smarty->parent->smarty;
	ccApp::tr($s->template_dir);
  ccApp::tr('Try1: "'.$s->base.DIRECTORY_SEPARATOR.$name.'"');
}
		if ($type == 'file' 
			&& isset($smarty->parent->smarty->base)
			// && call_user_func(array( $smarty->parent->smarty, 'parent::templateExists'),$smarty->parent->smarty->base.DIRECTORY_SEPARATOR.$name) ) 
			&& $smarty->templateExists($smarty->parent->smarty->base.DIRECTORY_SEPARATOR.$name) )
			// && $smarty->templateExists($s->template_dir[0].$smarty->parent->smarty->base.DIRECTORY_SEPARATOR.$name) )
		{
			ccApp::tr('Try2: "'.$smarty->parent->smarty->base.DIRECTORY_SEPARATOR.$name.'"');
			ccApp::tr($smarty->templateExists($smarty->parent->smarty->base.DIRECTORY_SEPARATOR.$name));
			return $s->template_dir[0].$smarty->parent->smarty->base.DIRECTORY_SEPARATOR.$name;
		}
		else
			return false;
	}
	
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
				$path = implode(DIRECTORY_SEPARATOR,$request->getUrlPath());
				if ($path)
					$template .=  DIRECTORY_SEPARATOR . $path;
			}
			if (substr($template,-strlen($this->ext)) != $this->ext)
				$template .= $this->ext;
			if ($this->smarty->templateExists($template))
			{
				if (method_exists($this,'begin'))	// See parent
					if (!call_user_func(array($this,'begin'), $request))
						return FALSE;

				if ( $this->display($request,$template) )
					return TRUE;			// Template found, return
			}
		}		
		return FALSE;						// No method and no template
	} // render()
	
	protected function display(ccRequest $request, $template, $args=NULL)
	{
		if (!$this->smarty)
			$this->initSmarty();			// Make sure Smarty is init'd
		$this->smarty->assign('_request', $request);
		if (substr($template,-strlen($this->ext)) != $this->ext)
			$template .= $this->ext;
		return $this->smarty->render(($this->base ? $this->base.'/' : '').$template, (Array)$args);
	} // display()
} // class ccSmartyController
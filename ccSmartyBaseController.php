<?php 
/**
 *
 * @package ccPhp\PageController
 */
/**
 * This controller processes page requests that map to Smarty templates. 
 * @package ccPhp\PageController
 */
class ccSmartyBaseController
	extends ccSimpleController
{
	/**
	 * Reference to Smarty object. 
	 */
	protected $smarty;
	/**
	 * Default template name.
	 */
	protected $ext = '.tpl';
	
	/**
	 * Create Smarty object.  Rather than calling this automatically in the 
	 * constructor, there may be many action-methods for which Smarty is not needed,
	 * so this should be called explicitly, when needed, from an action-method.
	 */
	protected function initSmarty()
	{
		if (!$this->smarty)
		{
			$this->smarty = new ccSmarty();
			
			if (!isset($this->templateBase))
				$this->templateBase = isset($this->base) ? $this->base : '';
				
			if (isset($this->templateBase))
			{
//				$this->smarty->base = $this->base;
				$dirs = $this->smarty->getTemplateDir();
				$dir = end($dirs);		// WRL HACK: This could break since we dont really know if the last entry is the "base" entry.
				array_unshift($dirs, $dir . $this->templateBase);
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
	 * Render pages based on Smarty templates. 
	 *
	 * Derivations of this class can implement methods just as for ccSimpleController,
	 * presumably to prep for displaying via Smarty. But, if no method exists
	 * but there is a template with a matching name, it is displayed. As in the 
	 * base-class, if any processing is to take place, then any existing begin()
	 * will be called first. 
	 *
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
// ccTrace::log(__LINE__.":ccSmartyController::render(".$template.")");
			{							// Use fullpath to find template
				$path = implode(DIRECTORY_SEPARATOR,$request->getUrlPath());
				if ($path)
					$template .=  DIRECTORY_SEPARATOR . $path;
			}
// ccTrace::log(__LINE__.":ccSmartyController::render(".$template.")");
			if (substr($template,-strlen($this->ext)) != $this->ext)
				$template .= $this->ext;
// ccTrace::log(__LINE__.":ccSmartyController::render(".$template.")");
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
	
	/**
	 * Convenience method to call ccSmarty's display function. This adds 
	 * the current request block and assumes a default extension to the template. 
	 */
	protected function display(ccRequest $request, $template, $args=NULL)
	{
		if (!$this->smarty)
			$this->initSmarty();			// Make sure Smarty is init'd
		$this->smarty->assign('_request', $request);
		if (substr($template,-strlen($this->ext)) != $this->ext)
			$template .= $this->ext;
		return $this->smarty->render(($this->templateBase ? $this->templateBase.DIRECTORY_SEPARATOR : '').$template, (Array)$args);
	} // display()
} // class ccSmartyController

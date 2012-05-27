<?php 
/**
 *
 * @package ccPhp\PageController
 */

/**
 * This controller dynamically serves CSS files upon request, preprocessing the 
 * file or pulling it from a cache. This keeps the CSS files out of the public 
 * directory and allows the option to minify the content on request and 
 * perform any translation of standard CSS to browser-vendor specific versions 
 * (e.g., border radius settings).
 *
 * 1. If the file exists in site/public/css, then this module is never called and 
 *    that file is processed, RAW. 
 * 2. If file exists in site/css, then it is processed and served by this module.
 * @package ccPhp\PageController
 */
class ccCssController 
	implements ccPageInterface
//	extends ccSimpleController
{
	protected $bMinify = FALSE;
	protected $cachePath;			// Cache of working files
	protected $bCache  = FALSE;
	protected $bCheckNewerSource = TRUE;
	protected $dirs;

	function __construct()
	{
		$app = ccApp::getApp();
		$this->base = 'css';
		$this->dirs[] = $app->getSitePath().$this->base.DIRECTORY_SEPARATOR;
		$this->cachePath = $app->createSiteDir($app->getWorkingPath().$this->base);
	} // __construct()
	
	/**
	 * @todo Eliminate $request parameter
	 */
	static protected function loadCss($filepath,ccRequest $request)
	{
		$file = file_get_contents($filepath);	// Load file content
		
		$search = array();
		$replace = array();
		if ($request['CssVersion'] < 3)			// Look for substitution opportunities.
			switch ($request['Browser'])
			{
			case 'Chrome':
			case 'Safari':
				$search[]= 'border-radius:'; $replace[]= '-webkit-border-radius:';
				$search[]= 'border-top-left-radius:'; $replace[]= '-webkit-border-top-left-radius:';
				$search[]= 'border-top-right-radius:'; $replace[]= '-webkit-border-top-right-radius:';
				$search[]= 'border-bottom-right-radius:'; $replace[]= '-webkit-border-bottom-right-radius:';
				$search[]= 'border-bottom-left-radius:'; $replace[]= '-webkit-border-bottom-left-radius:';
				break;
			case 'Firefox':
				$search[]= 'border-radius:'; $replace[]= '-moz-border-radius:';
				$search[]= 'border-top-left-radius:'; $replace[]= '-moz-border-radius-topleft:';
				$search[]= 'border-top-right-radius:'; $replace[]= '-moz-border-radius-topright:';
				$search[]= 'border-bottom-right-radius:'; $replace[]= '-moz-border-radius-bottomright:';
				$search[]= 'border-bottom-left-radius:'; $replace[]= '-moz-border-radius-bottomleft:';
				break;
			}
		if ($search)
			$file = str_replace($search, $replace, $file);
			
		return $file;
	} // loadCss()
	
	protected function minifyCss($string)
	{
		return $string;
	}
	
	function setMinify($bMinify = TRUE)
	{
		$this->bMinify = $bMinify;
		return $this;
	}

	/**
	 * Search for $file in list of paths
	 */
	protected function search($file)
	{
		foreach ($this->dirs as $path)
		{
			if (file_exists($path.$file))
				return $path.$file;
		}
		return NULL;
	}
	
	/**
	 * Based on the requested CSS or JS file name, load, concat (and optionally 
	 * minify) files. 
	 */
	function render(ccRequest $request)
	{
		if ($request->shiftUrlPath() != $this->base)
			return FALSE;
			
		$filename = $request->getTrueFilename();
		
		header('Content-type: text/css');
		header('Content-Disposition: inline');
		
		$files = explode(',',$filename);
		
		$rc = TRUE;
		foreach ($files as $file)
		{
			if (!file_exists($file))
			{
				if ($file[0] != '/')		// WRL!!! Use strpos() instead?
					$file = $this->search($file);
			}
			if (file_exists($file))
			{
				$out = $this->loadCss($file,$request);
				if ($this->bMinify)
					$out = $this->minifyCss($out);
				echo $out;
			}
			else 
				$rc = FALSE;
		}
		
//		header('Content-Length: '.strlen($file));
		// foreach ($request as $key => $value)
			// ccApp::out("$key = $value<br/>");
		return $rc;
	}
} // class ccCssController
<?php 

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
 */
class ccCssController 
	implements ccPageInterface
//	extends ccSimpleController
{
	protected $bMinify = FALSE;
	protected $cachePath;
	protected $bCache  = FALSE;
	protected $bCheckNewerSource = TRUE;

	function __construct()
	{
		$this->base = 'css';
	}
	
	/**
	 * @todo Eliminate $request parameter
	 */
	static function loadCss($filepath,ccRequest $request)
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
	
	function minifyCss($string)
	{
		return $string;
	}
	
	function setMinify($bMinify = TRUE)
	{
		$this->bMinify = $bMinify;
		return $this;
	}
	
	function render(ccRequest $request)
	{
		if ($request->shiftUrlPath() != $this->base)
			return FALSE;
			
		$filename = $request->getTrueFilename();
		$filepath = ccApp::getApp()->getSitePath().$this->base.DIRECTORY_SEPARATOR.$filename;
		
//		header('Content-type: text/css');
//		header('Content-Disposition: inline; filename="'.$filename.'"');
		
		if (strpos($filename, ',') !== FALSE)	// Compact 
		{
			// 1.Iterate thru list of files.
			// 2. Look in std public/css first.
			//    - If exist, simply append
		}
		elseif (file_exists($filepath))
		{
			$file = $this->loadCss($filepath,$request);
			if ($this->bMinify)
				$file = $this->minifyCss($file);
//			header('Content-Length: '.strlen($file));
			echo $file;
		}
		elseif (ccApp::getApp()->getDevMode() & ccApp::MODE_DEVELOPMENT)
		{
//			throw new Exception($filepath.' does not exist.');
			?>
			body:before {
				content: '<?php echo $filepath.' does not exist.' ?>';
				color:red;
				font-weight: bold;
			}
			<?php
//			throw new ccHttpStatusException(404);
		}
		else
			return FALSE;
		
		// foreach ($request as $key => $value)
			// ccApp::out("$key = $value<br/>");
		return TRUE;
	}
} // class ccCssController
<?php 

/**
 * ccSmarty encapsulates the Smarty templates. 
 */
class ccSmarty extends Smarty
{
	protected $commonParams=NULL;
	protected $ext='.tpl';

	function __construct()
	{
		parent::__construct();			// Default Smarty initialization
		$app = ccApp::getApp();			// Common app reference
		$smartyTempPath = $app->getSitePath().'.smarty'.DIRECTORY_SEPARATOR;
// echo __METHOD__.'()#'.__LINE__.$smartyTempPath .'<pre>'.PHP_EOL;
		
//		$this->use_sub_dirs = true;
		
		$this->setCacheDir($smartyTempPath . 'cache')
			->setCompileDir($smartyTempPath . 'compile')
			->setConfigDir($app->getSitePath() . 'templates');
		$this->setTemplateDir($this->config_dir);
// echo __METHOD__.'('.$className.')#'.__LINE__.SMARTY_DIR.$this->plugins_dir .'<br/>'.PHP_EOL;

		$devmode = $app->getDevMode();
		$this->debugging_ctrl = ( $devmode & ccApp::MODE_DEVELOPMENT 
		                          ? 'URL' 
								  : 'NONE' );
		$this->setCaching($devmode & ccApp::MODE_DEVELOPMENT 
							? Smarty::CACHING_OFF
							: Smarty::CACHING_LIFETIME_CURRENT);
		$this->setCompileCheck( ($devmode & ~ccApp::MODE_PRODUCTION ) );
//		$this->testInstall();
	} // __construct()
	
	/**
	 * Process and display template from the templates directory.
	 * @param string $template Template file name.
	 * #param array  $paramAssocArray Associative array of parameters for the template.
	 * @returns bool TRUE displayed; FALSE template not found.
	 */
	function render($template, $paramAssocArray=NULL)
	{
// var_dump($paramAssocArray);
// echo '<pre>'.$template.PHP_EOL;
// ccApp::getApp()->showTrace(debug_backtrace());
		if (substr($template,-strlen($this->ext)) != $this->ext)
			$template .= $this->ext;
		if ($this->templateExists($template))
		{
			$this->assign((Array)$paramAssocArray+(Array)$this->commonParams);
			$this->display($template);
			return TRUE;
		}
		else
			return FALSE;
	} // render()
	
	function setCommonParameters($paramAssocArray)
	{
		$this->commonParams = $paramAssocArray;
		return $this;
	}
	
	function setDebug($debug)
	{
		$this->debugging = $debug;
		return $this;
	}
	
	protected function _createDir($dir)
	{
		if ( substr($dir, -1) != DIRECTORY_SEPARATOR )
			$dir .= DIRECTORY_SEPARATOR;
		if (!is_dir($dir))
			mkdir($dir,0777,TRUE);
		return $dir;
	}
	function setCacheDir($dir)
	{
		$this->cache_dir = $this->_createDir($dir);
		return $this;
	}
	function setCompileDir($dir)
	{
		$this->compile_dir = $this->_createDir($dir);
		return $this;
	}
	function setConfigDir($dir)
	{
		$this->config_dir = $this->_createDir($dir);
		return $this;
	}
	function setTemplateDir($dirs)
	{
		if (is_array($dirs))
		{
			foreach ($dirs as $key => $dir)
				$dirs[$key] = $this->_createDir($dir);
			return parent::setTemplateDir($dirs);
		}
		else
			return parent::setTemplateDir($this->_createDir($dirs));
	}
	function addTemplateDir($dirs)
	{
		if (is_array($dirs))
		{
			foreach ($dirs as $key => $dir)
				$dirs[$key] = $this->_createDir($dir);
			return parent::addTemplateDir($dirs);
		}
		else
			return parent::addTemplateDir($this->_createDir($dirs));
	}
} // class ccSmarty
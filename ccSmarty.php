<?php 
/**
 * ccPhp Support for Smarty integration.
 * @package Smarty
 */

/**
 * ccSmarty derives the base Smarty class to add features for integration with ccPhp.
 * @used-by ccSmartyController
 * @package Smarty
 */
class ccSmarty extends Smarty
{
	/**
	 * @ignore
	 */ 
	function __construct()
	{
		parent::__construct();			// Default Smarty initialization
		$app = ccApp::getApp();			// Common app reference
		$smartyTempPath = $app->getWorkingPath().'smarty'.DIRECTORY_SEPARATOR;
		
//		$this->use_sub_dirs = true;
		
		$this->setCacheDir($smartyTempPath . 'cache')
			->setCompileDir($smartyTempPath . 'compile')
			->setConfigDir($app->getSitePath() . 'templates');
		$this->setTemplateDir($this->config_dir);
		
		$fwpath = ccApp::getApp()->getFrameworkPath();
		if (file_exists($fwpath.'smarty'))
			$this->addPluginsDir($fwpath.'smarty');

		$devmode = $app->getDevMode();
		$this->debugging_ctrl = ( $devmode & ~ccApp::MODE_PRODUCTION 
		                          ? 'URL' 
								  : 'NONE' );
		$this->setCaching($devmode & ccApp::MODE_DEVELOPMENT 
							? Smarty::CACHING_OFF
							: Smarty::CACHING_LIFETIME_CURRENT);
		$this->setCompileCheck( ($devmode & ~(ccApp::MODE_PRODUCTION) ) );
//		$this->setCompileCheck( TRUE );
//		$this->testInstall();
	} // __construct()
	
	/**
	 * Process and display template from the templates directory.
	 * @param string $template Template file name.
	 * @param array  $paramAssocArray Associative array of parameters for the template.
	 * @returns bool TRUE displayed; FALSE template not found.
	 */
	function render($template, $paramAssocArray=NULL)
	{
// ccApp::getApp()->showTrace(debug_backtrace());
//		if (substr($template,-strlen($this->ext)) != $this->ext)
//			$template .= $this->ext;
		if ($this->templateExists($template))
		{
			$this->assign((Array)$paramAssocArray);
			$this->display($template);
			return TRUE;
		}
		else
			return FALSE;
	} // render()
	
	/**
	 * Set the debug mode for Smarty.
	 * @param boolean $debug on or off.
	 */
	function setDebug($debug)
	{
		$this->debugging = $debug;
		return $this;
	} // setDebug()
	
	/**
	 * Create directory, if it doesn't exist.
	 * @param string $dir Name of directory to create
	 * @todo Accept array of directory names (to move common code here)
	 */
	protected function _createDir($dir)
	{
		if ( substr($dir, -1) != DIRECTORY_SEPARATOR )
			$dir .= DIRECTORY_SEPARATOR;
		if (!is_dir($dir))
			mkdir($dir,0744,TRUE);
		return $dir;
	} // _createDir()

	/**
	 * Add template dir(s), creating them, if they don't exist.
	 * @param string|array $dirs Name(s) of template directory(ies)
	 */
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
	} // setTemplateDir()
		
	/**
	 * Add template dir(s), creating them, if they don't exist.
	 * @param string|array $dirs Name(s) of template directory(ies)
	 */
	function addTemplateDir($dirs,$key=null)
	{
		if (is_array($dirs))
		{
			foreach ($dirs as $idx => $dir)
				$dirs[$idx] = $this->_createDir($dir);
			return parent::addTemplateDir($dirs,$key);
		}
		else
			return parent::addTemplateDir($this->_createDir($dirs));
	} // addTemplateDir()
	
	/**
	 * Check for template existence (appending default extension, if necessary)
	 */
/*	function templateExists($template)
	{
		if (substr($template,-strlen($this->ext)) != $this->ext)
			$template .= $this->ext;
		return parent::templateExists($template);
	}
*/
} // class ccSmarty
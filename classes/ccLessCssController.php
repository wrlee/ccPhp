<?php
namespace ccPhp;
//use \lessc;

/**
 * @todo  Add settings for enable/disabling caching
 * @todo  Add MEMCACHE support of caching.
 * @todo Consider propagating lessc Exceptions.
 * @todo  Consider whether/when compiled directory is needed
 */
class ccLessCssController
	extends \lessc
	implements ccPageInterface
{
	protected $working_dir='lessphp/'; //.DIRECTORY_SEPARATOR;
	protected $cache_dir='cache/'; //.DIRECTORY_SEPARATOR;
	protected $compile_dir='compiled/'; //.DIRECTORY_SEPARATOR;
	protected $source_dir='less/'; //.DIRECTORY_SEPARATOR;
	protected $css_dir='css/'; //.DIRECTORY_SEPARATOR;
	protected $app;

	private $last_modified;

	function __construct($css_dir=false)
	{
//		$this->setImportDir();
		$this->app = ccApp::getApp();
		$this->working_dir = $this->app->createWorkingDir($this->working_dir);
		$this->app->createWorkingDir($this->working_dir.$this->cache_dir);
//		$this->app->createWorkingDir($this->working_dir.$this->compile_dir);
		if ($css_dir) {
			if (substr($css_dir,-1) != DIRECTORY_SEPARATOR)
				$css_dir .= DIRECTORY_SEPARATOR;
			$this->css_dir = $css_dir;
		}
	}
	/**
	 * Process request for CSS from the CSS directory performing Less compiling,
	 * if necessary.
	 * @param  ccRequest $request
	 * @return true/false processed or not.
	 * @todo  Check whether compiled CSS is older than source.
	 */
	function render(ccRequest $request)
	{
		$dir = $request->shiftUrlPath();
		if ($dir.DIRECTORY_SEPARATOR != $this->css_dir)
			return false;

		$file = $request->getTrueFilename();
ccTrace::tr($file);
		$ifile = pathinfo($file, PATHINFO_FILENAME).'.less';

		$cache = $this->autoCompileLess($ifile /*,$this->working_dir.$this->compile_dir.$file*/);

		if (is_null($cache))// No valid less source.
			return false;	// 404

		$tz = date_default_timezone_get ();
		date_default_timezone_set('UTC');	// Stabilize time

// if (isset($_SERVER['HTTP_ETAG']) )
// ccTrace::tr("Etag=".$_SERVER['HTTP_ETAG']);
// if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) )
// ccTrace::tr($_SERVER['HTTP_IF_MODIFIED_SINCE'].'='.strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']));
// if (isset($_SERVER['HTTP_IF_NONE_MATCH']) )
// ccTrace::tr($_SERVER['HTTP_IF_NONE_MATCH'].'='.strtotime($_SERVER['HTTP_IF_NONE_MATCH']));
// ccTrace::tr($this->last_modified.'='.date("r",$this->last_modified));
// ccTrace::tr($_SERVER);

		// If ETag matches, then we can just return.
		if ((   isset($_SERVER['HTTP_IF_NONE_MATCH'])
			 && $_SERVER['HTTP_IF_NONE_MATCH'] == $this->last_modified)
//			||
		// If conditional header and less has not been modified since req't
		// then redirect 304 and circumvent content transfer.
//		  (   isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
//			&& strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $this->last_modified)
		   )
		{
			header('Cache-Control: public');
			throw new ccHttpStatusException(304, 'Not Modified');
//			header($_SERVER['SERVER_PROTOCOL'].' 304 Not Modified', TRUE, 304);
		}
		else
		{
			$this->display($cache);
		}
		date_default_timezone_set($tz);
		return true;
	}

	protected function display($content)
	{
		header('Content-type: text/css');
		header('Cache-Control: public');
		header('Last-Modified: '.date("r",$this->last_modified));
		header('ETag: '.$this->last_modified);
//		header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found', TRUE, 404);
		echo $content;
	}

	/**
	 * Conditionally compile cache or pull from cache, less files.
	 * @param  string $iFilename  Less CSS filename
	 * @param  string $outputFile CSS filename
	 * @return NULL|string CSS file. If NULL, then none found.
	 * @todo  Use MEMCACHE
	 */
	function autoCompileLess($iFilename, $outputFile=NULL)
	{
		$cacheFile = $this->working_dir.$this->cache_dir.$iFilename;	// load the cache
		ccTrace::tr($cacheFile);

		if (file_exists($cacheFile))
			$cache = unserialize(file_get_contents($cacheFile));
		else
			$cache = $this->app->getAppPath().$this->source_dir.$iFilename;

		$newCache = $this->cachedCompile($cache);

		if ($newCache == NULL)
			return NULL;

		// If no cache-file or new cache is newer than old one, save the cache-file
		// and save generated CSS.
		if (!is_array($cache) || $newCache["updated"] > $cache["updated"])
		{
			ccTrace::tr($cacheFile);
			ccTrace::tr($newCache);
			file_put_contents($cacheFile, serialize($newCache));
			if ($outputFile)
				file_put_contents($outputFile, $newCache['compiled']);
		}
		$this->last_modified = $newCache['updated'];
		return $newCache['compiled'];
	}


	function cachedCompile($in, $force = false)
	{
		try {
			return parent::cachedCompile($in,$force);
		} catch (Exception $e) {
			echo 'LessPHP: '.$e->getMessage();
			ccTrace::tr('Exception: '.$e->getMessage());
			return NULL;
		}
	}

} // class ccLesCssController

<?php
/**
 * Implements ccHtmlPage class.
 * @author Bill Lee bill@cachecrew.com
 */
/**
 * Class to output to a page, implement the following:
 *
 * 1. Define elements of the page: title meta tags, script & CSS inclusion, etc.
 *    This can be done in constructor.
 * 2. Implement onContent()
 *
 * - Add scripts and CSS references
 * - Add any addtional script content
 * - Add any content to preface or epilog
 * - Implement content generator (e.g., onContent())
 *
 *
 *
 * @todo Reimplement use of begin()/after() to free those up for normal
 * 		 derived-class overriding (call onPreface()/onEpilog() separately, explicitly)
 * @todo Define packages, e.g., jQuery, Bootstrap, etc. which will perform the appropriate
 *       setup for the corresponding package (include CSS, JS)
 * @todo Add meta tag support
 * @todo Consider abstracting this by eliminating making body an explicit part
 *       of the interface, describing only: preface, content, and epilog. Then allowing
 *       augmentation of those three elements by inserting to top and bottom of each.
 */
namespace ccPhp;

class ccHtmlPage
	extends ccSimpleController
{
											/** @var ccRequest Current request */
	protected $request;
											/** @var string Page title (in head) */
	protected $title;
											/** @var string GA tracking ID */
	protected $GoogleAnalyticsTrackingID;
											/** @var array <head> content */
	protected $head=[];
											/** @var array Content for top */
	protected $htmlHeadSources=[];
											/** @var array Content for bottom */
	protected $htmlTailSources=[];
											/** @var Array of scripts and locations */
	protected $scripts=[];
											/** @var array Preface suffix content */
	protected $preface=[];
											/** @var array Epilog prefix content */
	protected $epilog=[];
//	protected $packages; 					// List of packages to include
//		public const PKG_BOOTSTRAP='bootstrap';	// Implies jquery
//		public const PKG_JQUERY='jquery';
//	protected $meta;						/** Meta tag info */

/**
 * If method found, this will be called before method.
 * @param ccRequest $request HTTP request object
 */
	protected function begin($request)
	{
		$this->request = $request;
		$this->onHeaders();
		$this->onPreface();

		return true;
	} // begin()

	/**
	 * If method found, this will be called after method.
	 * @param ccRequest $request HTTP request object
	 */
	protected function after($request)
	{
		$this->onEpilog();
	} // after()


	/**
	 * Get/set Google Analytics tracking ID
	 * @param string $tracking_id Google tracking ID to set.
	 */
	protected function googleAnalytics($tracking_id=NULL)
	{
		if (!$tracking_id)
			return $this->GoogleAnalyticsTrackingID;
		else {
			$this->GoogleAnalyticsTrackingID = $tracking_id;
			$this->addScript(<<<GA_SCRIPT
// Google Analytics
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
  ga('create', '$this->GoogleAnalyticsTrackingID', 'auto');
  ga('send', 'pageview');
GA_SCRIPT
);
			return $this; 		// Allow chaining
		}
	} // googleAnalytics()

	/**
	 * Add reference to file by category:
	 * - title
	 * - meta
	 * - google analytics
	 * - CSS reference
	 * - Script reference
	 * - CSS?
	 * - Script content?
	 * @param string $uri Resource path
	 * @param bool $head Add to top or not. Default for css is top, js is bottom
	 */
	protected function addUriSource($uri, bool $head=false)
	{
		$type = pathinfo( $uri, PATHINFO_EXTENSION );
		if ($type == 'css')
			$head = true;
		if ($head)
			$this->htmlHeadSources[] = $uri;
		else
			$this->htmlTailSources[] = $uri;
	}

	const INSERT_TOP=2;				// Insert in header
	const INSERT_BOTTOM=3;			// Insert before end of <body>
	const INSERT_ONLOAD=1;			// Insert in doc load()

	/**
	 * Pass text or function to generate text of script to be inserted in content.
	 * @param string|method $script Content to be inserted, a function returning text or text
	 * @param $location Where to insert content
	 * @see  insertScript()
	 */
	protected function addScript($script, $location=self::INSERT_BOTTOM)
	{
		$this->scripts[] = [$script, $location];
	}
	/**
	 * Pass text or function to generate text of script to be inserted in content.
	 * @param string|method $head Content to be inserted, a function return text or text
	 * @see  insertScript()
	 */
	protected function addHead($head)
	{
		$this->head[] = $head;
	}
	/**
	 * Submit content to add to preface, later.
	 * @param string|method $content Content text or function
	 */
	protected function addPreface($content)
	{
		$this->preface[] = $content;
	}
	/**
	 * Submit content to add to epilog, later.
	 * @param string|method $content Content text or function
	 */
	protected function addEpilog($content)
	{
		$this->epilog[] = $content;
	}

	/**
	 * Include CSS, script, etc. Type is inferred by extension. Scripts are
	 * added to bottom, by default unless $head is TRUE.
	 * @param string $uri Path to resource.
	 * @param string $type Identifies the type of resource (if not discernable)
	 * @todo Support for <script>'s' async="async", defer="defer", type="text/javascript"
	 * 	     see http://www.w3schools.com/tags/tag_script.asp
	 */
	private function insertUriSource($uri, $type='')
	{
		if (! $type )
			$type = pathinfo( $uri, PATHINFO_EXTENSION );
		$CH_TAB = "\x9";
		switch ($type)
		{
			case 'css':
			case 'style':
			case 'stylesheet':
				$mime = isset($params['mime']) ? $params['mime'] : 'text/css';
				$media = isset($params['media']) ? 'media="'.$params['media'].'" ' : '';
//				if ($uri[0] != '/' && strpos($uri,':') === false)
//					$uri = ccApp::getApp()->getUrlOffset().'css/'.$uri;
				echo $CH_TAB.'<link rel="stylesheet" '.$media.'type="'.$mime.'" href="'.$uri.'"/>'.PHP_EOL;
			break;
			case 'js':
			case 'javascript':
				$mime = isset($params['mime']) ? " type=\"${params['mime']}\"" : '';
				$defer = /*isset($params['defer']) ? 'defer="defer" ' :*/ '';
//				if ($uri[0] != '/' && strpos($uri,':') === false)
//					$uri = ccApp::getApp()->getUrlOffset().'js/'.$uri;
//				echo '<script'.$mime.' '.$defer.'src="'.$uri.'"></script>'.PHP_EOL;
				echo $CH_TAB.'<script'.$mime.' '.$defer.'src="'.$uri.'"></script>'.PHP_EOL;

			break;
			case 'ico':
			case 'gif':		// Does not work in IE
			case 'png':		// Does not work in IE
			case 'jpg':		// Does not work in IE
			case 'jpeg':	// Does not work in IE
			case 'svg':		// Only works in Opera
			case 'icon':
				if ($uri[0] != '/' && strpos($uri,':') === false)
					$uri = ccApp::getApp()->getUrlOffset().$uri;
				echo '<link rel="shortcut icon"'.$media.$mime.' href="'.$uri.'"/>'.PHP_EOL;
				break;
//			default:
//				throw new SmartyCompilerException('Unexpected or unspecified type \''.$type.'\'');
		}
	} // insertUriSource()

	/**
	 * Insert script content based on setup previously defined by addScript() method.
	 * @param  $location Which set of scripts to insert: INSERT_TOP, INSERT_BOTTOM, INSERT_ONLOAD
	 * @return Undefined
	 * @see addScript()
	 */
	private function insertScripts($location)
	{
		$wrapper = false;
		foreach ($this->scripts as $script) {
			if ($script[1] == $location) {
				if (!$wrapper) {
					echo '<script type="text/javascript">'.PHP_EOL;
					$wrapper = true;
				}
				if (is_callable($script[0]))
					$script[0]();
				else {
					echo $script[0].PHP_EOL;
				}
			}
		}
		if ($location == self::INSERT_BOTTOM)
		{
			$loaded=false;
			foreach ($this->scripts as $script) {
				if ($script[1] == self::INSERT_ONLOAD) {
					if (!$wrapper) {
						echo '<script type="text/javascript">'.PHP_EOL;
						$wrapper = true;
					}
					if (!$loaded) {
						echo '$(function() {'.PHP_EOL;
						$loaded = true;
					}
					if (is_callable($script[0]))
						$script[0]();
					else {
						echo $script[0].PHP_EOL;
					}
				}
			}
			if ($loaded)
				echo '});'.PHP_EOL;
		}
		if ($wrapper)
			echo '</script>'.PHP_EOL;
	} // insertScripts()

	// Implement these as if you were being called, live. The content of each is captured then
	// output by the base-class so it can be performed in the correct order (e.g., onHeaders
	// output is sent first).
// 	protected function onPage() {}	// Output page (this calls the subsequent)
// //	protected function before() {} // Common stuff to do before each action (ccSimpleController)
// 	protected function onHeaders() {} // HTTP Header output
// 	protected function onHead() {} // Things within the head tag
// 	protected function onBody()	{} // Things within the body tags
// 	protected function onContent() {} // Core content, called by onBody()
// 	protected function onScript($head=FALSE) {} // Things within the script tags top or bottom
// //	protected function after() {} // Common stuff to do before each action (ccSimpleController)

//	protected function before() {} // Common stuff to do before each action (ccSimpleController)

	/**
	 * Output html headers
	 * @todo Implement or get rid of this.
	 */
	protected function onHeaders() {} // HTTP Header output

	/**
	 * Output "preface", i.e., HTML standard start, <head> content via onHead(),
	 * and beginning of body.
	 */
	protected function onPreface()
	{	?>
<!DOCTYPE html>
<html lang="en">
<?php $this->onHead(); ?>
<body>
<?php
		$this->insert($this->preface);
	} // onPreface()

	/**
	 * Output HTML <head> content.
	 * @param method $content Optional function called just before </head>
	 * @todo Add IE settings that determine IE compatibility level and other conditional statements
	 * @todo Add charset overrides
	 */
	protected function onHead($content=NULL)
	{
		?>
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta charset="utf-8">
	<base href="<?=$this->request->getRootUrl()?>">
	<title><?= $this->title ?></title>
<?php
		// onTitle();

		$this->insert($this->head);

		// Inclusions css & scripts
		foreach ($this->htmlHeadSources as $uri)
			$this->insertUriSource($uri);

		$this->insertScripts(self::INSERT_TOP);

		if (is_callable($content))
			$content();
	?>
</head>
<?php
	} // onHead()

//	protected function after() {} // Common stuff to do after each action (ccSimpleController)

	/**
	 * Output "epilog" content.
	 * Define common "template for top of output".
	 * - <doctype><hteml><head>...</head><body>
	 * @param method $content Optional function called after epilog's output
	 *
	 * @see footerTemplate()
	 */
	protected function onEpilog($content=NULL)
	{
		$this->insert($this->epilog);

		foreach ($this->htmlTailSources as $uri)
			$this->insertUriSource($uri);
		$this->insertScripts(self::INSERT_BOTTOM);
		?>
</body>
<?php
		if ($content)
			$content();
		?></html>
		<?php
	} // onEpilog()

	/**
	 * Output content from array elements of text or functions, calling
	 * functions to output content.
	 *
	 * @param Array $contentArray Array of content to be inserted.
	 * @param method $onEach Function will process ea array element, instead
	 *
	 */
	private function insert(Array $contentArray, $onEach=NULL)
	{
		foreach ($contentArray as $content) {
			if (is_callable($onEach))
				$onEach($content);
			else
				if (is_callable($content))
					$content();
				else
					echo $content.PHP_EOL;
		}
	} // insert()

} // ccHtmlPage

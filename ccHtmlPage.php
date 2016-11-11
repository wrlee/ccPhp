<?php

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
 * @todo Define packages, e.g., jQuery, Bootstrap, etc. which will perform the appropriate
 *       setup for the corresponding package (include CSS, JS)
 * @todo Add meta tag support
 * @todo Consider abstracting this by eliminating making body an explicit part
 *       of the interface, describing only: preface, content, and epilog. Then allowing
 *       augmentation of those three elements by inserting to top and bottom of each. 
 */
class ccHtmlPage 
	implements ccPageInterface
{
	protected $request;						// Current request
	protected $title;						// Page title (in head)
	protected $GoogleAnalyticsTrackingID;	// GA tracking ID
	protected $htmlHeadSources=[];
	protected $htmlTailSources=[];
	protected $scripts=[];					// Array of scripts and locations
//	protected $packages; 					// List of packages to include
//		public const PKG_BOOTSTRAP='bootstrap';	// Implies jquery
//		public const PKG_JQUERY='jquery';	
//	protected $meta;						// Meta tag info
	protected $preface=[];					// Preface suffix content
	protected $epilog=[];					// Epilog prefix content

	/**
	 * Get/set Google Analytics tracking ID
	 */
	protected function googleAnalytics($tracking_id=NULL)
	{
		if (!$tracking_id)
			return $this->GoogleAnalyticsTrackingID;
		else {
			$rc = $this->GoogleAnalyticsTrackingID;
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
			return $rc; 		// Return previous value
		}
	} // googleAnalytics()

	/**
	 * Add stuff to file by category:
	 * - title
	 * - meta 
	 * - google analytics
	 * - CSS reference
	 * - Script reference
	 * - CSS?
	 * - Script content?
	 */
	protected function addUriSource($uri, bool $head=NULL)
	{
		$type = pathinfo( $uri, PATHINFO_EXTENSION );
		if ($type == 'css')
			$head = true;
		if ($head)
			$this->htmlHeadSources[] = $uri;
		else
			$this->htmlTailSources[] = $uri;
	}

	const SCRIPT_ONLOAD=1;
	const SCRIPT_TOP=2;
	const SCRIPT_BOTTOM=3;
	protected function addScript($script, $location=self::SCRIPT_BOTTOM)
	{
		$this->scripts[] = [$script, $location];
	}
	protected function addPrefix($content)
	{
		$this->preface[] = $content;
	}
	protected function addEpilog($content)
	{
		$this->epilog[] = $content;
	}

	/**
	 * Include CSS, script, etc. Type is inferred by extension. Scripts are added to bottom, by 
	 * default unless $head is TRUE. 
	 */
	protected function insertUriSource($uri, $type='')
	{
		if (! $type )
			$type = pathinfo( $uri, PATHINFO_EXTENSION );
		switch ($type) 
		{
			case 'css':
			case 'style':
			case 'stylesheet':
				$mime = isset($params['mime']) ? $params['mime'] : 'text/css';
				$media = isset($params['media']) ? 'media="'.$params['media'].'" ' : '';
//				if ($uri[0] != '/' && strpos($uri,':') === false)
//					$uri = ccApp::getApp()->getUrlOffset().'css/'.$uri;
				echo '<link rel="stylesheet" '.$media.'type="'.$mime.'" href="'.$uri.'"/>'.PHP_EOL;
			break;
			case 'js':
			case 'javascript':
				$mime = isset($params['mime']) ? " type=\"${params['mime']}\"" : '';
				$defer = /*isset($params['defer']) ? 'defer="defer" ' :*/ '';
//				if ($uri[0] != '/' && strpos($uri,':') === false)
//					$uri = ccApp::getApp()->getUrlOffset().'js/'.$uri;
//				echo '<script'.$mime.' '.$defer.'src="'.$uri.'"></script>'.PHP_EOL;
				echo '<script'.$mime.' '.$defer.'src="'.$uri.'"></script>'.PHP_EOL;

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

	function insertScripts($location)
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
		if ($location == self::SCRIPT_BOTTOM)
		{
			$loaded=false;
			foreach ($this->scripts as $script) {
				if ($script[1] == self::SCRIPT_ONLOAD) {
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
	}

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
	public function render(ccRequest $request) 
	{
		$this->request = $request;
		$this->onPage();
		return true;
	}

	/**
	 * Output page (this calls the subsequent)
	 */
	protected function onPage() {
		$this->onHeaders();
		$this->onPreface();
		$this->onBody();
		$this->onEpilog();
	} // onPage()

	protected function onPreface()
	{
		?>
<!DOCTYPE html>
<html lang="en">
<?php
		$this->onHead();
	} // onPreface()
//	protected function before() {} // Common stuff to do before each action (ccSimpleController)
	protected function onHeaders() {} // HTTP Header output 

	protected function onHead($content=NULL) {
		?>
<head>
<?php
			// onTitle();
//			$this->onMeta();
			// Inclusions css & scripts
			foreach ($this->htmlHeadSources as $uri)
				$this->insertUriSource($uri);

			$this->insertScripts(self::SCRIPT_TOP);

			if (is_callable($content))
				$content();
		?>
</head> 
<?php
	} // Things within the head tag

	/**
	 * Render body content. If $onContent is specified, it will perform 
	 * core content rendering. 
	 * @param  function $onContent Function to perform content rendering.
	 * @return boolean            TRUE, handled, FALSE, rejected
	 */
	protected function onBody()	{
		?>
<body>
<?php
		foreach ($this->preface as $stanza) {
			if (is_callable($stanza))
				$stanza();
			else
				echo $stanza;
		}

		if (method_exists( $this, 'onContent' ))
			$this->onContent();

		foreach ($this->epilog as $stanza) {
			if (is_callable($stanza))
				$stanza();
			else
				echo $stanza;
		}
		foreach ($this->htmlTailSources as $uri)
			$this->insertUriSource($uri);
		$this->insertScripts(self::SCRIPT_BOTTOM);
		?>
</body> 
<?php
	} // Things within the body tags

//	protected function onContent() {} // Core content, called by onBody()
//	protected function after() {} // Common stuff to do before each action (ccSimpleController)

	/**
	 * Define common "template for top of output". 
	 *
	 * - <doctype><hteml><head>...</head><body>
	 *
	 * @see footerTemplate()
	 */
	protected function onEpilog($content=NULL)
	{
		if ($content)
			$content();
		?></html>
		<?php
	}

} // ccHtmlPage

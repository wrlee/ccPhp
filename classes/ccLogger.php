<?php
/**
 * @author
 */

namespace ccPhp;

/**
 * Custom, ccPhp-specific log-levels, augments standard \Psr\Log\LogLevels.
 */
class ccLogLevel
	extends \Psr\Log\LogLevel
{
	const TRACE = 'trace';
//	const FRAMEWORK = 'ccphp';
//	const CCPHP = 'ccphp';
}

/**
 * ccPhp-specific Logger which adds trace() support. Output can go to any of
 * error_log, file, screen.  For screen output, it can be HTML formatted.
 * @todo Allow error_log() header (e.g., date/time) formatting.
 */
class ccLogger
	extends \Psr\Log\AbstractLogger
{
				/** @var array $levels Array of LogLevel strings */
	private static $levels=NULL;
				/** @var bool $bHtml Should HTML formatted output be used? */
	private $bHtml		= true;
				/** @var bool $bScreen Should output go to screen? */
	private $bScreen	= true;
				/** @var bool $bPhpLog Should output go to the error_log()? */
	private $bPhpLog	= false;
				/** @var string $file If not null, target filename for output */
	private $file		= null;

	/**
	 * Initialize LogLevel array of strings.
	 */
	function __construct()
	{
		if (! self::$levels) {
			$reflection = new \ReflectionClass(__NAMESPACE__.'\ccLogLevel');
			self::$levels=array_flip($reflection->getConstants());
		}
	} // __construct()

	/**
	 * Enable HTML formatted output (when available) for content destined to the
	 * screen. This should not affect content sent to logs and files.
	 * @param bool $setting enable/disable setting
	 * @return ccLogger $this, allows chaining of settings methods.
	 */
	function enableHtml(bool $setting=true)
	{
		$this->bHtml = $setting;
		return $this;	// Allow chaining
	}
	/**
	 * Output to Php log (where errors go). NULL goes to stderr.
	 * @param bool $setting enable/disable setting
	 * @return ccLogger $this, allows chaining of settings methods.
	 */
	function enableScreen(bool $setting=true)
	{
		$this->bScreen = $setting;
		return $this;	// Allow chaining
	}
	/**
	 * Output to Php log (where errors go). NULL goes to stderr.
	 * @param bool $setting enable/disable setting
	 * @return ccLogger $this, allows chaining of settings methods.
	 */
	function enablePhpLog(bool $setting=true)
	{
		$this->bPhpLog = $setting;
		return $this;	// Allow chaining
	}
	/**
	 * Define where Php log output should go, e.g., file, stderr, etc. This is a
	 * global setting and will affect where error_log() and errors will be directed.
	 * @param string $setting Filename. If NULL, output is routed to stderr
	 * (i.e., usually the screen).
	 * @return ccLogger $this, allows chaining of settings methods.
	 */
	function setPhpLog(string $setting)
	{
		ini_set('error_log', $setting ? $setting : null);
		return $this;
	}

	/**
	 * @param string|null $filepath Path to file to output. This setting implies
	 *		that output will be active.
	 * @todo Make sure that non-absolute paths are relative to the app-path.
	 * @todo Allow bool value to enable/disable file output w/o setting target. Or
	 * add enableFile($set, $file=null)
	 * @return ccLogger $this, allows chaining of settings methods.
	 */
	function setFile(string $filepath=NULL)
	{
		$this->file = $filepath;
		return $this;	// Allow chaining
	}

	/**
	 * This method will prefix the output with the caller's info. An arbitray
	 * number of arguments can be passed in for output.
	 * @todo Decide how to output parameters (new line for each?)
	 * @todo Decsion to handle HTML is currently in log() and should not be
	 * set, here.
	 */
	public function trace(...$params)
	{
		$message =
		$html = '';
		// Build "header" info of caller
		{
			$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2);
//			$message = print_r($trace,true);
			isset($trace[0]['line']) && $lineno = $trace[0]['line'];
			$trace = $trace[1];

			$this->bHtml && $message .= '<tt>';
			isset($trace['class']) && $message .= $trace['class'];
			isset($trace['type']) && $trace['type'] == '->' && $this->bHtml
				? $message .= '&rarr;'
				: isset($trace['type']) && $message .= $trace['type'];
			if (isset($trace['function'])) {
				$message .= (in_array(substr($trace['function'],0,7),['include','require'] ))
								? $trace['file']
								: $trace['function'].'()';
			}
			isset($lineno) && $message .= '#'.$lineno;
			$message .= ':';
			$this->bHtml && $message .= '</tt>';
		}
		foreach ($params as $param)
			$message .= ($message[-1] == PHP_EOL ? '' : ' ').print_r($param,true);
		$this->debug($message, ['html' => $html]);
	} // trace()

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed  $level 	LogLevel settings.
	 * @param string $message	Text to output.
	 * @param array  $context
	 *
	 * @return void
	 */
	public function log($level, $message, array $context = array())
	{
		if (!isset(self::$levels))
			throw new \Psr\Log\InvalidArgumentException();

		$content = '';
//		if ($context)
//			$content .= print_r($context, true);

		$content .= $message;

		if ($this->file)
			error_log($content.PHP_EOL,3,$this->file);

		$bToStderr = ini_get('error_log');
		$bToStderr = ! $bToStderr && ! is_string($bToStderr);

		if ($this->bPhpLog && ! $bToStderr)
			error_log($content);		// Output to default log file

		// HTML-ify for screen output: Leading spaces and \n
		if ($this->bHtml)
			$content =  nl2br(preg_replace_callback( '/^ +/m',
									function ($matches) {
										return str_repeat('&nbsp;', strlen($matches[0]));
									}
									, $content), false);

		if ($this->bScreen) {
			echo $content;
			if ($this->bHtml) echo '<br>';
			echo PHP_EOL;
		}
		if ($this->bPhpLog && $bToStderr)
			error_log($content);		// Output to default log
	} // log()
} // ccLogger

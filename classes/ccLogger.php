<?php
/**
 * @author
 */

/**
 * ccPhp-specific Logger which adds trace() support.
 */
class ccLogLevel
	extends \Psr\Log\LogLevel
{
	const TRACE = 'trace';
	const FRAMEWORK = 'ccphp';
	const CCPHP = 'ccphp';
}

/**
 *
 */
class ccLogger
	extends \Psr\Log\AbstractLogger
{

	private static $levels=NULL;

	private $bHtml	=true; // Only applies to stdout
	private $bStdout=true;
	private $bPhplog=false;
	private $file	=NULL;

	// Destinations
	const HTML		=1;
	const STDOUT	=2;
	const PHPLOG	=4;
	const FILE		=8;

	function __construct()
	{
		if (! self::$levels) {
			$reflection = new ReflectionClass('ccLogLevel');
			self::$levels=array_flip($reflection->getConstants());

//			print_r(self::$levels);
		}
	}

	function enableHtml(bool $setting=true) {
		$this->bHtml = $setting;
	}
	function enableStdout(bool $setting=true) {
		$this->bStdout = $setting;
	}
	function setFile(string $filepath=NULL) {
		if ($filepath != $this->file) {
			// rest ini_set(logfile)
		}
		$this->file = filepath;
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
			$this->bHtml && $message .= '<tt>';
			isset($trace[1]['class']) && $message .= $trace[1]['class'];
			isset($trace[1]['type']) && $trace[1]['type'] == '->' && $this->bHtml
				? $message .= '&rarr;'
				: $message .= $trace[1]['type'];
			isset($trace[1]['function']) && $message .= $trace[1]['function'].'()';
			isset($trace[0]['line']) && $message .= '#'.$trace[0]['line'];
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
	 * @param mixed  $level
	 * @param string $message
	 * @param array  $context
	 *
	 * @return void
	 */
	public function log($level, $message, array $context = array())
	{
		if (!isset(self::$levels))
			throw new \Psr\Log\InvalidArgumentException();

		$content = '';
		if ($this->bStdout) {
			$content .= $message.PHP_EOL;
//			if ($context)
//				$content .= print_r($context, true);

			if ($this->bStdout)
			{
				if ($this->bHtml) {
					$content = str_replace(
						[PHP_EOL,       ' '],
						['<br>'.PHP_EOL,'&nbsp;'],
						$content);
				}
				echo $content;
			}
			if ($this->file) {
				//echo $content;
			}
			if ($this->bPhplog) {
				//echo $content;
			}
		}
	} // log()
} // ccLogger

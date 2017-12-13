<?php
/**
 * This is an example (and default case) for a class that performs logging. It
 * is attached to, but loosely coupled from the ccApp class.
 *
 * @see http://logging.apache.org/log4php/docs
 * @see http://codefury.net/projects/klogger/
 */
namespace ccPhp;

list ($bb,  $eb,   $bi,  $ei,   $btt,  $ett,   $rarr,   $ldquo,   $rdquo,   $hellip,   $nbsp,   $nl,            $bred,               $ered) =
array('<b>','</b>','<i>','</i>','<tt>','</tt>','&rarr;','&ldquo;','&rdquo;','&hellip;','&nbsp;','<br/>'.PHP_EOL,'<font color="red">','</font>');

/*
interface ccDebugSourceInterface					// Class supports developer
{															// support functions
	function getCaller($depth, $path);			// Get caller label
	function showSource($file,$line=0,$context=-1);	// Display PHP sourcefile
	function showStack(Array $traceback=NULL);  	// Renamed from showTrace()
} // interface ccDebugSourceInterface

interface ccTraceInterface							// Class implements trace output
{
	function setOutput($path=NULL);				// bool|string ON|OFF|destination
//	function setHtml($bEnable=TRUE);				// Enale/disable HTML formatting
//	function setSuppress($bSuppress=TRUE);		// Suppress output
	function tr(...);									// Trace output
	function out($string);							// Unbuffered output
} // interface ccTraceInterface
*/

/**
 * Trace output class.
 */
class ccTrace
//	implements	\Psr\Log\LoggerAwareInterface// setLogger()
//	ccTraceInterface, ccDebugSourceInterface
{
//	use \Psr\Log\LoggerAwareTrait;		//setLogger()

	protected $DefaultLevel = 9;			// Default output level of detail
	protected $ThresholdLevel=5;

	static protected $bSuppress=false;	// Suppress output?

	static protected $bHtml=true;			// Format for HTML?
	static protected $Output=null;		// Destination
	static protected $log=true;			// bool|string

	static protected $logger=null;		// Psr\Log\Logger

	/**
	 * Turn on/off HTML formatting. On is useful if the output is to appear
	 * on the web page; not so useful when output to a terminal console or
	 * file. It also sets a class variable (i.e., static),  $bHttml, to signal
	 * output methods for special output handling.
	 *
	 * @param boolean $bEnable Enable or disable HTML output.
	 */
	static function setHtml($bEnable=TRUE)
	{
		global $bb,$eb, $bi,$ei, $btt,$ett, $rarr,$ldquo,$rdquo,$hellip,$nbsp,$bred,$ered,$nl;
		self::$bHtml = $bEnable;
		if ($bEnable)
		{
list ($bb,  $eb,   $bi,  $ei,   $btt,  $ett,   $rarr,   $ldquo,   $rdquo,   $hellip,   $nbsp,   $nl,            $bred,               $ered) =
array('<b>','</b>','<i>','</i>','<tt>','</tt>','&rarr;','&ldquo;','&rdquo;','&hellip;','&nbsp;','<br/>'.PHP_EOL,'<font color="red">','</font>'.PHP_EOL);
		}
		else
		{
			$bb = $eb =
			$bi = $ei =
			$btt = $ett =
			$bred = $ered = '';
			list ($rarr,$ldquo,$rdquo,$hellip,$nbsp,$nl) =
			array('->', '"',   '"',   '...',  ' ',  PHP_EOL);
		}
	} // setHtml()

	/**
	 * Sets a logger.
	 *
	 * @param Psr\Log\LoggerInterface $logger
	 */
	public static function setLogger(\Psr\Log\LoggerInterface $logger)
	{
		 self::$logger = $logger;
//		 return $this;
	}

	/**
	 * Turns logging output on/off and can define an output file to output to.
	 * @param bool|string $log bool: enable|disable; string: output name
	 * @see self::log()
	 * @deprecated Moving output control to Psr\Log\Logger object.See ccLogger::setPhpLog()
	 */
	function setLogging($log=TRUE)
	{
		if (self::$logger && self::$logger instanceof ccLogger)
		{
			self::$logger->enablePhpLog( !! $log );
			if (is_string($log) || $log === false)
				self::$logger->setPhpLog($log);
		}
		else {
			self::$log = $log;
			if (is_string($log))
			{
				ini_set('error_log',$log);
			}
			else
				ini_set('error_log',NULL);
		}
	} // setLogging()

	/**
	 * Specifies that a file should be used for output. If set, HTML formatting
	 * is automatically disabled (since it rarely makes sense to format for
	 * HTML) in a text file. If HTML output is definitely wanted, setHtml() can
	 * be explictly called after this method.
	 * @param string $filepath Name of the output file (or NULL);
	 * @deprecated Moving output control to Psr\Log\Logger object. See ccLogger::setFile()
	 */
	static function setOutput($filepath=NULL)
	{
		if (self::$logger && self::$logger instanceof ccLogger)
		{
			self::$logger->setFile($filepath);
		}
		else
		{
			self::$Output = $filepath;
			self::setHtml(!self::$Output);
		}
	}
	/**
	 * Force suppression of any output via the output methods in this class.
	 * It is a draconean way to make sure no junk appears in the output and
	 * might only be useful for production releases.
	 * @param boolean $bSuppress [description]
	 */
	static function setSuppress($bSuppress=TRUE)
	{
		self::$bSuppress=$bSuppress;
	}

	/**
	 * Output string to log file, if defined. If a log file is not defined, output
	 * is displayed in stdout. When displayed, it is influenced by the bHtml setting.
	 * Unlike s_out() this output is not affected by the setSuppress() setting.
	 * @param string $msg
	 * @param bool $noNewLine If msg is displayed, the newline can be suppressed
	 */
	function out($msg, $bNoNewline=FALSE)
	{
		if (self::$logger && self::$logger instanceof ccLogger)
			self::$logger->debug($msg);
		else {
echo __METHOD__.'#'.__LINE__.'() deprecated<br>'.PHP_EOL;
			if ($this->Output)
				error_log($msg,3,$this->Output);
			else {
				$this->bHtml && $msg = nl2br($msg);
				if (!$bNoNewline) {
					$this->bHtml && $msg .= '<br/>';
					echo $msg.PHP_EOL;
				}
				else
					echo $msg;
			}
		}

/*		if ($bNoNewline)
		{
			if ($this->bHtml)
				echo nl2br($msg);
			else
				echo $msg;
		}
		else
		{
			if ($this->bHtml)
				echo nl2br($msg).'<br/>'.PHP_EOL;
			else
				echo $msg.PHP_EOL;
		}
*/	} // out()

	/**
	 * Format a line of the trace stack.
	 * @param array $line Content reprsenting a stack frame.
	 * @return string Stack trace line.
	 *
	 * @see debug_traceback() http://us.php.net/manual/en/function.debug-backtrace.php
	 * @see Exception::getTrace() http://us.php.net/manual/en/exception.gettrace.php
	 * @todo Consider moving to separate Trace class
	 */
	static function fmtTraceLine($line)
	{
		global $bb,$eb, $bi,$ei, $btt,$ett, $rarr,$ldquo,$rdquo,$hellip,$nbsp,$nl;

		$out = '';
		if (isset($line['class']))
			$out .= $bb.$line['class'].$eb;
		if (isset($line['object'])
			&& get_class($line['object']) != $line['class'])
			$out .= $bi.'('.get_class($line['object']).')'.$ei;
		if (isset($line['type']))
			$out .= ($line['type'] == '->' ? $rarr : $line['type']);
		$out .= $bb.$line['function'].$eb.'(';
		$first = true;
		if (isset($line['args']))
			foreach ($line['args'] as $arg)
			{
				if (!$first)
					$out .= ',';
				else
					$first = false;
				$out .= $btt;
				if ($arg === NULL)
					$out .= 'null';
				elseif (is_object($arg))
					$out .= get_class($arg);
				elseif (is_string($arg))
					if (self::$bHtml)
						$out .= $ett.$ldquo.$bi.htmlentities($arg).$ei.$rdquo.$btt;
					else
						$out .= $ett.$ldquo.$bi.$arg.$ei.$rdquo.$btt;
				elseif (is_array($arg))
				{
					if ((   $line['function'] == 'call_user_func'
						 || $line['function'] == 'call_user_func_array')
						&& count($arg) == 2)
					{
// $arg[0] is found to be the name of the class, a string, rather than an object...
// did something change?
//echo __METHOD__.'#'.__LINE__.'()<pre>';
//var_dump($arg);
//echo '</pre>'.PHP_EOL;
						$out .= is_string($arg[0]) ? $arg[0] : get_class($arg[0]);
						if (is_object($arg[0]))
							$out .= $rarr;
						else
							$out .= '::';
						$out .= $arg[1].'()'.$ett.','.$hellip;
						break;
					}
					else
					{
					$out .= 'Array(';
					$firstarg = true;
					foreach ($arg as $argkey => $argval)
					{
						if (!$firstarg)
							$out .= ',';
						else
							$firstarg = false;
						$out .= $argkey.$rarr;
						if ($argval === NULL)
							$out .= 'null';
						elseif (is_object($argval))
							$out .= get_class($argval);
						elseif (is_string($argval))
							if (self::$bHtml)
								$out .= $ett.$ldquo.$bi.htmlentities($argval).$ei.$rdquo.$btt;
							else
								$out .= $ett.$ldquo.$bi.$argval.$ei.$rdquo.$btt;
						else
							$out .= $argval;
					}
					$out .= ')';
					}
				}
				else
					$out .= $arg;
				$out .= $ett;
			}
		$out .= ')';
		if (isset($line['file']))
			$out .= ' in '.self::fmtPath($line['file'],$line['line']);
//			$out .= ' in '.$btt.dirname($line['file']).'/'.$ett.$bb.basename($line['file']).$eb.'#'.$line['line'];
// var_dump($line['args']);
		// echo '&nbsp;&nbsp;&nbsp;'.implode(',',$line['args']).'<br/>';
		return $out;
	} // fmtTraceLine()

	/**
	 * Format filepath for output. For HTML output, this will highlight the filename
	 * part of the path and suffix a line number.
	 * (e.g., '/root/part1/.../filename.ext[#line]')
	 * @return string Formatted path name
	 */
	static function fmtPath($path, $line=NULL)
	{
		global $bb,$eb, $bi,$ei, $btt,$ett, $rarr,$ldquo,$rdquo,$hellip,$nbsp,$nl;
		$dirname = dirname($path);
		$dirname === '.' && $dirname = '';
		if ($dirname)
			$dirname = $btt.$dirname.DIRECTORY_SEPARATOR.$ett;
		return $dirname.$bb.basename($path).$eb. ($line ? '#'.$line : '');
	}

	/**
	 * Return formated phrase of caller.
	 * @param int $traceOffset How far back in the callback stack to look.
	 * @param string $path Matching root path to display (ignore stack entries
	 *        that do not match in order to show only "app" sources).
	 *
	 * @return string Caller [filename][ [class{::|->}[{function}()][#{line#}]
	 */
	static function getCaller($traceOffset = 1, $path=NULL)
	{
		global $bb,$eb, $bi,$ei, $btt,$ett, $rarr,$ldquo,$rdquo,$hellip,$nbsp,$nl;

		if (PHP_VERSION_ID >= 50400)
			$trace = debug_backtrace(
					DEBUG_BACKTRACE_IGNORE_ARGS
					|DEBUG_BACKTRACE_PROVIDE_OBJECT
					,$traceOffset+1);
		elseif (PHP_VERSION_ID >= 50306)
			$trace = debug_backtrace(
					DEBUG_BACKTRACE_IGNORE_ARGS
					|DEBUG_BACKTRACE_PROVIDE_OBJECT
				);
		else
			$trace = debug_backtrace(TRUE);
// $debug_caller = FALSE;
		$out = '';

		if ($path && file_exists($path))	// Search first file in app
		{
// if ($debug_caller) $out .= '#'.__LINE__.' '.$path.' ';
			if (substr($path,-1) != DIRECTORY_SEPARATOR)
				$path .= DIRECTORY_SEPARATOR;
			// for ($traceOffset=$traceOffset+1;$traceOffset;$traceOffset--)
				// array_shift($trace);		// Ignore 1st entries & reset offset
			// Ignore 1st entries & search for entries that start w/path
			for ($traceOffset;
				 isset($trace[$traceOffset])
				 && (!isset($trace[$traceOffset]['file'])
					 || strpos($trace[$traceOffset]['file'],$path) !== 0);
				 $traceOffset++);
		}
		elseif (!isset($trace[$traceOffset]['file']))
			$traceOffset += 2;	// If no location, caller was invoked indirectly, so skip indirection call

		$file = $trace[$traceOffset]['file'];
		$line = $trace[$traceOffset]['line'];

		$traceOffset++;			// Get name of function that included

		if (isset($trace[$traceOffset]['class']))	// Set class info, if exists
		{
// if ($debug_caller) $out .= '#'.__LINE__.' ';
			$out .= $bb.$trace[$traceOffset]['class'].$eb;
			if (isset($trace[$traceOffset]['object'])
				&& get_class($trace[$traceOffset]['object']) != $trace[$traceOffset]['class'])
				$out .= $bi.'('.get_class($trace[$traceOffset]['object']).')'.$ei;
			if (isset($trace[$traceOffset]['type']))
				$out .= ($trace[$traceOffset]['type'] == '->' ? $rarr : $trace[$traceOffset]['type']);
		}
		if (isset($trace[$traceOffset]['function'])
			&& !( $trace[$traceOffset]['function'] === 'include'
				 || $trace[$traceOffset]['function'] === 'include_once'
				 || $trace[$traceOffset]['function'] === 'require'
				 || $trace[$traceOffset]['function'] === 'require_once'
				)
			)
			$out .= $bb.$trace[$traceOffset]['function'].$eb.'()'
				  . '#'.$line;

		else						// If no acceptable function name, show path
			$out .= self::fmtPath($file,$line);

		return $out;
	} // getCaller()

	/**
	 * Display PHP source content from a file with line nuumbers.
	 * @param string $file File to display.
	 * @param int $line File line number to highlight
	 */
	static function showSource($file,$line=NULL)
	{
		echo <<<EOD
			<a href="#currentline">{$file}#$line<a><br/>
			<style type="text/css">
			.num {
			float: left;
			color: gray;
			text-align: right;
			margin-right: 3pt;
			padding-right: 3pt;
			border-right: 1px solid gray;}
			</style>
EOD;
		$linenos = range(1, count(file($file)));
		$linenos[$line-1] = '<b id="currentline" style="color:white; background:red">' . $linenos[$line-1] . '</b>';
		echo '<code class="num">', implode('<br/>',$linenos), '</code>';
		highlight_file($file);
/*
	echo $file.'#'.$line.'<br/>';
		if ($line === NULL)
			highlight_file($file);
		else
		{
			$source = highlight_file($file, TRUE);
			$source= explode('<br />', $source);
			$width = strlen(count($source));
			foreach ($source as $lineno => $sline)
				$source[$lineno] = '<code style="padding-right:3px;margin-right:3px; color:black; border-right:1px solid gray;">'.str_replace(' ','&nbsp;',str_pad(($lineno+1), $width)).'</code>'.$sline;
			$source[$line-1] = '<b id="currentline" style="width:100%; background:rgb(200,200,200)">'.$source[$line-1].'</b>';
			print_r(implode('<br/>',$source));
		}
*/
	} // showSource()

	/**
	 * Display traceback call-stack.
	 * @param Array $trace is the trace-back array as generated by
	 *        debug_backtrace() and Exception::getTrace(). If not specified,
	 *        debug_backtrace() is used.
	 * @todo Consider moving to separate Trace class
	 */
	static function showTrace(Array $trace=NULL)
	{
		global $bb,$eb, $bi,$ei, $btt,$ett, $rarr,$ldquo,$rdquo,$hellip,$nbsp,$nl;

		$trace === NULL && $trace=debug_backtrace();

		$entry = 1;
		foreach ($trace as $key => $line)
		{
			if (isset($line['file']) && isset($line['line']))
			{
				self::s_out( ($entry++).'. '.self::fmtTraceLine($line).$nl);
				if (   $line['function'] == 'call_user_func'
					 || $line['function'] == 'call_user_func_array')
				{
// The following line, $key is sometimes 0, which gives an invalid index of -1.
// I hacked a solution preventing the index from being < 0, but we should find
// out why.
// echo __METHOD__.'#'.__LINE__."() key=$key<pre>";
// var_dump($trace);
// echo '</pre>'.PHP_EOL;
					self::s_out( $nbsp.$nbsp.$nbsp.$nbsp.self::fmtTraceLine($trace[max(0,$key-1)]).$nl);
				}
			}
		}
//		self::showSource($trace[0]['file'],$trace[0]['line']);
// echo '</pre>';
	} // showTrace()

	/**
	 * Output content to the log file, prefixed with time/date.
	 * @param  string $string Text to output
	 * @todo  Consider decorating output with timestamp, IP, etc.
	 */
	static function log($string='')
	{
		if (self::$logger && self::$logger instanceof ccLogger)
		{
			self::$logger->debug($string);
		}
		else {
echo __METHOD__.'#'.__LINE__.'() deprecated<br>'.PHP_EOL;
			if (is_string(self::$log))			// If output filename had been set,
				error_log($string);				//   output to log file.
			elseif (self::$log)					// If no output destination
				self::s_out($string.PHP_EOL);	// Just send it to the default place
		}
	} // log()

	/**
	 * Suppressable output.
	 * Output text to stdout or a file (depending on settings). EOL breaks
	 * are not assumed—new line or <br> need to be included, if desired.
	 * @param  string $string Text to output.
	 */
	static function s_out($string)
	{
		if (self::$logger && self::$logger instanceof ccLogger)
		{
			self::$logger->debug($string);
		}
		else
		{
echo __METHOD__.'#'.__LINE__.'() deprecated<br>'.PHP_EOL;
			if (self::$bSuppress)
				return;
	// echo __METHOD__.__LINE__.' '.self::$Output.'<br/>';
			if (self::$Output)
				error_log($string,3,self::$Output);
			else
				echo $string;
		}
	} // s_out()

	/**
	 * Output content prefixed with the source line, file, method, class it is
	 * called from. If the content is not a string, it is interpreted by print_r().
	 *
	 * options: HTML, log, stderr, stdout, formatted, timestamp
	 * @param mixed $msg Item to output.
	 */
	static function tr($msg='')
	{
		global $bb,$eb, $bi,$ei, $btt,$ett, $rarr,$ldquo,$rdquo,$hellip,$nbsp,$nl;
// echo '<pre>';
// debug_print_backtrace();
// echo '</pre>';
		$out = self::getCaller(1);	// Get formatted source-code line decoration

		if ($msg === '' || $msg === NULL || is_string($msg))
			self::s_out($out.' '.$msg.$nl);
		else
		{
			if (self::$bHtml) self::s_out('<span style="display:run-in;">');
			self::s_out($out.' ');
			if (self::$bHtml) self::s_out('</span><pre>');
			self::s_out(print_r($msg,TRUE));
			self::s_out(PHP_EOL);
			if (self::$bHtml) self::s_out('</pre>');
		}
	} // tr()
} // class ccTrace

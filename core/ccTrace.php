<?php


/**
 * This is an example (and default case) for a class that performs logging. It 
 * is attached to, but loosely coupled from the ccApp class. 
 *
 *
 *
 * @see http://logging.apache.org/log4php/docs
 * @see http://codefury.net/projects/klogger/
 * @see 
 */
list ($bb,  $eb,   $bi,  $ei,   $btt,  $ett,   $rarr,   $ldquo,   $rdquo,   $hellip,   $nbsp,   $nl,    $bred,               $ered) =
array('<b>','</b>','<i>','</i>','<tt>','</tt>','&rarr;','&ldquo;','&rdquo;','&hellip;','&nbsp;','<br/>','<font color="red">','</font>'.PHP_EOL);

/*
interface ccDebugSourceInterface					// Class supports developer 
{													// support functions
	function getCaller($depth, $path);				// Get caller label
	function showSource($file,$line=0,$context=-1);	// Display PHP sourcefile
	function showStack(Array $traceback=NULL);  	// Renamed from showTrace()
} // interface ccDebugSourceInterface

interface ccTraceInterface							// Class implements trace output
{
	function setOutput($path=NULL);					// bool|string ON|OFF|destination
//	function setHtml($bEnable=TRUE);				// Enale/disable HTML formatting
//	function setSuppress($bSuppress=TRUE);			// Suppress output
	function tr(...);								// Trace output
	function out($string);							// Unbuffered output
} // interface ccTraceInterface

interface ccLoggerInterface							// Class implements log output
{
	function setLogging($path);						// bool|string ON|OFF|destination
	function log(...);								// Log output
	function out($string);							// Unbuffered output
} // interface ccLoggerInterface
*/

class ccTrace
//	implements ccTraceInterface, ccLoggerInterface, ccDebugSourceInterface
{
	protected $DefaultLevel = 9;// Default output level of detail
	protected $ThresholdLevel=5;
	static protected $bHtml=TRUE;		// Format for HTML?
	static protected $Output=NULL;		// Destination
	static protected $bSuppress=FALSE;	// Suppress output?
	static protected $log=TRUE;			// bool|string
	
	static function setHtml($bEnable=TRUE) 
	{ 
		global $bb,$eb, $bi,$ei, $btt,$ett, $rarr,$ldquo,$rdquo,$hellip,$nbsp,$nl;
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
	 * @param bool|string $log bool: enable|disable; string: output name
	 */
	static function setLogging($log=TRUE)
	{
		self::$log = $log;
		if (is_string($log))
		{
			ini_set('error_log',$log);
		}
		else
			ini_set('error_log',NULL);
	} // setLogging()
	static function setOutput($filepath=NULL) 
	{ 
		self::$Output = $filepath; 
		self::setHtml(!self::$Output);
	}
	static function setSuppress($bSuppress=TRUE)
	{
		self::$bSuppress=$bSuppress;
	}
	
	function out($msg, $bNoNewline=FALSE)
	{
		if ($this->Output)
			error_log($msg,3,$this->Output);
		elseif ($bNoNewline)
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
	} // out()

	/**
	 * Format a line of the trace stack. 
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
						$out .= get_class($arg[0]);
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
	 * Format filepath for output. (e.g., '/root/part1/.../filename.ext[#line]')
	 */
	static function fmtPath($path, $line=NULL)
	{
		global $bb,$eb, $bi,$ei, $btt,$ett, $rarr,$ldquo,$rdquo,$hellip,$nbsp,$nl;
		$dirname = dirname($path);
		if ($dirname == '.') $dirname = '';
		if ($dirname) 
			$dirname = $btt.$dirname.DIRECTORY_SEPARATOR.$ett;
		return $dirname.$bb.basename($path).$eb. ($line ? '#'.$line : '');
	}
	
	/**
	 * Return formated phase of caller.
	 * @param int $traceOffset How far back in the callback stack to look.
	 * @param string $path Matching root path to display (ignore stack entries
	 *        that do not match in order to show only "app" sources).
	 *
	 * [filename][ [class{::|->}[{function}()][#{line#}]
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
	 * Display traceback. 
	 * @param Array $trace is the trace-back array as generated by
	 *        debug_backtrace() and Exception::getTrace().
	 * @todo Consider moving to separate Trace class
	 */
	static function showTrace(Array $trace)
	{
		global $bb,$eb, $bi,$ei, $btt,$ett, $rarr,$ldquo,$rdquo,$hellip,$nbsp,$nl;
		$entry = 1;

		foreach ($trace as $key => $line)
		{
			if (isset($line['file']) && isset($line['line']))
			{
				self::s_out( ($entry++).'. '.self::fmtTraceLine($line).$nl);
				if (   $line['function'] == 'call_user_func'
					 || $line['function'] == 'call_user_func_array')
				{
					self::s_out( $nbsp.$nbsp.$nbsp.$nbsp.self::fmtTraceLine($trace[$key-1]).$nl);
				}
			}
		}
//		self::showSource($trace[0]['file'],$trace[0]['line']);
// echo '</pre>';
	} // showTrace()
	
		
	static function log($string='')
	{
		if (is_string(self::$log))
			error_log($string);
		elseif (self::$log)					// If no output destination
			self::s_out($string.PHP_EOL);	// Just send it to the default place
	} // log()
		
	/**
	 * Output buffer
	 */
	static function s_out($string)
	{
		if (self::$bSuppress)
			return;
//		ini_set('error_log','/home/wrlee/htd.log');
// echo __METHOD__.__LINE__.' '.self::$Output.'<br/>';
		if (self::$Output)
			error_log($string,3,self::$Output);
		else
			echo $string;
	} // s_out()
	
	/**
	 * Output content based on predefined settings. 
	 * options: HTML, log, stderr, stdout, formatted, timestamp
	 */
	static function tr($msg='')
	{
		global $bb,$eb, $bi,$ei, $btt,$ett, $rarr,$ldquo,$rdquo,$hellip,$nbsp,$nl;
// echo '<pre>';
// debug_print_backtrace();		
// echo '</pre>';
		$out = self::getCaller(1);
		
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
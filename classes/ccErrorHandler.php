<?php
/**
 * @author
 */

/**
 *
 */
interface ccErrorHandlerInterface
	extends \Psr\Log\LoggerAwareInterface
{
	const ERROR_CODES = [
		E_ERROR			=> 'Error',			// 1
		E_PARSE			=> 'Parsing Error', // 4
//		E_CORE_ERROR	=> 'Core Error',	// 16
//		E_CORE_WARNING	=> 'Core Warning',	// 32
		E_COMPILE_ERROR	=> 'Compile Error',	// 64
//		E_COMPILE_WARNING => 'Compile Warning',	// 128
		E_WARNING		=> 'Warning',		// 2
		E_NOTICE			=> 'Notice',		// 8
		E_USER_ERROR	=> 'User Error',	// 256
		E_USER_WARNING	=> 'User Warning',	// 512
		E_USER_NOTICE	=> 'User Notice',	// 1024
		E_STRICT		=> 'Strict',			// 2048
		E_RECOVERABLE_ERROR => 'Recoverable Error', // 4096
		E_DEPRECATED => 'Deprecated',				// 8092
		E_USER_DEPRECATED => 'User Deprecated' // 16384
];

	function register($error_types=E_ALL);	// Register defaults

//	function __construct($logger=null); /* trait? */
//	function registerErrorHandler(callable $error_handler=null, $error_types=E_ALL); /* trait? */
//	function registerExceptionHandler(callable $exception_handler=null); /* trait? */

//	function enableStacktrace($setting=true);
//	function onError($errno, $errstr, $errfile, $errline, $errcontext=null);
//	function onException($exception);
} // interface ccErrorHandlerInterface

/**
 * Contain error and exception handlers. This class can be used it different
 * ways:
 * 1. The simplest, instantiate and call register().
 * 2. You can also derive from the class and implement your own handlers.
 * 3. You can use this class as a consiltated mechanism to activate your own
 *    methods, even if they are not part of the class.
 */
trait ccErrorHandlerTrait
{
	use \Psr\Log\LoggerAwareTrait;	// setLogger()

	private $showtrace=false;

	/**
	 * Register error and exception handlers.
	 * @todo Allow variable argument list:
	 * 	(callback $onerror=null, callback $onexception=null, callback $onshutdown=null)
	 *		If $onerror can be an array [ callback, $option ], $option defaults to E_ALL, otherwise
	 */
	function register($error_types=E_ALL)
	{
		if (method_exists($this, 'onError'))
			set_error_handler( [$this, 'onError'], $error_types );

		if (method_exists($this, 'onException'))
			set_exception_handler( [$this, 'onException'] );

		if (method_exists($this, 'onShutdown'))
			register_shutdown_function( [$this, 'onShutdown'] );
	}

	/**
	 * Should call stack be shown after errors and exceptions? This should probably
	 * only be displayed in development mode.
	 * @param bool $setting enable/disable setting
	 * @return ccErrorHandlerInterface $this, allows chaining of settings methods.
	 */
	function enableStacktrace($setting=true)
	{
		$this->showtrace=$setting; return $this;
	}

	/**
	 * Php error handler.
	 *
	 * @param  integer 	$errno		Error number
	 * @param  string 	$errstr     Error text
	 * @param  string 	$errfile    Filename containing error
	 * @param  integer 	$errline   	Line number of error occurance in $errfile
	 * @param  array 		$errcontext Symbol table state when error occurred
	 *											(Deprecated as of v7.2.0)
	 *
	 * @todo Consider throwing exception (caveat, flow of control does not continue)
	 * @todo Add distinction between dev and production modes of output.
	 * @todo Remove dependence on ccTrace
	 */
	function onError($errno, $errstr, $errfile, $errline, $errcontext=null)
	{
		if (error_reporting() & $errno)
		{
			$output = '';			// Prefix
			$severity = isset(self::ERROR_CODES[(int)$errno])
							? self::ERROR_CODES[$errno]
							: "Error($errno)";
			// Single line
			$output .= "<b style='color:red'>$severity</b>: \"$errstr\" in <tt>$errfile#$errline</tt>";

			if (class_exists('ccTrace')) {
				// Screen output:
				$output  = '';			// Prefix
				$output .= "<b style='color:red'>$severity</b>: \"$errstr\"".PHP_EOL
				 			. "        in ".ccTrace::fmtPath($errfile,$errline);
			}

			if ($this->logger)
				$this->logger->log(\Psr\Log\LogLevel::ERROR, $output);
			else
				echo nl2br($output.PHP_EOL,false);

			if ($this->showtrace) $this->showStack();

			return true; // false: propagate to system, true: don't propagate
		}
	} // onError()

	/**
	 * Default exception handler.
	 *
	 * @param  Exception $exception Exception object to report
	 *
	 * @todo Add distinction between dev and production modes of output.
	 * @todo See php.net on tips for proper handling of this handler.
	 * @todo Remove dependence on ccTrace
	 * @todo Finish matching functionality of ccApp::onException()
	 */
	function onException($exception)
	{
		do
		{
			$output  = '';			// Prefix
			$output  .= '<b style="color:red">'.get_class($exception)."</b>";
			if ($exception->getCode() !== 0)
				$output  .= "({$exception->getCode()})";
			if ($exception->getMessage())
				$output .= ": \"{$exception->getMessage()}\"";
			$output .= " in <tt>{$exception->getFile()}#{$exception->getLine()}</tt>";

			if ($this->logger)
				$this->logger->log(\Psr\Log\LogLevel::ERROR, $output);
			else
				echo nl2br($output.PHP_EOL,false);

			if ($this->showtrace)
				if ($this->logger) {
					if (class_exists('ccTrace'))
						ccTrace::showTrace($exception->getTrace()); // Missing first stack frame
					else {
						// Non ccTrace-reliant version
						$output  = '<pre style="margin-left:20px; margin-top:0">';
						$output .= $exception->getTraceAsString();
						$output .= '</pre>'.PHP_EOL;
						$this->logger->log( \Psr\Log\LogLevel::ERROR, $output );
					}
				}
				else
				{
					echo '<pre style="margin-left:20px; margin-top:0">'.PHP_EOL;
					echo nl2br($exception->getTraceAsString(),false);
					echo '</pre>'.PHP_EOL;
				}
		} while ($exception = $exception->getPrevious());

		return false;
	} // onException()

	/**
	 * Shutdown handler.
	 * Capture last error to report errors that are not normally trapped by error-
	 * handling functions, e.g., fatal and parsing errors.
	 * @todo Activate only for debug mode.
	 */
	public function onShutdown()
	{
		$err=error_get_last();
		switch ($err['type'])
		{
			case E_WARNING:
			case E_NOTICE:
			case E_USER_ERROR:
			case E_USER_WARNING:
			case E_USER_NOTICE:
				return false;
				break;

			case E_COMPILE_ERROR:
			case E_PARSE:
			default:
				return ccApp::onError($err['type'], $err['message'], $err['file'], $err['line'], $GLOBALS);
		}
  //	trigger_error($err['message'],$err['type']);
} // onShutdown()

	/**
	 * Display call-stack.
	 */
	protected function showStack()
	{
		if ($this->logger) {
			if (class_exists('ccTrace')) {
				$stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
//				array_shift($stack);
				array_shift($stack);
				ccTrace::showTrace($stack);// Display stack.
			}
			else { 								// Non ccTrace-reliant version
				$output  = '<pre style="margin-left:20px; margin-top:0">'.PHP_EOL;
				$output .= $this->debug_string_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
				$output .= '</pre>'.PHP_EOL;
				$this->logger->log( \Psr\Log\LogLevel::ERROR, $output );
			}
		}
		else {
			echo '<pre style="margin-left:20px; margin-top:0">'.PHP_EOL;
			echo $this->debug_string_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			echo '</pre>'.PHP_EOL;
		}
	}
	/**
	 * Return call-stack as formatted by debug_print_backtrace().
	 */
	private function debug_string_backtrace($options=0) {
		ob_start();
		debug_print_backtrace($options);
		$trace = ob_get_contents();
		ob_end_clean();

		// Remove first items from backtrace to show relevant entries
		$trace = preg_replace('/^(#[01]\s+'."[^\n]*\n)+/m", '', $trace, 1);
													// Renumber backtrace items.
		$trace = preg_replace_callback('/^#(\d+)/m',
					function (array $matches) {
						return '#'.($matches[1]-1);
					}
					,$trace);

		return $trace;
    }
} // trait ccErrorHandlerTrait

/**
 * Automatically
 */
class ccErrorHandler
	implements ccErrorHandlerInterface
	// , \Psr\Log\LoggerAwareInterface
{
	use ccErrorHandlerTrait;

	function __construct($logger=null,$error_types=E_ALL)
	{
		if ($logger) $this->logger = $logger;

		$this->register($error_types);
	}

} // class ccErrorHandler

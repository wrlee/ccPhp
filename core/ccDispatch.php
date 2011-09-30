<?php 

// error_reporting(E_ALL|E_STRICT);
// error_reporting(ini_get('error_reporting')|E_STRICT);
set_error_handler(Array('ccDispatch','onError'));
set_exception_handler(Array('ccDispatch','onException'));

/**
 * @todo Move error handling ccError class and refer through ccApp
 */
class ccDispatch
{
	protected $filterChainPrefix = Array();
	protected $controllerChain = Array();
	protected $controllerChainSuffix = Array();
	protected $error404 = NULL;
	
	function __construct()
	{
	}
	
	/**
	 * Add controller to handle dispatches of requests.
	 * @param ccController|string $controller Controller object or name of class.
	 *        If string, it is instantiated at invocation time. 
	 */
	function addPageRenderer($controller)
	{
		$this->controllerChain[] = $controller;
		if (!is_string($controller) && !($controller instanceof ccPageInterface))
			throw ErrrorException(get_class($controller).' rendering object needs to implement ccPageInterface.');
		return $this;
	} // function addPageRenderer()
	
	/**
	 * Add filter to modify request object.
	 * @param ccFilter $filter Filter object
	 */
	function addFilter(ccDispatchFilter $filter, $suffix=FALSE)
	{
		if ($suffix)
			$this->filterChainSuffix[] = $filter;
		else
			$this->filterChainPrefix[] = $filter;
		return $this;
	} // function addController
	
	/**
	 * Dispatch "request" for processing, first through the chain of filters
	 * (if any) then through the list of controllers (of which there should be 
	 * at least one.  If no controller handles the request, then invoke the 404
	 * handler.
	 * @param ccRequest $request The request object that represents the current req't
	 */
	function dispatch(ccRequest $request)
	{
		foreach ($this->filterChainPrefix as $filter)
		{
			$filter->filterRequest($request); // Can modify request
		}

		foreach ($this->controllerChain as $key => $controller)
		{
			if (is_string($controller))
			{
				$this->controllerChain[$key] = new $controller;
				if (!is_string($this->controllerChain[$key]) && !($this->controllerChain[$key] instanceof ccPageInterface))
					throw ErrrorException($controller.' rendering object needs to implement ccPageInterface.');
				$controller = $this->controllerChain[$key];
			}
// echo __METHOD__.'#'.__LINE__.' '.get_class($controller).'<br/>';			
			if ($controller->render(clone $request))
				return;
		}
// echo __METHOD__.'#'.__LINE__.' '.get_class($controller).'<br/>';			
		if ($this->error404)
		{
			if (is_string($this->error404))
				$this->error404 = new $this->error404;
			if ($this->getDebug() && !($this->error404 instanceof ccPageInterface))
			{
				trigger_error(get_class($this->error404).' does not implement ccPageInterface', E_WARNING);
			}
			call_user_func(array($this->error404,'render'), $request);
		}
		else 
			$this->on404($request);
	} // dispatch()

	function getDebug()
	{
		return ccApp::getApp()->getDevMode() == ccApp::MODE_DEVELOPMENT;
	}
	
	/**
	 * Default 404 handler.
	 * @param ccRequest $request The current request
	 */
	protected function on404(ccRequest $request)
	{
//		http_response_code(404);
		if (!headers_sent())
			header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
		?>
		<hr/>
		<?php print $_SERVER['SCRIPT_URI'] ?>
		<h1>404 Not Found</h1>
		This is not the page you are looking for.<hr/>
		<?php 
		if ($this->getDebug())
		{
			$trace = debug_backtrace();		// Get whole stack list
			array_shift($trace);			// Ignore this function
			self::showTrace($trace);		// Display stack.
		}
//		exit();
	} // on404()

	/**
	 * @todo Consider moving this to ccApp
	 * @todo Consider throwing exception (caveat, flow of control does not continue)
	 * @todo Add distinction between dev and production modes of output.
	 */
	static function onError($errno, $errstr, $errfile, $errline, $errcontext)
	{
//		throw new ErrorException($errstr, $errno,0,$errfile,$errline);
		if (ini_get('error_reporting') & $errno)
		{
			$errortype[E_WARNING] = 'Warning';
			$errortype[E_NOTICE] = 'Notice';
			$errortype[E_USER_ERROR] = 'User Error';
			$errortype[E_USER_WARNING] = 'User Warning';
			$errortype[E_USER_NOTICE] = 'User Notice';
			$errortype[E_STRICT] = 'Strict';
			$errortype[E_RECOVERABLE_ERROR] = 'Recoverable Error';
			$errortype[E_USER_DEPRECATED] = 'User Deprecated';
			error_log("$errortype[$errno]: $errstr in $errfile#$errline",0);
			print "<br/><b><font color='red'>$errortype[$errno]</font>: $errstr</b>\n        in $errfile#$errline<br/>".PHP_EOL;
//			echo '<pre>';
//			var_dump($errcontext);
//			echo '</pre>';
			$trace = debug_backtrace();		// Get whole stack list
			array_shift($trace);			// Ignore this function
			self::showTrace($trace);		// Display stack.
//			die();
			return TRUE;
		}
		else
			return FALSE; 	// chain to normal error handler
	} // onError()
	
	/**
	 * @todo Consider moving this to ccApp
	 * @todo Add distinction between dev and production modes of output.
	 * @todo See php.net on tips for proper handling of this handler.
	 */
	static function onException($exception)
	{
		try
		{
			print get_class($exception).' '.$exception->getMessage().' in '.$exception->getFile().'#'.$exception->getLine().'<br/>'.PHP_EOL;
			self::showTrace($exception->getTrace());
//			echo '<pre>';
//			print $exception->getTraceAsString();
//			echo '</pre>';
///			die();
		}
		catch (Exception $e)
		{
			print get_class($e)." thrown within the exception handler. Message: ".$e->getMessage()." on line ".$e->getLine();
		}
	} // onException()
	
	/**
	 * @param string $url Send rediret to browser
	 * @todo Forward qstring, post  variables, and cookies. 
	 * @todo Allow "internal" redirect that does not return to the client.
	 */
	function redirect($url)
	{
		if (!headers_sent())
		{
			header('Location: '.$url);
			echo "Redirecting to {$url} via header&hellip;";
		}
		else
		{ 
			echo <<<EOD
			Redirecting to {$url} via scripting&hellip;
			<script>window.top.location.href="$url"</script>
EOD;
		}
		exit();
	} // redirect()
	
	/**
	 * Handle 404 (page not found errors).
	 * @param ccPageInterface|$string $page404 The object or classname that would
	 *              render a 404 page.
	 */
	function set404Page($error404page)
	{
		$this->error404 = $error404page;
		return $this;
	} // set404handler()
	
	/**
	 * Set the error handler.
	 * @param callback $function The name of the callback function or array, when
	 *                           the callback is a class or object method.
	 * The callback function should look like: 
	 *   handler ( int $errno , string $errstr [, 
	 *             string $errfile [, int $errline [, array $errcontext ]]] )
	 * @see http://www.php.net/manual/en/function.set-error-handler.php
	 * @todo Probably don't need this since it is chained to exceptions.
	 */
	function setErrorHandler($function)
	{
		set_error_handler($function);
		return $this;
	} // setErrorHandler()
	
	/**
	 * Set the exception handler.
	 * @param callback $function The name of the callback function or array, when
	 *                           the callback is a class or object method.
	 * The callback function should look like: 
	 *   handler ( Exception $e )
	 * @see http://www.php.net/manual/en/function.set-error-handler.php
	 */
	function setExceptionHandler($function)
	{
		set_error_handler($function);
		return $this;
	} // setErrorHandler()
	
	static function showTrace(Array $trace)
	{
//		array_shift($trace);	// Ignore this method.
// echo '<pre>';
// echo __METHOD__.' ';
// var_dump($trace);
		$entry = 1;
		foreach ($trace as $key => $line)
		{
			if (isset($line['file']) && isset($line['line']))
			{
				echo ($entry++).'. '.self::showTraceline($line).'<br/>';
				if (   $line['function'] == 'call_user_func'
					 || $line['function'] == 'call_user_func_array')
				{
					echo '&nbsp;&nbsp;&nbsp;&nbsp;'.self::showTraceline($trace[$key-1]).'<br/>';
				}
			}
		}
// echo '</pre>';
	} // showTrace()
	
	protected static function showTraceLine($line)
	{
// echo __METHOD__.' ';
// var_dump($line);
// if (is_string($line))
	// return $line.'<br/>';
		$out = '<b>'.$line['class'].'</b>'.($line['type'] == '->' ? '&rarr;' : $line['type']).'<b>'.$line['function'].'</b>(';
		$first = true;
		foreach ($line['args'] as $arg)
		{
			if (!$first)
				$out .= ',';
			else
				$first = false;
			$out .= '<tt>';
			if ($arg === NULL)
				$out .= 'null';
			elseif (is_object($arg))
				$out .= get_class($arg);
			elseif (is_string($arg))
				$out .= '</tt>&ldquo;<i>'.$arg.'</i>&rdquo;<tt>';
			elseif (is_array($arg))
			{
				if ((   $line['function'] == 'call_user_func'
					 || $line['function'] == 'call_user_func_array')
					&& count($arg) == 2)
				{
					$out .= get_class($arg[0]);
					if (is_object($arg[0]))
						$out .= '&rarr;';
					else 
						$out .= '::';
					$out .= $arg[1].'()</tt>,&hellip;';
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
					$out .= $argkey.'&rArr;';
					if ($argval === NULL)
						$out .= 'null';
					elseif (is_object($argval))
						$out .= get_class($argval);
					elseif (is_string($argval))
						$out .= '</tt>&ldquo;<i>'.$argval.'</i>&rdquo;<tt>';
					else
						$out .= $argval;
				}
				$out .= ')';
				}
			}
			else
				$out .= $arg;
			$out .= '</tt>';
		}
		$out .= ')';
		if ($line['file'])
			$out .= ' in <tt>'.dirname($line['file']).'/</tt><b>'.basename($line['file']).'</b>#'.$line['line'];
		// echo ') in <tt>'.dirname($line['file']).'/<b>'.basename($line['file']).'</b>#</tt>'.$line['line'].'<br/>';
// var_dump($line['args']);
		// echo '&nbsp;&nbsp;&nbsp;'.implode(',',$line['args']).'<br/>';
		return $out;
	}

} // class ccDispatch
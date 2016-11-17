<?php
//!namespace ccPhp;
/**
 * @package ccPhp
 *
 * A ccPageInterface can throw this exception when it is "successfully" 
 * handling a page rendering but its handling results in a non-200 (and non-
 * 404 result-code. 
 * 
 * This should, generally, _not_ be used to throw a 404 "page not found"
 * result code since throwing this exception (or any exception) will 
 * circumvent other ccPageInterface implementations that might be able to 
 * handle the URL. That is, 404s should be implied by returning false from 
 * the render handler of the ccPageInterface, rather than throwing this
 * exception as a 404.
 */
class ccHttpStatusException extends \Exception
{
	protected $status;
	protected $location;	// For 30x redirections exceptions

	function __construct( $status, $message=NULL, Exception $previous = NULL )
	{
		$this->status = $status;
		// http://www.w3schools.com/tags/ref_httpmessages.asp
		switch ($status) 
		{
			// Only a part of the request has been received by the server, but
			// as long as it has not been rejected, the client should continue
			// with the request
			case 100: $message = 'Continue';
				break;
			// The server switches protocol
			case 101: $message = 'Switching Protocols';
				break;
			// The request is OK
			case 200: $message = 'OK';
				break;
			// The request is complete, and a new resource is created 
			case 201: $message = 'Created';
				break;
			// The request is accepted for processing, but the processing is not
			// complete
			case 202: $message = 'Accepted';
				break;
			case 203: $message = 'Non-authoritative Information';	 
				break;
			case 204: $message = 'No Content';	 
				break;
			case 205: $message = 'Reset Content';	 
				break;
			case 206: $message = 'Partial Content';
				break;

			case 304:  $message = 'Not Modified'; 
				break;

			case 300: case 301: case 302: case 303: 
			case 305: case 306: case 307: 
				if ($message === NULL)
					throw new ErrorException('Location not specified for redirection' );
				$this->location = $message;
				switch ($status)
				{
					// A link list. The user can select a link and go to that
					// location. Maximum five addresses  
					case 300: $message = 'Multiple Choices';
						break;
					// The requested page has moved to a new url 
					case 301: $message = 'Moved Permanently';
						break;
					// The requested page has moved temporarily to a new url 
					case 302: $message = 'Found';
						break;
					// The requested page can be found under a different url 
					case 303: $message = 'See Other';
						break; 
					case 305: $message = 'Use Proxy';	 
						break;
//					case 306: $message = 'Unused'; // This code was used in a previous version. It is no longer used, but the code is reserved
//						break;
					case 307: $message = 'Temporary Redirect';
						break;
				}
				break;
			// The server did not understand the request
			case 400: $message = 'Bad Request';
				break;
			// The requested page needs a username and a password
			case 401: $message = 'Unauthorized';
				break;
			// You can not use this code yet
			case 402: $message = 'Payment Required';
				break;
			// Access is forbidden to the requested page
			case 403: $message = 'Forbidden';
				break;
			// The server can not find the requested page
			case 404: $message = 'Not Found';
				break;
			// The method specified in the request is not allowed
			case 405: $message = 'Method Not Allowed';
				break;
			// The server can only generate a response that is not accepted by
			// the client
			case 406: $message = 'Not Acceptable';
				break;
			// You must authenticate with a proxy server before this request can be served
			case 407: $message = 'Proxy Authentication Required';
				break;
			// The request took longer than the server was prepared to wait
			case 408: $message = 'Request Timeout';
				break;
			// The request could not be completed because of a conflict
			case 409: $message = 'Conflict';
				break;
			// The requested page is no longer available 
			case 410: $message = 'Gone';
				break;
			// The "Content-Length" is not defined. The server will not accept
			// the request without it 
			case 411: $message = 'Length Required';
				break;
			// The precondition given in the request evaluated to false by the server
			case 412: $message = 'Precondition Failed';
				break;
			// The server will not accept the request, because the request
			// entity is too large
			case 413: $message = 'Request Entity Too Large';
				break;
			// The server will not accept the request, because the url is too
			// long. Occurs when you convert a "post" request to a "get" request
			// with a long query information 
			case 414: $message = 'Request-url Too Long';
				break;
			// The server will not accept the request, because the media type is
			// not supported 
			case 415: $message = 'Unsupported Media Type';
				break;
//			case 416: $message = '';	 
//				break;
			case 417: $message = 'Expectation Failed';
				break;
			// The request was not completed. The server met an unexpected condition
			case 500: $message = 'Internal Server Error';
				break;
			// The request was not completed. The server did not support the functionality required
			case 501: $message = 'Not Implemented';
				break;
			// The request was not completed. The server received an invalid
			// response from the upstream server
			case 502: $message = 'Bad Gateway';
				break;
			// The request was not completed. The server is temporarily
			// overloading or down
			case 503: $message = 'Service Unavailable';
				break;
			// The gateway has timed out
			case 504: $message = 'Gateway Timeout';
				break;
			// The server does not support the "http protocol" version
			case 505: $message = 'HTTP Version Not Supported';
				break;
			default:
				$message = $status.': HTTP Status Exception';
		}
		if (PHP_VERSION_ID >= 50300)
			parent::__construct($message, $status, $previous);
		else
			parent::__construct($message, $status);
	} // __construct()

	function getLocation()
	{
		return $this->location;
	}

/*	function getStatus()
	{
		return $this->status;
	}
*/
} // class ccHttpStatusException
<?php

class ccHttpStatusException extends Exception
{
	protected $status;
	protected $location;	// For 30x exceptions
	
	function __construct( $status, $message=NULL, Exception $previous = NULL )
	{
		$this->status = $status;
		// http://www.w3schools.com/tags/ref_httpmessages.asp
		switch ($status) 
		{
			case 100: $message = 'Continue';	// Only a part of the request has been received by the server, but as long as it has not been rejected, the client should continue with the request
				break;
			case 101: $message = 'Switching Protocols';	// The server switches protocol
				break;
			case 200: $message = 'OK';	// The request is OK
				break;
			case 201: $message = 'Created';	// The request is complete, and a new resource is created 
				break;
			case 202: $message = 'Accepted';	// The request is accepted for processing, but the processing is not complete
				break;
			case 203: $message = 'Non-authoritative Information';	 
				break;
			case 204: $message = 'No Content';	 
				break;
			case 205: $message = 'Reset Content';	 
				break;
			case 206: $message = 'Partial Content';
				break;

			case 300: case 301: case 302: case 303: 
			case 304: case 305: case 306: case 307: 
				if ($message === NULL)
					throw new ErrorException('Location not specified for redirection' );
				$this->location = $message;
				switch ($status)
				{
					case 300: $message = 'Multiple Choices';	// A link list. The user can select a link and go to that location. Maximum five addresses  
						break;
					case 301: $message = 'Moved Permanently';	// The requested page has moved to a new url 
						break;
					case 302: $message = 'Found';	// The requested page has moved temporarily to a new url 
						break;
					case 303: $message = 'See Other';	// The requested page can be found under a different url 
						break;
					case 304: $message = 'Not Modified';	 
						break;
					case 305: $message = 'Use Proxy';	 
						break;
					case 306: $message = 'Unused';	// This code was used in a previous version. It is no longer used, but the code is reserved
						break;
					case 307: $message = 'Temporary Redirect';
						break;
				}
				break;
			case 400: $message = 'Bad Request';	// The server did not understand the request
				break;
			case 401: $message = 'Unauthorized';	// The requested page needs a username and a password
				break;
			case 402: $message = 'Payment Required';	// You can not use this code yet
				break;
			case 403: $message = 'Forbidden';	// Access is forbidden to the requested page
				break;
			case 404: $message = 'Not Found';	// The server can not find the requested page
				break;
			case 405: $message = 'Method Not Allowed';	// The method specified in the request is not allowed
				break;
			case 406: $message = 'Not Acceptable';	// The server can only generate a response that is not accepted by the client
				break;
			case 407: $message = 'Proxy Authentication Required';	// You must authenticate with a proxy server before this request can be served
				break;
			case 408: $message = 'Request Timeout';	// The request took longer than the server was prepared to wait
				break;
			case 409: $message = 'Conflict';	// The request could not be completed because of a conflict
				break;
			case 410: $message = 'Gone';	// The requested page is no longer available 
				break;
			case 411: $message = 'Length Required';	// The "Content-Length" is not defined. The server will not accept the request without it 
				break;
			case 412: $message = 'Precondition Failed';	// The precondition given in the request evaluated to false by the server
				break;
			case 413: $message = 'Request Entity Too Large';	// The server will not accept the request, because the request entity is too large
				break;
			case 414: $message = 'Request-url Too Long';	// The server will not accept the request, because the url is too long. Occurs when you convert a "post" request to a "get" request with a long query information 
				break;
			case 415: $message = 'Unsupported Media Type';	// The server will not accept the request, because the media type is not supported 
				break;
//				case 416: $message = '';	 
//					break;
			case 417: $message = 'Expectation Failed';
				break;
			case 500: $message = 'Internal Server Error';	// The request was not completed. The server met an unexpected condition
				break;
			case 501: $message = 'Not Implemented';	// The request was not completed. The server did not support the functionality required
				break;
			case 502: $message = 'Bad Gateway';	// The request was not completed. The server received an invalid response from the upstream server
				break;
			case 503: $message = 'Service Unavailable';	// The request was not completed. The server is temporarily overloading or down
				break;
			case 504: $message = 'Gateway Timeout';	// The gateway has timed out
				break;
			case 505: $message = 'HTTP Version Not Supported';	// The server does not support the "http protocol" version
				break;
			default:
				$message = $status.': HTTP Status Exception';
		}
		parent::__construct($message, 0, $previous);
	} // __construct()
	
	function getLocation()
	{
		return $this->location;
	}
	
	function getStatus()
	{
		return $this->status;
	}
} // class ccHttpStatusException
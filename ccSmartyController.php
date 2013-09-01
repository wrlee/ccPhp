<?php

/**
 * A very simple Smarty template enabler. Add this to your project and add any *.tpl files
 * to the templates/ directory that correspond to the name of the URL that it corresponds to. 
 */
class ccSmartyController
	extends ccSmartyBaseController
{
	public function __construct()
	{
		$this->initSmarty();
	}
} // class uspsController

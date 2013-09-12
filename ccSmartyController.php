<?php

/**
 * A very simple Smarty template enabler. Add this to your project and add any 
 * *.tpl files to the templates/ directory that correspond to the name of the 
 * URL that it corresponds to. Derivations of this class allow methods to 
 * supercede template names of the same name. Those methods can call the 
 * display() method to display smarty templates or perform more advanced
 * features by refering the $this->smarty object directly. If either a method 
 * or a template is found to handle the URL, any exising begin() method is 
 * called, first. 
 */
class ccSmartyController
	extends ccSmartyBaseController
{
	/**
	 * Initiate the Smarty object, making it available across the class and
	 * automatically triggering the search for templates. This is what makes 
	 * this class so simple; pages can be implemented via a template or a 
	 * derived class's methods. 
	 */
	public function __construct()
	{
		$this->initSmarty();
	}
} // class uspsController

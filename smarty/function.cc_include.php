<?php
/**
 * @package Smarty
 */
/**
 * Decorate include spec with proper path info. 
 * @param type 		mime-type; usually optional
 * @param defer 	Applies to scripts only
 * @param media 	Applies to CSS only
 * @param minify 	Not currently supported
 * 
 * type=css files=file1,file2,... minify= mime= media= <br/>
 * type=js files=file1,file2,... minify= mime= 			
 *
 * @todo Support favicon and other types of inclusions
 * @todo Support singular 'file' attribute
 * @todo Check browser version to determine which icon to use.
 * @todo Create path relative to current page (not always from root)
 */
function smarty_function_cc_include(
	array $params, 
	Smarty_Internal_Template $template)
{
	// Load required plugins
//	$template->smarty->loadPlugin('smarty_shared_make_timestamp');

	$type = isset($params['type']) ? strtolower($params['type']) : '';
	$files = explode(',',$params['files']);
	$bMinify = isset($params['minify']);
//	ccApp::tr($params);
	
	$out = '';
	switch ($type)
	{
	case 'css':
	case 'style':
	case 'stylesheet':
		$mime = isset($params['mime']) ? $params['mime'] : 'text/css';
		$media = isset($params['media']) ? 'media="'.$params['media'].'" ' : '';
		if ($bMinify)
			$out .= '<link rel="stylesheet" '.$media.'type="'.$mime.'" href="'.implode(',',$files).'" />';
		else
		{
			$last = end($files);
			foreach ($files as $file)
			{
				$nl = ($file !== $last) ? $nl = PHP_EOL : $nl ='';
				if ($file[0] != '/')
					$file = ccApp::getApp()->getUrlOffset().'css/'.$file;
				$out .= '<link rel="stylesheet" '.$media.'type="'.$mime.'" href="'.$file.'"/>'.$nl;
			}
		}
		break;
	case 'js':
	case 'javascript':
		$mime = isset($params['mime']) ? " type=\"${params['mime']}\"" : '';
		$defer = /*isset($params['defer']) ? 'defer="defer" ' :*/ '';
		// if ($bMinify)
		// else
			foreach ($files as $file)
			{
				$out .= '<script'.$mime.' '.$defer.'src="'.$file.'"></script>'.PHP_EOL;
			}
		break;

	// Discern the type of include to be used.
	default:	
		$mime = isset($params['mime']) ? " type=\"${params['mime']}\"" : '';
		$defer = /*isset($params['defer']) ? 'defer="defer" ' :*/ '';
		$media = isset($params['media']) ? ' media="'.$params['media'].'"' : '';
		foreach ($files as $file)
		{
			$type=pathinfo($file, PATHINFO_EXTENSION);
			switch ($type) {
				case 'css':
					if ($file[0] != '/' && strpos($file,':') === false)
						$file = ccApp::getApp()->getUrlOffset().'css/'.$file;
					$out .= '<link type="text/css" rel="stylesheet"'.$media.$mime.' href="'.$file.'"/>'.PHP_EOL;
				break;
				case 'js':
					if ($file[0] != '/' && strpos($file,':') === false)
						$file = ccApp::getApp()->getUrlOffset().'js/'.$file;
					$out .= '<script'.$mime.' '.$defer.'src="'.$file.'"></script>'.PHP_EOL;
				break;
				case 'ico':
				case 'gif':		// Does not work in IE
				case 'png':		// Does not work in IE
				case 'jpg':		// Does not work in IE
				case 'jpeg':	// Does not work in IE	
				case 'svg':		// Only works in Opera
					if ($file[0] != '/' && strpos($file,':') === false)
						$file = ccApp::getApp()->getUrlOffset().$file;
					$out .= '<link rel="shortcut icon"'.$media.$mime.' href="'.$file.'"/>'.PHP_EOL;			
					break;
				default:
					throw new SmartyCompilerException('Unexpected or unspecified type \''.$type.'\'');
			}
		}

	}
	return $out;
}
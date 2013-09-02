<?php
/**
 * @package Smarty
 */
/**
 * Decorate include spec with proper path info. 
 * type 	mime-type; usually optional
 * defer 	Applies to scripts only
 * media 	Applies to CSS only
 * minify 	Not currently supported
 * 
 * type=css files=file1,file2,... minify= mime= media= 
 * type=js files=file1,file2,... minify= mime= 
 *
 * @todo Support favicon and other types of inclusions
 * @todo Support singular 'file' attribute
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
//					$nl = ($file !== $last) ? $nl = PHP_EOL : $nl ='';
					if ($file[0] != '/' && strpos($file,':') === false)
						$file = ccApp::getApp()->getUrlOffset().'css/'.$file;
					$out .= '<link rel="stylesheet"'.$media.$mime.' href="'.$file.'"/>'.PHP_EOL; // .$nl;
				break;
				case 'js':
					if ($file[0] != '/' && strpos($file,':') === false)
						$file = ccApp::getApp()->getUrlOffset().'js/'.$file;
					$out .= '<script'.$mime.' '.$defer.'src="'.$file.'"></script>'.PHP_EOL;
				break;
				default:
					throw new SmartyCompilerException('Unexpected or unspecified type \''.$type.'\'');
			}
		}

	}
	return $out;
}
			
<?php

/**
 * type=css files=file1,file2,... minify= mime= media= 
 * type=js files=file1,file2,... minify= mime= 
 */
function smarty_function_cc_include(
	array $params, 
	Smarty_Internal_Template $template)
{
	// Load required plugins
//	$template->smarty->loadPlugin('smarty_shared_make_timestamp');

	$type = strtolower($params['type']);
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
		$mime = isset($params['mime']) ? $params['mime'] : 'text/javascript';
		$defer = /*isset($params['defer']) ? 'defer="defer" ' :*/ '';
		// if ($bMinify)
		// else
			foreach ($files as $file)
			{
				$out .= '<style type="'.$mime.'" '.$defer.'src="'.$file.'" />'.PHP_EOL;
			}
		break;
	default:
		throw new SmartyCompilerException('Unexpected or unspecified type "'.$type.'"');
	}
	return $out;
}
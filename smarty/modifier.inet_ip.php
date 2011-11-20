<?php

/**
 * Convert int --> xxx.xxx.xxx.xxx
 */
function smarty_modifier_inet_ip($inet_ip)
{
	if (strpos('.',$inet_ip) === FALSE)
		return inet_ntoa($inet_ip);
	else
		return inet_aton($inet_ip);
} // smarty_inet_ntoa()


/*
 * @param string $str_ip IP addr as a string, e.g., "127.0.0.1"
 * @returns int IP
 *
function inet_aton($str_ip) 
{
    if ($str_ip == "") 
	{
        return 0;
    } else 
	{
        $ips = explode('.', "$str_ip");
        return ($ips[3] + $ips[2] * 256 + $ips[1] * 256 * 256 + $ips[0] * 256 * 256 * 256);
    }
} // inet_aton()
*/

/**
 * @param string $int_ip IP addr as an integer
 * @returns string IP addr string, e.g., "127.0.0.1"
 */
function inet_ntoa($int_ip) 
{
	$ip = array();
	$ip[3] = $int_ip % 256;
	$int_ip = floor($int_ip / 256);
	$ip[2] = $int_ip % 256;
	$int_ip = floor($int_ip / 256);
	$ip[1] = $int_ip % 256;
	$int_ip = floor($int_ip / 256);
	$ip[0] = $int_ip;

	return $ip[0].'.'.$ip[1].'.'. $ip[2].'.'. $ip[3];
} // inet_aton()


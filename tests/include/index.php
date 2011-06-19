<?php

require(dirname(__FILE__) . '/validate.php');
require(dirname(__FILE__) . '/harness.php');
require(dirname(__FILE__) . '/list.php');
require(dirname(__FILE__) . '/specialtheme.php');

error_reporting(E_ALL);
set_error_handler('error_handler');

function error_handler($type, $string, $file, $line)
{
	if (error_reporting() & $type)
	{
		$pretty_file = str_replace(dirname(dirname(dirname(__FILE__))), '...', $file);
		throw new ErrorException($pretty_file . '[' . $line . ']: ' . $string, 0, $type, $file, $line);
	}
	return false;
}

?>
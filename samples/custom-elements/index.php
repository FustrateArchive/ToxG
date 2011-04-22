<?php

require(dirname(dirname(__FILE__)) . '/include.php');
require(dirname(__FILE__) . '/theme.php');

$theme = new MyTheme(dirname(__FILE__), dirname(__FILE__));
$theme->loadTemplates('templates');
$theme->addLayer('main');

$theme->addTemplate('home');
$theme->context['site_name'] = 'My Site';

// Just some data to display and foreach over.
$theme->context['list'] = array(
	'123' => array(
		'name' => 'apple',
		'letters' => str_split('apple'),
	),
	'126' => array(
		'name' => 'banana',
		'letters' => str_split('banana'),
	),
	'130' => array(
		'name' => 'car',
		'letters' => str_split('car'),
	),
	'140' => array(
		'name' => 'dog',
		'letters' => str_split('dog'),
	),
	'141' => array(
		'name' => 'elephant',
		'letters' => str_split('elephant'),
	),
);

$theme->output();

?>

<?php

require(dirname(dirname(__FILE__)) . '/include.php');
require(dirname(__FILE__) . '/theme.php');

$theme = new MyTheme();
$theme->loadTemplates('templates');
$theme->addLayer('main');

$theme->addTemplate('home');
$theme->context['site_name'] = 'My Site';
$theme->output();

?>
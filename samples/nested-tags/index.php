<?php

require(dirname(dirname(__FILE__)) . '/include.php');

$theme = new SampleToxgTheme(dirname(__FILE__), dirname(__FILE__), array(), true);
$theme->loadTemplates('templates');
$theme->addLayer('main');

$theme->addTemplate('home');
$theme->output();

?>
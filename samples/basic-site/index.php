<?php

$time_st = microtime(true);

require(dirname(dirname(__FILE__)) . '/include.php');

$theme = new SampleToxgTheme(dirname(__FILE__), dirname(__FILE__));
$theme->loadTemplates('templates');
$theme->addLayer('main');

$theme->addTemplate('home');
$theme->context['site_name'] = 'My Site';
$theme->output();

$time_et = microtime(true);

//echo 'Took: ', number_format($time_et - $time_st, 4), ' seconds.';

?>
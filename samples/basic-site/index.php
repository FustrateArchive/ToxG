<?php

$time_st = microtime(true);

require(dirname(dirname(__FILE__)) . '/include.php');

$theme = new SampleToxgTheme();
$theme->loadTemplates(dirname(__FILE__) . '/templates.tox');
$theme->addLayer('main');

$theme->addTemplate('home');
$theme->context['site_name'] = 'My Site';
$theme->output();

$time_et = microtime(true);

//echo 'Took: ', number_format($time_et - $time_st, 4), ' seconds.';

?>
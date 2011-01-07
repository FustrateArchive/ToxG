<?php

// Something more should be done with this.

require(dirname(__FILE__) . '/../include/index.php');
require(dirname(__FILE__) . '/include/index.php');

header('Content-Type: text/plain');

$list = new ToxgTestList();
$list->loadFrom(dirname(__FILE__));

call_test_funcs($list);

function call_test_funcs(ToxgTestList $list)
{
	$pass = 0;
	$fail = 0;
	$t = null;
	$reason = null;

	$funcs = $list->getTestFuncs();
	foreach ($funcs as $func)
	{
		// !!! Should do something with timings ($t.)
		if ($list->executeTest($func, $reason, $t))
			$pass++;
		else
		{
			echo sprintf('%-60s', substr($func, strlen('test_'))), 'FAILED', "\n";
			echo '        Reason: ', $reason, "\n\n";

			$fail++;
		}
	}

	echo number_format($pass + $fail), ' tests run, ', number_format($fail), ' failed.', "\n";
}

?>
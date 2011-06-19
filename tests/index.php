<?php

// Something more should be done with this.

start_test_coverage();

require(dirname(__FILE__) . '/../include/index.php');
require(dirname(__FILE__) . '/include/index.php');

header('Content-Type: text/plain');

$list = new ToxgTestList();
$list->loadFrom(dirname(__FILE__));

call_test_funcs($list);

function call_test_funcs(ToxgTestList $list)
{
	global $argv;

	if (isset($argv))
		$only_tests = array_diff(array_slice($argv, 1), (array) '--coverage');
	else
		$only_tests = array();

	$pass = 0;
	$fail = 0;
	$t = null;
	$reason = null;

	$funcs = $list->getTestFuncs();
	foreach ($funcs as $func)
	{
		$name = substr($func, strlen('test_'));

		// !!! Do something more complicated like a wildcard?
		if (!empty($only_tests) && !in_array($name, $only_tests))
			continue;

		// !!! Should do something with timings ($t.)
		if ($list->executeTest($func, $reason, $t))
			$pass++;
		else
		{
			echo sprintf('%-60s', $name), 'FAILED', "\n";
			echo '        Reason: ', $reason, "\n\n";

			$fail++;
		}
	}

	echo number_format($pass + $fail), ' tests run, ', number_format($fail), ' failed.', "\n";

	if (can_get_test_coverage())
		echo 'Coverage: ', number_format(100 * get_test_coverage()), '%', "\n";

	if ($fail == 0)
		exit(0);
	else
		exit(1);
}

function start_test_coverage()
{
	if (can_get_test_coverage() && function_exists('xdebug_start_code_coverage'))
		xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
}

function can_get_test_coverage()
{
	global $argv;

	if (isset($argv) && in_array('--coverage', $argv))
		return function_exists('xdebug_start_code_coverage');
	else
		return false;
}

function get_test_coverage()
{
	$coverage_data = xdebug_get_code_coverage();

	$covered = 0;
	$uncovered = 0;

	foreach ($coverage_data as $filename => $lines)
	{
		if (strpos(basename($filename), '.test.output') === 0 || strpos($filename, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR) !== false)
			unset($coverage_data[$filename]);
	}

	ksort($coverage_data);

	$f_report = @fopen(dirname(__FILE__) . '/.test.coverage', 'wt');

	foreach ($coverage_data as $filename => $lines)
	{
		foreach ($lines as $line => $state)
		{
			if ($state > 0)
				$covered++;
			elseif (check_uncovered_line($filename, $line, $state == -2 ? 'dead' : 'uncovered'))
			{
				$uncovered++;
				report_uncovered_line($f_report, $filename, $line, $state == -2 ? 'dead' : 'uncovered');
			}
		}
	}

	fclose($f_report);

	if ($covered + $uncovered > 0)
		return $covered / ($covered + $uncovered);
	else
		return 0;
}

function check_uncovered_line($filename, $line, $state)
{
	// In many cases, it says dead, but it's not really code at all.
	if ($state === 'dead')
	{
		$source = file($filename);

		// Check for } after a return, assert, throw, or break.
		if ($line > 1 && trim($source[$line - 1]) === '}' && substr_count($source[$line - 2], ';') === 1)
		{
			$prev_line = trim($source[$line - 2]);
			if (strpos($prev_line, 'return') === 0 || strpos($prev_line, 'throw') === 0 || strpos($prev_line, 'break') === 0)
				return false;
			// This can't be passed.
			elseif ($prev_line === 'assert (false);')
				return false;
		}
	}

	return true;
}

function report_uncovered_line($f_report, $filename, $line, $state)
{
	if (!$f_report)
		return;

	fwrite($f_report, $filename . ':' . $line . ($state === 'dead' ? ' (DEAD)' : '') . "\n");
}

?>
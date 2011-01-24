<?php

class ToxgTestList
{
	protected $files = array();

	public function __construct()
	{
	}

	public function loadFrom($path)
	{
		$this->getTestFiles('', realpath($path));

		foreach ($this->files as $file)
			include($file);
	}

	public function getTestFuncs()
	{
		$funcs = get_defined_functions();

		$list = array();
		foreach ($funcs['user'] as $func)
		{
			if (strpos($func, 'test_') === 0)
				$list[] = $func;
		}

		return $list;
	}

	public function executeTest($func, &$reason, &$time)
	{
		// I know, yuck, this is rudimentry.  I am not worrying about it now, want to write the tests.
		$st = microtime(true);

		try
		{
			$harness = new ToxgTestHarness();
			ToxgStandardElements::useIn($harness);
			$harness->setNamespaces(array('tpl' => ToxgTemplate::TPL_NAMESPACE, 'my' => 'dummy' . $func));
			$func($harness);
			$harness->compile('dummy' . $func);

			$failure = $harness->isFailure();
		}
		catch (ToxgException $e)
		{
			$failure = $harness->isExceptionFailure($e);
		}
		catch (Exception $e)
		{
			$failure = $e->getMessage();
		}

		$et = microtime(true);
		$time = $et - $st;

		$reason = $failure === false ? null : $failure;
		return $failure === false;
	}

	protected function getTestFiles($path, $full_prefix)
	{
		$full = realpath($full_prefix . '/' . $path);
		$path_slash = $path == '' ? '' : $path . '/';

		$dir = dir($full);
		while ($entry = $dir->read())
		{
			if ($entry[0] === '.' || $path . $entry === 'index.php' || $path . $entry === 'include')
				continue;
			elseif (is_dir($full . '/' . $entry))
				$this->getTestFiles($path_slash . $entry, $full_prefix);
			else
				$this->files[] = $full . '/' . $entry;
			
		}
		$dir->close();
	}
}

?>
<<<<<<< HEAD
<?php

class ToxgException extends Exception
{
	public $tpl_file = null;
	public $tpl_line = 0;

	public function __construct($message, $file, $line)
	{
		$this->tpl_file = $file;
		$this->tpl_line = $line;

		parent::__construct($file . '[' . $line . '] ' . $message, 0);
	}
}

=======
<?php

class ToxgException extends Exception
{
	public $tpl_file = null;
	public $tpl_line = 0;

	public function __construct($message, $file, $line)
	{
		$this->tpl_file = $file;
		$this->tpl_line = $line;

		$list = array();
		$trace = debug_backtrace();
		unset($trace[0]);

		foreach ($trace as $item)
			$list[] = $item['file'] . ':' . $item['line'] . '...' . $item['function'];

		parent::__construct($file . '[' . $line . '] ' . $message . '<br /><br /><pre>Backtrace:<br />' . implode('<br />', $list) . '</pre>', 0);
	}
}

>>>>>>> dragooon
?>
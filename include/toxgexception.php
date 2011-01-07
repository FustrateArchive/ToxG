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

?>
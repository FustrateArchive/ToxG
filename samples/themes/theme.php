<?php

class MyTheme extends SampleToxgTheme
{
	protected $nsuri = 'http://www.example.com/#site';
	protected $theme = null;

	public function __construct($name)
	{
		$this->theme = $name;
		$this->template_dir = dirname(__FILE__) . '/themes/' . $name;
		$this->compile_dir = dirname(__FILE__) . '/';
		$this->inherited_dirs[] = dirname(__FILE__) . '/themes/base';

		parent::__construct();

		if (file_exists($this->template_dir . '/overlay.tox'))
			$this->loadOverlay('overlay');
	}

	protected function pathForCompiled($dir, $name)
	{
		return $dir . '/.toxg.' . $this->theme . '.' . $name . '.php';
	}
}

?>
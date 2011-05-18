<?php

class MyTheme extends SampleToxgTheme
{
	protected $nsuri = 'http://www.example.com/#site';
	protected $theme = null;

	public function __construct($name)
	{
		$this->theme = $name;
		$this->template_dir = dirname(__FILE__) . '/themes/' . $name;
		$this->compile_dir = dirname(__FILE__) . '/themes/' . $name;
		$this->inherited_dirs[] = $name === 'base' ? array() : (dirname(__FILE__) . '/themes/base');

		parent::__construct($this->template_dir, $this->compile_dir, $this->inherited_dirs);

		if (file_exists($this->template_dir . '/overlay.tpl'))
			$this->loadOverlay('overlay');
	}
}

?>
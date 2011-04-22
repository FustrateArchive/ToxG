<?php

class SampleToxgTheme extends ToxgTheme
{
	public $context = array();

	public function isTemplateUsed($name)
	{
		return ToxgTemplate::isTemplateUsed($this->nsuri, $name);
	}

	public function output()
	{
		$this->setTemplateParam('context', $this->context);
		parent::output();
	}
}

?>
<?php

class SampleToxgTheme extends ToxgTheme
{
	public $context = array();

	public function isTemplateUsed($name)
	{
		if ($this->needs_load)
			throw new ToxgException('untranslated', 'Templates haven\'t been loaded yet, call loadAll() first.');

		return ToxgTemplate::isTemplateUsed($this->nsuri, $name);
	}

	public function output()
	{
		$this->setTemplateParam('context', $this->context);
		parent::output();
	}
}

?>
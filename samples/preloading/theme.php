<?php

class MyTheme extends SampleToxgTheme
{
	protected $nsuri = 'http://www.example.com/#site';

	// Using a singleton pattern just for simplicity, load data however you like.
	protected static $instance = null;

	public function __construct()
	{
		self::$instance = $this;
		parent::__construct();
	}

	public static function getInstance()
	{
		return self::$instance;
	}

	protected function compileAll()
	{
		$this->setListeners();
		return parent::compileAll();
	}

	protected function setListeners()
	{
		// When compiling, ask TOX-G to tell us when it sees site:dynamic...
		$this->templates->listenEmit($this->nsuri, 'dynamic', array($this, 'site_dynamic'));
	}

	public function site_dynamic(ToxgBuilder $builder, $type, array $attributes, ToxgToken $token)
	{
		// This just loads the data when/if the template is ever used.
		// Inside there, we'll load the data smartly based on what's needed.
		if ($type === 'tag-start')
		{
			$builder->emitCode('$dynamic = ' . __CLASS__ . '::getInstance()->loadDynamic();');
			// And for illustration:
			$builder->emitCode('echo \'<em>Loaded:</em><pre>\', htmlspecialchars(print_r($dynamic, true)), \'</pre>\';');
		}
	}

	public function loadDynamic()
	{
		// Pretend we're actually using the database here.

		// Do we need to spend time getting the descriptions for each item?
		$need_desc = $this->isTemplateUsed('dynamic-desc');
		// That's just a helper for the following:
		//$need_desc = ToxgTemplate::isTemplateUsed($this->nsuri, 'dynamic-desc');

		if ($need_desc)
			return array(
				array(
					'title' => 'Some title',
					'description' => 'Some description',
				),
			);
		else
			return array(
				array(
					'title' => 'Some title',
				),
			);
	}
}

?>
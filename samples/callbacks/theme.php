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
		// When compiling, ask TOX-G to tell us when it sees templates...
		$this->templates->listenEmitBasic('template', array($this, 'maybeHookTemplate'));
	}

	public function maybeHookTemplate(ToxgBuilder $builder, $type, array $attributes, ToxgToken $token)
	{
		list ($ns, $name) = explode(':', $attributes['name'], 2);
		$nsuri = $token->getNamespace($ns);

		if ($nsuri == $this->nsuri && $name === 'dynamic')
			$builder->emitCode('$dynamic = ' . __CLASS__ . '::getInstance()->loadDynamic();', $token);
	}

	public function loadDynamic()
	{
		// Pretend we're actually using the database here.

		return array(
			array(
				'title' => 'Some title',
				'description' => 'Some description',
			),
			array(
				'title' => 'Another title',
				'description' => 'And another description',
			),
		);
	}
}

?>
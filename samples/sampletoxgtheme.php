<?php

class SampleToxgTheme
{
	protected $nsuri = 'http://www.example.com/#site';
	protected $template_dir = '.';
	protected $inherited_dirs = array();
	protected $compile_dir = '.';
	protected $mtime_check = true;
	protected $needs_compile = false;

	protected $overlays = array();
	protected $mtime = 0;

	protected $namespaces = array();

	protected $templates = null;
	protected $layers = array();
	protected $inside = array();
	public $context = array();

	public function __construct()
	{
		$this->template_dir = dirname(__FILE__);
		$this->compile_dir = dirname(__FILE__) . '/compiled';

		$this->templates = new ToxgTemplateList();
		$this->templates->setCommonVars(array('context'));

		$this->namespaces = array(
			'site' => $this->nsuri,
			'tpl' => ToxgTemplate::TPL_NAMESPACE,
		);
	}

	public function loadOverlay($source)
	{
		$base = basename($source, '.tox');
		$dir = dirname($source);

		$this->mtime = max($this->mtime, filemtime($source));
		$this->templates->addOverlays(array($source));
	}

	public function loadTemplates($source)
	{
		$base = basename($source, '.tox');
		$dir = dirname($source);

		$compiled = $dir . '/.toxg.' . $base . '.php';

		$inherited = array();
		foreach ($this->inherited_dirs as $dir)
			$inherited[] = $dir . '/' . $filename . '.tox';

		// Note: if overlays change, this won't work unless the overlay was touched.
		// Normally, you'd flush the system when it needs a recompile.
		if ($this->mtime_check)
		{
			$this->mtime = max($this->mtime, filemtime($source));

			foreach ($inherited as $file)
				$this->mtime = max($this->mtime, filemtime($file));

			$this->needs_compile |= !file_exists($compiled) || filemtime($compiled) <= $this->mtime;
		}

		$this->templates->addTemplate($source, $compiled, $inherited);
	}

	public function recompile()
	{
		$this->needs_compile = true;
	}

	public function addLayer($name, $namespace = 'site')
	{
		$this->layers[] = array($name, $namespace);
	}

	public function resetLayers()
	{
		$this->layers = array();
	}

	public function addTemplate($name, $namespace = 'site')
	{
		$this->inside[] = array($name, $namespace);
	}

	public function removeTemplate($name, $namespace = 'site')
	{
		$new = array();

		foreach ($this->inside as $template)
			if ($template != array($name, $namespace))
				$new[] = $template;

		$this->inside = $new;
	}

	public function resetTemplates($name)
	{
		$this->inside = array();
	}

	public function addNamespace($name, $nsuri)
	{
		$this->namespaces[$name] = $nsuri;
	}

	public function output()
	{
		if ($this->needs_compile)
		{
			ToxgStandardElements::useIn($this->templates);
			$this->templates->setNamespaces($this->namespaces);
			$this->templates->compileAll();
		}

		$this->templates->loadAll();

		foreach ($this->layers as $layer)
			$this->callTemplate($layer[0], 'above', $layer[1]);

		foreach ($this->inside as $inside)
		{
			$this->callTemplate($inside[0], 'above', $inside[1]);
			$this->callTemplate($inside[0], 'below', $inside[1]);
		}

		$reversed = array_reverse($this->layers);
		foreach ($reversed as $layer)
			$this->callTemplate($layer[0], 'below', $layer[1]);
	}

	protected function callTemplate($name, $side, $nsuri = 'site')
	{
		$func = ToxgExpression::makeTemplateName($this->namespaces[$nsuri], $name . '--toxg-direct') . '_' . $side;
		call_user_func($func, array('context' => $this->context));
	}
}

?>
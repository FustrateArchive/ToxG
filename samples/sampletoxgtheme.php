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

	protected $templates = null;
	protected $layers = array();
	protected $inside = array();
	public $context = array();

	public function __construct()
	{
		$this->templates = new ToxgTemplateList();
		$this->templates->setNamespaces(array('site' => $this->nsuri, 'tpl' => ToxgTemplate::TPL_NAMESPACE));
		$this->templates->setCommonVars(array('context'));
	}

	public function loadOverlay($filename)
	{
		$full = $this->template_dir . '/' . $filename . '.tox';

		$this->mtime = max($this->mtime, filemtime($full));
		$this->templates->addOverlays(array($full));
	}

	public function loadTemplates($filename)
	{
		$source = $this->template_dir . '/' . $filename . '.tox';
		$compiled = $this->compile_dir . '/.toxg.' . $filename . '.php';

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

	public function addLayer($name)
	{
		$this->layers[] = $name;
	}

	public function resetLayers()
	{
		$this->layers = array();
	}

	public function addTemplate($name)
	{
		$this->inside[] = $name;
	}

	public function resetTemplates($name)
	{
		$this->inside = array();
	}

	public function output()
	{
		if ($this->needs_compile)
		{
			ToxgStandardElements::useIn($this->templates);
			$this->templates->compileAll();
		}

		$this->templates->loadAll();

		foreach ($this->layers as $layer)
			$this->callTemplate($layer, 'above');

		foreach ($this->inside as $inside)
		{
			$this->callTemplate($inside, 'above');
			$this->callTemplate($inside, 'below');
		}

		$reversed = array_reverse($this->layers);
		foreach ($reversed as $layer)
			$this->callTemplate($layer, 'below');
	}

	protected function callTemplate($name, $side)
	{
		$func = ToxgExpression::makeTemplateName($this->nsuri, $name . '--toxg-direct') . '_' . $side;
		call_user_func($func, array('context' => $this->context));
	}
}

?>
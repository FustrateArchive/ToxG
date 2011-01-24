<?php

class SampleToxgTheme
{
	protected $nsuri = 'http://www.example.com/#site';
	protected $template_dir = '.';
	protected $inherited_dirs = array();
	// This would get pointed at some temp directory, etc.
	protected $compile_dir = '.';
	protected $mtime_check = true;
	protected $needs_compile = false;
	protected $needs_load = true;

	protected $mtime = 0;
	protected $overlay_hash = null;

	protected $namespaces = array();

	protected $templates = null;
	protected $layers = array();
	protected $inside = array();
	protected $overlays = array();
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

	public function loadOverlay($names)
	{
		// This sample class doesn't support loading incrementally.
		if (!$this->needs_load)
			throw new ToxgException('untranslated', 'Templates already loaded, too late to load overlays now.');

		$names = func_get_args();

		$files = array();
		foreach ($names as $filename)
		{
			$full = $this->pathForTemplate($this->template_dir, $filename);

			$this->mtime = max($this->mtime, filemtime($full));
			$files[] = $full;
			$this->overlays[] = $full;
		}

		$this->templates->addOverlays($files);
		$this->overlay_hash = sha1(implode(';', $this->overlays));
 	}
 
 	public function loadTemplates($names)
 	{
		// This sample class doesn't support loading incrementally.
		if (!$this->needs_load)
			throw new ToxgException('untranslated', 'Templates already loaded, too late to load more templates now.');

 		$names = func_get_args();
 
 		foreach ($names as $filename)
		{
			$source = $this->pathForTemplate($this->template_dir, $filename);
			$compiled = $this->pathForCompiled($this->compile_dir, $filename);

			$inherited = array();
			foreach ($this->inherited_dirs as $dir)
				$inherited[] = $this->pathForTemplate($dir, $filename);
			// Make sure no one accidentally inherits from itself.
			$inherited = array_diff($inherited, (array) $source);

			while (!file_exists($source) && !empty($inherited))
				$source = array_shift($inherited);

			if ($this->mtime_check)
			{
				$this->mtime = max($this->mtime, filemtime($source));
				foreach ($inherited as $file)
					$this->mtime = max($this->mtime, filemtime($file));

				$this->needs_compile |= !file_exists($compiled) || filemtime($compiled) <= $this->mtime;
			}

			$this->templates->addTemplate($source, $compiled, $inherited);
		}
	}

	protected function pathForTemplate($dir, $name)
	{
		return $name;
	}

	protected function pathForCompiled($dir, $name)
	{
		if ($this->overlay_hash === null)
			return dirname($name) . '/.toxg.' . basename($name, '.tox') . '.php';
		else
			return dirname($name) . '/.toxg.' . basename($name, '.tox') . '.' . $this->overlay_hash . '.php';
	}

	public function recompile()
	{
		// This sample class doesn't support loading incrementally.
		if (!$this->needs_load)
			throw new ToxgException('untranslated', 'Templates already loaded, too late to recompile now.');

		$this->needs_compile = true;
	}

	public function addLayer($name, $namespace = 'site')
	{
		$this->layers[] = array($name, $namespace);
	}

	public function removeLayer($name)
	{
		$this->layers = array_diff($this->layers, (array) $name);
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

	public function removeTemplate($name)
	{
		$this->inside = array_diff($this->inside, (array) $name);
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
		$this->loadAll();

		foreach ($this->layers as $layer)
			$this->callTemplate($layer[0], 'above', $layer[1]);

		foreach ($this->inside as $inside)
			$this->callTemplate($inside[0], 'both', $inside[1]);

		$reversed = array_reverse($this->layers);
		foreach ($reversed as $layer)
			$this->callTemplate($layer[0], 'below', $layer[1]);
	}
 
	public function loadAll()
	{
		if ($this->needs_compile)
			$this->compileAll();
		$this->needs_compile = false;

		if ($this->needs_load)
			$this->templates->loadAll();
		$this->needs_load = false;
	}

	protected function compileAll()
	{
		ToxgStandardElements::useIn($this->templates);
		$this->templates->setNamespaces($this->namespaces);
		$this->templates->compileAll();
	}

	public function isTemplateUsed($name)
	{
		if ($this->needs_load)
			throw new ToxgException('untranslated', 'Templates haven\'t been loaded yet, call loadAll() first.');

		return ToxgTemplate::isTemplateUsed($this->nsuri, $name);
	}

	protected function callTemplate($name, $side, $nsuri = 'site')
	{
		ToxgTemplate::callTemplate($this->namespaces[$nsuri], $name, array('context' => $this->context), $side);
	}
}

?>
<?php

class ToxgTemplateList
{
	protected $builder = null;
	protected $prebuilder = null;
	protected $overlays = array();
	protected $namespaces = array();
	protected $common_vars = array();
	protected $debugging = true;
	protected $templates = array();
	protected $overlayCalls = array();

	public function __construct($builder = null)
	{
		$this->builder = $builder;
	}

	public function callOverlays(array $overlays, array $ns)
	{
		$overlays = (array) $overlays;
		$ns = (array) $ns;

		foreach ($overlays as $k => $overlay)
			$this->overlayCalls[] = $ns[$k] . ':' . $overlay;
	}

	public function addOverlays(array $files)
	{
		foreach ($files as $file)
			$this->overlays[] = $file;
	}

	public function setNamespaces(array $uris)
	{
		$this->namespaces = $uris;
	}

	public function setCommonVars(array $names)
	{
		$this->common_vars = $names;
	}

	public function disableDebugging($disable = true)
	{
		$this->debugging = !$disable;
	}

	public function listenEmit($nsuri, $name, $callback)
	{
		if ($this->builder === null)
			$this->builder = new ToxgBuilder();

		$this->builder->listenEmit($nsuri, $name, $callback);
	}

	public function listenEmitBasic($name, $callback)
	{
		return $this->listenEmit(ToxgTemplate::TPL_NAMESPACE, $name, $callback);
	}

	public function addTemplate($source_file, $cache_file, array $inherited_files = array())
	{
		$this->templates[] = array(
			'source_file' => $source_file,
			'cache_file' => $cache_file,
			'inherited_files' => $inherited_files,
		);
	}

	protected function setupTemplate($template)
	{
		$object = $this->createTemplate($template['source_file']);
		
		foreach ($template['inherited_files'] as $file)
			$object->addInheritedFile($file);

		$object->callOverlays($this->overlayCalls);
		$object->addOverlays($this->overlays);
		$object->setNamespaces($this->namespaces);
		$object->setCommonVars($this->common_vars);
		$object->disableDebugging(!$this->debugging);
		$object->setPrebuilder($this->prebuilder);

		return $object;
	}

	protected function createTemplate($source_file)
	{
		return new ToxgTemplate($source_file, $this->builder);
	}

	public function compileAll()
	{
		if ($this->builder === null)
			$this->builder = new ToxgBuilder();
		if ($this->prebuilder === null)
			$this->prebuilder = new ToxgPrebuilder();

		$templates = array();
		foreach ($this->templates as $k => $template)
			$templates[$k] = $this->setupTemplate($template);

		foreach ($templates as $template)
		{
			$template->prepareCompile();
			$template->compileFirstPass();
		}

		foreach ($this->templates as $k => $template)
		{
			if ($template['cache_file'] !== false)
				$templates[$k]->compileSecondPass($template['cache_file']);
		}
	}

	public function loadAll()
	{
		foreach ($this->templates as $template)
			include($template['cache_file']);
	}
}

?>
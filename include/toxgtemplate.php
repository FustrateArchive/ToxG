<?php

class ToxgTemplate
{
	const VERSION = "0.1-alpha1";
	// !!! Need a domain name/final name/etc.?
	const TPL_NAMESPACE = 'urn:toxg:template';

	protected $source_files = array();
	protected $builder = null;
	protected $prebuilder = null;
	protected $namespaces = array();
	protected $overlays = array();
	protected $common_vars = array();
	protected $debugging = true;

	public function __construct($source_file, $builder = null)
	{
		$this->builder = $builder !== null ? $builder : new ToxgBuilder();
		$this->source_files[] = $source_file;
	}

	public function addInheritedFile($source_file)
	{
		$this->source_files[] = $source_file;
	}

	public function addOverlays(array $files)
	{
		foreach ($files as $file)
			$this->overlays[] = new ToxgOverlay($file);
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
		$this->builder->listenEmit($nsuri, $name, $callback);
	}

	public function listenEmitBasic($name, $callback)
	{
		return $this->listenEmit(self::TPL_NAMESPACE, $name, $callback);
	}

	public function setPrebuilder($prebuilder)
	{
		$this->prebuilder = $prebuilder;
	}

	public function compile($cache_file)
	{
		$this->prepareCompile();
		$this->compileFirstPass();
		$this->compileSecondPass($cache_file);
	}

	public function prepareCompile()
	{
		// Get the overlays parsed now so they can interfere with parsing later.
		foreach ($this->overlays as $overlay)
		{
			$overlay->setNamespaces($this->namespaces);
			$overlay->parse();
		}
	}

	public function compileFirstPass()
	{
		if ($this->prebuilder === null)
			$this->prebuilder = new ToxgPrebuilder();

		// We actually parse through each file twice: first time is for optimization.
		foreach ($this->source_files as $source_file)
		{
			// Each parser will check for duplicates, so we use a new one for each file.
			$parser = new ToxgParser($source_file);
			$parser->setNamespaces($this->namespaces);

			// These both install hooks into the parser, which calls them as necessary.
			foreach ($this->overlays as $overlay)
				$overlay->setupParser($parser);
			$this->prebuilder->setupParser($parser);

			// And this is the crux of the whole operation.
			$parser->parse();
		}

		// Preparse done, give all the useful details to the builder.
		$this->builder->setPrebuilder($this->prebuilder);
	}

	public function compileSecondPass($cache_file)
	{
		// Now set up the builder (which will be eventually called by the parser.)
		$this->builder->disableDebugging(!$this->debugging);
		$this->builder->setCommonVars($this->common_vars);
		$this->builder->setCacheFile($cache_file);

		// Each source file is processed one at a time, the builder omits duplicates.
		foreach ($this->source_files as $source_file)
		{
			// Each parser will check for duplicates, so we use a new one for each file.
			$parser = new ToxgParser($source_file);
			$parser->setNamespaces($this->namespaces);

			// These both install hooks into the parser, which calls them as necessary.
			foreach ($this->overlays as $overlay)
				$overlay->setupParser($parser);
			$this->builder->setupParser($parser);

			// And this is the crux of the whole operation.
			$parser->parse();
		}

		$this->builder->finalize();
	}
}

?>
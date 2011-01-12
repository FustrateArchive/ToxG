<?php

class ToxgParser
{
	protected $sources = array();
	protected $primary = null;
	protected $primary_fp = null;
	protected $listeners = array();
	protected $inside_cdata = false;
	protected $tree = array();
	protected $templates = array();
	protected $last_template = null;

	public function __construct($file)
	{
		if ($file instanceof ToxgSource)
			$this->primary = $file;
		else
		{
			$this->primary_fp = @fopen($file, 'rt');
			if (!$this->primary_fp)
			{
				$this->primary_fp = null;
				throw new ToxgException('Unable to open template file: ' . $file, '', 0);
			}

			$this->primary = ToxgSource::Factory($this->primary_fp, $file);
		}
	}

	public function __destruct()
	{
		if ($this->primary_fp !== null)
			fclose($this->primary_fp);
		$this->primary_fp = null;
	}

	public function setNamespaces(array $uris)
	{
		$this->primary->setNamespaces($uris);
	}

	public function listen($type, $callback)
	{
		$this->listeners[$type][] = $callback;
	}

	protected function fire($type, array $args)
	{
		if (empty($this->listeners[$type]))
			return;

		$args[] = $this;
		foreach ($this->listeners[$type] as $callback)
		{
			$result = call_user_func_array($callback, $args);
			if ($result === false)
				break;
		}
	}

	public function insertSource($source, $defer = false)
	{
		if (!($source instanceof ToxgSource) && !($source instanceof ToxgToken))
			throw new ToxgException('The inserted source must be either a ToxgSource or ToxgToken.', '', 0);

		array_unshift($this->sources, $source);

		// To defer means we wait, it goes up the chain.
		if ($defer)
			return;

		if ($source instanceof ToxgSource)
		{
			while (!$source->isEndOfTokens())
				$this->parseNextSource();
		}
		// Just need to process the token once.
		else
			$this->parseNextSource();
	}

	public function parse()
	{
		$this->insertSource($this->primary, true);

		while (!empty($this->sources))
			$this->parseNextSource();

		$this->verifyClosed();
	}

	protected function verifyClosed()
	{
		if (!empty($this->tree))
		{
			$token = array_pop($this->tree);
			throw new ToxgException('Unclosed element ' . $token->prettyName() . ' started at ' . $token->file . ', line ' . $token->line . '.', '', 0);
		}
	}

	protected function debugToken(ToxgToken $token)
	{
		// !!! All tokens go through here, which can help debugging overlays.
		// !!! I was just echoing them for testing, maybe build in some sort of hook or file output?
		//echo $token->data;
	}

	protected function parseNextSource()
	{
		if (empty($this->sources))
			throw new ToxgException('Internal parsing error.', '', 0);

		$source = $this->sources[0];

		// If it was actually an ToxgToken, pull it out right away.
		if ($source instanceof ToxgToken)
			$token = array_shift($this->sources);
		else
			$token = $source->readToken();

		// Gah, we hit the end of the stream... next source.
		if ($token === false)
		{
			array_shift($this->sources);
			return;
		}

		$this->debugToken($token);

		switch ($token->type)
		{
		case 'content':
			$this->parseContent($token);
			break;

		case 'var-ref':
		case 'lang-ref':
		case 'output-ref':
			$this->parseRef($token);
			break;

		case 'cdata-start':
			$this->parseCDATA($token, true);
			break;

		case 'cdata-end':
			$this->parseCDATA($token, false);
			break;

		case 'comment-start':
		case 'comment-end':
		case 'comment':
			$this->parseComment($token);
			break;

		case 'tag-start':
		case 'tag-empty':
			$this->parseTag($token);
			break;

		case 'tag-end':
			$this->parseTagEnd($token);
			break;
		}
	}

	protected function parseContent(ToxgToken $token)
	{
		// Shouldn't have content outside a template.
		if ($this->last_template === null)
		{
			if (trim($token->data) !== '')
				$token->toss('Unexpected content outside any template definition.');
		}
		else
			$this->fire('parsedContent', array($token));
	}

	protected function parseRef(ToxgToken $token)
	{
		if ($token->type == 'output-ref')
			$token->data = substr($token->data, 1, strlen($token->data) - 2);

		// Make the tag look like a normal tag.
		$token->type = 'tag-empty';
		$token->name = 'output';
		$token->ns = 'tpl';
		$token->nsuri = ToxgTemplate::TPL_NAMESPACE;
		$token->attributes['value'] = $token->data;
		$token->attributes['as'] = $this->inside_cdata ? 'raw' : 'html';

		$this->parseTag($token);
	}

	protected function parseCDATA(ToxgToken $token, $open)
	{
		$this->inside_cdata = $open;

		// Pass it through as if content (still want it outputted.)
		$this->fire('parsedContent', array($token));
	}

	protected function parseComment(ToxgToken $token)
	{
		// Do nothing.
	}

	protected function parseTag(ToxgToken $token)
	{
		// For a couple of these, we do special stuff.
		if ($token->nsuri == ToxgTemplate::TPL_NAMESPACE)
		{
			// We only have a couple of built in constructs.
			if ($token->name === 'template')
				$this->handleTagTemplate($token);
			elseif ($token->name === 'content')
				$this->handleTagContent($token);
			elseif ($token->name === 'output')
				$this->handleTagOutput($token);
			elseif ($token->name === 'json')
				$this->handleTagJSON($token);
			elseif ($token->name === 'alter')
				$token->toss('Unexpected tpl:alter within template source.');
		}
		// Before we fire the event, save the template vars (before alters insert data.)
		else
			$this->handleTagCall($token, 'before');

		if ($token->type === 'tag-start')
			array_push($this->tree, $token);

		$this->fire('parsedElement', array($token));

		// After we fire the event, for empty tags, we cleanup.
		if ($token->nsuri !== ToxgTemplate::TPL_NAMESPACE)
			$this->handleTagCall($token, 'after');
	}

	protected function parseTagEnd(ToxgToken $token)
	{
		if (empty($this->tree))
			$token->toss('Unmatched element ' . $token->prettyName() . ', you closed it already or never opened it.');

		$close_token = array_pop($this->tree);

		// Darn, it's not the same one.
		if ($close_token->nsuri != $token->nsuri || $close_token->name !== $token->name)
			$token->toss('End tag for ' . $token->prettyName() . ' found instead of ' . $close_token->prettyName() . ', started at ' . $close_token->file . ', line ' . $close_token->line . '.');

		// This makes it easier, since they're on the same element after all.
		$token->attributes = $close_token->attributes;
		$this->fire('parsedElement', array($token));

		// We might be exiting a template.  These can't be nested.
		if ($token->nsuri == ToxgTemplate::TPL_NAMESPACE && $token->name === 'template')
			$this->handleTagTemplateEnd($token);

		// After we fire the event, we'll cleanup the call variables.
		if ($token->nsuri !== ToxgTemplate::TPL_NAMESPACE)
			$this->handleTagCall($token, 'after');
	}

	protected function handleTagTemplate(ToxgToken $token)
	{
		if ($token->type === 'tag-empty')
			$token->toss('Please always use an start tag like <tpl:template name="x:y">, it should have content inside it.');
		if (!isset($token->attributes['name']))
			$token->toss('Undefined name attribute for tpl:template.');

		if (strpos($token->attributes['name'], ':') === false)
			$token->toss('Every template should have a namespace, ' . $token->attributes['name'] . ' didn\'t have one.');

		// Figure out the namespace and validate it.
		list ($ns, $name) = explode(':', $token->attributes['name'], 2);
		$nsuri = $token->getNamespace($ns);
		if ($nsuri === false)
			$token->toss('You need to declare namespaces even for templates (' . $ns . ' was undeclared.)');

		// This is the fully-qualified name, which can/should not be duplicated.
		$fqname = $nsuri . ':' . $name;
		if (isset($this->templates[$fqname]))
			$token->toss('Duplicate tpl:template named ' . $ns . ':' . $name . '.');

		$this->templates[$fqname] = true;
		$this->last_template = $fqname;
	}

	protected function handleTagTemplateEnd(ToxgToken $token)
	{
		$this->last_template = null;

		// After a template, we generate a fake template for overlays to apply to.
		// Note that (in case they don't actually exist) templates don't have overlays
		// applied inside them, but instead upon call.
		if (strpos($token->attributes['name'], '--toxg-direct') === false)
		{
			list ($ns, $name) = explode(':', $token->attributes['name'], 2);

			$template_attributes = $token->attributes;
			$template_attributes['name'] .= '--toxg-direct';

			$call_attributes = array(
				ToxgTemplate::TPL_NAMESPACE . ':all' => 'true',
			);

			$tokens = array(
				// <tpl:template name="ns:name--toxg-direct">
				$token->createInject('tag-start', false, 'template', $template_attributes),
				// <ns:name tpl:all="true">
				$token->createInject('tag-start', $ns, $name, $call_attributes),
				// <tpl:content />
				$token->createInject('tag-empty', false, 'content'),
				// </ns:name>
				$token->createInject('tag-end', $ns, $name, $call_attributes),
				// </tpl:template>
				$token->createInject('tag-end', false, 'template', $template_attributes),
			);

			// Need to reverse them because they are going to each go in first place.
			$tokens = array_reverse($tokens);
			foreach ($tokens as $new_token)
				$this->insertSource($new_token, true);
		}
	}

	protected function handleTagCall(ToxgToken $token, $pos)
	{
		// If no attributes, we don't need to push/pop, save some cycles.
		if (empty($token->attributes))
			return;
		// No reason if we're using tpl:all.
		if (count($token->attributes) === 1 && isset($token->attributes[ToxgTemplate::TPL_NAMESPACE . ':all']))
			return;

		// Overlays and content should be able to reference the attributes in the call.
		// Example: <some:example abc="123">{$abc}</some>
		// Would be easier if content/alters applied to templates, but then they must be defined.
		if ($token->type === 'tag-start' && $pos === 'before')
			$type = 'template-push';
		elseif ($token->type === 'tag-empty' && $pos === 'before')
			$type = 'template-push';
		elseif ($token->type === 'tag-empty' && $pos === 'after')
			$type = 'template-pop';
		elseif ($token->type === 'tag-end' && $pos === 'after')
			$type = 'template-pop';
		else
			return;

		// We want it to have the same file/line info, same attributes, etc.
		$new_token = $token->createInject('tag-empty', false, $type, $token->attributes);
		$this->insertSource($new_token, false);
	}

	protected function handleTagContent(ToxgToken $token)
	{
		// Doesn't make sense for these to have content, so warn.
		if ($token->type === 'tag-start')
			$token->toss('Please always use an empty tag like <tpl:content />, it cannot have content inside it.');

		// This can't be used in loops, ifs, or anything really except tpl:template and tpl:container.
		// Other template calls are allowed too.
		foreach ($this->tree as $tree_token)
		{
			// Template call, that's fine.
			if ($tree_token->nsuri !== ToxgTemplate::TPL_NAMESPACE)
				continue;

			if ($tree_token->name !== 'template' && $tree_token->name !== 'container')
				$token->toss('You cannot use tpl:content within tpl:if, tpl:foreach, etc.  It must be inside a tpl:template.');
		}
	}

	protected function handleTagOutput(ToxgToken $token)
	{
		if ($token->type === 'tag-start')
			$token->toss('Please always use an empty tag like <tpl:output />, it cannot have content inside it.');
		if (!isset($token->attributes['value']))
			$token->toss('Undefined value attribute for tpl:output.');

		// Default the as parameter just like {$x} does.
		if (!isset($token->attributes['as']))
			$token->attributes['as'] = $this->inside_cdata ? 'raw' : 'html';
	}

	protected function handleTagJSON(ToxgToken $token)
	{
		if ($token->type === 'tag-start')
			$token->toss('Please always use an empty tag like <tpl:json />, it cannot have content inside it.');
		if (!isset($token->attributes['value']))
			$token->toss('Undefined value attribute for tpl:output.');

		// Default the as parameter just like {$x} does.
		if (!isset($token->attributes['as']))
			$token->attributes['as'] = $this->inside_cdata ? 'raw' : 'html';
	}
}
?>
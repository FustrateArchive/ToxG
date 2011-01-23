<?php

class ToxgOverlay
{
	const RECURSION_LIMIT = 10;

	protected $source = null;
	protected $source_fp = null;
	protected $alters = array();
	protected $parse_state = 'outside';
	protected $parse_alter = null;
	protected $match_tree = array();
	protected $match_recursion = array();

	public function __construct($file, array $called_overlays = array())
	{
		if ($file instanceof ToxgSource)
			$this->source = $file;
		else
		{
			$this->source_fp = @fopen($file, 'rt');
			if (!$this->source_fp)
				throw new ToxgException('Unable to open overlay file: ' . $file, '', 0);

			$this->source = new ToxgSource($this->source_fp, $file);
		}

		// This array is indexed by position.
		$this->alters = array(
			'before' => array(),
			'after' => array(),
			'beforecontent' => array(),
			'aftercontent' => array(),
		);

		$this->called_overlays = $called_overlays;
	}

	public function __destruct()
	{
		if ($this->source_fp !== null)
			fclose($this->source_fp);
		$this->source_fp = null;
	}

	public function setNamespaces(array $uris)
	{
		$this->source->setNamespaces($uris);
	}

	public function setupParser(ToxgParser $parser)
	{
		$parser->listen('parsedElement', array($this, 'parsedElement'));
	}

	public function parse()
	{
		while ($token = $this->source->readToken())
		{
			switch ($this->parse_state)
			{
			case 'outside':
				$this->parseOutside($token);
				break;

			case 'alter':
				$this->parseInAlter($token);
				break;

			default:
				$token->toss('Internal parsing error.');
			}
		}

		if ($this->parse_state !== 'outside')
			throw new ToxgException('Unexpected end of file before a tpl:alter was finished.', '', 0);
	}

	protected function parseOutside(ToxgToken $token)
	{
		switch ($token->type)
		{
		case 'tag-start':
		case 'tag-end':
			// We're only interested in tpl:alter or a tpl:container.
			if ($token->nsuri != ToxgTemplate::TPL_NAMESPACE)
				$token->toss('Unexpected namespace in ' . $token->prettyName() . ' while looking for <tpl:container>s and <tpl:alter>s.');

			if ($token->name === 'alter')
				$this->setupAlter($token);
			elseif ($token->name !== 'container')
				$token->toss('Unexpected element: ' . $token->prettyName());

			break;

		case 'comment':
		case 'comment-start':
		case 'comment-end':
			// Eat silently.  Yum, yum, comments are tasty.
			break;

		case 'content':
			if (trim($token->data) !== '')
				$token->toss('Unexpected content when looking for <tpl:container>s and <tpl:alter>s.  Put it in a comment, perhaps?');

			// Otherwise, just whitespace, ignore it.
			break;

		default:
			$token->toss('Unexpected ' . $token->type . ' when looking for <tpl:container>s and <tpl:alter>s.');
		}
	}

	protected function parseInAlter(ToxgToken $token)
	{
		switch ($token->type)
		{
		case 'tag-end':
			if ($token->type === 'tag-end' && $token->nsuri == ToxgTemplate::TPL_NAMESPACE && $token->name === 'alter')
			{
				// Okay, let's end it.
				$this->finalizeAlter();
				break;
			}

			// Intentional fallthrough, any other tag-ends should be copied.

		default:
			// We copy everything else.
			// !!! Maybe there's a more efficient way, like storing the tokens?
			$this->parse_alter['data'] .= $token->data;
		}
	}

	protected function setupAlter(ToxgToken $token)
	{
		if (!isset($token->attributes['match'], $token->attributes['position']))
			$token->toss('Element tpl:alter must have match="ns:template" and position="before" or similar.');
		if (!isset($this->alters[$token->attributes['position']]))
			$token->toss('Unsupported position for tpl:alter.');

		$this->parse_state = 'alter';
		$this->parse_alter = &$this->alters[$token->attributes['position']][];

		$this->parse_alter['token'] = $token;
		$this->parse_alter['file'] = $token->file;
		$this->parse_alter['line'] = $token->line;
		$this->parse_alter['data'] = '';
		$this->parse_alter['match'] = $token->attributes['match'];
		$this->parse_alter['name'] = isset($token->attributes['name']) ? $token->attributes['name'] : false;
		if ($this->parse_alter['name'] !== false)
		{
			list($ns, $name) = explode(':', $this->parse_alter['name']);
			$nsuri = $token->getNamespace($ns);
			if (empty($ns) || empty($name) || empty($nsuri))
				$token->toss('Invalid name for tpl:alter');
		}
	}

	protected function finalizeAlter()
	{
		$this->parse_state = 'outside';

		if (!empty($this->parse_alter['name']) && !in_array($this->parse_alter['name'], $this->called_overlays))
		{
			$this->parse_alter = false;
			return true;
		}

		// Load the matches now, we don't do it previously anymore because an alter not called may not have any alters
		$matches = preg_split('~[ \t\r\n]+~', $this->parse_alter['match']);
		$this->parse_alter['match'] = array();
		foreach ($matches as $match)
		{
			if (strpos($match, ':') === false)
				$this->parse_alter['token']->toss('Every matched element should have a namespace, ' . $match . ' didn\'t have one.');

			list ($ns, $name) = explode(':', $match, 2);

			$nsuri = $this->parse_alter['token']->getNamespace($ns);
			if ($nsuri === false)
				$this->parse_alter['token']->toss('You need to declare namespaces even for matched elements (' . $ns . ' was undeclared.)');

			// Just store it "fully qualified"...
			$this->parse_alter['match'][] = $nsuri . ':' . $name;
		}

		$this->parse_alter['source'] = new ToxgSource($this->parse_alter['data'], $this->parse_alter['file'], $this->parse_alter['line']);
		$this->parse_alter['source']->copyNamespaces($this->source);
	}

	public function parsedElement(ToxgToken $token, ToxgParser $parser)
	{
		// This is where we hook into the parser.  It's sorta complicated, because of positions.
		// When you use a template or something, we modify its usage inline.
		// For "before": BEFORE template start/empty tag.
		// For "beforecontent": AFTER template start tag, or BEFORE template empty tag.
		// For "aftercontent": BEFORE template end tag, or AFTER template empty tag.
		// For "after": AFTER template end tag.

		// We don't care about instructions, just templates.
		if ($token->nsuri == ToxgTemplate::TPL_NAMESPACE)
			return;

		$fqname = $token->nsuri . ':' . $token->name;

		if ($token->type === 'tag-start')
		{
			if (!isset($this->match_recursion[$fqname]))
				$this->match_recursion[$fqname] = 0;
			$this->match_recursion[$fqname]++;
			array_push($this->match_tree, $token);

			// Maybe this is dumb, I can't really think of when recursing once will even be okay?
			if ($this->match_recursion[$fqname] > self::RECURSION_LIMIT)
				$token->toss('Potential alter recursion detected on ' . $token->prettyName() . '.');

			$this->insertMatchedAlters('before', 'normal', $token, $parser);
			$this->insertMatchedAlters('beforecontent', 'defer', $token, $parser);
		}
		elseif ($token->type === 'tag-end')
		{
			$this->match_recursion[$fqname]--;
			$close_token = array_pop($this->match_tree);

			if ($close_token->nsuri != $token->nsuri || $close_token->name != $token->name)
				$token->toss('Expecting the close tag from ' . $close_token->prettyName() . ' in ' . $close_token->file . ', line ' . $close_token->line . '.');

			$this->insertMatchedAlters('aftercontent', 'normal', $close_token, $parser);
			$this->insertMatchedAlters('after', 'defer', $close_token, $parser);
		}
		elseif ($token->type === 'tag-empty')
		{
			$this->insertMatchedAlters('before', 'normal', $token, $parser);
			$this->insertMatchedAlters('beforecontent', 'normal', $token, $parser);
			$this->insertMatchedAlters('aftercontent', 'defer', $token, $parser);
			$this->insertMatchedAlters('after', 'defer', $token, $parser);
		}
	}

	protected function insertMatchedAlters($position, $defer, ToxgToken $token, ToxgParser $parser)
	{
		// We need the fully-qualified name to do matching.
		$fqname = $token->nsuri . ':' . $token->name;

		$alters = $this->alters[$position];
		foreach ($alters as $alter)
		{
			if (!$alter)
				continue;

			if (in_array($fqname, $alter['match']))
				$parser->insertSource(clone $alter['source'], $defer === 'defer');
		}
	}
}
?>
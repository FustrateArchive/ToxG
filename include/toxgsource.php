<?php

/**
 * class ToxgSource
 *
 * Represents the source code to a template or overlay.  Provides lexing facilities.
 *
 * The use of this class is to parse source into tokens, which are chunks of categorized
 * source code.  Since namespaces also change per source, it manages those as well.
 * Tokens are represented be an array, which has this basic format:
 *
 *   - file: The file in which the token appeared (for errors.)
 *   - line: The line the token /started/ on (for errors.)
 *   - type: The type of token (see below.)
 *   - data: The contents of the token (source code.)
 *
 * The standard token types are:
 *
 *   - var-ref:       A reference to a variable. ({$x})
 *   - lang-ref:      A reference to a language string. ({#x})
 *   - tag-start:     A start tag. ({tpl:if} or <tpl:if>)
 *   - tag-empty:     An empty tag. (<tpl:if />)
 *   - tag-end:       An end tag. (</tpl:if>)
 *   - cdata-start:   Start of CDATA. (<![CDATA[)
 *   - cdata-end:     End of CDATA. (]]>)
 *   - comment-start: The start of a comment. (<!---)
 *   - comment-end:   The end of a comment. (--->)
 *   - comment:       The contents of a comment.
 *   - content:       Any other HTML.
 *
 * The basic use of this class will look like:
 *
 * $source = new ToxgSource($data, 'filename.tox');
 * while ($token = $source->readToken())
 *     do_something($token);
 *
 * $nsuri = $source->getNamespace($ns);
 */
class ToxgSource
{

	protected $data = null;
	protected $data_pos = 0;
	protected $data_buffer = '';
	protected $file = null;
	protected $line = 1;
	protected $namespaces = array();
	protected $wait_comment = false;
	protected static $cache = array();
	protected $tokens = array();
	protected $token_index = -1;

	public static function Factory($data, $file, $line = 1)
	{
		if (!isset(self::$cache[$file]))
			return self::$cache[$file] = new self($data, $file, $line);
		else
			return clone self::$cache[$file];
	}

	public function __clone()
	{
		$this->resetTokenIndex();
	}

	protected function __construct($data, $file, $line = 1)
	{
		if ($data === false)
			throw new ToxgException('Unable to read template file.', $file, 0);

		$this->file = $file;
		$this->line = $line;

		// We simply store every piece of the file into the buffer and get rid of the resource,
		// saves headache
		if (is_resource($data))
			while (!feof($data))
				$this->data_buffer .= fread($data, 8092);
		else
			$this->data_buffer = $data;

		unset($data);
	}

	public function setNamespaces(array $uris)
	{
		$this->namespaces = $uris;
	}

	public function addNamespace($name, $uri)
	{
		$this->namespaces[$name] = $uri;
	}

	public function copyNamespaces(ToxgSource $source)
	{
		foreach ($source->namespaces as $ns => $uri)
			$this->namespaces[$ns] = $uri;
	}

	public function getNamespace($name)
	{
		if (isset($this->namespaces[$name]))
			return $this->namespaces[$name];
		else
			return false;
	}

	public function readToken()
	{
		$this->token_index++;
		if ($this->isDataEOF() && isset($this->tokens[$this->token_index]))
			return $this->tokens[$this->token_index];

		if ($this->isDataEOF())
		{
			if ($this->wait_comment !== false)
				throw new ToxgException('Unterminated comment started on line ' . $this->wait_comment . '.', $this->file, $this->line);
			return false;
		}

		$this->tokens[$this->token_index] = $this->readStringToken();

		return $this->tokens[$this->token_index];
	}

	public function isDataEOF()
	{
		if ($this->data_pos < strlen($this->data_buffer))
			return false;
		return true;
	}

	public function isEndOfTokens()
	{
		if (($ret = $this->isDataEOF()) === true)
			return ($this->token_index >= (count($this->tokens) - 1));
		return false;
	}

	protected function resetTokenIndex()
	{
		$this->token_index = -1;
	}

	protected function readStringToken()
	{
		if ($this->wait_comment !== false)
			return $this->readComment();

		switch ($this->data_buffer[$this->data_pos])
		{
		case '<':
			return $this->readTagToken();
			break;

		case '{':
			return $this->readCurlyToken();
			break;

		case ']':
			if ($this->firstPosOf(']]>') === $this->data_pos)
				return $this->makeToken('cdata-end', strlen(']]>'));

			// Intentional fall-through, anything else after ] is fine.

		default:
			return $this->readContent();
		}
	}

	protected function readComment()
	{
		if ($this->firstPosOf('--->') === $this->data_pos)
		{
			$this->wait_comment = false;
			return $this->makeToken('comment-end', strlen('--->'));
		}

		// Find the next interesting character.
		$next_pos = $this->firstPosOf('--->');
		if ($next_pos === false)
			$next_pos = strlen($this->data_buffer);

		return $this->makeToken('comment', $next_pos - $this->data_pos);
	}

	protected function readContent($offset = 0)
	{
		// Find the next interesting character.
		$next_pos = $this->firstPosOf(array('<', '{', ']]>'), $offset);
		if ($next_pos === false)
			$next_pos = strlen($this->data_buffer);

		return $this->makeToken('content', $next_pos - $this->data_pos);
	}

	protected function readTagToken()
	{
		// CDATA sections toggle escaping.
		if ($this->firstPosOf('<![CDATA[') === $this->data_pos)
			return $this->makeToken('cdata-start', strlen('<![CDATA['));

		// Comments can comment out commands, which won't get processed.
		if ($this->firstPosOf('<!---') === $this->data_pos)
		{
			// This tells us to do nothing until a --->.
			$this->wait_comment = $this->line;
			return $this->makeToken('comment-start', strlen('<!---'));
		}

		// Must be namespaced or not interesting, so bail early if obviously not.
		$ns_mark = $this->firstPosOf(':', 1);
		if ($ns_mark !== false)
		{
			$ns = substr($this->data_buffer, $this->data_pos + 1, $ns_mark - $this->data_pos - 1);

			// Oops, don't look at the / at the front...
			if ($ns[0] === '/')
				$ns = substr($ns, 1);

			if (!self::validNCName($ns))
				$ns = false;
		}
		else
			$ns = false;

		// Okay, then, the namespace was found invalid so just treat it as content.
		if ($ns === false)
			return $this->readContent(1);

		return $this->readGenericTag('tag', '>', '<', 1 + strlen($ns) + 1);
	}

	protected function readCurlyToken()
	{
		// Make sure it's something interesting and we're not wasting our time...
		if (strlen($this->data_buffer) <= $this->data_pos + 1)
			return $this->readContent(1);
		$next_c = $this->data_buffer[$this->data_pos + 1];

		// We support {$var}, {#lang}, and {tpl:stuff /}.
		if ($next_c === '$')
			$type = 'var-ref';
		elseif ($next_c === '#')
			$type = 'lang-ref';
		else
		{
			// Could still be a var-ref in form CLASS::constant or CLASS::value.
			$type = 'tag';

			$ns_mark = $this->firstPosOf(':', 1);
			if ($ns_mark !== false)
			{
				$ns = substr($this->data_buffer, $this->data_pos + 1, $ns_mark - $this->data_pos - 1);

				// Oops, don't look at the / at the front...
				if ($ns[0] === '/')
					$ns = substr($ns, 1);

				if (!self::validNCName($ns))
					$ns = false;
				elseif ($this->data_buffer[$ns_mark + 1] === ':')
					$type = 'var-ref';
				// What we're checking here is that we don't have this: {key:'value'}...
				// Or in other words, after the : we need an alphanumeric char or similar.
				elseif (!self::validNCName($this->data_buffer[$ns_mark + 1]))
					$ns = false;
			}
			else
				$ns = false;

			if ($ns === false && $type === 'tag')
				return $this->readContent(1);
		}

		// Now it's time to parse a tag, lang, or var.
		return $this->readGenericTag($type, '}', '{', 1);
	}

	protected function findClose($close_tag, $stack_tag, $offset = 0)
	{
		// Find the closing tag
		$stack = 0;
		$stack_tag = (array) $stack_tag;
		$close_tag = (array) $close_tag;
		for ($pos = $this->data_pos + $offset; $pos < strlen($this->data_buffer); $pos++)
		{
			if (in_array($this->data_buffer[$pos], $close_tag) && empty($stack))
				return $pos;
			elseif (in_array($this->data_buffer[$pos], $close_tag))
				$stack--;
			elseif (in_array($this->data_buffer[$pos], $stack_tag))
				$stack++;
		}

		return false;
	}

	protected function readGenericTag($type, $end_c, $stack_c, $offset)
	{
		// Now it's time to parse a tag.  Start after any namespace/</etc. we already found.
		$end_pos = $this->data_pos + $offset;
		$finality = strlen($this->data_buffer);
		while ($end_pos < $finality)
		{
			// The only way to end a tag is >/}, but we respect quotes too.
			$end_bracket = $this->findClose($end_c, $stack_c, $offset);
			$quote = strpos($this->data_buffer, '"', $end_pos);

			// If the > is before the ", we're done.
			if ($end_bracket !== false && ($quote === false || $end_bracket < $quote))
			{
				$end_pos = $end_bracket + 1;
				break;
			}

			if ($quote !== false)
			{
				$quote = strpos($this->data_buffer, '"', $quote + 1);
				if ($quote === false)
					throw new ToxgException('Unclosed quote or unexpectedly long tag.', $this->file, $this->line);

				$end_pos = $quote + 1;
			}
			else
				throw new ToxgException('Unclosed or unexpectedly long instruction, try breaking it up.', $this->file, $this->line);
		}

		if ($type === 'tag')
		{
			// Last char is > or }, so an empty tag would have a / before that.
			if ($this->data_buffer[$end_pos - 2] === '/')
				$type = 'tag-empty';
			// And... obviously, if the second char is a /, it's an end tag.
			elseif ($this->data_buffer[$this->data_pos + 1] === '/')
				$type = 'tag-end';
			else
				$type = 'tag-start';
		}

		return $this->makeToken($type, $end_pos - $this->data_pos);
	}

	protected function makeToken($type, $chars)
	{
		$data = substr($this->data_buffer, $this->data_pos, $chars);
		$this->data_pos += $chars;

		$token = array(
			'file' => $this->file,
			'line' => $this->line,
			'type' => $type,
			'data' => $data,
		);
		$tok = new ToxgToken($token, $this);

		// If it wasn't actually a valid tag, let's go back and eat less after all.
		if ($tok->type != $type && $tok->type == 'content' && $chars > 1)
		{
			// Backpeddle....
			$this->data_pos -= $chars;
			return $this->makeToken('content', 1);
		}

		// This token was now, next token will move forward as much as this token did.
		$this->line += substr_count($data, "\n");
		return $tok;
	}

	protected function firstPosOf($find, $offset = 0)
	{
		$least = false;

		// Just look for each and take the lowest.
		$find = (array) $find;
		foreach ($find as $arg)
		{
			$found = strpos($this->data_buffer, $arg, $this->data_pos + $offset);
			if ($found !== false && ($least === false || $found < $least))
				$least = $found;
		}

		return $least;
	}

	public static function validNCName($ns)
	{
		// See XML spec for the source of this list.
		// !!! All Unicode allowed.  This is wrong, but manual UTF-8 makes it a pain.
		$first_char = "A..Z_a..z\x80..\xFF";
		$rest_chars = $first_char . '-.0..9';

		// Instead of a regex, we're using trim-syntax.  It trims out the valid chars above...
		// If there are any other chars left, then it wasn't valid.
		if (trim($ns, $rest_chars) !== '')
			return false;
		if (strlen($ns) == 0 || trim($ns[0], $first_char) !== '')
			return false;

		return true;
	}
}
?>
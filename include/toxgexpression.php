<?php

class ToxgExpression
{
	protected $data = null;
	protected $data_len = 0;
	protected $data_pos = 0;
	protected $unterminated_string = false;
	protected $token = null;
	protected $built = array();
	protected $is_raw = false;

	public function __construct($data, ToxgToken $token)
	{
		$this->data = $data;
		$this->data_len = strlen($data);
		$this->token = $token;
	}

	public function parseInterpolated()
	{
		// An empty string, let's short-circuit this common case.
		if ($this->data_len === 0)
			$this->readString(0);

		while ($this->data_pos < $this->data_len)
		{
			if (!empty($this->built))
				$this->built[] = ' . ';

			switch ($this->data[$this->data_pos])
			{
			case '{':
				$this->readReference();
				break;

			default:
				$this->readStringInterpolated();
			}
		}

		return $this->getCode();
	}

	public function parseVariable($allow_lang = true)
	{
		$this->eatWhite();

		if ($this->data_len === 0 || $this->data[$this->data_pos] !== '{')
			$this->toss('expected variable reference.');

		$this->readReference($allow_lang);

		$this->eatWhite();
		if ($this->data_pos < $this->data_len)
			$this->toss('expecting only a variable reference.');

		return $this->getCode();
	}

	public function parseNormal($accept_raw = false)
	{
		// An empty string, let's short-circuit this common case.
		if ($this->data_len === 0)
			$this->toss('expected expression, found empty.');

		while ($this->data_pos < $this->data_len)
		{
			switch ($this->data[$this->data_pos])
			{
			case '{':
				$this->readReference();
				break;

			default:
				// !!! Maybe do more here?
				$this->readRaw();
			}
		}

		if ($this->is_raw && $accept_raw)
			return array($this->getCode(), true);
		else
			return $this->getCode();
	}

	public function getCode()
	{
		foreach ($this->built as $part)
		{
			// We'll get a "[] can't be used for reading" fatal error.
			if (substr($part, -2) === '[]' || substr($part, -3) === '[ ]')
				$this->toss('unable to parse properly.');
		}

		$expr = implode('', $this->built);

		$saved = error_reporting(E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR);
		$attempt = create_function('', 'return (' . $expr . ');');
		error_reporting($saved);

		if ($attempt === false)
			$this->toss('unable to parse properly.');

		return $expr;
	}

	protected function readStringInterpolated()
	{
		$pos = $this->firstPosOf('{');
		if ($pos === false)
			$pos = $this->data_len;

		// Should never happen?
		if ($pos === $this->data_pos)
			$this->toss('unexpected characters.');

		$this->readString($pos);
	}

	protected function readReference($allow_lang = true)
	{
		// Expect to be on a {.
		$this->data_pos++;

		$pos = $this->firstPosOf('}');
		if ($pos === false)
			$this->toss('unmatched braces.');

		switch ($this->data[$this->data_pos])
		{
		case '$':
			$this->readVarRef($pos);
			break;

		case '#':
			if ($allow_lang)
			{
				$this->readLangRef($pos);
				break;
			}

		default:
			// This could be a static.  If it is, we have a :: later on.
			$next = $this->firstPosOf('::');
			if ($next !== false && $next < $pos)
			{
				$this->built[] = $this->eatUntil($next);
				$this->built[] = $this->eatUntil($next + 2);
				$this->readVarRef($pos);
				break;
			}
			// Intentional fall-through on false.

			if ($allow_lang)
				$this->toss('expecting reference like {$name} or {#name}.');
			else
				$this->toss('expecting variable like {$name} inside braces.');
		}

		// Skip over the }.
		$this->data_pos++;
	}

	protected function readVarRef($end)
	{
		// It looks like this: $xyz.abc[$mno][nilla].$rpg
		// Which means:
		//   x.y.z = x [ y ] [ z ]
		//   x[y.z] = x [ y [ z ] ] 
		//   x[y][z] = x [ y ] [ z ]
		//   x[y[z]] = x [ y [ z ] ]
		//
		// When we hit a ., the next item is surrounded by brackets.
		// When we hit a [, the next item has a [ before it.
		// When we hit a ], there is no item, but just a ].

		while ($this->data_pos < $end)
		{
			$next = $this->firstPosOf(array('[', '.', ']', '->'), 1);
			if ($next === false || $next > $end)
				$next = $end;

			$c = $this->data[$this->data_pos];
			$this->data_pos++;

			switch ($c)
			{
			case '$':
				$this->built[] = '$' . self::makeVarName($this->eatUntil($next));
				break;

			case '.':
				$this->built[] = '[';
				$this->readVarPart($next, true);
				$this->built[] = ']';
				break;

			case '[':
				$this->built[] = '[';
				$this->eatWhite();
				$this->readVarPart($next, false);
				$this->eatWhite();
				break;

			case ']':
				$this->built[] = ']';
				break;

			// When we hit a ->, first we output a -... then, next round...
			case '-':
				$this->built[] = '->';
				break;

			// We output the property/etc.
			case '>':
				$this->built[] = $this->eatUntil($next);
				break;

			default:
				// A constant, like a class constant: {Class::CONST}.
				// We want to grab the "C", so we take a step back and eat.
				$this->data_pos--;
				$this->built[] = $this->eatUntil($next);
			}
		}
	}

	protected function readVarPart($end, $require = false)
	{
		switch ($this->data[$this->data_pos])
		{
		case '$':
			$this->readVarRef($end);
			break;

		case '#':
			$this->readLangRef($end);
			break;

		// Is it "raw"? If so, we remove htmlspecialchars
		case 'r':
			$this->readRawRef($end, $require);
			break;

		default:
			if ($require && $this->data_pos == $end)
				$this->toss('incomplete expression.');
			$this->readString($end);
		}
	}

	protected function readRawRef($end, $require)
	{
		$string = substr($this->data, $this->data_pos, 3);

		if ($string == 'raw')
		{
			unset($this->built[count($this->built) - 1]);

			$this->data_pos = $end;
			$this->is_raw = true;
		}
		else
		{
			if ($require && $this->data_pos == $end)
				$this->toss('incomplete expression.');
			$this->readString($end);
		}

		return true;
	}

	protected function readLangRef($end)
	{
		$this->built[] = 'lang(';

		if ($this->data_pos >= $end - 1)
			$this->toss('empty language reference.');

		while ($this->data_pos < $end)
		{
			$next = $this->firstPosOf(array(':'), 1);

			// If this is a quoted string, we continue over
			if ($this->firstPosOf(array('"')) == $this->data_pos + 1 && !$this->unterminated_string)
			{
				$next = $this->firstPosOf(array('"'), 2);
				$this->data_pos++;
				$this->unterminated_string = true;
			}
			// Or a quoted string is coming to an end
			elseif ($this->unterminated_string && $this->data_pos == $this->firstPosOf(array('"')))
			{
				$this->data_pos = $this->firstPosOf(array('"')) + 1;
				$next = $this->firstPosOf(array(':'), 1);
				$this->unterminated_string = false;
			}

			if ($next === false || $next > $end)
				$next = $end;

			$this->data_pos++;
			$this->readVarPart($next, true);

			if ($this->data_pos < $end)
				$this->built[] = ', ';
		}

		$this->built[] = ')';
	}

	protected function readString($end)
	{
		$this->built[] = '\'' . addcslashes($this->eatUntil($end), '\\\'') . '\'';
	}

	protected function readRaw()
	{
		$pos = $this->firstPosOf('{');
		if ($pos === false)
			$pos = $this->data_len;

		// Should never happen?
		if ($pos === $this->data_pos)
			$this->toss('unexpected characters.');

		$this->built[] = $this->eatUntil($pos);
	}

	protected function toss($error)
	{die(print_r($this->built));
		$this->token->toss('Invalid expression ' . $this->data . ', ' . $error);
	}

	protected function eatWhite()
	{
		while ($this->data_pos < $this->data_len)
		{
			$c = ord($this->data[$this->data_pos]);

			// Okay, found whitespace (space, tab, CR, LF, etc.)
			if ($c != 32 && $c != 9 && $c != 10 && $c != 13)
				break;

			$this->data_pos++;
		}
	}

	protected function eatUntil($pos)
	{
		$data = substr($this->data, $this->data_pos, $pos - $this->data_pos);
		$this->data_pos = $pos;

		return $data;
	}

	protected function firstPosOf($find, $offset = 0)
	{
		$least = false;

		// Just look for each and take the lowest.
		$find = (array) $find;
		foreach ($find as $arg)
		{
			$found = strpos($this->data, $arg, $this->data_pos + $offset);
			if ($found !== false && ($least === false || $found < $least))
				$least = $found;
		}

		return $least;
	}

	public static function variable($string, ToxgToken $token)
	{
		$expr = new ToxgExpression($string, $token);
		return $expr->parseVariable();
	}

	public static function variableNotLang($string, ToxgToken $token)
	{
		$expr = new ToxgExpression($string, $token);
		return $expr->parseVariable(false);
	}

	public static function stringWithVars($string, ToxgToken $token)
	{
		$expr = new ToxgExpression($string, $token);
		return $expr->parseInterpolated();
	}

	public static function normal($string, ToxgToken $token, $accept_raw = false)
	{
		return self::boolean($string, $token, $accept_raw);
	}

	public static function boolean($string, ToxgToken $token, $accept_raw = false)
	{
		$expr = new ToxgExpression($string, $token);
		return $expr->parseNormal($accept_raw);
	}

	public static function makeVarName($name)
	{
		return preg_replace('~[^a-zA-Z0-9_]~', '_', $name);
	}

	public static function makeTemplateName($nsuri, $name)
	{
		return 'tpl_' . md5($nsuri) . '_' . self::makeVarName($name);
	}
}
?>
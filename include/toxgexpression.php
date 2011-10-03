<?php

class ToxgExpression
{
	protected $data = null;
	protected $data_len = 0;
	protected $data_pos = 0;
	protected $token = null;
	protected $built = array();
	protected $is_raw = false;

	protected static $lang_function = 'lang';
	protected static $format_function = 'format';

	public static function setLangFunction($func)
	{
		self::$lang_function = $func;
	}

	public static function setFormatFunction($func)
	{
		self::$format_function = $func;
	}

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

		return $this->validate();
	}

	public function parseVariable($allow_lang = true)
	{
		$this->eatWhite();

		if ($this->data_len === 0 || $this->data[$this->data_pos] !== '{')
			$this->toss('expression_expected_var');

		$this->readReference($allow_lang);

		$this->eatWhite();
		if ($this->data_pos < $this->data_len)
			$this->toss('expression_expected_var_only');

		return $this->validate();
	}

	public function parseNormal($accept_raw = false)
	{
		// An empty string, let's short-circuit this common case.
		if ($this->data_len === 0)
			$this->toss('expression_empty');

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
			return array($this->validate(), true);
		else
			return $this->validate();
	}

	public function validate()
	{
		foreach ($this->built as $part)
		{
			// We'll get a "[] can't be used for reading" fatal error.
			if (substr($part, -2) === '[]' || substr($part, -3) === '[ ]')
				$this->toss('expression_unknown_error');
		}

		$expr = $this->getCode();

		// !!! Well, create_function() leaks memory because there's no destroy_function().  Maybe we can avoid this...
		$saved = error_reporting(E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR);
		$attempt = create_function('', 'return (' . $expr . ');');
		error_reporting($saved);

		if ($attempt === false)
			$this->toss('expression_unknown_error');

		return $expr;
	}

	public function getCode()
	{
		return implode($this->built);
	}

	protected function readStringInterpolated()
	{
		$pos = $this->firstPosOf('{');
		if ($pos === false)
			$pos = $this->data_len;

		// Should never happen, unless we were called wrong.
		if ($pos === $this->data_pos)
			$this->toss('expression_unknown_error');

		$this->readString($pos);
	}

	protected function readReference($allow_lang = true)
	{
		// Expect to be on a {.
		$this->data_pos++;

		$pos = $this->firstPosOf('}');
		if ($pos === false)
			$this->toss('expression_braces_unmatched');

		switch ($this->data[$this->data_pos])
		{
		case '$':
			$this->readVarRef();

			if ($this->data_pos >= $this->data_len || $this->data[$this->data_pos] !== '}')
			{
				if ($this->data[$this->data_pos] === ']')
					$this->toss('expression_brackets_unmatched');
				else
					$this->toss('expression_unknown_error');
			}
			break;

		case '#':
			if ($allow_lang)
			{
				$this->readLangRef();

				if ($this->data_pos >= $this->data_len || $this->data[$this->data_pos] !== '}')
					$this->toss('expression_unknown_error');

				break;
			}
			else
				$this->toss('expression_expected_ref_nolang');
		case '%':
			$this->readFormatRef();
			if ($this->data_pos >= $this->data_len || $this->data[$this->data_pos] !== '}')
				$this->toss('expression_unknown_error');
			break;

		default:
			// This could be a static.  If it is, we have a :: later on.
			$next = $this->firstPosOf('::');
			if ($next !== false && $next < $pos)
			{
				$this->built[] = $this->eatUntil($next);
				$this->built[] = $this->eatUntil($next + 2);
				$this->readVarRef();

				if ($this->data_pos >= $this->data_len || $this->data[$this->data_pos] !== '}')
				{
					if ($this->data[$this->data_pos] === ']')
						$this->toss('expression_brackets_unmatched');
					else
						$this->toss('expression_unknown_error');
				}
				break;
			}
			// Intentional fall-through on false.

			if ($allow_lang)
				$this->toss('expression_expected_ref');
			else
				$this->toss('expression_expected_ref_nolang');
		}

		// Skip over the }.
		$this->data_pos++;
	}

	protected function readVarRef()
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

		$brackets = 0;
		while ($this->data_pos < $this->data_len)
		{
			$next = $this->firstPosOf(array('[', '.', ']', '->', '}', ':'), 1);
			if ($next === false)
				$next = $this->data_len;

			$c = $this->data[$this->data_pos];
			$this->data_pos++;

			switch ($c)
			{
			case '$':
				$name = $this->eatUntil($next);
				if ($name === '')
					$this->toss('expression_var_name_empty');

				$this->built[] = '$' . self::makeVarName($name);
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

				$brackets++;
				break;

			case ']':
				// Ah, hit the end, jump out.  Must be a nested one.
				if ($brackets <= 0)
				{
					$this->data_pos--;
					break 2;
				}

				$this->built[] = ']';

				$brackets--;
				break;

			// When we hit a ->, first we output a -... then, next round...
			case '-':
				$this->built[] = '->';
				break;

			// We output the property/etc.
			case '>':
				$this->built[] = $this->eatUntil($next);
				break;

			// All done - but don't skip it, our caller doesn't expect that.
			case '}':
				$this->data_pos--;
				break 2;

			// Maybe we're done with this?
			case ':':
				$this->built[] = ',';
				$this->readVarPart($next);
				break 2;

			default:
				// A constant, like a class constant: {Class::CONST}.
				// We want to grab the "C", so we take a step back and eat.
				$this->data_pos--;
				$this->built[] = $this->eatUntil($next);
			}
		}

		if ($brackets != 0)
			$this->toss('expression_brackets_unmatched');
	}

	protected function readVarPart($end, $require = false)
	{
		switch ($this->data[$this->data_pos])
		{
		case '$':
			$this->readVarRef();
			break;

		case '#':
			$this->readLangRef();
			break;
		case '%':
			$this->readFormatRef();
			break;

		case '{':
			$this->readReference();
			break;

		// Is it "raw"? If so, we remove htmlspecialchars
		case 'r':
			$this->readRawRef($end, $require);
			break;

		default:
			if ($require && $this->data_pos == $end)
				$this->toss('expression_incomplete');
			$this->readString($end);
		}
	}

	protected function readRawRef($end, $require)
	{
		$string = substr($this->data, $this->data_pos - 1, 4);

		// Make sure it's not just an index that starts with raw
		if ($string == ':raw')
		{
			// Get the last index of array
			$keys = array_keys($this->built);
			$last_key = $keys[count($keys) - 1];

			unset($this->built[$last_key]);
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

	protected function readLangRef()
	{
		// It looks like this: #xyz.abc[$mno][nilla].$rpg
		// Which means:
		//   x.y.z = x [ y ] [ z ]
		//   x[y.z] = x [ y [ z ] ] 
		//   x[y][z] = x [ y ] [ z ]
		//   x[y[z]] = x [ y [ z ] ]
		//
		// When we hit a ., the next item is surrounded by brackets.
		// When we hit a [, the next item has a [ before it.
		// When we hit a ], there is no item, but just a ].
		$brackets = 0;
		$key = true;
		if ($this->data_pos >= $this->data_len - 1 || $this->data[$this->data_pos + 1] === ':' || $this->data[$this->data_pos + 1] === '}')
			$this->toss('expression_lang_name_empty');
		while ($this->data_pos < $this->data_len)
		{
			$next = $this->firstPosOf(array('[', '.', ']', '}', ':'), 1);
			if ($next === false)
				$next = $this->data_len;
			$c = $this->data[$this->data_pos];
			$this->data_pos++;
			switch ($c)
			{
			case '#':
				$name = $this->eatUntil($next);
				if ($name === '')
					$this->toss('expression_lang_name_empty');
				$this->built[] = self::$lang_function . '(array(\'' . $name . '\'';
				break;
			case '.':
				if ($key)
				{
					$this->built[] = ',';
					$this->readVarPart($next, true);
				}
				else
				{
					$this->built[] = '[';
					$this->readVarPart($next, true);
					$this->built[] = ']';
				}
				break;
			case '[':
				if ($key)
				{
					$this->built[] = ',';
					$this->eatWhite();
					$this->readVarPart($next, false);
					$this->eatWhite();
				}
				else
				{
					$this->built[] = '[';
					$this->eatWhite();
					$this->readVarPart($next, false);
					$this->eatWhite();
				}
				$brackets++;
				break;
			case ']':
				// Ah, hit the end, jump out.  Must be a nested one.
				if ($brackets <= 0)
				{
					// Quick exit, but we have to close the function first
					$this->built[] = '))';

					$this->data_pos--;
					break 2;
				}
				if (!$key)
					$this->built[] = ']';
				$brackets--;
				break;
			// All done - but don't skip it, our caller doesn't expect that.
			case '}':
				$this->data_pos--;
				$this->built[] = '))';
				break 2;
			// Maybe we're done with this?
			case ':':
				if ($key)
				{
					$this->built[] = '), array(';
				}
				else
				{
					$this->built[] = ',';
				}
				$key = false;
				$this->readVarPart($next);
				break;
			}
		}
		if ($brackets != 0)
			$this->toss('expression_brackets_unmatched');
	}
	protected function readFormatRef()
	{
		// It looks like this: %type:$mno:"nilla":$rpg
		// Which means:
		//   use formatter "type"
		//   on $mno
		//   with paramaters "nilla" and $rpg

		$brackets = 0;

		// Are we still looking for these?
		$type = true;
		$value = true;

		if ($this->data_pos >= $this->data_len - 1 || $this->data[$this->data_pos + 1] === ':' || $this->data[$this->data_pos + 1] === '}')
			$this->toss('expression_format_type_empty');

		while ($this->data_pos < $this->data_len)
		{
			$next = $this->firstPosOf(array('[', '.', ']', '}', ':'), 1);
			if ($next === false)
				$next = $this->data_len;
			$c = $this->data[$this->data_pos];
			$this->data_pos++;

			switch ($c)
			{
			case '%':
				$name = $this->eatUntil($next);
				if ($name === '')
					$this->toss('expression_format_name_empty');
				$this->built[] = self::$format_function . '(\'' . $name . '\'';
				break;
			case '.':
				if ($type || $value)
				{
					$this->built[] = ',';
					$this->readVarPart($next, true);
				}
				else
				{
					$this->built[] = '[';
					$this->readVarPart($next, true);
					$this->built[] = ']';
				}
				break;
			case '[':
				if ($type || $value)
				{
					$this->built[] = ',';
					$this->eatWhite();
					$this->readVarPart($next, false);
					$this->eatWhite();
				}
				else
				{
					$this->built[] = '[';
					$this->eatWhite();
					$this->readVarPart($next, false);
					$this->eatWhite();
				}
				$brackets++;
				break;
			case ']':
				// Ah, hit the end, jump out.  Must be a nested one.
				if ($brackets <= 0)
				{
					// Quick exit, but we have to close the function first
					$this->built[] = $value ? ')' : '))';

					$this->data_pos--;
					break 2;
				}
				if (!$type && !$value)
					$this->built[] = ']';
				$brackets--;
				break;
			// All done - but don't skip it, our caller doesn't expect that.
			case '}':
				$this->data_pos--;
				$this->built[] = $value ? ')' : '))';
				break 2;
			// Maybe we're done with this?
			case ':':
				if ($type)
				{
					$this->built[] = ', ';
					$type = false;
				}
				else if ($value)
				{
					$this->built[] = ', array(';
					$value = false;
				}
				else
				{
					$this->built[] = ',';
				}
				$this->readVarPart($next);
				break;
			}
		}
		if ($brackets != 0)
			$this->toss('expression_brackets_unmatched');
	}

	protected function readString($end)
	{
		$value = $this->eatUntil($end);

		// Did we split inside a string literal? Try to find the rest
		if (!empty($value) && ($value[0] === '"' || $value[0] === '\'') && $value[0] !== substr($value, -1))
		{
			$next = $this->firstPosOf(array($value[0]));
			$value = substr($value, 1) . $this->eatUntil($next);
		}

		$this->built[] = '\'' . addcslashes($value, '\\\'') . '\'';
	}

	protected function readRaw()
	{
		$pos = $this->firstPosOf('{');
		if ($pos === false)
			$pos = $this->data_len;

		// Should never happen, unless we were called wrong?
		if ($pos === $this->data_pos)
			$this->toss('expression_unknown_error');

		$this->built[] = $this->eatUntil($pos);
	}

	protected function toss($error)
	{
		$this->token->toss('expression_invalid_meta', $this->data, ToxgException::format($error, array()));
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
		$expr = new self($string, $token);
		return $expr->parseVariable();
	}

	public static function variableNotLang($string, ToxgToken $token)
	{
		$expr = new self($string, $token);
		return $expr->parseVariable(false);
	}

	public static function stringWithVars($string, ToxgToken $token)
	{
		$expr = new self($string, $token);
		return $expr->parseInterpolated();
	}

	public static function normal($string, ToxgToken $token, $accept_raw = false)
	{
		return self::boolean($string, $token, $accept_raw);
	}

	public static function boolean($string, ToxgToken $token, $accept_raw = false)
	{
		$expr = new self($string, $token);
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

	/**
	 * I wasn't completely sure where to put this
	 * Recursively htmlspecialchar's a string or array
	 *
	 * @static
	 * @access public
	 * @param mixed $value The value to htmlspecialchar
	 * @return mixed
	 */
	public static function htmlspecialchars($value)
	{
		if (is_array($value))
			foreach ($value as $k => $v)
				$value[$k] = self::htmlspecialchars($v);
		else
			$value = htmlspecialchars($value, ENT_COMPAT, 'UTF-8');

		return $value;
	}
}
?>
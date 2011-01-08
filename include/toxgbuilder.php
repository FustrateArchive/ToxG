<?php

class ToxgBuilder
{
	protected $debugging = true;
	protected $data = null;
	protected $last_file = null;
	protected $last_line = 1;
	protected $prebuilder = null;
	protected $last_template = null;
	protected $has_emitted = false;
	protected $disable_emit = false;
	protected $emit_output = array();
	protected $common_vars = array();
	protected $listeners = array();

	public function __construct()
	{
		$this->listeners = array(
			'tag-start' => array(),
			'tag-empty' => array(),
			'tag-end' => array(),
			'tag-both' => array(),
			'*' => array(),
		);
	}

	public function __destruct()
	{
		if ($this->data !== null)
			@fclose($this->data);
	}

	public function disableDebugging($disable)
	{
		$this->debugging = !$disable;
	}

	public function setCommonVars(array $names)
	{
		$this->common_vars = $names;
	}

	public function setCacheFile($cache_file)
	{
		if (is_resource($cache_file))
			$this->data = $cache_file;
		else
		{
			$this->data = @fopen($cache_file, 'wt');
			if (!$this->data)
				throw new ToxgException('Unable to open output file: ' . $cache_file, '', 0);
		}

		$this->emitCode('<?php ');
	}

	public function setPrebuilder($prebuilder)
	{
		$this->prebuilder = $prebuilder;
	}

	// callback(ToxgBuilder $builder, $type, array $attributes, ToxgToken $token)
	public function listenEmit($nsuri, $name, $callback)
	{
		$this->listeners[$nsuri][$name][] = $callback;
	}

	protected function fireEmit(ToxgToken $token)
	{
		// This actually fires a whole mess of events, but easier to hook into.
		// In this case, it's cached, so it's fairly cheap.
		$this->fireActualEmit($token->nsuri, $token->name, $token);
		$this->fireActualEmit('*', $token->name, $token);
		$this->fireActualEmit($token->nsuri, '*', $token);
		$this->fireActualEmit('*', '*', $token);
	}

	protected function fireActualEmit($nsuri, $name, ToxgToken $token)
	{
		// If there are no listeners, nothing to do.
		if (empty($this->listeners[$nsuri]) || empty($this->listeners[$nsuri][$name]))
			return;

		$listeners = $this->listeners[$nsuri][$name];
		$args = array($this, $token->type, $token->attributes, $token);
		foreach ($listeners as $callback)
		{
			$result = call_user_func_array($callback, $args);
			if ($result === false)
				break;
		}
	}

	public function setupParser(ToxgParser $parser)
	{
		$parser->listen('parsedContent', array($this, 'parsedContent'));
		$parser->listen('parsedElement', array($this, 'parsedElement'));
	}

	public function parsedContent(ToxgToken $token, ToxgParser $parser)
	{
		$this->emitOutputString($token->data, $token);
	}

	public function parsedElement(ToxgToken $token, ToxgParser $parser)
	{
		if ($token->nsuri === ToxgTemplate::TPL_NAMESPACE)
		{
			$this->has_emitted = false;

			// Everything else is handled via a hook.
			if ($token->name === 'container')
				$this->handleTagContainer($token);
			elseif ($token->name === 'template')
				$this->handleTagTemplate($token);
			elseif ($token->name === 'content')
				$this->handleTagContent($token);

			$this->fireEmit($token);

			// If there was no emitted code, it's probably an error.
			if ($this->has_emitted === false && $this->debugging)
				$token->toss('Unrecognized or misspelled element tpl:' . $token->name . ' (or it didn\'t generate code.)');
		}
		else
		{
			$this->handleTagCall($token);

			$this->fireEmit($token);
		}
	}

	protected function handleTagContainer(ToxgToken $token)
	{
		// A container is just a thing to set namespaces, it does nothing.
		// However, we have to omit something or it will think it's unrecognized.
		$this->emitCode('');
	}

	protected function handleTagTemplate(ToxgToken $token)
	{
		// Assumption: can't be tag-empty (verified by parser.)
		if ($token->type === 'tag-start')
		{
			$this->last_template = $this->prebuilder->getTemplateForBuild($token);

			// Template was already built, so don't emit it again.
			if ($this->last_template['should_emit'] === false)
				$this->disable_emit = true;

			$this->emitTemplateStart($this->last_template['name'] . '_above', $token);
		}
		elseif ($token->type === 'tag-end')
		{
			// If we haven't output the below, output it now.
			if ($this->last_template['stage'] == 1)
			{
				$this->emitTemplateEnd(false, $token);
				$this->emitTemplateStart($this->last_template['name'] . '_below', $token);

				$this->last_template['stage'] = 2;
			}

			$this->emitTemplateEnd(true, $token);

			// Even if it wasn't disabled before, enable it until the next template.
			$this->disable_emit = false;
			$this->last_template = null;
		}
	}

	protected function handleTagContent(ToxgToken $token)
	{
		// Already hit one, can't have two.
		if ($this->last_template['stage'] == 2)
			$token->toss('Only one tpl:content is allowed per template.');

		// Assumption: must be tag-empty (verified by parser.)
		$this->emitTemplateEnd(false, $token);
		$this->emitTemplateStart($this->last_template['name'] . '_below', $token);

		// Mark that we've output the above AND below.
		$this->last_template['stage'] = 2;
	}

	protected function handleTagCall(ToxgToken $token)
	{
		$template = $this->prebuilder->getTemplateForCall($token);
		$name = addcslashes($template['name'], '\\\'');

		// This means we're passing through the value of $__toxg_params as-is.
		if (count($token->attributes) === 1 && isset($token->attributes[ToxgTemplate::TPL_NAMESPACE . ':all']))
			$args = true;
		else
		{
			$args = array();

			// When calling, we pass along the common vars.
			foreach ($this->common_vars as $var_name)
			{
				$k = '\'' . addcslashes(ToxgExpression::makeVarName($var_name), '\\\'') . '\'';
				$args[] = $k . ' => ' . ToxgExpression::variable('{$' . $var_name . '}', $token);
			}

			// We also pass any attributes along.
			$arg_names = array();
			foreach ($token->attributes as $k => $v)
			{
				$arg_names[] = ToxgExpression::makeVarName($k);

				$k = '\'' . addcslashes(ToxgExpression::makeVarName($k), '\\\'') . '\'';
				$args[] = $k . ' => ' . ToxgExpression::stringWithVars($v, $token);
			}

			// This checks the requires parameter to make sure they passed everything necessary.
			$required = array_diff($template['requires'], $arg_names);
			if (!empty($required))
				$token->toss('Template ' . $token->prettyName() . ' is missing the following attributes: ' . implode(', ', $required));

			// The parameters arguements, unfortunately we can't check this and actually throw a error
			$parameters = isset($template['parameters']) ? $template['parameters'] : array();
			foreach ($parameters as $parameter)
			{
				$k = '\'' . addcslashes(ToxgExpression::makeVarName($parameter), '\\\'') . '\'';
				$args[] = $k . ' => ' . ToxgExpression::variable('{$' . $parameter . '}', $token);
			}
		}

		if ($token->type == 'tag-start' || $token->type == 'tag-empty')
			$this->emitTagCall($name . '_above', $args, true, $template, $token);
		if ($token->type == 'tag-end' || $token->type == 'tag-empty')
			$this->emitTagCall($name . '_below', $args, false, $template, $token);
	}

	protected function emitTagCall($escaped_name, $escaped_arg_list, $first, $template, ToxgToken $token)
	{
		// Do we know for sure that it is defined?  If so, we can skip an if.
		if (!$template['defined'])
			$this->emitCode('if (function_exists(\'' . $escaped_name . '\')) {', $token);

		if ($first)
		{
			$this->emitCode('global $__toxg_argstack; if (!isset($__toxg_argstack)) $__toxg_argstack = array();', $token);

			if ($escaped_arg_list === true)
				$this->emitCode('$__toxg_args = $__toxg_params; $__toxg_argstack[] = &$__toxg_args;', $token);
			else
				$this->emitCode('$__toxg_args = array(' . implode(', ', $escaped_arg_list) . '); $__toxg_argstack[] = &$__toxg_args;', $token);
		}
		else
			$this->emitCode('global $__toxg_argstack; $__toxg_args = array_pop($__toxg_argstack);', $token);

		// Better to use a de-refenced call than call_user_func/_array, because of reference issue.
		$this->emitCode('$__toxg_func = \'' . $escaped_name . '\'; $__toxg_func($__toxg_args);', $token);

		if (!$template['defined'])
			$this->emitCode('}', $token);
	}

	protected function emitTemplateStart($escaped_name, ToxgToken $token)
	{
		$this->emitCode('function ' . $escaped_name . '(&$__toxg_params = array()) {');
		$this->emitCode('extract($__toxg_params, EXTR_SKIP);', $token);

		if ($this->debugging)
			$this->emitCode('ToxgErrors::register();');
	}

	protected function emitTemplateEnd($last, $token)
	{
		// This updates the parameters for the _below function.
		if (!$last)
		{
			$omit = array('\'__toxg_args\'', '\'__toxg_argstack\'', '\'__toxg_stack\'', '\'__toxg_params\'', '\'__toxg_func\'');
			$this->emitCode('$__toxg_params = compact(array_diff(array_keys(get_defined_vars()), array(' . implode(', ', $omit) . ')));', $token);
		}

		if ($this->debugging)
			$this->emitCode('ToxgErrors::restore();');

		$this->emitCode('}', $token);
	}

	public function finalize()
	{
		// Just end the file now.
		$this->emitCode(' ?>');
		fclose($this->data);
		$this->data = null;

		if ($this->last_template !== null)
			throw new ToxgException('Finished template cache file with an open template.', '', 0);
	}

	public function emitCode($code, ToxgToken $token = null)
	{
		$this->has_emitted = true;
		if ($this->disable_emit)
			return;

		$this->flushOutputCode();

		if ($this->debugging && $token !== null)
			$this->emitDebugPos($token);

		$this->emitCodeInternal($code);
	}

	public function emitOutputString($data, ToxgToken $token = null)
	{
		$this->has_emitted = true;
		if ($this->disable_emit)
			return;

		$this->emit_output[] = array(
			'type' => 'string',
			'data' => $data,
			'token' => $token,
		);
	}

	public function emitOutputParam($expr, ToxgToken $token = null)
	{
		$this->has_emitted = true;
		if ($this->disable_emit)
			return;

		$this->emit_output[] = array(
			'type' => 'param',
			'data' => $expr,
			'token' => $token,
		);
	}

	protected function flushOutputCode()
	{
		if (empty($this->emit_output))
			return;

		// We're going to enter and exit strings.
		$in_string = false;
		$first = true;

		foreach ($this->emit_output as $node)
		{
			if ($node['type'] === 'string')
			{
				// If we're not inside a string already, start one with debug info.
				if (!$in_string)
				{
					if ($this->emitDebugPos($node['token'], 'echo'))
						$first = true;
					$this->emitCodeInternal(($first ? 'echo ' : ', ') . '\'');
					$in_string = true;
				}

				$this->emitCodeInternal(addcslashes($node['data'], "'\\"));
			}
			elseif ($node['type'] === 'param')
			{
				if ($in_string)
				{
					$this->emitCodeInternal('\'');
					$in_string = false;
				}

				// Just in case the position has changed for some reason (overlay, etc.)
				if ($this->emitDebugPos($node['token'], 'echo'))
					$first = true;

				$this->emitCodeInternal(($first ? 'echo ' : ', ') . $node['data']);
			}

			$first = false;
		}

		if ($in_string)
			$this->emitCodeInternal('\'');
		$this->emitCodeInternal(';');

		$this->emit_output = array();
	}

	protected function emitCodeInternal($code)
	{
		// Don't output any \r's, we use 't' mode, so those are automatic.
		$this->fwrite(str_replace("\r", '', $code));
		$this->last_line += substr_count($code, "\n");
	}

	protected function emitDebugPos(ToxgToken $token, $type = 'code')
	{
		// Okay, maybe we don't need to bulk up the template.  Let's see how we can get out of updating the pos.

		// If the file is the same, we have a chance.
		if ($token->file === $this->last_file)
		{
			// If the line is the same as it should be, we're good.
			if ($token->line == $this->last_line)
				return false;
			// If we just need a higher line number, then just print some newlines (cheaper for PHP to cache.)
			elseif ($token->line > $this->last_line)
			{
				$this->emitCodeInternal(str_repeat("\n", $token->line - $this->last_line));
				return false;
			}

			// Okay, this means the line number was lower (template?) so let's go.
		}

		// This triggers the error system to remap the caller's file/line with the specified.
		if ($type === 'echo')
			$this->fwrite(';');
		$this->fwrite("\n" . 'ToxgErrors::remap(\'' . addcslashes(realpath($token->file), '\\\'') . '\', ' . (int) $token->line . ');');

		$this->last_file = $token->file;
		$this->last_line = $token->line;
		return true;
	}

	protected function fwrite($string)
	{
		if ($string === '')
			return;

		if (@fwrite($this->data, $string) === false)
			throw new ToxgException('Unable to write to template cache file.', '', 0);
	}
}

?>
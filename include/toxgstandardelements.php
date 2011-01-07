<?php

class ToxgStandardElements
{
	protected $template_push_level = 0;

	protected function __construct()
	{
	}

	public static function useIn($template)
	{
		// In case any state is needed.
		$inst = new ToxgStandardElements();

		$tags = array(
			'output',
			'raw',
			// !!! 'format',
			'foreach',
			'if',
			'else',
			'flush',
			'set',
			'json',
			'default',
			'template-push',
			'template-pop',
		);

		foreach ($tags as $tag)
			$template->listenEmitBasic($tag, array($inst, 'tpl_' . str_replace('-', '_', $tag)));
	}

	public function tpl_output(ToxgBuilder $builder, $type, array $attributes, ToxgToken $token)
	{
		$this->requireEmpty($token);
		$this->requireAttributes(array('value', 'as'), $token);

		$expr = ToxgExpression::normal($attributes['value'], $token);

		if ($attributes['as'] === 'html')
			$builder->emitOutputParam('htmlspecialchars(' . $expr . ')', $token);
		elseif ($attributes['as'] === 'raw')
			$builder->emitOutputParam('(' . $expr . ')', $token);
		else
			$token->toss('Invalid value for as attribute: expecting html or raw.');
	}

	public function tpl_raw(ToxgBuilder $builder, $type, array $attributes, ToxgToken $token)
	{
		$this->requireEmpty($token);
		$this->requireAttributes(array('value'), $token);

		$expr = ToxgExpression::normal($attributes['value'], $token);
		$builder->emitOutputParam('(' . $expr . ')', $token);
	}

	public function tpl_foreach(ToxgBuilder $builder, $type, array $attributes, ToxgToken $token)
	{
		$this->requireNotEmpty($token);
		$this->requireAttributes(array('from', 'as'), $token);

		if ($type === 'tag-start')
		{
			$from = ToxgExpression::normal($attributes['from'], $token);
			$as = ToxgExpression::variableNotLang($attributes['as'], $token);

			// If there's no parens or $'s in it, it can't be foreachable.
			if (strpos($from, '$') === false && strpos($from, '(') === false)
				$token->toss('Cannot foreach over a string, you probably want a variable.');

			// !!! Do we want a way to have a key?  I think no.
			$builder->emitCode('foreach (' . $from . ' as ' . $as . ') {', $token);
		}
		else
		{
			$builder->emitCode('}', $token);
		}
	}

	public function tpl_if(ToxgBuilder $builder, $type, array $attributes, ToxgToken $token)
	{
		$this->requireNotEmpty($token);
		$this->requireAttributes(array('test'), $token);

		if ($type === 'tag-start')
		{
			$expr = ToxgExpression::boolean($attributes['test'], $token);
			$builder->emitCode('if (' . $expr . ') {', $token);
		}
		else
		{
			$builder->emitCode('}', $token);
		}
	}

	public function tpl_else(ToxgBuilder $builder, $type, array $attributes, ToxgToken $token)
	{
		$this->requireEmpty($token);

		if (isset($attributes['test']))
		{
			$expr = ToxgExpression::boolean($attributes['test'], $token);
			$builder->emitCode('} elseif (' . $expr . ') {', $token);
		}
		else
			$builder->emitCode('} else {', $token);
	}

	public function tpl_flush(ToxgBuilder $builder, $type, array $attributes, ToxgToken $token)
	{
		$this->requireEmpty($token);

		$builder->emitCode('ob_flush(); flush();', $token);
	}

	public function tpl_set(ToxgBuilder $builder, $type, array $attributes, ToxgToken $token)
	{
		$this->requireEmpty($token);
		$this->requireAttributes(array('var', 'value'), $token);

		$var = ToxgExpression::variableNotLang($attributes['var'], $token);
		$value = ToxgExpression::normal($attributes['value'], $token);

		$builder->emitCode($var . ' = ' . $value . ';', $token);
	}

	public function tpl_json(ToxgBuilder $builder, $type, array $attributes, ToxgToken $token)
	{
		$this->requireEmpty($token);
		$this->requireAttributes(array('value', 'as'), $token);

		$expr = ToxgExpression::normal($attributes['value'], $token);

		if ($attributes['as'] === 'html')
			$builder->emitOutputParam('htmlspecialchars(json_encode(' . $expr . '))', $token);
		elseif ($attributes['as'] === 'raw')
			$builder->emitOutputParam('json_encode(' . $expr . ')', $token);
		else
			$token->toss('Invalid value for as attribute: expecting html or raw.');
	}

	public function tpl_default(ToxgBuilder $builder, $type, array $attributes, ToxgToken $token)
	{
		$this->requireEmpty($token);
		$this->requireAttributes(array('var'), $token);

		$value = ToxgExpression::variable($attributes['var'], $token);
		if (isset($attributes['default']))
			$default = ToxgExpression::stringWithVars($attributes['default'], $token);
		else
			$default = '\'\'';

		// !!! Better way to detect lang use?
		if ($value[0] !== '$')
			$builder->emitCode('if (' . $value . ') echo htmlspecialchars(' . $value . ');', $token);
		else
			$builder->emitCode('if (!empty(' . $value . ')) echo htmlspecialchars(' . $value . ');', $token);

		// Don't bother with an else if it's not needed.
		if ($default != '\'\'')
			$builder->emitCode('else echo htmlspecialchars(' . $default . ');', $token);
	}

	public function tpl_template_push(ToxgBuilder $builder, $type, array $attributes, ToxgToken $token)
	{
		$this->requireEmpty($token);

		$args = array();
		$save = array();
		foreach ($attributes as $k => $v)
		{
			$k = '\'' . addcslashes(ToxgExpression::makeVarName($k), '\\\'') . '\'';
			$save[] = $k;
			$args[] = $k . ' => ' . ToxgExpression::stringWithVars($v, $token);
		}

		// First, save the existing variables (if any.)
		$builder->emitCode('global $__toxg_stack; if (!isset($__toxg_stack)) $__toxg_stack = array();', $token);
		$builder->emitCode('array_push($__toxg_stack, compact(' . implode(', ', $save) . '));', $token);

		// Next, overwrite them with the args.
		$builder->emitCode('extract(array(' . implode(', ', $args) . '), EXTR_OVERWRITE);', $token);

		// Just to match things up.
		$this->template_push_level++;
	}

	public function tpl_template_pop(ToxgBuilder $builder, $type, array $attributes, ToxgToken $token)
	{
		$this->requireEmpty($token);
		if ($this->template_push_level <= 0)
			$token->toss('Please always use a template-push before a template-pop.');

		// Just restore the previously saved variables, actually.
		$builder->emitCode('global $__toxg_stack; extract(array_pop($__toxg_stack), EXTR_OVERWRITE);', $token);

		// Just to match things up.
		$this->template_push_level--;
	}

	protected function requireEmpty(ToxgToken $token)
	{
		if ($token->type !== 'tag-empty')
			$token->toss('All ' . $token->prettyName() . ' must be empty.)');
	}

	protected function requireNotEmpty(ToxgToken $token)
	{
		if ($token->type === 'tag-empty')
			$token->toss('All ' . $token->prettyName() . ' cannot be empty.)');
	}

	protected function requireAttributes(array $reqs, ToxgToken $token)
	{
		if ($token->type === 'tag-end')
			return;

		foreach ($reqs as $req)
		{
			if (!isset($token->attributes[$req]))
				$token->toss('Missing attribute ' . $req . ' for ' . $token->prettyName() . ' (required: ' . implode(', ', $reqs) . '.)');
		}
	}
}

?>
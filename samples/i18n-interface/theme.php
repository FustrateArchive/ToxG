<?php

class MyTheme extends SampleToxgTheme
{
	protected $nsuri = 'http://www.example.com/#site';
	protected $lang_debugging = false;

	public function enableLangDebugging()
	{
		$this->lang_debugging = true;
	}

	public function output()
	{
		$this->setListeners();
		return parent::output();
	}

	protected function setListeners()
	{
		// We're going to replace the default implementation.
		if ($this->lang_debugging)
		{
			$this->templates->listenEmit(ToxgTemplate::TPL_NAMESPACE, 'output', array($this, 'tpl_output'));
			ToxgExpression::setLangFunction('MyTheme::debuggingLangString');
		}
		else
			ToxgExpression::setLangFunction('my_lang_formatter');
	}

	public function tpl_output(ToxgBuilder $builder, $type, array $attributes, ToxgToken $token)
	{
		$this->requireEmpty($token);
		$this->requireAttributes(array('value', 'as'), $token);

		$expr = $builder->parseExpression('normal', $attributes['value'], $token);
		$debug = isset($attributes['debug']) && $attributes['debug'] === 'false' ? 'false' : 'true';

		if ($attributes['as'] === 'html')
			$builder->emitOutputParam(__CLASS__ . '::escapeDebuggingHTML(' . $expr . ', ' . $debug . ')', $token);
		elseif ($attributes['as'] === 'raw')
			$builder->emitOutputParam('(' . $expr . ')', $token);
		else
			$token->toss('tpl_output_invalid_as');

		// False means: don't process any other events for this.
		return false;
	}

	protected function requireEmpty(ToxgToken $token)
	{
		if ($token->type !== 'tag-empty')
			$token->toss('generic_tpl_must_be_empty', $token->prettyName());
	}

	protected function requireNotEmpty(ToxgToken $token)
	{
		if ($token->type === 'tag-empty')
			$token->toss('generic_tpl_must_be_not_empty', $token->prettyName());
	}

	protected function requireAttributes(array $reqs, ToxgToken $token)
	{
		if ($token->type === 'tag-end')
			return;

		foreach ($reqs as $req)
		{
			if (!isset($token->attributes[$req]))
				$token->toss('generic_tpl_missing_required', $req, $token->prettyName(), implode(', ', $reqs));
		}
	}

	static function escapeDebuggingHTML($string, $debug)
	{
		// We still need to escape for XSS reasons, but we want to markup the language strings.
		$string = htmlspecialchars($string);

		$replacements = array(
			'~&lt;&lt;&lt;lang:([^&:]+):(\d+)&gt;&gt;&gt;~' => '<span class="lang-debug" data-lang="$1" data-lang-params="$2">',
			'~&lt;&lt;&lt;/lang&gt;&gt;&gt;~' => '</span>',
			'~&lt;&lt;&lt;langparam&gt;&gt;&gt;~' => '<span class="lang-debug-param">',
			'~&lt;&lt;&lt;/langparam&gt;&gt;&gt;~' => '</span>',
		);

		if ($debug)
			return preg_replace(array_keys($replacements), array_values($replacements), $string);
		else
			return preg_replace(array_keys($replacements), array_pad(array(), count($replacements), ''), $string);
	}

	static function debuggingLangString()
	{
		$args = func_get_args();

		// This is kinda a cheap way to format it so we can find it later.
		for ($i = 1; $i < count($args); $i++)
			$args[$i] = '<<<langparam>>>' . $args[$i] . '<<</langparam>>>';

		$text = call_user_func_array('my_lang_formatter', $args);

		// This is kinda a cheap way to format it so we can find it later.
		return '<<<lang:' . $args[0] . ':' . count($args) . '>>>' . $text . '<<</lang>>>';
	}
}

?>
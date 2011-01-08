<?php

class ToxgPrebuilder
{
	protected $templates = array();
	protected $inside_template = false;

	public function getTemplateForBuild($token)
	{
		$name = self::makeTemplateName($token, 'name-attr');
		if (!isset($this->templates[$name]))
			$token->toss('New template found by builder, not found by prebuilder.');
		$template = $this->templates[$name];

		if ($template['file'] == $token->file && $template['line'] == $token->line)
			$template['should_emit'] = true;
		else
			$template['should_emit'] = false;
		$template['stage'] = 1;

		return $template;
	}

	public function getTemplateForCall($token)
	{
		$name = self::makeTemplateName($token);

		// It's okay if it's not defined yet.
		if (!isset($this->templates[$name]))
			return array(
				'name' => $name,
				'defined' => false,
				'requires' => array(),
			);
		else
		{
			$template = $this->templates[$name];
			$template['defined'] = true;
		}

		return $template;
	}

	public function setupParser(ToxgParser $parser)
	{
		$parser->listen('parsedContent', array($this, 'parsedContent'));
		$parser->listen('parsedElement', array($this, 'parsedElement'));
	}

	public function parsedContent(ToxgToken $token, ToxgParser $parser)
	{
		$this->requireTemplate($token);
	}

	public function parsedElement(ToxgToken $token, ToxgParser $parser)
	{
		if ($token->nsuri === ToxgTemplate::TPL_NAMESPACE)
		{
			if ($token->name === 'template')
				$this->handleTagTemplate($token);

			// !!! For some reason template-push needs a better error message?
			$okay_outside_template = array('container', 'template');
			if (!in_array($token->name, $okay_outside_template))
				$this->requireTemplate($token);
		}
		else
			$this->requireTemplate($token);
	}

	protected function handleTagTemplate(ToxgToken $token)
	{
		// We only care about start tags.
		if ($token->type === 'tag-start')
		{
			if ($this->inside_template)
				$token->toss('Templates cannot contain other templates.  Forgot a close tpl:template?');

			$name = self::makeTemplateName($token, 'name-attr');

			// Doesn't exist yet.
			if (empty($this->templates[$name]))
			{
				$this->templates[$name] = array(
					'name' => $name,
					'file' => $token->file,
					'line' => $token->line,
					'parameters' => array(),
					'requires' => array(),
				);

				// The (optional) requires attribute lists required attributes.
				if (!empty($token->attributes['requires']))
				{
					// It can be comma separated or space separated.
					$requires = array_filter(array_map('trim', preg_split('~[\s,]+~', $token->attributes['requires'])));

					foreach ($requires as $required)
						$this->templates[$name]['requires'][] = ToxgExpression::makeVarName($required);
				}

				// The (optional) parameters attribute allows the template to request variables from the parent template
				// without the need to explictly pass it
				if (!empty($token->attributes['parameters']))
				{
					$params = array_filter(array_map('trim', preg_split('~[\s,]+~', $token->attributes['parameters'])));

					foreach ($params as $param)
						$this->templates[$name]['parameters'][] = $param;
				}
			}

			$this->inside_template = true;
		}
		elseif ($token->type === 'tag-end')
			$this->inside_template = false;
	}

	protected function requireTemplate(ToxgToken $token)
	{
		if (!$this->inside_template)
		{
			// Okay, make it pretty for the user.
			if ($token->type === 'tag-start' || $token->type === 'tag-empty' || $token->type === 'tag-end')
				throw new ToxgException('Element ' . $token->prettyName() . ' found outside template.', $token->file, $token->line);
			else
				throw new ToxgException('Text or code found outside template.', $token->file, $token->line);
		}
	}

	public static function makeTemplateName($token, $type = 'token')
	{
		// Pull the nsuri and name from the name attribute?
		if ($type === 'name-attr')
		{
			list ($ns, $name) = explode(':', $token->attributes['name'], 2);
			$nsuri = $token->getNamespace($ns);
		}
		// Or from the token itself?
		elseif ($type === 'token')
		{
			$nsuri = $token->nsuri;
			$name = $token->name;
		}

		return ToxgExpression::makeTemplateName($nsuri, $name);
	}
}

?>
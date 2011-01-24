<?php

require(dirname(dirname(__FILE__)) . '/include.php');
require(dirname(__FILE__) . '/source.php');

// This should be the "system" namespace (templates might use another for their own stuff.)
$nsuri = 'http://www.example.com/#site';

$context = array(
	'site_name' => 'Testing',
);

$templates = new ToxgTemplateList();
$templates->setNamespaces(array('site' => $nsuri, 'tpl' => ToxgTemplate::TPL_NAMESPACE));

// 1. TOX-G uses a flexible interface for you to provide it template data (choose one.)

// 1.1. Just use a string (filename is used in error messages.)
$template = new ToxgSource(get_template_data(), 'db:some-name-for-debugging');

// 1.2. Use a stream (must be seekable.)
//$fp = fopen('php://memory', 'wt+');
//fwrite($fp, get_template_data());
//rewind($fp);
//
//$template = new ToxgSource($fp, 'db:some-name-for-debugging');

// 1.3. Use a filename through ToxgSourceFile (or a subclass.)
//file_put_contents(dirname(__FILE__) . '/.toxg.templates.tox', get_template_data());
//$template = new ToxgSourceFile(dirname(__FILE__) . '/.toxg.templates.tox');

// 1.4. Use a filename directly.
//file_put_contents(dirname(__FILE__) . '/.toxg.templates.tox', get_template_data());
//$template = dirname(__FILE__) . '/.toxg.templates.tox';

// 1.5. Implement your own subclass of ToxgSource
//$template = new MySource(12345);

// 2. You can also do a few things with the compiled cache file (choose one.)

// 2.1. Use php://memory.
$compiled = fopen('php://memory', 'wt+');

// 2.2. Use a temporary file.
//$compiled = tempnam('/tmp', 'tox');

// 2.3. Use a simple filename.
//$compiled = dirname(__FILE__) . '/.toxg.templates.php';

//$templates->addOverlays(
$templates->addTemplate($template, $compiled);

// For simplicity, we're compiling every time.
ToxgStandardElements::useIn($templates);
$templates->compileAll();

if (!is_resource($compiled))
	$templates->loadAll();
else
{
	// Can't include file pointers.  This is non-ideal, but if you really want...
	rewind($compiled);
	eval('?>' . stream_get_contents($compiled));
}

ToxgTemplate::callTemplate($nsuri, 'main', array('context' => $context), 'above');
ToxgTemplate::callTemplate($nsuri, 'home', array('context' => $context), 'both');
ToxgTemplate::callTemplate($nsuri, 'main', array('context' => $context), 'below');

function get_template_data()
{
	// Pretend this is loading from a database query or some such.
	return '<tpl:container>
	<tpl:template name="site:main"><!DOCTYPE html>
		<html>
			<head>
				<title>{$context.site_name}</title>
				<style type="text/css">
					body
					{
						font: 10pt sans-serif;
					}
				</style>
			</head>
			<body>
				<tpl:content />
			</body>
		</html>
	</tpl:template>

	<tpl:template name="site:home">
		<p>Hello, this is the home page.  Isn\'t it pretty?</p>
	</tpl:template>
</tpl:container>';
}

?>
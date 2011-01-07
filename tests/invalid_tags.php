<?php

function test_invalid_source_001($harness)
{
	$harness->expectFailure(1);
	$harness->addData('<');
}

function test_invalid_source_002($harness)
{
	$harness->expectFailure(5);
	$harness->addData('    ' . "\n\n\n\n" . '<');
}

function test_invalid_source_003($harness)
{
	$harness->expectFailure(5);
	$harness->addData('    ' . "\n\n\n\n" . '<t');
}

function test_invalid_source_004($harness)
{
	$harness->expectFailure(5);
	$harness->addData('    ' . "\n\n\n\n" . '<tpl:');
}

function test_invalid_source_005($harness)
{
	$harness->expectFailure(5);
	$harness->addData('    ' . "\n\n\n\n" . '<tpl:asdasd');
}

function test_invalid_source_006($harness)
{
	$harness->expectFailure();
	$harness->addData('<tpl:container>' . "\n" . "\n");
}

function test_invalid_source_007($harness)
{
	$harness->expectFailure(1);
	$harness->addData('</tpl:container>');
}

function test_invalid_source_008($harness)
{
	$harness->expectFailure(1);
	$harness->addData('</');
}

function test_invalid_source_009($harness)
{
	$harness->expectFailure(2);
	$harness->addData('<tpl:container>' . "\n" . '</tpl:container xmlns:asdf="http://www.example.com/">');
}

function test_invalid_source_010($harness)
{
	$harness->expectFailure(2);
	$harness->addData('<tpl:container>' . "\n" . '</tpl:container a>');
}

function test_invalid_source_011($harness)
{
	$harness->expectFailure(1);
	$harness->addData('<tpl:container a>' . "\n" . '</tpl:container>');
}

function test_invalid_source_012($harness)
{
	$harness->expectFailure(1);
	$harness->addData('<tpl:container a=>' . "\n" . '</tpl:container>');
}

function test_invalid_source_013($harness)
{
	$harness->expectFailure(2);
	$harness->addData('<tpl:container />' . "\n" . '</tpl:container>');
}

function test_invalid_source_014($harness)
{
	$harness->expectFailure(1);
	$harness->addData('<!---');
}

function test_invalid_source_015($harness)
{
	$harness->expectFailure();
	$harness->addData('<tpl:template name="my:asdf"><!--- </tpl:template>');
}

function test_invalid_source_016($harness)
{
	// Mismatching start and end.
	$harness->expectFailure(2);
	$harness->addData('<tpl:container>' . "\n" . '</tpl:template>');
}

function test_invalid_source_017($harness)
{
	// Shouldn't be considered a tag.
	$harness->addData('<tpl:template name="my:asdf"> { </tpl:template>');
}

function test_invalid_source_018($harness)
{
	$harness->addData('<tpl:template name="my:asdf"> {} </tpl:template>');
}

function test_invalid_source_019($harness)
{
	$harness->addData('<tpl:template name="my:asdf"> {tpl:} </tpl:template>');
}

function test_invalid_source_020($harness)
{
	$harness->expectFailure(1);
	$harness->addData('<tpl:template name="my:asdf"> {tpl:v} </tpl:template>');
}

function test_invalid_source_021($harness)
{
	$harness->expectFailure(1);
	$harness->addData('<tpl:template name="my:asdf"> {$} </tpl:template>');
}

function test_invalid_source_022($harness)
{
	$harness->expectFailure(1);
	$harness->addData('<tpl:template name="my:asdf"> {#} </tpl:template>');
}

function test_invalid_source_023($harness)
{
	$harness->addData('{tpl:template name="my:asdf"}{/tpl:template}');
}

function test_invalid_source_024($harness)
{
	$harness->addData('{tpl:template name=\'my:asdf\'}{/tpl:template}');
}

function test_invalid_source_025($harness)
{
	$harness->expectFailure(3);
	$harness->addData('
		{tpl:template name="my:asdf"}
			{tpl:template name="my:asdf2"}
				(line 4)
			{/tpl:template}
		{/tpl:template}');
}

function test_invalid_source_026($harness)
{
	$harness->expectFailure(1);
	$harness->addData('{tpl:template name="my:asdf">{/tpl:template}');
}

function test_invalid_source_027($harness)
{
	$harness->expectFailure(1);
	$harness->addData('<tpl:template name="my:asdf"}{/tpl:template}');
}

function test_invalid_source_028($harness)
{
	$harness->expectFailure(1);
	// !!! Maybe this should work?
	$harness->addData('{tpl:template name="my:asdf"}<tpl:output value="{tpl:output value="$x" /}" />{/tpl:template}');
}

function test_invalid_source_029($harness)
{
	$harness->addWrappedData('var x = {tpl: xyz};');
}

function test_invalid_overlay_001($harness)
{
	$harness->expectFailure(1);
	$harness->addData('<tpl:template name="my:asdf"></tpl:template>');
	$harness->addOverlay('<');
}

function test_invalid_overlay_002($harness)
{
	$harness->expectFailure(1);
	$harness->addData('<tpl:template name="my:asdf"></tpl:template>');
	$harness->addOverlay('<tpl:>');
}

function test_invalid_overlay_003($harness)
{
	$harness->expectFailure(1);
	$harness->addData('<tpl:template name="my:asdf"></tpl:template>');
	$harness->addOverlay('<tpl:alter match="my:asdf" position="before" />test</tpl:alter>');
}

function test_invalid_overlay_004($harness)
{
	$harness->expectFailure();
	$harness->addData('<tpl:template name="my:asdf"></tpl:template>');
	$harness->addOverlay('<tpl:alter match="my:asdf" position="before">test</tpl:container>');
}

function test_invalid_overlay_005($harness)
{
	// Just to make sure they're not broken.
	$harness->addData('<tpl:template name="my:asdf"></tpl:template>');
	$harness->addOverlay('<tpl:alter match="my:asdf" position="before">test</tpl:alter>');
}

// !!! Need tests for using tpl:content in loops, if, etc.

?>
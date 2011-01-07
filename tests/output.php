<?php

function test_output_001($harness)
{
	$harness->expectOutput('pass');
	$harness->addData('<tpl:template name="my:output">pass</tpl:template>');
}

function test_output_002($harness)
{
	$harness->expectOutput('pass');
	$harness->addData('<tpl:template name="my:output1">pass</tpl:template><tpl:template name="my:output2">fail</tpl:template><tpl:template name="my:output"><my:output1 /></tpl:template>');
}

function test_output_003($harness)
{
	$harness->expectOutput('pass');
	$harness->addData('<tpl:template name="my:output"><tpl:if test="true">pass<tpl:else />fail</tpl:if></tpl:template>');
}

function test_output_004($harness)
{
	$harness->expectOutput('test: <li id="123">test</li>');
	$harness->addData('
<tpl:template name="my:test1"><li id="{$id}"><tpl:content /></li></tpl:template>
<tpl:template name="my:test2">{$name}: <my:test1 id="123"><tpl:content /></my:test1></tpl:template>
<tpl:template name="my:output"><my:test2 name="test">test</my:test2></tpl:template>');
}

function test_output_005($harness)
{
	$harness->expectOutput('pass');
	$harness->addData('<tpl:template name="my:output"><tpl:set var="{$x}" value="3" /><tpl:if test="{$x} == 1">fail1<tpl:else test="{$x} == 2" />fail2<tpl:else test="{$x} == 3" />pass<tpl:else test="{$x} == 4" />fail2</tpl:if></tpl:template>');
}

function test_output_006($harness)
{
	$harness->expectOutput('12345');
	$harness->addData('<tpl:template name="my:output"><tpl:set var="{$x}" value="array(1, 2, 3, 4, 5)" /><tpl:foreach from="{$x}" as="{$y}">{$y}</tpl:foreach></tpl:template>');
}

function test_output_007($harness)
{
	$harness->expectOutput('<>& <>&');
	$harness->addData('<tpl:template name="my:output"><tpl:set var="{$x}" value="\'<>&\'" /><tpl:output value="\'<>&\'" as="raw" /> <tpl:output value="{$x}" as="raw" /></tpl:template>');
}

function test_output_008($harness)
{
	$harness->expectOutput('<![CDATA[ <>& <>& ]]>');
	$harness->addData('<tpl:template name="my:output"><tpl:set var="{$x}" value="\'<>&\'" /><![CDATA[ <tpl:output value="\'<>&\'" /> {$x} ]]></tpl:template>');
}

function test_output_009($harness)
{
	$harness->expectOutput('pass');
	$harness->addData('<tpl:template name="my:output"><tpl:set var="{$x}" value="\'pass\'" /><!--- {$x} ---> {$x}</tpl:template>');
}

function test_output_010($harness)
{
	$harness->expectOutput('pass');
	$harness->addData('<tpl:template name="my:output"><my:underscore x_y_z="pass" /></tpl:template><tpl:template name="my:underscore">{$x_y_z}</tpl:template>');
}

function test_output_011($harness)
{
	$harness->expectOutput('pass');
	$harness->addData('
		<tpl:template name="my:output"><my:example /></tpl:template>
		<tpl:template name="my:example">
			<tpl:set var="{$y}" value="\'pass\'" />
			<tpl:content />
			{$y}
		</tpl:template>');
}

function test_output_012($harness)
{
	$harness->expectOutput('pass');
	$harness->addData('
		<tpl:template name="my:output"><my:example></my:example></tpl:template>
		<tpl:template name="my:example">
			<tpl:set var="{$y}" value="\'pass\'" />
			<tpl:content />
			{$y}
		</tpl:template>');
}

function test_output_013($harness)
{
	$harness->expectOutput('pass');
	$harness->addData('
		<tpl:template name="my:output"><my:example /><my:example2 /></tpl:template>
		<tpl:template name="my:example">
			pass
		</tpl:template>');
}

function test_output_014($harness)
{
	$harness->expectOutputFailure(4);
	$harness->addData('
		<tpl:template name="my:output"><my:example /><my:example2 /></tpl:template>
		<tpl:template name="my:example">
			{$undef}
		</tpl:template>');
}

function test_output_015($harness)
{
	$harness->expectOutputFailure(4);
	$harness->addData('
		<tpl:template name="my:output"><my:example /><my:example2 /></tpl:template>
		<tpl:template name="my:example">
			<tpl:if test="{$undef}"></tpl:if>
		</tpl:template>');
}

function test_output_016($harness)
{
	$harness->expectOutput('pass');
	$harness->addData('<tpl:template name="my:output"><my:example>pass</my:example></tpl:template>');
}

function test_output_017($harness)
{
	$harness->expectOutput('pass');
	$harness->addWrappedData('');
	$harness->addWrappedOverlay('pass');
}

function test_output_018($harness)
{
	$harness->expectOutput('pass pass pass');
	$harness->addData('<tpl:template name="my:output"><my:example /> <my:example /> <my:example /></tpl:template>');
	$harness->addOverlay('<tpl:alter match="my:example" position="after">pass</tpl:alter>');
}

function test_output_019($harness)
{
	$harness->expectOutput('Aalter(B)C');
	$harness->addData('
		<tpl:template name="my:output">A<my:example>B</my:example>C</tpl:template>
		<tpl:template name="my:example">(<tpl:content />)</tpl:template>');
	$harness->addOverlay('<tpl:alter match="my:example" position="before">alter</tpl:alter>');
}

function test_output_020($harness)
{
	$harness->expectOutput('A(alterB)C');
	$harness->addData('
		<tpl:template name="my:output">A<my:example>B</my:example>C</tpl:template>
		<tpl:template name="my:example">(<tpl:content />)</tpl:template>');
	$harness->addOverlay('<tpl:alter match="my:example" position="beforecontent">alter</tpl:alter>');
}

function test_output_021($harness)
{
	$harness->expectOutput('A(Balter)C');
	$harness->addData('
		<tpl:template name="my:output">A<my:example>B</my:example>C</tpl:template>
		<tpl:template name="my:example">(<tpl:content />)</tpl:template>');
	$harness->addOverlay('<tpl:alter match="my:example" position="aftercontent">alter</tpl:alter>');
}

function test_output_022($harness)
{
	$harness->expectOutput('A(B)alterC');
	$harness->addData('
		<tpl:template name="my:output">A<my:example>B</my:example>C</tpl:template>
		<tpl:template name="my:example">(<tpl:content />)</tpl:template>');
	$harness->addOverlay('<tpl:alter match="my:example" position="after">alter</tpl:alter>');
}

function test_output_023($harness)
{
	$harness->expectOutput('pass');
	$harness->addData('<tpl:template name="my:output"><my:example x="pass">{$x}</my:example></tpl:template>');
}

function test_output_024($harness)
{
	$harness->setLayers(array('output--toxg-direct', 'layer--toxg-direct'));
	$harness->expectOutput('beforebeforeafterafter');
	$harness->addData('
		<tpl:template name="my:output">before<tpl:content />after</tpl:template>
		<tpl:template name="my:layer">before<tpl:content />after</tpl:template>');
}

function test_output_025($harness)
{
	$harness->setLayers(array('output--toxg-direct', 'layer'));
	$harness->expectOutput('beforebeforeafterafter');
	$harness->addData('
		<tpl:template name="my:output">before<tpl:content />after</tpl:template>
		<tpl:template name="my:layer">before<tpl:content />after</tpl:template>');
}

function test_output_026($harness)
{
	$harness->expectOutput(ToxgTestHarness::$test);
	$harness->addWrappedData('{ToxgTestHarness::$test}');
}

function test_output_027($harness)
{
	$harness->expectOutput(ToxgTestHarness::TEST);
	$harness->addWrappedData('{ToxgTestHarness::TEST}');
}

function test_output_028($harness)
{
	$harness->setCommonVars(array('common'));
	$harness->setOutputParams(array('common' => 'pass'));
	$harness->expectOutput('pass pass');
	$harness->addWrappedData('{$common} <tpl:content />{$common}');
}

?>
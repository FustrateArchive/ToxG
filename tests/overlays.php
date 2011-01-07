<?php

function test_invalid_alter_001($harness)
{
	$harness->expectFailure(1);
	$harness->addDataForOverlay();
	$harness->addOverlay('<tpl:alter match="my:output my:example">test</tpl:alter>');
}

function test_invalid_alter_002($harness)
{
	$harness->expectFailure(1);
	$harness->addDataForOverlay();
	$harness->addOverlay('<tpl:alter match="" position="before">test</tpl:alter>');
}

function test_invalid_alter_003($harness)
{
	$harness->expectFailure(1);
	$harness->addDataForOverlay();
	$harness->addOverlay('<tpl:alter match="">test</tpl:alter>');
}

function test_invalid_alter_004($harness)
{
	$harness->expectFailure(1);
	$harness->addDataForOverlay();
	$harness->addOverlay('<tpl:alter>test</tpl:alter>');
}

function test_invalid_alter_005($harness)
{
	$harness->expectFailure(1);
	$harness->addDataForOverlay();
	$harness->addOverlay('<tpl:alter match="my:output my:example" position="blah">test</tpl:alter>');
}

function test_invalid_alter_006($harness)
{
	$harness->expectFailure(1);
	$harness->addDataForOverlay();
	$harness->addOverlay('<tpl:alter match="my:output my:example" position="before">test</tpl:alter> test');
}

function test_invalid_alter_007($harness)
{
	$harness->expectFailure(1);
	$harness->addDataForOverlay();
	$harness->addOverlay('<tpl:alter match="my:output my:example" position="before"><tpl:alter match="my:output my:example" position="before"></tpl:alter></tpl:alter> test');
}

function test_invalid_alter_008($harness)
{
	$harness->expectFailure(1);
	$harness->addDataForOverlay();
	$harness->addOverlay('<tpl:alter match="my:output my:example" position="before"/>');
}

function test_alter_001($harness)
{
	$harness->addDataForOverlay();
	$harness->addOverlay('<tpl:alter match="my:output" position="before">test</tpl:alter>');
}

function test_alter_002($harness)
{
	$harness->addDataForOverlay();
	$harness->addOverlay('<tpl:alter match="my:output my:example" position="before">test</tpl:alter>');
}

function test_alter_003($harness)
{
	$harness->addDataForOverlay();
	$harness->addOverlay('<tpl:alter match="my:output my:example" position="beforecontent">test</tpl:alter>');
}

function test_alter_004($harness)
{
	$harness->addDataForOverlay();
	$harness->addOverlay('<tpl:alter match="my:output my:example" position="after">test</tpl:alter>');
}

function test_alter_005($harness)
{
	$harness->addDataForOverlay();
	$harness->addOverlay('<tpl:alter match="my:output my:example" position="aftercontent">test</tpl:alter>');
}

function test_alter_006($harness)
{
	$harness->addDataForOverlay();
	$harness->addOverlay('<tpl:alter match="my:output my:example" position="aftercontent">test1</tpl:alter>');
	$harness->addOverlay('<tpl:alter match="my:output my:example" position="aftercontent">test2</tpl:alter>');
	$harness->addOverlay('<tpl:alter match="my:output my:example" position="aftercontent">test3</tpl:alter>');
	$harness->addOverlay('<tpl:alter match="my:output my:example" position="aftercontent">test4</tpl:alter>');
}

function test_alter_007($harness)
{
	$harness->addDataForOverlay();
	$harness->addOverlay('<tpl:alter match="my:output my:example" position="aftercontent">test1</tpl:alter>');
	$harness->addOverlay('<tpl:alter match="my:output my:example" position="aftercontent">test2</tpl:alter>');
	$harness->addOverlay('<tpl:alter match="my:output my:example" position="aftercontent">test3</tpl:alter>');
	$harness->addOverlay('<tpl:alter match="my:output my:example" position="aftercontent">test4</tpl:alter>');
}

?>
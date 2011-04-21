<?php

// Needed for execution (and compiling.)
// For debugging.
require(dirname(__FILE__) . '/toxgerrors.php');
// For makeTemplateName().
require(dirname(__FILE__) . '/toxgexpression.php');
// For VERSION, callTemplate().
require(dirname(__FILE__) . '/toxgtemplate.php');
// For simple handling of many templates.
require(dirname(__FILE__) . '/toxgtemplatelist.php');

// Needed for only compiling.
require(dirname(__FILE__) . '/toxgexception.php');
require(dirname(__FILE__) . '/toxgexceptionfile.php');
require(dirname(__FILE__) . '/toxgsource.php');
require(dirname(__FILE__) . '/toxgsourcefile.php');
require(dirname(__FILE__) . '/toxgprebuilder.php');
require(dirname(__FILE__) . '/toxgbuilder.php');
require(dirname(__FILE__) . '/toxgoverlay.php');
require(dirname(__FILE__) . '/toxgparser.php');
require(dirname(__FILE__) . '/toxgtoken.php');
require(dirname(__FILE__) . '/toxgstandardelements.php');
require(dirname(__FILE__) . '/toxgtheme.php');

?>
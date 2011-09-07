<?php

define ('STARTED', True);

require_once 'kernel3.php';

print 'Data Parsing Demo<br />';
print '<pre>'.FStr::PHPDefine(get_loaded_extensions()).'</pre><br />';
$newUrl = FStr::urlAddParam(FStr::fullUrl($_SERVER['REQUEST_URI']), 'SID', FStr::shortUID(), false, true);
print '<a href="'.FStr::smartAmpersands($newUrl).'">'.$newUrl.'</a><br />';
print F('LNG')->timeFormat(rand(0, 0x7FFFFFFF), false, 6).'<br />';

// linearizing complex tree and parsing it
$a = Array('foo', Array('bar1' => 'data', 'foo1'));
print FStr::PHPDefine($a).'<br />';
$b = FMisc::linearize($a);
print FStr::PHPDefine($b).'<br />';
foreach($b as &$v)
    $v = '0';
print FStr::PHPDefine($a).'<br />';

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>'.F('HTTP')->getOB().'
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body></html>';
F('HTTP')->write($page);
F('HTTP')->sendBuffer();
?>

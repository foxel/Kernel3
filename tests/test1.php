<?php

require_once 'init.php';
require_once 'kernel3.php';

F()->Response->write('Data Parsing Demo<br />');
F()->Response->write('<pre>'.K3_Util_Value::definePHP(get_loaded_extensions()).'</pre><br />');
$newUrl = K3_Util_Url::urlAddParam(K3_Util_Url::fullUrl($_SERVER['REQUEST_URI'], F()->appEnv), 'SID', K3_Util_String::shortUID(), false, true);
F()->Response->write('<a href="'.htmlspecialchars($newUrl).'">'.htmlspecialchars($newUrl).'</a><br />');
F()->Response->write(F()->LNG->timeFormat(rand(0, 0x7FFFFFFF), false, 6).'<br />');

// linearizing complex tree and parsing it
$a = Array('foo', Array('bar1' => 'data', 'foo1'));
F()->Response->write(K3_Util_Value::definePHP($a).'<br />');
$b = FMisc::linearize($a);
F()->Response->write(K3_Util_Value::definePHP($b).'<br />');
foreach($b as &$v)
    $v = '0';
F()->Response->write(K3_Util_Value::definePHP($a).'<br />');

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>'.F()->Response->getBuffer().'
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body></html>';
F()->Response->clearBuffer()->write($page);
F()->Response->sendBuffer();

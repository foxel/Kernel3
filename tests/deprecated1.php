<?php

require_once 'init.php';
require_once 'kernel3.php';


ob_start();
$time1 = F()->LNG->timeFormat(time(), 'Y-m-d H:i', false);
$time2 = F()->LNG->timeFormat(time(), 'Y-m-d H:i', 'America/New_York');
//$time2 = F()->LNG->timeFormat(time(), 'Y-m-d H:i', 0); //deprecated variant

F()->Session->set('foo', 'bar');
$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>
<pre>'.print_r(F()->Timer->getLog(), true).'</pre>
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body>';
F()->Response->write($page)
    ->sendBuffer();

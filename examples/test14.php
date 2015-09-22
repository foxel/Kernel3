<?php

require_once 'init.php';
require_once 'kernel3.php';

$data = array(
    123,
    true,
    'Simple',
    'String data',
    'string "with" quotes',
    'complex \"string"',
);

ob_start();
$csv = new K3_Csv('php://output');
$csv->open('w');
$csv->write($data);
$csv->close();
$string = ob_get_clean();

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>
<pre>'.$string.'</pre>
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body>';
F()->Response->write($page)
    ->sendBuffer();

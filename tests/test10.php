<?php

define ('STARTED', True);

require_once 'kernel3.php';

F('DBase')->connect(Array('dbname' => 'dev.sandbox'), 'k3tester', false);
$s = F()->DBase->select('objects', 'u')
    ->where('class_id', 'user')
    ;
$p = F()->FlexyStore('object_values', null, 'object_texts')
    ->loadClassesFromDB('object_class_fields', 'user')
    ->addClassProperty('user', 'frieadwnds', 'text')
    //->pushClassesToDB('object_class_fields', 'user')
    ;
$p->joinToSelect($s, 'user');
$s->where('city', 'Tomsk');

$string = $s->toString();
if (FGPC::getBin('execute'))
    $string.= '<pre>'.FStr::phpDefine($s->fetchAll()).'</pre>';

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>
'.$string.'
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body>';
F('HTTP')->write($page);
F('HTTP')->sendBuffer();
?>

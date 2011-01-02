<?php

define ('STARTED', True);

require_once 'kernel3.php';


$file = F()->MetaFile(512, " ");
$file->add(new FFileStream(__FILE__));
$file->add(new FFileStream('test0.php'));
$file->add(new FStringStream('This is a test string ^.^'));
$file->open();
$str = '';

$file->read($str, $file->size());
$page = '
<body>('.$file->size().')'.nl2br(htmlspecialchars($str)).'
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body>';
F('HTTP')->write($page);
F('HTTP')->sendBuffer();
?>
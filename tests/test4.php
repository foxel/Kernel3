<?php

define ('STARTED', True);

require_once 'kernel3.php';


if (F()->GPC->getBin('load'))
    $file = F()->MetaFile->load('test1.file');
else
{
    $file = F()->MetaFile->createTar();
    $file->add(__FILE__);
    $file->add('test0.php');
    $file->addData('This is a test string ^.^');
    F()->MetaFile->save($file, 'test1.file');
}

$file->open();
$str = '';

$file->toFile('test4.tar');
$page = '
<body>('.$file->size().') <a href="test4.tar">test4.tar</a>
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body>';
F('HTTP')->write($page);
F('HTTP')->sendBuffer();
?>
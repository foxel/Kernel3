<?php

define ('STARTED', True);

require_once 'kernel3.php';


if (F()->GPC->getBin('download'))
{
    $file = F()->MetaFile->load('test1.file');
    F('HTTP')->sendDataStream($file, 'test4.tar');
}
else
{
    $file = F()->MetaFile->createTar();
    $file->add('.');
    $file->add('test0.php');
    $file->addData('This is a test string ^.^');
    F()->MetaFile->save($file, 'test1.file');
}

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>('.F('LNG')->sizeFormat($file->size()).') <a href="'.F_SITE_INDEX.'?download">download test4.tar</a>
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body></html>';
F('HTTP')->write($page);
F('HTTP')->sendBuffer();
?>

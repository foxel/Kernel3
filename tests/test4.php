<?php

define ('STARTED', True);

require_once 'kernel3.php';

function filterFunc($fname)
{    return preg_match('#\.(php|png|vis)$#', $fname);
}

if (F()->GPC->getBin('download'))
{
    $file = F()->MetaFile->load('test1.file');
    F('HTTP')->sendDataStream($file, 'test4.tar');
}
else
{
    $file = F()->MetaFile->createTar();
    $file->add('.', false, false, false, 'filterFunc');
    $file->add('test0.php');
    $file->addData('This is a test string ^.^');
    F()->MetaFile->save($file, 'test1.file');
}

$page = '
<body>('.F('LNG')->sizeFormat($file->size()).') <a href="'.F_SITE_INDEX.'?download">download test4.tar</a>
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body>';
F('HTTP')->write($page);
F('HTTP')->sendBuffer();
?>
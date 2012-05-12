<?php

require_once 'init.php';
require_once 'kernel3.php';

if (F()->GPC->getBin('load')) {
    /** @var $file FMetaFile */
    $file = F()->MetaFile->load('test.file');
} else {
    /** @var $file FMetaFile */
    $file = F()->MetaFile(512, " ");
    $file->add(new FFileStream(__FILE__));
    $file->add(new FFileStream('test0.php'));
    $file->add(new FStringStream('This is a test string ^.^'));
    F()->MetaFile->save($file, 'test.file');
}

$file->open();
$str = '';

$file->read($str, $file->size());
$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>('.$file->size().')<pre>'.htmlspecialchars($str).'</pre>
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body></html>';
F()->Response->write($page);
F()->Response->sendBuffer();

<?php

require_once 'init.php';
require_once 'phar://../kernel3.phar.gz';

F()->Response->write('Hello World');

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>'.F()->Response->getBuffer().'
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body></html>';
F()->Response->clearBuffer()->write($page);
F()->Response->sendBuffer();

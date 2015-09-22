<?php

require_once 'init.php';
require_once 'kernel3.php';

if (F()->Request->getBinary('image'))
    F()->HTTP->sendFile('lightning.png', 'молния.png', 'image/png', false, FHTTPInterface::FILE_RFC2231);

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body><img src="'.F_SITE_INDEX.'?image" alt="image" />
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body></html>';
F()->HTTP->write($page);
F()->HTTP->sendBuffer();

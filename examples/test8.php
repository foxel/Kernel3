<?php

require_once 'init.php';
require_once 'kernel3.php';

/*$m = F()->Mail->create('testmail');
$m->addTo('foxel@mitracompany.ru');
$m->setBody('test');
$m->attachFile('lightning.png');
$m->send();*/


$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body></html>';
F()->Response->write($page);
F()->Response->sendBuffer();

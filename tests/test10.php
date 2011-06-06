<?php

define ('STARTED', True);

require_once 'kernel3.php';

F('DBase')->connect(Array('dbname' => 'dev.quickfox'), 'k3tester', false);
$s = F()->DBase->select('qf2_users', 'u')
    ->calculateRows()
    ;
$p = F()->FlexyStore('qf2_userinfo')
    ->addClass('user', array(
        'url' => 'str',
        'visits' => 'int',
        'city' => 'str',
        'brthday' => 'time',
        'sex' => 'int',
        ));
$p->joinToSelect($s, 'user');
$s->where('city', 'Tomsk');

$string = $s->toString();

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>
'.$string.'
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body>';
F('HTTP')->write($page);
F('HTTP')->sendBuffer();
?>

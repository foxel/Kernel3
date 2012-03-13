<?php

require_once 'init.php';
require_once 'kernel3.php';

F()->DBase->connect(Array('dbname' => 'dev.quickfox'), 'k3tester', false);
$s = F()->DBase->select('qf2_users', 'u')
    ->calculateRows()
    ->joinLeft('qf2_users_auth', array('uid' => 'uid'), 'ua', array('*', 'passs' => 'pass_dropcode'))
    ->column(F()->DBase->select('qf2_pt_posts', 'p', array('COUNT(p.post_id)'))->where('p.author_id = u.uid'), 'posts_count')
    ->column('u.level - u.mod_lvl', 'lvl_diff')
    //->where('lvl_diff', 0, null)
    ->where('passs', '')
    ->order('posts_count', false, null)
    //->limit(3, 2)
    ;

$string = $s->toString();
if (F()->Request->getBinary('execute')) {
    $string.= '<pre>'.FStr::phpDefine($s->fetchAll()).'</pre>';
    $string.= ' ('.print_r(F()->DBase->lastSelectRowsCount, true).' rows total)';
}

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>
'.$string.'
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body></html>';
F()->Response->write($page);
F()->Response->sendBuffer();

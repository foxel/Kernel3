<?php

define ('STARTED', True);

require_once 'kernel3.php';

F('DBase')->connect(Array('dbname' => 'dev.quickfox'), 'k3tester', false);
$s = F()->DBase->select('qf2_users', 'u')
    ->calculateRows()
    ->joinLeft('qf2_users_auth', array('uid' => array('u', 'uid')), 'ua', array('*'))
    ->column(F()->DBase->select('qf2_pt_posts', 'p', array('COUNT(p.post_id)'))->where('p.author_id = u.uid'), 'posts_count')
    ->column('u.level - u.mod_lvl', 'lvl_diff')
    //->where('uid', array(1, 2, 3))
    ->order('nick')
    ->limit(3, 2);

$string = $s->toString();
$string.= '<pre>'.FStr::phpDefine($s->fetchAll()).'</pre>';
$string.= ' ('.print_r(F()->DBase->lastSelectRowsCount, true).' rows total)';

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>
'.$string.'
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body>';
F('HTTP')->write($page);
F('HTTP')->sendBuffer();
?>
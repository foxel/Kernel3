<?php

define ('STARTED', True);

require_once 'kernel3.php';

F()->Registry->setBackFile('reg.reg');

if ($name = F()->GPC->getString('name', FGPC::POST)) {
    $value = F()->GPC->getString('value', FGPC::POST);
    F()->Registry->set('store.'.$name, $value, true);
    F()->Registry->set('local.'.$name, $value, false);
    F()->Config->$name = $value;
}

$string = print_r(F()->Registry->getAll(), true);

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>
<form method="POST">
<input name="name" type="text" value="'.$name.'" />
<input name="value" type="text" value="'.$value.'" />
<input type="submit" value="Save" />
</form>
<pre>'.$string.'</pre>
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body>';
F('HTTP')->write($page)
    ->sendBuffer();
?>

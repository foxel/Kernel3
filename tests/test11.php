<?php

define ('STARTED', True);

require_once 'kernel3.php';

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>
<form action="'.F_SITE_INDEX.'" method="post">
 Enter e-mail: <input type="text" name="email" />
 '.(($email=F('GPC')->getString('email', FGPC::POST)) ? (FStr::isEmail($email, true) ? ' >> "'.$email.'" All right!' : ' >> E-mail not valid :(') : '').'
</form>
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body></html>';
F('HTTP')->write($page);
F('HTTP')->sendBuffer();
?>

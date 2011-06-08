<?php

define ('STARTED', True);

require_once 'kernel3.php';

print 'Hello World';

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>'.F('HTTP')->getOB().'
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body></html>';
F('HTTP')->write($page);
F('HTTP')->sendBuffer();
?>

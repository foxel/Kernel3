<?php

define ('STARTED', True);

require_once 'kernel3.php';


if (FGPC::getBin('image'))
    F('HTTP')->sendFile('lightning.png', false, 'image/png');

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body><img src="'.F_SITE_INDEX.'?image" alt="image" />
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body></html>';
F('HTTP')->write($page);
F('HTTP')->sendBuffer();
?>

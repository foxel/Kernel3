<?php

define ('STARTED', True);

require_once 'kernel3.php';

if (F('GPC')->getBin('image'))
    F('HTTP')->sendBinary(F()->Captcha(7, 0xefefff, 0x000033), 'image/jpeg');

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>
<form action="'.F_SITE_INDEX.'" method="post">
 <img src="'.F_SITE_INDEX.'?image" onclick="this.src=\''.F_SITE_INDEX.'?image&rand=\'+Math.random();" style="cursor: pointer;" title="Click to reload" />
 Enter the code: <input type="text" name="code" />
 '.(($code=F('GPC')->getString('code', FGPC::POST)) ? (F()->Captcha->check($code) ? ' >> "'.$code.'" All right!' : ' >> Wrong :(') : '').'
</form>
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body>';
F('HTTP')->write($page);
F('HTTP')->sendBuffer();
?>
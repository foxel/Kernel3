<?php

define ('STARTED', True);

require_once 'kernel3.php';

$answer = '';

if ($email=F('GPC')->getString('email', FGPC::POST))
{
    if (FStr::isEmail($email, true))
    {
        $emailObj = F()->Mail()
            ->setSubject('foo Моя тема')
            ->addTo($email)
            ->setBody('<html><body>'.highlight_file(__FILE__, true).'</body></html>', true)
            ->attachFile(__FILE__);
            
        $emailBody = $emailObj->toString();
            
        //file_put_contents('mail.eml', $emailBody);
        $answer = ' >> "'.$email.'" All right!';
        $answer.= ' <input type="submit" name="doSend" value="send E-mail" />';
        if (F('GPC')->getBin('doSend', FGPC::POST) && $emailObj->send())
            $answer.= ' >> Sent OK!';
        $answer.= '<pre>'.htmlspecialchars($emailBody).'</pre>';
    }
    else
        $answer = ' >> E-mail not valid :(';
}

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>
<form action="'.F_SITE_INDEX.'" method="post">
 Enter e-mail: <input type="text" name="email" value="'.$email.'"/> 
 '.$answer.'
</form>
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body></html>';
F('HTTP')->write($page)
    ->sendBuffer();
?>

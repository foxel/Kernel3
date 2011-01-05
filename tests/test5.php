<?php

define ('STARTED', True);

require_once 'kernel3.php';


F('HTTP')->sendFile('lightning.png', false, 'image/png');
?>
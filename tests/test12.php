<?php

define ('STARTED', True);

require_once 'kernel3.php';

$mpc = F()->MPC('localhost');
$string = '';
//$string.= print_r($mpc, true);
//$string.= print_r($mpc->getPlaylists(), true);
//$string.= print_r($mpc->getPlaylist('makar'), true);
$string.= print_r($mpc->playlist[$mpc->curTrack], true);
$string.= print_r($mpc->playlist[$mpc->nextTrack], true);
$string.= date('r', strtotime($mpc->playlist[$mpc->curTrack]['last-modified']));
//$string.= print_r($mpc->lsAll('BEST', 'file'), true);
//$mpc->shuffle(2, 24);

$page = '<html><head><!--Meta-Content-Type--><title>'.F_SITE_INDEX.'</title></head>
<body>
<pre>'.$string.'</pre>
<hr>'.highlight_file(__FILE__, true).'
<hr><!--Page-Stats--></body>';
F('HTTP')->write($page)
    ->sendBuffer();
?>

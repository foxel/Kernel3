<?php

require_once 'init.php';
require_once 'kernel3.php';

$content = 'Visualizer Demo<br />';

FCache::clear();
F()->VIS->loadTemplates('test.vis');
F()->VIS->addData(0, 'PAGE_TITLE', 'basic test');
F()->VIS->addData(0, 'PAGE_SUBTITLE', 'subtitle');

$content.= '<pre>'.K3_Util_Value::definePHP(get_loaded_extensions()).'</pre><br />';
$content.= F()->appEnv->server->rootUrl.'<br />';
$content.= K3_String::strToUpper('Всем огромный привет от меня').'<br />';

$content.= memory_get_usage().'<br />';
$content.= memory_get_peak_usage().'<br />';

$w = F()->VIS->addNode('TEST_WINDOW', 'PAGE_CONTENTS');
for ($i = 0; $i < 30; $i++)
    F()->VIS->addNode('TEST_WINDOW', 'PAGE_CONTENTS', 0, Array('CONTENTS' => F()->Timer->timeSpent()));

$w->addData('CONTENTS', $content);
F()->VIS->addNode('TEST_WINDOW', 'PAGE_CONTENTS')->addData('CONTENTS', '<pre>'.htmlspecialchars(F()->VIS->prepJSFunc('TEST_WINDOW')).'</pre>');
F()->VIS->addNode('TEST_WINDOW', 'PAGE_CONTENTS')
    ->addDataArray(Array('CONTENTS' => highlight_file(__FILE__, true), 'WIDTH' => '100%'));
F()->Response->write(F()->VIS->makeHTML())
    ->sendBuffer();

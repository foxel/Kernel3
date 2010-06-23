<?php

define ('STARTED', True);

require_once 'kernel3.php';

print 'Visualizer Demo<br />';

FCache::clear();
F('VIS')->loadTemplates('test.vis');
F('VIS')->addData(0, 'PAGE_TITLE', 'basic test');

print '<pre>'.FStr::PHPDefine(get_loaded_extensions()).'</pre><br />';
print F('HTTP')->rootUrl.'<br />';
print FStr::strToUpper('Всем огромный привет от меня').'<br />';

print memory_get_usage().'<br />';
print memory_get_peak_usage().'<br />';

$w = F('VIS')->addNode('TEST_WINDOW', 'PAGE_CONTENTS');
for ($i = 0; $i < 30; $i++)
    F('VIS')->addNode('TEST_WINDOW', 'PAGE_CONTENTS', 0, Array('CONTENTS' => F('Timer')->timeSpent()));
F('VIS')->addData($w, 'CONTENTS', F('HTTP')->getOB());
F('HTTP')->write(F('VIS')->makeHTML());
F('HTTP')->sendBuffer();
?>
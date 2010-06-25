<?php
/*
 * QuickFox kernel 3 'SlyFox' HTTP interface
 * Requires PHP >= 5.1.0
 */


if (!defined('F_STARTED'))
    die('Hacking attempt');

define('VIS_BR', "\n");

// VIS node class
class FVISNode extends FBaseClass // FEventDispatcher
{
    // node flags
    const VISNODE_ARRAY = 1; // node is an array of sametype nodes

    private $type  = '';
    private $vars  = Array();
    private $subs  = Array();
    private $flags = 0;
    private $parsed = '';
    private $isParsed = false;

    public function __construct($type, $flags = 0)
    {        $this->type = (string) $type;
        $this->flags = $flags;
        $this->pool = Array(
            'type'   => &$this->type,
            'flags'  => &$this->flags,
            'parsed' => &$this->isParsed,
            );
    }

    public function setType($type)
    {        $this->type = (string) $type;
    }

    public function parse($force_reparse = false, Array $add_vars = Array())
    {
        $visualizer = FVISInterface::getInstance();        $data = Array();
        $text = '';

        if ($this->isParsed && !$force_reparse)
            return $this->parsed;

        if ($this->flags && self::VISNODE_ARRAY)
        {
            $parts = Array();
            $delimiter = (isset($this->vars['_DELIM'])) ? $this->vars['_DELIM'] : '';
            foreach ($this->vars as $data)
                if (is_array($data))
                    $parts[] = $visualizer->parseVIS($this->type, $data);
            $text = implode($delimiter, $parts);
        }
        else
        {
            foreach ($this->subs as $var => $subnodes)
                foreach ($subnodes as $subnode)
                    $this->vars[$var][] = $subnode->parse($force_reparse);

            foreach ($this->vars as $var => $vals)
                $data[$var] = implode(VIS_BR, $vals);

            if ($add_vars)
                foreach ($add_vars as $var => $vals)
                    $data[$var] = implode(VIS_BR, $vals);

            $text = $visualizer->parseVIS($this->type, $data);
        }

        $this->parsed =& $text;
        $this->isParsed = true;

        return $text;
    }

    public function addData($varname, $data)
    {        if (!$data || !$varname)
            return false;

        $varname = strtoupper($varname);
        if (is_array($data))
            $data = implode(' ', $data);
        if (!isset($this->vars[$varname]))
            $this->vars[$varname] = Array($data);
        else
            $this->vars[$varname][] = $data;

        return true;
    }

    public function addDataArray($arr, $prefix = '', $delimiter = '')
    {
        if (!is_array($arr) || !is_string($prefix))
            return false;

        if ($this->flags && self::VISNODE_ARRAY)
        {
            if (is_array($arr) && count($arr))
            {
                $this->vars = Array();
                $in = 0;
                foreach ($data_arr as $arr)
                    if (is_array($arr))
                    {
                        $this->vars[$in] = Array();
                        foreach ($arr as $key => $var)
                        {
                            $key = strtoupper($prefix.$key);
                            if (is_array($var))
                                $var = implode(' ', $var);
                            $this->vars[$in][$key] = $var;
                        }

                        $this->vars[$in]['_POS'] = $in;

                        $in++;
                    }

                $this->vars[0]['_IS_FIRST'] = '1';
                $this->vars[$in-1]['_IS_LAST'] = '1';
            }

            if (strlen($delimiter))
                $this->vars['_DELIM'] = (string) $delimiter;
        }
        else
            foreach ($arr as $key => $var)
            {
                $key = strtoupper($prefix.$key);
                if (is_array($var))
                    $var = implode(' ', $var);
                if (!isset($this->vars[$key]))
                    $this->vars[$key] = Array($var);
                else
                    $this->vars[$key][] = $var;
            }

        return true;
    }

    public function appendChild($varname, FVISNode $node)
    {        if (!$node || !$varname)
            return false;

        $varname = strtoupper($varname);
        // TODO: loops checking
        $this->subs[$varname][] = $node;
        return true;
    }

    public function clear()
    {        $this->vars = Array();
        $this->subs = Array();
        $this->parsed = '';
    }

    public function __toString()
    {
        return $this->parse();
    }
}

// VIS interface
class FVISInterface extends FEventDispatcher
{
    // Cache prefixes for module data
    const VPREFIX = 'VIS.';
    const CPREFIX = 'VIS_CSS.';
    const JPREFIX = 'VIS_JS.';
    const COMMON = 'common';

    // defining some usefull constants
    // VIS resource types
    const VIS_NORMAL =  0;
    const VIS_STATIC =  1;
    const VIS_DINAMIC = 2;

    private $templates  = Array();

    private $VCSS_data  = ''; // CSS loaded from visuals
    private $VJS_data   = ''; // JS loaded from visuals
    private $CSS_data   = '';
    private $JS_data    = '';
    private $Consts     = Array();

    // nodes arrays
    private $nodes      = Array();
    private $named      = Array();

    private $VIS_loaded = Array();
    private $CSS_loaded = false;
    private $JS_loaded  = Array();

    private $vis_consts = Array();
    private $func_parsers = Array();

    private $auto_loads = Array();

    private $cPrefix = '';
    var $force_compact = true;  // forces to compact CSS/JS data

    private function __construct()
    {
        $this->nodes[0] = new FVISNode('GLOBAL_HTMLPAGE');
        $this->named = Array('PAGE' => 0, 'MAIN' => 0);
        $this->func_parsers = Array(
            'FULLURL'   => array('FStr', 'fullUrl'),
            'HTMLQUOTE' => 'htmlspecialchars',
            'SMARTHTMLQUOTE' => array('FStr', 'smartHTMLSchars'),
            'URLEN'     => array('FStr', 'urlencode'),
            'JS_DEF'    => array('FStr', 'JSDefine'),
            'PHP_DEF'   => array('FStr', 'PHPDefine'),
            'FTIME'     => Array(F('LNG'), 'timeFormat'),
            'FBYTES'    => Array(F('LNG'), 'sizeFormat'),
            );

        $this->clear();
    }

    public function clear($keep_nodes = false)
    {        $this->templates  = Array();
        $this->VCSS_data  = '';
        $this->VJS_data   = '';
        $this->VIS_loaded = Array();
        $this->CSS_data   = '';
        $this->JS_data    = '';
        $this->CSS_loaded = false;
        $this->JS_loaded  = Array();
        $this->vis_consts = Array(
            'TIME' => F('Timer')->qTime(),
            );

        if ($keep_nodes)
            return;

        //clearing nodes
        foreach ($this->nodes as $node)
            if ($node)
                $node->clear();

        array_splice($this->nodes, 1);
        $this->named = Array('PAGE' => 0, 'MAIN' => 0);
    }

    public function setVConsts($consts, $no_replace = false)
    {        if (!is_array($consts))
            return false;

        $this->vis_consts = ($no_replace)
            ? $this->vis_consts + $consts
            : $consts + $this->vis_consts;

        return true;
    }

    public function addAutoLoadDir($directory, $file_suff = '.vis')
    {        $directory = FStr::path($directory);
        $hash = FStr::pathHash($directory);
        if (isset($this->auto_loads[$hash]))
            return true;

        $cachename = self::VPREFIX.'ald-'.$hash;
        if ($aldata = FCache::get($cachename))
        {
            $this->auto_loads[$hash] = $aldata;
            F('Timer')->logEvent($directory.' autoloads installed (from global cache)');
        }
        else
        {
            if ($dir = opendir($directory))
            {
                $aldata = Array(0 => $directory);
                $preg_pattern = '#'.preg_quote($file_suff, '#').'$#';
                while ($entry = readdir($dir))
                {                    $filename = $directory.DIRECTORY_SEPARATOR.$entry;
                    if (preg_match($preg_pattern, $entry) && is_file($filename) && $datas = FMisc::loadDatafile($filename, FMisc::DF_BLOCK, true))
                    {                        $datas = array_keys($datas);
                        foreach ($datas as $key)
                            $aldata[$key] = $entry;
                    }
                }
                closedir($dir);

                FCache::set($cachename, $aldata);
                $this->auto_loads[$hash] = $aldata;
                F('Timer')->logEvent($filename.' autoloads installed (from filesystem)');
            }
            else
            {
                trigger_error('VIS: error installing '.$directory.' auto loading directory', E_USER_WARNING );
                return false;
            }
        }

        return true;
    }

    public function loadECSS($filename)
    {
        $hash = FStr::pathHash($filename);
        $cachename = self::CPREFIX.$this->cPrefix.F('LNG')->ask().'.'.$hash;

        if ($Cdata = FCache::get($cachename))
        {
            $this->CSS_data = $Cdata;
            F('Timer')->logEvent($filename.' CSS file loaded (from global cache)');
        }
        else
        {
            if ($indata = FMisc::loadDatafile($filename))
            {
                $Cdata = $this->prepareECSS($indata, $this->vis_consts);

                FCache::set($cachename, $Cdata);
                $this->CSS_data = $Cdata;
                F('Timer')->logEvent($filename.' CSS file loaded (from ECSS file)');
            }
            else
                trigger_error('VIS: error loading '.$filename.' ECSS file', E_USER_WARNING );
        }

        $this->CSS_loaded = $hash;
        return true;
    }

    public function loadEJS($filename)
    {        $hash = FStr::pathHash($filename);

        if (!in_array($hash, $this->JS_loaded))
        {
            $cachename = self::JPREFIX.$this->cPrefix.F('LNG')->ask().'.'.$hash;

            if ($JSData = FCache::get($cachename))
            {
                $this->JS_data.= VIS_BR.$JSData;

                F('Timer')->logEvent('"'.$filename.'" JScript loaded (from global cache)');
            }
            else
            {
                if (!file_exists($filename))
                {
                    trigger_error('VIS: there is no '.$filename.' EJS file', E_USER_WARNING );
                }
                elseif ($indata = FMisc::loadDatafile($filename))
                {
                    $this->throwEventRef('EJS_PreParse', $indata);

                    $JSData = $this->prepareEJS($indata, $this->vis_consts);
                    $this->JS_data.= VIS_BR.$JSData;

                    FCache::set($cachename, $JSData);
                    F('Timer')->logEvent('"'.$filename.'" JScript loaded (from EJS file)');
                }
                else
                    trigger_error('VIS: error loading "'.$filename.'" EJS file', E_USER_WARNING );
            }

            $this->JS_loaded[] = $hash;
        }
        return true;
    }

    public function loadTemplates($filename)
    {
        $hash = FStr::pathHash($filename);

        if (!in_array($hash, $this->VIS_loaded))
        {
            $cachename = self::VPREFIX.$this->cPrefix.F('LNG')->ask().'.'.$hash;

            if (list($Tdata, $VCSS, $VJS) = FCache::get($cachename))
            {
                $this->templates += $Tdata;
                $this->VCSS_data .= VIS_BR.$VCSS;
                $this->VJS_data  .= VIS_BR.$VJS;

                F('Timer')->logEvent('"'.$filename.'" visuals loaded (from global cache)');
            }
            else
            {
                if (!file_exists($filename))
                {
                    trigger_error('VIS: there is no '.$filename.' VIS file', E_USER_WARNING );
                }
                elseif ($indata = FMisc::loadDatafile($filename, FMisc::DF_BLOCK, true))
                {
                    $this->throwEventRef('VIS_PreParse', $indata, $style, $part);

                    $Tdata  = Array();
                    $VCSS   = '';
                    $VJS    = '';
                    foreach ($indata as $name => $templ)
                    {
                        if ($name == 'CSS')
                            $VCSS.= $this->prepareECSS($templ);
                        elseif ($name == 'JS')
                            $VJS.= $templ; // EJS can contain {V_ links
                                           // so we need to store it first and parse after VIS loading
                        else // normal VIS
                            $Tdata[$name] = $this->prepareVIS($templ);
                    }

                    $this->templates += $Tdata;
                    $this->VCSS_data .= VIS_BR.$VCSS;
                    $VJS = $this->prepareEJS($VJS); // and here we actually parse EJS
                    $this->VJS_data  .= VIS_BR.$VJS;

                    FCache::set($cachename, Array($Tdata, $VCSS, $VJS) );
                    F('Timer')->logEvent('"'.$filename.'" visuals loaded (from VIS file)');
                }
                else
                    trigger_error('VIS: error loading or parsing "'.$filename.'" VIS file for style "'.$this->style_name.'"', E_USER_WARNING );
            }

            $this->VIS_loaded[] = $hash;
        }
        return true;
    }

    // parsing functions
    public function parse($node = 0, $force_reparse = false)
    {
        if (!is_int($node))
            $node = $this->findNodeId($node);

        if (is_null($node))
            return false;

        if (!isset($this->nodes[$node]))
        {
            trigger_error('VIS: trying to parse a fake node', E_USER_WARNING);
            return false;
        }

        return $this->nodes[$node]->parse($force_reparse);
    }

    public function makeHTML($force_reparse = false)
    {
        $vars = Array(
            'CSS' => Array($this->CSS_data, $this->VCSS_data),
            'JS'  => Array($this->JS_data,  $this->VJS_data),
            );

        return $this->nodes[0]->parse($force_reparse, $vars);
    }

    public function makeCSS()
    {
        return ($this->CSS_loaded) ? trim($this->CSS_data) : false;
    }

    public function makeJS()
    {
        return ($this->JS_loaded) ? trim($this->JS_data) : false;
    }

    // node tree construction functions
    public function createNode($template, $data_arr = false, $globname = null)
    {
        $template = (string) $template;
        if (!$template)
            return false;

        end($this->nodes);
        $id = key($this->nodes) + 1;

        $this->nodes[$id] = new FVISNode($template);

        if (is_array($data_arr))
            $this->nodes[$id]->addDataArray($data_arr);

        if ($globname && !is_numeric($globname))
        {
            $globname = strtoupper($globname);
            $this->named[$globname] = $id;
        }

        return $id;
    }

    public function appendNode($node_id, $varname, $parent = 0)
    {
        if (!is_int($parent))
            $parent = $this->findNodeId($parent);
        if (!is_int($node_id))
            $node_id = $this->findNodeId($node_id);

        if (!$varname)
            return false;

        if (!isset($this->nodes[$parent]))
        {
            trigger_error('VIS: trying to append data to fake node', E_USER_WARNING);
            return false;
        }
        if (!isset($this->nodes[$node_id]))
        {
            trigger_error('VIS: trying to append a fake node', E_USER_WARNING);
            return false;
        }

        return $this->nodes[$parent]->appendChild($varname, $this->nodes[$node_id]);
    }

    public function addNode($template, $varname, $parent = 0, $data_arr = false, $globname = null)
    {
        if (!is_int($parent))
            $parent = $this->findNodeId($parent);

        if (!$varname)
            return false;

        if (!isset($this->nodes[$parent]))
        {
            trigger_error('VIS: trying to append data to fake node', E_USER_WARNING);
            return false;
        }

        if ($id = $this->createNode($template, $data_arr, $globname))
            if ($this->nodes[$parent]->appendChild($varname, $this->nodes[$id]))
                return $id;

        return false;
    }

    // Adds arrayed node
    public function addNodeArray($template, $varname, $parent = 0, $data_arr = false, $delimiter = false)
    {
        if (!is_int($parent))
            $parent = $this->findNodeId($parent);

        if (!$varname)
            return false;

        if (!isset($this->nodes[$parent]))
        {
            trigger_error('VIS: trying to append node to fake node', E_USER_WARNING);
            return false;
        }

        $template = (string) $template;
        if (!$template)
            return false;

        end($this->nodes);
        $id = key($this->nodes) + 1;

        if ($this->nodes[$id] = new FVISNode($template, FVISNode::VISNODE_ARRAY))
        {
            $this->nodes[$parent]->appendChild($varname, $this->nodes[$id]);

            $this->nodes[$id]->addDataArray($data_arr);

            return $id;
        }

        return false;
    }

    public function addData($node, $varname, $data)
    {
        if (is_null($node))
            return false;

        if (!isset($this->nodes[$node]) || ($this->nodes[$node]->flags && self::VISNODE_ARRAY))
        {
            trigger_error('VIS: trying to append data to fake node', E_USER_WARNING);
            return false;
        }

        return $this->nodes[$node]->addData($varname, $data);
    }

    public function addDataArray($node, $arr, $prefix = '')
    {
        if (is_null($node))
            return false;

        if (!isset($this->nodes[$node]) || ($this->nodes[$node]->flags && self::VISNODE_ARRAY))
        {
            trigger_error('VIS: trying to append data to fake node', E_USER_WARNING);
            return false;
        }

        return $this->nodes[$node]->addDataArray($arr, $prefix = '');
    }

    public function findNodeId($to_find)
    {
        if (!$to_find)
            return 0;

        if ($to_find instanceof FVISNode)
            list($to_find) = array_keys($this->nodes, $to_find);
        elseif (!is_numeric($to_find))
        {
            $to_find = strtoupper($to_find);
            if (isset($this->named[$to_find]))
                $to_find = $this->named[$to_find];
            else
                return null;
        }

        $to_find = (int) $to_find;
        return $to_find;
    }


    // parsing functions

    public function prepareVIS($text)
    {
        static $consts = Array(
            'F_MARK'  => 'Powered by<br />Kernel 3<br />&copy; Foxel aka LION<br /> 2006 - 2009',
            'F_INDEX' => F_INDEX,
            );

        $consts['F_ROOT'] = F('HTTP')->rootUrl;

        $consts = $this->vis_consts;

        $text = trim($text);

        if ($this->force_compact)
            $text = $this->compactHTML($text);

        $text = $this->templLang($text);

        $text = preg_replace('#(?<=\})\n\s*?(?=\{\w)#', '', $text);
        preg_match_all('#\{(\!?)((?>\w+))(?:\:((?:(?>\w+)(?:[\!=\>\<]{1,2}(?:\w+|\"[^\"]*\"))?|\||)*))?\}|[^\{]+|\{#', $text, $struct, PREG_SET_ORDER);

        $writes_to = '$OUT';
        $text = $writes_to.' = <<<FTEXT'.VIS_BR;
        $vars = Array();

        $iflevel = 0;
        $outiflevel = 0;
        $in_for = false;

        $keys = array_keys($struct);

        foreach ($keys as $key)
        {
            $part =& $struct[$key];

            if (isset($part[2]) && ($tag = strtoupper($part[2])))
            {
                $got_a = ($part[1]) ? true : false;
                $params = Array();
                if (isset($part[3]) && ($got = preg_match_all('#((?>\w+))(?:([\!=\>\<]{1,2})(\w+|\"[^\"]*\"))?#', $part[3], $params, PREG_PATTERN_ORDER)))
                    for ($i = 0; $i < $got; $i++)
                        $params[1][$i] = strtoupper($params[1][$i]);

                if ($tag == 'WRITE')
                {                    if (isset($params[1]) && count($params[1]) && ($var = $params[1][0]) && !is_numeric($var[0]))
                        $var = '$'.$var;
                    else
                        $var = '$OUT';
                    if ($var != $writes_to)
                    {                        $writes_to = $var;
                        $text.= VIS_BR.'FTEXT;'.VIS_BR.$writes_to.(($got_a) ? '' : '.').'= <<<FTEXT'.VIS_BR;
                    }
                }
                elseif (isset($this->func_parsers[$tag])) //parsing the variable with func
                {
                    $func_parser = $this->func_parsers[$tag];

                    if (!isset($params[1]) || !count($params[1]))
                        continue;
                    $pars = count($params[1]);

                    $text.= VIS_BR.'FTEXT'.VIS_BR.'.';
                    for ($i = 0; $i < $pars; $i++)
                        $text.= $this->templVISParamCB($params[1][$i], $vars, $consts, $tag);
                    $text.= '.<<<FTEXT'.VIS_BR;
                }
                elseif ($tag == 'SET')
                {                    if ($pars = count($params[1]))
                    {
                        $sets = '';
                        for($i = 0; $i < $pars; $i++)
                        {                            $var = $params[1][$i];
                            if (is_numeric($var[0]) || !isset($params[3][$i]) || !strlen($params[3][$i]))
                                continue;
                            $val = $params[3][$i];
                            $sets.= '$'.$var.' = '.$this->templVISParamCB($val, $vars, $consts).';';
                        }

                        if ($sets)
                            $text.= VIS_BR.'FTEXT;'.VIS_BR.$sets.VIS_BR.$writes_to.'.= <<<FTEXT'.VIS_BR;
                    }
                }
                elseif ($tag == 'FOR')
                {                    if (!isset($params[1]) || !count($params[1]) || $in_for)
                        continue;
                    $params = $params[1];
                    $p1 = array_shift($params);
                    $p2 = array_shift($params);
                    $p3 = array_shift($params);

                    $p1 = (is_numeric($p1[0])) ? intval($p1) : '(int) $'.$p1.($vars[$p1] = '');
                    if ($p2)
                        $p2 = (is_numeric($p2[0])) ? intval($p2) : '(int) $'.$p2.($vars[$p2] = '');
                    else
                    {
                        $p2 = $p1;
                        $p1 = '0';
                    }
                    if ($p3)
                        $p3 = (is_numeric($p3[0])) ? intval($p3) : '(int) $'.$p3.($vars[$p3] = '');
                    else
                        $p3 = '1';

                    $in_for = true;
                    $outiflevel = $iflevel;
                    $iflevel = 0;
                    $text.= VIS_BR.'FTEXT;'.VIS_BR.'for ($I = '.$p1.'; $I <= '.$p2.'; $I+= '.$p3.') {'.VIS_BR.$writes_to.'.= <<<FTEXT'.VIS_BR;
                }
                elseif ($tag == 'ENDFOR')
                {
                    if ($in_for)
                    {
                        $text.= VIS_BR.'FTEXT;'.VIS_BR;
                        $in_for = false;
                        $text.= str_repeat('} ', $iflevel);
                        $text.= VIS_BR.'}'.VIS_BR.$writes_to.'.= <<<FTEXT'.VIS_BR;
                        $iflevel = $outiflevel;
                    }
                }
                elseif ($tag == 'VIS')
                {
                    if (!isset($params[1]) || !count($params[1]))
                        continue;
                    $visname = $params[1][0];
                    $text.= VIS_BR.'FTEXT;'.VIS_BR.$writes_to.(($got_a) ? '' : '.').'= $this->parseVIS(\''.$visname.'\'';
                    if (count($params[1]) > 1)
                    {
                        $text.= ', Array(';
                        $pars = count($params[1]);
                        for($i = 1; $i < $pars; $i++)
                        {
                            $var = $params[1][$i];
                            $val = (isset($params[3][$i]) && strlen($params[3][$i])) ? $params[3][$i] : '1';
                            $text.= '\''.$var.'\' => '.$this->templVISParamCB($val, $vars, $consts).',';
                        }
                        $text.= ') ';
                    }
                    $text.= ');'.VIS_BR.$writes_to.'.= <<<FTEXT'.VIS_BR;
                }
                elseif ($tag == 'IF' || $tag == 'ELSEIF')
                {
                    if (!isset($params[1]) || !count($params[1]))
                        continue;
                    $varname = $params[1][0];
                    $vars[$varname] = '';
                    if (isset($params[3][0]) && strlen($params[3][0]))
                    {
                        $condvar = $params[3][0];
                        $condition = $params[2][0];
                        $condition = (in_array($condition, Array('>', '<', '>=', '<=', '!=')))
                            ? ' '.$condition.' '
                            : ' == ';

                        if (is_numeric($condvar[0]))
                            $condition = '((int) $'.$varname.$condition.intval($condvar).')';
                        else
                            $condition = '($'.$varname.$condition.$this->templVISParamCB($condvar, $vars, $consts).')';
                    }
                    else
                        $condition = 'strlen($'.$varname.')';

                    if ($got_a)
                        $condition = '!'.$condition;

                    if ($tag == 'IF')
                    {
                        $text.= VIS_BR.'FTEXT;'.VIS_BR.'if ('.$condition.') {'.VIS_BR.$writes_to.'.= <<<FTEXT'.VIS_BR;
                        $iflevel++;
                    }
                    elseif ($iflevel)
                        $text.= VIS_BR.'FTEXT;'.VIS_BR.'} elseif('.$condition.') {'.VIS_BR.$writes_to.'.= <<<FTEXT'.VIS_BR;
                }
                elseif ($tag == 'ELSE')
                {
                    if ($iflevel)
                        $text.= VIS_BR.'FTEXT;'.VIS_BR.'} elseif(true) {'.VIS_BR.$writes_to.'.= <<<FTEXT'.VIS_BR;
                }
                elseif ($tag == 'ENDIF')
                {
                    if ($iflevel)
                    {
                        $text.= VIS_BR.'FTEXT;'.VIS_BR.'}'.VIS_BR.$writes_to.'.= <<<FTEXT'.VIS_BR;
                        $iflevel--;
                    }
                }
                else
                {
                    $varname = $tag;
                    if (isset($consts[$varname]))
                        $text.= FStr::addslashesHeredoc($consts[$varname], 'FTEXT');
                    elseif (!is_numeric($varname[0]))
                    {
                        $vars[$varname] = '';
                        if ($got_a)
                            $text.= VIS_BR.'FTEXT'.VIS_BR.'.FStr::smartHTMLSchars($'.$varname.').<<<FTEXT'.VIS_BR;
                        else
                            $text.= '{$'.$varname.'}';
                    }
                }
            }
            else
            {
                $text.= FStr::addslashesHeredoc($part[0], 'FTEXT');
            }

            unset($struct[$key]);
        }

        $text.= VIS_BR.'FTEXT;'.VIS_BR;

        $text.= str_repeat('} ', $iflevel);
        if ($in_for)
            $text.= str_repeat('} ', $outiflevel + 1);

        $text = preg_replace('#\$\w+\.\=\s*\<\<\<FTEXT\s+FTEXT;#', '', $text);
        return Array('T' => $text, 'V' => $vars);
    }

    public function parseVIS($vis, $data = Array())
    {
        Static $__COUNTER=1;
        /* Static $F_SID;
        if (!$F_SID)
            $F_SID = F('Session')->SID; */

        $COUNTER = $__COUNTER++;
        $RANDOM = dechex(rand(0x1FFF, getrandmax()));

        $vis = strtoupper($vis);
        if (!isset($this->templates[$vis]) && !$this->tryAutoLoad($vis))
            return implode(VIS_BR, $data);

        if (extract($this->templates[$vis]['V'], EXTR_SKIP))
        {
            extract($data, EXTR_OVERWRITE | EXTR_PREFIX_ALL, 'IN');
            extract($this->vis_consts, EXTR_OVERWRITE | EXTR_PREFIX_ALL, 'C');
        }

        $OUT = '';

        if (eval($this->templates[$vis]['T']) === false)
            trigger_error('VIS: error running "'.$vis.'" template. CODE ['.$this->templates[$vis]['T'].']', E_USER_ERROR);

        return $OUT;
    }

    public function prepareECSS($indata, $constants = null, $force_compact = false)
    {
        if (is_array($constants))
            foreach ($constants as $name=>$val)
                $indata = str_replace('{'.$name.'}', $val, $indata);

        $vars_mask='#\{((?>[\w\-]+))\}\s*=(.*)#';
        $vars_block='#\{VARS\}(.*?)\{/VARS\}#si';

        preg_match_all($vars_block, $indata, $blocks);
        $blocks = implode(' ', $blocks[0]);

        preg_match_all($vars_mask, $blocks, $sets);
        if (is_array($sets[1]))
            foreach ($sets[1] as $num => $name)
                $CSSVars[strtoupper($name)] = trim($sets[2][$num]);

        $Cdata = preg_replace($vars_block, '', $indata);

        if ($this->force_compact || $force_compact)
            $Cdata = $this->compactCSS($Cdata);

        $Cdata = preg_replace('#\{(?>(\w+))\}#e', '(isset(\$CSSVars[strtoupper("\1")])) ? \$CSSVars[strtoupper("\1")] : ""', $Cdata);

        return $Cdata;
    }

    public function prepareEJS($Jdata, $constants = null, $force_compact = false)
    {
        if (is_array($constants))
            foreach ($constants as $name=>$val)
                $Jdata = str_replace('{'.$name.'}', $val, $Jdata);
        if ($this->force_compact || $force_compact)
            $Jdata = $this->compactJS($Jdata);
        return $Jdata;
    }

    public function compactJS($indata)
    {
        $indata = str_replace("\r", '', $indata);
        $indata = preg_replace('#^//.*?$#m', '', $indata);
        $indata = preg_replace('#(?<=\s)//.*?$#m', '', $indata);
        $indata = preg_replace('#(\n\s*)+#', "\n", $indata);
        $indata = preg_replace('#\n(.{1,5})$#m', ' \\1', $indata);
        $indata = str_replace("\n", VIS_BR, $indata);
        $indata = trim($indata);
        return $indata;
    }

    public function compactCSS($indata)
    {
        $indata = str_replace("\r", '', $indata);
        $indata = preg_replace('#/\*.+\*/#sU', '', $indata);
        $indata = preg_replace('#(\n\s*)+#', "\n", $indata);
        $indata = preg_replace('#\n(.{1,5})$#m', ' \\1', $indata);
        $indata = preg_replace('#\s*(,|:|;|\{)\s+#', '\\1 ', $indata);
        $indata = preg_replace('#\s+\}\s+#', " }\n", $indata);
        $indata = str_replace("\n", VIS_BR, $indata);
        $indata = trim($indata);
        return $indata;
    }

    public function compactHTML($indata)
    {
        $indata = str_replace("\r", '', $indata);
        $indata = preg_replace('#(\n\s*)+#', "\n", $indata);
        $indata = preg_replace('#\n(.{1,5})$#m', ' \\1', $indata);
        $indata = preg_replace('#\x20+#', ' ', $indata);
        $indata = str_replace("\n", VIS_BR, $indata);
        $indata = trim($indata);
        return $indata;
    }

    // private loaders

    private function tryAutoLoad($vis)
    {        $loads = end($this->auto_loads);
        do {            if (isset($loads[$vis]))
            {
                $this->loadTemplates($loads[0].DIRECTORY_SEPARATOR.$loads[$vis]);
                return isset($this->templates[$vis]);
            }
        } while ($loads = prev($this->auto_loads));

        return false;
    }

    // private parsers

    // reparses {L_...} blocks in raw templates
    private function templLang($data)
    {
        return preg_replace('#\{(?>L_((?:\w+|\"[^\"]+\"|\|)+))\}#e','\$this->templLangCB(\'$1\')', $data);
    }

    private function templLangCB($code)
    {
        $code = explode('|', $code);

        if (!($lng = strtoupper($code[0])))
            return '';

        if (count($code)>1)
        {
            $params = array_slice($code, 1);
            foreach ($params as &$val)
                $val = ($val[0] == '"') ? substr($val, 1, -1) : '{'.$val.'}';

            $data = F('LNG')->lang($lng, $params);
        }
        else
            $data = F('LNG')->lang($lng);

        return $data;
    }

    private function templVISParamCB($val, $vars, $consts, $parsewith = null)
    {        $code = '';
        $static = true;

        if (is_numeric($val[0]) && $val[0] != '0')
            $val = intval($val);
        elseif ($val[0] == '"')
            $val = substr($val, 1, -1);
        else
        {
            $val = strtoupper($val);
            if (substr($val, 0, 2) == 'L_')
                $val = $this->templLangCB(Array(1 => substr($val, 2)));
            elseif (isset($consts[$val]))
                $val = $consts[$val];
            else
            {
                $vars[$val] = '';
                $static = false;
                $code = '$'.$val;
            }
        }

        if ($static)
        {            if ($parsewith)
                $val = $this->callParseFunction($parsewith, $val);
            $code = is_string($val) ? '<<<FTEXT'.VIS_BR.FStr::addslashesHeredoc($val, 'FTEXT').VIS_BR.'FTEXT'.VIS_BR : (string) $val;
        }
        elseif ($parsewith)
            $code = '$this->callParseFunction('.$parsewith.', '.$code.')';

        return $code;
    }

    private function callParseFunction($func_name, $data) //parsing data with funcParser
    {
        if (!isset($this->func_parsers[$func_name]))
            return $data;

        $func_parser = $this->func_parsers[$func_name];
        return call_user_func($func_parser, $data);
    }


    // singleton structures
    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FVISInterface();
        return self::$self;
    }
}


?>

<?php
/*
 * QuickFox kernel 3 'SlyFox' HTTP interface
 * Requires PHP >= 5.1.0
 */


if (!defined('F_STARTED'))
    die('Hacking attempt');

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
            $vars = $this->vars; // needed not to store duplicates of subnode data while forced reparsing

            foreach ($this->subs as $var => $subnodes)
                foreach ($subnodes as $subnode)
                    $vars[$var][] = $subnode->parse($force_reparse);

            foreach ($vars as $var => $vals)
                $data[$var] = implode(FStr::ENDL, $vals);

            if ($add_vars)
                foreach ($add_vars as $var => $vals)
                    $data[$var] = implode(FStr::ENDL, $vals);

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

    public function addNode($template, $varname, $data_arr = false, $globname = null)
    {
        if (!$varname)
            return false;

        $visualizer = FVISInterface::getInstance();
        if ($node = $visualizer->createNode($template, $data_arr, $globname))
            if ($this->appendChild($varname, $node))
                return $node;

        return null;
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
    const COMMON  = 'common';

    const VIS_BR = FStr::ENDL;

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
                $this->JS_data.= FStr::ENDL.$JSData;

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
                    $this->JS_data.= FStr::ENDL.$JSData;

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
                $this->VCSS_data .= FStr::ENDL.$VCSS;
                $this->VJS_data  .= FStr::ENDL.$VJS;

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
                    $this->VCSS_data .= FStr::ENDL.$VCSS;
                    $VJS = $this->prepareEJS($VJS); // and here we actually parse EJS
                    $this->VJS_data  .= FStr::ENDL.$VJS;

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

        return $this->nodes[$id];
    }

    public function appendNode($node, $varname, $parent = 0)
    {
        $parent = $this->findNode($parent);
        $node = $this->findNode($node);

        if (!$varname)
            return false;

        if (!$parent)
        {
            trigger_error('VIS: trying to append data to fake node', E_USER_WARNING);
            return false;
        }
        if (!$node)
        {
            trigger_error('VIS: trying to append a fake node', E_USER_WARNING);
            return false;
        }

        return $parent->appendChild($varname, $node);
    }

    public function addNode($template, $varname, $parent = 0, $data_arr = false, $globname = null)
    {
        if (!$varname)
            return false;

        $parent = $this->findNode($parent);

        if (!$parent)
        {
            trigger_error('VIS: trying to append data to fake node', E_USER_WARNING);
            return false;
        }

        if ($node = $this->createNode($template, $data_arr, $globname))
            if ($parent->appendChild($varname, $node))
                return $node;

        return null;
    }

    // Adds arrayed node
    public function addNodeArray($template, $varname, $parent = 0, $data_arr = false, $delimiter = false)
    {
        $parent = $this->findNode($parent);

        if (!$varname)
            return false;

        if (!$parent)
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
            $parent->appendChild($varname, $this->nodes[$id]);

            $this->nodes[$id]->addDataArray($data_arr);

            return $this->nodes[$id];
        }

        return false;
    }

    public function addData($node, $varname, $data)
    {
        $node = $this->findNode($node);

        if (!$node || ($node->flags && self::VISNODE_ARRAY))
        {
            trigger_error('VIS: trying to append data to fake node', E_USER_WARNING);
            return false;
        }

        return $node->addData($varname, $data);
    }

    public function addDataArray($node, $arr, $prefix = '')
    {
        $node = $this->findNode($node);

        if (!$node || ($node->flags && self::VISNODE_ARRAY))
        {
            trigger_error('VIS: trying to append data to fake node', E_USER_WARNING);
            return false;
        }

        return $node->addDataArray($arr, $prefix = '');
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

    public function findNode($to_find)
    {
        if ($to_find instanceof FVISNode)
        {
            if (!in_array($to_find, $this->nodes))
                array_push($this->nodes, $to_find);
            return $to_find;
        }
        elseif (is_numeric($to_find))
        {            $to_find = (int) $to_find;
            if (isset($this->nodes[$to_find]))
                return $this->nodes[$to_find];
        }
        else
        {
            $to_find = strtoupper($to_find);
            if (isset($this->named[$to_find]))
                return $this->nodes[$this->named[$to_find]];
        }

        return null;
    }

    // parsing functions

    public function prepareVIS($text, $store_to = false)
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

        $writes_to = 'OUT';
        $text = '$'.$writes_to.' = <<<FTEXT'.FStr::ENDL;
        $jstext = $writes_to.' = "';
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
                        $var = $var;
                    else
                        $var = 'OUT';
                    if ($var != $writes_to)
                    {                        $writes_to = $var;
                        $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL.'$'.$writes_to.(($got_a) ? '' : '.').'= <<<FTEXT'.FStr::ENDL;
                        $jstext.= '";'.FStr::ENDL.$writes_to.(($got_a) ? '' : '+').'= "';
                    }
                }
                elseif (isset($this->func_parsers[$tag])) //parsing the variable with func
                {
                    $func_parser = $this->func_parsers[$tag];

                    if (!isset($params[1]) || !count($params[1]))
                        continue;
                    $pars = count($params[1]);

                    $text.= FStr::ENDL.'FTEXT'.FStr::ENDL.'.';
                    $jstext.= '"'.FStr::ENDL.'+';
                    for ($i = 0; $i < $pars; $i++)
                    {
                        $text.= $this->templVISParamCB($params[1][$i], $vars, $consts, $tag);
                        $jstext.= $this->templVISParamCB($params[1][$i], $vars, $consts, $tag, true);
                    }
                    $text.= '.<<<FTEXT'.FStr::ENDL;
                    $jstext.= '+"';
                }
                elseif ($tag == 'SET')
                {                    if ($pars = count($params[1]))
                    {
                        $sets = $jssets = '';
                        for($i = 0; $i < $pars; $i++)
                        {                            $var = $params[1][$i];
                            if (is_numeric($var[0]) || !isset($params[3][$i]) || !strlen($params[3][$i]))
                                continue;
                            $val = $params[3][$i];
                            $sets.= '$'.$var.' = '.$this->templVISParamCB($val, $vars, $consts).';';
                            $jssets.= $var.' = '.$this->templVISParamCB($val, $vars, $consts, false, true).';'.FStr::ENDL;
                        }

                        if ($sets)
                        {
                            $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL.$sets.FStr::ENDL.'$'.$writes_to.'.= <<<FTEXT'.FStr::ENDL;
                            $jstext.= '";'.FStr::ENDL.$sets.$writes_to.'+= "';
                        }
                    }
                }
                elseif ($tag == 'FOR')
                {                    if (!isset($params[1]) || !count($params[1]) || $in_for)
                        continue;
                    $params = $params[1];
                    $p1 = array_shift($params);
                    $p2 = array_shift($params);
                    $p3 = array_shift($params);

                    $in_for = true;
                    $outiflevel = $iflevel;
                    $iflevel = 0;

                    $pp1 = (is_numeric($p1[0])) ? intval($p1) : '(int) $'.$p1.($vars[$p1] = '');
                    if ($p2)
                        $pp2 = (is_numeric($p2[0])) ? intval($p2) : '(int) $'.$p2.($vars[$p2] = '');
                    else
                    {
                        $pp2 = $p2 = $p1;
                        $pp1 = $p1 = '0';
                    }
                    if ($p3)
                        $pp3 = (is_numeric($p3[0])) ? intval($p3) : '(int) $'.$p3.($vars[$p3] = '');
                    else
                        $pp3 = $p3 = '1';

                    $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL.'for ($I = '.$pp1.'; $I <= '.$pp2.'; $I+= '.$pp3.') {'.FStr::ENDL.'$'.$writes_to.'.= <<<FTEXT'.FStr::ENDL;
                    $jstext.= '";'.FStr::ENDL.'for (I = '.$p1.'; I <= '.$p2.'; I+= '.$p3.') {'.FStr::ENDL.$writes_to.'+= "';
                }
                elseif ($tag == 'ENDFOR')
                {
                    if ($in_for)
                    {
                        $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL;
                        $jstext.= '";'.FStr::ENDL;
                        $in_for = false;
                        $text.= str_repeat('} ', $iflevel);
                        $jstext.= str_repeat('} ', $iflevel);
                        $text.= FStr::ENDL.'}'.FStr::ENDL.'$'.$writes_to.'.= <<<FTEXT'.FStr::ENDL;
                        $jstext.= FStr::ENDL.'}'.FStr::ENDL.$writes_to.'.= "';
                        $iflevel = $outiflevel;
                    }
                }
                elseif ($tag == 'VIS') // no JS parsing for now
                {
                    if (!isset($params[1]) || !count($params[1]))
                        continue;
                    $visname = $params[1][0];
                    $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL.'$'.$writes_to.(($got_a) ? '' : '.').'= $this->parseVIS(\''.$visname.'\'';
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
                    $text.= ');'.FStr::ENDL.'$'.$writes_to.'.= <<<FTEXT'.FStr::ENDL;
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
                        {
                            $condition = '((int) $'.$varname.$condition.intval($condvar).')';
                            $jscondition = '('.$varname.$condition.intval($condvar).')';
                        }
                        else
                        {
                            $condition = '($'.$varname.$condition.$this->templVISParamCB($condvar, $vars, $consts).')';
                            $jscondition = '('.$varname.$condition.$this->templVISParamCB($condvar, $vars, $consts, false, true).')';
                        }
                    }
                    else
                    {
                        $condition = 'strlen($'.$varname.')';
                        $jscondition = $varname.'.length';
                    }

                    if ($got_a)
                        $condition = '!'.$condition;

                    if ($tag == 'IF')
                    {
                        $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL.'if ('.$condition.') {'.FStr::ENDL.'$'.$writes_to.'.= <<<FTEXT'.FStr::ENDL;
                        $jstext.= '";'.FStr::ENDL.'if ('.$jscondition.') {'.FStr::ENDL.$writes_to.'+= "';
                        $iflevel++;
                    }
                    elseif ($iflevel)
                    {
                        $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL.'} elseif('.$condition.') {'.FStr::ENDL.'$'.$writes_to.'.= <<<FTEXT'.FStr::ENDL;
                        $jstext.= '";}'.FStr::ENDL.'else if ('.$jscondition.') {'.FStr::ENDL.$writes_to.'+= "';
                    }
                }
                elseif ($tag == 'ELSE')
                {
                    if ($iflevel)
                    {
                        $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL.'} elseif(true) {'.FStr::ENDL.'$'.$writes_to.'.= <<<FTEXT'.FStr::ENDL;
                        $jstext.= '";}'.FStr::ENDL.'else if (true) {'.FStr::ENDL.$writes_to.'+= "';
                    }
                }
                elseif ($tag == 'ENDIF')
                {
                    if ($iflevel)
                    {
                        $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL.'}'.FStr::ENDL.'$'.$writes_to.'.= <<<FTEXT'.FStr::ENDL;
                        $jstext.= '";}'.FStr::ENDL.$writes_to.'+= "';
                        $iflevel--;
                    }
                }
                else
                {
                    $varname = $tag;
                    if (isset($consts[$varname]))
                    {
                        $text.= FStr::addslashesHeredoc($consts[$varname], 'FTEXT');
                        $jstext.= FStr::addslashesJS($consts[$varname]);
                    }
                    elseif (!is_numeric($varname[0]))
                    {
                        $vars[$varname] = '';
                        if ($got_a)
                        {
                            $text.= FStr::ENDL.'FTEXT'.FStr::ENDL.'.FStr::smartHTMLSchars($'.$varname.').<<<FTEXT'.FStr::ENDL;
                            $jstext.= '"+FStr.smartHTMLSchars('.$varname.')+"';
                        }
                        else
                        {
                            $text.= '{$'.$varname.'}';
                            $jstext.= '"+'.$varname.'+"';
                        }
                    }
                }
            }
            else
            {
                $text.= FStr::addslashesHeredoc($part[0], 'FTEXT');
                $jstext.= FStr::addslashesJS($part[0]);
            }

            unset($struct[$key]);
        }

        $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL;
        $jstext.= '";'.FStr::ENDL;

        $text.= str_repeat('} ', $iflevel);
        $jstext.= str_repeat('} ', $iflevel);
        if ($in_for)
        {
            $text.= str_repeat('} ', $outiflevel + 1);
            $jstext.= str_repeat('} ', $outiflevel + 1);
        }

        $text = preg_replace('#\$\w+\.\=\s*\<\<\<FTEXT\s+FTEXT;#', '', $text);
        $jstext = preg_replace('#\w+\+\=\s*"\s+";#', '', $jstext);
        $out = Array('T' => $text, 'V' => $vars, 'J' => $jstext);
        if ($store_to && is_string($store_to))
            $this->templates[$store_to] = $out;
        return $out;
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
            return implode(FStr::ENDL, $data);

        if (extract($this->templates[$vis]['V'], EXTR_SKIP))
        {
            extract($data, EXTR_REFS | EXTR_OVERWRITE | EXTR_PREFIX_ALL, 'IN');
            extract($this->vis_consts, EXTR_REFS | EXTR_OVERWRITE | EXTR_PREFIX_ALL, 'C');
        }

        $OUT = '';

        if (eval($this->templates[$vis]['T']) === false)
            trigger_error('VIS: error running "'.$vis.'" template. CODE ['.$this->templates[$vis]['T'].']', E_USER_ERROR);

        return $OUT;
    }

    public function prepJSFunc($vis, $data = Array())
    {
        Static $__COUNTER=1;
        /* Static $F_SID;
        if (!$F_SID)
            $F_SID = F('Session')->SID; */

        $COUNTER = $__COUNTER++;
        $RANDOM = dechex(rand(0x1FFF, getrandmax()));

        $vis = strtoupper($vis);
        if (!isset($this->templates[$vis]) && !$this->tryAutoLoad($vis))
            return implode(FStr::ENDL, $data);

        if (extract($this->templates[$vis]['V'], EXTR_REFS | EXTR_SKIP))
        {
            extract($data, EXTR_REFS | EXTR_OVERWRITE | EXTR_PREFIX_ALL, 'IN');
            extract($this->vis_consts, EXTR_REFS | EXTR_OVERWRITE | EXTR_PREFIX_ALL, 'C');
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
        $indata = str_replace("\n", FStr::ENDL, $indata);
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
        $indata = str_replace("\n", FStr::ENDL, $indata);
        $indata = trim($indata);
        return $indata;
    }

    public function compactHTML($indata)
    {
        $indata = str_replace("\r", '', $indata);
        $indata = preg_replace('#(\n\s*)+#', "\n", $indata);
        $indata = preg_replace('#\n(.{1,5})$#m', ' \\1', $indata);
        $indata = preg_replace('#\x20+#', ' ', $indata);
        $indata = str_replace("\n", FStr::ENDL, $indata);
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

    private function templVISParamCB($val, &$vars, $consts, $parsewith = null, $for_js = false)
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
            $code = is_string($val)
                ? ($for_js ? FStr::JSDefine($val) : FStr::heredocDefine($val, 'FTEXT'))
                : (string) $val;
        }
        elseif ($parsewith)
            $code = $for_js
                ? 'FVIS.callParseFunction(\''.$parsewith.'\', '.$val.')'
                : '$this->callParseFunction(\''.$parsewith.'\', $'.$val.')';

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

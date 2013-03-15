<?php
/**
 * Copyright (C) 2010 - 2012 Andrey F. Kupreychik (Foxel)
 *
 * This file is part of QuickFox Kernel 3.
 * See https://github.com/foxel/Kernel3/ for more details.
 *
 * Kernel 3 is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Kernel 3 is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Kernel 3. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * QuickFox kernel 3 'SlyFox' Visualizer/templater module
 * Requires PHP >= 5.1.0
 * @package kernel3
 * @subpackage visual
 */


if (!defined('F_STARTED'))
    die('Hacking attempt');

// VIS node class
class FVISNode extends FBaseClass // FEventDispatcher
{
    // node flags
    const VISNODE_ARRAY = 1; // node is an array of sametype nodes

    protected $type  = '';
    protected $vars  = array();

    protected $subs  = array();
    protected $flags = 0;
    protected $parsed = '';
    protected $isParsed = false;
    protected $VIS   = null;

    public function __construct($type, $flags = 0, FVISInterface $vis = null)
    {
        $this->type = (string) $type;
        $this->flags = $flags;
        $this->VIS = is_null($vis) ? F()->VIS : $vis;

        $this->pool = array(
            'type'       => &$this->type,
            'flags'      => &$this->flags,
            'parsed'     => &$this->isParsed,
            'visualizer' => &$this->VIS,
        );
    }

    public function setType($type)
    {
        $this->type = (string) $type;

        return $this;
    }

    public function setVisualizer(FVISInterface $vis)
    {
        $this->VIS = $vis;

        return $this;
    }

    public function parse($force = false, array $addVars = array())
    {
        $text = null;

        if ($this->isParsed && !$force)
            return $this->parsed;

        $this->VIS->checkVIS($this->type);

        if ($this->flags & self::VISNODE_ARRAY) {
            $parts = array();
            $delimiter = (isset($this->vars['_DELIM'])) ? $this->vars['_DELIM'] : '';
            $i = 0;
            foreach ($this->vars as $data) {
                if (is_array($data)) {
                    $parts[] = $this->VIS->parseVIS($this->type, $data + array('NODE_INDEX' => ++$i));
                }
            }
            $text = implode($delimiter, $parts);
        } else {
            $vars = $this->vars; // needed not to store duplicates of subnode data while forced reparsing
            $data = array();

            foreach ($this->subs as $var => $subnodes) {
                foreach ($subnodes as $subnode) {
                    /* @var FVISNode $subnode */
                    $vars[$var][] = $subnode->parse($force);
                }
            }

            foreach ($vars as $var => $val) {
                $data[$var] = implode(FStr::ENDL, $val);
            }

            if ($addVars) {
                foreach ($addVars as $var => $val) {
                    $data[$var] = is_array($val)
                        ? FStr::implodeRecursive($val, FStr::ENDL)
                        : $val;
                }
            }

            $text = $this->VIS->parseVIS($this->type, $data);
        }

        $this->parsed =& $text;
        $this->isParsed = true;

        return $text;
    }

    public function sort($varname, $rsort = false, $flags = SORT_REGULAR) // sorting. For array nodes only
    {
        if ($this->flags & self::VISNODE_ARRAY) {
            F2DArray::sort($this->vars, strtoupper($varname), $rsort, $flags);
        }

        return $this;
    }

    public function addData($varname, $data, $replace = false)
    {
        if (!$varname) {
            trigger_error('VIS: no varname given to add data', E_USER_WARNING);
        } elseif ($data instanceof FVISNode) {
            return $this->appendChild($varname, $data, $replace);
        } else {
            $varname = strtoupper($varname);
            if (is_array($data))
                $data = FStr::implodeRecursive($data, ' ');

            if ($this->flags & self::VISNODE_ARRAY)
            {
                foreach ($this->vars as &$varSet) {
                    if (is_array($varSet)) {
                        $varSet[$varname] = $data;
                    }
                }
            } else {
                if ($replace || !isset($this->vars[$varname]))
                    $this->vars[$varname] = array($data);
                else
                    $this->vars[$varname][] = $data;

                if ($replace && isset($this->subs[$varname])) {
                    unset($this->subs[$varname]);
                }
            }
        }

        return $this;
    }

    public function addDataArray(array $data_arr, $prefix = '', $delimiter = '')
    {
        if (empty($data_arr) || !is_string($prefix)) {
           trigger_error('VIS: no data to add', E_USER_WARNING);
        } else { if ($this->flags & self::VISNODE_ARRAY) {
                if (count($data_arr)) {
                    $this->vars = array();
                    $in = 0;

                    foreach ($data_arr as $arr) {
                        if (is_array($arr)) {
                            $this->vars[$in] = array();
                            foreach ($arr as $key => $var) {
                                $key = strtoupper($prefix.$key);
                                if (is_array($var)) {
                                    $var = FStr::implodeRecursive($var, ' ');
                                }
                                $this->vars[$in][$key] = $var;
                            }

                            $this->vars[$in]['_POS'] = $in;

                            $in++;
                        }
                    }

                    $this->vars[0]['_IS_FIRST'] = '1';
                    $this->vars[$in-1]['_IS_LAST'] = '1';
                }

                if (strlen($delimiter)) {
                    $this->vars['_DELIM'] = (string) $delimiter;
                }
            } else {
                foreach ($data_arr as $key => $var) {
                    $key = strtoupper($prefix.$key);
                    if (is_array($var))
                        $var = FStr::implodeRecursive($var, ' ');
                    if (!isset($this->vars[$key]))
                        $this->vars[$key] = array($var);
                    else
                        $this->vars[$key][] = $var;
                }
            }
        }

        return $this;
    }

    /**
     * @param string $template
     * @param string $varname
     * @param array|null $data_arr
     * @param string|null $globname
     * @return FVISNode|null
     */
    public function addNode($template, $varname, array $data_arr = null, $globname = null)
    {
        if (!$varname)
            return null;

        if ($node = $this->VIS->createNode($template, $data_arr, $globname)) {
            if ($this->appendChild($varname, $node)) {
                return $node;
            }
        }

        return null;
    }

    public function appendChild($varname, FVISNode $node, $replace = false)
    {
        if (!$node || !$varname) {
            trigger_error('VIS: no data to add', E_USER_WARNING);
        } else { $varname = strtoupper($varname);
            // TODO: loops checking
            if ($replace || !isset($this->subs[$varname]))
                $this->subs[$varname] = array($node);
            else
                $this->subs[$varname][] = $node;

            if ($replace && isset($this->vars[$varname])) {
                unset($this->vars[$varname]);
            }
        }

        return $this;
    }

    public function clear()
    {
        $this->vars = array();
        $this->subs = array();
        $this->parsed = '';

        return $this;
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

    const VIS_BR = FStr::ENDL;

    // defining some usefull constants
    // VIS resource types
    const VIS_NORMAL =  0;
    const VIS_STATIC =  1;
    const VIS_DINAMIC = 2;

    protected $templates  = array();

    protected $VCSS_data  = ''; // CSS loaded from visuals
    protected $VJS_data   = ''; // JS loaded from visuals
    protected $CSS_data   = '';
    protected $JS_data    = '';
    protected $Consts     = array();

    // nodes arrays
    /**
     * @var FVISNode[]
     */
    protected $nodes      = array();
    protected $named      = array();

    protected $VIS_loaded = array();
    protected $CSS_loaded = false;
    protected $JS_loaded  = array();

    protected $vis_consts = array();
    /** @var callable[] */
    protected $func_parsers = array();

    protected $auto_loads = array();

    protected $cPrefix = '';
    protected $_forceCompact = true;  // forces to compact CSS/JS data
    protected $_rootNodeId = 0;

    protected $env = null;

    public function __construct(K3_Environment $env = null)
    {
        $this->env = is_null($env) ? $env : F()->appEnv;

        $this->nodes[0] = new FVISNode('GLOBAL_HTMLPAGE', 0, $this);
        $this->named = array('PAGE' => 0, 'MAIN' => 0);
        $this->func_parsers = array(
            'FULLURL'   => array('FStr', 'fullUrl'),
            'CAST'      => array('FStr', 'cast'),
            'HTMLQUOTE' => 'htmlspecialchars',
            'SMARTHTMLQUOTE' => array('FStr', 'smartHTMLSchars'),
            'URLENCODE' => array('FStr', 'urlencode'),
            'URLEN'     => array('FStr', 'urlencode'),
            'JS_DEF'    => array('FStr', 'JSDefine'),
            'PHP_DEF'   => array('FStr', 'PHPDefine'),
            'FTIME'     => array(F()->LNG, 'timeFormat'),
            'FBYTES'    => array(F()->LNG, 'sizeFormat'),
            'STRFORMAT' => 'sprintf',
            'NL2BR'     => 'nl2br',
            'RANDOM'    => 'mt_rand',
        );

        $this->clear();
    }

    public function clear($keep_nodes = false)
    {
        $this->templates  = array();
        $this->VCSS_data  = '';
        $this->VJS_data   = '';
        $this->VIS_loaded = array();
        $this->CSS_data   = '';
        $this->JS_data    = '';
        $this->CSS_loaded = false;
        $this->JS_loaded  = array();
        $this->vis_consts = array(
            'FALSE'   => false,
            'TRUE'    => true,
            'NULL'    => null,
            'TIME'    => F()->Timer->qTime(),
            'ROOTURL' => $this->env->server->rootUrl,
            'CAST_HEX'  => FStr::HEX,
            'CAST_HTML' => FStr::HTML,
            'CAST_WORD' => FStr::WORD,
            'CAST_PATH' => FStr::UNIXPATH, // unix always
        );

        if (!$keep_nodes)
        {
            //clearing nodes
            foreach ($this->nodes as $node)
                if ($node)
                    $node->clear();

            array_splice($this->nodes, 1);
            $this->named = array('PAGE' => 0, 'MAIN' => 0);
            $this->_rootNodeId = 0;
        }

        return $this;
    }

    public function setRootNode($node)
    {
        if (!is_int($node))
            $node = $this->findNodeId($node);

        if (!isset($this->nodes[$node]))
            trigger_error('VIS: trying to set a fake node', E_USER_WARNING);
        else
            $this->_rootNodeId = $node;

        return $this;
    }

    public function getRootNode()
    {
        return $this->nodes[$this->_rootNodeId];
    }

    /**
     * @param array $consts
     * @param bool $no_replace
     * @return FVISInterface
     */
    public function setVConsts(array $consts, $no_replace = false)
    {
        $this->vis_consts = ($no_replace)
            ? $this->vis_consts + $consts
            : $consts + $this->vis_consts;

        return $this;
    }

    /**
     * @param string $name
     * @param callable $callback
     * @return FVISInterface
     */
    public function addFuncParser($name, $callback)
    {
        if (!$name || !is_callable($callback))
            trigger_error('VIS: no function use', E_USER_WARNING);
        else
        {
            $name = strtoupper($name);

            if (!isset($this->func_parsers[$name]))
                $this->func_parsers[$name] = $callback;
        }

        return $this;
    }

    /**
     * @param $directory
     * @param string $fileSuffix
     * @return FVISInterface
     */
    public function addAutoLoadDir($directory, $fileSuffix = '.vis')
    {
        $directory = FStr::path($directory);
        $hash = FStr::pathHash($directory);

        if (isset($this->auto_loads[$hash]))
            return $this;

        $cachename = self::VPREFIX.'ald-'.$hash;
        if ($aldata = FCache::get($cachename))
        {
            $this->auto_loads[$hash] = $aldata;
            F()->Timer->logEvent($directory.' autoloads installed (from global cache)');
        }
        else
        {
            if ($dir = opendir($directory))
            {
                $aldata = array(0 => $directory);
                $preg_pattern = '#'.preg_quote($fileSuffix, '#').'$#';
                while ($entry = readdir($dir))
                {
                    $filename = $directory.DIRECTORY_SEPARATOR.$entry;
                    if (preg_match($preg_pattern, $entry) && is_file($filename) && $datas = FMisc::loadDatafile($filename, FMisc::DF_BLOCK, true))
                    {
                        $datas = array_keys($datas);
                        foreach ($datas as $key)
                            $aldata[$key] = $entry;
                    }
                }
                closedir($dir);

                ksort($aldata);
                FCache::set($cachename, $aldata);
                $this->auto_loads[$hash] = $aldata;
                F()->Timer->logEvent($directory.' autoloads installed (from filesystem)');
            }
            else
                trigger_error('VIS: error installing '.$directory.' auto loading directory', E_USER_WARNING );
        }

        return $this;
    }

    public function loadECSS($filename)
    {
        $hash = FStr::pathHash($filename);
        $cachename = self::CPREFIX.$this->cPrefix.F()->LNG->ask().'.'.$hash;

        if ($Cdata = FCache::get($cachename))
        {
            $this->CSS_data = $Cdata;
            F()->Timer->logEvent($filename.' CSS file loaded (from global cache)');
        }
        else
        {
            if ($indata = FMisc::loadDatafile($filename))
            {
                $Cdata = $this->prepareECSS($indata, $this->vis_consts);

                FCache::set($cachename, $Cdata);
                $this->CSS_data = $Cdata;
                F()->Timer->logEvent($filename.' CSS file loaded (from ECSS file)');
            }
            else
                trigger_error('VIS: error loading '.$filename.' ECSS file', E_USER_WARNING );
        }

        $this->CSS_loaded = $hash;

        return $this;
    }

    public function loadEJS($filename)
    {
        $hash = FStr::pathHash($filename);

        if (!in_array($hash, $this->JS_loaded))
        {
            $cachename = self::JPREFIX.$this->cPrefix.F()->LNG->ask().'.'.$hash;

            if ($JSData = FCache::get($cachename))
            {
                $this->JS_data.= FStr::ENDL.$JSData;

                F()->Timer->logEvent('"'.$filename.'" JScript loaded (from global cache)');
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
                    F()->Timer->logEvent('"'.$filename.'" JScript loaded (from EJS file)');
                }
                else
                    trigger_error('VIS: error loading "'.$filename.'" EJS file', E_USER_WARNING );
            }

            $this->JS_loaded[] = $hash;
        }

        return $this;
    }

    public function loadTemplates($filename)
    {
        $hash = FStr::pathHash($filename);

        if (!in_array($hash, $this->VIS_loaded))
        {
            $cachename = self::VPREFIX.$this->cPrefix.F()->LNG->ask().'.'.$hash;

            if (list($Tdata, $VCSS, $VJS) = FCache::get($cachename))
            {
                $this->templates += $Tdata;
                $this->VCSS_data .= FStr::ENDL.$VCSS;
                $this->VJS_data  .= FStr::ENDL.$VJS;

                F()->Timer->logEvent('"'.$filename.'" visuals loaded (from global cache)');
            }
            else
            {
                if (!file_exists($filename))
                {
                    trigger_error('VIS: there is no '.$filename.' VIS file', E_USER_WARNING );
                }
                elseif ($indata = FMisc::loadDatafile($filename, FMisc::DF_BLOCK, true))
                {
                    $this->throwEventRef('VIS_PreParse', $indata, $filename);

                    $Tdata  = array();
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

                    FCache::set($cachename, array($Tdata, $VCSS, $VJS) );
                    F()->Timer->logEvent('"'.$filename.'" visuals loaded (from VIS file)');
                }
                else
                    trigger_error('VIS: error loading or parsing "'.$filename.'" VIS file', E_USER_WARNING );
            }

            $this->VIS_loaded[] = $hash;
        }

        return $this;
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
        $vars = array(
            'CSS' => array(&$this->CSS_data, &$this->VCSS_data),
            'JS'  => array(&$this->JS_data,  &$this->VJS_data),
            );

        return $this->nodes[$this->_rootNodeId]->parse($force_reparse, $vars);
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
    public function createNode($template, array $data_arr = null, $globname = null)
    {
        $template = (string) $template;
        if (!$template)
            return false;

        end($this->nodes);
        $id = key($this->nodes) + 1;

        $this->nodes[$id] = new FVISNode($template, 0, $this);

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

    /**
     * @param string $template
     * @param string $varname
     * @param FVISNode|int|string $parent
     * @param array|null $data_arr
     * @param string|null $globname
     * @return FVISNode|null
     */
    public function addNode($template, $varname, $parent = 0, array $data_arr = null, $globname = null)
    {
        if (!$varname)
            return null;

        $parent = $this->findNode($parent);

        if (!$parent)
        {
            trigger_error('VIS: trying to append data to fake node', E_USER_WARNING);
            return null;
        }

        if ($node = $this->createNode($template, $data_arr, $globname))
            if ($parent->appendChild($varname, $node))
                return $node;

        return null;
    }

    /**
     * Adds arrayed node
     * @param string $template
     * @param string $varname
     * @param FVISNode|int|string $parent
     * @param array|null $data_arr
     * @param string $delimiter
     * @return FVISNode|null
     */
    public function addNodeArray($template, $varname, $parent = 0, array $data_arr = null, $delimiter = '')
    {
        $parent = $this->findNode($parent);

        if (!$varname)
            return null;

        if (!$parent)
        {
            trigger_error('VIS: trying to append node to fake node', E_USER_WARNING);
            return null;
        }

        $template = (string) $template;
        if (!$template)
            return null;

        end($this->nodes);
        $id = key($this->nodes) + 1;

        if ($this->nodes[$id] = new FVISNode($template, FVISNode::VISNODE_ARRAY, $this))
        {
            $parent->appendChild($varname, $this->nodes[$id]);

            $this->nodes[$id]->addDataArray($data_arr, '', $delimiter);

            return $this->nodes[$id];
        }

        return null;
    }

    public function addData($node, $varname, $data, $replace = false)
    {
        $node = $this->findNode($node);

        if (!$node) {
            trigger_error('VIS: trying to append data to fake node', E_USER_WARNING);
            return false;
        }

        return $node->addData($varname, $data, $replace);
    }

    public function addDataArray($node, $arr, $prefix = '')
    {
        $node = $this->findNode($node);

        if (!$node) {
            trigger_error('VIS: trying to append data to fake node', E_USER_WARNING);
            return false;
        }

        return $node->addDataArray($arr, $prefix);
    }

    public function findNodeId($to_find)
    {
        if (!$to_find)
            return 0;

        if ($to_find instanceof FVISNode) {
            if (!in_array($to_find, $this->nodes, true))
                array_push($this->nodes, $to_find);
            list($to_find) = array_keys($this->nodes, $to_find, true);
        } elseif (!is_numeric($to_find)) {
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
            if (!in_array($to_find, $this->nodes, true))
                array_push($this->nodes, $to_find);
            return $to_find;
        }
        elseif (is_numeric($to_find))
        {
            $to_find = (int) $to_find;
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
        /*static $consts = array(
            'F_MARK'  => 'Powered by<br />Kernel 3<br />&copy; Foxel aka LION<br /> 2006 - 2009',
            'F_INDEX' => F_SITE_INDEX,
            );*/

        $consts = $this->vis_consts;

        $text = trim($text);

        if ($this->_forceCompact)
            $text = $this->compactHTML($text);

        $text = $this->templLang($text);

        $text = preg_replace('#(?<=\})\r?\n\s*?(?=\{\w)#', '', $text);
        preg_match_all('#\{([\!\/]?)((?>\w+))(?:\:((?:(?>-?[0-9]+|\w+|\"[^\"]*\")(?:[\!=\>\<]{1,2}(?:-?[0-9]+|\w+|\"[^\"]*\"))?|\|)*))?\}|[^\{]+|\{#', $text, $struct, PREG_SET_ORDER);

        $writes_to = 'OUT';
        $text = '$'.$writes_to.' = <<<FTEXT'.FStr::ENDL;
        $jstext = 'v.'.$writes_to.' = "';
        $vars = array();

        $iflevel = 0;
        $outiflevel = 0;
        $in_for = false;

        $keys = array_keys($struct);

        foreach ($keys as $key)
        {
            $part =& $struct[$key];

            if (isset($part[2]) && ($tag = strtoupper($part[2])))
            {
                $got_a = ($part[1] == '!') ? true : false;
                if ($part[1] == '/')
                    $tag = '/'.$tag;

                $params = array();
                if (isset($part[3])) {
                    preg_match_all('#((?>-?[0-9]+|\w+|\"[^\"]*\"))(?:([\!=\>\<]{1,2})(-?[0-9]+|\w+|\"[^\"]*\"))?#', $part[3], $params, PREG_PATTERN_ORDER);
                }

                if ($tag == 'WRITE')
                {
                    if (isset($params[1]) && count($params[1]) && ($var = $params[1][0]) && FStr::isWord($var))
                        $var = strtoupper($var);
                    else
                        $var = 'OUT';
                    if ($var != $writes_to)
                    {
                        $writes_to = $var;
                        $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL.'$'.$writes_to.(($got_a) ? '' : '.').'= <<<FTEXT'.FStr::ENDL;
                        $jstext.= '";'.FStr::ENDL.'v.'.$writes_to.(($got_a) ? '' : '+').'= "';
                    }
                }
                elseif (isset($this->func_parsers[$tag])) //parsing the variable with func
                {
                    if (!isset($params[1]) || !count($params[1]))
                        continue;

                    $text.= FStr::ENDL.'FTEXT'.FStr::ENDL.'.';
                    $jstext.= '"'.FStr::ENDL.'+';
                    $text.= $this->templVISFuncCB($tag, $params[1], $vars, $consts, false, $got_a);
                    $jstext.= $this->templVISFuncCB($tag, $params[1], $vars, $consts, true, $got_a);
                    $text.= '.<<<FTEXT'.FStr::ENDL;
                    $jstext.= '+"';
                }
                elseif ($tag == 'SET')
                {
                    if ($pars = count($params[1]))
                    {
                        $sets = $jssets = '';
                        for($i = 0; $i < $pars; ++$i)
                        {
                            $var = $params[1][$i];
                            if (!FStr::isWord($var) || !isset($params[3][$i]) || !strlen($params[3][$i]))
                                continue;
                            $var = strtoupper($var);
                            $val = $params[3][$i];
                            $sets.= '$'.$var.' = '.$this->templVISParamCB($val, $vars, $consts).';';
                            $jssets.= 'v.'.$var.' = '.$this->templVISParamCB($val, $vars, $consts, false, true).';'.FStr::ENDL;
                        }

                        if ($sets)
                        {
                            $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL.$sets.FStr::ENDL.'$'.$writes_to.'.= <<<FTEXT'.FStr::ENDL;
                            $jstext.= '";'.FStr::ENDL.$sets.'v.'.$writes_to.'+= "';
                        }
                    }
                }
                elseif ($tag == 'FOR')
                {
                    if (!isset($params[1]) || !count($params[1]) || $in_for)
                        continue;
                    $params = $params[1];
                    $p1 = array_shift($params);
                    $p2 = array_shift($params);
                    $p3 = array_shift($params);

                    $in_for = true;
                    $outiflevel = $iflevel;
                    $iflevel = 0;

                    $pp1 = (is_numeric($p1)) ? intval($p1) : $p1;
                    if ($p2)
                        $pp2 = (is_numeric($p2)) ? intval($p2) : $p2;
                    else
                    {
                        $pp2 = $p2 = $p1;
                        $pp1 = $p1 = '0';
                    }
                    if ($p3)
                        $pp3 = (!FStr::isWord($p3)) ? intval($p3) : $p3;
                    else
                        $pp3 = $p3 = '1';

                    $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL.'for ($I = '.$this->templVISParamCB($pp1, $vars, $consts).'; $I <= '.$this->templVISParamCB($pp2, $vars, $consts).'; $I+= '.$this->templVISParamCB($pp3, $vars, $consts).') {'.FStr::ENDL.'$'.$writes_to.'.= <<<FTEXT'.FStr::ENDL;
                    $jstext.= '";'.FStr::ENDL.'for (v.I = '.$this->templVISParamCB($p1, $vars, $consts, false, true).'; v.I <= '.$this->templVISParamCB($p2, $vars, $consts, false, true).'; v.I+= '.$this->templVISParamCB($p3, $vars, $consts, false, true).') {'.FStr::ENDL.'v.'.$writes_to.'+= "';
                }
                elseif ($tag == 'ENDFOR' || $tag == '/FOR')
                {
                    if ($in_for)
                    {
                        $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL;
                        $jstext.= '";'.FStr::ENDL;
                        $in_for = false;
                        $text.= str_repeat('} ', $iflevel);
                        $jstext.= str_repeat('} ', $iflevel);
                        $text.= FStr::ENDL.'}'.FStr::ENDL.'$'.$writes_to.'.= <<<FTEXT'.FStr::ENDL;
                        $jstext.= FStr::ENDL.'}'.FStr::ENDL.'v.'.$writes_to.'.= "';
                        $iflevel = $outiflevel;
                    }
                }
                elseif ($tag == 'VIS') // no JS parsing for now
                {
                    if (!isset($params[1]) || !count($params[1]))
                        continue;
                    $visname = $params[1][0];
                    $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL.'$'.$writes_to.(($got_a) ? '' : '.').'= $_vis->parseVIS(\''.$visname.'\'';
                    if (count($params[1]) > 1)
                    {
                        $passMyParams = false;
                        if (end($params[1]) == '_') {
                            $passMyParams = true;
                            array_pop($params[1]);
                        }

                        if (count($params[1]) > 1) {
                            $text.= ', array(';
                            $pars = count($params[1]);
                            for($i = 1; $i < $pars; ++$i)
                            {
                                $var = $params[1][$i];
                                if (!FStr::isWord($var))
                                    continue;
                                $var = strtoupper($var);
                                $val = (isset($params[3][$i]) && strlen($params[3][$i])) ? $params[3][$i] : '1';
                                $text.= '\''.$var.'\' => '.$this->templVISParamCB($val, $vars, $consts).',';
                            }
                            $text.= ') ';
                            if ($passMyParams) {
                                $text.= '+ $_in';
                            }
                        } elseif ($passMyParams) {
                            $text.= ', $_in';
                        }
                    }
                    $text.= ');'.FStr::ENDL.'$'.$writes_to.'.= <<<FTEXT'.FStr::ENDL;
                }
                elseif ($tag == 'IF' || $tag == 'ELSEIF')
                {
                    if (!isset($params[1]) || !count($params[1]))
                        continue;
                    $var = $params[1][0];
                    if (isset($params[3][0]) && strlen($params[3][0]))
                    {
                        $condvar = $params[3][0];
                        $condition = $params[2][0];
                        $condition = (in_array($condition, array('>', '<', '>=', '<=', '!=')))
                            ? ' '.$condition.' '
                            : ' == ';

                        $condition = '('.$this->templVISParamCB($var, $vars, $consts).$condition.$this->templVISParamCB($condvar, $vars, $consts).')';
                        $jscondition = '('.$this->templVISParamCB($var, $vars, $consts, false, true).$condition.$this->templVISParamCB($condvar, $vars, $consts, false, true).')';
                    }
                    else
                    {
                        $condition = 'strlen('.$this->templVISParamCB($var, $vars, $consts).')';
                        $jscondition = '('.$this->templVISParamCB($var, $vars, $consts, false, true).'.length)';
                    }

                    if ($got_a)
                    {
                        $condition = '!'.$condition;
                        $jscondition = '!'.$jscondition;
                    }

                    if ($tag == 'IF')
                    {
                        $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL.'if ('.$condition.') {'.FStr::ENDL.'$'.$writes_to.'.= <<<FTEXT'.FStr::ENDL;
                        $jstext.= '";'.FStr::ENDL.'if ('.$jscondition.') {'.FStr::ENDL.'v.'.$writes_to.'+= "';
                        $iflevel++;
                    }
                    elseif ($iflevel)
                    {
                        $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL.'} elseif('.$condition.') {'.FStr::ENDL.'$'.$writes_to.'.= <<<FTEXT'.FStr::ENDL;
                        $jstext.= '";}'.FStr::ENDL.'else if ('.$jscondition.') {'.FStr::ENDL.'v.'.$writes_to.'+= "';
                    }
                }
                elseif ($tag == 'ELSE')
                {
                    if ($iflevel)
                    {
                        $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL.'} elseif(true) {'.FStr::ENDL.'$'.$writes_to.'.= <<<FTEXT'.FStr::ENDL;
                        $jstext.= '";}'.FStr::ENDL.'else if (true) {'.FStr::ENDL.'v.'.$writes_to.'+= "';
                    }
                }
                elseif ($tag == 'ENDIF' || $tag == '/IF')
                {
                    if ($iflevel)
                    {
                        $text.= FStr::ENDL.'FTEXT;'.FStr::ENDL.'}'.FStr::ENDL.'$'.$writes_to.'.= <<<FTEXT'.FStr::ENDL;
                        $jstext.= '";}'.FStr::ENDL.'v.'.$writes_to.'+= "';
                        $iflevel--;
                    }
                }
                else
                {
                    $varname = strtoupper($part[2]);
                    if (isset($consts[$varname]))
                    {
                        $text.= FStr::addslashesHeredoc($consts[$varname], 'FTEXT');
                        $jstext.= FStr::addslashesJS($consts[$varname]);
                    }
                    elseif (FStr::isWord($varname))
                    {
                        $vars[$varname] = '';
                        if ($got_a)
                        {
                            $text.= FStr::ENDL.'FTEXT'.FStr::ENDL.'.FStr::smartHTMLSchars($'.$varname.').<<<FTEXT'.FStr::ENDL;
                            $jstext.= '"+FStr.smartHTMLSchars(v.'.$varname.')+"';
                        }
                        else
                        {
                            $text.= '{$'.$varname.'}';
                            $jstext.= '"+v.'.$varname.'+"';
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
        $text = preg_replace('#\r?\nFTEXT\s*\.\s*\<\<\<FTEXT\r?\n#', '', $text);
        $jstext = preg_replace('#\w+\+\=\s*"\s+";#', '', $jstext);
        $out = array('T' => $text, 'V' => $vars, 'J' => $jstext);
        if ($store_to && is_string($store_to))
            $this->templates[$store_to] = $out;
        return $out;
    }

    public function checkVIS($vis)
    {
        $vis = strtoupper($vis);

        return (isset($this->templates[$vis]) || $this->tryAutoLoad($vis));
    }

    public function parseVIS($vis, array $data = array())
    {
        //Static $__COUNTER=1;
        /* Static $F_SID;
        if (!$F_SID)
            $F_SID = F()->Session->SID; */

        /* $COUNTER = $__COUNTER++;
        $RANDOM = dechex(rand(0x1FFF, getrandmax())); */

        $vis = strtoupper($vis);
        if (!isset($this->templates[$vis]) && !$this->tryAutoLoad($vis))
            return implode(FStr::ENDL, $data);

        if (!isset($this->templates[$vis]['F']))
            $this->templates[$vis]['F'] = create_function(
                '&$_vis, &$_v, &$_in, &$_c',
                'if (extract($_v, EXTR_SKIP)) {
                extract($_in, EXTR_REFS | EXTR_OVERWRITE | EXTR_PREFIX_ALL, \'IN\');
                extract($_c, EXTR_REFS | EXTR_OVERWRITE | EXTR_PREFIX_ALL, \'C\'); }
                $UNIQID = dechex(mt_rand(0x1FFF, getrandmax()));
                $OUT = \'\';
                '.$this->templates[$vis]['T'].' return $OUT;');

        return call_user_func_array($this->templates[$vis]['F'], array(&$this, &$this->templates[$vis]['V'], &$data, &$this->vis_consts));
    }

    public function prepJSFunc($vis)
    {
        if (!isset($this->templates[$vis]) && !$this->tryAutoLoad($vis))
            return '';

        $body = 'v = '.FStr::JSDefine($this->templates[$vis]['V']).';'.FStr::ENDL
            .'for (var i in FVIS.consts) v[i] = FVIS.consts[i];'.FStr::ENDL
            .'for (var i in data) v[i] = data[i];'.FStr::ENDL
            .$this->templates[$vis]['J'].FStr::ENDL
            .'return v.OUT';

        return 'function(data) {'.FStr::ENDL.$body.FStr::ENDL.'}';
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

        if ($this->_forceCompact || $force_compact)
            $Cdata = $this->compactCSS($Cdata);

        $Cdata = preg_replace('#\{(?>(\w+))\}#e', '(isset(\$CSSVars[strtoupper("\1")])) ? \$CSSVars[strtoupper("\1")] : ""', $Cdata);

        return $Cdata;
    }

    public function prepareEJS($Jdata, $constants = null, $force_compact = false)
    {
        if (is_array($constants))
            foreach ($constants as $name=>$val)
                $Jdata = str_replace('{'.$name.'}', $val, $Jdata);
        if ($this->_forceCompact || $force_compact)
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
    {
        $loads = end($this->auto_loads);
        do {
            if (isset($loads[$vis]))
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

            $data = F()->LNG->lang($lng, $params);
        }
        else
            $data = F()->LNG->lang($lng);

        return $data;
    }

    private function templVISFuncCB($parsewith, array $params, &$vars, $consts, $for_js = false, $do_schars = false) // calling function with many params
    {
        $code = null;
        $static = strpos($parsewith, 'RAND') === false;

        $st_pars = $dyn_pars_js = $dyn_pars = array();
        foreach ($params as $id => $val)
        {
            $this_static = true;
            if (FStr::isWord($val))
            {
                $val = strtoupper($val);
                if (substr($val, 0, 2) == 'L_')
                    $val = $this->templLangCB(substr($val, 2));
                elseif (isset($consts[$val]))
                    $val = $consts[$val];
                else
                {
                    $vars[$val] = '';
                    $static = $this_static = false;
                }
                $st_pars[$id]  = $val;
                $dyn_pars[$id] = ($this_static)
                    ? (is_bool($val) || is_null($val) ? FStr::PHPDefine($val) : FStr::heredocDefine($val, 'FTEXT'))
                    : '$'.$val;
                $dyn_pars_js[$id] = ($this_static) ? FStr::JSDefine($val) : 'v.'.$val;
            }
            elseif ($val[0] == '"')
            {
                $val = substr($val, 1, -1);
                $st_pars[$id]  = $val;
                $dyn_pars[$id] = FStr::heredocDefine($val, 'FTEXT');
                $dyn_pars_js[$id] = FStr::JSDefine($val);
            } else {
                if (is_numeric($val) && $val[0] != '0') {
                    $val = intval($val);
                }
                $st_pars[$id] = $dyn_pars[$id] = $dyn_pars_js[$id] = $val;
            }
        }

        if ($static)
        {
            $val = (string) $this->callParseFunctionArr($parsewith, $st_pars);
            if ($do_schars)
                $val = FStr::smartHTMLSchars($val);
            $code = $for_js ? FStr::JSDefine($val) : FStr::heredocDefine($val, 'FTEXT');
        }
        else
        {
            $code = $for_js
                ? 'FVIS.callParseFunctionArr(\''.$parsewith.'\', ['.implode(', ', $dyn_pars_js).'])'
                : '$_vis->callParseFunctionArr(\''.$parsewith.'\', array('.implode(', ', $dyn_pars).'))';
            if ($do_schars)
                $code = $for_js
                    ? 'FStr.smartHTMLSchars('.$code.')'
                    : 'FStr::smartHTMLSchars('.$code.')';
        }

        return $code;
    }

    private function templVISParamCB($val, &$vars, $consts, $parsewith = null, $for_js = false, $do_schars = false)
    {
        $code = '';
        $static = strpos($parsewith, 'RAND') === false;

        if (FStr::isWord($val))
        {
            $val = strtoupper($val);
            if (substr($val, 0, 2) == 'L_')
                $val = $this->templLangCB(substr($val, 2));
            elseif (isset($consts[$val]))
                $val = $consts[$val];
            else
            {
                $vars[$val] = '';
                $static = false;
                $code = ($for_js)
                    ? 'v.'.$val
                    : '$'.$val;
            }
        }
        elseif (is_numeric($val) && $val[0] != '0')
            $val = intval($val);
        elseif ($val[0] == '"')
            $val = substr($val, 1, -1);

        if ($static)
        {
            if ($parsewith)
                $val = $this->callParseFunction($parsewith, $val);
            if ($do_schars)
                $val = FStr::smartHTMLSchars($val);
            $code = $for_js ? FStr::JSDefine($val) : FStr::heredocDefine($val, 'FTEXT');
        }
        elseif ($parsewith)
        {
            $code = $for_js
                ? 'FVIS.callParseFunction(\''.$parsewith.'\', v.'.$val.')'
                : '$_vis->callParseFunction(\''.$parsewith.'\', $'.$val.')';
            if ($do_schars)
                $code = $for_js
                    ? 'FStr.smartHTMLSchars('.$code.')'
                    : 'FStr::smartHTMLSchars('.$code.')';
        }

        return $code;
    }

    public function callParseFunction($func_name, $data) //parsing data with funcParser
    {
        if (!isset($this->func_parsers[$func_name]))
            return $data;

        $func_parser = $this->func_parsers[$func_name];
        return call_user_func($func_parser, $data);
    }

    public function callParseFunctionArr($func_name, $data) //parsing data with funcParser
    {
        if (!isset($this->func_parsers[$func_name]))
            return '';

        $func_parser = $this->func_parsers[$func_name];
        return call_user_func_array($func_parser, $data);
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


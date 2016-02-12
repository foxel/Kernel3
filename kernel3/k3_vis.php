<?php
/**
 * Copyright (C) 2010 - 2012, 2014 - 2016 Andrey F. Kupreychik (Foxel)
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

define('K3_VIS_TEMPLATE_REGEXP_PARAM_VALUE', '-?[0-9]+|\w+|\"(?:\"\"|[^\"])*\"');
define('K3_VIS_TEMPLATE_REGEXP_PARAM', '((?>'.K3_VIS_TEMPLATE_REGEXP_PARAM_VALUE.'))(?:([\!=\>\<]{1,2})('.K3_VIS_TEMPLATE_REGEXP_PARAM_VALUE.'))?');
define('K3_VIS_TEMPLATE_REGEXP_CALL', '\{([\!\/]?)((?>\w+))(?:\:((?:'.K3_VIS_TEMPLATE_REGEXP_PARAM.'|\|)*))?\}');
define('K3_VIS_TEMPLATE_REGEXP', K3_VIS_TEMPLATE_REGEXP_CALL.'|[^\{]+|\{');

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
                $data[$var] = implode(K3_String::EOL, $val);
            }

            if ($addVars) {
                foreach ($addVars as $var => $val) {
                    $data[$var] = is_array($val)
                        ? K3_Util_Array::implodeRecursive(PHP_EOL, $val)
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
                $data = K3_Util_Array::implodeRecursive(' ', $data);

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
                                    $var = K3_Util_Array::implodeRecursive(' ', $var);
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
                        $var = K3_Util_Array::implodeRecursive(' ', $var);
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

/**
 * Class FVISInterface
 * @author Andrey F. Kupreychik
 */
class FVISInterface extends FEventDispatcher
{
    // Cache prefixes for module data
    const VPREFIX = 'VIS.';
    const CPREFIX = 'VIS_CSS.';
    const JPREFIX = 'VIS_JS.';

    const VIS_BR = K3_String::EOL;

    // defining some usefull constants
    // VIS resource types
    const VIS_NORMAL =  0;
    const VIS_STATIC =  1;
    const VIS_DINAMIC = 2;

    const EVENT_EJS_PRE_PARSE = 'EJS_PreParse';
    const EVENT_VIS_PRE_PARSE = 'VIS_PreParse';

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

    protected $_constants = array();
    protected $_globals = array();

    /** @var callable[] */
    protected $_funcParsers = array();

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
        $this->_funcParsers = array(
            'FULLURL'   => array('K3_Util_Url', 'fullUrl', $this->env),
            'CAST'      => array('K3_Util_String', 'filter'),
            'HTMLQUOTE' => array('K3_Util_String', 'escapeXML'),
            'SMARTHTMLQUOTE' => array('K3_Util_String', 'escapeXML'), //deprecated
            'URLENCODE' => array('K3_Util_Url', 'urlencode'),
            'URLEN'     => array('K3_Util_Url', 'urlencode'),
            'JS_DEF'    => array('K3_Util_Value', 'defineJSON'),
            'PHP_DEF'   => array('K3_Util_Value', 'definePHP'),
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
        $this->_constants = array(
            'FALSE'   => false,
            'TRUE'    => true,
            'NULL'    => null,
            'TIME'    => $this->env->clock->startTime,
            'ROOTURL' => $this->env->server->rootUrl,
            'CAST_HEX'  => K3_Util_String::FILTER_HEX,
            'CAST_HTML' => K3_Util_String::FILTER_HTML,
            'CAST_WORD' => K3_Util_String::FILTER_WORD,
            'CAST_PATH' => K3_Util_String::FILTER_PATH_UNIX, // unix always
        );
        $this->_globals = array();

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
     * @param array $constants
     * @param bool $noReplace
     * @return FVISInterface
     */
    public function setVConsts(array $constants, $noReplace = false)
    {
        $this->_constants = ($noReplace)
            ? $this->_constants + $constants
            : $constants + $this->_constants;

        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     * @return FVISInterface
     */
    public function addGlobal($name, $value)
    {
        $name = strtoupper($name);

        $this->_globals[$name] = K3_Util_Array::implodeRecursive(' ', $value);

        return $this;
    }

    /**
     * @param string[] $values
     * @param string $prefix
     * @return FVISInterface
     */
    public function addGlobalArray(array $values, $prefix = '')
    {
        foreach ($values as $key => $value) {
            $key = strtoupper($prefix.$key);
            $value = K3_Util_Array::implodeRecursive(' ', $value);
            $this->_globals[$key] = $value;
        }

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

            if (!isset($this->_funcParsers[$name])) {
                $this->_funcParsers[$name] = $callback;
            }
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
        $directory = K3_Util_String::filter($directory, K3_Util_String::FILTER_PATH);
        $hash      = K3_Util_File::pathHash($directory);

        if (isset($this->auto_loads[$hash]))
            return $this;

        $cacheName = self::VPREFIX.'ald-'.$hash;
        if ($ALData = F()->Cache->get($cacheName, filemtime($directory)))
        {
            $this->auto_loads[$hash] = $ALData;
            F()->Profiler->logEvent($directory.' autoload installed (from global cache)');
        }
        else
        {
            if ($dir = opendir($directory))
            {
                $ALData = array(0 => $directory);
                $preg_pattern = '#'.preg_quote($fileSuffix, '#').'$#';
                while ($entry = readdir($dir))
                {
                    $filename = $directory.DIRECTORY_SEPARATOR.$entry;
                    if (preg_match($preg_pattern, $entry) && is_file($filename) && $data = FMisc::loadDatafile($filename, FMisc::DF_BLOCK, true)) {
                        $keys = array_keys($data);
                        foreach ($keys as $key) {
                            $ALData[$key] = $entry;
                        }
                    }
                }
                closedir($dir);

                ksort($ALData);
                F()->Cache->set($cacheName, $ALData);
                $this->auto_loads[$hash] = $ALData;
                F()->Profiler->logEvent($directory.' autoload installed (from filesystem)');
            }
            else
                trigger_error('VIS: error installing '.$directory.' auto loading directory', E_USER_WARNING );
        }

        return $this;
    }

    /**
     * @param string $filename
     * @return $this
     */
    public function loadECSS($filename)
    {
        $hash = K3_Util_File::pathHash($filename);
        $cacheName = self::CPREFIX.$this->cPrefix.F()->LNG->ask().'.'.$hash;

        if ($Cdata = F()->Cache->get($cacheName, filemtime($filename)))
        {
            $this->CSS_data = $Cdata;
            F()->Profiler->logEvent($filename.' CSS file loaded (from global cache)');
        } else {
            if ($inData = FMisc::loadDatafile($filename)) {
                $Cdata = $this->prepareECSS($inData, $this->_constants);

                F()->Cache->set($cacheName, $Cdata);
                $this->CSS_data = $Cdata;
                F()->Profiler->logEvent($filename.' CSS file loaded (from ECSS file)');
            } else {
                trigger_error('VIS: error loading '.$filename.' ECSS file', E_USER_WARNING );
            }
        }

        $this->CSS_loaded = $hash;

        return $this;
    }

    /**
     * @param string $filename
     * @return $this
     */
    public function loadEJS($filename)
    {
        $hash = K3_Util_File::pathHash($filename);

        if (!in_array($hash, $this->JS_loaded))
        {
            $cacheName = self::JPREFIX.$this->cPrefix.F()->LNG->ask().'.'.$hash;

            if ($JSData = F()->Cache->get($cacheName, filemtime($filename)))
            {
                $this->JS_data.= K3_String::EOL.$JSData;

                F()->Profiler->logEvent('"'.$filename.'" JScript loaded (from global cache)');
            }
            else
            {
                if (!file_exists($filename))
                {
                    trigger_error('VIS: there is no '.$filename.' EJS file', E_USER_WARNING );
                }
                elseif ($inData = FMisc::loadDatafile($filename))
                {
                    $this->throwEventRef(self::EVENT_EJS_PRE_PARSE, $inData);

                    $JSData = $this->prepareEJS($inData, $this->_constants);
                    $this->JS_data.= K3_String::EOL.$JSData;

                    F()->Cache->set($cacheName, $JSData);
                    F()->Profiler->logEvent('"'.$filename.'" JScript loaded (from EJS file)');
                }
                else
                    trigger_error('VIS: error loading "'.$filename.'" EJS file', E_USER_WARNING );
            }

            $this->JS_loaded[] = $hash;
        }

        return $this;
    }

    /**
     * @param string $filename
     * @return $this
     */
    public function loadTemplates($filename)
    {
        $hash = K3_Util_File::pathHash($filename);

        if (!in_array($hash, $this->VIS_loaded))
        {
            $cacheName = self::VPREFIX.$this->cPrefix.F()->LNG->ask().'.'.$hash;

            if (list($templates, $VCSS, $VJS) = F()->Cache->get($cacheName, filemtime($filename)))
            {
                $this->templates += $templates;
                $this->VCSS_data .= K3_String::EOL.$VCSS;
                $this->VJS_data  .= K3_String::EOL.$VJS;

                F()->Profiler->logEvent('"'.$filename.'" visuals loaded (from global cache)');
            }
            else
            {
                if (!file_exists($filename))
                {
                    trigger_error('VIS: there is no '.$filename.' VIS file', E_USER_WARNING );
                }
                elseif ($indata = FMisc::loadDatafile($filename, FMisc::DF_BLOCK, true))
                {
                    $this->throwEventRef(self::EVENT_VIS_PRE_PARSE, $indata, $filename);

                    $templates  = array();
                    $VCSS       = '';
                    $VJS        = '';
                    foreach ($indata as $name => $templ)
                    {
                        if ($name == 'CSS')
                            $VCSS.= $this->prepareECSS($templ, $this->_constants);
                        elseif ($name == 'JS')
                            $VJS.= $templ; // EJS can contain {V_ links
                                           // so we need to store it first and parse after VIS loading
                        else // normal VIS
                            $templates[$name] = $this->prepareVIS($templ);
                    }

                    $this->templates += $templates;
                    $this->VCSS_data .= K3_String::EOL.$VCSS;
                    $VJS = $this->prepareEJS($VJS, $this->_constants); // and here we actually parse EJS
                    $this->VJS_data  .= K3_String::EOL.$VJS;

                    F()->Cache->set($cacheName, array($templates, $VCSS, $VJS) );
                    F()->Profiler->logEvent('"'.$filename.'" visuals loaded (from VIS file)');
                }
                else
                    trigger_error('VIS: error loading or parsing "'.$filename.'" VIS file', E_USER_WARNING );
            }

            $this->VIS_loaded[] = $hash;
        }

        return $this;
    }

    /*********************\
     * parsing functions *
    \*********************/

    /**
     * @param int $node
     * @param bool $forceParse
     * @return bool|mixed|null|string
     */
    public function parse($node = 0, $forceParse = false)
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

        return $this->nodes[$node]->parse($forceParse);
    }

    /**
     * @param bool $forceParse
     * @return mixed|null|string
     */
    public function makeHTML($forceParse = false)
    {
        $vars = array(
            'CSS' => array(&$this->CSS_data, &$this->VCSS_data),
            'JS'  => array(&$this->JS_data,  &$this->VJS_data),
            );

        return $this->nodes[$this->_rootNodeId]->parse($forceParse, $vars);
    }

    /**
     * @return bool|string
     */
    public function makeCSS()
    {
        return ($this->CSS_loaded) ? trim($this->CSS_data) : false;
    }

    /**
     * @return bool|string
     */
    public function makeJS()
    {
        return ($this->JS_loaded) ? trim($this->JS_data) : false;
    }

    // node tree construction functions
    /**
     * @param string $template
     * @param array $data_arr
     * @param string $globname
     * @return bool|FVISNode
     */
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
    const TEMPLATE_REGEXP_PARAM_VALUE = K3_VIS_TEMPLATE_REGEXP_PARAM_VALUE;
    const TEMPLATE_REGEXP_PARAM       = K3_VIS_TEMPLATE_REGEXP_PARAM;
    const TEMPLATE_REGEXP_CALL        = K3_VIS_TEMPLATE_REGEXP_CALL;
    const TEMPLATE_REGEXP             = K3_VIS_TEMPLATE_REGEXP;

    public function prepareVIS($text, $store_to = false)
    {
        $consts = $this->_constants;

        $text = trim($text);

        if ($this->_forceCompact)
            $text = $this->compactHTML($text);

        $text = $this->_templateReplaceLang($text);

        $text = preg_replace('#(?<=\})\r?\n\s*?(?=\{\w)#', '', $text);
        preg_match_all('#'.self::TEMPLATE_REGEXP.'#', $text, $struct, PREG_SET_ORDER);

        $writes_to = 'OUT';
        $text = '$'.$writes_to.' = <<<FTEXT'.K3_String::EOL;
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
                    preg_match_all('#'.self::TEMPLATE_REGEXP_PARAM.'#', $part[3], $params, PREG_PATTERN_ORDER);
                }

                if ($tag == 'WRITE')
                {
                    if (isset($params[1]) && count($params[1]) && ($var = $params[1][0]) && K3_String::isWord($var))
                        $var = strtoupper($var);
                    else
                        $var = 'OUT';
                    if ($var != $writes_to)
                    {
                        $writes_to = $var;
                        $text.= K3_String::EOL.'FTEXT;'.K3_String::EOL.'$'.$writes_to.(($got_a) ? '' : '.').'= <<<FTEXT'.K3_String::EOL;
                        $jstext.= '";'.K3_String::EOL.'v.'.$writes_to.(($got_a) ? '' : '+').'= "';
                    }
                }
                elseif (isset($this->_funcParsers[$tag])) //parsing the variable with func
                {
                    if (!isset($params[1]) || !count($params[1]))
                        continue;

                    $text.= K3_String::EOL.'FTEXT'.K3_String::EOL.'.';
                    $jstext.= '"'.K3_String::EOL.'+';
                    $text.= $this->_templateVISFunctionCB($tag, $params[1], $vars, $consts, false, $got_a);
                    $jstext.= $this->_templateVISFunctionCB($tag, $params[1], $vars, $consts, true, $got_a);
                    $text.= '.<<<FTEXT'.K3_String::EOL;
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
                            if (!K3_String::isWord($var) || !isset($params[3][$i]) || !strlen($params[3][$i]))
                                continue;
                            $var = strtoupper($var);
                            $val = $params[3][$i];
                            $sets.= '$'.$var.' = '.$this->_templateVISParamCB($val, $vars, $consts).';';
                            $jssets.= 'v.'.$var.' = '.$this->_templateVISParamCB($val, $vars, $consts, false, true).';'.K3_String::EOL;
                        }

                        if ($sets)
                        {
                            $text.= K3_String::EOL.'FTEXT;'.K3_String::EOL.$sets.K3_String::EOL.'$'.$writes_to.'.= <<<FTEXT'.K3_String::EOL;
                            $jstext.= '";'.K3_String::EOL.$sets.'v.'.$writes_to.'+= "';
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
                        $pp3 = (!K3_String::isWord($p3)) ? intval($p3) : $p3;
                    else
                        $pp3 = $p3 = '1';

                    $text.= K3_String::EOL.'FTEXT;'.K3_String::EOL.'for ($I = '.$this->_templateVISParamCB($pp1, $vars, $consts).'; $I <= '.$this->_templateVISParamCB($pp2, $vars, $consts).'; $I+= '.$this->_templateVISParamCB($pp3, $vars, $consts).') {'.K3_String::EOL.'$'.$writes_to.'.= <<<FTEXT'.K3_String::EOL;
                    $jstext.= '";'.K3_String::EOL.'for (v.I = '.$this->_templateVISParamCB($p1, $vars, $consts, false, true).'; v.I <= '.$this->_templateVISParamCB($p2, $vars, $consts, false, true).'; v.I+= '.$this->_templateVISParamCB($p3, $vars, $consts, false, true).') {'.K3_String::EOL.'v.'.$writes_to.'+= "';
                }
                elseif ($tag == 'ENDFOR' || $tag == '/FOR')
                {
                    if ($in_for)
                    {
                        $text.= K3_String::EOL.'FTEXT;'.K3_String::EOL;
                        $jstext.= '";'.K3_String::EOL;
                        $in_for = false;
                        $text.= str_repeat('} ', $iflevel);
                        $jstext.= str_repeat('} ', $iflevel);
                        $text.= K3_String::EOL.'}'.K3_String::EOL.'$'.$writes_to.'.= <<<FTEXT'.K3_String::EOL;
                        $jstext.= K3_String::EOL.'}'.K3_String::EOL.'v.'.$writes_to.'.= "';
                        $iflevel = $outiflevel;
                    }
                }
                elseif ($tag == 'VIS') // no JS parsing for now
                {
                    if (!isset($params[1]) || !count($params[1]))
                        continue;
                    $visname = $params[1][0];
                    $text.= K3_String::EOL.'FTEXT;'.K3_String::EOL.'$'.$writes_to.(($got_a) ? '' : '.').'= $_vis->parseVIS(\''.$visname.'\'';
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
                                if (!K3_String::isWord($var))
                                    continue;
                                $var = strtoupper($var);
                                $val = (isset($params[3][$i]) && strlen($params[3][$i])) ? $params[3][$i] : '1';
                                $text.= '\''.$var.'\' => '.$this->_templateVISParamCB($val, $vars, $consts).',';
                            }
                            $text.= ') ';
                            if ($passMyParams) {
                                $text.= '+ $_in';
                            }
                        } elseif ($passMyParams) {
                            $text.= ', $_in';
                        }
                    }
                    $text.= ');'.K3_String::EOL.'$'.$writes_to.'.= <<<FTEXT'.K3_String::EOL;
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

                        $condition = '('.$this->_templateVISParamCB($var, $vars, $consts).$condition.$this->_templateVISParamCB($condvar, $vars, $consts).')';
                        $jscondition = '('.$this->_templateVISParamCB($var, $vars, $consts, false, true).$condition.$this->_templateVISParamCB($condvar, $vars, $consts, false, true).')';
                    }
                    else
                    {
                        $condition = 'strlen('.$this->_templateVISParamCB($var, $vars, $consts).')';
                        $jscondition = '('.$this->_templateVISParamCB($var, $vars, $consts, false, true).'.length)';
                    }

                    if ($got_a)
                    {
                        $condition = '!'.$condition;
                        $jscondition = '!'.$jscondition;
                    }

                    if ($tag == 'IF')
                    {
                        $text.= K3_String::EOL.'FTEXT;'.K3_String::EOL.'if ('.$condition.') {'.K3_String::EOL.'$'.$writes_to.'.= <<<FTEXT'.K3_String::EOL;
                        $jstext.= '";'.K3_String::EOL.'if ('.$jscondition.') {'.K3_String::EOL.'v.'.$writes_to.'+= "';
                        $iflevel++;
                    }
                    elseif ($iflevel)
                    {
                        $text.= K3_String::EOL.'FTEXT;'.K3_String::EOL.'} elseif('.$condition.') {'.K3_String::EOL.'$'.$writes_to.'.= <<<FTEXT'.K3_String::EOL;
                        $jstext.= '";}'.K3_String::EOL.'else if ('.$jscondition.') {'.K3_String::EOL.'v.'.$writes_to.'+= "';
                    }
                }
                elseif ($tag == 'ELSE')
                {
                    if ($iflevel)
                    {
                        $text.= K3_String::EOL.'FTEXT;'.K3_String::EOL.'} elseif(true) {'.K3_String::EOL.'$'.$writes_to.'.= <<<FTEXT'.K3_String::EOL;
                        $jstext.= '";}'.K3_String::EOL.'else if (true) {'.K3_String::EOL.'v.'.$writes_to.'+= "';
                    }
                }
                elseif ($tag == 'ENDIF' || $tag == '/IF')
                {
                    if ($iflevel)
                    {
                        $text.= K3_String::EOL.'FTEXT;'.K3_String::EOL.'}'.K3_String::EOL.'$'.$writes_to.'.= <<<FTEXT'.K3_String::EOL;
                        $jstext.= '";}'.K3_String::EOL.'v.'.$writes_to.'+= "';
                        $iflevel--;
                    }
                }
                else
                {
                    $varname = strtoupper($part[2]);
                    if (isset($consts[$varname]))
                    {
                        $text.= K3_Util_String::escapeHeredoc($consts[$varname], 'FTEXT', false);
                        $jstext.= K3_Util_String::escapeJSON($consts[$varname]);
                    }
                    elseif (K3_String::isWord($varname))
                    {
                        $vars[$varname] = '';
                        if ($got_a)
                        {
                            $text.= K3_String::EOL.'FTEXT'.K3_String::EOL.'.K3_Util_String::escapeXML($'.$varname.').<<<FTEXT'.K3_String::EOL;
                            $jstext.= '"+K3_Util_String.escapeXML(v.'.$varname.')+"';
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
                $text.= K3_Util_String::escapeHeredoc($part[0], 'FTEXT', false);
                $jstext.= K3_Util_String::escapeJSON($part[0]);
            }

            unset($struct[$key]);
        }

        $text.= K3_String::EOL.'FTEXT;'.K3_String::EOL;
        $jstext.= '";'.K3_String::EOL;

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
            return implode(K3_String::EOL, $data);

        if (!isset($this->templates[$vis]['F']))
            $this->templates[$vis]['F'] = create_function(
                '&$_vis, &$_v, &$_in, &$_c',
                'if (extract($_v, EXTR_SKIP)) {
                extract($_in, EXTR_REFS | EXTR_OVERWRITE | EXTR_PREFIX_ALL, \'IN\');
                extract($_c, EXTR_REFS | EXTR_OVERWRITE | EXTR_PREFIX_ALL, \'C\'); }
                $UNIQID = dechex(mt_rand(0x1FFF, getrandmax()));
                $OUT = \'\';
                '.$this->templates[$vis]['T'].' return $OUT;');

        return call_user_func_array($this->templates[$vis]['F'], array(&$this, &$this->templates[$vis]['V'], &$data, &$this->_constants));
    }

    public function prepJSFunc($vis)
    {
        if (!isset($this->templates[$vis]) && !$this->tryAutoLoad($vis))
            return '';

        $body = 'v = '.K3_Util_Value::defineJSON($this->templates[$vis]['V']).';'.K3_String::EOL
            .'for (var i in FVIS.consts) v[i] = FVIS.consts[i];'.K3_String::EOL
            .'for (var i in data) v[i] = data[i];'.K3_String::EOL
            .$this->templates[$vis]['J'].K3_String::EOL
            .'return v.OUT';

        return 'function(data) {'.K3_String::EOL.$body.K3_String::EOL.'}';
    }

    /**
     * @param string $input
     * @param array|null $constants
     * @param bool $forceCompact
     * @return mixed
     */
    public function prepareECSS($input, array $constants = null, $forceCompact = false)
    {
        if (is_array($constants)) {
            foreach ($constants as $name => $val) {
                $input = str_replace('{'.$name.'}', $val, $input);
            }
        }

        $varsMask  = '#\{((?>[\w\-]+))\}\s*=(.*)#';
        $varsBlock = '#\{VARS\}(.*?)\{/VARS\}#si';

        preg_match_all($varsBlock, $input, $blocks);
        $blocks = implode(' ', $blocks[0]);

        $CSSVars = array();
        preg_match_all($varsMask, $blocks, $sets);
        if (is_array($sets[1])) {
            foreach ($sets[1] as $num => $name) {
                $CSSVars[strtoupper($name)] = trim($sets[2][$num]);
            }
        }

        $output = preg_replace($varsBlock, '', $input);

        if ($this->_forceCompact || $forceCompact) {
            $output = $this->compactCSS($output);
        }

        $output = preg_replace_callback('#\{(?>(\w+))\}#', function (array $match) use ($CSSVars) {
            $varName = strtoupper($match[1]);

            return isset($CSSVars[$varName])
                ? $CSSVars[$varName]
                : "";
        }, $output);

        return $output;
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
        $indata = str_replace("\n", K3_String::EOL, $indata);
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
        $indata = str_replace("\n", K3_String::EOL, $indata);
        $indata = trim($indata);
        return $indata;
    }

    public function compactHTML($indata)
    {
        $indata = str_replace("\r", '', $indata);
        $indata = preg_replace('#(\n\s*)+#', "\n", $indata);
        $indata = preg_replace('#\n(.{1,5})$#m', ' \\1', $indata);
        $indata = preg_replace('#\x20+#', ' ', $indata);
        $indata = str_replace("\n", K3_String::EOL, $indata);
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

    /**
     * reparses {L_...} blocks in raw templates
     * @param string $data
     * @return string mixed
     */
    protected function _templateReplaceLang($data)
    {
        return preg_replace_callback('#\{(?>L_((?:\w+|\"[^\"]+\"|\|)+))\}#',array($this, '_templateReplaceLangCallback'), $data);
    }

    /**
     * @param array $match
     * @return string
     */
    protected function _templateReplaceLangCallback(array $match)
    {
        return $this->templLangCB($match[1]);
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

    /**
     * calling function with many params
     *
     * @param string $parserName
     * @param array $params
     * @param string[] $vars
     * @param string[] $constants
     * @param bool $forJavaScript
     * @param bool $escapeXML
     * @return string
     */
    protected function _templateVISFunctionCB($parserName, array $params, array &$vars, array $constants, $forJavaScript = false, $escapeXML = false)
    {
        $code = null;
        $callIsStatic = $this->_parserIsPure($parserName);

        $staticArgs = $dynamicArgsJS = $dynamicArgs = array();
        foreach ($params as $id => $val) {
            $argumentIsStatic = true;
            if (K3_String::isWord($val)) {
                $val = strtoupper($val);
                if (substr($val, 0, 2) == 'L_') {
                    $val = $this->templLangCB(substr($val, 2));
                } elseif (isset($constants[$val])) {
                    $val = $constants[$val];
                } else {
                    $vars[$val]       = '';
                    $argumentIsStatic = false;
                }
                $staticArgs[$id]    = $val;
                $dynamicArgs[$id]   = ($argumentIsStatic)
                    ? (is_bool($val) || is_null($val) ? K3_Util_Value::definePHP($val) : $this->_escapeString($val))
                    : '$'.$val;
                $dynamicArgsJS[$id] = ($argumentIsStatic) ? K3_Util_Value::defineJSON($val) : 'v.'.$val;
            } elseif ($val[0] == '"') {
                $val                = substr($val, 1, -1);
                $staticArgs[$id]    = $val;
                $dynamicArgs[$id]   = $this->_escapeString($val);
                $dynamicArgsJS[$id] = K3_Util_Value::defineJSON($val);
            } else {
                if (is_numeric($val) && $val[0] != '0') {
                    $val = intval($val);
                }
                $staticArgs[$id] = $dynamicArgs[$id] = $dynamicArgsJS[$id] = $val;
            }
            $callIsStatic = $callIsStatic && $argumentIsStatic;
        }

        if ($callIsStatic) {
            $val = (string)$this->callParseFunctionArr($parserName, $staticArgs);
            if ($escapeXML) {
                $val = K3_Util_String::escapeXML($val);
            }
            $code = $forJavaScript
                ? K3_Util_Value::defineJSON($val)
                : $this->_escapeString($val);
        } else {
            $code = $forJavaScript
                ? 'FVIS.callParseFunctionArr(\''.$parserName.'\', ['.implode(', ', $dynamicArgsJS).'])'
                : '$_vis->callParseFunctionArr(\''.$parserName.'\', array('.implode(', ', $dynamicArgs).'))';
            if ($escapeXML) {
                $code = $forJavaScript
                    ? 'K3_Util_String.escapeXML('.$code.')'
                    : 'K3_Util_String::escapeXML('.$code.')';
            }
        }

        return $code;
    }

    /**
     * @param string $val
     * @param string[] $vars
     * @param string[] $consts
     * @param string $parseWith
     * @param bool $forJavaScript
     * @param bool $escapeXML
     * @return string
     */
    protected function _templateVISParamCB($val, array &$vars, array $consts, $parseWith = null, $forJavaScript = false, $escapeXML = false)
    {
        $code = '';
        $callIsStatic = empty($parseWith) || $this->_parserIsPure($parseWith);

        if (K3_String::isWord($val)) {
            $val = strtoupper($val);
            if (substr($val, 0, 2) == 'L_') {
                $val = $this->templLangCB(substr($val, 2));
            } elseif (isset($consts[$val])) {
                $val = $consts[$val];
            } else {
                $vars[$val] = '';
                $callIsStatic = false;
                $code       = ($forJavaScript)
                    ? 'v.'.$val
                    : '$'.$val;
            }
        } elseif (is_numeric($val) && $val[0] != '0') {
            $val = intval($val);
        } elseif ($val[0] == '"') {
            $val = substr($val, 1, -1);
        }

        if ($callIsStatic) {
            if ($parseWith) {
                $val = $this->callParseFunction($parseWith, $val);
            }
            if ($escapeXML) {
                $val = K3_Util_String::escapeXML($val);
            }
            $code = $forJavaScript
                ? K3_Util_Value::defineJSON($val)
                : $this->_escapeString($val);
        } elseif ($parseWith) {
            $code = $forJavaScript
                ? 'FVIS.callParseFunction(\''.$parseWith.'\', v.'.$val.')'
                : '$_vis->callParseFunction(\''.$parseWith.'\', $'.$val.')';
            if ($escapeXML) {
                $code = $forJavaScript
                    ? 'K3_Util_String.escapeXML('.$code.')'
                    : 'K3_Util_String::escapeXML('.$code.')';
            }
        }

        return $code;
    }

    /**
     * parsing data with funcParser
     * @param string $parserName
     * @param mixed $data
     * @return mixed
     */
    public function callParseFunction($parserName, $data)
    {
        if (!isset($this->_funcParsers[$parserName])) {
            return $data;
        }

        $parserDefinition = $this->_funcParsers[$parserName];

        $args = array($data);
        if (is_array($parserDefinition) && count($parserDefinition) > 2) {
            $args = array_merge($args, array_splice($parserDefinition, 2));
        }

        return call_user_func_array($parserDefinition, $args);
    }

    /**
     * parsing data with funcParser
     * @param string $parserName
     * @param array $data
     * @return mixed|string
     */
    public function callParseFunctionArr($parserName, array $data)
    {
        if (!isset($this->_funcParsers[$parserName])) {
            return '';
        }

        $parserDefinition = $this->_funcParsers[$parserName];

        if (is_array($parserDefinition) && count($parserDefinition) > 2) {
            $data = array_merge($data, array_splice($parserDefinition, 2));
        }

        return call_user_func_array($parserDefinition, $data);
    }

    /**
     * Determines if the parser function is pure
     * @param $parserName
     * @return bool
     */
    protected function _parserIsPure($parserName)
    {
        if (!isset($this->_funcParsers[$parserName])) {
            return false;
        }

        $parserDefinition = $this->_funcParsers[$parserName];

        if (is_array($parserDefinition)) {
            if (count($parserDefinition) > 2) {
                return false;
            }
            if (is_object($parserDefinition[0])) {
                return false;
            }
        }

        return (strpos($parserName, 'RAND') === false);
    }

    /**
     * @param string $text
     * @return string
     */
    protected function _escapeString($text)
    {
        if (strpos($text, PHP_EOL) !== false || strpos($text, '\'') !== false) {
            return K3_Util_String::escapeNowdoc($text, 'FTEXT', true, false);
        } else {
            return K3_Util_String::escapeSingleQuote($text, true);
        }
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


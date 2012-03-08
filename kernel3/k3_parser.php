<?php
/**
 * QuickFox kernel 3 parser classes
 * @package kernel3
 * @subpackage extra
 */

// Simple InUSE Check
if ( !defined('F_STARTED') )
    die('Hacking attempt');



class FParser extends FEventDispatcher
{
    // defining parse mode constants
    const BBPARSE_CHECK = 0;      // only checks and reconstructs bb-code structure
    const BBPARSE_ALL   = 1;      // parses all tags
    const BBPARSE_PREP  = 2;      // parses only static tags (preaparation to cache)
    const BBPARSE_POSTPREP = 3;   // parses only tags (postparsing of cached)

    // defining tag mode constants
    const BBTAG_NOSUB   = 1;      // inside that tag bbtags can not be opened
    const BBTAG_SUBDUP  = 2;      // can have itself as a subtag (e.g. quote inside quote)
    const BBTAG_BLLEV   = 4;      // blocklevel tags - can't be inside of non-blocklevel
    const BBTAG_USEBRK  = 8;      // this tag uses bekers within it's contents
    const BBTAG_FHTML  = 16;      // formatted html string as a replace (not a html tag name)
    const BBTAG_NOCH   = 32;      // tag data in not cachable (must have function to parse)

    // defining XML tag mode constants
    const XMLTAG_ACLOSE   = 1;    // self closing tag (e.g. <br />)
    const XMLTAG_XHEADER  = 2;

    private $mode = 0;
    private $tags = Array();
    private $pregs = Array();
    private $noparse_tag = 'no_bb';    // contetns of this tag will not be parsed (lower case)
    private $tagbreaker  = '*';
    private $parabreaker = '%';
    private $tag_stack = Array();

    private $last_time = 0;
    private $cur_mode = 0;

    public function __construct()
    {
    }

    public function initStdTags()
    {
        $this->addBBTag('b', 'b');
        $this->addBBTag('i', 'i');
        $this->addBBTag('u', 'u');
        $this->addBBTag('s', 'strike');
        $this->addBBTag('sub', 'sub');
        $this->addBBTag('sup', 'sup');

        $this->addBBTag('h0', 'h1', self::BBTAG_BLLEV);
        $this->addBBTag('h1', 'h1', self::BBTAG_BLLEV);
        $this->addBBTag('h2', 'h2', self::BBTAG_BLLEV);
        $this->addBBTag('h3', 'h3', self::BBTAG_BLLEV);

        $this->addBBTag('align', '<div style="text-align: {param};">{data}</div>', self::BBTAG_FHTML | self::BBTAG_BLLEV, Array('param_mask' => 'left|right|center|justify') );
        $this->addBBTag('center', '<div style="text-align: center;">{data}</div>', self::BBTAG_FHTML | self::BBTAG_BLLEV);
        $this->addBBTag('float', '<div style="float: {param};">{data}</div>', self::BBTAG_FHTML | self::BBTAG_BLLEV, Array('param_mask' => 'left|right') );

        $this->addBBTag('color', '<span style="color: {param};">{data}</span>', self::BBTAG_FHTML, Array('param_mask' => '\#[0-9A-Fa-f]{6}|[A-z\-]+') );
        $this->addBBTag('background', '<span style="background-color: {param};">{data}</span>', self::BBTAG_FHTML, Array('param_mask' => '\#[0-9a-f]{6}|[a-z\-]+') );
        $this->addBBTag('font', '<span style="font-family: {param};">{data}</span>', self::BBTAG_FHTML, Array('param_mask' => '[0-9A-z\x20]+') );
        $this->addBBTag('size', '<span style="font-size: {param}px;">{data}</span>', self::BBTAG_FHTML, Array('param_mask' => '[1-2]?[0-9]') );
        $this->addBBTag('email', '<a href="mailto:{data}">{data}</a>', self::BBTAG_FHTML, Array('data_mask' => FStr::EMAIL_MASK));
        $this->addBBTag('img', '', self::BBTAG_NOSUB, Array('func' => Array( &$this, 'BBCodeStdUrlImg') ) );
        $this->addBBTag('url', '', false, Array('func' => Array( &$this, 'BBCodeStdUrlImg') ) );
        $this->addBBTag('table', '', self::BBTAG_BLLEV | self::BBTAG_USEBRK, Array('func' => Array( &$this, 'BBCodeStdTable') ) );
        $this->addBBTag('list', '', self::BBTAG_BLLEV | self::BBTAG_USEBRK, Array('func' => Array( &$this, 'BBCodeStdList') ) );

        $this->addPreg(FStr::URL_MASK_F, '[url]{data}[/url]');
        //$this->addPreg(FStr::EMAIL_MASK, '[email]{data}[/email]');
    }

    public function addBBTag($bbtag, $html, $tag_mode=0, $extra = null)
    {
        static $extras = Array( 'param', 'param_mask', 'func', 'data_mask' );

        $bbtag = strtolower($bbtag);
        if (!$bbtag)
            return false;

        $newtag = Array(
            'html'       => $html,
            'mode'       => (int) $tag_mode,
            );

        if (is_array($extra))
        {
            foreach ($extras as $exname)
                if (isset($extra[$exname]))
                    $newtag[$exname] = $extra[$exname];
                else
                    $newtag[$exname] = '';
        }
        else
            foreach ($extras as $exname)
                $newtag[$exname] = '';

        $this->tags[$bbtag] = $newtag;

        return true;
    }

    // k2 compat
    public function addTag($bbtag, $html, $tag_mode=0, $extra = null)
    {
        $this->addBBTag($bbtag, $html, $tag_mode, $extra);
    }

    public function addPreg($mask, $data, $func = null)
    {
        $id = count($this->pregs);
        $mask = '#(?<=\s|^)'.$mask.'(?=\s|$)#';
        $data = strtr($data, Array('\\' => '\\\\', '$' => '\\$'));
        $data = strtr($data, Array('{data}' => '${0}'));
        $new_preg = Array(
            'mask' => $mask,
            'data' => $data,
            );
        if ($func && is_callable($func)) // some trick with functioned replaces
        {
            $gen_tag = 'preg_trigger_'.$id;
            $this->addBBTag($gen_tag, '', self::BBTAG_NOSUB, Array('func' => $func ) );
            $new_preg['data'] = '['.$gen_tag.']$0[/'.$gen_tag.']';
        }
        $this->pregs[$id] = $new_preg;

        return true;
    }

    public function parse($input, $mode = self::BBPARSE_ALL, $style = 0)
    {
        if (!count($this->tags))
            $this->initStdTags();
            
        if ($mode == self::BBPARSE_ALL || $mode == self::BBPARSE_PREP) // doing replaces and html strips
        {
            $input = htmlspecialchars($input, ENT_NOQUOTES);
            $input = $this->pregsParse($input, $style);
            //$input = nl2br($input);
        }
        elseif ($mode == self::BBPARSE_POSTPREP) // in postprep mode tagparcer works with all the tags
            $mode = self::BBPARSE_ALL;

        $input = $this->BBParse($input, $mode, $style);

        return $input;
    }

    public function pregsParse($input, $style = 0)
    {
        if (!is_array($this->pregs))
            return $input;
        foreach ($this->pregs as $preg)
        {
            $input = preg_replace($preg['mask'], $preg['data'], $input);
        }

        return $input;
    }

    public function BBParse($input, $mode = self::BBPARSE_ALL, $style = 0)
    {
        $start_time=microtime(true);

        if (!count($this->tags))
            $this->initStdTags();
        //    return $input;       // there is no loaded tags data

        $this->cur_mode = (int) $mode;

        $state_nobb  = false;
        $state_strip = false;
        $state_breakers = 0;
        $used_tags   = Array();
        $cur_tag     = null;
        $buffer      = '';
        $struct      = Array();

        $input  = preg_replace('#[\r\n]+#', '['.$this->parabreaker.']', $input);
        if ($this->cur_mode == self::BBPARSE_CHECK)
            $popen  = $pclose = '';
        else
        {
            $popen  = '<p>';
            $pclose = '</p>';
        }
        
        preg_match_all('#\[((?>[\w]+)|'.preg_quote($this->tagbreaker).'|'.preg_quote($this->parabreaker).')(?:\s*=\s*(\"([^\"\[\]]*)\"|[^\s<>\[\]]+))?\s*\]|\[\/((?>\w+))\]|[^\[]+|\[#', $input, $struct, PREG_SET_ORDER);

        $this->TStackClear();

        $buffer.= $popen;

        foreach ($struct as $part)
        {

            if (isset($part[1]) && $tagname = strtolower($part[1]))      // open tag
            {
                if ($tagname == $this->noparse_tag)
                {
                    if ($this->cur_mode == self::BBPARSE_CHECK || $this->cur_mode == self::BBPARSE_PREP || $state_nobb)
                    {
                        $tdata = '['.$this->noparse_tag.']';
                        if (!$this->TStackWrite($tdata))
                            $buffer.= $tdata;
                    }
                    $state_nobb = true;
                }
                elseif ($tagname == $this->parabreaker)
                {
                    while ($subtname = $this->TStackLast())
                    {
                        $subtmode = $this->tags[$subtname]['mode'];
                        if ($subtmode & self::BBTAG_BLLEV)
                            break;
                        else
                        {
                            $tdata = $this->TStackGet();
                            if (isset($used_tags[$subtname]))
                                $used_tags[$subtname]--;

                            $tdata = $this->parseBBTag($tdata['name'], $tdata['param'], $tdata['buffer']);
                            if (!$this->TStackWrite($tdata))
                                $buffer.= $tdata;
                        }
                    }
                    $tdata = $pclose.FStr::ENDL.$popen;
                    if (!$this->TStackWrite($tdata))
                        $buffer.= $tdata;
                }
                elseif ($tagname == $this->tagbreaker && !$state_nobb)
                {
                    if ($state_breakers)
                    {
                        while ($subtname = $this->TStackLast())
                        {
                            $subtmode = $this->tags[$subtname]['mode'];
                            if ($subtmode & self::BBTAG_USEBRK)
                                break;
                            else
                            {
                                $tdata = $this->TStackGet();
                                if (isset($used_tags[$subtname]))
                                    $used_tags[$subtname]--;

                                $tdata = $this->parseBBTag($tdata['name'], $tdata['param'], $tdata['buffer']);
                                if (($this->cur_mode != self::BBPARSE_CHECK) && ($subtmode & self::BBTAG_BLLEV))
                                    $tdata = $pclose.FStr::ENDL.$tdata.FStr::ENDL.$popen;
                                if (!$this->TStackWrite($tdata))
                                    $buffer.= $tdata;

                            }
                        }
                    }

                    $tdata = '['.$this->tagbreaker.']';
                    if (!$this->TStackWrite($tdata))
                        $buffer.= $tdata;
                }
                elseif (isset($this->tags[$tagname]) && !$state_nobb)
                {
                    $tag = $this->tags[$tagname];
                    $tmode = $tag['mode'];

                    if ($state_strip)
                    {
                        // do nothing - strippeng tags
                    }
                    else
                    {
                        if ($tmode & self::BBTAG_BLLEV)
                            while ($subtname = $this->TStackLast())
                            {
                                $subtmode = $this->tags[$subtname]['mode'];
                                if ($subtmode & self::BBTAG_BLLEV)
                                    break;
                                $tdata = $this->TStackGet();
                                $subtname = $tdata['name'];
                                if (isset($used_tags[$subtname]))
                                    $used_tags[$subtname]--;

                                if ($subtmode & self::BBTAG_USEBRK && $state_breakers)
                                    $state_breakers--;

                                $tdata = $this->parseBBTag($tdata['name'], $tdata['param'], $tdata['buffer']);
                                if (!$this->TStackWrite($tdata))
                                    $buffer.= $tdata;
                            }

                        if ($tmode & self::BBTAG_USEBRK)
                            $state_breakers++;

                        $tused = (isset($used_tags[$tagname])) ? $used_tags[$tagname] : 0;

                        if (!$tused || ($tmode & self::BBTAG_SUBDUP))
                        {
                            $tparam = ($part[2]) ? (($part[3]) ? $part[3] : $part[2]) : '';
                            $this->TStackAdd($tagname, $tparam);

                            if ($tmode & self::BBTAG_NOSUB)
                                $state_strip = true;

                            $tused++;

                            $used_tags[$tagname] = $tused;
                        }
                    }

                }
                else
                {
                    if (!$this->TStackWrite($part[0]))
                        $buffer.= $part[0];
                }
            }
            elseif (isset($part[4]) && $tagname = strtolower($part[4]))  // close tag
            {
                if ($tagname == $this->noparse_tag)
                {
                    if ($state_nobb && ($this->cur_mode == self::BBPARSE_CHECK || $this->cur_mode == self::BBPARSE_PREP))
                    {
                        $tdata = '[/'.$this->noparse_tag.']';
                        if (!$this->TStackWrite($tdata))
                            $buffer.= $tdata;
                    }
                    $state_nobb = false;
                }

                elseif (isset($this->tags[$tagname]) && !$state_nobb)
                {
                    $tag = $this->tags[$tagname];
                    $tmode = $tag['mode'];

                    if ($state_strip)
                    {
                        if ($tagname == $this->TStackLast())
                            $state_strip = false;
                    }

                    if (!$state_strip)
                    {
                        $tused = (isset($used_tags[$tagname])) ? $used_tags[$tagname] : 0;

                        if ($tused)
                            while ($tdata = $this->TStackGet())
                            {
                                $subtname = $tdata['name'];
                                $subtmode = $this->tags[$subtname]['mode'];
                                if (isset($used_tags[$subtname]))
                                    $used_tags[$subtname]--;

                                if ($subtmode & self::BBTAG_USEBRK && $state_breakers)
                                    $state_breakers--;

                                $tdata = $this->parseBBTag($tdata['name'], $tdata['param'], $tdata['buffer']);
                                if (($this->cur_mode != self::BBPARSE_CHECK) && ($subtmode & self::BBTAG_BLLEV))
                                    $tdata = $pclose.FStr::ENDL.$tdata.FStr::ENDL.$popen;
                                if (!$this->TStackWrite($tdata))
                                    $buffer.= $tdata;

                                if ($subtname == $tagname)
                                    break;
                            }
                    }

                }
                else
                {
                    if (!$this->TStackWrite($part[0]))
                        $buffer.= $part[0];
                }

            }
            else              // string data
            {
                if (!$this->TStackWrite($part[0]))
                    $buffer.= $part[0];
            }

        }

        if ($state_nobb && ($this->cur_mode == self::BBPARSE_CHECK || $this->cur_mode == self::BBPARSE_PREP))
        {
            $tdata = '[/'.$this->noparse_tag.']';
            if (!$this->TStackWrite($tdata))
                $buffer.= $tdata;
            $state_nobb = false;
        }

        while ($tdata = $this->TStackGet())
        {
            $subtname = $tdata['name'];
            $subtmode = $this->tags[$subtname]['mode'];
            if (isset($used_tags[$subtname]))
                $used_tags[$subtname]--;

            $tdata = $this->parseBBTag($tdata['name'], $tdata['param'], $tdata['buffer']);
            if (($this->cur_mode != self::BBPARSE_CHECK) && ($subtmode & self::BBTAG_BLLEV))
                $tdata = $pclose.FStr::ENDL.$tdata.FStr::ENDL.$popen;
            if (!$this->TStackWrite($tdata))
                $buffer.= $tdata;
        }

        $buffer = preg_replace('#<p>\s*</p>#m', '', $buffer.$pclose);

        $stop_time = microtime(true);
        $this->last_time = $stop_time - $start_time;

        return $buffer;
    }

    public function parseBBTag($name, $param, $buffer='')
    {
        if (!$buffer)
            return '';

        $param = preg_replace('#\[(\/?\w+)#', '[ $1', $param);
        if ($this->cur_mode == self::BBPARSE_CHECK)
            return ('['.$name.($param ? '="'.$param.'"' : '').']'.$buffer.'[/'.$name.']');

        elseif ($tag = $this->tags[$name])
        {
            $tmode = $tag['mode'];

            if ($tag['func'])
            {
                if (($tmode & self::BBTAG_NOCH) && $this->cur_mode == self::BBPARSE_PREP)
                    return ('['.$name.($param ? '="'.$param.'"' : '').']'.$buffer.'[/'.$name.']');
                else
                    return call_user_func($tag['func'], $name, $buffer, $param);
            }

            if ($p_mask = $tag['param_mask'])
            {
                if (preg_match('#('.$p_mask.')#', $param, $parr))
                    $param = $parr[0];
                else
                    return $buffer;
            }
            if ($d_mask = $tag['data_mask'])
            {
                if (preg_match('#('.$d_mask.')#', $buffer, $darr))
                    $buffer = $darr[0];
                else
                    return $buffer;
            }

            if (($tmode & self::BBTAG_NOCH) && $this->cur_mode == self::BBPARSE_PREP)
                return ('['.$name.($param ? '="'.$param.'"' : '').']'.$buffer.'[/'.$name.']');
            elseif ($tmode & self::BBTAG_FHTML)
            {
                if ($tmode & self::BBTAG_BLLEV)
                    $buffer = $popen.$buffer.$pclose;
                $out = strtr($tag['html'], Array('{param}' => $param, '{data}' => $buffer));
                return $out;
            }
            else
            {
                if ($tmode & self::BBTAG_BLLEV)
                    $buffer = $popen.$buffer.$pclose;
                $out = '<'.$tag['html'].(($param && $tag['param']) ? ' '.$tag['param'].'="'.$param.'"' : '').'>'.$buffer.'</'.$tag['html'].'>';
                return $out;
            }
        }
    }

    public function XMLCheck($input, $use_html_specs = false)
    {
        $stime = explode(' ',microtime());
        $start_time=$stime[1]+$stime[0];

        $state_strip = false;
        $used_tags   = Array();
        $struct      = Array();
        $t_flags     = Array('?xml' => self::XMLTAG_XHEADER); // flags to control behaviour
        $t_pars      = Array(); // some tags may be only clids of some parents
        $t_clilds    = Array(); // some tags may include only some clilds (not implemented yet)

        if ($use_html_specs)
        {
            $t_pars = Array(
                'tr' => 'table|tbody',
                'td' => 'tr',
                'th' => 'tr',
                );

            $t_flags = Array(
                '?xml'  => self::XMLTAG_XHEADER,
                '!DOCTYPE' => self::XMLTAG_XHEADER,
                'hr'    => self::XMLTAG_ACLOSE,
                'br'    => self::XMLTAG_ACLOSE,
                'img'   => self::XMLTAG_ACLOSE,
                'input' => self::XMLTAG_ACLOSE,
                );
        }

        preg_match_all('#\<\!\[(CDATA)\[.*?\]\]\>|\<(\!\-\-).*?\-\-\>|\<((?:\!|\?)?[\w\-\:_]+)((?:\s+|\"[^\"]*\"|\'[^\']*\'|[^\s\<\>]+)*)\>|\<\/\s*([\w\-\:_]+)\s*\>|[^\<]+|\<#s', $input, $struct, PREG_SET_ORDER);

        $this->TStackClear();

        $output = '';

        foreach ($struct as $part)
        {
            if ($part[1] == 'CDATA' || $part[2] == '!--') // CDATA & comments
                $output.= $part[0];
            elseif ($tag = strtolower($part[3])) // open tag or full tag
            {
                $pstr = $part[4];
                $flags = (isset($t_flags[$tag])) ? $t_flags[$tag] : 0;
                $is_full = (bool) ($flags & self::XMLTAG_ACLOSE);

                if ($flags & self::XMLTAG_XHEADER)
                {
                    $output.= '<'.$tag.$pstr.'>';
                    continue;
                }

                if (isset($t_pars[$tag]) && ($ptags = explode('|', $t_pars[$tag])))
                {
                    $ptused = false;
                    foreach ($ptags as $ptag)
                        if (isset($used_tags[$ptag]) && $used_tags[$ptag])
                        {
                            $ptused = true;
                            break;
                        }

                    if ($ptused)
                    {
                        while ($suptag = $this->TStackLast())
                        {
                            if (in_array($suptag, $ptags))
                                break;
                            else
                            {
                                $this->TStackGet();
                                if (isset($used_tags[$suptag]))
                                    $used_tags[$suptag]--;
                                $output.= '</'.$suptag.'>';
                            }
                        }
                    }
                    else
                        continue;
                }

                if ($pstr = trim($pstr))
                {
                    if (substr($pstr, -1) == '/')
                        $is_full = true;
                    if ($pstr = $this->XMLCheckParams($tag, $pstr))
                        $pstr = ' '.$pstr;
                }

                if (!$is_full)
                {
                    $this->TStackAdd($tag);
                    if (isset($used_tags[$tag]))
                        $used_tags[$tag]++;
                    else
                        $used_tags[$tag] = 1;
                }

                $output.= '<'.$tag.$pstr.(($is_full) ? ' /' : '').'>';
            }
            elseif ($tag = strtolower($part[5]))
            {
                $tused = isset($used_tags[$tag]) ? $used_tags[$tag] : 0;
                if ($tused)
                    while ($tdata = $this->TStackGet())
                    {
                        $subtag = $tdata['name'];
                        if (isset($used_tags[$subtag]))
                            $used_tags[$subtag]--;

                        $output.= '</'.$subtag.'>';

                        if ($subtag == $tag)
                            break;
                    }
            }
            else
            {
                $output.= FStr::smartHTMLSchars($part[0]);
            }
        }

        while ($tdata = $this->TStackGet())
            $output.= '</'.$tdata['name'].'>';

        $stime = explode(' ',microtime());
        $stop_time = $stime[1]+$stime[0];
        $this->last_time = $stop_time - $start_time;

        return $output;
    }

    private function XMLCheckParams($tag, $param_str)
    {
        preg_match_all('#([\w\-]+)(\s*=\s*(?:\"([^\"]*)\"|\'([^\']*)\'|([^\s]+)))?#', $param_str, $struct, PREG_SET_ORDER);
        $params = Array();
        foreach ($struct as $part)
        {
            $val = $par = strtolower($part[1]);
            if (isset($part[2]))
            {
                $val = (isset($part[5]))
                    ? $part[5]
                    : ((isset($part[4]))
                        ? $part[4]
                        : $part[3]);
                $val = FStr::smartHTMLSchars($val);
            }
            $params[] = $par.'="'.$val.'"';
        }
        $param_str = implode(' ', $params);
        return $param_str;
    }

    private function TStackClear()
    {
        $this->tag_stack = Array();
    }

    private function TStackAdd($name, $param='')
    {
        $pos = count($this->tag_stack);
        $new = Array('name' => $name, 'param' => $param, 'buffer' => '');
        $this->tag_stack[$pos] =& $new;
    }

    private function TStackWrite($text)
    {
        $pos = count($this->tag_stack)-1;
        if ($pos>=0)
        {
            $this->tag_stack[$pos]['buffer'].= $text;
            return true;
        }
        else
            return false;
    }

    private function TStackGet()
    {
        if ($out = array_pop($this->tag_stack))
            return $out;
        else
            return false;
    }

    private function TStackLast()
    {
        $pos=count($this->tag_stack)-1;
        if ($pos>=0) {
            $out = $this->tag_stack[$pos]['name'];
            return $out;
        }
        else
            return false;
    }

    private function BBCodeStdUrlImg($name, $buffer, $param = false)
    {
        if ($name == 'url')
            $html = '<a href="{url}" title="{url}" >{capt}</a>';
        elseif ($name == 'img')
            $html = '<img src="{url}" alt="{capt}" />';
        else
            return $buffer;

        if ($param)
        {
            $url = $param;
            $capt = $buffer;
        }
        else
        {
            $url = $capt = $buffer;
        }

        if (FStr::isUrl($url) != 1)
            return $buffer;

        if ($name == 'img')
            if (!preg_match('#\.(jpg|jpeg|png|gif|swf|bmp|tif|tiff)$#i', $url))
                $html = '<a href="{url}" title="{url}" >{capt} [Image blocked]</a>';

        return strtr($html, Array('{url}' => $url, '{capt}' => $capt));
    }

    private function BBCodeStdTable($name, $buffer, $param = false)
    {
        static $cellaligns = array('t' => 'vertical-align: top; ', 'b' => 'vertical-align: bottom; ', 'm' => 'vertical-align: middle; ');
        static $aligns = array('l' => 'text-align: left; ', 'r' => 'text-align: right; ', 'c' => 'text-align: center; ', 'j' => 'text-align: justify; ');
        
        $useborder = $width = $align = false;
        $parr = explode('|', $param);
        if (count($parr)>1)
        {
            $param = $parr[0];
            $useborder = (int) $parr[1];
            if ($width = (int) $parr[2])
            {
                if (substr($parr[2], -1) == '%')
                    $width = $width.'%';
                else
                    $width = $width.'px';
            }
            if ($align = $parr[3])
                $align = str_split($align);
        }
        $param = (int) $param;
        if ($param <= 0)
            $param = 1;

        $table = explode('['.$this->tagbreaker.']', $buffer);
        $style = $cellstyle = '';
        if ($useborder)
            $style.= 'border: solid '.$useborder.'px; ';
        if ($width)
            $style.= 'width: '.$width.'; ';
        if ($align)
            foreach($align as $part)
            {
                if (isset($aligns[$part]))
                    $style.= $aligns[$part];
                if (isset($cellaligns[$part]))
                    $cellstyle.= $cellaligns[$part];
            }
        $buffer = ($style)
            ? '<table style="'.$style.'"><tr>'
            : '<table><tr>';
        $i = 0;
        foreach ($table as $part)
        {
            if ($i>0 && ($i%$param == 0))
                $buffer.= '</tr><tr>';

            if ($part==='')
                $part = '&nbsp;';
            else
                $part = preg_replace('#^\s*\<br\s?/?\>#', '', $part);
            $buffer.= ($cellstyle)
                ? '<td style="'.$cellstyle.'">'.$part.'</td>'
                : '<td>'.$part.'</td>';
            $i++;
        }
        while ($i%$param != 0)
        {
            $buffer.= '<td>&nbsp;</td>';
            $i++;
        }
        $buffer.= '</table>';

        return $buffer;
    }

    private function BBCodeStdList($name, $buffer, $param = false)
    {
        static $styles = Array(
            '' => 'disc', 'd' => 'disc', 'c' => 'circle', 's' => 'square', '1' => 'decimal',
            'a' => 'lower-alpha', 'A' => 'upper-alpha', 'i' => 'lower-roman', 'I' => 'upper-roman',
            );
        static $ols = Array('1', 'a', 'A', 'i', 'I');

        $useborder = false;
        $parr = explode('|', $param);
        $style = $prefix = '';
        if (isset($styles[$parr[0]]))
            $style = ' style="list-style-type: '.$styles[$parr[0]].';"';
        else
        {
            $style = ' style="list-style-position: inside; list-style-type: none;"';
            $prefix = $parr[0].' ';
        }
        $useOL = in_array($parr[0], $ols);

        $list = explode('['.$this->tagbreaker.']', $buffer);
        $buffer = '';
        foreach ($list as $item)
        {
            $item = preg_replace('#^\s+#', '', $item);
            if (strlen($item))
                $buffer.= '<li><p>'.$prefix.$item.'</p></li>';
        }
        $buffer = preg_replace('#<li>(<p></p>|\s)*</li>#m', '', $buffer);
        $buffer = ($useOL)
            ? '<ol'.$style.'>'.$buffer.'</ol>'
            : '<ul'.$style.'>'.$buffer.'</ul>';

        return $buffer;
    }

}

?>

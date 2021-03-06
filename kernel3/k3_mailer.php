<?php
/**
 * Copyright (C) 2011 - 2012, 2014 Andrey F. Kupreychik (Foxel)
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
 * QuickFox kernel 3 sendmail control interface
 * @package kernel3
 * @subpackage extra
 */

if (!defined('F_STARTED'))
    die('Hacking attempt');

class FMail
{
    const BR = "\n";
    private $send_to = array();
    private $copy_to = array();
    private $bcc_to  = array();
    private $from    = array();
    private $subject = '';
    private $is_html = false;
    private $text    = '';
    private $parts   = array();

    public function __construct($subject = false, $from_name = false, $from_addr = false, K3_Environment $env = null)
    {
        if (is_null($env)) {
            $env = F()->appEnv;
        }

        if (!K3_String::isEmail($from_addr))
            $from_addr = 'no-reply@'.$env->server->domain;

        $this->send_to = array();
        $this->copy_to = array();
        $this->bcc_to  = array();
        $this->from    = array($from_addr, $from_name);
        $this->subject = ($subject) ? (string) $subject : 'no-subject';
        $this->is_html = false;
        $this->text    = '';
        $this->parts   = array();
    }

    public function setSubject($subject)
    {
        $this->subject = ($subject) ? (string) $subject : 'no-subject';

        return $this;
    }

    public function addTo($addr, $name = false)
    {
        if (K3_String::isEmail($addr))
            $this->send_to[$addr] = (string) $name;
        else
            trigger_error('Mailer: email address is invalid', E_USER_WARNING);

        return $this;
    }

    public function addCopy($addr, $name = false)
    {
        if (K3_String::isEmail($addr))
            $this->copy_to[$addr] = (string) $name;
        else
            trigger_error('Mailer: email address is invalid', E_USER_WARNING);
            
        return $this;
    }

    public function addBcc($addr, $name = false)
    {
        if (K3_String::isEmail($addr))
            $this->bcc_to[$addr] = (string) $name;
        else
            trigger_error('Mailer: email address is invalid', E_USER_WARNING);
            
        return $this;
    }

    public function setBody($text, $is_html = false)
    {
        $this->is_html = (bool) $is_html;
        $this->text = (string) $text;

        return $this;
    }

    public function attachFile($file, $filename = false, $filemime = false)
    {
        if ($filedata = file_get_contents($file))
        {
            if (!$filename)
                $filename = $file;

            if (!$filemime)
                $filemime = 'application/octet-stream';

            $filebname = K3_Util_File::basename($filename);
            $FileSize = filesize($file);
            $FileTime = gmdate('D, d M Y H:i:s ', filemtime($file)).'GMT';
            // making part headers
            $data = array(
                'Content-Type: '.$filemime.'; name="'.$filebname.'"',
                'Content-Location: '.$filename,
                'Content-Transfer-Encoding: base64',
            );

            $data = implode(self::BR, $data).self::BR;
            $data.= self::BR; // closing headers
            $data.= chunk_split(base64_encode($filedata), 76, self::BR);
            $this->parts[] = $data;
            unset ($filedata, $data);
        }
        else
            trigger_error('Mailer: error reading file to attach', E_USER_WARNING);

        return $this;
    }

    public function send($recode_to = '')
    {
        list ($m_to, $m_subject, $m_body, $m_headers) = $this->prepare($recode_to);
        $m_headers = implode(self::BR, $m_headers);
        
        return mail($m_to, $m_subject, $m_body, $m_headers);
    }

    public function toString($recode_to = '')
    {
        list ($m_to, $m_subject, $m_body, $m_headers) = $this->prepare($recode_to);

        array_unshift($m_headers, 'To: '.$m_to);
        array_unshift($m_headers, 'Subject: '.$m_subject);
        $m_headers = implode(self::BR, $m_headers);

        return implode(self::BR.self::BR, array($m_headers, $m_body));
    }

    protected function prepare($recode_to = '')
    {
        $m_from = K3_String::strToMime($this->from[1], $recode_to).' <'.$this->from[0].'>';
        $m_subject = K3_String::strToMime($this->subject, $recode_to);
        $m_to = $m_cc = $m_bcc = array();
        foreach ($this->send_to as $mail=>$name)
            $m_to[]  = K3_String::strToMime($name, $recode_to).' <'.$mail.'>';
        foreach ($this->copy_to as $mail=>$name)
            $m_cc[]  = K3_String::strToMime($name, $recode_to).' <'.$mail.'>';
        foreach ($this->bcc_to  as $mail=>$name)
            $m_bcc[] = K3_String::strToMime($name, $recode_to).' <'.$mail.'>';

        $m_headers = array(
            'From: '.$m_from,
            'Message-ID: <'.md5(uniqid(time())).'@'.F()->appEnv->server->domain.'>',
            'MIME-Version: 1.0',
            'Date: '.date('r', time()),
            'X-Priority: 3',
            'X-MSMail-Priority: Normal',
            'X-Mailer: Kernel 3',
            'X-MimeOLE: Kernel 3',
            );

        if (count($m_cc))
            $m_headers[] = 'Cc: '.implode(', ', $m_cc);
        if (count($m_bcc))
            $m_headers[] = 'Bcc: '.implode(', ', $m_bcc);
        if (count($m_to))
            $m_to = implode(', ', $m_to);
        else
            $m_to = 'Undisclosed-Recipients';
        //$m_headers[] = 'To: '.$m_to;

        if ($recode_to && $m_text = K3_String::strRecode($this->text, $recode_to))
            $m_encoding = $recode_to;
        else
        {
            $m_text = $this->text;
            $m_encoding = F::INTERNAL_ENCODING;
        }

        $m_type = ($this->is_html)
            ? 'text/html'
            : 'text/plain';

        if (count($this->parts)) //multipart message
        {
            $t_headers = array(
                'Content-Type: '.$m_type.'; charset='.$m_encoding,
                'Content-Transfer-Encoding: 8bit',
                );

            $m_boundary = 'MailPart-'.uniqid(time());
            $m_headers[] = 'Content-Type: multipart/related; boundary="'.$m_boundary.'"';
            $m_boundary = '--'.$m_boundary;
            $m_body = $m_boundary.self::BR;
            $m_body.= implode(self::BR, $t_headers).self::BR;
            $m_body.= self::BR; // closing headers
            $m_body.= $m_text.self::BR;
            foreach($this->parts as $part)
                $m_body.= self::BR.$m_boundary.self::BR.$part;
            $m_body.= self::BR.$m_boundary.'--';
        }
        else
        {
            $m_headers = array(
                'Content-Type: '.$m_type.'; charset='.$m_encoding,
                'Content-Transfer-Encoding: 8bit',
                );
            $m_body = $m_text;
        }

        return array($m_to, $m_subject, $m_body, $m_headers);
    }
    

    public static function create($subject = false, $from_name = false, $from_addr = false)
    {
        return new FMail($subject, $from_name, $from_addr);
    }
    
    public static function _Call($subject = false, $from_name = false, $from_addr = false)
    {
        return new FMail($subject, $from_name, $from_addr);
    }

    public static function getInstance()
    {
        return new StaticInstance('FMail');
    }
}

/*
 * @TODO: add Mail factory
 */

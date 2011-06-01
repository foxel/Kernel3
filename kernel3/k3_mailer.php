<?php

/**
 * QuickFox kernel 3 sendmail control interface
 */

if (!defined('F_STARTED'))
    die('Hacking attempt');

class FMail
{
    const BR = "\n";
    private $send_to = Array();
    private $copy_to = Array();
    private $bcc_to  = Array();
    private $from    = Array();
    private $subject = '';
    private $is_html = false;
    private $text    = '';
    private $parts   = Array();

    public function __construct($subject, $from_name = false, $from_addr = false)
    {
        if (!FStr::isEmail($from_addr))
            $from_addr = 'no-reply@'.F('HTTP')->srvName;

        $this->send_to = Array();
        $this->copy_to = Array();
        $this->bcc_to  = Array();
        $this->from    = Array($from_addr, $from_name);
        $this->subject = ($subject) ? (string) $subject : 'no-subject';
        $this->is_html = false;
        $this->text    = '';
        $this->parts   = Array();
    }

    public function addTo($addr, $name)
    {
        if (!FStr::isEmail($addr))
            return false;

        $this->send_to[$addr] = (string) $name;
        return true;
    }

    public function addCopy($addr, $name)
    {
        if (!FStr::isEmail($addr))
            return false;

        $this->copy_to[$addr] = (string) $name;
        return true;
    }

    public function addBcc($addr, $name)
    {
        if (!FStr::isEmail($addr))
            return false;

        $this->bcc_to[$addr] = (string) $name;
        return true;
    }

    public function setBody($text, $is_html = false)
    {
        $this->is_html = (bool) $is_html;
        $this->text = (string) $text;

        return $this;
    }

    public function attachFile($file, $filename = '', $filemime = '')
    {
        if ($filedata = file_get_contents($file))
        {
            if (!$filename)
                $filename = $file;

            if (!$filemime)
                $filemime = 'application/octet-stream';

            $filebname = FStr::basename($filename);
            $FileSize = filesize($file);
            $FileTime = gmdate('D, d M Y H:i:s ', filemtime($file)).'GMT';
            // making part headers
            $data = Array(
                'Content-Type: '.$filemime.'; name="'.$filebname.'"',
                'Content-Location: '.$filename,
                'Content-Transfer-Encoding: base64',
                );

            $data = implode(self::BR, $data).self::BR;
            $data.= self::BR; // closing headers
            $data.= chunk_split(base64_encode($filedata), 76, self::BR);
            $this->parts[] = $data;
            unset($filedata, $data);
            return true;
        }

        return false;
    }

    public function send($recode_to = '')
    {
        $m_from = FStr::strToMime($this->from[1], $recode_to).' <'.$this->from[0].'>';
        $m_subject = FStr::strToMime($this->subject, $recode_to);
        $m_to = $m_cc = $m_bcc = Array();
        foreach ($this->send_to as $mail=>$name)
            $m_to[]  = FStr::strToMime($name, $recode_to).' <'.$mail.'>';
        foreach ($this->copy_to as $mail=>$name)
            $m_cc[]  = FStr::strToMime($name, $recode_to).' <'.$mail.'>';
        foreach ($this->bcc_to  as $mail=>$name)
            $m_bcc[] = FStr::strToMime($name, $recode_to).' <'.$mail.'>';

        $m_headers = Array(
            'From: '.$m_from,
            'Message-ID: <'.md5(uniqid(time())).'@'.F('HTTP')->srvName.'>',
            'MIME-Version: 1.0',
            'Date: '.date('r', time()),
            'X-Priority: 3',
            'X-MSMail-Priority: Normal',
            'X-Mailer: QuickFox',
            'X-MimeOLE: QuickFox',
            );

        if (count($m_cc))
            $m_headers[] = 'Cc: '.implode(', ', $m_cc);
        if (count($m_bcc))
            $m_headers[] = 'Bcc: '.implode(', ', $m_bcc);
        if (count($m_to))
            $m_to = implode(', ', $m_to);
        else
            $m_to = 'Undisclosed-Recipients';

        if ($recode_to && $m_text = FStr::strRecode($this->text, $recode_to))
            $m_encoding = $recode_to;
        else
        {
            $m_text = $this->text;
            $m_encoding = QF_INTERNAL_ENCODING;
        }
        $m_type = ($this->is_html)
            ? 'text/html'
            : 'text/plain';
        if (count($this->parts)) //multipart message
        {
            $t_headers = Array(
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
            $m_headers = Array(
                'Content-Type: '.$m_type.'; charset='.$m_encoding,
                'Content-Transfer-Encoding: 8bit',
                );
            $m_body = $m_text;
        }
        $m_headers = implode(self::BR, $m_headers);
        return mail($m_to, $m_subject, $m_body, $m_headers);
    }

    public static function create($subject, $from_name = false, $from_addr = false)
    {
        return new FMail($subject, $from_name, $from_addr);
    }
    
    public static function getInstance()
    {
        return new StaticInstance('FMail');
    }
}
?>
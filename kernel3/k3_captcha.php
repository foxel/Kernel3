<?php
/**
 * Copyright (C) 2011 - 2012 Andrey F. Kupreychik (Foxel)
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
 * QuickFox kernel 3 'SlyFox' Cptcha generating module
 * Requires PHP >= 5.1.0, GD2
 * Thanks to http://www.captcha.ru/
 * @package kernel3
 * @subpackage extra
 */

if (!defined('F_STARTED'))
    die('Hacking attempt');


final class FCaptcha extends FEventDispatcher
{
    const FONT_CHARS = '0123456789abcdefghijklmnopqrstuvwxyz';
    const ALLOW_CHARS = '23456789abcdeghkmnpqsuvxyz'; #alphabet without similar symbols (o=0, 1=l, i=j, t=f)
    const STR_LEN = 8;
    
    private $font = null;
    private $char_w = 0;
    private $char_h = 0;
    
    private static $self = null;

    public static function getInstance()
    {
        if (!self::$self)
            self::$self = new FCaptcha();
        return self::$self;
    }

    private function __construct()
    {
        $this->font = imagecreatefrompng(F_KERNEL_DIR.DIRECTORY_SEPARATOR.'font.png');
        imagealphablending($this->font, true);
		$this->char_w = imagesx($this->font)/strlen(self::FONT_CHARS);
		$this->char_h = imagesy($this->font);
    }

    public function generate($string = false, $bgcolor = 0xffffff, $fgcolor = 0, $no_session = false)
    {
        if (!is_string($string))
            $string = $this->genString($string);
        else
            $string = preg_replace('#[^'.self::FONT_CHARS.'\s]#', '', strtolower($string));
        
        $len = strlen($string);
        $width = ($this->char_w-1)*($len + 2);
        $height = $this->char_h*2;
        
        $img = imagecreatetruecolor($width, $height);
		imagealphablending($img, true);
		$white = imagecolorallocate($img, 255, 255, 255);
		$black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $width-1, $height-1, $white);

        // lines
        if (function_exists('imageantialias'))
            imageantialias($img, true);
                
        imagesetthickness($img, 1);
        for ($i = 0; $i < 5; ++$i)
            ($i%2)
                ? imageline($img, rand(-10, $width + 10), 0, rand(-10, $width + 10), $height, $black)
                : imageline($img, 0, rand(-10, $height + 10), $width, rand(-10, $height + 10), $black);
                
        if (function_exists('imagefilter'))
            imagefilter($img, IMG_FILTER_SMOOTH, 50);

        for ($i = 0; $i < $len; ++$i)
        {
            $c = $string[$i];
            $fx = strpos(self::FONT_CHARS, $c);
            if ($fx === false)
                continue;
            $fx*= $this->char_w;
                
            $x = ($i+1)*($this->char_w-1);
            $y = mt_rand($this->char_h, $this->char_h*2)/3;
            
            imagecopy($img, $this->font, $x, $y, $fx, 0, $this->char_w, $this->char_h);
        }
        imagealphablending($img, false);
        
        $imgi = $img;
        
        $img = imagecreatetruecolor($width, $height);
        
        $bgcolor = (int) $bgcolor;
        $fgcolor = (int) $fgcolor;
        
        $backR = ($bgcolor >> 16) & 0xff;
        $backG = ($bgcolor >> 8) & 0xff;
        $backB = $bgcolor & 0xff;

        $textR = ($fgcolor >> 16) & 0xff;
        $textG = ($fgcolor >> 8) & 0xff;
        $textB = $fgcolor & 0xff;

        $backCol = imagecolorallocate($img, $backR, $backG, $backB);
		$textCol = imagecolorallocate($img, $textR, $textG, $textB);
		$lineCol = imagecolorallocate($img, ($textR+$backR*2)/3, ($textG+$backG)/2, ($textB+$backB)/2);
        imagefilledrectangle($img, 0, 0, $width-1, $height-1, $backCol);

        

        // periods
		$period1 = mt_rand(750000, 1200000)/10000000;
		$period2 = mt_rand(750000, 1200000)/10000000;
		$period3 = mt_rand(750000, 1200000)/10000000;
		$period4 = mt_rand(750000, 1200000)/10000000;
		// phases
		$phase1 = mt_rand(0, 31415926)/10000000;
		$phase2 = mt_rand(0, 31415926)/10000000;
		$phase3 = mt_rand(0, 31415926)/10000000;
		$phase4 = mt_rand(0, 31415926)/10000000;
		// amplitudes
		$ampl1 = mt_rand(330, 420)/110;
		$ampl2 = mt_rand(330, 450)/110;

		//wave distortion
		for($x = 0; $x < $width; ++$x)
        for($y = 0; $y < $height; ++$y)
        {
            $sx = $x+(sin($x*$period1 + $phase1)+sin($y*$period3 + $phase3))*$ampl1;
            $sy = $y+(sin($x*$period2 + $phase2)+sin($y*$period4 + $phase4))*$ampl2;

            $sx = max(0, min($width-1, $sx));
            $sy = max(0, min($height-1, $sy));
            //if ($sx < 0 || $sy < 0 || $sx >= $width-1 || $sy >= $height-1)
            //    continue;

            $color_0  = imagecolorat($imgi, $sx, $sy) & 0xFF;
            $color_x  = imagecolorat($imgi, min($sx+1, $width-1), $sy) & 0xFF;
            $color_y  = imagecolorat($imgi, $sx, min($sy+1, $height-1)) & 0xFF;
            $color_xy = imagecolorat($imgi, min($sx+1, $width-1), min($sy+1, $height-1)) & 0xFF;

            $frsx = $sx - floor($sx);
            $frsy = $sy - floor($sy);
            $frsx1 = 1 - $frsx;
            $frsy1 = 1 - $frsy;

            $newcolor = 
                $color_0*$frsx1*$frsy1 + 
                $color_x*$frsx*$frsy1 + 
                $color_y*$frsx1*$frsy +
                $color_xy*$frsx*$frsy;

            if ($newcolor >= 255)
                continue;
                
            $newcolor = $newcolor/255;
            $newcolor0 = 1 - $newcolor;
            
            $bgcolor = imagecolorat($img, $x, $y);
            $backR = ($bgcolor >> 16) & 0xff;
            $backG = ($bgcolor >> 8) & 0xff;    
            $backB = $bgcolor & 0xff;
        
            $newR = $newcolor0*$textR + $newcolor*$backR;
            $newG = $newcolor0*$textG + $newcolor*$backG;
            $newB = $newcolor0*$textB + $newcolor*$backB;

            imagesetpixel($img, $x, $y, ($newR << 16) + ($newG << 8) + $newB);
        }
        
        ob_start();
        imagejpeg($img, null, 85);
        $imgData = ob_get_clean();
        
        imagedestroy($imgi);
        imagedestroy($img);
        
        F()->Session->captchaString = $string;
        
        return $imgData;
    }
    
    public function check($string, $check_for = false)
    {
        if (!$string)
            return false;
        
        if (!$check_for)
        {
            $check_for = F()->Session->captchaString;
            unset(F()->Session->captchaString);
        }
        else
            $check_for = preg_replace('#[^'.self::FONT_CHARS.'\s]#', '', strtolower(trim($check_for)));
            
        $check_for = preg_replace('#\s+#', ' ', $check_for);
        $string = preg_replace('#\s+#', ' ', strtolower(trim($string)));
        
        return ($string && $string == $check_for);
    }

    public function _Call($string = false, $bgcolor = 0xffffff, $fgcolor = 0, $no_session = false)
    {
        return $this->generate($string, $bgcolor, $fgcolor, $no_session);
    }
    
    private function genString($length = false)
    {
        $length = (int) $length;
        if (!$length)
            $length = self::STR_LEN;
        else
            $length = max(4, min(16, $length));
        
        $chars = self::ALLOW_CHARS;
        do {
            $string = '';
            for($i = 0; $i < $length; ++$i)
                $string.= $chars[mt_rand(0, strlen(self::ALLOW_CHARS)-1)];
        } while (preg_match('/cp|cb|ck|c6|c9|rn|rm|mm|co|do|cl|db|qp|qb|dp|ww/', $string));
        
        return $string;
    }
}


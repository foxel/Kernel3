<?php
/**
 * Copyright (C) 2012 Andrey F. Kupreychik (Foxel)
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
 * Class K3_Image
 * @method int width
 * @method int height
 */
class K3_Image extends FBaseClass
{
    const RESIZE_SCALE = 0;
    const RESIZE_COVER = 1;
    const RESIZE_FIT   = 2;
    const RESIZE_CROP  = 3;

    protected $_resource;
    protected $_format = IMAGETYPE_PNG;

    /**
     * @param string $path
     * @return bool|K3_Image
     */
    public static function load($path)
    {
        try {
            return new self($path);
        } catch(FException $e) {
            return null;
        }
    }

    /**
     * @param resource|string|int $data
     * @throws FException
     */
    public function __construct($data)
    {
        if (is_resource($data) && @imagesx($data)) {
            $this->_resource = $data;
        } elseif (is_string($data) && ($imageInfo = @getimagesize($data))) {
            switch ($imageInfo[2]) {
                case IMAGETYPE_JPEG:
                    $img = imagecreatefromjpeg($data);
                    break;
                case IMAGETYPE_PNG:
                    $img = imagecreatefrompng($data);
                    break;
                case IMAGETYPE_GIF:
                    $img = imagecreatefromgif($data);
                    break;
                case IMAGETYPE_XBM:
                    $img = imagecreatefromxbm($data);
                    break;
                default:
                    throw new FException('Image type not supported');
            }
            $this->_format = $imageInfo[2];
            $this->_resource = $img;
        } elseif (func_num_args() == 2) {
            list($w, $h) = func_get_args();
            $this->_resource = imagecreatetruecolor($w, $h);
        } else {
            throw new FException('Input parameters type not supported');
        }
    }

    /**
     * @return resource
     */
    public function getResource()
    {
        return $this->_resource;
    }

    /**
     * @return int
     */
    public function getFormat()
    {
        return $this->_format;
    }


    /**
     * @param int $newWidth
     * @param int $newHeight
     * @param int $mode
     * @return $this
     */
    public function resize($newWidth, $newHeight = 0, $mode = self::RESIZE_SCALE)
    {
        $srcW = $this->width();
        $srcH = $this->height();
        $dstW = $newWidth;
        $dstH = $newHeight;
        $dstX = $dstY = $srcX = $srcY = 0;

        $scaleX = $newWidth/$srcW;
        $scaleY = $newHeight/$srcH;

        if ($scaleX && $scaleY) {
            switch ($mode) {
                case self::RESIZE_COVER:
                    $scale = max($scaleX, $scaleY);
                    $srcW = $newWidth/$scale;
                    $srcH = $newHeight/$scale;
                    $srcX = ($this->width() - $srcW)/2;
                    $srcY = ($this->height() - $srcH)/2;
                    break;
                case self::RESIZE_FIT:
                    $scale = min($scaleX, $scaleY);
                    $dstW = $srcW*$scale;
                    $dstH = $srcH*$scale;
                    $dstX = ($newWidth - $dstW)/2;
                    $dstY = ($newHeight - $dstH)/2;
                    break;
                case self::RESIZE_CROP:
                    $srcW = min($newWidth, $srcW);
                    $srcH = min($newHeight, $srcH);
                    $dstW = min($dstW, $srcW);
                    $dstH = min($dstH, $srcW);

                    $srcX = ($this->width() - $srcW)/2;
                    $srcY = ($this->height() - $srcH)/2;
                    $dstX = ($newWidth - $dstW)/2;
                    $dstY = ($newHeight - $dstH)/2;
                    break;
                case self::RESIZE_SCALE:
                default:
                    // do nothing
            }

        } else {
            $scale = max($scaleX, $scaleY);
            $dstW  = $newWidth  = (int) ($scale*$this->width());
            $dstH  = $newHeight = (int) ($scale*$this->height());
        }

        if (imageistruecolor($this->_resource)) {
            $newImg = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($newImg, false);
            imagefilledrectangle($newImg, 0, 0, $newWidth - 1, $newHeight - 1, 0x7FFFFFFF);
            imagesavealpha($newImg, true);
        } else {
            $newImg = imagecreate($newWidth, $newHeight);
            $cid = imagecolortransparent($this->_resource);
            if ($cid != -1) {
                $rgb  = imagecolorsforindex($this->_resource, $cid);
                $back = imagecolorallocate($newImg, $rgb['red'], $rgb['green'], $rgb['blue']);
                imagecolortransparent($newImg, $back);
            }
        }

        imagecopyresampled(
            $newImg, $this->_resource,
            (int) $dstX, (int) $dstY,
            (int) $srcX, (int) $srcY,
            (int) $dstW, (int) $dstH,
            (int) $srcW, (int) $srcH
        );

        imagedestroy($this->_resource);
        $this->_resource = $newImg;

        return $this;
    }

    /**
     * @param string $text
     * @param string $fontFile
     * @param int|string $fontSize
     * @return $this
     */
    public function watermark($text, $fontFile, $fontSize = 24)
    {
        if (is_string($fontSize) && preg_match('#^([\d\.]+)%$#', $fontSize, $matches)) {
            $percent  = ($matches[1]/100);
            $fontSize = (int)($percent*(imagesy($this->_resource) - 20));
            $testBox  = imagettfbbox(20, 0, $fontFile, $text);
            $fontSize = min($fontSize, (int)($percent*20*(imagesx($this->_resource) - 20)/$testBox[2]));
        } else {
            $fontSize = (int)$fontSize;
        }

        $box = imagettfbbox($fontSize, 0, $fontFile, $text);
        $x   = imagesx($this->_resource) - 10 - $box[2];
        $y   = imagesy($this->_resource) - 10 - $box[3];
        imagettftext($this->_resource, $fontSize, 0, $x + 1, $y + 1, $this->_imageColorAllocate(0, 0, 0), $fontFile, $text);
        imagettftext($this->_resource, $fontSize, 0, $x, $y, $this->_imageColorAllocate(255, 255, 255), $fontFile, $text);

        return $this;
    }

    /**
     * @param int $r
     * @param int $g
     * @param int $b
     * @return int
     */
    protected function _imageColorAllocate($r, $g, $b)
    {
        $idx = imagecolorallocate($this->_resource, $r, $g, $b);
        if ($idx === false) {
            $idx = imagecolorclosesthwb($this->_resource, $r, $g, $b);
        }

        return $idx;
    }


    /**
     * @param string|null $file
     * @param int|null $format
     * @param int|null $quality
     * @return bool
     * @throws FException
     */
    public function save($file = null, $format = null, $quality = null)
    {
        if (!$format) {
            $format = $this->_format;
        }
        switch ($format) {
            case IMAGETYPE_JPEG:
                if (!$quality) {
                    $quality = 70;
                }
                return imagejpeg($this->_resource, $file, $quality);
                break;
            case IMAGETYPE_PNG:
                return imagepng($this->_resource, $file, $quality);
                break;
            case IMAGETYPE_GIF:
                return imagegif($this->_resource, $file);
                break;
            case IMAGETYPE_XBM:
                return imagexbm($this->_resource, $file);
                break;
            default:
                throw new FException('Image type not supported');
        }
    }

    /**
     * @param int|null $format
     * @param int|null $quality
     * @return string
     */
    public function toString($format = null, $quality = null)
    {
        ob_start();
        $this->save(null, $format, $quality);
        $string = ob_get_contents();
        ob_end_clean();
        return $string;
    }

    /**
     * @return string
     */
    function __toString()
    {
        return $this->toString();
    }


    public function __destruct()
    {
        imagedestroy($this->_resource);
    }

    /**
     * @var callable[]
     */
    protected static $_callMap = array(
        'width'              => 'imagesx',
        'height'             => 'imagesy',

        'affine'             => 'imageaffine',
        'affinematrixconcat' => 'imageaffinematrixconcat',
        'affinematrixget'    => 'imageaffinematrixget',
        'alphablending'      => 'imagealphablending',
        'antialias'          => 'imageantialias',
        'arc'                => 'imagearc',
        'char'               => 'imagechar',
        'charup'             => 'imagecharup',
        'colorallocate'      => 'imagecolorallocate',
        'colorallocatealpha' => 'imagecolorallocatealpha',
        'colorat'            => 'imagecolorat',
        'colorclosest'       => 'imagecolorclosest',
        'colorclosestalpha'  => 'imagecolorclosestalpha',
        'colorclosesthwb'    => 'imagecolorclosesthwb',
        'colordeallocate'    => 'imagecolordeallocate',
        'colorexact'         => 'imagecolorexact',
        'colorexactalpha'    => 'imagecolorexactalpha',
        'colormatch'         => 'imagecolormatch',
        'colorresolve'       => 'imagecolorresolve',
        'colorresolvealpha'  => 'imagecolorresolvealpha',
        'colorset'           => 'imagecolorset',
        'colorsforindex'     => 'imagecolorsforindex',
        'colorstotal'        => 'imagecolorstotal',
        'colortransparent'   => 'imagecolortransparent',
        'convolution'        => 'imageconvolution',
        //'copy'               => 'imagecopy',
        //'copymerge'          => 'imagecopymerge',
        //'copymergegray'      => 'imagecopymergegray',
        //'copyresampled'      => 'imagecopyresampled',
        //'copyresized'        => 'imagecopyresized',
        'dashedline'         => 'imagedashedline',
        'ellipse'            => 'imageellipse',
        'fill'               => 'imagefill',
        'filledarc'          => 'imagefilledarc',
        'filledellipse'      => 'imagefilledellipse',
        'filledpolygon'      => 'imagefilledpolygon',
        'filledrectangle'    => 'imagefilledrectangle',
        'filltoborder'       => 'imagefilltoborder',
        'filter'             => 'imagefilter',
        'flip'               => 'imageflip',
        'fontheight'         => 'imagefontheight',
        'fontwidth'          => 'imagefontwidth',
        'ftbbox'             => 'imageftbbox',
        'fttext'             => 'imagefttext',
        'gammacorrect'       => 'imagegammacorrect',
        'interlace'          => 'imageinterlace',
        'istruecolor'        => 'imageistruecolor',
        'layereffect'        => 'imagelayereffect',
        'line'               => 'imageline',
        'loadfont'           => 'imageloadfont',
        'palettecopy'        => 'imagepalettecopy',
        'palettetotruecolor' => 'imagepalettetotruecolor',
        'polygon'            => 'imagepolygon',
        'psbbox'             => 'imagepsbbox',
        'psencodefont'       => 'imagepsencodefont',
        'psextendfont'       => 'imagepsextendfont',
        'psfreefont'         => 'imagepsfreefont',
        'psloadfont'         => 'imagepsloadfont',
        'psslantfont'        => 'imagepsslantfont',
        'pstext'             => 'imagepstext',
        'rectangle'          => 'imagerectangle',
        'rotate'             => 'imagerotate',
        'savealpha'          => 'imagesavealpha',
        'setbrush'           => 'imagesetbrush',
        'setinterpolation'   => 'imagesetinterpolation',
        'setpixel'           => 'imagesetpixel',
        'setstyle'           => 'imagesetstyle',
        'setthickness'       => 'imagesetthickness',
        'settile'            => 'imagesettile',
        'string'             => 'imagestring',
        'stringup'           => 'imagestringup',
        'sx'                 => 'imagesx',
        'sy'                 => 'imagesy',
        'truecolortopalette' => 'imagetruecolortopalette',
        'ttfbbox'            => 'imagettfbbox',
        'ttftext'            => 'imagettftext',
    );

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed|void
     */
    public function __call($name, $arguments)
    {
        if (isset(self::$_callMap[$name])) {
            array_unshift($arguments, $this->_resource);
            return call_user_func_array(self::$_callMap[$name], $arguments);
        }

        /** @noinspection PhpVoidFunctionResultUsedInspection */
        return parent::__call($name, $arguments);
    }
}

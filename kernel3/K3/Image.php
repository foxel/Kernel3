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

    const ALIGN_LEFT   = 1;
    const ALIGN_RIGHT  = 2;
    const ALIGN_CENTER = 3;
    const ALIGN_TOP    = 4;
    const ALIGN_BOTTOM = 8;
    const ALIGN_MIDDLE = 12;

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
     * @throws FException
     * @return $this
     */
    public function resize($newWidth, $newHeight = 0, $mode = self::RESIZE_SCALE)
    {
        $srcW = $this->width();
        $srcH = $this->height();
        $dstW = $newWidth;
        $dstH = $newHeight;
        $dstX = $dstY = $srcX = $srcY = 0;

        if ($newWidth && $newHeight) {
            switch ($mode) {
                case self::RESIZE_COVER:
                    $scale = max($newWidth/$srcW, $newHeight/$srcH);
                    $srcW = $newWidth/$scale;
                    $srcH = $newHeight/$scale;
                    $srcX = ($this->width() - $srcW)/2;
                    $srcY = ($this->height() - $srcH)/2;
                    break;
                case self::RESIZE_FIT:
                    $scale = min($newWidth/$srcW, $newHeight/$srcH);
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

        } elseif ($newWidth) {
            $dstH = $newHeight = (int) round($srcH*$newWidth/$srcW);
        } elseif ($newHeight) {
            $dstW = $newWidth  = (int) round($srcW*$newHeight/$srcH);
        } else {
            throw new FException('Image size can to be set to zero');
        }

        if (imageistruecolor($this->_resource)) {
            $newImg = self::_prepareTransparentImage($newWidth, $newHeight);
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
            (int) round($dstX), (int) round($dstY),
            (int) round($srcX), (int) round($srcY),
            (int) round($dstW), (int) round($dstH),
            (int) round($srcW), (int) round($srcH)
        );

        imagedestroy($this->_resource);
        $this->_resource = $newImg;

        return $this;
    }

    /**
     * @param K3_Image $srcImg
     * @param int $dstX
     * @param int $dstY
     * @param int $srcX
     * @param int $srcY
     * @param int $width
     * @param int $height
     * @param int $percent
     * @return $this
     */
    public function copymerge(K3_Image $srcImg, $dstX, $dstY, $srcX, $srcY, $width, $height, $percent)
    {
        return $this->copymergeresampled($srcImg, $dstX, $dstY, $srcX, $srcY, $width, $height, $width, $height, $percent);
    }

    /**
     * @param K3_Image $srcImg
     * @param int $dstX
     * @param int $dstY
     * @param int $srcX
     * @param int $srcY
     * @param int $dstWidth
     * @param int $dstHeight
     * @param int $srcWidth
     * @param int $srcHeight
     * @param int $percent
     * @return $this
     */
    public function copymergeresampled(K3_Image $srcImg, $dstX, $dstY, $srcX, $srcY, $dstWidth, $dstHeight, $srcWidth, $srcHeight, $percent)
    {
        // creating a cut resource
        $cut = imagecreatetruecolor($dstWidth, $dstHeight);

        // copying relevant section from background to the cut resource
        imagecopy($cut, $this->_resource, 0, 0, $dstX, $dstY, $dstWidth, $dstHeight);

        $prevAB = imagealphablending($srcImg->getResource(), true);
        // copying relevant section from watermark to the cut resource
        if ($srcWidth == $dstWidth && $srcHeight == $dstHeight) {
            imagecopy($cut, $srcImg->getResource(), 0, 0, $srcX, $srcY, $dstWidth, $dstHeight);
        } else {
            imagecopyresampled($cut, $srcImg->getResource(), 0, 0, $srcX, $srcY, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
        }
        imagealphablending($srcImg->getResource(), $prevAB);

        // insert cut resource to destination image
        imagecopymerge($this->_resource, $cut, $dstX, $dstY, 0, 0, $dstWidth, $dstHeight, $percent);

        return $this;
    }

    /**
     * @param string $text
     * @param string $fontFile
     * @param int|string $fontSize
     * @param int $align
     * @param int $opacity
     * @return $this
     */
    public function watermark($text, $fontFile, $fontSize = 24, $align = 0, $opacity = null)
    {
        $hAlign = $align & 3;
        $vAlign = $align & 12;
        $border = 20;

        if (is_string($fontSize) && preg_match('#^([\d\.]+)%$#', $fontSize, $matches)) {
            $percent  = ($matches[1]/100);
            $fontSize = (int)($percent*(imagesy($this->_resource)*0.9));
            $testBox  = imagettfbbox(20, 0, $fontFile, $text);
            $fontSize = min($fontSize, (int)($percent*20*(imagesx($this->_resource)*0.9)/$testBox[2]));
            $border   = (int) (min(imagesx($this->_resource), imagesy($this->_resource))*0.05);
        } else {
            $fontSize = (int)$fontSize;
        }

        $box = imagettfbbox($fontSize, 0, $fontFile, $text);
        $boxWidth  = $box[2] - $box[6] + 1;
        $boxHeight = $box[3] - $box[7] + 1;

        switch ($hAlign) {
            case self::ALIGN_LEFT:
                $x = $border;
                break;
            case self::ALIGN_CENTER:
                $x = intval((imagesx($this->_resource) - $boxWidth)/2);
                break;
            case self::ALIGN_RIGHT:
            default:
                $x = imagesx($this->_resource) - $border - $boxWidth;
                break;
        }

        switch ($vAlign) {
            case self::ALIGN_TOP:
                $y = $border;
                break;
            case self::ALIGN_MIDDLE:
                $y = intval((imagesy($this->_resource) - $boxHeight)/2);
                break;
            case self::ALIGN_BOTTOM:
            default:
                $y = imagesy($this->_resource) - $border - $boxHeight;
                break;
        }

        $textImage = self::_prepareTransparentImage($boxWidth, $boxHeight);

        imagettftext($textImage, $fontSize, 0, -$box[6] + 1, -$box[7] + 1, imagecolorallocate($textImage, 0, 0, 0), $fontFile, $text);
        imagettftext($textImage, $fontSize, 0, -$box[6], -$box[7], imagecolorallocate($textImage, 255, 255, 255), $fontFile, $text);

        if (is_null($opacity)) {
            imagecopy($this->_resource, $textImage, $x, $y, 0, 0, $boxWidth, $boxHeight);
        } else {
            $this->copymerge(new K3_Image($textImage), $x, $y, 0, 0, $boxWidth, $boxHeight, $opacity);
        }

        return $this;
    }

    /**
     * @param int $r
     * @param int $g
     * @param int $b
     * @param int|bool $a
     * @return int
     */
    protected function _imageColorAllocate($r, $g, $b, $a = false)
    {
        $idx = is_bool($a)
            ? imagecolorallocatealpha($this->_resource, $r, $g, $b, $a)
            : imagecolorallocate($this->_resource, $r, $g, $b);
        if ($idx === false) {
            $idx = imagecolorclosesthwb($this->_resource, $r, $g, $b);
        }

        return $idx;
    }

    /**
     * @param int $width
     * @param int $height
     * @return resource
     */
    protected static function _prepareTransparentImage($width, $height)
    {
        $img = imagecreatetruecolor($width, $height);
        imagealphablending($img, false);
        imagefilledrectangle($img, 0, 0, $width - 1, $height - 1, 0x7FFFFFFF);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        return $img;
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

    protected static $_combinatorsMap = array(
        'copy'               => 'imagecopy',
        //'copymerge'          => 'imagecopymerge',
        //'copymergegray'      => 'imagecopymergegray',
        'copyresampled'      => 'imagecopyresampled',
        'copyresized'        => 'imagecopyresized',
    );

    /**
     * @param string $name
     * @param array $arguments
     * @throws FException
     * @return mixed|void
     */
    public function __call($name, $arguments)
    {
        if (isset(self::$_callMap[$name])) {
            array_unshift($arguments, $this->_resource);
            return call_user_func_array(self::$_callMap[$name], $arguments);
        }

        if (isset(self::$_combinatorsMap[$name])) {
            $sourceImage = $arguments[0];
            if (!$sourceImage instanceof K3_Image) {
                throw new FException('source image should be instance of K3_Image');
            }
            $arguments[0] = $sourceImage->getResource();

            array_unshift($arguments, $this->_resource);
            call_user_func_array(self::$_combinatorsMap[$name], $arguments);
            return $this;
        }

        /** @noinspection PhpVoidFunctionResultUsedInspection */
        return parent::__call($name, $arguments);
    }
}

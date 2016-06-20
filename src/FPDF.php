<?php

/* vim: set expandtab tabstop=4 shiftwidth=4: */

namespace silverorange\MBFPDF;

/**
 * Adapter class that makes FPDF work in mbstring function overloaded
 * environments
 *
 * @package   MBFPDF
 * @author    Michael Gauthier <mike@silverorange.com>
 * @copyright 2016 silverorange
 * @license   http://www.opensource.org/licenses/mit-license.html MIT License
 */
class FPDF extends \FPDF
{
    /**
     * @var float
     */
    protected $font_scale = 1.0;

    /**
     * Sets a value used to scale fonts in the PDF or turns off scaling
     *
     * For most operations to use font scaling the
     * {@link silverorange\MBFPDF\FPDF::getFontScaledValue()} method must be
     * used to specify the font size.
     *
     * @param float $scale optional. The new font scaling value. If omitted,
     *                     font scaling is turned off.
     *
     * @return void
     */
    public function setFontScale($scale = 1.0)
    {
        $this->font_scale = (float)$scale;
    }

    /**
     * Gets a value scaled by the current font scaling value
     *
     * Use this to specify font sizes when generating a document.
     *
     * @param float $value the value to scale.
     *
     * @return float the scaled value.
     */
    public function getFontScaledValue($value)
    {
        return $value * $this->font_scale;
    }

    /**
     * Takes a block of text foramtted with <b> and <i> tags and outputs
     * multiple PDF text flow lines
     *
     * Tags other than <b> and <i> are stripped. Text uses font scaling.
     *
     * @param string the formatted text.
     *
     * @return void
     */
    public function writeFormattedText($text)
    {
        $parts = preg_split(
            '~(</?[\w]+>)~',
            $text,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        $this->SetFont('', '');
        $this->writeNewLine(5);

        $bold = 0;
        $italic = 0;

        foreach ($parts as $part) {
            if ($part === '<i>') {
                if ($italic === 0) {
                    if ($bold > 0) {
                        $this->SetFont('', 'bi');
                    } else {
                        $this->SetFont('', 'i');
                    }
                }
                $italic++;
            } elseif ($part === '<b>') {
                if ($bold === 0) {
                    if ($italici > 0) {
                        $this->SetFont('', 'bi');
                    } else {
                        $this->SetFont('', 'b');
                    }
                }
                $bold++;
            } elseif ($part === '</i>') {
                $italic--;
                if ($italic === 0) {
                    if ($bold > 0) {
                        $this->SetFont('', 'b');
                    } else {
                        $this->SetFont('', '');
                    }
                }
            } elseif ($part === '</b>') {
                $bold--;
                if ($bold === 0) {
                    if ($italic > 0) {
                        $this->SetFont('', 'i');
                    } else {
                        $this->SetFont('', '');
                    }
                }
            } else {
                $this->Write(
                    $this->getFontScaledValue(5),
                    $this->to1252($part)
                );
            }
        }

        $this->writeNewLine(5);
        $this->SetY($this->GetY() + $this->getFontScaledValue(6));
    }

    /**
     * Sets the text cursor position to a new line
     *
     * @param integer $height optional. The height in page units of the
     *                        line break. If not specified, the cursor is
     *                        just set to the beginning of the at the document
     *                        origin. Font scaling is applied to this value.
     *
     * @return void
     */
    public function writeNewLine($height = 0)
    {
        if ($height == 0) {
            $this->SetY(0, true);
        } else {
            $this->SetY(
                $this->GetY() + $this->getFontScaledValue($height),
                true
            );
        }
    }

    /**
     * Converts a string from UTF-8 to Windows-1252
     *
     * The underlying FPDF library only supports Windows-1252 so text must be
     * converted for proper glyph width calculation.
     *
     * @param string $string the UTF-8 source string.
     *
     * @return string the Windows-1252 string.
     */
    public function to1252($string)
    {
        return iconv('UTF-8', 'Windows-1252//TRANSLIT', $string);
    }

    /**
     * Verifies the required settings and excentions are available for FPDF
     *
     * We disable the <kbd>mbstring.function_overload</kbd> check because
     * multibyte functions are explicitly used where required in this class.
     *
     * @return void
     *
     * @codingStandardsIgnoreStart
     */
    protected function _dochecks()
    {
        /* @codingStandardsIgnoreEnd */
        if (get_magic_quotes_runtime()) {
            @set_magic_quotes_runtime(0);
        }
    }

    /**
     * Reads n bytes from stream
     *
     * @param resource $f the stream to read.
     * @param integer  $n the number of bytes.
     *
     * @return string the read bytes.
     *
     * @codingStandardsIgnoreStart
     */
    protected function _readstream($f, $n)
    {
        /* @codingStandardsIgnoreEnd */

        $res = '';
        while ($n > 0 && !feof($f)) {
            $s = fread($f, $n);
            if ($s === false) {
                $this->Error('Error while reading stream');
            }
            $n -= mb_strlen($s, '8bit');
            $res .= $s;
        }

        if ($n > 0) {
            $this->Error('Unexpected end of stream');
        }

        return $res;
    }

    /**
     * Reads a PNG file from a stream
     *
     * @param resource $f    the file stream.
     * @param string   $file the filename.
     *
     * @return array array containing PNG metadata and raw data.
     *
     * @codingStandardsIgnoreStart
     */
    protected function _parsepngstream($f, $file)
    {
        /* @codingStandardsIgnoreEnd */

        // Check signature
        $signature = "\x89PNG\x0d\x0a\x1a\x0a";
        if ($this->_readstream($f, 8) !== $signature) {
            $this->Error('Not a PNG file: ' . $file);
        }

        // Read header chunk
        $this->_readstream($f, 4);
        if ($this->_readstream($f, 4) !== 'IHDR') {
            $this->Error('Incorrect PNG file: ' . $file);
        }

        $w = $this->_readint($f);
        $h = $this->_readint($f);

        $bpc = ord($this->_readstream($f, 1));
        if ($bpc > 8) {
            $this->Error('16-bit depth not supported: ' . $file);
        }

        $ct = ord($this->_readstream($f, 1));
        if ($ct === 0 || $ct === 4) {
            $colspace = 'DeviceGray';
        } elseif ($ct === 2 || $ct === 6) {
            $colspace = 'DeviceRGB';
        } elseif ($ct === 3) {
            $colspace = 'Indexed';
        } else {
            $this->Error('Unknown color type: ' . $file);
        }

        if (ord($this->_readstream($f, 1)) !== 0) {
            $this->Error('Unknown compression method: ' . $file);
        }

        if (ord($this->_readstream($f, 1)) !== 0) {
            $this->Error('Unknown filter method: ' . $file);
        }

        if (ord($this->_readstream($f, 1)) !== 0) {
            $this->Error('Interlacing not supported: ' . $file);
        }

        $this->_readstream($f, 4);
        $dp = sprintf(
            '/Predictor 15 /Colors %s /BitsPerComponent %s /Columns %s',
            ($colspace === 'DeviceRGB') ? 3 : 1,
            $bpc,
            $w
        );

        // Scan chunks looking for palette, transparency and image data
        $pal = '';
        $trns = '';
        $data = '';
        do {
            $n = $this->_readint($f);
            $type = $this->_readstream($f, 4);
            if ($type === 'PLTE') {
                // Read palette
                $pal = $this->_readstream($f, $n);
                $this->_readstream($f, 4);
            } elseif ($type === 'tRNS') {
                // Read transparency info
                $t = $this->_readstream($f, $n);
                if ($ct === 0) {
                    $trns = array(ord(mb_substr($t, 1, 1, '8bit')));
                } elseif ($ct === 2) {
                    $trns = array(
                        ord(mb_substr($t, 1, 1, '8bit')),
                        ord(mb_substr($t, 3, 1, '8bit')),
                        ord(mb_substr($t, 5, 1, '8bit'))
                    );
                } else {
                    $pos = mb_strpos($t, "\x00", 0, '8bit');
                    if ($pos !== false) {
                        $trns = array($pos);
                    }
                }
                $this->_readstream($f, 4);
            } elseif ($type === 'IDAT') {
                // Read image data block
                $data .= $this->_readstream($f, $n);
                $this->_readstream($f, 4);
            } elseif ($type === 'IEND') {
                break;
            } else {
                $this->_readstream($f, $n + 4);
            }
        } while ($n);

        if ($colspace === 'Indexed' && empty($pal)) {
            $this->Error('Missing palette in ' . $file);
        }

        $info = array(
            'w'    => $w,
            'h'    => $h,
            'cs'   => $colspace,
            'bpc'  => $bpc,
            'f'    => 'FlateDecode',
            'dp'   => $dp,
            'pal'  => $pal,
            'trns' => $trns
        );

        if ($ct >= 4) {
            // Extract alpha channel
            if (!function_exists('gzuncompress')) {
                $this->Error('Zlib not available, can\'t handle alpha channel: ' . $file);
            }

            $data = gzuncompress($data);
            $color = '';
            $alpha = '';
            if ($ct == 4) {
                // Gray image
                $len = 2 * $w;
                for ($i = 0; $i < $h; $i++) {
                    $pos = (1 + $len) * $i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line = mb_substr($data, $pos + 1, $len, '8bit');
                    $color .= preg_replace('/(.)./s', '$1', $line);
                    $alpha .= preg_replace('/.(.)/s', '$1', $line);
                }
            } else {
                // RGB image
                $len = 4 * $w;
                for ($i = 0; $i < $h; $i++) {
                    $pos = (1 + $len) * $i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line = mb_substr($data, $pos + 1, $len, '8bit');
                    $color .= preg_replace('/(.{3})./s', '$1', $line);
                    $alpha .= preg_replace('/.{3}(.)/s', '$1', $line);
                }
            }
            unset($data);
            $data = gzcompress($color);
            $info['smask'] = gzcompress($alpha);
            $this->WithAlpha = true;

            if ($this->PDFVersion < '1.4') {
                $this->PDFVersion = '1.4';
            }
        }

        $info['data'] = $data;
        return $info;
    }

    /**
     * Gets the length of the current buffer in bytes
     *
     * @return integer the length of the current buffer in bytes.
     *
     * @codingStandardsIgnoreStart
     */
    protected function _getoffset()
    {
        /* @codingStandardsIgnoreEnd */
        return mb_strlen($this->buffer, '8bit');
    }
}

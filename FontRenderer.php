<?php

/**
 * PHP & GD port of the FontRenderer class of the popular video-game Minecraft.
 *
 * This class allows you to draw some text on a GD image using the Minecraft "font". Color codes,
 * formatting codes, shadows and, finally, text wrapping are all supported. It also provides some
 * utility methods to get the width or the height in pixels of a single character, or of a string,
 * as well as trimming and formatting a string to a specified width.
 *
 * @author    NeatMonster <neatmonster@hotmail.fr>
 * @copyright Copyright (c) 2014 NeatMonster (http://neatmonster.fr)
 * @license   http://opensource.org/licenses/mit-license.php
 *
 * Licensed under the MIT License.
 * Redistributions of this file must retain the above copyright notice.
 *
 * @todo      Add support for the obfuscated formatting code.
 */
class FontRenderer {
    /** Array of unicode code points of all the characters in ascii.png. */
    private static $ASCII = array(
         192,  193,  194,  200,  202,  203,  205,  211,  212,  213,  218,  223,  227,  245,  287,  304,
         305,  338,  339,  350,  351,  372,  373,  382,  519,    0,    0,    0,    0,    0,    0,    0,
          32,   33,   34,   35,   36,   37,   38,   39,   40,   41,   42,   43,   44,   45,   46,   47,
          48,   49,   50,   51,   52,   53,   54,   55,   56,   57,   58,   59,   60,   61,   62,   63,
          64,   65,   66,   67,   68,   69,   70,   71,   72,   73,   74,   75,   76,   77,   78,   79,
          80,   81,   82,   83,   84,   85,   86,   87,   88,   89,   90,   91,   92,   93,   94,   95,
          96,   97,   98,   99,  100,  101,  102,  103,  104,  105,  106,  107,  108,  109,  110,  111,
         112,  113,  114,  115,  116,  117,  118,  119,  120,  121,  122,  123,  124,  125,  126,    0,
         199,  252,  233,  226,  228,  224,  229,  231,  234,  235,  232,  239,  238,  236,  196,  197,
         201,  230,  198,  244,  246,  242,  251,  249,  255,  214,  220,  248,  163,  216,  215,  402,
         225,  237,  243,  250,  241,  209,  170,  186,  191,  174,  172,  189,  188,  161,  171,  187,
        9617, 9618, 9619, 9474, 9508, 9569, 9570, 9558, 9557, 9571, 9553, 9559, 9565, 9564, 9563, 9488,
        9492, 9524, 9516, 9500, 9472, 9532, 9566, 9567, 9562, 9556, 9577, 9574, 9568, 9552, 9580, 9575,
        9576, 9572, 9573, 9561, 9560, 9554, 9555, 9579, 9578, 9496, 9484, 9608, 9604, 9612, 9616, 9600,
         945,  946,  915,  960,  931,  963,  956,  964,  934,  920,  937,  948, 8734, 8709, 8712, 8745,
        8801,  177, 8805, 8804, 8992, 8993,  247, 8776,  176, 8729,  183, 8730, 8319,  178, 9632,    0
    );
    /** The height in pixel of default text. */
    private static $FONT_HEIGHT = 18;

    /** Array of the start/end column (in upper/lower nibble) of all the characters in ascii.png. */
    private $charWidth;
    /** Array of the start/end column (in upper/lower nibble) for every glyph in the /font directory. */
    private $glyphWidth;
    /**
     * Array of RGB triplets defining the 16 standard chat colors followed by 16 darker version of
     * the same colors for drop shadows.
     */
    private $colorCode;

    /** Current X coordinate at which to draw the next character. */
    private $posX               = 0;
    /** Current Y coordinate at which to draw the next character. */
    private $posY               = 0;

    /** Used to specify new red value for the current color. */
    private $red                = 255;
    /** Used to specify new green value for the current color. */
    private $green              = 255;
    /** Used to specify new blue value for the current color. */
    private $blue               = 255;

    /** Set if the "l" style (bold) is active in currently rendering string. */
    private $boldStyle          = FALSE;
    /** Set if the "o" style (italic) is active in currently rendering string. */
    private $italicStyle        = FALSE;
    /** Set if the "n" style (underlined) is active in currently rendering string. */
    private $underlineStyle     = FALSE;
    /** Set if the "m" style (strikethrough) is active in currently rendering string. */
    private $strikethroughStyle = FALSE;

    /*
     *   ooooo              o8o      .   
     *   `888'              `"'    .o8   
     *    888  ooo. .oo.   oooo  .o888oo 
     *    888  `888P"Y88b  `888    888   
     *    888   888   888   888    888   
     *    888   888   888   888    888 . 
     *   o888o o888o o888o o888o   "888" 
     */

    /**
     * Instanciates a new font renderder.
     */
    public function FontRenderer() {
        // This is how the colors are generated in Minecraft.
        for ($i = 0; $i < 32; $i++) {
            $base = ($i >> 3 & 1) * 85;
            $red = ($i >> 2 & 1) * 170 + $base;
            $green = ($i >> 1 & 1) * 170 + $base;
            $blue = ($i >> 0 & 1) * 170 + $base;
            if ($i == 6)
                $red += 85;
            if ($i >= 16) {
                $red /= 4;
                $green /= 4;
                $blue /= 4;
            }
            $this->colorCode[] = array(intval($red), intval($green), intval($blue));
        }
        $this->read_char_sizes();
        $this->read_glyph_sizes();
    }

    /**
     * Calculates the size of each character contained in ascii.png.
     *
     * The width of a character is deduced from its non-empty pixels' position.
     */
    private function read_char_sizes() {
        $img = imagecreatefrompng(__DIR__ . "/font/ascii.png");
        for ($chr = 0; $chr < 256; $chr++) {
            $sta = 8;
            $end = 0;
            for ($x = 0; $x < 8; $x++)
                for ($y = 0; $y < 8; $y++) {
                    $pix = imagecolorsforindex($img, imagecolorat($img, ($chr % 16) * 8 + $x,
                        intval($chr / 16) * 8 + $y));
                    if ($pix['red'] + $pix['green'] + $pix['blue'] > 0) {
                        $sta = min($sta, $x);
                        $end = max($end, $x + 1);
                    }
                }
            // Fix for all blank characters.
            if ($sta > $end)
                $sta = $end = 0;
            $this->charWidth[] = array($sta, $end);
        }
        // Fix for the whitespace character.
        $this->charWidth[32] = array(0, 3);
    }

    /**
     * Reads the size of each glyph contained in all unicode_page_XX.png.
     *
     * The data located in glyph_sizes.bin. gives both the start and end columns (in upper/lower
     * nibble) for every glyph in the /font directory.
     */
    private function read_glyph_sizes() {
        $file = fopen(__DIR__ . "/glyph_sizes.bin", 'r');
        foreach (unpack('c65536', fread($file, 65536)) as $byte)
            $this->glyphWidth[] = array($byte >> 4, ($byte & 0x0F) + 1);
    }

    /*
     *   oooooooooo.                                       
     *   `888'   `Y8b                                      
     *    888      888 oooo d8b  .oooo.   oooo oooo    ooo 
     *    888      888 `888""8P `P  )88b   `88. `88.  .8'  
     *    888      888  888      .oP"888    `88..]88..8'   
     *    888     d88'  888     d8(  888     `888'`888'    
     *   o888bood8P'   d888b    `Y888""8o     `8'  `8'     
     */

    /**
     * Draws the specified string.
     *
     * @param s the string
     * @param x the x coordinate
     * @param y the y coordinate
     * @param bkgd the image
     */
    public function draw_string($s, $x, $y, $bkgd) {
        $this->render_string($s, $x, $y, False, False, $bkgd);
    }

    /**
     * Draws the specified string with a shadow.
     *
     * @param s the string
     * @param x the x coordinate
     * @param y the y coordinate
     * @param bkgd the image
     */
    public function draw_string_with_shadow($s, $x, $y, $bkgd) {
        $this->render_string($s, $x + 2, $y + 2, True, False, $bkgd);
        $this->render_string($s, $x, $y, False, False, $bkgd);
    }

    /**
     * Splits and draws a string with wordwrap.
     *
     * @param s the string
     * @param x the x coordinate
     * @param y the y coordinate
     * @param w the maximum width
     * @param bkgd the image
     */
    public function draw_split_string($s, $x, $y, $w, $bkgd) {
        $this->render_split_string($s, $x, $y, $w, False, $bkgd);
    }

    /**
     * Splits and draws a string with wordwrap and a shadow.
     *
     * @param s the string
     * @param x the x coordinate
     * @param y the y coordinate
     * @param w the maximum width
     * @param bkgd the image
     */
    public function draw_split_string_with_shadow($s, $x, $y, $w, $bkgd) {
        $this->render_split_string($s, $x + 2, $y + 2, $w, True, $bkgd);
        $this->render_split_string($s, $x, $y, $w, False, $bkgd);
    }

    /*
     *   ooooo              .o88o.           
     *   `888'              888 `"           
     *    888  ooo. .oo.   o888oo   .ooooo.  
     *    888  `888P"Y88b   888    d88' `88b 
     *    888   888   888   888    888   888 
     *    888   888   888   888    888   888 
     *   o888o o888o o888o o888o   `Y8bod8P' 
     */

    /**
     * Returns the width of this character as rendered.
     *
     * @param chr the character
     */
    public function get_char_width($chr) {
        if (array_search(ord($chr), FontRenderer::$ASCII) !== FALSE) {
            $chr = array_search(ord($chr), FontRenderer::$ASCII);
            return ($this->charWidth[$chr][1] - $this->charWidth[$chr][0]) * 2;
        } else
            return $this->glyphWidth[ord($chr)][1] - $this->glyphWidth[ord($chr)][0];
    }

    /**
     * Returns the width of this string as rendered.
     *
     * @param s the string
     */
    public function get_string_width($s) {
        $w = 0;
        $bold = False;
        for ($i = 0; $i < strlen($s); $i++) {
            $chr = substr($s, $i, 1);
            if (ord($chr) == 167 && $i + 1 < strlen($s)) {
                $pos = strpos("0123456789abcdefklmnor", substr($s, $i + 1, 1));
                // Any color code, as well as the reset code, removes bold.
                if ($pos === FALSE || $pos < 16 || $pos == 21)
                    $bold = False;
                else if ($pos == 17)
                    $bold = True;
                $i++;
            } else
                // A character in bold is 2 pixels larger.
                $w += ($w > 0 ? 2 : 0) + $this->get_char_width($chr) + ($bold ? 2 : 0);
        }
        return $w;
    }

    /**
     * Returns the height of the wordwrapped string as rendered.
     *
     * @param s the string
     * @param w the maximum width
     */
    public function split_string_height($s, $w) {
        return FontRenderer::$FONT_HEIGHT * count($this->list_formatted_string_to_width($s, $w));
    }

    /*
     *   ooooo     ooo     .    o8o  oooo           
     *   `888'     `8'   .o8    `"'  `888           
     *    888       8  .o888oo oooo   888   .oooo.o 
     *    888       8    888   `888   888  d88(  "8 
     *    888       8    888    888   888  `"Y88b.  
     *    `88.    .8'    888 .  888   888  o.  )88b 
     *      `YbodP'      "888" o888o o888o 8""888P' 
     */

    /**
     * Trims a string to fit a specified width.
     *
     * The trimming will only split the string between two characters, or a character and a color
     * code. This way, no color codes are lost in the process.
     *
     * @param s the string
     * @param w the maximum width
     */
    public function trim_string_to_width($s, $w) {
        $trm = $mod = "";
        for ($i = 0; $i < strlen($s); $i++) {
            $chr = substr($s, $i, 1);
            if (ord($chr) == 167 && $i + 1 < strlen($s)) {
                $mod .= $chr . substr($s, $i + 1, 1);
                $i++;
            } else if ($this->get_string_width($trm . $mod . $chr) <= $w) {
                $trm .= $mod . $chr;
                $mod = "";
            } else
                return $trm;
        }
        return $trm;
    }

    /**
     * Breaks a string into a list of pieces that will fit a specified width.
     *
     * @param s the string
     * @param w the maximum width
     */
    public function list_formatted_string_to_width($s, $w) {
        return explode("\n", $this->wrap_formatted_string_to_width($s, $w));
    }

    /**
     * Inserts newline and formatting into a string to wrap it within the specified width.
     *
     * @param s the string
     * @param w the maximum width
     */
    public function wrap_formatted_string_to_width($s, $w) {
        $trm = "";
        while (strlen($s) > 0) {
            $ss = $this->trim_string_to_width($s, $w);
            $s = substr($s, strlen($ss));
            $trm .= (strlen($trm) > 0 ? "\n" : "") . $ss;
        }
        return $trm;
    }

    /*
     *   ooooooooo.                               .o8                     
     *   `888   `Y88.                            "888                     
     *    888   .d88'  .ooooo.  ooo. .oo.    .oooo888   .ooooo.  oooo d8b 
     *    888ooo88P'  d88' `88b `888P"Y88b  d88' `888  d88' `88b `888""8P 
     *    888`88b.    888ooo888  888   888  888   888  888ooo888  888     
     *    888  `88b.  888    .o  888   888  888   888  888    .o  888     
     *   o888o  o888o `Y8bod8P' o888o o888o `Y8bod88P" `Y8bod8P' d888b    
     */

    /**
     * Loads the character from either ascii.png or /font/glyph_XX.png into a GD image.
     *
     * @param chr the character
     */
    private function get_char($chr) {
        if (array_search($chr, FontRenderer::$ASCII) !== FALSE) {
            $chr = array_search($chr, FontRenderer::$ASCII);
            $asc = imagecreatefrompng(__DIR__ . "/font/ascii.png");
            $wid = $this->charWidth[$chr][1] - $this->charWidth[$chr][0];
            $img = imagecreatetruecolor($wid * 2, 16);
            imagealphablending($img, false);
            // Upscale the image by a factor of 2.
            imagecopyresized($img, $asc, 0, 0, ($chr % 16) * 8 + $this->charWidth[$chr][0],
                intval($chr / 16) * 8, imagesx($img), imagesy($img), $wid, 8);
        } else {
            $pge = imagecreatefrompng(sprintf(__DIR__ . "/font/unicode_page_%02x.png", $chr / 256));
            $img = imagecreatetruecolor($this->glyphWidth[$chr][1] - $this->glyphWidth[$chr][0], 16);
            imagealphablending($img, false);
            imagecopy($img, $pge, 0, 0, ($chr % 16) * 16 + $this->glyphWidth[$chr][0],
                intval(($chr % 256) / 16) * 16, imagesx($img), imagesy($img));
        }
        return $img;
    }

    /**
     * Renders a single character at the current location.
     *
     * @param chr the character
     * @param bkgd the image
     */
    private function render_char($chr, $bkgd) {
        $img = $this->get_char(ord($chr));
        if ($this->italicStyle)
            $img = $this->apply_italic($img);
        if ($this->boldStyle)
            $img = $this->apply_bold($img);
        if ($this->underlineStyle)
            $img = $this->apply_underline($img);
        if ($this->strikethroughStyle)
            $img = $this->apply_strikethrough($img);
        $img = $this->set_color($img);
        imagecopy($bkgd, $img, $this->posX, $this->posY, 0, 0, imagesx($img), imagesy($img));
        $this->posX += imagesx($img);
        // Italic, underline and strikethrough styles enlarge the image.
        if (!$this->italicStyle && !$this->underlineStyle && !$this->strikethroughStyle)
            $this->posX += 2;
    }

    /**
     * Renders a single line string at the current location and updates it accordingly.
     *
     * @param s the string
     * @param x the x coordinate
     * @param y the y coordinate
     * @param shad add a shadow
     * @param mult is multiline
     * @param bkgd the image
     */
    private function render_string($s, $x, $y, $shad, $mult, $bkgd) {
        $this->posX = $x;
        $this->posY = $y;
        if (!$mult)
            $this->reset($shad);
        for ($i = 0; $i < strlen($s); $i++) {
            $chr = substr($s, $i, 1);
            if (ord($chr) == 167 && $i + 1 < strlen($s)) {
                $pos = strpos("0123456789abcdefklmnor", substr($s, $i + 1, 1));
                // Any invalid code is considered to be a white color code.
                if ($pos === FALSE)
                    $pos = 15;
                if ($pos < 16) {
                    // Any color code resets all styles.
                    $this->reset(NULL);
                    if ($shad)
                        $pos += 16;
                    $this->red = $this->colorCode[$pos][0];
                    $this->green = $this->colorCode[$pos][1];
                    $this->blue = $this->colorCode[$pos][2];
                } else if ($pos == 17)
                    $this->boldStyle = True;
                else if ($pos == 18)
                    $this->strikethroughStyle = True;
                else if ($pos == 19)
                    $this->underlineStyle = True;
                else if ($pos == 20)
                    $this->italicStyle = True;
                else if ($pos == 21)
                    $this->reset(NULL);
                $i++;
            } else
                $this->render_char($chr, $bkgd);
        }
    }

    /**
     * Perform actual work of rendering a multi-line string with wordwrap.
     *
     * @param s the string
     * @param x the x coordinate
     * @param y the y coordinate
     * @param w the maximum width
     * @param shad add a shadow
     * @param bkgd the image
     */
    private function render_split_string($s, $x, $y, $w, $shad, $bkgd) {
        foreach ($this->list_formatted_string_to_width($s, $w) as $ss) {
            $this->render_string($ss, $x, $y, $shad, True, $bkgd);
            $y += FontRenderer::$FONT_HEIGHT;
        }
    }

    /**
     * Reset all styles; called at the start of string rendering.
     *
     * @param shad add a shadow
     */
    private function reset($shad) {
        $this->boldStyle = False;
        $this->strikethroughStyle = False;
        $this->underlineStyle = False;
        $this->italicStyle = False;
        if (!is_null($shad) && $shad)
            $this->red = $this->green = $this->blue = 63;
        else if (!is_null($shad))
            $this->red = $this->green = $this->blue = 255;
    }

    /*
     *    .oooooo..o     .               oooo                     
     *   d8P'    `Y8   .o8               `888                     
     *   Y88bo.      .o888oo oooo    ooo  888   .ooooo.   .oooo.o 
     *    `"Y8888o.    888    `88.  .8'   888  d88' `88b d88(  "8 
     *        `"Y88b   888     `88..8'    888  888ooo888 `"Y88b.  
     *   oo     .d8P   888 .    `888'     888  888    .o o.  )88b 
     *   8""88888P'    "888"     .8'     o888o `Y8bod8P' 8""888P' 
     *                       .o..P'                               
     *                       `Y8P'                                
     */

    /**
     * Applies bold to the image.
     *
     * @param src the image
     */
    private function apply_bold($src) {
        $dst = imagecreatetruecolor(imagesx($src) + 2, imagesy($src));
        imagecopy($dst, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
        imagecopy($dst, $src, 2, 0, 0, 0, imagesx($src), imagesy($src));
        return $dst;
    }

    /**
     * Applies italic to the image.
     *
     * @param src the image
     */
    private function apply_italic($src) {
        $dst = imagecreatetruecolor(imagesx($src) + 4, 16);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        for ($x = 0; $x < imagesx($src); $x++)
            for ($y = 0; $y < 16; $y++) {
                $pix = imagecolorsforindex($src, imagecolorat($src, $x, $y));
                if ($pix['red'] + $pix['green'] + $pix['blue'] > 0) {
                    $c = imagecolorallocate($dst, $pix['red'], $pix['green'], $pix['blue']);
                    // Shred the image of 4 pixels along the x axis.
                    $x1 = $x + 4 - intval($y / 4);
                    imagesetpixel($dst, $x1, $y, $c);
                }
            }
        // We enlarge the image, so we need to compensate.
        $this->posX -= 2;
        return $dst;
    }

    /**
     * Applies underline to the image.
     *
     * @param src the image
     */
    private function apply_underline($src) {
        // Italic alreay enlarges the image.
        $out = $this->italicStyle ? 0 : 4;
        $dst = imagecreatetruecolor(imagesx($src) + $out, imagesy($src) + 2);
        imagecopy($dst, $src, $out / 2, 0, 0, 0, imagesx($src), imagesy($src));
        imageline($dst, 0, imagesy($src), imagesx($src) + $out, imagesy($src),
            imagecolorallocate($dst, 255, 255, 255));
        imageline($dst, 0, imagesy($src) + 1, imagesx($src) + $out, imagesy($src) + 1,
            imagecolorallocate($dst, 255, 255, 255));
        // We enlarge the image, so we need to compensate.
        $this->posX -= $out / 2;
        return $dst;
    }

    /**
     * Applies strikethrough to the image.
     *
     * @param src the image
     */
    private function apply_strikethrough($src) {
        // Italic and underline alreay enlarge the image.
        $out = $this->italicStyle || $this->underlineStyle ? 0 : 2;
        $dst = imagecreatetruecolor(imagesx($src) + $out, imagesy($src));
        imagecopy($dst, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
        imageline($dst, 2 - $out, 6, imagesx($src) + $out, 6, imagecolorallocate($dst, 255, 255, 255));
        imageline($dst, 2 - $out, 7, imagesx($src) + $out, 7, imagecolorallocate($dst, 255, 255, 255));
        return $dst;
    }

    /**
     * Sets the color of the image.
     *
     * @param src the image
     */
    private function set_color($src) {
        $dst = imagecreatetruecolor(imagesx($src), imagesy($src));
        imagealphablending($dst, false);
        for ($x = 0; $x < imagesx($src); $x++)
            for ($y = 0; $y < imagesy($src); $y++) {
                $pix = imagecolorsforindex($src, imagecolorat($src, $x, $y));
                if ($pix['red'] + $pix['green'] + $pix['blue'] > 0)
                    imagesetpixel($dst, $x, $y,
                        imagecolorallocatealpha($src, $this->red, $this->green, $this->blue, 0));
                else
                    imagesetpixel($dst, $x, $y,
                        imagecolorallocatealpha($src, $this->red, $this->green, $this->blue, 127));
            }
        return $dst;
    }
}

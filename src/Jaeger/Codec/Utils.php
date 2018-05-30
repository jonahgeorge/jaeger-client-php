<?php

namespace Jaeger\Codec;

// TODO Can these be done without the bcmath extension?
class Utils
{
    /**
     * http://php.net/manual/en/ref.bc.php#99130
     * @param string $hex
     * @return string
     */
    public static function hexToHeader(string $hex)
    {
        if (strlen($hex) == 1) {
            return hexdec($hex);
        } else {
            $remain = substr($hex, 0, -1);
            $last = substr($hex, -1);
            return bcadd(bcmul(16, self::hexToHeader($remain)), hexdec($last));
        }
    }

    /**
     * http://php.net/manual/en/ref.bc.php#99130
     * @param string $dec
     * @return string
     */
    public static function headerToHex(string $dec)
    {
        $last = bcmod($dec, 16);
        $remain = bcdiv(bcsub($dec, $last), 16);
        if ($remain == 0) {
            return dechex($last);
        } else {
            return self::headerToHex($remain) . dechex($last);
        }
    }
}

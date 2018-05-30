<?php

namespace Jaeger\Codec;

/**
 * Error produced by invalid inputs to `gmp_init` are [uncatchable errors](https://bugs.php.net/bug.php?id=68002)
 * Because of this, we suppress the function and explicitly check the output.
 */
class Utils
{
    /**
     * @param string $hex
     * @return string|null
     */
    public static function hexdec(string $hex)
    {
        $gmp = @gmp_init($hex, 16);
        if ($gmp === false) {
            return null;
        }

        $dec = gmp_strval($gmp, 10);
        if ($dec === false) {
            return null;
        }

        return $dec;
    }

    /**
     * @param string $dec
     * @return string|null
     */
    public static function dechex(string $dec)
    {
        $gmp = @gmp_init($dec, 10);
        if ($gmp === false) {
            return null;
        }

        $hex =  gmp_strval($gmp, 16);
        if ($hex === false) {
            return null;
        }

        return $hex;
    }
}

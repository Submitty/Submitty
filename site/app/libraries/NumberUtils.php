<?php

namespace app\libraries;

/**
 * Class NumberUtils
 *
 * Utility functions for interacting with numbers
 */

class NumberUtils {

    /**
     * @param $x float The number to round
     * @param $y float The number $x to be rounded with respect to this variable $y
     * @return float The rounded result to the nearest multiple of $y
     */
    public static function roundPointValue($x, $y) {

        // No $y, no rounding
        if ($y === 0.0) {
            return $x;
        }

        $x = floatval($x);
        $q = (int) ($x / $y);
        $r = self::fmod_round($x, $y);

        // If the remainder is more than half the $y away from zero, then add one
        //  times the direction from zero to the quotient.  Multiply by $y
        return ($q + (abs($r) > $y / 2 ? ($r > 0 ? 1 : -1) : 0)) * $y;
    }

    /**
     * @param $x float
     * @param $y float
     * @return float|int
     */
    public static function fmod_round($x, $y) {
        $i = round($x / $y);
        return $x - $i * $y;
    }
}

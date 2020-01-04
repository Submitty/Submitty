<?php

namespace app\libraries;

/**
 * Class NumberUtils
 *
 * Utility functions for interacting with numbers
 */
class NumberUtils {

    /**
     *  Gives the closest number to the `value` with respect to `precision`
     * @param float $value The number to round
     * @param float $precision The precision with calculation to be made
     * @return float The rounded result to the nearest multiple of precision
     */
    public static function roundPointValue(float $value, float $precision) {

        // No $precision, no rounding
        if ($precision === 0.0) {
            return $value;
        }

        $value = floatval($value);
        $qtnt_f = $value / $precision;
        $qtnt_i = (int) ($value / $precision);

        if ($qtnt_i == $qtnt_f) {
            return $value;
        }

        $mod = $value - $qtnt_i * $precision;

        $shift = null;
        // difference is to little to be considered , No shifting needed
        if (abs($mod) < $precision / 2) {
            $shift = 0;
        }
        else {
            // shift to one unit in the direction from zero to the quotient
            $shift = $mod > 0 ? 1 : -1;
        }

        return ($qtnt_i + $shift) * $precision;
    }
}

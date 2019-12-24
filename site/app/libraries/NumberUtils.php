<?php

namespace app\libraries;

/**
 * Class NumberUtils
 *
 * Utility functions for interacting with numbers
 */

class NumberUtils {

    /**
     * NumberUtils constructor.
     * @param Core $core
     */
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    /**
     *  Gives the closest number to the `$value`  with respect to `$precision`
     * @param $value float The number to round
     * @param $precision float The precision with calculation to be made
     * @return float The rounded result to the nearest multiple of $y
     */
    public static function roundPointValue($value, $precision) {

        // No $precision, no rounding
        if ($precision === 0.0) return $value;

        $value = floatval($value);
        $qtnt_f = $value / $precision;
        $qtnt_i = (int) ($value / $precision);

        if ($qtnt_i == $qtnt_f) return $value;

        $mod = $value - $qtnt_i * $precision;

        $shift = null;
        // difference is to little to be considered , No shifting needed
        if(abs($mod) < $precision / 2 ) {
            $shift = 0 ;
        } else {
            // shift to one unit in the direction from zero to the quotient
            $shift = $mod > 0 ? 1 : -1;
        }

        return ($qtnt_i + $shift) * $precision;
    }

}

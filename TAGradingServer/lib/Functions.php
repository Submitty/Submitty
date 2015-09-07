<?php
/**
 * Various utility functions
 */

namespace lib;

class Functions {
    /**
     * Left pad a string with 0 for width of 2. Useful for dates.
     *
     * @param $string
     *
     * @return string
     */
    public static function pad($string) {
        return str_pad($string, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param $json
     *
     * @return mixed
     */
    public static function removeTrailingCommas($json){
        $json=preg_replace('/,\s*([\]}])/m', '$1', $json);
        return $json;
    }
}
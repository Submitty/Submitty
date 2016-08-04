<?php


namespace lib;

/**
 * Class Utils
 * @package lib
 */
class Utils {
    /**
     * Strips some string recursively from an array, removing it from both the
     * array's keys and its values
     *
     * @param string $string
     * @param array  $array
     */
    public static function stripStringFromArray($string, &$array) {
        foreach($array as $key => $value) {
            if (is_array($value)) {
                Utils::stripStringFromArray($string, $value);
            }
            else {
                $value = str_replace($string, "", $value);
            }
            $new_key = str_replace($string, "", $key);
            if($new_key != $key) {
                $array[$new_key] = $value;
                unset($array[$key]);
            } else {
                $array[$key] = $value;
            }

        }
    }
    
    public static function generateRandomString($bytes = 16) {
        return bin2hex(openssl_random_pseudo_bytes($bytes));
    }
}

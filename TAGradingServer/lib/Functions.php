<?php
/**
 * Various utility functions
 */

namespace lib;

class Functions {
    /**
     * Recursively goes through a directory deleting everything in it
     * before deleting the folder itself
     *
     * @param $dir
     */
    public static function recursiveRmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") {
                        Functions::recursiveRmdir($dir . "/" . $object);
                    }
                    else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    /**
     * Remove all files inside of a dir, but leave the directory
     * 
     * @param $dir
     */
    public static function emptyDir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") {
                        Functions::recursiveRmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
        }
    }

    /**
     * Create a directory if it doesn't already exist. If it's a file,
     * delete the file, and then create it as a directory
     * 
     * @param $dir
     */
    public static function createDir($dir) {
        if (!is_dir($dir)) {
            if (file_exists($dir)) {
                unlink($dir);
            }
            mkdir($dir);
        }
    }

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
<?php

namespace lib;


class FileUtils {
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
                        FileUtils::recursiveRmdir($dir . "/" . $object);
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
                        FileUtils::recursiveRmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
        }
    }
    
    /**
     * Create a directory if it doesn't already exist. If it's a file,
     * delete the file, and then try to create directory. 
     *
     * @param $dir
     * 
     * @return bool
     */
    public static function createDir($dir) {
        if (!is_dir($dir)) {
            if (file_exists($dir)) {
                unlink($dir);
            }
            return mkdir($dir);
        }
        return true;
    }
}
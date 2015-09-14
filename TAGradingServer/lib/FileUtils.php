<?php

namespace lib;


class FileUtils {
    /**
     * Recursively return all files in a directory and subdirectories, assuming the files
     * are part of the $allowed_file_extensions array (if it's non-empty).
     * 
     * @param       $dir
     * @param array $allowed_file_extensions
     * 
     * @return array
     */
    public static function getAllFiles($dir, $allowed_file_extensions=array()) {
        $return = array();
        if (file_exists($dir)) {
            // If it's not an array, assume it's a comma separated string, and use that
            if(!is_array($allowed_file_extensions)) {
                $allowed_file_extensions = explode(",", $allowed_file_extensions);
            }
    
            $check_extension = count($allowed_file_extensions) > 0;
    
            if(is_dir($dir)) {
                if($handle = opendir($dir)) {
                    while (false !== ($entry = readdir($handle))) {
                        if(in_array(strtolower($entry), array(".", "..", ".svn", ".git", ".idea", "__macosx"))) {
                            continue;
                        }
                        $file = "{$dir}/{$entry}";
                        if(is_dir($file)) {
                    
                            $return = array_merge($return, FileUtils::getAllFiles($file, $allowed_file_extensions));
                        } else {
                            $info = pathinfo($file);
                            if($check_extension) {
                                if(in_array($info['extension'], $allowed_file_extensions)) {
                                    $return[] = $file;
                                }
                            } else {
                                if(!in_array(strtolower($entry), array(".ds_store"))) {
                                    $return[] = $file;
                                }
                            }
                        }
                    }
                }
            } else if(is_file($dir)) {
                $info = pathinfo($dir);
                if($check_extension) {
                    if(in_array($info['extension'], $allowed_file_extensions)) {
                        $return[] = $dir;
                    }
                } else {
                    if(!in_array(strtolower($dir), array(".ds_store"))) {
                        $return[] = $dir;
                    }
                }
            }
        }
        return $return;
    }
    
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
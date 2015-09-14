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
        // we never want to include these files/folders as they do not contain any useful
        // information for us and would just end up making the file viewer have way too many files
        $disallowed_folders = array(".", "..", ".svn", ".git", ".idea", "__macosx");
        $disallowed_files = array('.ds_store');
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
                        if(in_array(strtolower($entry), $disallowed_folders)) {
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
                                if(!in_array(strtolower($entry), $disallowed_files)) {
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
                    if(!in_array(strtolower($dir), $disallowed_files)) {
                        $return[] = $dir;
                    }
                }
            }
        }
        sort($return);
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
<?php

namespace app\libraries;

/**
 * Class FileUtils
 *
 * Contains various useful functions for interacting with files and directories.
 */
class FileUtils {
    /**
     * Recursively return all files in a directory and subdirectories, assuming the files
     * are part of the $allowed_file_extensions array (if it's non-empty).
     *
     * TODO: Add in flag for whether or not it should work recursively
     *
     * @param string $dir
     * @param array  $allowed_file_extensions
     * @param array  $skip_files
     * @return array
     */
    public static function getAllFiles($dir, $allowed_file_extensions=array(), $skip_files=array()) {
        // we never want to include these files/folders as they do not contain any useful
        // information for us and would just end up making the file viewer have way too many files
        // case them to lowercase for easier string comparisons
        $skip_files = array_map(function($str) { return strtolower($str); }, $skip_files);

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
                        if (in_array(strtolower($entry), $skip_files)) {
                            continue;
                        }
                        $file = "{$dir}/{$entry}";
                        if(is_dir($file)) {
                            $return = array_merge($return, array($entry => FileUtils::getAllFiles($file, $allowed_file_extensions)));
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
                $check = explode("/", $dir);
                if (!in_array($check[count($check)-1], $skip_files)) {
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
            $return = array($dir => $return);
        }

        return $return;
    }

    /**
     * Recursively goes through a directory deleting everything in it
     * before deleting the folder itself
     *
     * @param string $dir
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
     * @param string $dir
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
     * @param string $dir
     * @param bool   $recursive
     *
     * @return bool
     */
    public static function createDir($dir, $recursive = false) {
        $return = true;
        if (!is_dir($dir)) {
            if (file_exists($dir)) {
                unlink($dir);
            }
            $return = @mkdir($dir, 0777, $recursive);
        }
        return $return;
    }
    
    /**
     * Given a path, return all directories in an array that are contained in that path.
     *
     * @param string $path
     *
     * @return array
     */
    public static function getAllDirs($path) {
        $disallowed_folders = array(".", "..", ".svn", ".git", ".idea", "__macosx");
        $return = array();
        if (is_dir($path)) {
            if ($handle = opendir($path)) {
                while (false !== ($entry = readdir($handle))) {
                    if(in_array(strtolower($entry), $disallowed_folders)) {
                        continue;
                    }
                    $file = "{$path}/{$entry}";
                    if(is_dir($file)) {
                        $return[] = $entry;
                    }
                }
            }
        }
        sort($return);
        return $return;
    }

    /**
     * Given a filename, load the file and then parse it as a json file, creating an associative array if
     * possible. For any loaded file, we perform a bit of regex that attempts to remove the comma on the last
     * element of any list if it exists
     *
     * @param string $filename
     *
     * @return string
     */
    public static function loadJsonFile($filename) {
        $json = json_decode(Utils::removeTrailingCommas(file_get_contents($filename)), true);
        if ($json === null) {
            return false;
        }
        return $json;
    }
    
    /**
     * Given a file, returns its mimetype based on the file's so-called maagic bytes.
     *
     * @param string $filename
     *
     * @return string
     */
    public static function getMimeType($filename) {
        $finfo = finfo_open(FILEINFO_MIME);
        $mimetype = finfo_file($finfo, $filename);
        finfo_close($finfo);
        $mimetype = explode(";", $mimetype);
        return trim($mimetype[0]);
    }
    
    /**
     * Given a filename of a zip, open the zip, and then read in each entry in the zip, getting its size
     * and then returning that to the user. If the file is not a zip, then the returned size should be 0.
     *
     * @param $filename
     *
     * @return int
     */
    public static function getZipSize($filename) {
        $size = 0;
        $zip = zip_open($filename);
        if (is_resource($zip)) {
            while ($inner_file = zip_read($zip)) {
                $size += zip_entry_filesize($inner_file);
            }
            zip_close($zip);
        }
        return $size;
    }
}
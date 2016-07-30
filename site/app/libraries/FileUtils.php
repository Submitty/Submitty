<?php

namespace app\libraries;

/**
 * Class FileUtils
 *
 * Contains various useful functions for interacting with files and directories.
 */
class FileUtils {
    /**
     * Return all files from a given directory. If the $recursive flag is true, then also scan subdirectories
     * recursively to get files from them. All subdirectories are an array pointing to their files (and additional
     * subdirecotires pointing to their files, so on and so forth). If the $recursive flag is false, then we skip
     * any subdirectories and do not include them in the returned array. If passed a file, the function will not
     * return anything. If the $flatten flag is true, any subdirectory arrays are flattened to just be elements
     * of the directory array with they key being {subdirectory}/{entry} giving a one-dimensional array.
     *
     * @param string $dir
     * @param array  $skip_files
     * @param bool   $recursive
     * @param bool   $flatten
     * @return array
     */
    public static function getAllFiles($dir, $skip_files=array(), $recursive=false, $flatten=false) {
        $skip_files = array_map(function($str) { return strtolower($str); }, $skip_files);
    
        // we ignore these files and folders as they're "junk" folders that are
        // not really useful in the context of our application that potentially
        // would just add a ton of additional files we wouldn't want or use
        $disallowed_folders = array(".", "..", ".svn", ".git", ".idea", "__macosx");
        $disallowed_files = array('.ds_store');
        $return = array();
        if (is_dir($dir)) {
            if ($handle = opendir($dir)) {
                while (false !== ($entry = readdir($handle))) {
                    $path = "{$dir}/{$entry}";
                    if (is_dir($path) && !in_array(strtolower($entry), $disallowed_folders)) {
                        if ($recursive) {
                            $temp = FileUtils::getAllFiles($path, $skip_files);
                            if ($flatten) {
                                foreach ($temp as $file => $details) {
                                    $return[$entry."/".$file] = $details;
                                }
                            }
                            else {
                                $return[$entry] = $temp;
                            }
                            
                        }
                    }
                    else if (is_file($path) && !in_array(strtolower($entry), $skip_files) &&
                        !in_array(strtolower($entry), $disallowed_files)) {
                        $return[$entry] = array('name' => $entry, 'path' => $path, 'size' => filesize($path));
                    }
                }
            }
            ksort($return);
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
                    }
                    else {
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
                    $file = "{$path}/{$entry}";
                    if(is_dir($file) && !in_array(strtolower($entry), $disallowed_folders)) {
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
    public static function readJsonFile($filename) {
        if (!is_file($filename)) {
            return false;
        }
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
     * @param string $filename
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
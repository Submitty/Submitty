<?php

namespace app\libraries;

/**
 * Class FileUtils
 *
 * Contains various useful functions for interacting with files and directories.
 */
class FileUtils {
    /**
     * Return all files from a given directory.  All subdirectories
     * are an array pointing to their files (and additional
     * subdirectories pointing to their files, so on and so forth). If
     * passed a file, the function will not return anything. If the
     * $flatten flag is true, any subdirectory arrays are flattened to
     * just be elements of the directory array with they key being
     * {subdirectory}/{entry} giving a one-dimensional array.
     *
     * @param string $dir
     * @param array  $skip_files
     * @param bool   $flatten
     * @return array
     */
    public static function getAllFiles($dir, $skip_files=array(), $flatten=false) {

        $skip_files = array_map(function($str) { return strtolower($str); }, $skip_files);
    
        // we ignore these files and folders as they're "junk" folders that are
        // not really useful in the context of our application that potentially
        // would just add a ton of additional files we wouldn't want or use
        $disallowed_folders = array(".", "..", ".svn", ".git", ".idea", "__macosx");
        $disallowed_files = array('.ds_store');

        // Return an array of all discovered files
        $return = array();

        if (is_dir($dir)) {
            if ($handle = opendir($dir)) {
                // loop over items in this directory
                while (false !== ($entry = readdir($handle))) {
                    // the full path
                    $path = "{$dir}/{$entry}";
                    // recurse into subdirectories
                    if (is_dir($path) && !in_array(strtolower($entry), $disallowed_folders)) {
                        $temp = FileUtils::getAllFiles($path, $skip_files,$flatten);
                        if ($flatten) {
                            foreach ($temp as $file => $details) {
                                if (isset($details['relative_name'])) {
                                    $details['relative_name'] = $entry."/".$details['relative_name'];
                                }
                                else {
                                    $details['relative_name'] = $entry."/".$details['name'];
                                }
                                $return[$entry."/".$file] = $details;
                            }
                        }
                        else {
                            $return[$entry] = $temp;
                        }
                    }
                    else if (is_file($path) && !in_array(strtolower($entry), $skip_files) &&
                        !in_array(strtolower($entry), $disallowed_files)) {
                        // add file to array
                        $return[$entry] = array('name' => $entry,
                                                'path' => $path,
                                                'size' => filesize($path),
                                                'relative_name' => $entry);
                    }
                }
            }
            ksort($return);
        }

        return $return;
    }

    /**
     * Recursively goes through a directory deleting everything in it before deleting the folder itself. Returns
     * true if successful, false otherwise.
     *
     * @param string $dir
     * @return bool
     */
    public static function recursiveRmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") {
                        if (!FileUtils::recursiveRmdir($dir . "/" . $object)) {
                            return false;
                        }
                    }
                    else {
                        if (!unlink($dir . "/" . $object)) {
                            return false;
                        }
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
        return true;
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
     * Create a directory if it doesn't already exist. If it's a file, delete the file, and then try to create
     * directory. Additionally, we can specify a certain mode for the directory as well as if we should recursively
     * create any folders specified in $dir if they don't all exist. The mkdir function takes into account the
     * umask setting of your computer (which by default would be something like 022). However, we set it such that
     * if we specify a mode, then we turn off the umask while we create the folder before reenabling it so we have
     * absolute power for it without having to worry about umasks (and can actually make a folder 0777 if we so
     * wanted).
     *
     * @param string $dir
     * @param int    $mode
     * @param bool   $recursive
     *
     * @return bool
     */
    public static function createDir($dir, $mode = null, $recursive = false) {
        $return = true;
        if (!is_dir($dir)) {
            if (file_exists($dir)) {
                unlink($dir);
            }
            // Umask gets applied to the mode when creating a folder, so we have to turn it off
            $umask = null;
            if ($mode !== null) {
                $umask = umask(0000);
            }
            $return = @mkdir($dir, $mode !== null ? $mode : 0777, $recursive);
            if ($mode !== null) {
                umask($umask);
            }
        }
        return $return;
    }

    /**
     * @param $path
     * @param $mode
     *
     * @return bool
     */
    public static function recursiveChmod($path, $mode) {
        $dir = new \FilesystemIterator($path);
        $files = array();
        foreach ($dir as $item) {
            if ($item->isDir()) {
                static::recursiveChmod($item->getPathname(), $mode);
            }
            $files[] = $item->getPathname();
        }

        $return = true;
        foreach ($files as $file) {
            $return = $return && chmod($file, $mode);
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

    /**
     * @param $zipname
     * @return bool
     */
    public static function checkFileInZipName($zipname) {
        $zip = zip_open($zipname);
        if(is_resource(($zip))) {
            while ($inner_file = zip_read($zip)) {
                $fname = zip_entry_name($inner_file);
                if(FileUtils::isValidFileName($fname) === false) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Given a string filename, checks the string for any quotes, brackets or slashes, returning
     * false if any of them are found within the string.
     *
     * @param string $filename
     * @return bool
     */
    public static function isValidFileName($filename) {
        if (!is_string($filename)) {
            return false;
        }
        else {
            foreach (str_split($filename) as $char) {
                if ($char == "'" ||
                    $char == '"' ||
                    $char == "\\" ||
                    $char == "<" ||
                    $char == ">") {
                    return false;
                }
            }
        }
        return true;
    }

    public static function encodeJson($string) {
        return json_encode($string, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Given some number of arguments, joins them together separating them with the DIRECTORY_SEPERATOR constant. This
     * works in the same way as os.path.join does in Python, making sure that we do not end up with any doubles of
     * a seperator and that we can start the path with a seperator if we specify the first argument as starting with
     * it.
     *
     * Credit goes to SO user Riccardo Galli (http://stackoverflow.com/users/210090/riccardo-galli) for his answer:
     * http://stackoverflow.com/a/15575293/4616655
     *
     * @return string
     */
    public static function joinPaths() {
        $paths = array();

        foreach (func_get_args() as $arg) {
            if ($arg !== '') {
                $paths[] = $arg;
            }
        }

        $sep = DIRECTORY_SEPARATOR;
        return preg_replace('#'.preg_quote($sep).'+#', $sep, join($sep, $paths));
    }
}

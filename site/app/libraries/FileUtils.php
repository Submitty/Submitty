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
                        $temp = FileUtils::getAllFiles($path, $skip_files, $flatten);
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
                            $return[$entry] = array('files' => $temp, 'path' => $path);
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
     * Given a path, return all directories in an array that are contained in that path, ignoring several
     * known names that are used for VCS, OS, or IDE systems that we can safely ignore.
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
     * @return array|boolean
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

    public static function encodeJson($string) {
        return json_encode($string, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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

    /**
     * Given some number of arguments, joins them together separating them with the DIRECTORY_SEPARATOR constant. This
     * works in the same way as os.path.join does in Python, making sure that we do not end up with any doubles of
     * a separator and that we can start the path with a separator if we specify the first argument as starting with
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

    /**
     * Given a filename (with or without the fully formed path), this function will return a string that
     * acts as a pseudo content-type for that file in any text based file that is recognized can use
     * that content-type to tell CodeMirror how to highlight the file. As such, any unrecognized file
     * extension will get the content type "text/x-sh" even though just "text" would probably be more
     * appropriate. This is a weaker check for binary files than FileUtils::getMimeType which does
     * some basic analysis of the actual file to determine the information as opposed to just the filename.
     *
     * @param $filename
     * @return null|string
     */
    public static function getContentType($filename){
        if ($filename === null) {
            return null;
        }
        switch (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            // pdf
            case 'pdf':
                $content_type = "application/pdf";
                break;
            // images
            case 'png':
                $content_type = "image/png";
                break;
            case 'jpg':
            case 'jpeg':
                $content_type = "image/jpeg";
                break;
            case 'gif':
                $content_type = "image/gif";
                break;
            case 'bmp':
                $content_type = "image/bmp";
                break;
            // text
            case 'c':
                $content_type = 'text/x-csrc';
                break;
            case 'cpp':
            case 'cxx':
            case 'h':
            case 'hpp':
            case 'hxx':
                $content_type = 'text/x-c++src';
                break;
            case 'java':
                $content_type = 'text/x-java';
                break;
            case 'py':
                $content_type = 'text/x-python';
                break;
            case 'csv':
                $content_type = 'text/csv';
                break;
            case 'xlsx':
                $content_type = 'spreadsheet/xlsx';
                break;
            case 'txt':
                $content_type = 'text/plain';
                break;
            default:
                $content_type = 'text/x-sh';
                break;
        }
        return $content_type;
    }

    /**
     * Given a set of new files and a set of existing files (both constructed with the filenames as keys, e.g. 
     * array("foo.txt" => true, "bar.cpp" => "baz"), a php hashset), this function returns an array
     * mapping the original names in $new_files to new names that will not conflict among themselves or
     * with the filenames in $existing_files.
     *
     * To use a numerically indexed array (with the filenames as values), call array_flip on it before passing it
     * as a parameter (note that doing so will remove duplicate elements and thus duplicates won't work).
     *
     * @param $existing_files
     * @param $new_files
     * @return array
     */
    public static function renameNoClobber($new_files, $existing_files) {
        $new_files_set = array_flip($new_files);
        $existing_files_set = array_flip($existing_files);
        foreach($new_files as $file) {
            if(isset($existing_files_set[$file])) {
                $original_file = $file;
                unset($new_files_set[$file]);
                $parts = explode(".", $file);
                $num = 3;
                if(strlen($parts[0])) {
                    $start = substr($parts[0], 0, strlen($parts[0]) - 1);
                    $end = substr($parts[0], strlen($parts[0]) - 1, strlen($parts[0]));
                    // if $start ends with "_version_" and $end is numeric
                    if(substr_compare($start, "_version_", strlen($start) - 9) === 0 && is_numeric($end)) { 
                        $parts[0] = $start.($end + 1);
                        $num = $end + 1;
                    }
                    else {
                        $parts[0] .= "_version_2"; 
                    }
                }
                else {
                    $parts[0] .= "_version_2"; 
                }
                $file = implode(".", $parts);
                for($c = $num; isset($existing_files_set[$file]) || isset($new_files_set[$file]); $c++) {
                    $parts = explode(".", $file);
                    $parts[0] = substr($parts[0], 0, strlen($parts[0]) - 1).$c;
                    $file = implode(".", $parts);
                }
                $new_files_set[$file] = $original_file;
            }
            else {
                $new_files_set[$file] = $file;
            }
        }
        return array_flip($new_files_set);
    }

}

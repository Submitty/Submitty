<?php

namespace app\libraries;

use app\exceptions\FileReadException;

/**
 * Class FileUtils
 *
 * Contains various useful functions for interacting with files and directories.
 */
class FileUtils {
    const IGNORE_FOLDERS = [".svn", ".git", ".idea", "__macosx"];
    const IGNORE_FILES = ['.ds_store'];
    const ALLOWED_IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif'];

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
    public static function getAllFiles(string $dir, array $skip_files = [], bool $flatten = false): array {
        $skip_files = array_map(function ($str) {
            return strtolower($str);
        }, $skip_files);

        // we ignore these files and folders as they're "junk" folders that are
        // not really useful in the context of our application that potentially
        // would just add a ton of additional files we wouldn't want or use
        $disallowed_folders = FileUtils::IGNORE_FOLDERS;
        $disallowed_files = array_merge(FileUtils::IGNORE_FILES, $skip_files);

        // Return an array of all discovered files
        $return = [];

        if (is_dir($dir)) {
            foreach (new \FilesystemIterator($dir) as $file) {
                /** @var \SplFileInfo $file */
                $entry = $file->getFilename();
                $path = FileUtils::joinPaths($dir, $entry);
                // recurse into subdirectories
                if (is_dir($path) && !in_array(strtolower($entry), $disallowed_folders)) {
                    $temp = FileUtils::getAllFiles($path, $skip_files, $flatten);
                    if ($flatten) {
                        foreach ($temp as $file => $details) {
                            $details['relative_name'] = $entry . "/" . $details['relative_name'];
                            $return[$entry . "/" . $file] = $details;
                        }
                    }
                    else {
                        $return[$entry] = ['files' => $temp, 'path' => $path];
                    }
                }
                elseif (is_file($path) && !in_array(strtolower($entry), $disallowed_files)) {
                    // add file to array
                    $return[$entry] = [
                        'name' => $entry,
                        'path' => $path,
                        'size' => $file->getSize(),
                        'relative_name' => $entry
                    ];
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
    public static function recursiveRmdir(string $dir): bool {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
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
            rmdir($dir);
        }
        return true;
    }

    /**
     * Copies all image files from a source folder ($src) and each of its
     * subfolders to a flattened destination folder ($dst), making all
     * filenames lowercase, ignoring subfolders that match our IGNORE_FOLDERS
     *
     * Ex:
     *  src/
     *   image.jpg
     *   sub/
     *     image_2.jpg
     *  dest/
     *   image.jpg
     *   image_2.jpg
     *
     * @param string $src
     * @param string $dst
     */
    public static function recursiveFlattenImageCopy(string $src, string $dst): void {
        foreach (new \FilesystemIterator($src) as $iter) {
            /** @var \SplFileInfo $iter */
            if ($iter->isFile()) {
                if (FileUtils::isValidImage($iter->getPathname())) {
                    copy($iter->getPathname(), FileUtils::joinPaths($dst, strtolower($iter->getFilename())));
                }
            }
            elseif ($iter->isDir()) {
                if (in_array(strtolower($iter->getFilename()), FileUtils::IGNORE_FOLDERS)) {
                    continue;
                }
                FileUtils::recursiveFlattenImageCopy($iter->getPathname(), $dst);
            }
        }
    }

    /**
     * Given a directory, gets the path of all files in that directory and its
     * subdirectories (recursively), trimming the first $path_length characters
     * off the string.
     */
    public static function getAllFilesTrimSearchPath(string $search_path, int $path_length): array {
        return array_map(function ($entry) use ($path_length) {
            return substr($entry['path'], $path_length, strlen($entry['path']) - $path_length);
        }, array_values(FileUtils::getAllFiles($search_path, [], true)));
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
    public static function createDir($dir, $recursive = false, $mode = null) {
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
                    if (is_dir($file) && !in_array(strtolower($entry), $disallowed_folders)) {
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

    public static function encodeJson($data) {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Given some data, encode it as pretty printed JSON and write it to a file.
     *
     * @param string $filename filename to write data to
     * @param mixed  $data JSON data to write to the file
     * @return bool
     */
    public static function writeJsonFile(string $filename, $data): bool {
        $data = FileUtils::encodeJson($data);
        if ($data === false) {
            return false;
        }
        return static::writeFile($filename, $data);
    }

    /**
     * Given some data, write it to a file.
     * @param string $filename
     * @param mixed  $data
     * @return bool
     */
    public static function writeFile(string $filename, $data): bool {
        if (file_exists($filename) && !is_writable($filename)) {
            return false;
        }
        return file_put_contents($filename, $data) !== false;
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
        if (is_resource(($zip))) {
            while ($inner_file = zip_read($zip)) {
                $fname = zip_entry_name($inner_file);
                if (FileUtils::isValidFileName($fname) === false) {
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
                if (
                    $char == "'"
                    || $char == '"'
                    || $char == "\\"
                    || $char == "<"
                    || $char == ">"
                ) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Given an image path, validates that the mime type of the file is "image"
     * and its subtype is one of our allowed subtypes
     */
    public static function isValidImage(string $image_path): bool {
        [$mime_type, $mime_subtype] = explode('/', mime_content_type($image_path), 2);
        return $mime_type === 'image' && in_array($mime_subtype, FileUtils::ALLOWED_IMAGE_TYPES);
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
        return preg_replace('#' . preg_quote($sep) . '+#', $sep, join($sep, $paths));
    }

    /**
     * Given a filename (with or without the fully formed path), this function will return a string that
     * acts as a pseudo content-type for that file in any text based file that is recognized can use
     * that content-type to tell CodeMirror how to highlight the file. As such, any unrecognized file
     * extension will get the content type "text/x-sh" even though just "text" would probably be more
     * appropriate. This is a weaker check for binary files than mime_content_type which does
     * some basic analysis of the actual file to determine the information as opposed to just the filename.
     */
    public static function getContentType(?string $filename): ?string {
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
     * Search over a file to see if it contains specified words
     *
     * @param string $file Path to file to search through
     * @param array $words An array of words to look for
     * @throws FileReadException Unable to either locate or read the file
     * @return bool true if any words in the $words array were found in the file, false otherwise
     */
    public static function areWordsInFile(string $file, array $words) {
        // Get file contents
        $file_contents = @file_get_contents($file);

        // Check for failure
        if ($file_contents == false) {
            throw new FileReadException('Unable to either locate or read the file contents');
        }

        $words_detected = false;

        // Foreach word in the words array check to see if it exist in the file
        foreach ($words as $word) {
            $word_was_found = strpos($file_contents, $word);

            if ($word_was_found) {
                $words_detected = true;
                break;
            }
        }

        return $words_detected;
    }

    /**
     * Attempt to open a zip archive and return the response
     *
     * @param string $file Path to the zip archive
     * @return bool | int returns true on success or an error code otherwise
     */
    public static function getZipFileStatus($file) {
        $zip = new \ZipArchive();
        //open file with additional checks
        return $zip->open($file, \ZipArchive::CHECKCONS);
    }

    /**
     * Given an array of uploaded files, makes sure they are properlly uploaded
     *
     * @param array $files - should be in the same format as the $_FILES[] variable
     * @return array representing the status of each file
     * e.g. array('name' => 'foo.txt','type' => 'application/octet-stream', 'error' =>
     *            'success','size' => 100, 'is_zip' => false, 'success' => true)
     * if $files is null returns failed => no files sent to validate
     */
    public static function validateUploadedFiles($files) {
        if (empty($files)) {
            return array("failed" => "No files sent to validate");
        }

        $ret = array();
        $num_files = count($files['name']);
        $max_size = Utils::returnBytes(ini_get('upload_max_filesize'));

        for ($i = 0; $i < $num_files; $i++) {
            //extract the values from each file
            $name = $files['name'][$i];
            $tmp_name = $files['tmp_name'][$i];
            $type = mime_content_type($tmp_name);

            $zip_status = FileUtils::getZipFileStatus($tmp_name);
            $errors = [];
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = ErrorMessages::uploadErrors($files['error'][$i]);
            }

            //check if its a zip file
            $is_zip = $type === 'application/zip';
            if ($is_zip) {
                $zip_status = FileUtils::getZipFileStatus($tmp_name);
                if ($zip_status !== \ZipArchive::ER_OK) {
                    $err_tmp = ErrorMessages::getZipErrorMessage($zip_status);
                    if ($err_tmp != "No error.") {
                        $errors[] = $err_tmp;
                    }
                }
                else {
                    $size = FileUtils::getZipSize($tmp_name);
                    if (!FileUtils::checkFileInZipName($tmp_name)) {
                        $errors[] = "Invalid filename within zip file";
                    }
                }
            }

            //for zip files use the size of the contents in case it gets extracted
            $size = $is_zip ? FileUtils::getZipSize($tmp_name) : $files['size'][$i];

            //manually check against set size limit
            //incase the max POST size is greater than max file size
            if ($size > $max_size) {
                $errors[] = "File \"" . $name . "\" too large got (" . Utils::formatBytes("mb", $size) . ")";
            }

            //check filename
            if (!FileUtils::isValidFileName($name)) {
                $errors[] = "Invalid filename";
            }

            $success = true;
            if (count($errors) > 0) {
                $success = false;
            }

            $ret[] = [
                'name' => $name,
                'type' => $type,
                'error' => $success ? "No error." : implode(" ", $errors),
                'size' => $size,
                'is_zip' => $is_zip,
                'success' => $success,
            ];
        }

        return $ret;
    }
}

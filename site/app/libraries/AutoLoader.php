<?php
/**
 * AutoLoader.php
 */

namespace app\libraries;

/**
 * Class AutoLoader
 * @package lib
 *
 * Autoloads files for classes assuming that class name and file name are
 * are the same
 */
class AutoLoader {
    /**
     * @var array
     */
    static private $classNames = array();

    private function __construct() { }
    private function __clone() { }

    /**
     * Store the filename (sans extension) & full path of all ".php" files found
     *
     * @param string $dirName: dirName to iterate through adding in php files
     * @param boolean $use_namespace: class requires namespace
     * @param string $namespace: namespace to append to class name
     */
    public static function registerDirectory($dirName, $use_namespace = false, $namespace="") {
        $di = new \DirectoryIterator($dirName);
        foreach ($di as $file) {
            if ($file->isDir() && !$file->isLink() && !$file->isDot()) {
                // recurse into directories other than a few special ones
                self::registerDirectory($file->getPathname(), $use_namespace, $namespace."\\".$file->getFilename());
            } elseif (substr($file->getFilename(), -4) === '.php') {
                // save the class name / path of a .php file found
                $className = substr($file->getFilename(), 0, -4);
                if ($use_namespace) {
                    $className = "$namespace\\".$className;
                }
                AutoLoader::registerClass($className, $file->getPathname());
            }
        }
    }

    /**
     * @param string $className:
     * @param string $fileName:
     */
    public static function registerClass($className, $fileName) {
        AutoLoader::$classNames[$className] = $fileName;
    }

    /**
     * @param $className
     */
    public static function loadClass($className) {
        if (isset(AutoLoader::$classNames[$className])) {
            require_once(AutoLoader::$classNames[$className]);
        }
    }

    /**
     * @param $className
     */
    public static function unregisterClass($className) {
        unset(AutoLoader::$classNames[$className]);
    }

    /**
     * @return array
     */
    public static function getClasses() {
        return AutoLoader::$classNames;
    }

    /**
     * Generates the relative path to the root of the repo
     * such that if you ran "cd $append" you would get there
     *
     * @param string $path: path we want to examine
     *
     * @return string: relative path to root of repo
     */
    public static function getPathToRoot($path) {
        $dir = AutoLoader::getRootName();
        $current_dir = explode("/", $path);
        $start = array_search($dir, $current_dir);
        $start = ($start == null) ? 0 : $start;
        $append = "";
        for ($i = $start; $i < count($current_dir)-1; $i++) {
            $append .= "../";
        }
        return $append;
    }

    /**
     * Returns the root name of the git repo.
     *
     * Useful if someone has renamed it from "TAGradingServer"
     * to "hwgrading" or something, which might break
     * autoloading without this
     *
     * @return mixed: Root name of the git repo
     */
    private static function getRootName() {
        $dir = explode("/",str_replace("/site/lib", "", __DIR__));
        return end($dir);
    }

    /**
     * Emptys the AutoLoader of all its detected classes
     */
    public static function emptyLoader() {
        AutoLoader::$classNames = array();
    }

    /**
     *
     * @param $classes
     */
    public static function setClasses($classes) {
        AutoLoader::$classNames = $classes;
    }

}

spl_autoload_register(array('app\\libraries\\AutoLoader', 'loadClass'));
?>
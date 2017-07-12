<?php
/**
 * AutoLoader.php
 */

namespace app\libraries;

/**
 * Class AutoLoader
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

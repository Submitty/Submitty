<?php

namespace app\libraries;

use app\exceptions\FileNotFoundException;
use app\exceptions\IniException;
use app\exceptions\IOException;

/**
 * Class IniParser
 *
 * Helper to interact with ini files, both reading them in as well as writing out an array
 * to the ini file. Loosely based on austinhyde's IniParser (@link https://github.com/austinhyde/IniParser)
 * however stripped of some of its more advanced (and unnecessary features) as well as slight changes to
 * type handling.
 */
class IniParser {
    /**
     * Reads in an ini file giving an associate array which is indexed by
     * section names. Additionally, we further decode the array that PHP
     * gives us in its builtin function to other primitive types than just
     * string. "true", "on", "yes" evaluate to bool true while "false", "off",
     * "no" evaluate to bool false, and "null" evaluates to null. Additionally,
     * if the string is a numeric, we will parse it to either int or float as
     * appropriate
     *
     * @param string $filename
     * @throws IniException | FileNotFoundException
     * @return array
     */
    public static function readFile($filename) {
        // @codeCoverageIgnoreStart
        if (!function_exists('parse_ini_file')) {
            throw new IniException("parse_ini_file needs to be enabled");
        }
        // @codeCoverageIgnoreEnd

        if (!file_exists($filename)) {
            throw new FileNotFoundException("Could not find ini file to parse: {$filename}");
        }

        $parsed = @parse_ini_file($filename, true, INI_SCANNER_RAW);
        if ($parsed === false) {
            $e = error_get_last();
            $basename = basename($filename);
            throw new IniException("Error reading ini file '{$basename}': {$e['message']}");
        }
        return static::decode($parsed);
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private static function decode($value) {
        if (is_array($value)) {
            foreach ($value as $i => &$subvalue) {
                $subvalue = static::decode($subvalue);
            }
        }

        if (is_string($value)) {
            // Do we have a boolean?
            $test_value = strtolower($value);
            if ($test_value == "true" || $test_value == "yes" || $test_value == "on") {
                $value = true;
            }
            else if ($test_value == "false" || $test_value == "no" || $test_value == "off") {
                $value = false;
            }
            // Or do we have a null?
            else if ($test_value == "null") {
                $value = null;
            }
            // or is it a number?
            else if (is_numeric($value)) {
                if (intval($value) == floatval($value)) {
                    $value = intval($value);
                }
                else {
                    $value = floatval($value);
                }
            }
        }
        return $value;


    }

    /**
     * @param string $filename
     * @param array  $array
     *
     */
    public static function writeFile($filename, $array) {
        $to_write = "";
        foreach ($array as $key => $value) {
            // is this a section?
            if (is_array($value)) {
                if (static::isSection($value)) {
                    if ($to_write != "") {
                        $to_write .= "\n";
                    }
                    $to_write .= "[{$key}]\n";
                    foreach ($value as $kkey => $vvalue) {
                        if (is_array($vvalue)) {
                            foreach ($vvalue as $vvvalue) {
                                if (is_array($vvvalue)) {
                                    throw new IniException("Cannot have nested arrays in ini files");
                                }
                                static::addElement($to_write, $kkey."[]", $vvvalue);
                            }
                        }
                        else {
                            static::addElement($to_write, $kkey, $vvalue);
                        }
                    }
                }
                else {
                    foreach ($value as $kkey => $vvalue) {
                        if (is_array($vvalue)) {
                            throw new IniException("Cannot have nested arrays in ini files");
                        }
                        self::addElement($to_write, $key."[]", $vvalue);
                    }
                }
            }
            else {
                self::addElement($to_write, $key, $value);
            }
        }

        if (@file_put_contents($filename, $to_write) === false) {
            throw new IOException("Could not write ini file {$filename}");
        }
    }

    private static function addElement(&$to_write, $key, $value) {
        $to_write .= "{$key}=";
        if (is_string($value)) {
            $to_write .= "\"{$value}\"\n";
        }
        else {
            if (is_bool($value)) {
                $to_write .= (($value === true) ? "true" : "false")."\n";
            }
            else if ($value === null) {
                $to_write .= "null\n";
            }
            else {
                $to_write .= "{$value}\n";
            }
        }
    }
    /**
     * Given an array, we test if it might be a section header or just an array
     * within the
     * @param $array
     *
     * @return bool
     */
    private static function isSection($array) {
        foreach ($array as $key => $value) {
            if (!is_numeric($key)) {
                return true;
            }
        }
        return false;
    }
}
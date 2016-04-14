<?php

namespace app\libraries;

use app\exceptions\FileNotFoundException;
use app\exceptions\IniException;
use app\exceptions\IOException;

/**
 * Class IniParser
 * @package app\libraries
 *
 *
 */
class IniParser {
    /**
     * @param $filename
     *
     * @return array
     */
    public static function readFile($filename) {
        if (!function_exists('parse_ini_file')) {
            throw new IniException("parse_ini_file needs to be enabled");
        }

        if (!file_exists($filename)) {
            throw new FileNotFoundException("Could not find ini file to parse: {$filename}");
        }

        $parsed = parse_ini_file($filename, true, INI_SCANNER_RAW);
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
            if ($value == "true" || $value == "yes" || $value == "on") {
                $value = true;
            }
            else if ($value == "false" || $value == "no" || $value == "off") {
                $value = false;
            }
            // Or do we have a null?
            else if ($value == "null") {
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
     * @param $filename
     * @param $config
     *
     */
    public static function writeFile($filename, $config) {
        $to_write = "";
        foreach ($config as $key => $value) {
            // is this a section?
            if (is_array($value)) {
                if (static::isSection($value)) {
                    $to_write .= "[{$key}]\n";
                    foreach ($value as $kkey => $vvalue) {
                        if (is_array($vvalue)) {
                            foreach ($vvalue as $vvvalue) {
                                $to_write .= "{$kkey}[]=";
                                if (is_string($vvvalue)) {
                                    $to_write .= "\"{$vvvalue}\"\n";
                                }
                                else {
                                    $to_write .= "{$vvvalue}\n";
                                }
                            }
                        }
                        else {
                            $to_write .= "{$key}=";
                            if (is_string($vvalue)) {
                                $to_write .= "\"{$vvalue}\"\n";
                            }
                            else {
                                $to_write .= "{$vvalue}\n";
                            }
                        }
                    }
                }
            }
            else {
                $to_write .= "{$key}=";
                if (is_string($value)) {
                    $to_write .= "\"{$value}\"\n";
                }
                else {
                    $to_write .= "{$value}\n";
                }
            }
        }

        if (file_put_contents($filename, $to_write) === false) {
            throw new IOException("Could not write ini file");
        }
    }

    /**
     * Given an array, we test if it might be a section or just an array
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
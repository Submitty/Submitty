<?php

namespace lib;

/**
 * Class DatabaseUtils
 * @package lib
 */
class DatabaseUtils {

    /**
     * Get instance of DatabaseUtils
     *
     * Creates a DatabaeUtils if one doesn't exist
     * and then subsequently returns the same object in the future
     *
     * @return DatabaseUtils The instance of DatabaseUtils
     */
    public static function getInstance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new DatabaseUtils();
        }
        return $instance;
    }

    /**
     * Don't allow anyone outside of DatabaseUtils and subclasses
     * to initailze a singleton object
     */
    private function __construct() { }

    /**
     * Don't allow someone to clone a singleton object
     *
     * @codeCoverageIgnore
     */
    private function __clone() { }

    /**
     * Converts a Postgres style array to a PHP array
     *
     * Postgres returns a text that contains their array when querying
     * through the PDO interface, meaning it has to processed into a PHP
     * array post Database for it to be actually usable.
     *
     * ex: "{1, 2, 3, 4}" => array(1, 2, 3, 4)
     *
     * @param string $text the text representation of the postgres array
     * @param int $start index to start looking through $text at
     * @param int $end index of $text where we exist current pgArrayToPhp call
     * @param bool $parseBools set to true to convert "true"/"false" to booleans instead of strings
     *
     * @return array PHP array representation
     */
    public static function fromPGToPHPArray($text, $start=0, &$end=null, $parseBools=false) {
        $text = trim($text);

        if(empty($text) || $text[0] != "{") {
            return array();
        } else if(is_string($text)) {
            $return = array();
            $element = "";
            $in_string = false;
            $have_string = false;
            $in_array = false;
            $quot = "";
            for ($i = $start; $i < strlen($text); $i++) {
                $ch = $text[$i];
                if (!$in_array && !$in_string && $ch == "{") {
                    $in_array = true;
                }
                else if (!$in_string && $ch == "{") {
                    $return[] = DatabaseUtils::fromPGToPHPArray($text,$i,$i);
                }
                else if (!$in_string && $ch == "}") {
                    if ($have_string) {
                        $return[] = $element;
                    }
                    else if (strlen($element) > 0) {
                        if (is_numeric($element)) {
                            $return[] = $element + 0;
                        }
                        else {
                            if (!$parseBools) {
                                $return[] = $element;
                            }
                            else {
                                $return[] = (strtolower($element) == "true") ? true : false;
                            }
                        }
                    }
                    $end = $i;
                    return $return;
                }
                else if (($ch == '"' || $ch == "'") && !$in_string) {
                    $in_string = true;
                    $quot = $ch;
                }
                else if ($in_string && $ch == $quot) {
                    $in_string = false;
                    $have_string = true;
                }
                else if (!$in_string && $ch == " ") {
                    continue;
                }
                else if (!$in_string && $ch == ",") {
                    if ($have_string) {
                        $return[] = $element;
                    }
                    else if (strlen($element) > 0) {

                        if (is_numeric($element)) {
                            $return[] = $element + 0;
                        }
                        else {
                            if (!$parseBools) {
                                $return[] = $element;
                            }
                            else {
                                $return[] = (strtolower($element) == "true") ? true : false;
                            }
                        }
                    }
                    $element = "";
                }
                else {
                    $element .= $ch;
                }
            }
        }

        return array();
    }

    /**
     * Converts a PHP array into a Postgres text array
     *
     * Gets a PHP array ready to be put into a postgres array field
     * as part of a database update/insert
     *
     * ex: Array(1, 2, 3, 4) => "{1, 2, 3, 4)"
     *
     * @param array $array PHP array
     *
     * @return string Postgres text representation of array
     */
    public static function fromPHPToPGArray($array) {
        if (!is_array($array)) {
            return '{}';
        }
        $elements = array();
        foreach ($array as $e) {
            if (is_array($e)) {
                $elements[]= DatabaseUtils::fromPHPToPGArray($e);
            }
            else if (is_string($e)) {
                $elements[] .= "\"{$e}\"";
            }
            else if (is_bool($e)) {
                $elements[] .= ($e) ? "true" : "false";
            }
            else {
                $elements[] .= "{$e}";
            }
        }
        $text = "{".implode(", ", $elements)."}";
        return $text;
    }
}

?>
<?php

namespace app\libraries\database;


class PostgresqlDatabase extends AbstractDatabase {
    protected $host;
    protected $port;
    protected $dbname;
    protected $unix_socket;

    /**
     * @inheritdoc
     */
    public function __construct($connection_params) {
        parent::__construct($connection_params);
        if (isset($connection_params['unix_socket'])) {
            $this->unix_socket = $connection_params['unix_socket'];
        }
        else {
            if (isset($connection_params['host'])) {
                $this->host = $connection_params['host'];
            }
            if (isset($connection_params['port'])) {
                $this->port = $connection_params['port'];
            }
        }
        if (isset($connection_params['dbname'])) {
            $this->dbname = $connection_params['dbname'];
        }
    }

    protected function getDSN() {
        $params = array();
        if (isset($this->unix_socket)) {
            $params[] = "host={$this->unix_socket}";
        }
        else {
            if ($this->host !== null) {
                $params[] = "host={$this->host}";
            }
            if ($this->port !== null) {
                $params[] = "port={$this->port}";
            }
        }

        if (isset($this->dbname)) {
            $params[] = "dbname={$this->dbname}";
        }

        return 'pgsql:'.implode(';', $params);
    }

    /**
     * Converts a Postgres style array to a PHP array
     *
     * Postgres returns a text that contains their array when querying
     * through the PDO interface, meaning it has to processed into a PHP
     * array post Database for it to be actually usable.
     *
     * ex: "{1, 2, 3, 4}" => array(1, 2, 3, 4)
     *
     * @param string $text        the text representation of the postgres array
     * @param bool   $parse_bools set to true to convert "true"/"false" to booleans instead of strings
     * @param int    $start       index to start looking through $text at
     * @param int    $end         index of $text where we exist current pgArrayToPhp call
     *
     * @return array PHP array representation
     */
    public function fromDatabaseArrayToPHP($text, $parse_bools = false, $start=0, &$end=null) {
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
                    $return[] = $this->fromDatabaseArrayToPHP($text, $parse_bools, $i, $i);
                }
                else if (!$in_string && $ch == "}") {
                    $this->parsePGArrayValue($element, $have_string, $parse_bools, $return);
                    $end = $i;
                    return $return;
                }
                else if (($ch == '"' || $ch == "'") && !$in_string) {
                    $in_string = true;
                    $quot = $ch;
                }
                else if ($in_string && $ch == $quot && $text[$i-1] == "\\") {
                    $element = substr($element, 0, -1).$ch;
                }
                else if ($in_string && $ch == $quot && $text[$i-1] != "\\") {
                    $in_string = false;
                    $have_string = true;
                }
                else if (!$in_string && $ch == " ") {
                    continue;
                }
                else if (!$in_string && $ch == ",") {
                    $this->parsePGArrayValue($element, $have_string, $parse_bools, $return);
                    $have_string = false;
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
     * Method that given an element figures out how to add it to the $return array whether it's a string, a numeric,
     * a null, a boolean, or an unquoted string
     *
     * @param string $element     element to analyze
     * @param bool   $have_string do we have a quoted element (using either ' or " characters around the string)
     * @param bool   $parse_bools set to true to convert "true"/"false" to booleans instead of strings
     * @param array  &$return     this is the array being built to contain the parsed PG array
     */
    private function parsePGArrayValue($element, $have_string, $parse_bools, &$return) {
        if ($have_string) {
            $return[] = $element;
        }
        else if (strlen($element) > 0) {
            if (is_numeric($element)) {
                $return[] = ($element + 0);
            }
            else {
                $lower = strtolower($element);
                if ($parse_bools && in_array($lower, array("true", "t", "false", "f"))) {
                    $return[] = ($lower === "true" || $lower === "t") ? true : false;
                }
                else if ($lower == "null") {
                    $return[] = null;
                }
                else {
                    $return[] = $element;
                }
            }
        }
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
    public function fromPHPArrayToDatabase($array) {
        if (!is_array($array)) {
            return '{}';
        }
        $elements = array();
        foreach ($array as $e) {
            if ($e === null) {
                $elements[] = "null";
            }
            else if (is_array($e)) {
                $elements[] = DatabaseUtils::fromPHPToPGArray($e);
            }
            else if (is_string($e)) {
                $elements[] .= '"'. str_replace('"', '\"', $e) .'"';
            }
            else if (is_bool($e)) {
                $elements[] .= ($e === true) ? "true" : "false";
            }
            else {
                $elements[] .= "{$e}";
            }
        }
        $text = "{".implode(", ", $elements)."}";
        return $text;
    }
}

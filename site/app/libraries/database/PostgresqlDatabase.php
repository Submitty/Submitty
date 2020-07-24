<?php

namespace app\libraries\database;

class PostgresqlDatabase extends AbstractDatabase {
    protected $host;
    protected $port;
    protected $dbname;

    /**
     * @inheritdoc
     *
     * Connection parameters for the PostgreSQL driver are:
     * username
     * password
     * host
     * port
     * dbname
     */
    public function __construct($connection_params = []) {
        parent::__construct($connection_params);
        if (isset($connection_params['host'])) {
            $this->host = $connection_params['host'];
        }
        if (isset($connection_params['port'])) {
            $this->port = $connection_params['port'];
        }
        if (isset($connection_params['dbname'])) {
            $this->dbname = $connection_params['dbname'];
        }
    }

    public function getDSN() {
        $params = [];
        if ($this->host !== null) {
            $params[] = "host={$this->host}";
        }

        if ($this->port !== null && ($this->host === null || $this->host[0] !== '/')) {
            $params[] = "port={$this->port}";
        }

        if (isset($this->dbname)) {
            $params[] = "dbname={$this->dbname}";
        }

        return 'pgsql:' . implode(';', $params);
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

     *
     * @return array PHP array representation
     */
    public function fromDatabaseToPHPArray($text, $parse_bools = false, $start = 0, &$end = null) {
        $text = trim($text);

        if (empty($text) || $text[0] != "{") {
            return [];
        }
        elseif (is_string($text)) {
            $return = [];
            $element = "";
            $in_string = false;
            $have_string = false;
            $in_array = false;
            $quot = "";
            for ($i = $start; $i < strlen($text); $i++) {
                $ch = $text[$i];
                if (!$in_array && !$in_string && $ch === "{") {
                    $in_array = true;
                }
                elseif (!$in_string && $ch === "{") {
                    $return[] = $this->fromDatabaseToPHPArray($text, $parse_bools, $i, $i);
                }
                elseif (!$in_string && $ch === "}") {
                    $this->parsePGArrayValue($element, $have_string, $parse_bools, $return);
                    $end = $i;
                    return $return;
                }
                elseif (($ch === '"' || $ch === "'") && !$in_string) {
                    $in_string = true;
                    $quot = $ch;
                }
                elseif ($in_string && $ch == "\\" && strlen($text) > $i) {
                    if ($text[$i + 1] === "\\") {
                        $element .= "\\";
                        $i++;
                    }
                    elseif ($text[$i + 1] === "\"") {
                        $element .= "\"";
                        $i++;
                    }
                    else {
                        $element .= $text[$i];
                    }
                }
                elseif (!$in_string && $ch === "\\") {
                    //Insert literal \
                    $element .= $text[$i];
                }
                elseif ($in_string && $ch === $quot) {
                    $in_string = false;
                    $have_string = true;
                }
                elseif (!$in_string && $ch === " ") {
                    continue;
                }
                elseif (!$in_string && $ch === ",") {
                    $this->parsePGArrayValue($element, $have_string, $parse_bools, $return);
                    $have_string = false;
                    $element = "";
                }
                else {
                    $element .= $ch;
                }
            }
        }

        return [];
    }

    /**
     * Method that given an element figures out how to add it to the $return array whether it's a string, a numeric,
     * a null, a boolean, or an unquoted string
     *
     * @param int|string $element     element to analyze
     * @param bool       $have_string do we have a quoted element (using either ' or " characters around the string)
     * @param bool       $parse_bools set to true to convert "true"/"false" to booleans instead of strings
     * @param array      $return     this is the array being built to contain the parsed PG array
     */
    private function parsePGArrayValue($element, $have_string, $parse_bools, &$return) {
        if ($have_string) {
            $return[] = $element;
        }
        elseif (strlen($element) > 0) {
            if (is_numeric($element)) {
                $return[] = ($element + 0);
            }
            else {
                $lower = strtolower($element);
                if ($parse_bools && in_array($lower, ["true", "t", "false", "f"])) {
                    $return[] = ($lower === "true" || $lower === "t");
                }
                elseif ($lower == "null") {
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
    public function fromPHPToDatabaseArray($array) {
        if (!is_array($array)) {
            return '{}';
        }
        $elements = [];
        foreach ($array as $e) {
            if ($e === null) {
                $elements[] = "null";
            }
            elseif (is_array($e)) {
                $elements[] = $this->fromPHPToDatabaseArray($e);
            }
            elseif (is_string($e)) {
                //Turn every \ into \\ that's either preceding a " another \ or the end
                $elements[] = '"' . str_replace('"', '\"', preg_replace('/\\\\(?=["\\\\]|$)/', '\\\\\\\\', $e)) . '"';
            }
            elseif (is_bool($e)) {
                $elements[] = ($e === true) ? "true" : "false";
            }
            else {
                $elements[] = "{$e}";
            }
        }
        return "{" . implode(", ", $elements) . "}";
    }

    public function convertBoolean($value) {
        return ($value === true || $value == "t" || $value == "true") ? 'true' : 'false';
    }
}

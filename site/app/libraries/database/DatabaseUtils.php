<?php

declare(strict_types=1);

namespace app\libraries\database;

use app\libraries\DateUtils;

class DatabaseUtils {
    public static function formatQuery(string $sql, ?array $params): string {
        if ($params === null) {
            return $sql;
        }

        foreach ($params as $param) {
            if ($param instanceof \DateTime) {
                $param = DateUtils::dateTimeToString($param);
            }
            if (is_array($param)) {
                $str_param = json_encode($param);

                /*
                // in case queries with json function calls are not formatted properly
                $json_functions = ["json_array_length", "json_array_elements", "json_array_elements_text"];
                $function_call = false;
                $qmark_info = [];
                preg_match('/\?/', $sql, $qmark_info, PREG_OFFSET_CAPTURE);
                foreach ($json_functions as $json_func) {
                    $func_name_offset = $qmark_info[0][1]-strlen($json_func)-1;
                    if ($func_name_offset >= 0 && substr($sql, $func_name_offset, strlen($json_func)) === $json_func) {
                        $function_call = true;
                        break;
                    }
                }
                if ($function_call) {
                    $param = "'{$str_param}'";
                }
                else {
                    $param = substr($str_param, 1, strlen($str_param) - 2);
                }
                */

                // remove square brackets
                $param = substr($str_param, 1, strlen($str_param) - 2);
            }
            elseif (!is_numeric($param)) {
                $param = "'{$param}'";
            }
            $sql = preg_replace('/\?/', strval($param), $sql, 1);
        }
        return $sql;
    }
}

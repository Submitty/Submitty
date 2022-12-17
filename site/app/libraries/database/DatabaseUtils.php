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
                $param = substr($str_param, 1, strlen($str_param) - 2);
            }
            elseif (!is_numeric($param)) {
                $param = "'{$param}'";
            }
            $sql = preg_replace('/\?/', $param, $sql, 1);
        }
        return $sql;
    }
}

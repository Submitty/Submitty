<?php

declare(strict_types=1);

namespace app\libraries\database;

use app\libraries\DateUtils;

class DatabaseUtils {
    public static function formatQuery($sql, $params): string {
        if (is_null($params)) {
            return '';
        }

        foreach ($params as $param) {
            if ($param instanceof \DateTime) {
                $param = DateUtils::dateTimeToString($param);
            }
            $sql = preg_replace('/\?/', is_numeric($param) ? $param : "'{$param}'", $sql, 1);
        }
        return $sql;
    }
}

<?php

declare(strict_types=1);

namespace app\libraries\database;

use app\libraries\DateUtils;
use app\libraries\Utils;

class DatabaseUtils {
    public static function formatQuery(string $sql, ?array $params): string {
        if ($params === null) {
            return $sql;
        }

        foreach ($params as $param) {
            if ($param instanceof \DateTime) {
                $param = DateUtils::dateTimeToString($param);
            }
            if (gettype($param) === "array") {
                $param = Utils::arrayToCommaSepString($param);
            }
            elseif (!is_numeric($param)) {
                $param = "'{$param}'";
            }
            $sql = preg_replace('/\?/', $param, $sql, 1);
        }
        return $sql;
    }
}

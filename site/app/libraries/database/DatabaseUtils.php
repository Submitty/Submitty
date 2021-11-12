<?php

declare(strict_types=1);

namespace app\libraries\database;

class DatabaseUtils {
    public static function formatQuery($sql, $params): string {
        foreach ($params as $param) {
            $sql = preg_replace('/\?/', is_numeric($param) ? $param : "'{$param}'", $sql, 1);
        }
        return $sql;
    }
}

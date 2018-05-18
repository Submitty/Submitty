<?php

namespace tests\app\libraries\database;

use app\libraries\database\DatabaseQueries;

abstract class AbstractDatabaseQueriesTester extends \PHPUnit\Framework\TestCase {
    protected static $expected_functions;

    public static function setUpBeforeClass() {
        $filter = function($name) { return $name !== '__construct'; };
        self::$expected_functions = array_filter(get_class_methods(DatabaseQueries::class), $filter);
        sort(self::$expected_functions);
    }
}

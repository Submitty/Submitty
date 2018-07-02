<?php

namespace tests\app\libraries\database;

use app\libraries\database\PostgresqlDatabaseQueries;

class PostgresqlDatabaseQueriesTester extends AbstractDatabaseQueriesTester {
    public function testHasAllFunctions() {
        $filter = function($name) { return $name !== '__construct'; };
        $actual = array_filter(get_class_methods(PostgresqlDatabaseQueries::class), $filter);
        sort($actual);
        $this->assertEquals(self::$expected_functions, $actual);
    }
}

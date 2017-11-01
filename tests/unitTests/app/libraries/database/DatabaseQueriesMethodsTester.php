<?php

namespace unitTests\app\libraries\database;

use app\libraries\AutoLoader;
use app\libraries\database\DatabaseQueries;
use app\libraries\Utils;

/**
 * Class DatabaseQueriesLinterTester
 *
 * This class is purely a style checker for DatabaseQueries and the classes that
 * extend from it to ensure that no class that extends implements its own
 * public functions, meaning that we keep things in lock-step and not have the potential
 * of some functions being available only for one database provider.
 */
class DatabaseQueriesMethodsTester extends \PHPUnit_Framework_TestCase {
    public function testFunctions() {
        $filter = function($name) { return $name !== '__construct'; };
        $expected = array_filter(get_class_methods(DatabaseQueries::class), $filter);
        sort($expected);
        foreach (AutoLoader::getClasses() as $classname => $file) {
            if (Utils::endsWith($classname, "DatabaseQueries") && $classname !== DatabaseQueries::class) {
                $actual = array_filter(get_class_methods($classname), $filter);
                sort($actual);
                $this->assertEquals($expected, $actual, "Make sure all queries in {$classname} are also in DatabaseQueries.");
            }
        }
    }
}

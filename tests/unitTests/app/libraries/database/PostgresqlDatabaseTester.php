<?php

namespace unitTests\app\libraries\database;


use app\libraries\database\PostgresqlDatabase;

class PostgresqlDatabaseTester extends \PHPUnit_Framework_TestCase {
    public function testPostgresqlHost() {
        $database = new PostgresqlDatabase(array('host' => 'localhost'));
        $this->assertEquals("pgsql:host=localhost", $database->getDSN());
    }

    public function testPostgresqlPort() {
        $database = new PostgresqlDatabase(array('port' => '15432'));
        $this->assertEquals("pgsql:port=15432", $database->getDSN());
    }

    public function arrayData() {
        return array(
            array("{}", array()),
            array("{{}, {}}", array(array(),array())),
            array("{1, 2, 3, 4}", array(1,2,3,4)),
            array("{1.5, 2, 4, 5.5}", array(1.5,2,4,5.5)),
            array('{{"breakfast", "consulting"}, {"meeting", "lunch"}}',
                  array(array('breakfast','consulting'), array('meeting', 'lunch'))),
            array('{{"breakfast", "test"}, {{"another", "array"}, {"test", "me"}}}',
                  array(array('breakfast','test'),array(array('another','array'),array('test','me')))),
            array('{"true", "false"}', array("true", "false")),
            array('{"M5"}', array('M5')),
            array('{"aa", null, "null", null}', array('aa', null, "null", null)),
            array('{"aaa\"bbb\nccc"}', array('aaa"bbb\nccc')),
            array('{"yes?{}", "", "blah/yes(more).'."\n".'", "\"aaaa. \""}', array("yes?{}", "", "blah/yes(more).\n", '"aaaa. "'))
        );
    }

    /**
     * @dataProvider arrayData
     *
     * @param $pgArray
     * @param $phpArray
     */
    public function testFromPHPToDatabaseArray($pgArray, $phpArray) {
        $database = new PostgresqlDatabase();
        $this->assertEquals($pgArray, $database->fromPHPToDatabaseArray($phpArray));
    }

    /**
     * @dataProvider arrayData
     *
     * @param $pgArray
     * @param $phpArray
     */
    public function testFromDatabaseToPHPArray($pgArray, $phpArray) {
        $database = new PostgresqlDatabase();
        $this->assertEquals($phpArray, $database->fromDatabaseToPHPArray($pgArray));
    }
}

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
        $this->assertEquals('pgsql:port=15432', $database->getDSN());
    }

    public function testPostgresqlDbname() {
        $database = new PostgresqlDatabase(array('dbname' => 'submitty'));
        $this->assertEquals('pgsql:dbname=submitty', $database->getDSN());
    }

    public function arrayData() {
        return array(
            array('{}', array()),
            array('{{}, {}}', array(array(),array())),
            array('{1, 2, 3, 4}', array(1,2,3,4)),
            array('{1.5, 2, 4, 5.5}', array(1.5,2,4,5.5)),
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

    /**
     * Test invalid PG arrays that should convert to empty arrays in php
     */
    public function testBadPostgrestoPHP() {
        $database = new PostgresqlDatabase();
        $this->assertEquals(array(), $database->fromDatabaseToPHPArray('{,}'));
        $this->assertEquals(array(array(),array()), $database->fromDatabaseToPHPArray('{{,},{,}}'));
    }
    /**
     * Test that invalid input for PG returns null
     */
    public function testInvalidInputPostgres() {
        $database = new PostgresqlDatabase();
        $this->assertEquals(array(), $database->fromDatabaseToPHPArray(''));
        $this->assertEquals(array(), $database->fromDatabaseToPHPArray(null));
        $this->assertEquals(array(), $database->fromDatabaseToPHPArray('abcd'));
        $this->assertEquals(array(), $database->fromDatabaseToPHPArray(1));
        $this->assertEquals(array(), $database->fromDatabaseToPHPArray('{1,2'));
    }

    /**
     * Test that invalid input for PHP
     */
    public function testInvalidInputPHP() {
        $database = new PostgresqlDatabase();
        $this->assertEquals('{}', $database->fromPHPToDatabaseArray(null));
        /** @noinspection PhpParamsInspection */
        $this->assertEquals('{}', $database->fromPHPToDatabaseArray(''));
        /** @noinspection PhpParamsInspection */
        $this->assertEquals('{}', $database->fromPHPToDatabaseArray('abcd'));
        /** @noinspection PhpParamsInspection */
        $this->assertEquals('{}', $database->fromPHPToDatabaseArray(1));
    }

    public function testNullPGArray() {
        $database = new PostgresqlDatabase();
        $this->assertEquals(array(null), $database->fromDatabaseToPHPArray('{NULL}'));
    }

    public function testSeatNumberPGToPHP() {
        $database = new PostgresqlDatabase();
        $this->assertEquals(array('M5'), $database->fromDatabaseToPHPArray('{M5}'));
    }

    public function testBooleanPGToPHP() {
        $database = new PostgresqlDatabase();
        $this->assertEquals(array(true, false, 'test'), $database->fromDatabaseToPHPArray("{true, false, 'test'}", true));
    }

    public function testBooleanPHPToPG() {
        $database = new PostgresqlDatabase();
        $this->assertEquals('{true, false}', $database->fromPHPToDatabaseArray(array(true, false)));
    }

    public function testShortBooleanPGToPHP() {
        $database = new PostgresqlDatabase();
        $this->assertEquals(array(true, false, false, true), $database->fromDatabaseToPHPArray('{t, f, f, t}', true));
    }

    public function testNullCasingPGToPHP() {
        $database = new PostgresqlDatabase();
        $this->assertEquals(array(null, null, null), $database->fromDatabaseToPHPArray('{null, NULL, NuLl}'));
    }
}

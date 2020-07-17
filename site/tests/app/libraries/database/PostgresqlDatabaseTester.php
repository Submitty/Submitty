<?php

namespace tests\app\libraries\database;

use app\libraries\database\PostgresqlDatabase;

class PostgresqlDatabaseTester extends \PHPUnit\Framework\TestCase {
    public function testPostgresqlHost() {
        $database = new PostgresqlDatabase(['host' => 'localhost']);
        $this->assertEquals("pgsql:host=localhost", $database->getDSN());
    }

    public function testPostgresqlPort() {
        $database = new PostgresqlDatabase(['port' => '15432']);
        $this->assertEquals('pgsql:port=15432', $database->getDSN());
    }

    public function testPostgresqlDbname() {
        $database = new PostgresqlDatabase(['dbname' => 'submitty']);
        $this->assertEquals('pgsql:dbname=submitty', $database->getDSN());
    }

    public function testPostgresqlUnixSocket() {
        $database = new PostgresqlDatabase(['host' => '/var/run/postgresql', 'port' => 5432]);
        $this->assertEquals('pgsql:host=/var/run/postgresql', $database->getDSN());
    }

    public function arrayData() {
        return [
            ['{}', []],
            ['{{}, {}}', [[],[]]],
            ['{1, 2, 3, 4}', [1,2,3,4]],
            ['{1.5, 2, 4, 5.5}', [1.5,2,4,5.5]],
            ['{{"breakfast", "consulting"}, {"meeting", "lunch"}}',
                  [['breakfast','consulting'], ['meeting', 'lunch']]],
            ['{{"breakfast", "test"}, {{"another", "array"}, {"test", "me"}}}',
                  [['breakfast','test'],[['another','array'],['test','me']]]],
            ['{"true", "false"}', ["true", "false"]],
            ['{"M5"}', ['M5']],
            ['{"aa", null, "null", null}', ['aa', null, "null", null]],
            ['{"aaa\"bbb\nccc"}', ['aaa"bbb\nccc']],
            ['{"yes?{}", "", "blah/yes(more).' . "\n" . '", "\"aaaa. \""}', ["yes?{}", "", "blah/yes(more).\n", '"aaaa. "']],
            ['{"\\\\"}', ["\\"]],
            ['{"a,b,c\\\\"}', ["a,b,c\\"]],
            ['{"a,b,c\'"}', ["a,b,c'"]],
            ['{"a,b,c\\""}', ["a,b,c\""]],
            ['{{"a"}, {"b"}, {"c\\""}}', [["a"],["b"],["c\""]]]
        ];
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
        $this->assertEquals([], $database->fromDatabaseToPHPArray('{,}'));
        $this->assertEquals([[],[]], $database->fromDatabaseToPHPArray('{{,},{,}}'));
    }
    /**
     * Test that invalid input for PG returns null
     */
    public function testInvalidInputPostgres() {
        $database = new PostgresqlDatabase();
        $this->assertEquals([], $database->fromDatabaseToPHPArray(''));
        $this->assertEquals([], $database->fromDatabaseToPHPArray(null));
        $this->assertEquals([], $database->fromDatabaseToPHPArray('abcd'));
        $this->assertEquals([], $database->fromDatabaseToPHPArray(1));
        $this->assertEquals([], $database->fromDatabaseToPHPArray('{1,2'));
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
        $this->assertEquals([null], $database->fromDatabaseToPHPArray('{NULL}'));
    }

    public function testSeatNumberPGToPHP() {
        $database = new PostgresqlDatabase();
        $this->assertEquals(['M5'], $database->fromDatabaseToPHPArray('{M5}'));
    }

    public function testBooleanPGToPHP() {
        $database = new PostgresqlDatabase();
        $this->assertEquals([true, false, 'test'], $database->fromDatabaseToPHPArray("{true, false, 'test'}", true));
    }

    public function testBooleanPHPToPG() {
        $database = new PostgresqlDatabase();
        $this->assertEquals('{true, false}', $database->fromPHPToDatabaseArray([true, false]));
    }

    public function testShortBooleanPGToPHP() {
        $database = new PostgresqlDatabase();
        $this->assertEquals([true, false, false, true], $database->fromDatabaseToPHPArray('{t, f, f, t}', true));
    }

    public function testNullCasingPGToPHP() {
        $database = new PostgresqlDatabase();
        $this->assertEquals([null, null, null], $database->fromDatabaseToPHPArray('{null, NULL, NuLl}'));
    }

    public function booleanConverts() {
        return [
            [true, 'true'],
            [1, 'false'],
            [false, 'false'],
            [null, 'false'],
            ["a", 'false']
        ];
    }

    /**
     * @dataProvider booleanConverts
     *
     * @param $value
     * @param $expected
     */
    public function testConvertBooleanFalseString($value, $expected) {
        $database = new PostgresqlDatabase();
        $this->assertEquals($expected, $database->convertBoolean($value));
    }
}

<?php

namespace tests\unitTests\app\libraries;

use \app\libraries\DatabaseUtils;

class DatabaseUtilsTester extends \PHPUnit_Framework_TestCase {
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
     * Test various valid PHP -> PG Array conversions
     *
     * @param string $pgArray
     * @param array $phpArray
     *
     * @dataProvider arrayData
     */
    public function testFromPHPToPostgres($pgArray, $phpArray) {
        $this->assertEquals($pgArray, DatabaseUtils::fromPHPToPGArray($phpArray));
    }

    /**
     * Test various valid PG -> PHP array conversions
     *
     * @param string $pgArray
     * @param array $phpArray
     *
     * @dataProvider arrayData
     */
    public function testFromPostgresToPHP($pgArray, $phpArray) {
        $this->assertEquals($phpArray, DatabaseUtils::fromPGToPHPArray($pgArray));
    }

    /**
     * Test invalid PG arrays that should convert to empty arrays in php
     */
    public function testBadPostgrestoPHP() {
        $this->assertEquals(array(), DatabaseUtils::fromPGToPHPArray("{,}"));
        $this->assertEquals(array(array(),array()), DatabaseUtils::fromPGToPHPArray("{{,},{,}}"));
    }

    /**
     * Test that invalid input for PG returns null
     */
    public function testInvalidInputPostgres() {
        $this->assertEquals(array(), DatabaseUtils::fromPGToPHPArray(""));
        $this->assertEquals(array(), DatabaseUtils::fromPGToPHPArray(null));
        $this->assertEquals(array(), DatabaseUtils::fromPGToPHPArray("abcd"));
        $this->assertEquals(array(), DatabaseUtils::fromPGToPHPArray(1));
        $this->assertEquals(array(), DatabaseUtils::fromPGToPHPArray("{1,2"));
    }

    /**
     * Test that invalid input for PHP
     */
    public function testInvalidInputPHP() {
        $this->assertEquals("{}", DatabaseUtils::fromPHPToPGArray(null));
        /** @noinspection PhpParamsInspection */
        $this->assertEquals("{}", DatabaseUtils::fromPHPToPGArray(""));
        /** @noinspection PhpParamsInspection */
        $this->assertEquals("{}", DatabaseUtils::fromPHPToPGArray("abcd"));
        /** @noinspection PhpParamsInspection */
        $this->assertEquals("{}", DatabaseUtils::fromPHPToPGArray(1));
    }

    public function testNullPGArray() {
        $this->assertEquals(array(null), DatabaseUtils::fromPGToPHPArray("{NULL}"));
    }

    public function testSeatNumberPGToPHP() {
        $this->assertEquals(array("M5"), DatabaseUtils::fromPGToPHPArray("{M5}"));
    }

    public function testBooleanPGToPHP() {
        $this->assertEquals(array(true, false, 'test'), DatabaseUtils::fromPGToPHPArray("{true, false, 'test'}", true));
    }

    public function testBooleanPHPToPG() {
        $this->assertEquals('{true, false}', DatabaseUtils::fromPHPToPGArray(array(true, false)));
    }

    public function testShortBooleanPGToPHP() {
        $this->assertEquals(array(true, false, false, true), DatabaseUtils::fromPGToPHPArray("{t, f, f, t}", true));
    }

    public function testNullCasingPGToPHP() {
        $this->assertEquals(array(null, null, null), DatabaseUtils::fromPGToPHPArray("{null, NULL, NuLl}"));
    }
}
<?php

namespace tests\unitTests\app\libraries;

use \app\libraries\Utils;

class UtilsTester extends \PHPUnit_Framework_TestCase {
    public function testPad1() {
        $this->assertEquals("00", Utils::pad("0"));
    }

    public function testPad2() {
        $this->assertEquals("00", Utils::pad("00"));
    }

    public function testRemovePerfect() {
        $this->assertEquals("{a:'a'}", Utils::removeTrailingCommas("{a:'a'}"));
    }

    public function testRemoveSimple() {
        $json = '[ "element": { "a", "b", }, ]';
        $expected = '[ "element": { "a", "b"}]';
        $this->assertEquals($expected, Utils::removeTrailingCommas($json));
    }

    public function testGenerateRandomString() {
        $first = Utils::generateRandomString();
        $second = Utils::generateRandomString();
        $this->assertNotEquals($first, $second);
        $this->assertEquals(32, strlen($first));
        $this->assertEquals(32, strlen($second));
    }

    public function testGenerateRandomString2() {
        $this->assertEquals(16, strlen(Utils::generateRandomString(8)));
    }
}
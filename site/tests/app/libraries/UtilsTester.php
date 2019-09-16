<?php

namespace tests\app\libraries;

use \app\libraries\Utils;

class UtilsTester extends \PHPUnit\Framework\TestCase {
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

    public function stringStarts() {
        return array(
            array("test", "test", true),
            array("test", "tes", true),
            array("test", "te", true),
            array("test", "t", true),
            array("test", "", true),
            array("test", "st", false)
        );
    }

    /**
     * @dataProvider stringStarts
     *
     * @param $haystack
     * @param $needle
     * @param $result
     */
    public function testStartsWith($haystack, $needle, $result) {
        $this->assertEquals(Utils::startsWith($haystack, $needle), $result);
    }

    public function stringEnds() {
        return array(
            array("test", "test", true),
            array("test", "est", true),
            array("test", "st", true),
            array("test", "t", true),
            array("test", "", true),
            array("test", "te", false)
        );
    }

    /**
     * @dataProvider stringEnds
     *
     * @param $haystack
     * @param $needle
     * @param $result
     */
    public function testEndsWith($haystack, $needle, $result) {
        $this->assertEquals(Utils::endsWith($haystack, $needle), $result);
    }

    public function testPrepareHtmlString() {
        $string = "<test\n\ntest>";
        $this->assertEquals("&lt;test<br />\n<br />\ntest&gt;", Utils::prepareHtmlString($string));
    }

    public function testStripStringFromArray() {
        $array = array(
            "test/aa",
            array(
                "test/test2/aa",
                "bb"
            )
        );
        $expected = array("/aa", array("/2/aa", "bb"));
        $this->assertEquals($expected, Utils::stripStringFromArray("test", $array));
    }

    public function testStripStringFromArrayNull() {
        $this->assertNull(Utils::stripStringFromArray("test", null));
        $this->assertNull(Utils::stripStringFromArray(null, array()));
        $this->assertNull(Utils::stripStringFromArray(1, array()));
    }

    public function elementDataProvider() {
        return [
            [[], null, null],
            [[1], 1, 1],
            [[1, 2, 3], 1, 3]
        ];
    }

    /**
     * @dataProvider elementDataProvider
     */
    public function testGetLastArrayElement($array, $first_element, $last_element) {
        $this->assertEquals($last_element, Utils::getLastArrayElement($array));
    }

    /**
     * @dataProvider elementDataProvider
     */
    public function testGetFirstArrayElement($array, $first_element, $last_element) {
        $this->assertEquals($first_element, Utils::getFirstArrayElement($array));
    }

    public function imageDataProvider() {
        return [
            ['test', false],
            ['test.txt', false],
            ['test.gif', true],
            ['test.jpg', true],
            ['test.jpeg', true],
            ['test.png', true]
        ];
    }
    /**
     * @dataProvider imageDataProvider
     */
    public function testIsImage($name, $is_image) {
        $this->assertEquals($is_image, Utils::isImage($name));
    }
}

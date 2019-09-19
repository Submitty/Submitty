<?php

namespace tests\app\libraries;

use \app\libraries\Utils;

class UtilsTester extends \PHPUnit\Framework\TestCase {
    use \phpmock\phpunit\PHPMock;

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

    public function testSetCookieString() {
        $cookie = $this->getFunctionMock("app\\libraries", "setcookie");
        $cookie
            ->expects($this->once())
            ->willReturnCallback(
                function($name, $value, $expires, $path, $domain, $secure) {
                    $this->assertEquals('test', $name);
                    $this->assertEquals('data', $value);
                    $this->assertEquals(100, $expires);
                    $this->assertEquals('/', $path);
                    $this->assertEquals('', $domain);
                    $this->assertFalse($secure);
                    return true;
                }
            );
        $this->assertTrue(Utils::setCookie('test', 'data', 100));
    }

    public function testSetCookieArray() {
        $cookie = $this->getFunctionMock("app\\libraries", "setcookie");
        $cookie
            ->expects($this->once())
            ->willReturnCallback(
                function($name, $value, $expires, $path, $domain, $secure) {
                    $this->assertEquals('test', $name);
                    $this->assertEquals('{"a":true}', $value);
                    $this->assertEquals(100, $expires);
                    $this->assertEquals('/', $path);
                    $this->assertEquals('', $domain);
                    $this->assertFalse($secure);
                    return true;
                }
            );
        $this->assertTrue(Utils::setCookie('test', ['a' => true], 100));
    }

    public function testSetCookieHttps() {
        try {
            $_SERVER['HTTPS'] = 'on';
            $cookie = $this->getFunctionMock("app\\libraries", "setcookie");
            $cookie
                ->expects($this->once())
                ->willReturnCallback(
                    function($name, $value, $expires, $path, $domain, $secure) {
                        $this->assertEquals('test', $name);
                        $this->assertEquals('{"a":true}', $value);
                        $this->assertEquals(100, $expires);
                        $this->assertEquals('/', $path);
                        $this->assertEquals('', $domain);
                        $this->assertTrue($secure);
                        return true;
                    }
                );
            $this->assertTrue(Utils::setCookie('test', ['a' => true], 100));
        }
        finally {
            unset($_SERVER['HTTPS']);
        }
    }

    public function testSetCookieHttpsOff() {
        try {
            $_SERVER['HTTPS'] = 'off';
            $cookie = $this->getFunctionMock("app\\libraries", "setcookie");
            $cookie
                ->expects($this->once())
                ->willReturnCallback(
                    function($name, $value, $expires, $path, $domain, $secure) {
                        $this->assertEquals('test', $name);
                        $this->assertEquals('{"a":true}', $value);
                        $this->assertEquals(100, $expires);
                        $this->assertEquals('/', $path);
                        $this->assertEquals('', $domain);
                        $this->assertFalse($secure);
                        return true;
                    }
                );
            $this->assertTrue(Utils::setCookie('test', ['a' => true], 100));
        }
        finally {
            unset($_SERVER['HTTPS']);
        }
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

    public function uploadedImageProvider() {
        return [
            [__TEST_DATA__.'/images/test_image.png', true],
            [__TEST_DATA__.'/images/test_image.jpg', true],
            [__TEST_DATA__.'/.gitkeep', false]
        ];
    }
    /**
     * @dataProvider uploadedImageProvider
     */
    public function testCheckUploadedImageFile($image_path, $expected) {
        try {
            $_FILES['test'] = [
                'name' => [basename($image_path)],
                'tmp_name' => [$image_path],
                'type' => ['image/png'],
                'error' => [UPLOAD_ERR_OK],
                'size' => [123]
            ];
            $this->assertEquals($expected, Utils::checkUploadedImageFile('test'));
        }
        finally {
            $_FILES = [];
        }
    }

    /**
     * @runInSeparateProcess
     */
    public function testCheckUploadedImageFileImageSizeFalse() {
        try {
            $_FILES['test'] = [
                'name' => [basename(__TEST_DATA__.'/images/test_image.png')],
                'tmp_name' => [__TEST_DATA__.'/images/test_image.png'],
                'type' => ['image/png'],
                'error' => [UPLOAD_ERR_OK],
                'size' => [123]
            ];
            $this->getFunctionMock("app\\libraries", 'getimagesize')
                ->expects($this->once())
                ->willReturn(false);
            $this->assertFalse(Utils::checkUploadedImageFile('test'));
        }
        finally {
            $_FILES = [];
        }
    }

    public function testCheckUploadedImageInvalidId() {
        $this->assertFalse(Utils::checkUploadedImageFile('invalid'));
    }

    public function comparableNullableGtProvider() {
        return [
            [null, null, false],
            [null, 1, false],
            [1, null, false],
            [1, 1, false],
            [1, 2, false],
            [2, 1, true]
        ];
    }

    /**
     * @dataProvider comparableNullableGtProvider
     */
    public function testCompareNullableGt($left, $right, $expected) {
        $this->assertEquals($expected, Utils::compareNullableGt($left, $right));
    }

    public function safeCalcPercentProvider() {
        return [
            [100, 5, false, 20],
            [-100, 5, false, -20],
            [100, 5, true, 1.0],
            [-100, 5, true, 0.0],
            [0, 10, false, 0],
            [0, 10, true, 0],
            [5, 10, false, 0.5],
            [5, 10, true, 0.5]
        ];
    }

    /**
     * @dataProvider safeCalcPercentProvider
     */
    public function testSafeCalcPercent($dividend, $divisor, $clamp, $expected) {
        $this->assertEquals($expected, Utils::safeCalcPercent($dividend, $divisor, $clamp));
    }

    public function testSafeCalcPercentNan() {
        $this->assertNan(Utils::safeCalcPercent(1, 0));
    }

    public function testRemoveStudentWithId() {
        $data = [
            ['user_id' => 'test'],
            ['user_id' => 'test2']
        ];
        $expected = [1 => ['user_id' => 'test2']];
        $this->assertEquals($expected, Utils::removeStudentWithId($data, 'user_id', 'test'));
        $this->assertEquals($data, Utils::removeStudentWithId($data, 'user_id', ''));
    }

    public function returnBytesProvider() {
        return [
            ['10B', 10],
            ['1M', 1048576],
            ['2M', 2097152],
            ['1G', 1073741824],
            ['2G', 2147483648],
            ['1K', 1024],
            ['2K', 2048]
        ];
    }

    /**
     * @dataProvider returnBytesProvider
     */
    public function testReturnBytes($byte_string, $expected) {
        $this->assertEquals($expected, Utils::returnBytes($byte_string));
    }

    public function formatBytesProvider() {
        return [
            ['b', 0, '0B'],
            ['b', 100, '100B'],
            ['b', 1024, '1024B'],
            ['kb', 1024, '1KB'],
            ['kb', 2048, '2KB'],
            ['b', 1048576, '1048576B'],
            ['kb', 1048576, '1024KB'],
            ['mb', 1048576, '1MB'],
            ['mb', 2097152, '2MB'],
        ];
    }

    /**
     * @dataProvider formatBytesProvider
     */
    public function testFormatBytes($format, $bytes, $expected) {
        $this->assertEquals($expected, Utils::formatBytes($format, $bytes));
    }
}

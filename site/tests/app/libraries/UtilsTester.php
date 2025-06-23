<?php

namespace tests\app\libraries;

use app\libraries\Core;
use app\libraries\Utils;
use app\models\User;
use tests\BaseUnitTest;

class UtilsTester extends BaseUnitTest {
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

    public function testValidPassword() {
        $good_password = 'GoodPassword#123';
        $this->assertTrue(Utils::isValidPassword($good_password));
        $bad_password_length = 'Bad#123';
        $this->assertFalse(Utils::isValidPassword($bad_password_length));
        $bad_password_numbers = 'BadPasswordWithoutNumbers@';
        $this->assertFalse(Utils::isValidPassword($bad_password_numbers));
        $bad_password_special = 'BadPasswordWithoutSpecialCharacter123';
        $this->assertFalse(Utils::isValidPassword($bad_password_special));
        $bad_password_capital = 'badpasswordwithoutcapital#123';
        $this->assertFalse(Utils::isValidPassword($bad_password_capital));
        $bad_password_lowercase = 'BADPASSWORDWITHOUTLOWERCASE#123';
        $this->assertFalse(Utils::isValidPassword($bad_password_lowercase));
    }

    public function testAcceptedEmail() {
        $core = $this->createMockCore(['accepted_emails' => ['gmail.com']]);
        $reqs = $core->getConfig()->getAcceptedEmails();
        $good_email_gmail = 'goodemail@gmail.com';
        $this->assertTrue(Utils::isAcceptedEmail($reqs, $good_email_gmail));
        $bad_email_extension = 'goodemail@notanextension.edu';
        $this->assertFalse(Utils::isAcceptedEmail($reqs, $bad_email_extension));
        $accepts_multiple_at_signs = 'good@email@testing@gmail.com';
        $this->assertTrue(Utils::isAcceptedEmail($reqs, $accepts_multiple_at_signs));
    }

    public function testGenerateRandomString2() {
        $this->assertEquals(16, strlen(Utils::generateRandomString(8)));
    }

    public function testPrepareHtmlString() {
        $string = "<test\n\ntest>";
        $this->assertEquals("&lt;test<br />\n<br />\ntest&gt;", Utils::prepareHtmlString($string));
    }

    public static function elementDataProvider() {
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
                function ($name, $value, $expires, $path, $domain, $secure) {
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
                function ($name, $value, $expires, $path, $domain, $secure) {
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
                    function ($name, $value, $expires, $path, $domain, $secure) {
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
                    function ($name, $value, $expires, $path, $domain, $secure) {
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

    public static function imageDataProvider() {
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

    public static function uploadedImageProvider() {
        return [
            [__TEST_DATA__ . '/images/test_image.png', true],
            [__TEST_DATA__ . '/images/test_image.jpg', true],
            [__TEST_DATA__ . '/.gitkeep', false]
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
     * @preserveGlobalState disabled
     */
    public function testCheckUploadedImageFileImageSizeFalse() {
        try {
            $_FILES['test'] = [
                'name' => [basename(__TEST_DATA__ . '/images/test_image.png')],
                'tmp_name' => [__TEST_DATA__ . '/images/test_image.png'],
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

    public static function comparableNullableGtProvider() {
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

    public static function safeCalcPercentProvider() {
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

    public function testGetCompareByReference() {
        $array1 = [];
        $array2 = [];
        for ($i = 0; $i < 5; $i++) {
            $obj = new \stdClass();
            $obj->i = $i;
            $array1[] = $obj;
            $array2[] = $obj;
        }
        $inferior_func = function ($a, $b) {
            return $a === $b ? -1 : 1;
        };
        $this->assertCount(5, array_udiff($array1, $array2, $inferior_func));
        $this->assertCount(0, array_udiff($array1, $array2, Utils::getCompareByReference()));
        $this->assertCount(5, array_udiff($array1, [$array2[3]], $inferior_func));
        $this->assertCount(4, array_udiff($array1, [$array2[3]], Utils::getCompareByReference()));
    }

    public function testGetAutoFillData() {
        $details = [];
        $details[] = [
            'user_id' => "test",
            'anon_id' => "TestAnon",
            'user_numeric_id' => '123456789',
            'user_password' => "test",
            'user_givenname' => "User",
            'user_preferred_givenname' => null,
            'user_familyname' => "Tester",
            'user_preferred_familyname' => null,
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "test@example.com",
            'user_email_secondary' => "test@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_STUDENT,
            'registration_section' => null,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1, 2]
        ];

        $details[] = [
            'user_id' => "aphacker",
            'anon_id' => "anon_id",
            'user_numeric_id' => '987654321',
            'user_password' => "aphacker",
            'user_givenname' => "Alyss",
            'user_preferred_givenname' => "Allison",
            'user_familyname' => "Hacker",
            'user_preferred_familyname' => "Hacks",
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "aphacker@example.com",
            'user_email_secondary' => "aphacker@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_STUDENT,
            'registration_section' => 1,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1, 2]
        ];

        $core = new Core();
        $users = [];
        foreach ($details as $detail) {
            $users[] = new User($core, $detail);
        }

        $expected = '[{"value":"aphacker","label":"Allison Hacks <aphacker>"},{"value":"test","label":"[NULL section] User Tester <test>"}]';
        $this->assertEquals($expected, Utils::getAutoFillData($users));
    }

    public function testGetAutoFillDataDuplicateIds() {
        $details = [];
        $details[] = [
            'user_id' => "test",
            'anon_id' => "TestAnon",
            'user_numeric_id' => '123456789',
            'user_password' => "test",
            'user_givenname' => "User",
            'user_preferred_givenname' => null,
            'user_familyname' => "Tester",
            'user_preferred_familyname' => null,
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "test@example.com",
            'user_email_secondary' => "test@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_STUDENT,
            'registration_section' => null,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1, 2]
        ];

        $details[] = [
            'user_id' => "aphacker",
            'anon_id' => "anon_id",
            'user_numeric_id' => '987654321',
            'user_password' => "aphacker",
            'user_givenname' => "Alyss",
            'user_preferred_givenname' => "Allison",
            'user_familyname' => "Hacker",
            'user_preferred_familyname' => "Hacks",
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "aphacker@example.com",
            'user_email_secondary' => "aphacker@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_STUDENT,
            'registration_section' => 1,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1, 2]
        ];

        $details[] = [
            'user_id' => "test",
            'anon_id' => "TestAnon",
            'user_numeric_id' => '123456789',
            'user_password' => "test",
            'user_givenname' => "User",
            'user_preferred_givenname' => null,
            'user_familyname' => "Tester",
            'user_preferred_familyname' => null,
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "test@example.com",
            'user_email_secondary' => "test@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_STUDENT,
            'registration_section' => null,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1, 2]
        ];

        $details[] = [
            'user_id' => "aphacker",
            'anon_id' => "anon_id",
            'user_numeric_id' => '987654321',
            'user_password' => "aphacker",
            'user_givenname' => "Alyss",
            'user_preferred_givenname' => "Allison",
            'user_familyname' => "Hacker",
            'user_preferred_familyname' => "Hacks",
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "aphacker@example.com",
            'user_email_secondary' => "aphacker@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_STUDENT,
            'registration_section' => 1,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1, 2]
        ];

        $core = new Core();
        $users = [];
        foreach ($details as $detail) {
            $users[] = new User($core, $detail);
        }

        $expected = '[{"value":"aphacker","label":"Allison Hacks <aphacker>"},{"value":"test","label":"[NULL section] User Tester <test>"}]';
        $this->assertEquals($expected, Utils::getAutoFillData($users));
    }

    public static function booleanProvider() {
        return [
            ['true', true],
            ['on', true],
            ['1', true],
            ['false', false],
            ['random_string', false],
            ['0', false],
            [true, true],
            [false, false],
            [123, true],
            [1, true],
            [0, false]
        ];
    }

    /**
     * @dataProvider booleanProvider
     */
    public function testConvertBooleans(mixed $variable, bool $boolean): void {
        // Strict assertion to assert actual boolean value is returned, not possibly equal other values, such as an integer or a string.
        $this->assertSame($boolean, Utils::getBooleanValue($variable));
    }

    public function testGetAutoFillDataVersion() {
        $details = [];
        $details[] = [
            'user_id' => "test",
            'anon_id' => "TestAnon",
            'user_numeric_id' => '123456789',
            'user_password' => "test",
            'user_givenname' => "User",
            'user_preferred_givenname' => null,
            'user_familyname' => "Tester",
            'user_preferred_familyname' => null,
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "test@example.com",
            'user_email_secondary' => "test@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_STUDENT,
            'registration_section' => null,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1, 2]
        ];

        $details[] = [
            'user_id' => "aphacker",
            'anon_id' => "anon_id",
            'user_numeric_id' => '987654321',
            'user_password' => "aphacker",
            'user_givenname' => "Alyss",
            'user_preferred_givenname' => "Allison",
            'user_familyname' => "Hacker",
            'user_preferred_familyname' => "Hacks",
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "aphacker@example.com",
            'user_email_secondary' => "aphacker@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_STUDENT,
            'registration_section' => 1,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1, 2]
        ];

        $core = new Core();
        $users = [];
        foreach ($details as $detail) {
            $users[] = new User($core, $detail);
        }
        $versions = [
            'test' => 0,
            'aphacker' => 5
        ];

        $expected = '[{"value":"test","label":"User Tester <test>"},{"value":"aphacker","label":"Allison Hacks <aphacker> (5 Prev Submission)"}]';
        $this->assertEquals($expected, Utils::getAutoFillData($users, $versions));
    }

    public function testGetAutoFillDataVersionsDuplicates() {
        $details = [];
        $details[] = [
            'user_id' => "test",
            'anon_id' => "TestAnon",
            'user_numeric_id' => '123456789',
            'user_password' => "test",
            'user_givenname' => "User",
            'user_preferred_givenname' => null,
            'user_familyname' => "Tester",
            'user_preferred_familyname' => null,
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "test@example.com",
            'user_email_secondary' => "test@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_STUDENT,
            'registration_section' => null,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1, 2]
        ];

        $details[] = [
            'user_id' => "aphacker",
            'anon_id' => "anon_id",
            'user_numeric_id' => '987654321',
            'user_password' => "aphacker",
            'user_givenname' => "Alyss",
            'user_preferred_givenname' => "Allison",
            'user_familyname' => "Hacker",
            'user_preferred_familyname' => "Hacks",
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "aphacker@example.com",
            'user_email_secondary' => "aphacker@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_STUDENT,
            'registration_section' => 1,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1, 2]
        ];

        $details[] = [
            'user_id' => "test",
            'anon_id' => "TestAnon",
            'user_numeric_id' => '123456789',
            'user_password' => "test",
            'user_givenname' => "User",
            'user_preferred_givenname' => null,
            'user_familyname' => "Tester",
            'user_preferred_familyname' => null,
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "test@example.com",
            'user_email_secondary' => "test@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_STUDENT,
            'registration_section' => null,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1, 2]
        ];

        $details[] = [
            'user_id' => "aphacker",
            'anon_id' => "anon_id",
            'user_numeric_id' => '987654321',
            'user_password' => "aphacker",
            'user_givenname' => "Alyss",
            'user_preferred_givenname' => "Allison",
            'user_familyname' => "Hacker",
            'user_preferred_familyname' => "Hacks",
            'user_pronouns' => '',
            'display_pronouns' => false,
            'user_email' => "aphacker@example.com",
            'user_email_secondary' => "aphacker@exampletwo.com",
            'user_email_secondary_notify' => false,
            'user_group' => User::GROUP_STUDENT,
            'registration_section' => 1,
            'rotating_section' => null,
            'manual_registration' => false,
            'grading_registration_sections' => [1, 2]
        ];

        $core = new Core();
        $users = [];
        foreach ($details as $detail) {
            $users[] = new User($core, $detail);
        }

        $versions = [
            'test' => 0,
            'aphacker' => 2
        ];

        $expected = '[{"value":"test","label":"User Tester <test>"},{"value":"aphacker","label":"Allison Hacks <aphacker> (2 Prev Submission)"}]';
        $this->assertEquals($expected, Utils::getAutoFillData($users, $versions));
    }

    public static function returnBytesProvider() {
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

    public static function formatBytesProvider() {
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

    public static function formatBytesRoundingProvider() {
        return [
            ['b', 0, '0B'],
            ['b', 1000, '1000B'],
            ['kb', 999, '1KB'],
            ['kb', 1023, '1KB'],
            ['kb', 500, '0KB'],
            ['kb', 1049999, '1025KB'],
            ['mb', 1, '0MB'],
            ['mb', 5000123, '5MB'],
        ];
    }

    /**
     * @dataProvider formatBytesRoundingProvider
     */
    public function testFormatBytesRounding($format, $bytes, $expected) {
        $this->assertEquals($expected, Utils::formatBytes($format, $bytes, true));
    }

    public function testMbStrSplitRegularString() {
        $this->assertEquals(str_split('abcdef'), Utils::mb_str_split('abcdef'));
    }

    public function testMbStrSplitMbString() {
        $this->assertEquals(
            ["α", "β", "γ", "δ", "ε", "f"],
            Utils::mb_str_split("αβγδεf")
        );
    }

    public function testMbStrSplitLength() {
        $this->assertEquals(
            ["αβ", "γδ", "εf", "g"],
            Utils::mb_str_split("αβγδεfg", 2)
        );
    }
}

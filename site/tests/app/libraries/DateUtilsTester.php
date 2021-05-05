<?php

namespace tests\app\libraries;

use app\libraries\Core;
use app\libraries\DateUtils;
use app\models\Config;
use app\models\User;

class DateUtilsTester extends \PHPUnit\Framework\TestCase {
    public function dayDiffProvider(): array {
        return [
            [1, "Now", "Tomorrow"],
            [0, "2017-01-12 19:10:53.000000", "2017-01-12 19:10:53.000000"],
            [1, "2016-07-19 00:00:00", "2016-07-19 00:00:30"],
            [0, "2016-07-19 00:00:30", "2016-07-19 00:00:00"],
            [1, "2016-07-19 00:00:00", "2016-07-19 00:01:00"],
            [0, "2016-07-19 00:01:00", "2016-07-19 00:00:00"],
            [1, "2016-07-19 00:00:00", "2016-07-19 01:00:00"],
            [0, "2016-07-19 01:00:00", "2016-07-19 00:00:00"],
            [10, "2016-07-19 00:00:00", "2016-07-28 12:00:00"],
            [0, "2016-07-19 00:00:00", "2016-07-18 23:55:00"],
            [-1, "2016-07-19 00:00:00", "2016-07-17 23:00:00"],
            [-6, "2016-07-19 00:00:00", "2016-07-12 12:00:00"]
        ];
    }

    /**
     * @dataProvider dayDiffProvider
     */
    public function testCalculateDayDiff(int $expected, string $date1, string $date2): void {
        $this->assertEquals($expected, DateUtils::calculateDayDiff($date1, $date2));
    }

    public function validateTimestampProvider(): array {
        return [
            ['01-25-2019', true],
            ['02-29-2020', true],
            ['06-31-2019', false],
            ['01/25/2019', true],
            ['02/29/2020', true],
            ['06/31/2019', false],
            ['01-25-19', true],
            ['02-29-20', true],
            ['06-31-19', false],
            ['01/25/19', true],
            ['02/29/20', true],
            ['06/31/19', false],
        ];
    }

    /**
     * @dataProvider validateTimestampProvider
     */
    public function testValidateTimestamp(string $expected, bool $valid): void {
        $this->assertTrue(DateUtils::validateTimestamp($expected) === $valid);
    }

    public function validateTimestampFormatProvider(): array {
        return [
            // valid formats
            ['m-d-Y', '02-29-2020', true],
            ['m-d-Y', '06-31-2019', false],
            ['m/d/Y', '02/29/2020', true],
            ['m/d/Y', '06/31/2019', false],
            ['m-d-y', '02-29-20', true],
            ['m-d-y', '06-31-19', false],
            ['m/d/y', '02/29/20', true],
            ['m/d/y', '06/31/19', false],
            // invalid formats
            ['invalid', '01-01-2020', false],
            ['a/b/c', '01/01/2020', false]
        ];
    }

    /**
     * @dataProvider validateTimestampFormatProvider
     */
    public function testValidateTimestampForFormat(string $format, string $timestamp, bool $valid): void {
        $this->assertTrue(DateUtils::validateTimestampForFormat($format, $timestamp) === $valid);
    }

    public function testParseDateTimeInvalidType(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Passed object was not a DateTime object or a date string');
        DateUtils::parseDateTime(false, new \DateTimeZone('America/New_York'));
    }

    public function testParseDateTimeInvalidFormat(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DateTime Format');
        DateUtils::parseDateTime('this is an invalid date', new \DateTimeZone('America/New_York'));
    }

    public function parseDateTimeProvider(): array {
        return [
            ['01/20/2019T13:24:55Z', 'Etc/GMT+5', 'd/m/Y\TH:i:s', '20/01/2019T08:24:55'],
            // max time value
            ['9999/12/31T13:24:55Z', 'Etc/GMT-5', 'd/m/Y\TH:i:s', '01/02/9999T00:00:00'],
            // test timezones with DST
            ['2020-06-01 12:00:00-04', 'America/New_York', 'Y-m-d H:i:s', '2020-06-01 12:00:00'],
            ['2020-12-01 12:00:00-05', 'America/New_York', 'Y-m-d H:i:s', '2020-12-01 12:00:00'],
            ['2020-06-01 12:00:00-04', 'America/Los_Angeles', 'Y-m-d H:i:s', '2020-06-01 09:00:00'],
            ['2020-12-01 12:00:00-05', 'America/Los_Angeles', 'Y-m-d H:i:s', '2020-12-01 09:00:00'],
        ];
    }

    /**
     * @dataProvider parseDateTimeProvider
     */
    public function testParseDateTimeString(string $input, string $timezoneString, string $format, string $expected): void {
        $timezone = new \DateTimeZone($timezoneString);
        $datetime = DateUtils::parseDateTime($input, $timezone);
        $this->assertEquals($expected, $datetime->format($format));
        $this->assertEquals($timezone, $datetime->getTimezone());
    }

    public function testParseDateTimeDifferentTimezone(): void {
        $timezone = new \DateTimeZone('Etc/GMT-5');
        $datetime = DateUtils::parseDateTime(
            new \DateTime('01/20/2019T13:24:55Z', new \DateTimeZone('UTC')),
            $timezone
        );
        $this->assertEquals("20/01/2019T18:24:55", $datetime->format('d/m/Y\TH:i:s'));
        $this->assertEquals($timezone, $datetime->getTimezone());
    }

    public function testParseDateTimeMaxString(): void {
        $timezone = new \DateTimeZone('Etc/GMT-5');
        $datetime = DateUtils::parseDateTime(
            new \DateTime('9999/12/31T13:24:55Z', $timezone),
            $timezone
        );
        $this->assertEquals("01/02/9999T00:00:00", $datetime->format('d/m/Y\TH:i:s'));
        $this->assertEquals($timezone, $datetime->getTimezone());
    }

    public function testDateTimeToString(): void {
        $actual = DateUtils::dateTimeToString(
            new \DateTime('01/20/2019T13:24:55', new \DateTimeZone('Etc/GMT+5'))
        );
        $this->assertEquals('2019-01-20 13:24:55-0500', $actual);
    }

    public function testDateTimeToStringNoOffset(): void {
        $actual = DateUtils::dateTimeToString(
            new \DateTime('01/20/2019T13:24:55', new \DateTimeZone('Etc/GMT+5')),
            false
        );
        $this->assertEquals('2019-01-20 13:24:55', $actual);
    }

    public function testGetServerTime(): void {
        $core = new Core();
        $config = new Config($core);
        $core->setConfig($config);
        $time = DateUtils::getServerTimeJson($core);
        $this->assertRegExp("/20[0-9]{2}/", $time['year']);
        $this->assertRegExp("/[0-1][0-9]/", $time['month']);
        $this->assertRegExp("/([0-9]|[1-3][0-9])/", $time['day']);
        $this->assertRegExp("/^([0-9]|1[0-9]|2[0-3])$/", $time['hour']);
        $this->assertRegExp("/[0-5][0-9]/", $time['minute']);
        $this->assertRegExp("/[0-5][0-9]/", $time['second']);
    }

    public function testGetAvailableTimeZones(): void {
        $timezones = DateUtils::getAvailableTimeZones();
        $this->assertIsArray($timezones);
        $this->assertNotEmpty($timezones);
        $this->assertSame('NOT_SET/NOT_SET', $timezones[0]);
        $this->assertNotContains('UTC', $timezones);
    }

    public function utcOffsetProvider(): array {
        // we cannot test a timezone with DST here as offset will shift through the year
        return [
            ['NOT_SET/NOT_SET', 'NOT SET'],
            ['UTC', '+00:00'],
            ['Antarctica/Mawson', '+05:00'],
            ['Pacific/Honolulu', '-10:00'],
        ];
    }

    /**
     * @dataProvider utcOffsetProvider
     */
    public function testGetUTCOffset(string $timezone, string $expected): void {
        $this->assertSame($expected, DateUtils::getUTCOffset($timezone));
    }

    public function convertTimeStampProvider(): array {
        return [
            ['NOT_SET/NOT_SET', '2020-06-12 12:55:00', 'Y-m-d H:i:s', '2020-06-12 12:55:00'],
            ['NOT_SET/NOT_SET', '2020-06-12 12:55:00Z+01:00', 'Y-m-d H:i:s', '2020-06-12 08:55:00'],
            ['NOT_SET/NOT_SET', '2020-12-12 12:55:00Z+01:00', 'H:i:s Y-m-d', '07:55:00 2020-12-12'],
            ['America/Los_Angeles', '2020-06-12 12:55:00', 'Y-m-d H:i:s', '2020-06-12 12:55:00'],
            ['America/Los_Angeles', '2020-06-12T12:55:00Z+01:00', 'Y-m-d H:i:s', '2020-06-12 05:55:00'],
            ['America/Los_Angeles', '2020-12-12T12:55:00Z+01:00', 'H:i:sP Y-m-d', '04:55:00-08:00 2020-12-12'],
        ];
    }

    /**
     * @dataProvider convertTimeStampProvider
     */
    public function testConvertTimeStamp(string $timezone, string $timestamp, string $format, string $expected): void {
        $core = new Core();
        $config = new Config($core);
        $config->setTimezone(new \DateTimeZone('America/New_York'));
        $core->setConfig(new Config($core));
        $user = new User($core, [
            'user_id' => 'test',
            'user_firstname' => 'test',
            'user_lastname' => 'person',
            'user_email' => null,
            'time_zone' => $timezone,
        ]);
        $this->assertSame($expected, DateUtils::convertTimeStamp($user, $timestamp, $format));
    }

    public function timeIntToStringProvider(): array {
        return [
            [0, '0:00'],
            [5, '0:05'],
            [15, '0:15'],
            [300, '5:00'],
            [1815, '30:15'],
            [3600, '60:00'],
            [7200, '120:00'],
        ];
    }

    /**
     * @dataProvider timeIntToStringProvider
     */
    public function testTimeIntoToString(int $time, string $expected): void {
        $this->assertSame($expected, DateUtils::timeIntToString($time));
    }
}

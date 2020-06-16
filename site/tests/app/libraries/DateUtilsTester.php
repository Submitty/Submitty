<?php

namespace tests\app\libraries;

use app\libraries\Core;
use app\libraries\DateUtils;
use app\models\Config;

class DateUtilsTester extends \PHPUnit\Framework\TestCase {
    public function dayDiffData() {
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
     * @param string $expected
     * @param string $date1
     * @param string $date2
     *
     * @dataProvider dayDiffData
     */
    public function testCalculateDayDiff($expected, $date1, $date2) {
        $this->assertEquals($expected, DateUtils::calculateDayDiff($date1, $date2));
    }

    public function validateTimestampData(): array {
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
     * @dataProvider validateTimestampData
     */
    public function testValidateTimestamp(string $expected, bool $valid): void {
        $this->assertTrue(DateUtils::validateTimestamp($expected) === $valid);
    }

    public function validateTimestampForFormatData(): array {
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
     * @dataProvider validateTimestampForFormatData
     */
    public function testValidateTimestampForFormat(string $format, string $timestamp, bool $valid): void {
        $this->assertTrue(DateUtils::validateTimestampForFormat($format, $timestamp) === $valid);
    }

    public function testParseDateTimeInvalidType() {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Passed object was not a DateTime object or a date string');
        DateUtils::parseDateTime(false, new \DateTimeZone('America/New_York'));
    }

    public function testParseDateTimeInvalidFormat() {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DateTime Format');
        DateUtils::parseDateTime('this is an invalid date', new \DateTimeZone('America/New_York'));
    }

    public function testParseDateTimeString() {
        $timezone = new \DateTimeZone("Etc/GMT+5");
        $datetime = DateUtils::parseDateTime('01/20/2019T13:24:55Z', $timezone);
        $this->assertEquals("20/01/2019T08:24:55", $datetime->format('d/m/Y\TH:i:s'));
        $this->assertEquals($timezone, $datetime->getTimezone());
    }

    public function testParseDateTimeDifferentTimezone() {
        $timezone = new \DateTimeZone('Etc/GMT-5');
        $datetime = DateUtils::parseDateTime(
            new \DateTime('01/20/2019T13:24:55Z', new \DateTimeZone('UTC')),
            $timezone
        );
        $this->assertEquals("20/01/2019T18:24:55", $datetime->format('d/m/Y\TH:i:s'));
        $this->assertEquals($timezone, $datetime->getTimezone());
    }

    public function testParseDateTimeMaxString() {
        $timezone = new \DateTimeZone('Etc/GMT-5');
        $datetime = DateUtils::parseDateTime(
            new \DateTime('9999/12/31T13:24:55Z', $timezone),
            $timezone
        );
        $this->assertEquals("01/02/9999T00:00:00", $datetime->format('d/m/Y\TH:i:s'));
        $this->assertEquals($timezone, $datetime->getTimezone());
    }

    public function testDateTimeToString() {
        $actual = DateUtils::dateTimeToString(
            new \DateTime('01/20/2019T13:24:55', new \DateTimeZone('Etc/GMT+5'))
        );
        $this->assertEquals('2019-01-20 13:24:55-0500', $actual);
    }

    public function testDateTimeToStringNoOffset() {
        $actual = DateUtils::dateTimeToString(
            new \DateTime('01/20/2019T13:24:55', new \DateTimeZone('Etc/GMT+5')),
            false
        );
        $this->assertEquals('2019-01-20 13:24:55', $actual);
    }

    /**
     * Time stamp with -04 offset into America/New_York
     * Date format: YYYY-MM-DD HH:MM:SS
     *
     * Time stamp: 2020-06-01 12:00:00-04
     * UTC time: 2020-06-01 16:00:00
     *
     * Expected: 2020-06-01 12:00:00
     */
    public function testParseDateTimeNYOne() {
        $format = 'Y-m-d H:i:s';
        $time_zone = new \DateTimeZone('America/New_York');

        $time_stamp = '2020-06-01 12:00:00-04';
        $date_time = DateUtils::parseDateTime($time_stamp, $time_zone);

        $expected_string = '2020-06-01 12:00:00';
        $captured_string = $date_time->format($format);

        $this->assertEquals($expected_string, $captured_string);
    }

    /**
     * Time stamp with -05 offset into America/New_York
     * Date format: YYYY-MM-DD HH:MM:SS
     *
     * Time stamp: 2020-12-01 12:00:00-05
     * UTC time: 2020-12-01 17:00:00
     *
     * Expected: 2020-12-01 12:00:00
     */
    public function testParseDateTimeNYTwo() {
        $format = 'Y-m-d H:i:s';
        $time_zone = new \DateTimeZone('America/New_York');

        $time_stamp = '2020-12-01 12:00:00-05';
        $date_time = DateUtils::parseDateTime($time_stamp, $time_zone);

        $expected_string = '2020-12-01 12:00:00';
        $captured_string = $date_time->format($format);

        $this->assertEquals($expected_string, $captured_string);
    }

    /**
     * Time stamp with -04 offset into America/Los_Angeles
     * Date format: YYYY-MM-DD HH:MM:SS
     *
     * Time stamp: 2020-06-01 12:00:00-04
     * UTC time: 2020-06-01 16:00:00
     *
     * Expected: 2020-06-01 09:00:00
     */
    public function testParseDateTimeLAOne() {
        $format = 'Y-m-d H:i:s';
        $time_zone = new \DateTimeZone('America/Los_Angeles');

        $time_stamp = '2020-06-01 12:00:00-04';
        $date_time = DateUtils::parseDateTime($time_stamp, $time_zone);

        $expected_string = '2020-06-01 09:00:00';
        $captured_string = $date_time->format($format);

        $this->assertEquals($expected_string, $captured_string);
    }

    /**
     * Time stamp with -05 offset into America/Los_Angeles
     * Date format: YYYY-MM-DD HH:MM:SS
     *
     * Time stamp: 2020-12-01 12:00:00-05
     * UTC time: 2020-12-01 17:00:00
     *
     * Expected: 2020-12-01 09:00:00
     */
    public function testParseDateTimeLATwo() {
        $format = 'Y-m-d H:i:s';
        $time_zone = new \DateTimeZone('America/Los_Angeles');

        $time_stamp = '2020-12-01 12:00:00-05';
        $date_time = DateUtils::parseDateTime($time_stamp, $time_zone);

        $expected_string = '2020-12-01 09:00:00';
        $captured_string = $date_time->format($format);

        $this->assertEquals($expected_string, $captured_string);
    }


    public function testGetServerTime() {
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
}

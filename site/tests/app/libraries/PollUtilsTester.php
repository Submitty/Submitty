<?php

namespace tests\app\libraries;

use app\libraries\PollUtils;

class PollUtilsTester extends \PHPUnit\Framework\TestCase {
    use \phpmock\phpunit\PHPMock;

    public function setUp(): void {

    }

    public function tearDown(): void {

    }

    public function testExportDataWithEmptyPolls() {
        $polls = [];
        $expected_data = [];
        $actual_data = PollUtils::getPollExportData($polls);
        $this->assertSame($actual_data, $expected_data);
    }


}
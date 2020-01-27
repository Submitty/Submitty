<?php

namespace tests\app\libraries;

use app\libraries\Core;
use app\libraries\NumberUtils;
use app\models\Config;
use PHPUnit\Framework\TestCase;

class NumbersUtilsTester extends TestCase {
    /**
     * @return array -containing the test cases as { expectedValue, value, precision}
     */
    public function roundPointValueData() {
        return array(
            array(6, 6, 0.05),
            array(1.002, 1, 0.006),
            array(0.996, 0.998, 0.006),
            array(21, 20, 7),
            array(24, 24, 8),
            array(15, 15, 0.5),
            array(49.8, 50, 0.6),
            array(50.4, 50.2, 0.6),
            array(190, 194, 10),
            array(200, 197, 10),
            array(1.02, 0.992, 0.06),
            array(98, 100, 7),
        );
    }

    /**
     * @param string $expectedValue
     * @param string $value
     * @param string $precision
     *
     * @dataProvider roundPointValueData
     */
    public function testRoundPointValue($expectedValue, $value, $precision) {
        $this->assertEquals($expectedValue, NumberUtils::roundPointValue($value, $precision));
    }
}

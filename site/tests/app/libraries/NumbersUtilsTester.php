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
        return [
            [6, 6, 0.05],
            [1.002, 1, 0.006],
            [0.996, 0.998, 0.006],
            [21, 20, 7],
            [24, 24, 8],
            [15, 15, 0.5],
            [49.8, 50, 0.6],
            [50.4, 50.2, 0.6],
            [190, 194, 10],
            [200, 197, 10],
            [1.02, 0.992, 0.06],
            [98, 100, 7],
        ];
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

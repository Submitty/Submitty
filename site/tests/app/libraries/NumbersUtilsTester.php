<?php

namespace tests\app\libraries;

use app\libraries\NumberUtils;
use PHPUnit\Framework\TestCase;

class NumbersUtilsTester extends TestCase {
    /**
     * @return array -containing the test cases as { expectedValue, value, precision}
     */
    public static function roundPointValueData() {
        return [
            [6.0, 6, 0.05],
            [1.002, 1, 0.006],
            [0.996, 0.998, 0.006],
            [21.0, 20, 7],
            [24.0, 24, 8],
            [15.0, 15, 0.5],
            [49.8, 50, 0.6],
            [50.4, 50.2, 0.6],
            [190.0, 194, 10],
            [200.0, 197, 10],
            [1.02, 0.992, 0.06],
            [98.0, 100, 7],
        ];
    }

    /**
     * @dataProvider roundPointValueData
     */
    public function testRoundPointValue(float $expectedValue, float $value, float $precision): void {
        $this->assertSame($expectedValue, NumberUtils::roundPointValue($value, $precision));
    }

    public function testRoundPointValueZeroPrecision(): void {
        $this->assertSame(12.134, NumberUtils::roundPointValue(12.134, 0));
    }

    public static function randomIndicesData(): array {
        return [
            [-1, []],
            [0, []],
            [1, [0]],
            [5, [2, 3, 1, 4, 0]],
        ];
    }

    /**
     * @dataProvider randomIndicesData
     */
    public function testGetRandomIndices(int $len, array $expected): void {
        $this->assertSame($expected, NumberUtils::getRandomIndices($len, 'test'));
    }

    public function testGetRandomIndicesDifferentSeeds(): void {
        $this->assertNotSame(NumberUtils::getRandomIndices(5, 'seed1'), NumberUtils::getRandomIndices(5, 'seed2'));
    }
}

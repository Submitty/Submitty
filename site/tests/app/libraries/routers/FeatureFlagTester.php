<?php

namespace tests\app\libraries\routers;

use app\libraries\routers\FeatureFlag;
use InvalidArgumentException;

class FeatureFlagTester extends \PHPUnit\Framework\TestCase {
    public function testFeatureFlag() {
        $flag = new FeatureFlag('test_flag');
        $this->assertSame('test_flag', $flag->getFlag());
    }

    public function testEmptyValue() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Must have non-empty string "flag" for FeatureFlag attribute');
        new FeatureFlag();
    }
}

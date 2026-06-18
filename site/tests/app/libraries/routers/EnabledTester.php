<?php

declare(strict_types=1);

namespace tests\app\libraries\routers;

use app\libraries\routers\Enabled;
use InvalidArgumentException;

class EnabledTester extends \PHPUnit\Framework\TestCase {
    public function testFeatureFlag() {
        $enabled = new Enabled('forum');
        $this->assertSame('forum', $enabled->getFeature());
    }

    public function testEmptyValue() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Must have non-empty string "feature" for Enabled attribute');
        new Enabled();
    }
}

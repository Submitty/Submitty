<?php

declare(strict_types=1);

namespace tests\app\models\gradeable;

use app\models\gradeable\Redaction;
use tests\BaseUnitTest;

class RedactionTester extends BaseUnitTest {
    private $core;

    public function setUp(): void {
        $this->core = $this->createMockCore();
    }

    public function testGetters(): void {
        $redaction = new Redaction($this->core, 2, 1.5, 2.5, 3.5, 4.5);

        $this->assertEquals(2, $redaction->getPageNumber());
        $this->assertEquals(1.5, $redaction->getX1());
        $this->assertEquals(2.5, $redaction->getY1());
        $this->assertEquals(3.5, $redaction->getX2());
        $this->assertEquals(4.5, $redaction->getY2());
    }

    public function testJsonSerialize(): void {
        $redaction = new Redaction($this->core, 1, 0.0, 0.0, 10.0, 20.0);

        $this->assertEquals(
            [
                'page_number' => 1,
                'coordinates' => [0.0, 0.0, 10.0, 20.0]
            ],
            $redaction->jsonSerialize()
        );
    }

    public function testJsonEncode(): void {
        $redaction = new Redaction($this->core, 3, 1.0, 2.0, 3.0, 4.0);

        $this->assertJsonStringEqualsJsonString(
            '{"page_number":3,"coordinates":[1.0,2.0,3.0,4.0]}',
            json_encode($redaction)
        );
    }
}

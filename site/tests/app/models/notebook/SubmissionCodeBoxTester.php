<?php

declare(strict_types=1);

namespace tests\app\models\notebook;

use app\models\notebook\SubmissionCodeBox;
use tests\BaseUnitTest;

class SubmissionCodeBoxTester extends BaseUnitTest {
    private $core;

    public function setUp(): void {
        $this->core = $this->createMockCore();
    }

    public function testBasicGetters(): void {
        $box = new SubmissionCodeBox($this->core, [
            'filename' => 'solution.py',
            'programming_language' => 'python',
            'rows' => 10
        ]);

        $this->assertEquals('solution.py', $box->getFileName());
        $this->assertEquals('python', $box->getLanguage());
        $this->assertEquals(10, $box->getRowCount());
    }

    public function testDefaultsWhenOptionalFieldsMissing(): void {
        $box = new SubmissionCodeBox($this->core, [
            'filename' => 'solution.txt'
        ]);

        $this->assertEquals('solution.txt', $box->getFileName());
        $this->assertNull($box->getLanguage());
        $this->assertEquals(0, $box->getRowCount());
    }
}

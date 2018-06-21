<?php

namespace tests\app\models;

use app\models\Button;
use tests\BaseUnitTest;

class ButtonTester extends BaseUnitTest {
    public function testDefaults() {
        $button = new Button($this->createMockCore(), []);
        $this->assertEquals('', $button->getTitle());
        $this->assertEquals('', $button->getHref());
        $this->assertEquals('btn', $button->getClass());
        $this->assertNull($button->getProgress());
        $this->assertNull($button->getSubtitle());
        $this->assertFalse($button->isDisabled());
    }

    public function testOverrides() {
        $details = [
            'title' => 'test',
            'href' => 'http://example.com',
            'class' => 'test-btn',
            'progress' => 100,
            'subtitle' => 'blah blah',
            'disabled' => true
        ];
        $button = new Button($this->createMockCore(), $details);
        $this->assertEquals($details['title'], $button->getTitle());
        $this->assertEquals($details['href'], $button->getHref());
        $this->assertEquals($details['class'], $button->getClass());
        $this->assertEquals($details['progress'], $button->getProgress());
        $this->assertEquals($details['subtitle'], $button->getSubtitle());
        $this->assertTrue($button->isDisabled());
    }

    public function testWrongTypeProgress() {
        $button = new Button($this->createMockCore(), ['progress' => 'a']);
        $this->assertEquals(0, $button->getProgress());
    }
}

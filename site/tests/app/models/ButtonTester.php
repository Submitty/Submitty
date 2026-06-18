<?php

namespace tests\app\models;

use app\libraries\Core;
use app\models\Button;

class ButtonTester extends \PHPUnit\Framework\TestCase {
    public function testDefaults() {
        $button = new Button(new Core(), []);
        $this->assertNull($button->getTitle());
        $this->assertEquals('', $button->getHref());
        $this->assertEquals('btn', $button->getClass());
        $this->assertNull($button->getProgress());
        $this->assertNull($button->getSubtitle());
        $this->assertFalse($button->isDisabled());
        $this->assertFalse($button->isTitleOnHover());
        $this->assertNull($button->getAriaLabel());
        $this->assertNull($button->getDate());
        $this->assertEquals('fa', $button->getPrefix());
    }

    public function testOverrides() {
        $details = [
            'title' => 'test',
            'href' => 'http://example.com',
            'class' => 'test-btn',
            'progress' => 100,
            'subtitle' => 'blah blah',
            'disabled' => true,
            'title_on_hover' => true,
            'aria_label' => 'placeholder',
            'date' => new \DateTime(),
            'prefix' => 'foo'
        ];
        $button = new Button(new Core(), $details);
        $this->assertEquals($details['title'], $button->getTitle());
        $this->assertEquals($details['href'], $button->getHref());
        $this->assertEquals($details['class'], $button->getClass());
        $this->assertEquals($details['progress'], $button->getProgress());
        $this->assertEquals($details['subtitle'], $button->getSubtitle());
        $this->assertTrue($button->isDisabled());
        $this->assertTrue($button->isTitleOnHover());
        $this->assertEquals($details['aria_label'], $button->getAriaLabel());
        $this->assertEquals($details['date'], $button->getDate());
        $this->assertEquals($details['prefix'], $button->getPrefix());
    }

    public function testWrongTypeProgress() {
        $button = new Button(new Core(), ['progress' => 'a']);
        $this->assertEquals(0, $button->getProgress());
    }

    public function testDate() {
        $button = new Button(new Core(), []);
        $date = new \DateTime();
        $button->setDate($date);
        $this->assertEquals($date, $button->getDate());
    }
}

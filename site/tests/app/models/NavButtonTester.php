<?php

declare(strict_types=1);

namespace tests\app\models;

use app\libraries\Core;
use app\models\NavButton;

class NavButtonTester extends \PHPUnit\Framework\TestCase {
    public function testNavButton() {
        $button = new NavButton(new Core(), [
            'title' => 'Test Button',
        ]);
        $this->assertSame('nav-row', $button->getClass());
        $this->assertSame('nav-sidebar-test-button', $button->getId());
    }

    public function testNavButtonWithId() {
        $button = new NavButton(new Core(), [
            'title' => 'Test Button',
            'id' => 'foo',
        ]);
        $this->assertSame('nav-row', $button->getClass());
        $this->assertSame('foo', $button->getId());
    }
}

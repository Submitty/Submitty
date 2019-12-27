<?php

namespace tests\app\libraries;

use app\libraries\Output;
use tests\BaseUnitTest;

class OutputTester extends BaseUnitTest {
    /** @var \app\libraries\Core */
    private $core;

    protected function setUp(): void {
        $this->core = $this->createMockCore();
    }

    public function testAddJs(): void {
        $output = new Output($this->core);
        $this->assertEquals(0, count($output->getJs()));
        $output->addJs('foo.js');
        $output->addJs('bar.js');
        $output->addJs('baz.js');
        $output->addJs('foo.js');
        $this->assertEquals(3, count($output->getJs()));
        $expected = ['foo.js', 'bar.js', 'baz.js'];
        for ($i = 0; $i < 3; $i++) {
            $this->assertEquals($expected[$i], $output->getJs()->get($i));
        }
    }

    public function testAddCss(): void {
        $output = new Output($this->core);
        $this->assertEquals(0, count($output->getCss()));
        $output->addCss('foo.css');
        $output->addCss('bar.css');
        $output->addCss('baz.css');
        $output->addCss('foo.css');
        $this->assertEquals(3, count($output->getCss()));
        $expected = ['foo.css', 'bar.css', 'baz.css'];
        for ($i = 0; $i < 3; $i++) {
            $this->assertEquals($expected[$i], $output->getCss()->get($i));
        }
    }
}

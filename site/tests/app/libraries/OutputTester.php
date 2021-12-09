<?php

namespace tests\app\libraries;

use app\libraries\Core;
use app\libraries\Output;
use tests\BaseUnitTest;

class OutputTester extends BaseUnitTest {
    /** @var Core */
    private $core;

    protected function setUp(): void {
        $this->core = new Core();
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
        $this->assertEmpty($output->getModuleJs());
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

    public function testAddModuleJs(): void {
        $output = new Output($this->core);
        $this->assertEmpty($output->getModuleJs());
        $output->addModuleJs('foo.js');
        $output->addModuleJs('bar.js');
        $output->addModuleJs('baz.js');
        $output->addModuleJs('foo.js');
        $this->assertEquals(3, count($output->getModuleJs()));
        $expected = ['foo.js', 'bar.js', 'baz.js'];
        for ($i = 0; $i < 3; $i++) {
            $this->assertEquals($expected[$i], $output->getModuleJs()->get($i));
        }
        $this->assertEmpty($output->getJs());
    }
}

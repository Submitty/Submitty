<?php

use PHPUnit\Framework\TestCase;
use app\models\notebook\SubmissionMultipleChoice;
use app\libraries\Core;

class SubmissionMultipleChoiceTester extends TestCase {
    private $core;

    protected function setUp(): void {
        $this->core = $this->createMock(Core::class);
    }

    public function testConstructorAllowMultipleTrueRandomizeTrue() {
        $details = [
            'filename' => 'mc.json',
            'allow_multiple' => true,
            'randomize_order' => true,
            'choices' => ['A', 'B', 'C']
        ];
        $mc = new SubmissionMultipleChoice($this->core, $details);
        $this->assertTrue($this->getProtected($mc, 'allow_multiple'));
        $this->assertTrue($this->getProtected($mc, 'randomize_order'));
        $this->assertEquals(['A', 'B', 'C'], $this->getProtected($mc, 'choices'));
        $this->assertEquals('mc.json', $this->getProtected($mc, 'file_name'));
    }

    public function testConstructorAllowMultipleFalseRandomizeFalse() {
        $details = [
            'filename' => 'mc2.json',
            'allow_multiple' => false,
            // randomize_order omitted
            'choices' => ['X', 'Y']
        ];
        $mc = new SubmissionMultipleChoice($this->core, $details);
        $this->assertFalse($this->getProtected($mc, 'allow_multiple'));
        $this->assertFalse($this->getProtected($mc, 'randomize_order'));
        $this->assertEquals(['X', 'Y'], $this->getProtected($mc, 'choices'));
        $this->assertEquals('mc2.json', $this->getProtected($mc, 'file_name'));
    }

    public function testConstructorAllowMultipleTruthyString() {
        $details = [
            'filename' => 'mc3.json',
            'allow_multiple' => '1',
            'randomize_order' => '0',
            'choices' => ['foo']
        ];
        $mc = new SubmissionMultipleChoice($this->core, $details);
        $this->assertTrue($this->getProtected($mc, 'allow_multiple'));
        $this->assertFalse($this->getProtected($mc, 'randomize_order'));
        $this->assertEquals(['foo'], $this->getProtected($mc, 'choices'));
        $this->assertEquals('mc3.json', $this->getProtected($mc, 'file_name'));
    }

    private function getProtected($object, $property) {
        $ref = new ReflectionClass($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }
}


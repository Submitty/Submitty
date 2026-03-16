<?php

use PHPUnit\Framework\TestCase;
use app\models\notebook\SubmissionCodeBox;
use app\models\notebook\AbstractNotebookInput;
use app\libraries\Core;
use app\libraries\CodeMirrorUtils;

class SubmissionCodeBoxTester extends TestCase {
    private $core;

    protected function setUp(): void {
        $this->core = $this->createMock(Core::class);
    }

    public function testConstructorWithAllDetails() {
        $details = [
            'filename' => 'main.py',
            'programming_language' => 'python',
            'codemirror_mode' => 'text/x-python',
            'rows' => 10
        ];
        $box = new SubmissionCodeBox($this->core, $details);
        $this->assertEquals('python', $this->getProtected($box, 'language'));
        $this->assertEquals('text/x-python', $this->getProtected($box, 'codeMirrorMode'));
        $this->assertEquals(10, $this->getProtected($box, 'row_count'));
        $this->assertEquals('main.py', $this->getProtected($box, 'file_name'));
    }

    public function testConstructorWithDefaults() {
        $details = [
            'filename' => 'main.cpp',
            'programming_language' => 'cpp'
            // codemirror_mode and rows omitted
        ];
        $box = new SubmissionCodeBox($this->core, $details);
        $this->assertEquals('cpp', $this->getProtected($box, 'language'));
        $this->assertEquals(CodeMirrorUtils::getCodeMirrorMode('cpp'), $this->getProtected($box, 'codeMirrorMode'));
        $this->assertEquals(0, $this->getProtected($box, 'row_count'));
        $this->assertEquals('main.cpp', $this->getProtected($box, 'file_name'));
    }

    public function testConstructorWithNullLanguage() {
        $details = [
            'filename' => 'main.txt',
            // programming_language omitted
            // codemirror_mode omitted
            // rows omitted
        ];
        $box = new SubmissionCodeBox($this->core, $details);
        $this->assertNull($this->getProtected($box, 'language'));
        $this->assertEquals(CodeMirrorUtils::getCodeMirrorMode(null), $this->getProtected($box, 'codeMirrorMode'));
        $this->assertEquals(0, $this->getProtected($box, 'row_count'));
        $this->assertEquals('main.txt', $this->getProtected($box, 'file_name'));
    }

    private function getProtected($object, $property) {
        $ref = new ReflectionClass($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }
}


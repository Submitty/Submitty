<?php

namespace unitTests\app\libraries;

use app\libraries\LipsumGenerator;

class LipsumGeneratorTests extends \PHPUnit_Framework_TestCase {
    public function testGetParagraphs() {
        $generated = LipsumGenerator::getParagraphs();
        $this->assertNotEmpty($generated);
        $this->assertStringStartsWith("Lorem ipsum dolor sit amet", $generated);
        $this->assertEquals(4, substr_count($generated, "<br />"));
    }

    public function testGetParagraphsOne() {
        $generated = LipsumGenerator::getParagraphs(1);
        $this->assertNotEmpty($generated);
        $this->assertStringStartsWith("Lorem ipsum dolor sit amet", $generated);
        $this->assertEquals(0, substr_count($generated, "<br />"));
    }

    public function testGetParagraphsNegative() {
        $generated = LipsumGenerator::getParagraphs(-1);
        $this->assertNotEmpty($generated);
        $this->assertStringStartsWith("Lorem ipsum dolor sit amet", $generated);
        $this->assertEquals(4, substr_count($generated, "<br />"));
    }

    public function testGetParagraphsNoLorem() {
        $generated = LipsumGenerator::getParagraphs(5, false);
        $this->assertNotEmpty($generated);
        $this->assertStringStartsNotWith("Lorem ipsum dolor sit amet", $generated);
        $this->assertEquals(4, substr_count($generated, "<br />"));
    }

    public function testGetWords() {
        $generated = LipsumGenerator::getWords();
        $this->assertNotEmpty($generated);
        $this->assertEquals("Lorem ipsum dolor sit amet.", $generated);
        $this->assertEquals(4, substr_count($generated, " "));
    }

    public function testGetBytes() {
        $generated = LipsumGenerator::getBytes();
        $this->assertNotEmpty($generated);
        $this->assertEquals("Lorem ipsum dolor sit amet.", $generated);
        $this->assertEquals(4, substr_count($generated, " "));
    }

    public function testGetLists() {
        $generated = LipsumGenerator::getLists();
        $this->assertCount(5, $generated);
        $this->assertStringStartsWith("Lorem ipsum dolor sit amet", $generated[0][0]);
        foreach ($generated as $value) {
            foreach ($value as $vv) {
                $this->assertStringEndsWith(".", $vv);
            }
        }
    }
}
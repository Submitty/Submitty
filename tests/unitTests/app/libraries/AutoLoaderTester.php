<?php

namespace tests\unitTests\app\libraries;

use \app\libraries\AutoLoader;

class AutoLoaderTester extends \PHPUnit_Framework_TestCase {
    private $autoloader_classes;

    public function setUp() {
        $this->autoloader_classes = AutoLoader::getClasses();
    }

    public function tearDown() {
        AutoLoader::setClasses($this->autoloader_classes);
    }

    public function testRegisterDirectory() {
        AutoLoader::registerDirectory(__DIR__);
        $classes = AutoLoader::getClasses();
        $this->assertTrue(in_array('AutoLoaderTester',array_keys($classes)));
        $this->assertEquals(__FILE__,$classes['AutoLoaderTester']);
    }

    public function testEmptyLoader() {
        AutoLoader::registerDirectory(__DIR__);
        AutoLoader::emptyLoader();
        $this->assertEmpty(AutoLoader::getClasses());
    }

    public function testUnregister() {
        AutoLoader::registerDirectory(__TEST_DIRECTORY__);
        $this->assertTrue(in_array('DummyClass', array_keys(AutoLoader::getClasses())));
        AutoLoader::unregisterClass('DummyClass');
        $this->assertFalse(in_array('DummyClass', array_keys(AutoLoader::getClasses())));
    }

    public function testSetClasses() {
        AutoLoader::emptyLoader();
        AutoLoader::setClasses(array("DummyClass"=>"Dummy"));
        $this->assertEquals(1, count(AutoLoader::getClasses()));
        $this->assertTrue(in_array('DummyClass', array_keys(AutoLoader::getClasses())));
    }

    public function testRegisterNamespace() {
        AutoLoader::registerDirectory(__TEST_DIRECTORY__, true, "tests");
        $this->assertTrue(in_array('tests\misc\DummyClass', array_keys(AutoLoader::getClasses())));
    }
}
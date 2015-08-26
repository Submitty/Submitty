<?php

use \lib\Functions;

class FunctionsTester extends \PHPUnit_Framework_TestCase {
    public function testRecurseiveRmDir() {
        $root = __DIR__."/data/".uniqid();
        mkdir($root);
        mkdir($root."/test1");
        mkdir($root."/test1/test2");
        mkdir($root."/test3");
        file_put_contents($root."/test.txt", "a");
        file_put_contents($root."/.hidden", "b");
        file_put_contents($root."/test1/file", "c");
        file_put_contents($root."/test1/test2/file", "d");
        
        Functions::recursiveRmdir($root);
        
        $this->assertFalse(file_exists($root));
    }
    
    public function testPad1() {
        $this->assertEquals("00", Functions::pad("0"));
    }
    
    public function testPad2() {
        $this->assertEquals("00", Functions::pad("00"));
    }
}
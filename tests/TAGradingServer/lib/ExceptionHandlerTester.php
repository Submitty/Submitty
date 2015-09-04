<?php

namespace tests\lib;

use lib\ExceptionHandler;
use lib\FileUtils;
use lib\Logger;
use lib\ServerException;
use lib\Functions;

class ExceptionHandlerTester extends \PHPUnit_Framework_TestCase {
    
    public static function setUpBeforeClass() {
        if (is_dir(__DIR__."/logs/EHLogs")) {
            FileUtils::emptyDir(__DIR__."/logs/EHLogs");
        }
        else {
            FileUtils::createDir(__DIR__."/logs/EHLogs");
        }
        
        Logger::$log_path = __DIR__."/logs/EHLogs/";
    }
    
    public static function tearDownAfterClass() {
        FileUtils::recursiveRmdir(__DIR__."/logs/EHLogs");
    }
    
    /**
     * @expectedException \lib\ServerException
     */
    public function testThrowServerException() {
        ExceptionHandler::throwException("ExceptionHandlerTester", new \Exception("test"));
    }
    
    /**
     * @expectedException \RuntimeException
     */
    public function testThrowRuntimeException() {
        ExceptionHandler::$debug = true;
        ExceptionHandler::throwException("ExceptionHandlerTester", new \RuntimeException("test"));
        ExceptionHandler::$debug = false;
    }
    
    public function testExceptionHandlerLog() {
        date_default_timezone_set("America/New_York");
        $date = getdate(time());
        $filename = $date['year'].str_pad($date['mon'], 2, '0', STR_PAD_LEFT).str_pad($date['mday'], 2, '0', STR_PAD_LEFT);
        ExceptionHandler::$debug = false;
        ExceptionHandler::$logExceptions = true;
        try {
            ExceptionHandler::throwException("ExceptionHandlerTester", new \Exception("test"), array("test"=>"b", "test2"=>array('a','c')));
        }
        catch (\Exception $e) {
            $this->assertTrue($e instanceof ServerException);
        }
        
        $this->assertFileExists(__DIR__."/logs/EHLogs/".$filename.".txt");
        $actual = file_get_contents(__DIR__."/logs/EHLogs/".$filename.".txt");

        $this->assertEquals(1, preg_match('/[0-9]{2}\/[0-9]{2}\/[0-9]{4}\ [0-9]{2}\:[0-9]{2}\:[0-9]{2} \- FATAL ERROR\nExceptionHandlerTester threw Exception\nMessage\:\ntest\nStrack Trace\:\n.+/', $actual));
        $this->assertEquals(1, preg_match('/Extra Details:\n\ttest: b\n\ttest2:\n\t\ta\n\t\tc/', $actual));
        ExceptionHandler::$logExceptions = false;
    }
}
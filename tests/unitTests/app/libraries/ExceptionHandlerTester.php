<?php

namespace tests\unitTests\app\libraries;

use \app\exceptions\BaseException;
use \app\libraries\ExceptionHandler;
use \app\libraries\FileUtils;
use \app\libraries\Logger;
use \app\libraries\ServerException;

class ExceptionHandlerTester extends \PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
        if (is_dir(__TEST_DIRECTORY__."/EHLogs")) {
            FileUtils::emptyDir(__TEST_DIRECTORY__."/EHLogs");
        }
        else {
            FileUtils::createDir(__TEST_DIRECTORY__."/EHLogs", true);
        }

        Logger::setLogPath(__TEST_DIRECTORY__."/EHLogs/");
    }

    public static function tearDownAfterClass() {
        FileUtils::recursiveRmdir(__TEST_DIRECTORY__."/EHLogs");
    }

    public function testThrowServerException() {
        $this->assertContains("An exception was thrown. Please contact an administrator about what<br />you were doing that caused this exception.", ExceptionHandler::throwException(new \RuntimeException("test")));
    }

    public function testThrowRuntimeException() {
        ExceptionHandler::setDisplayExceptions(true);
        $this->assertContains("An exception was thrown. Please contact an administrator about what<br />you were doing that caused this exception.", ExceptionHandler::throwException(new \RuntimeException("test")));
        ExceptionHandler::setDisplayExceptions(false);
    }

    public function testExceptionHandlerLog() {
        date_default_timezone_set("America/New_York");
        $date = getdate(time());
        $filename = $date['year'].str_pad($date['mon'], 2, '0', STR_PAD_LEFT).str_pad($date['mday'], 2, '0', STR_PAD_LEFT);
        ExceptionHandler::setDisplayExceptions(false);
        ExceptionHandler::setLogExceptions(true);
        try {
            ExceptionHandler::throwException(new BaseException("test", array("test"=>"b", "test2"=>array('a','c'))));
        }
        catch (\Exception $e) {
            $this->assertTrue($e instanceof ServerException);
        }

        $this->assertFileExists(__TEST_DIRECTORY__."/EHLogs/".$filename.".txt");
        $actual = file_get_contents(__TEST_DIRECTORY__."/EHLogs/".$filename.".txt");
        $this->assertEquals(1, preg_match('/[0-9]{2}\/[0-9]{2}\/[0-9]{4}\ [0-9]{2}\:[0-9]{2}\:[0-9]{2} \- FATAL ERROR\napp.+/', $actual));
        $this->assertEquals(1, preg_match('/Extra Details:\n\ttest: b\n\ttest2:\n\t\ta\n\t\tc/', $actual));
        ExceptionHandler::setLogExceptions(false);
    }
}
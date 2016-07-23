<?php

namespace tests\unitTests\app\libraries;

use \app\libraries\Logger;
use \app\libraries\Utils;
use \app\libraries\FileUtils;

class LoggerTester extends \PHPUnit_Framework_TestCase {
    private static $file_name;

    public static function setUpBeforeClass() {
        Logger::setLogPath(__TEST_DIRECTORY__."/LoggerTesterLogs");
        FileUtils::createDir(__TEST_DIRECTORY__."/LoggerTesterLogs", true);
        FileUtils::emptyDir(__TEST_DIRECTORY__."/LoggerTesterLogs");
        $date = getdate(time());
        LoggerTester::$file_name = __TEST_DIRECTORY__."/LoggerTesterLogs/".
            $date['year'].Utils::pad($date['mon']).Utils::pad($date['mday']).".txt";

        $_SERVER['HTTP_HOST'] = "localhost";
        $_SERVER['HTTPS'] = true;
        $_SERVER['REQUEST_URI'] = "index.php?test=1";
    }

    public static function tearDownAfterClass() {
        FileUtils::recursiveRmdir(__TEST_DIRECTORY__."/LoggerTesterLogs");
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['HTTPS']);
        unset($_SERVER['REQUEST_URI']);
    }

    public function tearDown() {
        if (file_exists(LoggerTester::$file_name)) {
            unlink(LoggerTester::$file_name);
        }
    }

    public function testLoggerDebug() {
        $this->assertFileNotExists(LoggerTester::$file_name);
        Logger::debug("Debug Message");
        $this->assertFileExists(LoggerTester::$file_name);
        $this->assertMessage("DEBUG", "Debug Message");
    }

    public function testLoggerInfo() {
        $this->assertFileNotExists(LoggerTester::$file_name);
        Logger::info("Info Message");
        $this->assertMessage("INFO", "Info Message");
    }

    public function testLoggerWarn() {
        $this->assertFileNotExists(LoggerTester::$file_name);
        Logger::warn("Warn Message");
        $this->assertMessage("WARN", "Warn Message");
    }

    public function testLoggerError() {
        $this->assertFileNotExists(LoggerTester::$file_name);
        Logger::error("Error Message");
        $this->assertMessage("ERROR", "Error Message");
    }

    public function testLoggerFatalError() {
        $this->assertFileNotExists(LoggerTester::$file_name);
        Logger::fatal("Fatal Message");
        $this->assertMessage("FATAL ERROR", "Fatal Message");
    }

    public function assertMessage($level, $message) {
        $current_date = getdate(time());
        $file = file_get_contents(LoggerTester::$file_name);
        $lines = explode("\n", $file);
        $this->assertCount(5, $lines);
        $first_line = explode(" - ", $lines[0]);
        $datetime = explode(" ", $first_line[0]);
        $date = explode("/", $datetime[0]);
        $time = explode(":", $datetime[1]);
        $this->assertEquals(Utils::pad($current_date['mday']), $date[0], "Day is not right");
        $this->assertEquals(Utils::pad($current_date['mon']), $date[1], "Month is not right");
        $this->assertEquals(Utils::pad($current_date['year']), $date[2], "Year is not right");
        $this->assertEquals(Utils::pad($current_date['hours']), $time[0], "Hours place is not right");
        $this->assertEquals(Utils::pad($current_date['minutes']), $time[1], "Minutes place is not right");
        // Give seconds place a leeway of 3 seconds incase of test slowdown so we don't get intermittent failures
        $this->assertEquals(Utils::pad($current_date['seconds']), $time[2], "Seconds place is not right", 3);

        $this->assertEquals($level, $first_line[1], "Level is wrong");
        $this->assertEquals($message, $lines[1], "Message is wrong");

        $this->assertEquals("URL: https://localhost/index.php?test=1", $lines[2], "URL is wrong");
        $this->assertEquals("=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=", $lines[3], "Divider is wrong");
        $this->assertEmpty($lines[4]);
    }

    public static function generateMessage($level, $message) {
        $date = getdate(time());
        $return = Utils::pad($date['mday'])."/".Utils::pad($date['mon'])."/".Utils::pad($date['year']);
        $return .= " ".Utils::pad($date['hours']).":".Utils::pad($date['minutes']).":";
        $return .= Utils::pad($date['seconds'])." - ".$level."\n".$message."\n";
        $return .= "URL: https://localhost/index.php?test=1\n";
        $return .= "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
        return $return;
    }
}
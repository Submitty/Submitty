<?php

namespace tests\unitTests\app\libraries;

use \app\libraries\Logger;
use \app\libraries\Utils;
use \app\libraries\FileUtils;

class LoggerTester extends \PHPUnit_Framework_TestCase {
    private $error;
    private $access;
    private $directory;

    public static function setUpBeforeClass() {
        $_SERVER['HTTP_HOST'] = "localhost";
        $_SERVER['HTTPS'] = true;
        $_SERVER['REQUEST_URI'] = "index.php?test=1";
    }

    public static function tearDownAfterClass() {
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['HTTPS']);
        unset($_SERVER['REQUEST_URI']);
    }

    public function setUp() {
        $this->directory = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        FileUtils::createDir($this->directory);
        $this->assertFileExists($this->directory);
        Logger::setLogPath($this->directory);
        $date = getdate(time());
        $filename = $date['year'].Utils::pad($date['mon']).Utils::pad($date['mday']);
        $this->error = FileUtils::joinPaths($this->directory, 'error', $filename.".log");
        $this->access = FileUtils::joinPaths($this->directory, 'access', $filename.".log");
    }

    public function tearDown() {
        FileUtils::recursiveRmdir($this->directory);
    }

    public function testLoggerDebug() {
        $this->assertFileNotExists($this->error);
        Logger::debug("Debug Message");
        $this->assertFileExists($this->error);
        $this->assertMessage("DEBUG", "Debug Message");
    }

    public function testLoggerInfo() {
        $this->assertFileNotExists($this->error);
        Logger::info("Info Message");
        $this->assertMessage("INFO", "Info Message");
    }

    public function testLoggerWarn() {
        $this->assertFileNotExists($this->error);
        Logger::warn("Warn Message");
        $this->assertMessage("WARN", "Warn Message");
    }

    public function testLoggerError() {
        $this->assertFileNotExists($this->error);
        Logger::error("Error Message");
        $this->assertMessage("ERROR", "Error Message");
    }

    public function testLoggerFatalError() {
        $this->assertFileNotExists($this->error);
        Logger::fatal("Fatal Message");
        $this->assertMessage("FATAL ERROR", "Fatal Message");
    }

    public function assertMessage($level, $message) {
        $current_date = getdate(time());
        $file = file_get_contents($this->error);
        $lines = explode("\n", $file);
        $this->assertCount(5, $lines);
        $first_line = explode(" - ", $lines[0]);
        $datetime = explode(" ", $first_line[0]);
        $time = explode(":", $datetime[0]);
        $date = explode("/", $datetime[1]);
        $this->assertEquals(Utils::pad($current_date['mon']), $date[0], "Month is not right");
        $this->assertEquals(Utils::pad($current_date['mday']), $date[1], "Day is not right");
        $this->assertEquals(Utils::pad($current_date['year']), $date[2], "Year is not right");
        $this->assertEquals(Utils::pad($current_date['hours']), $time[0], "Hours place is not right");
        $this->assertEquals(Utils::pad($current_date['minutes']), $time[1], "Minutes place is not right");
        $this->assertEquals(2, strlen($time[2]));
        if (intval($time[2]) < 10) {
            $this->assertStringStartsWith('0', $time[2]);
        }

        $this->assertEquals($level, $first_line[1], "Level is wrong");
        $this->assertEquals($message, $lines[1], "Message is wrong");

        $this->assertEquals("URL: https://localhost/index.php?test=1", $lines[2], "URL is wrong");
        $this->assertEquals("=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=", $lines[3], "Divider is wrong");
        $this->assertEmpty($lines[4]);
    }

    public function testInvalidDirectory() {
        $log_path = Logger::getLogPath();
        Logger::setLogPath("/invalid");
        $this->assertEquals($log_path, Logger::getLogPath());
    }

    public function testAccessLog() {
        $current_date = getdate(time());
        $_SERVER['REMOTE_ADDR'] = "127.0.0.1";
        $_SERVER['HTTP_USER_AGENT'] = "PHPUnit";
        Logger::logAccess("test", "action");
        $file = file_get_contents($this->access);
        $lines = explode("\n", $file);
        $this->assertCount(2, $lines);
        $this->assertEmpty($lines[1]);
        $line = explode(" | ", $lines[0]);
        $datetime = explode(" ", $line[0]);
        $time = explode(":", $datetime[0]);
        $date = explode("/", $datetime[1]);
        $this->assertEquals(Utils::pad($current_date['mon']), $date[0], "Month is not right");
        $this->assertEquals(Utils::pad($current_date['mday']), $date[1], "Day is not right");
        $this->assertEquals(Utils::pad($current_date['year']), $date[2], "Year is not right");
        $this->assertEquals(Utils::pad($current_date['hours']), $time[0], "Hours place is not right");
        $this->assertEquals(Utils::pad($current_date['minutes']), $time[1], "Minutes place is not right");
        $this->assertEquals(2, strlen($time[2]));
        if (intval($time[2]) < 10) {
            $this->assertStringStartsWith('0', $time[2]);
        }
        $this->assertEquals("test", $line[1]);
        $this->assertEquals("127.0.0.1", $line[2]);
        $this->assertEquals("action", $line[3]);
        $this->assertEquals("PHPUnit", $line[4]);
    }
}

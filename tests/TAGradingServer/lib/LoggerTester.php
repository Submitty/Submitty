<?php

use \lib\Logger;
use \lib\Functions;

class LoggerTester extends \PHPUnit_Framework_TestCase {
    private static $file_name;
    
    public static function setUpBeforeClass() {
        Logger::$log_path = __DIR__."/logs/log_test/";
        Functions::createDir(__DIR__."/logs/log_test");
        Functions::emptyDir(__DIR__."/logs/log_test");
        $date = getdate(time());
        LoggerTester::$file_name = __DIR__."/logs/log_test/".
            $date['year'].Functions::pad($date['mon']).Functions::pad($date['mday']).".txt";
        
        $_SERVER['HTTP_HOST'] = "localhost";
        $_SERVER['HTTPS'] = true;
        $_SERVER['REQUEST_URI'] = "index.php?test=1";
    }
    
    public static function tearDownAfterClass() {
        Functions::recursiveRmdir(__DIR__."/logs/log_test");
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
        $this->assertStringEqualsFile(LoggerTester::$file_name, 
                                      LoggerTester::generateMessage("DEBUG", "Debug Message"));
    }
    
    public function testLoggerInfo() {
        $this->assertFileNotExists(LoggerTester::$file_name);
        Logger::info("Info Message");
        $this->assertStringEqualsFile(LoggerTester::$file_name,
                                      LoggerTester::generateMessage("INFO", "Info Message"));
    }
    
    public function testLoggerWarn() {
        $this->assertFileNotExists(LoggerTester::$file_name);
        Logger::warn("Warn Message");
        $this->assertStringEqualsFile(LoggerTester::$file_name,
                                      LoggerTester::generateMessage("WARN", "Warn Message"));
    }
    
    public function testLoggerError() {
        $this->assertFileNotExists(LoggerTester::$file_name);
        Logger::error("Error Message");
        $this->assertStringEqualsFile(LoggerTester::$file_name,
                                      LoggerTester::generateMessage("ERROR", "Error Message"));
    }
    
    public function testLoggerFatalError() {
        $this->assertFileNotExists(LoggerTester::$file_name);
        Logger::fatal("Fatal Message");
        $this->assertStringEqualsFile(LoggerTester::$file_name,
                                      LoggerTester::generateMessage("FATAL ERROR", "Fatal Message"));
    }
    
    public function testNoLogPath() {
        $log_path = Logger::$log_path;
        Logger::$log_path = null;
        Logger::fatal("Should not happen");
        $this->assertFileNotExists(LoggerTester::$file_name);
        Logger::$log_path = $log_path;
    }
    
    public static function generateMessage($level, $message) {
        $date = getdate(time());
        $return = Functions::pad($date['mday'])."/".Functions::pad($date['mon'])."/".Functions::pad($date['year']);
        $return .= " ".Functions::pad($date['hours']).":".Functions::pad($date['minutes']).":";
        $return .= Functions::pad($date['seconds'])." - ".$level."\n".$message."\n";
        $return .= "URL: https://localhost/index.php?test=1\n";
        $return .= "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
        
        return $return;
    }
}
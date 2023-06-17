<?php

namespace tests\app\libraries;

use app\exceptions\AuthenticationException;
use app\exceptions\BaseException;
use app\libraries\ExceptionHandler;
use app\libraries\FileUtils;
use app\libraries\Logger;
use app\libraries\Utils;

class ExceptionHandlerTester extends \PHPUnit\Framework\TestCase {
    public function testClassVariables() {
        $class = new \ReflectionClass('app\libraries\ExceptionHandler');
        $properties = $class->getProperties();
        for ($i = 0; $i < count($properties); $i++) {
            $properties[$i]->setAccessible(true);
        }
        $this->assertEquals("log_exceptions", $properties[0]->getName());
        $this->assertFalse($properties[0]->getValue());
        $this->assertEquals("display_exceptions", $properties[1]->getName());
        $this->assertFalse($properties[1]->getValue());
    }

    public function testThrowServerException() {
        $message = ExceptionHandler::handleException(new \RuntimeException("test"));
        $this->assertEquals("An exception was thrown. Please contact an administrator about what you were doing that caused this exception.\n", $message);
    }

    public function testThrowRuntimeException() {
        ExceptionHandler::setDisplayExceptions(true);
        $this->assertRegExp("/Message:\ntest\n\n/", ExceptionHandler::handleException(new \RuntimeException("test")));
        ExceptionHandler::setDisplayExceptions(false);
    }

    public function testExceptionHandlerLog() {
        $tmp_dir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
        $this->assertTrue(FileUtils::createDir($tmp_dir));
        $this->assertTrue(FileUtils::createDir(FileUtils::joinPaths($tmp_dir, 'site_errors')));
        Logger::setLogPath($tmp_dir);

        $date = getdate(time());
        $filename = $date['year'] . Utils::pad($date['mon']) . Utils::pad($date['mday']) . '.log';
        ExceptionHandler::setDisplayExceptions(false);
        ExceptionHandler::setLogExceptions(true);
        ExceptionHandler::handleException(new BaseException("test", ["test" => "b", "test2" => ['a','c']]));
        $file = FileUtils::joinPaths($tmp_dir, 'site_errors', $filename);
        $this->assertFileExists($file);
        $actual = file_get_contents($file);
        $this->assertRegExp('/[0-9]{2}\:[0-9]{2}\:[0-9]{2}\ [0-9]{2}\/[0-9]{2}\/[0-9]{4} \- FATAL ERROR\napp.+/', $actual);
        $this->assertRegExp('/Extra Details:\n\ttest: b\n\ttest2:\n\t\ta\n\t\tc/', $actual);
        ExceptionHandler::setLogExceptions(false);
        $this->assertTrue(FileUtils::recursiveRmdir($tmp_dir));
    }

    private function authenticate($username, $password) {
        throw new AuthenticationException($username . "  " . $password);
    }

    public function testScrubPassword() {
        try {
            $this->authenticate("test", "test");
            $this->fail("Should have thrown exception");
        }
        catch (AuthenticationException $e) {
            ExceptionHandler::setDisplayExceptions(true);
            $message = ExceptionHandler::handleException($e);
            $this->assertRegExp("/Stack Trace:\n#0 (.*)\/site\/tests\/app\/libraries\/ExceptionHandlerTester\.php\(62\): tests\\\app\\\libraries\\\ExceptionHandlerTester\-\>authenticate\(\)\n/", $message);
        }
    }

    public function testDisplayJustExceptionMessage() {
        ExceptionHandler::setDisplayExceptions(false);
        $exception = new BaseException("exception message");
        $exception->setDisplayMessage(true);
        $message = ExceptionHandler::handleException($exception);
        $this->assertStringContainsString("exception message", $message);
        $this->assertStringNotContainsString("Stack Trace", $message);
    }
}

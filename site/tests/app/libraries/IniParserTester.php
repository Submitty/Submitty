<?php

namespace tests\app\libraries;

use app\libraries\IniParser;
use PHPUnit\Runner\Exception;

class IniParserTester extends \PHPUnit\Framework\TestCase {
    /**
     * Test that an array writes correctly and is then read back exactly the same
     * @param $writeData
     * @param string $message
     */
    private static function assertReadWriteEquals($writeData, $message = "") {
        $tmpFile = tempnam(sys_get_temp_dir(), "iniparsetest");
        unlink($tmpFile); //Because tempnam creates the file as well

        try {
            IniParser::writeFile($tmpFile, $writeData);
            $conts = file_get_contents($tmpFile);
            $readData = IniParser::readFile($tmpFile);
        } finally {
            unlink($tmpFile); //Clean up
        }

        self::assertNotFalse($readData, "Written contents: " . $conts);
        self::assertEquals($writeData, $readData, "Written contents: " . $conts);
    }

    /**
     * @expectedException \app\exceptions\FileNotFoundException
     * @expectedExceptionMessage Could not find ini file to parse: invalid_file
     */
    public function testNonExistFile() {
        IniParser::readFile("invalid_file");
    }

    public function testBasicUsage() {
        self::assertReadWriteEquals([
            "test" => [
                "hello" => "world"
            ]
        ]);
        self::assertReadWriteEquals([
            "test" => [
                "hello" => "world",
                "foo" => "bar"
            ]
        ]);
        self::assertReadWriteEquals([
            "test" => [
                "hello" => "world"
            ],
            "foo" => [
                "hello" => "world"
            ]
        ]);
    }

    public function testWeirderStrings()
    {
        self::assertReadWriteEquals([
            "foo" => "bar"
        ]);
        self::assertReadWriteEquals([
            "foo" => "bar",
            "foO" => "Bar",
            "FoO" => "BAr",
            "fOO" => "BaRr"
        ]);
        self::assertReadWriteEquals([
            "foo" => "ba\"r"
        ]);
        self::assertReadWriteEquals([
            "foo" => "ba\'r"
        ]);
        self::assertReadWriteEquals([
            "foo" => ""
        ]);
        self::assertReadWriteEquals([
            "foo" => "bar\n"
        ]);
        self::assertReadWriteEquals([
            "foo" => "bar\r"
        ]);
        self::assertReadWriteEquals([
            "foo" => "bar\t"
        ]);
        self::assertReadWriteEquals([
            "foo" => "bar\\\\\\\\\\\\\\\\\\\\\n"
        ]);
    }
    public function testCRLF() {
        // Pretty much directly from #2024
        self::assertReadWriteEquals([
            "course_details" => [
                "zero_rubric_grades" => false,
                "upload_message" => "By clicking \"Submit\" you are confirming that you have read, understand, and agree to follow the Academic Integrity Policy.<br />\r\n<br />\r\nwhy",
                "keep_previous_files" => false
            ]
        ]);
    }
}

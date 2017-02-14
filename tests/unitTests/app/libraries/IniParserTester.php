<?php

namespace unitTests\app\libraries;

use app\libraries\IniParser;

class IniParserTester extends \PHPUnit_Framework_TestCase {
    /**
     * @expectedException \app\exceptions\FileNotFoundException
     * @expectedExceptionMessage Could not find ini file to parse: invalid_file
     */
    public function testNonExistFile() {
        IniParser::readFile("invalid_file");
    }
}
<?php

namespace tests\app\libraries;

use app\libraries\IniParser;

class IniParserTester extends \PHPUnit\Framework\TestCase {
    /**
     * Test that an array writes correctly and is then read back exactly the same
     * @param $writeData
     * @param string $message
     */
    private function assertReadWriteEquals($writeData) {
        $tmpFile = tempnam(sys_get_temp_dir(), "iniparsetest");
        unlink($tmpFile); //Because tempnam creates the file as well

        try {
            IniParser::writeFile($tmpFile, $writeData);
            $conts = file_get_contents($tmpFile);
            $readData = IniParser::readFile($tmpFile);
        } finally {
            //Clean up
            if (is_file($tmpFile)) {
                unlink($tmpFile);
            }
        }

        $this->assertEquals($writeData, $readData, "Written contents: " . $conts);
    }

    /**
     * @expectedException \app\exceptions\FileNotFoundException
     * @expectedExceptionMessage Could not find ini file to parse: invalid_file
     */
    public function testNonExistFile() {
        IniParser::readFile("invalid_file");
    }

    /**
     * @dataProvider provider
     */
    public function testWriteFileReadFile($data) {
        $this->assertReadWriteEquals($data);
    }

    public function provider() {
        return [
            [
                [
                    "test" => [
                        "hello" => "world",
                        "foo" => "bar"
                    ]
                ]
            ],
            [
                [
                    "test" => [
                        "hello" => "world"
                    ],
                    "foo" => [
                        "hello" => "world"
                    ]
                ]
            ],
            [
                [
                    "foo" => "bar"
                ]
            ],
            [
                [
                    "foo" => "bar",
                    "foO" => "Bar",
                    "FoO" => "BAr",
                    "fOO" => "BaRr"
                ]
            ],
            [
                [
                    "foo" => "ba\"r"
                ]
            ],
            [
                [
                    "foo" => "ba\'r"
                ]
            ],
            [
                [
                    "foo" => ""
                ]
            ],
            [
                [
                    "foo" => "bar\n"
                ]
            ],
            [
                [
                    "foo" => "bar\r"
                ]
            ],
            [
                [
                    "foo" => "bar\t"
                ]
            ],
            [
                [
                    "foo" => "bar\\\\\\\\\\\\\\\\\\\\\n"
                ]
            ],
            [
                [
                    // Pretty much directly from #2024
                    "course_details" => [
                        "zero_rubric_grades" => false,
                        "upload_message" => "By clicking \"Submit\" you are confirming that you have read, understand, and agree to follow the Academic Integrity Policy.<br />\r\n<br />\r\nwhy",
                        "keep_previous_files" => false
                    ]
                ]
            ]
        ];
    }
}

<?php

declare(strict_types=1);

namespace tests\app\libraries\plagiarism;

use app\libraries\FileUtils;
use app\libraries\plagiarism\Interval;
use app\libraries\plagiarism\PlagiarismUtils;
use app\libraries\plagiarism\Stack;
use app\libraries\Utils;

class PlagiarismUtilsTester extends \PHPUnit\Framework\TestCase {
    public function testConstructIntervals(): void
    {
        // TODO: Write *correct* tests for the PlagiarismUtils file
        $testData = [
            [
                "end" => 5,
                "others" => [
                    [
                        "matchingpositions" => [
                            ["end" => 5, "start" => 1]
                        ],
                        "username" => "persona",
                        "version" => 2
                    ]
                ],
                "start" => 1,
                "type" => "match"
            ],
            [
                "end" => 27,
                "others" => [
                    [
                        "matchingpositions" => [
                            ["end" => 7, "start" => 3]
                        ],
                        "username" => "personc",
                        "version" => 2
                    ]
                ],
                "start" => 23,
                "type" => "match"
            ],
            [
                "end" => 7,
                "others" => [
                    [
                        "matchingpositions" => [
                            ["end" => 7, "start" => 3]
                        ],
                        "username" => "persona",
                        "version" => 2
                    ],
                    [
                        "matchingpositions" => [
                            ["end" => 9, "start" => 5]
                        ],
                        "username" => "personb",
                        "version" => 2
                    ],
                    [
                        "matchingpositions" => [
                            ["end" => 7, "start" => 3]
                        ],
                        "username" => "personc",
                        "version" => 2
                    ]
                ],
                "start" => 3,
                "type" => "match"
            ],
            [
                "end" => 6,
                "others" => [
                    [
                        "matchingpositions" => [
                            ["end" => 6, "start" => 2]
                        ],
                        "username" => "persona",
                        "version" => 2
                    ]
                ],
                "start" => 2,
                "type" => "match"
            ],
        ];
    }
}

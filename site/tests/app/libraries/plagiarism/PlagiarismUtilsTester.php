<?php

declare(strict_types=1);

namespace tests\app\libraries\plagiarism;

use app\libraries\FileUtils;
use app\libraries\plagiarism\Interval;
use app\libraries\plagiarism\PlagiarismUtils;
use app\libraries\plagiarism\Stack;
use app\libraries\plagiarism\Submission;
use app\libraries\Utils;

class PlagiarismUtilsTester extends \PHPUnit\Framework\TestCase {
    public function testConstructIntervals(): void {
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

        try {
            $randomDir = FileUtils::joinPaths(sys_get_temp_dir(), Utils::generateRandomString());
            FileUtils::createDir($randomDir);
            $testFile = FileUtils::joinPaths($randomDir, 'test.json');
            FileUtils::writeJsonFile($testFile, $testData);

            $expected = [
                new Interval(1, 5, "match"),
                new Interval(2, 6, "match"),
                new Interval(3, 7, "match"),
                new Interval(23, 27, "match")
            ];
            $expected[0]->addUser(new Submission(
                'persona',
                2,
                [["end" => 5, "start" => 1]],
                1,
                5
            ));
            $expected[1]->addUser(new Submission(
                'persona',
                2,
                [['end' => 6, 'start' => 2]],
                2,
                6
            ));
            $expected[2]->addUser(new Submission(
                'persona',
                2,
                [['end' => 7, 'start' => 3]],
                3,
                7
            ));
            $expected[2]->addUser(new Submission(
                'personb',
                2,
                [['end' => 9, 'start' => 5]],
                3,
                7
            ));
            $expected[2]->addUser(new Submission(
                'personc',
                2,
                [['end' => 7, 'start' => 3]],
                3,
                7
            ));
            $expected[3]->addUser(new Submission(
                'personc',
                2,
                [['end' => 7, 'start' => 3]],
                23,
                27
            ));
            $this->assertEquals(
                $expected,
                PlagiarismUtils::constructIntervals($testFile)
            );
        }
        finally {
            FileUtils::recursiveRmdir($randomDir);
        }
    }

    public function testMergeIntervals(): void {
        $intervalArray = [
            new Interval(1, 5, "match"),
            new Interval(2, 6, "match"),
            new Interval(3, 7, "match"),
            new Interval(23, 27, "match")
        ];
        $intervalArray[0]->addUser(new Submission(
            'persona',
            2,
            [["end" => 5, "start" => 1]],
            1,
            5
        ));
        $intervalArray[1]->addUser(new Submission(
            'persona',
            2,
            [['end' => 6, 'start' => 2]],
            2,
            6
        ));
        $intervalArray[2]->addUser(new Submission(
            'persona',
            2,
            [['end' => 7, 'start' => 3]],
            3,
            7
        ));
        $intervalArray[2]->addUser(new Submission(
            'personb',
            2,
            [['end' => 9, 'start' => 5]],
            3,
            7
        ));
        $intervalArray[2]->addUser(new Submission(
            'personc',
            2,
            [['end' => 7, 'start' => 3]],
            3,
            7
        ));
        $intervalArray[3]->addUser(new Submission(
            'personc',
            2,
            [['end' => 7, 'start' => 3]],
            23,
            27
        ));

        $expected = [];
        $expected[] = $intervalArray[3];
        $interval = new Interval(1, 7, "match");
        $interval->addUser(new Submission(
            'persona',
            2,
            [['end' => 5, 'start' => 1], ['end' => 6, 'start' => 2], ['end' => 7, 'start' => 3]],
            1,
            5
        ));
        $interval->addUser(new Submission(
            'personb',
            2,
            [['end' => 9, 'start' => 5]],
            3,
            7
        ));
        $interval->addUser(new Submission(
            'personc',
            2,
            [['end' => 7, 'start' => 3]],
            3,
            7
        ));
        $expected[] = $interval;
        $this->assertEquals($expected, PlagiarismUtils::mergeIntervals($intervalArray)->toArray());
    }
}

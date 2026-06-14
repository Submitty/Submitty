<?php

declare(strict_types=1);

namespace tests\app\libraries\grading_cluster;

use app\libraries\grading_cluster\DummySplitAlgorithm;
use PHPUnit\Framework\TestCase;

class DummySplitAlgorithmTester extends TestCase {
    public function testRun(): void {
        $submitters = [
            ['user_id' => 'alice'],
            ['user_id' => 'bob'],
            ['team_id' => 'team_alpha'],
            ['user_id' => 'martha'],
            ['user_id' => 'nancy'],
            ['team_id' => 'zebra'],
        ];

        $algorithm = new DummySplitAlgorithm();
        $result = $algorithm->run($submitters);

        $expected_cluster_a = [
            ['user_id' => 'alice'],
            ['user_id' => 'bob'],
            ['user_id' => 'martha'],
        ];

        $expected_cluster_b = [
            ['team_id' => 'team_alpha'],
            ['user_id' => 'nancy'],
            ['team_id' => 'zebra'],
        ];

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('Cluster A (A-M)', $result);
        $this->assertArrayHasKey('Cluster B (N-Z)', $result);

        $this->assertEquals($expected_cluster_a, $result['Cluster A (A-M)']);
        $this->assertEquals($expected_cluster_b, $result['Cluster B (N-Z)']);
    }
}
